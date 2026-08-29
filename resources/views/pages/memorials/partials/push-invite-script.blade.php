{{--
    Push for a visitor with no account.

    layouts/app runs an equivalent under @auth for the dashboard; a memorial page never passes
    through that layout, and its audience mostly has no account at all — so this is the same
    handshake written for the people who actually visit memorials.

    Nothing here asks the browser for anything until a tap. See push-invite.blade.php for why
    that matters: an unprompted requestPermission() is refused by Safari and Firefox outright,
    and in Chrome a dismissal is a permanent denial for the whole origin.

    @param \App\Models\Memorial $memorial
--}}
@php
    $vapidPublicKey = (string) \App\Models\SystemSetting::get('notifications.vapid_public_key', '');
    $pushEnabled = (bool) \App\Models\SystemSetting::get('notifications.push_enabled', false) && $vapidPublicKey !== '';
@endphp

@if ($pushEnabled)
    <script>
    (function () {
        const VAPID = @json($vapidPublicKey);
        const SLUG = @json($memorial->slug);
        const BASE = @json(rtrim(url()->current(), '/'));
        const SW_URL = @json(asset('sw.js'));
        // Per memorial and per device. Someone who follows links to two memorials is asked
        // about each, because they are two different things to want news of.
        const ASKED_KEY = 'push-invite:' + SLUG;

        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
            return;
        }

        const invite = document.getElementById('push-invite');

        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const raw = window.atob(base64);
            const out = new Uint8Array(raw.length);
            for (let i = 0; i < raw.length; ++i) out[i] = raw.charCodeAt(i);
            return out;
        }

        // Storage throws in a private window on some browsers, and a memorial page must not
        // break because a visitor is browsing privately.
        function asked() {
            try { return localStorage.getItem(ASKED_KEY) !== null; } catch (e) { return false; }
        }
        function remember(answer) {
            try { localStorage.setItem(ASKED_KEY, answer); } catch (e) { /* private window */ }
        }

        async function register() {
            return navigator.serviceWorker.register(SW_URL);
        }

        async function send(path, body) {
            return fetch(BASE.replace(/\/$/, '') + path, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(body),
            });
        }

        async function subscribe(registration) {
            const existing = await registration.pushManager.getSubscription();
            const sub = existing || await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(VAPID),
            });

            const json = sub.toJSON();
            json.contentEncoding = PushManager.supportedContentEncodings?.includes('aes128gcm') ? 'aes128gcm' : 'aesgcm';

            await send('/push/subscribe', json);
            return true;
        }

        function hide() {
            invite?.classList.add('hidden');
            invite?.classList.remove('flex');
        }

        function show() {
            if (!invite) return;
            invite.classList.remove('hidden');
            invite.classList.add('flex');
        }

        invite?.querySelector('[data-push-invite-dismiss]')?.addEventListener('click', () => {
            // "Not now" is remembered as firmly as "yes". Asking twice is what turns a soft no
            // into a browser-level block we can never come back from.
            remember('dismissed');
            hide();
        });

        invite?.querySelector('[data-push-invite-accept]')?.addEventListener('click', async () => {
            remember('asked');
            hide();
            try {
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') return;
                await subscribe(await register());
            } catch (e) {
                console.warn('Push subscribe failed:', e);
            }
        });

        (async function start() {
            // Already answered, on this device or in the browser itself. `denied` is not ours to
            // reopen — only the visitor can, in their own settings.
            if (asked() || Notification.permission === 'denied') return;

            if (Notification.permission === 'granted') {
                // They have already allowed us somewhere. Register quietly; there is nothing to
                // ask and no dialog to open.
                try {
                    await subscribe(await register());
                    remember('granted');
                } catch (e) { /* nothing to show a visitor */ }
                return;
            }

            if (!invite) return;

            // After the page has been read, not on arrival. Whichever comes first: a scroll past
            // the hero, or twenty seconds of someone sitting with it.
            let shown = false;
            const reveal = () => {
                if (shown || asked()) return;
                shown = true;
                window.removeEventListener('scroll', onScroll);
                show();
            };
            const onScroll = () => { if (window.scrollY > 400) reveal(); };

            window.addEventListener('scroll', onScroll, { passive: true });
            setTimeout(reveal, 20000);
        })();
    })();
    </script>
@endif
