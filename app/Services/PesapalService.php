<?php

namespace App\Services;

use App\Models\Reseller;
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
    private ?Reseller $reseller = null;

    public function __construct()
    {
        $env = SystemSetting::get('payments.pesapal_environment', 'sandbox');
        $this->baseUrl = $env === 'live'
            ? 'https://pay.pesapal.com/v3'
            : 'https://cybqa.pesapal.com/pesapalv3';
        $this->consumerKey = SystemSetting::get('payments.pesapal_consumer_key', '');
        $this->consumerSecret = SystemSetting::get('payments.pesapal_consumer_secret', '');
    }

    /**
     * Client money for a reseller's memorials flows through the reseller's own Pesapal
     * account, not the platform's. When the given reseller has their own credentials
     * configured, use them; otherwise fall through to the platform's global credentials
     * (SystemSetting) so platform-direct checkouts are unaffected.
     */
    public static function forReseller(?Reseller $reseller): self
    {
        $service = new self();

        if ($reseller && $reseller->pesapal_enabled && $reseller->pesapal_consumer_key && $reseller->pesapal_consumer_secret) {
            $service->reseller = $reseller;
            $service->baseUrl = $reseller->pesapal_environment === 'live'
                ? 'https://pay.pesapal.com/v3'
                : 'https://cybqa.pesapal.com/pesapalv3';
            $service->consumerKey = $reseller->pesapal_consumer_key;
            $service->consumerSecret = $reseller->pesapal_consumer_secret;
        }

        return $service;
    }

    public function isConfigured(): bool
    {
        return ! empty($this->consumerKey) && ! empty($this->consumerSecret);
    }

    public function isEnabled(): bool
    {
        if ($this->reseller) {
            return $this->reseller->pesapal_enabled && $this->isConfigured();
        }

        return (bool) SystemSetting::get('payments.pesapal_enabled', false) && $this->isConfigured();
    }

    /**
     * Get URL for Pesapal callback/cancellation. Uses PESAPAL_CALLBACK_BASE_URL when set,
     * otherwise uses APP_URL (e.g. your Hostinger domain).
     */
    public function getCallbackUrl(string $routeName, array $params = []): string
    {
        $base = config('services.pesapal.callback_base_url');
        if (empty($base)) {
            return route($routeName, $params);
        }
        $path = route($routeName, $params, false);
        return rtrim($base, '/') . $path;
    }

    /**
     * Base HTTP client with optional SSL verification (for local dev on Windows/XAMPP).
     * Timeouts are set here so no Pesapal call can hang a web request or the IPN endpoint.
     */
    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        $client = Http::acceptJson()->contentType('application/json')
            ->connectTimeout(10)
            ->timeout(30);
        if (! config('services.pesapal.verify_ssl', true)) {
            $client = $client->withOptions(['verify' => false]);
        }
        return $client;
    }

    /**
     * Get a valid Bearer token. Caches token until near expiry.
     */
    public function getToken(): ?string
    {
        if ($this->token && $this->tokenExpiresAt && time() < $this->tokenExpiresAt - 60) {
            return $this->token;
        }

        try {
            $response = $this->http()
                ->post("{$this->baseUrl}/api/Auth/RequestToken", [
                    'consumer_key' => $this->consumerKey,
                    'consumer_secret' => $this->consumerSecret,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Pesapal auth unreachable', ['error' => $e->getMessage()]);
            return null;
        }

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
     * The public URL Pesapal calls with payment notifications.
     */
    public function getIpnUrl(): string
    {
        return $this->getCallbackUrl('payment.ipn');
    }

    /**
     * Register our IPN URL with Pesapal (POST /api/URLSetup/RegisterIPN) and store the
     * returned ipn_id, which SubmitOrderRequest requires as notification_id.
     *
     * @return array{success: bool, ipn_id?: string, url?: string, error?: string}
     */
    public function registerIpn(?string $url = null, string $method = 'GET'): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Add your Pesapal consumer key and secret first.'];
        }

        $url = $url ?: $this->getIpnUrl();

        if (! preg_match('#^https?://#i', $url) || $this->isLocalUrl($url)) {
            return ['success' => false, 'error' => "Pesapal cannot reach {$url}. Set APP_URL (or PESAPAL_CALLBACK_BASE_URL) to your public domain."];
        }

        $token = $this->getToken();
        if (! $token) {
            return ['success' => false, 'error' => 'Could not authenticate with Pesapal. Check the consumer key, secret and environment.'];
        }

        try {
            $response = $this->http()
                ->withToken($token)
                ->post("{$this->baseUrl}/api/URLSetup/RegisterIPN", [
                    'url' => $url,
                    'ipn_notification_type' => strtoupper($method) === 'POST' ? 'POST' : 'GET',
                ]);
        } catch (\Throwable $e) {
            Log::warning('Pesapal RegisterIPN unreachable', ['url' => $url, 'error' => $e->getMessage()]);

            return ['success' => false, 'error' => 'Could not reach Pesapal. Check your connection and try again.'];
        }

        $data = $response->json();

        if (! $response->successful() || empty($data['ipn_id'])) {
            Log::error('Pesapal IPN registration failed', ['url' => $url, 'response' => $data]);

            return ['success' => false, 'error' => $this->errorMessage($data, 'IPN registration failed.')];
        }

        if ($this->reseller) {
            $this->reseller->update(['pesapal_ipn_id' => $data['ipn_id']]);
        } else {
            SystemSetting::set('payments.pesapal_ipn_id', $data['ipn_id']);
        }

        return [
            'success' => true,
            'ipn_id' => $data['ipn_id'],
            'url' => $data['url'] ?? $url,
        ];
    }

    /**
     * All IPN URLs registered on this merchant account (GET /api/URLSetup/GetIpnList).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getIpnList(): array
    {
        $token = $this->getToken();
        if (! $token) {
            return [];
        }

        try {
            $response = $this->http()
                ->withToken($token)
                ->get("{$this->baseUrl}/api/URLSetup/GetIpnList");
        } catch (\Throwable $e) {
            Log::warning('Pesapal GetIpnList unreachable', ['error' => $e->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            Log::error('Pesapal GetIpnList failed', ['response' => $response->json()]);

            return [];
        }

        $data = $response->json();

        return is_array($data) ? $data : [];
    }

    /**
     * The stored IPN ID, registering one on the fly if it is missing. Keeps checkout
     * working on a fresh install without an admin first pasting an ID by hand.
     */
    public function ensureIpnId(): ?string
    {
        $ipnId = $this->reseller
            ? trim((string) $this->reseller->pesapal_ipn_id)
            : trim((string) SystemSetting::get('payments.pesapal_ipn_id', ''));
        if ($ipnId !== '') {
            return $ipnId;
        }

        $result = $this->registerIpn();

        return $result['success'] ? $result['ipn_id'] : null;
    }

    /**
     * Pesapal reports failures either as {error: {message|description}} or a bare string.
     */
    private function errorMessage($data, string $fallback): string
    {
        if (! is_array($data)) {
            return $fallback;
        }

        $error = $data['error'] ?? $data['message'] ?? null;
        if (is_array($error)) {
            $error = $error['message'] ?? $error['description'] ?? $error['code'] ?? null;
        }

        return is_string($error) && $error !== '' ? $error : $fallback;
    }

    private function isLocalUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '::1'
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.test');
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
        $ipnId = $this->ensureIpnId();
        if (empty($ipnId)) {
            Log::error('Pesapal IPN ID missing and auto-registration failed');
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

        try {
            $response = $this->http()
                ->withToken($token)
                ->post("{$this->baseUrl}/api/Transactions/SubmitOrderRequest", $payload);
        } catch (\Throwable $e) {
            Log::warning('Pesapal SubmitOrderRequest unreachable', ['merchant_reference' => $merchantReference, 'error' => $e->getMessage()]);

            return ['success' => false, 'error' => 'Could not reach the payment gateway. Please try again in a moment.'];
        }

        $data = $response->json();

        if (! $response->successful()) {
            $errorMsg = 'Request failed';
            if (is_array($data)) {
                $errorMsg = $data['error']['message'] ?? $data['error']['description'] ?? $data['message'] ?? $data['error'] ?? $errorMsg;
                if (is_array($errorMsg)) {
                    $errorMsg = $errorMsg['message'] ?? json_encode($errorMsg);
                }
            }
            Log::error('Pesapal submit order failed', ['status' => $response->status(), 'response' => $data]);
            return ['success' => false, 'error' => (string) $errorMsg];
        }

        if (empty($data['redirect_url'])) {
            Log::error('Pesapal no redirect_url', ['response' => $data]);
            return ['success' => false, 'error' => 'No redirect URL received from payment gateway'];
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

        try {
            $response = $this->http()
                ->withToken($token)
                ->get("{$this->baseUrl}/api/Transactions/GetTransactionStatus", [
                    'orderTrackingId' => $orderTrackingId,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Pesapal GetTransactionStatus unreachable', ['orderTrackingId' => $orderTrackingId, 'error' => $e->getMessage()]);

            return null;
        }

        $data = $response->json();
        if (! $response->successful()) {
            Log::error('Pesapal get status failed', ['response' => $data]);
            return null;
        }

        return $data;
    }

    /**
     * Check if payment status is completed.
     * Pesapal returns: COMPLETED, Completed, or status_code 1.
     */
    public function isPaymentCompleted(?array $status): bool
    {
        if (! $status) {
            return false;
        }
        $desc = strtoupper(trim($status['payment_status_description'] ?? ''));
        $code = $status['status_code'] ?? null;
        return $desc === 'COMPLETED' || $code === 1 || $code === '1';
    }

    /**
     * Check if payment status is failed.
     * Pesapal returns: FAILED, INVALID, REVERSED or status_code 0, 2, 3.
     */
    public function isPaymentFailed(?array $status): bool
    {
        if (! $status) {
            return false;
        }
        $desc = strtoupper(trim($status['payment_status_description'] ?? ''));
        $code = $status['status_code'] ?? null;
        return in_array($desc, ['FAILED', 'INVALID', 'REVERSED'])
            || in_array($code, [0, 2, 3, '0', '2', '3']);
    }
}
