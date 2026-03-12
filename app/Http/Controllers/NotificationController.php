<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Show the notifications page.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = Notification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('pages.notifications.index', [
            'title' => 'Notifications',
            'notifications' => $notifications,
            'unreadCount' => NotificationService::unreadCount($user->id),
        ]);
    }

    /**
     * Get notifications for the header dropdown (AJAX).
     */
    public function dropdown(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'notifications' => NotificationService::getRecentForUser($user->id),
            'unread_count' => NotificationService::unreadCount($user->id),
        ]);
    }

    /**
     * Mark a single notification as read (AJAX).
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'unread_count' => NotificationService::unreadCount($request->user()->id),
        ]);
    }

    /**
     * Mark all notifications as read (AJAX).
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'unread_count' => 0,
        ]);
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'unread_count' => NotificationService::unreadCount($request->user()->id),
        ]);
    }

    /**
     * Store a push subscription (for Web Push).
     */
    public function subscribePush(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|url',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
            'contentEncoding' => 'nullable|string|in:aesgcm,aes128gcm',
        ]);

        // Modern Chrome uses aes128gcm; default to it when not specified
        $encoding = $validated['contentEncoding'] ?? 'aes128gcm';

        $request->user()->pushSubscriptions()->updateOrCreate(
            ['endpoint' => $validated['endpoint']],
            [
                'p256dh_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => $encoding,
            ]
        );

        return response()->json(['success' => true]);
    }

    /**
     * Send a test push notification to the current user (for debugging).
     */
    public function testPush(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscriptions = $user->pushSubscriptions;

        if ($subscriptions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Enable push in this browser first—allow when the popup appears, or click Enable in the bell dropdown.',
            ], 400);
        }

        try {
            $reports = NotificationService::sendPushToSubscriptions(
                $subscriptions,
                'Test Push Notification',
                'If you see this, push notifications are working!',
                url('/dashboard'),
                'test-' . time()
            );

            $allOk = collect($reports)->every(fn ($r) => $r['success']);
            $failedReason = collect($reports)->firstWhere('success', false)['reason'] ?? 'Unknown error';

            if (!$allOk) {
                \Illuminate\Support\Facades\Log::warning('Test push failed', [
                    'user_id' => $user->id,
                    'reports' => $reports,
                ]);
            }

            return response()->json([
                'success' => $allOk,
                'message' => $allOk ? 'Test push sent. Check your browser.' : 'Push failed: ' . $failedReason,
                'reports' => $reports,
            ], $allOk ? 200 : 500);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Test push failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $msg = $e->getMessage();
            if (str_contains($msg, 'GMP') || str_contains($msg, 'BCMath')) {
                $msg = 'Push requires the BCMath PHP extension. In Hostinger: Advanced → PHP Configuration → PHP Extensions → enable BCMath. After enabling, wait a few minutes or restart PHP.';
            } else {
                $msg = 'Push failed: ' . $msg;
            }
            return response()->json([
                'success' => false,
                'message' => $msg,
            ], 500);
        }
    }

    /**
     * Remove a push subscription.
     */
    public function unsubscribePush(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|url',
        ]);

        $request->user()->pushSubscriptions()
            ->where('endpoint', $validated['endpoint'])
            ->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Reset all push subscriptions for the current user.
     * Use when subscription was created with old VAPID keys (e.g. after DB reset).
     */
    public function resetPush(Request $request): JsonResponse
    {
        $request->user()->pushSubscriptions()->delete();
        $request->session()->forget('admin_push_onboarding_dismissed');

        return response()->json(['success' => true]);
    }
}
