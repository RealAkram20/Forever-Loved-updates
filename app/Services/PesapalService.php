<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PesapalService
{
    private string $baseUrl;
    private string $consumerKey;
    private string $consumerSecret;
    private ?string $token = null;
    private ?int $tokenExpiresAt = null;

    public function __construct()
    {
        $env = SystemSetting::get('payments.pesapal_environment', 'sandbox');
        $this->baseUrl = $env === 'live'
            ? 'https://pay.pesapal.com/v3'
            : 'https://cybqa.pesapal.com/pesapalv3';
        $this->consumerKey = SystemSetting::get('payments.pesapal_consumer_key', '');
        $this->consumerSecret = SystemSetting::get('payments.pesapal_consumer_secret', '');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->consumerKey) && ! empty($this->consumerSecret);
    }

    public function isEnabled(): bool
    {
        return (bool) SystemSetting::get('payments.pesapal_enabled', false) && $this->isConfigured();
    }

    /**
     * Get a valid Bearer token. Caches token until near expiry.
     */
    public function getToken(): ?string
    {
        if ($this->token && $this->tokenExpiresAt && time() < $this->tokenExpiresAt - 60) {
            return $this->token;
        }

        $response = Http::acceptJson()
            ->contentType('application/json')
            ->post("{$this->baseUrl}/api/Auth/RequestToken", [
                'consumer_key' => $this->consumerKey,
                'consumer_secret' => $this->consumerSecret,
            ]);

        $data = $response->json();
        if (! $response->successful() || empty($data['token'])) {
            Log::error('Pesapal auth failed', ['response' => $response->json()]);
            return null;
        }

        $this->token = $data['token'];
        if (! empty($data['expiryDate'])) {
            $this->tokenExpiresAt = strtotime($data['expiryDate']);
        } else {
            $this->tokenExpiresAt = time() + 300; // 5 min default
        }

        return $this->token;
    }

    /**
     * Register IPN URL with Pesapal. Returns the IPN ID on success.
     */
    public function registerIpn(string $url, string $method = 'GET'): ?string
    {
        $token = $this->getToken();
        if (! $token) {
            return null;
        }

        $response = Http::acceptJson()
            ->contentType('application/json')
            ->withToken($token)
            ->post("{$this->baseUrl}/api/URLSetup/RegisterIPN", [
                'url' => $url,
                'ipn_notification_type' => strtoupper($method),
            ]);

        $data = $response->json();
        if (! $response->successful() || empty($data['ipn_id'])) {
            Log::error('Pesapal IPN registration failed', ['response' => $data]);
            return null;
        }

        return $data['ipn_id'];
    }

    /**
     * Submit an order to Pesapal. Returns redirect_url on success.
     *
     * @param  array{email_address?: string, phone_number?: string, first_name?: string, last_name?: string, country_code?: string}  $billingAddress
     */
    public function submitOrder(
        string $merchantReference,
        float $amount,
        string $currency,
        string $description,
        string $callbackUrl,
        $billingAddress,
        ?string $cancellationUrl = null
    ): array {
        $ipnId = SystemSetting::get('payments.pesapal_ipn_id', '');
        if (empty($ipnId)) {
            Log::error('Pesapal IPN ID not configured');
            return ['success' => false, 'error' => 'IPN not configured'];
        }

        $token = $this->getToken();
        if (! $token) {
            return ['success' => false, 'error' => 'Authentication failed'];
        }

        $payload = [
            'id' => $merchantReference,
            'currency' => $currency,
            'amount' => round($amount, 2),
            'description' => substr($description, 0, 100),
            'callback_url' => $callbackUrl,
            'notification_id' => $ipnId,
            'redirect_mode' => 'PARENT_WINDOW',
            'billing_address' => array_merge([
                'email_address' => $billingAddress['email_address'] ?? '',
                'phone_number' => $billingAddress['phone_number'] ?? '',
                'country_code' => $billingAddress['country_code'] ?? 'KE',
                'first_name' => $billingAddress['first_name'] ?? '',
                'middle_name' => '',
                'last_name' => $billingAddress['last_name'] ?? '',
                'line_1' => '',
                'line_2' => '',
                'city' => '',
                'state' => '',
                'postal_code' => '',
                'zip_code' => '',
            ], $billingAddress),
        ];

        if ($cancellationUrl) {
            $payload['cancellation_url'] = $cancellationUrl;
        }

        $response = Http::acceptJson()
            ->contentType('application/json')
            ->withToken($token)
            ->post("{$this->baseUrl}/api/Transactions/SubmitOrderRequest", $payload);

        $data = $response->json();

        if (! $response->successful()) {
            Log::error('Pesapal submit order failed', ['response' => $data]);
            return [
                'success' => false,
                'error' => $data['error']['message'] ?? $data['message'] ?? 'Request failed',
            ];
        }

        if (empty($data['redirect_url'])) {
            return ['success' => false, 'error' => 'No redirect URL received'];
        }

        return [
            'success' => true,
            'redirect_url' => $data['redirect_url'],
            'order_tracking_id' => $data['order_tracking_id'] ?? null,
            'merchant_reference' => $data['merchant_reference'] ?? $merchantReference,
        ];
    }

    /**
     * Get transaction status by OrderTrackingId.
     */
    public function getTransactionStatus(string $orderTrackingId): ?array
    {
        $token = $this->getToken();
        if (! $token) {
            return null;
        }

        $response = Http::acceptJson()
            ->contentType('application/json')
            ->withToken($token)
            ->get("{$this->baseUrl}/api/Transactions/GetTransactionStatus", [
                'orderTrackingId' => $orderTrackingId,
            ]);

        $data = $response->json();
        if (! $response->successful()) {
            Log::error('Pesapal get status failed', ['response' => $data]);
            return null;
        }

        return $data;
    }

    /**
     * Check if payment status is completed.
     */
    public function isPaymentCompleted(?array $status): bool
    {
        return $status && ($status['payment_status_description'] ?? '') === 'Completed';
    }

    /**
     * Check if payment status is failed.
     */
    public function isPaymentFailed(?array $status): bool
    {
        return $status && in_array($status['payment_status_description'] ?? '', ['Failed', 'Invalid', 'Reversed']);
    }
}
