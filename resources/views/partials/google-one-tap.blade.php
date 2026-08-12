{{--
    Google One Tap — the corner prompt that signs a returning Google user in with one click.

    Included once, from the fullscreen layout, so every public page gets it under the same
    rules. It renders nothing at all unless ALL of these hold:

    - the visitor is signed out (a prompt to sign in over a session that exists is noise);
    - Google login is enabled and configured in admin;
    - this is the platform's own site, not a reseller tenant. One Tap only serves origins
      registered under the OAuth client id, and reseller subdomains and custom domains are
      not registered under ours — Google would refuse the prompt with a console error on
      every page view. Those sites keep the ordinary Google button on the login page.

    Two modes, chosen by the page ($oneTapMode, default 'load'):

    - 'load'   — prompt as soon as Google's script arrives. For the platform's own pages
                 (home, pricing, directory), where a visitor is here to use the product.
    - 'intent' — hold everything, including the third-party script itself, until the
                 visitor first touches something interactive. For memorial pages: someone
                 opening a WhatsApp link to read about a person who died is not greeted by
                 a Google popup — but the moment they move to participate, the prompt they
                 will need is already appearing.
--}}
@if (auth()->guest() && \App\Helpers\SocialLoginHelper::googleLoginEnabled() && ! \App\Helpers\ThemeSetting::siteTenant())
    @php
        $oneTapClientId = trim((string) (\App\Models\SystemSetting::get('oauth.google_client_id', '') ?: env('GOOGLE_CLIENT_ID', '')));
    @endphp
    <script>
        (function () {
            'use strict';
            const MODE = @json($oneTapMode ?? 'load');
            const CLIENT_ID = @json($oneTapClientId);
            let started = false;

            function csrfToken() {
                return document.querySelector('meta[name="csrf-token"]')?.content || '';
            }

            function onCredential(response) {
                if (!response?.credential) return;
                fetch(@json(route('google.one-tap')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ credential: response.credential }),
                })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) return;
                        // A sign-in page seeded where the visitor was headed; follow it.
                        // Anywhere else, reload in place: every view of this page has a
                        // signed-in rendering already, and a reload is the one transition
                        // guaranteed to agree with it everywhere.
                        if (data.redirect) window.location.href = data.redirect;
                        else window.location.reload();
                    })
                    .catch(() => { /* the prompt failing must never break the page */ });
            }

            function start() {
                if (started) return;
                started = true;

                // The third-party script is fetched only now — on a memorial this means
                // only after the visitor moved to participate, never on a quiet read.
                const s = document.createElement('script');
                s.src = 'https://accounts.google.com/gsi/client';
                s.async = true;
                s.onload = function () {
                    if (!window.google?.accounts?.id) return;
                    google.accounts.id.initialize({
                        client_id: CLIENT_ID,
                        callback: onCredential,
                        cancel_on_tap_outside: true,
                        context: 'signin',
                        itp_support: true,
                    });
                    google.accounts.id.prompt();
                };
                document.head.appendChild(s);
            }

            if (MODE === 'intent') {
                // The first touch of anything that would eventually need an identity:
                // a tribute card, the composer, a heart, the comment sheet. Capture
                // phase, once — after that One Tap either showed or Google declined to.
                const INTENT = '[data-tribute-action], #story-composer-open, [data-reaction-btn], [data-open-comments], [data-sheet-input], #story-composer';
                document.addEventListener('click', function onFirstIntent(e) {
                    if (!e.target.closest(INTENT)) return;
                    document.removeEventListener('click', onFirstIntent, true);
                    start();
                }, true);
            } else {
                start();
            }
        })();
    </script>
@endif
