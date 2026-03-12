<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\PushSubscription;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class NotificationService
{
    /**
     * Send a notification to a specific user.
     * Creates a DB record and dispatches email + push if enabled.
     */
    public static function send(
        int $userId,
        string $type,
        string $title,
        string $message,
        string $icon = 'info',
        ?string $actionUrl = null,
        ?array $data = null
    ): Notification {
        $user = User::find($userId);

        $notification = Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'icon' => $icon,
            'action_url' => $actionUrl,
            'data' => $data,
        ]);

        $sendEmail = $user && $user->email_notifications_enabled && static::isEmailEnabled();
        $sendPush = $user && $user->push_notifications_enabled && static::isPushEnabled();

        if ($sendEmail) {
            try {
                static::dispatchEmail($notification);
            } catch (\Throwable $e) {
                Log::warning('Failed to send email notification', [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($sendPush) {
            try {
                static::dispatchPush($notification);
            } catch (\Throwable $e) {
                Log::warning('Failed to send push notification', [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $notification;
    }

    /**
     * Send a notification to all users with admin/super-admin roles.
     * Use for system-wide notifications (signups, payments, etc.).
     */
    public static function sendToAdmins(
        string $type,
        string $title,
        string $message,
        string $icon = 'info',
        ?string $actionUrl = null,
        ?array $data = null
    ): void {
        $adminUsers = User::role(['admin', 'super-admin'])->get();

        foreach ($adminUsers as $admin) {
            static::send($admin->id, $type, $title, $message, $icon, $actionUrl, $data);
        }
    }

    /**
     * Send a memorial-specific notification.
     * Admin: receives for all memorials. Super-admin: only for memorials they own.
     */
    public static function sendToAdminsForMemorial(
        \App\Models\Memorial $memorial,
        string $type,
        string $title,
        string $message,
        string $icon = 'info',
        ?string $actionUrl = null,
        ?array $data = null
    ): void {
        $adminUsers = User::role(['admin', 'super-admin'])->get();

        foreach ($adminUsers as $admin) {
            if ($admin->hasRole('super-admin') && $memorial->user_id !== $admin->id) {
                continue;
            }
            static::send($admin->id, $type, $title, $message, $icon, $actionUrl, $data);
        }
    }

    // ─── Email Dispatch ─────────────────────────────────────────────

    private static function isEmailEnabled(): bool
    {
        return (bool) SystemSetting::get('notifications.email_enabled', false)
            && (bool) SystemSetting::get('smtp.enabled', false)
            && !empty(SystemSetting::get('smtp.host'));
    }

    private static function dispatchEmail(Notification $notification): void
    {
        static::configureSmtp();

        $user = $notification->user;
        if (!$user || !$user->email) {
            return;
        }

        $appName = SystemSetting::get('branding.app_name', config('app.name'));
        $subject = $notification->title;

        Mail::html(
            static::buildEmailHtml($notification, $appName),
            function ($msg) use ($user, $subject, $appName) {
                $msg->to($user->email, $user->name)
                    ->subject("{$appName} - {$subject}");
            }
        );
    }

    /**
     * Dynamically override Laravel's SMTP config from SystemSettings.
     */
    private static function configureSmtp(): void
    {
        $host = SystemSetting::get('smtp.host');
        if (!$host) {
            return;
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', $host);
        Config::set('mail.mailers.smtp.port', SystemSetting::get('smtp.port', 587));
        Config::set('mail.mailers.smtp.username', SystemSetting::get('smtp.username'));
        Config::set('mail.mailers.smtp.password', SystemSetting::get('smtp.password'));

        $encryption = SystemSetting::get('smtp.encryption', 'tls');
        Config::set('mail.mailers.smtp.encryption', $encryption === 'none' ? null : $encryption);

        $fromAddress = SystemSetting::get('smtp.from_address');
        $fromName = SystemSetting::get('smtp.from_name', SystemSetting::get('branding.app_name', config('app.name')));

        if ($fromAddress) {
            Config::set('mail.from.address', $fromAddress);
            Config::set('mail.from.name', $fromName);
        }
    }

    private static function buildEmailHtml(Notification $notification, string $appName): string
    {
        $actionButton = '';
        if ($notification->action_url) {
            $actionButton = <<<HTML
            <tr>
                <td style="padding:24px 0 0;">
                    <a href="{$notification->action_url}"
                       style="display:inline-block;background:#465fff;color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;">
                        View Details
                    </a>
                </td>
            </tr>
            HTML;
        }

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"></head>
        <body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 16px;">
                <tr>
                    <td align="center">
                        <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                            <tr>
                                <td style="background:#465fff;padding:24px 32px;">
                                    <h1 style="margin:0;color:#ffffff;font-size:18px;font-weight:600;">{$appName}</h1>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:32px;">
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td>
                                                <h2 style="margin:0 0 8px;color:#1f2937;font-size:18px;font-weight:600;">{$notification->title}</h2>
                                                <p style="margin:0;color:#6b7280;font-size:15px;line-height:1.6;">{$notification->message}</p>
                                            </td>
                                        </tr>
                                        {$actionButton}
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:16px 32px;background:#f9fafb;border-top:1px solid #e5e7eb;">
                                    <p style="margin:0;color:#9ca3af;font-size:12px;text-align:center;">
                                        You received this because you have notifications enabled on {$appName}.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        HTML;
    }

    // ─── Push Notification Dispatch ─────────────────────────────────

    /**
     * Guzzle client options for WebPush. Fixes "SSL certificate problem: unable to get local issuer certificate"
     * on Windows/XAMPP by disabling verify in local env when no CA bundle is configured.
     */
    private static function getWebPushClientOptions(): array
    {
        $cacert = config('services.webpush.cacert') ?? env('CURL_CA_BUNDLE');
        if ($cacert && is_file($cacert)) {
            return ['verify' => $cacert];
        }
        if (app()->environment('local', 'development')) {
            return ['verify' => false];
        }
        return [];
    }

    private static function isPushEnabled(): bool
    {
        return (bool) SystemSetting::get('notifications.push_enabled', false)
            && !empty(SystemSetting::get('notifications.vapid_public_key'))
            && !empty(SystemSetting::get('notifications.vapid_private_key'));
    }

    private static function dispatchPush(Notification $notification): void
    {
        $subscriptions = PushSubscription::where('user_id', $notification->user_id)->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $appUrl = config('app.url', 'http://localhost');
        $actionUrl = $notification->action_url;
        if ($actionUrl && !str_starts_with($actionUrl, 'http')) {
            $actionUrl = rtrim($appUrl, '/') . '/' . ltrim($actionUrl, '/');
        }
        $actionUrl = $actionUrl ?: rtrim($appUrl, '/') . '/';

        $reports = static::sendPushToSubscriptions(
            $subscriptions,
            $notification->title,
            $notification->message,
            $actionUrl,
            $notification->type . '-' . $notification->id
        );

        foreach ($reports as $report) {
            if (!$report['success']) {
                Log::warning('Push notification failed', [
                    'notification_id' => $notification->id,
                    'endpoint' => $report['endpoint'] ?? null,
                    'reason' => $report['reason'],
                ]);
            }
        }
    }

    /**
     * Send a push payload to given subscriptions. Returns array of report data.
     * Used by dispatchPush and test push endpoint.
     */
    /**
     * Check if PHP has GMP or BCMath (required for Web Push crypto).
     */
    public static function hasPushMathExtension(): bool
    {
        return extension_loaded('gmp') || extension_loaded('bcmath');
    }

    public static function sendPushToSubscriptions(
        \Illuminate\Support\Collection $subscriptions,
        string $title,
        string $body,
        string $url,
        string $tag
    ): array {
        if ($subscriptions->isEmpty()) {
            return [];
        }

        if (! static::hasPushMathExtension()) {
            throw new \RuntimeException(
                'Push requires the BCMath or GMP PHP extension. In Hostinger: Advanced → PHP Configuration → PHP Extensions → enable BCMath. Wait a few minutes after enabling.'
            );
        }

        $appUrl = config('app.url', 'http://localhost');
        $auth = [
            'VAPID' => [
                'subject' => $appUrl,
                'publicKey' => SystemSetting::get('notifications.vapid_public_key'),
                'privateKey' => SystemSetting::get('notifications.vapid_private_key'),
            ],
        ];

        $clientOptions = static::getWebPushClientOptions();
        $webPush = new WebPush($auth, [], 30, $clientOptions);
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'icon' => rtrim($appUrl, '/') . '/images/icon-192.png',
            'badge' => rtrim($appUrl, '/') . '/images/badge-72.png',
            'url' => $url,
            'tag' => $tag,
        ]);

        foreach ($subscriptions as $sub) {
            $encoding = $sub->content_encoding ?? 'aes128gcm';
            if ($encoding === 'aesgcm' && str_contains($sub->endpoint, 'fcm.googleapis.com')) {
                $encoding = 'aes128gcm';
            }
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'publicKey' => $sub->p256dh_key,
                    'authToken' => $sub->auth_token,
                    'contentEncoding' => $encoding,
                ]),
                $payload
            );
        }

        $reports = [];
        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();
            $statusCode = $report->getResponse()?->getStatusCode();
            $vapidMismatch = $statusCode === 403 || str_contains($report->getReason(), 'VAPID');
            $shouldRemove = $report->isSubscriptionExpired() || $vapidMismatch;

            $reports[] = [
                'success' => $report->isSuccess(),
                'reason' => $report->getReason(),
                'expired' => $report->isSubscriptionExpired(),
                'endpoint' => $endpoint,
            ];

            if ($shouldRemove) {
                PushSubscription::where('endpoint', $endpoint)->delete();
            }
        }
        return $reports;
    }

    // ─── Query Helpers ──────────────────────────────────────────────

    public static function unreadCount(int $userId): int
    {
        $user = User::find($userId);
        if (!$user || !$user->in_app_notifications_enabled) {
            return 0;
        }
        return Notification::where('user_id', $userId)->whereNull('read_at')->count();
    }

    public static function getRecentForUser(int $userId, int $limit = 8): array
    {
        $user = User::find($userId);
        if (!$user || !$user->in_app_notifications_enabled) {
            return [];
        }

        $notifications = Notification::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $notifications->map(fn (Notification $n) => [
            'id' => $n->id,
            'type' => $n->type,
            'title' => $n->title,
            'message' => $n->message,
            'icon' => $n->icon,
            'action_url' => $n->action_url,
            'data' => $n->data,
            'is_read' => $n->read_at !== null,
            'time' => $n->created_at->diffForHumans(),
            'created_at' => $n->created_at->toIso8601String(),
        ])->toArray();
    }

    // ─── Notification Trigger Helpers ──────────────────────────────

    public static function notifyNewUserSignup(User $user): void
    {
        static::sendToAdmins(
            type: 'new_user_signup',
            title: 'New User Registered',
            message: "{$user->name} has created an account.",
            icon: 'user',
            actionUrl: route('users.index'),
            data: ['user_id' => $user->id, 'user_name' => $user->name, 'user_email' => $user->email],
        );
    }

    public static function notifyNewPayment(User $user, string $planName, string $amount): void
    {
        static::sendToAdmins(
            type: 'new_payment',
            title: 'New Payment Received',
            message: "{$user->name} subscribed to {$planName} ({$amount}).",
            icon: 'payment',
            actionUrl: route('settings.subscriptions'),
            data: ['user_id' => $user->id, 'plan' => $planName, 'amount' => $amount],
        );
    }

    public static function notifyPaymentCanceled(User $user, string $planName): void
    {
        static::sendToAdmins(
            type: 'payment_canceled',
            title: 'Payment Canceled',
            message: "{$user->name}'s subscription to {$planName} was canceled.",
            icon: 'payment',
            actionUrl: route('settings.subscriptions'),
            data: ['user_id' => $user->id, 'plan' => $planName],
        );
    }

    public static function notifyPaymentMade(User $user, string $planName, string $amount): void
    {
        static::send($user->id, 'payment_made', 'Payment Successful', "Your payment of {$amount} for {$planName} was received.", 'payment', route('dashboard'), ['plan' => $planName, 'amount' => $amount]);
    }

    public static function notifyNewTribute(
        \App\Models\Memorial $memorial,
        string $tributeType,
        string $authorName,
        ?int $excludeUserId = null,
        ?\App\Models\Tribute $tribute = null
    ): void {
        $actionUrl = $tribute?->share_id
            ? route('memorial.tribute.public', ['memorial_slug' => $memorial->slug, 'share_id' => $tribute->share_id])
            : route('memorial.public', $memorial->slug);

        $deceasedName = $memorial->full_name ?? 'your loved one';
        $tributeMessage = static::formatTributeMessage($authorName, $tributeType, $deceasedName);

        $adminNotification = [
            'type' => 'new_tribute',
            'title' => 'New Tribute Received',
            'message' => $tributeMessage,
            'icon' => 'tribute',
            'actionUrl' => $actionUrl,
            'data' => [
                'memorial_id' => $memorial->id,
                'memorial_name' => $memorial->full_name,
                'tribute_type' => $tributeType,
                'author' => $authorName,
            ],
        ];

        static::sendToAdminsForMemorial($memorial, ...$adminNotification);

        if ($memorial->user_id && $memorial->user_id !== $excludeUserId) {
            $owner = $memorial->owner;
            if ($owner && !$owner->hasRole(['admin', 'super-admin'])) {
                static::send(
                    userId: $memorial->user_id,
                    type: 'new_tribute',
                    title: 'New Tribute on Your Memorial',
                    message: $tributeMessage,
                    icon: 'tribute',
                    actionUrl: $actionUrl,
                    data: $adminNotification['data'],
                );
            }
        }
    }

    private static function formatTributeMessage(string $authorName, string $tributeType, string $deceasedName): string
    {
        $type = strtolower($tributeType);
        $article = $type === 'image' ? 'an ' : 'a ';
        return "{$authorName} left {$article}{$type} for {$deceasedName}.";
    }

    public static function notifyNewLifeChapter(
        \App\Models\Memorial $memorial,
        string $chapterTitle,
        ?int $authorUserId = null,
        ?\App\Models\Post $post = null,
        ?string $authorName = null
    ): void {
        $actionUrl = ($post && $post->share_id)
            ? route('memorial.chapter.public', ['memorial_slug' => $memorial->slug, 'share_id' => $post->share_id])
            : route('memorial.public', $memorial->slug);

        $deceasedName = $memorial->full_name ?? 'your loved one';
        $author = $authorName ?? ($post?->user?->name ?? $memorial->owner?->name ?? 'Someone');
        $chapterMessage = "{$author} added a new chapter on {$deceasedName}'s Life.";

        $adminNotification = [
            'type' => 'new_life_chapter',
            'title' => 'New Life Chapter Added',
            'message' => $chapterMessage,
            'icon' => 'chapter',
            'actionUrl' => $actionUrl,
            'data' => [
                'memorial_id' => $memorial->id,
                'memorial_name' => $memorial->full_name,
                'chapter_title' => $chapterTitle,
            ],
        ];

        static::sendToAdminsForMemorial($memorial, ...$adminNotification);

        if ($memorial->user_id && $memorial->user_id !== $authorUserId) {
            $owner = $memorial->owner;
            if ($owner && !$owner->hasRole(['admin', 'super-admin'])) {
                static::send(
                    userId: $memorial->user_id,
                    type: 'new_life_chapter',
                    title: 'New Chapter on Your Memorial',
                    message: $chapterMessage,
                    icon: 'chapter',
                    actionUrl: $actionUrl,
                    data: $adminNotification['data'],
                );
            }
        }
    }

    public static function notifyMemorialStatusChange(
        \App\Models\Memorial $memorial,
        string $newStatus
    ): void {
        if (!$memorial->user_id) {
            return;
        }

        $statusLabels = [
            'active' => 'activated',
            'deactivated' => 'deactivated',
            'suspended' => 'suspended',
        ];

        $label = $statusLabels[$newStatus] ?? $newStatus;

        static::send(
            userId: $memorial->user_id,
            type: 'memorial_status_change',
            title: 'Memorial Status Updated',
            message: "Your memorial for {$memorial->full_name} has been {$label}.",
            icon: 'status',
            actionUrl: route('memorials.index'),
            data: [
                'memorial_id' => $memorial->id,
                'memorial_name' => $memorial->full_name,
                'new_status' => $newStatus,
            ],
        );
    }

    public static function notifyMemorialAssigned(
        \App\Models\Memorial $memorial,
        int $userId
    ): void {
        static::send(
            userId: $userId,
            type: 'memorial_assigned',
            title: 'Memorial Assigned to You',
            message: "The memorial for {$memorial->full_name} has been assigned to your account.",
            icon: 'memorial',
            actionUrl: route('memorial.public', $memorial->slug),
            data: [
                'memorial_id' => $memorial->id,
                'memorial_name' => $memorial->full_name,
            ],
        );
    }

    /**
     * Notify when someone comments on a tribute.
     * New comment: notify tribute owner + memorial owner.
     * Reply: notify parent comment author.
     */
    public static function notifyCommentOnTribute(
        \App\Models\Tribute $tribute,
        \App\Models\TributeComment $comment,
        ?int $commenterUserId
    ): void {
        $memorial = $tribute->memorial;
        $commenterName = $comment->user?->name ?? $comment->guest_name ?? 'Someone';
        $deceasedName = $memorial->full_name ?? 'your loved one';

        $actionUrl = $tribute->share_id
            ? route('memorial.tribute.public', ['memorial_slug' => $memorial->slug, 'share_id' => $tribute->share_id])
            : route('memorial.public', $memorial->slug);

        if ($comment->parent_id) {
            $parent = $comment->parent;
            $parentUserId = $parent->user_id ?? ($parent->guest_email ? User::where('email', strtolower($parent->guest_email))->value('id') : null);
            if ($parentUserId && $parentUserId !== $commenterUserId) {
                static::send(
                    userId: $parentUserId,
                    type: 'comment_reply',
                    title: 'Reply to Your Comment',
                    message: "{$commenterName} replied to your comment.",
                    icon: 'tribute',
                    actionUrl: $actionUrl,
                    data: [
                        'tribute_id' => $tribute->id,
                        'comment_id' => $comment->id,
                        'commenter' => $commenterName,
                    ],
                );
            }
        } else {
            $recipients = collect();
            $tributeOwnerId = $tribute->user_id ?? ($tribute->guest_email ? User::where('email', strtolower($tribute->guest_email))->value('id') : null);
            if ($tributeOwnerId && $tributeOwnerId !== $commenterUserId) {
                $recipients->push($tributeOwnerId);
            }
            if ($memorial->user_id && $memorial->user_id !== $commenterUserId && !$recipients->contains($memorial->user_id)) {
                $recipients->push($memorial->user_id);
            }
            $messageToTributeOwner = "{$commenterName} commented on your tribute.";
            $messageToMemorialOwner = "{$commenterName} commented on {$deceasedName}.";
            foreach ($recipients as $userId) {
                $isTributeOwner = $userId === $tributeOwnerId;
                $message = ($isTributeOwner && $memorial->user_id === $tributeOwnerId)
                    ? $messageToTributeOwner
                    : ($isTributeOwner ? $messageToTributeOwner : $messageToMemorialOwner);
                static::send(
                    userId: $userId,
                    type: 'comment_on_tribute',
                    title: 'New Comment on Tribute',
                    message: $message,
                    icon: 'tribute',
                    actionUrl: $actionUrl,
                    data: [
                        'tribute_id' => $tribute->id,
                        'comment_id' => $comment->id,
                        'commenter' => $commenterName,
                        'memorial_name' => $memorial->full_name,
                    ],
                );
            }
        }
    }

    /**
     * Notify when someone comments on a life chapter (post).
     * New comment: notify post owner + memorial owner.
     * Reply: notify parent comment author.
     */
    public static function notifyCommentOnChapter(
        \App\Models\Post $post,
        \App\Models\Comment $comment,
        ?int $commenterUserId
    ): void {
        $memorial = $post->memorial;
        $commenterName = $comment->user?->name ?? $comment->guest_name ?? 'Someone';
        $deceasedName = $memorial->full_name ?? 'your loved one';

        $actionUrl = $post->share_id
            ? route('memorial.chapter.public', ['memorial_slug' => $memorial->slug, 'share_id' => $post->share_id])
            : route('memorial.public', $memorial->slug);

        if ($comment->parent_id) {
            $parent = $comment->parent;
            $parentUserId = $parent->user_id ?? ($parent->guest_email ? User::where('email', strtolower($parent->guest_email))->value('id') : null);
            if ($parentUserId && $parentUserId !== $commenterUserId) {
                static::send(
                    userId: $parentUserId,
                    type: 'comment_reply',
                    title: 'Reply to Your Comment',
                    message: "{$commenterName} replied to your comment.",
                    icon: 'chapter',
                    actionUrl: $actionUrl,
                    data: [
                        'post_id' => $post->id,
                        'comment_id' => $comment->id,
                        'commenter' => $commenterName,
                    ],
                );
            }
        } else {
            $recipients = collect();
            if ($post->user_id && $post->user_id !== $commenterUserId) {
                $recipients->push($post->user_id);
            }
            if ($memorial->user_id && $memorial->user_id !== $commenterUserId && !$recipients->contains($memorial->user_id)) {
                $recipients->push($memorial->user_id);
            }
            $messageToChapterOwner = "{$commenterName} commented on your chapter.";
            $messageToMemorialOwner = "{$commenterName} commented on {$deceasedName}.";
            foreach ($recipients as $userId) {
                $isChapterOwner = $userId === $post->user_id;
                $message = ($isChapterOwner && $memorial->user_id === $post->user_id)
                    ? $messageToChapterOwner
                    : ($isChapterOwner ? $messageToChapterOwner : $messageToMemorialOwner);
                static::send(
                    userId: $userId,
                    type: 'comment_on_chapter',
                    title: 'New Comment on Chapter',
                    message: $message,
                    icon: 'chapter',
                    actionUrl: $actionUrl,
                    data: [
                        'post_id' => $post->id,
                        'comment_id' => $comment->id,
                        'commenter' => $commenterName,
                        'memorial_name' => $memorial->full_name,
                    ],
                );
            }
        }
    }
}
