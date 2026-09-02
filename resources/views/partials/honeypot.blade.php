{{--
    The honeypot: a field a person never sees and a bot usually fills.

    One partial rather than a field pasted into five forms, because the *name* is behavioural.
    The controller checks one key, and a form that spells it differently is a form with no
    protection at all — which is precisely the state this codebase was already in: the comment
    at the top of `banner-form.blade.php` claimed a honeypot the markup never had.

    Include it inside any <form> that a logged-out visitor can post:

        @include('partials.honeypot')

    Why these particular attributes:

    - **Positioned off-canvas, not `display: none` or `hidden`.** The better bots skip fields
      that are display:none precisely because that is how honeypots are usually built. Moving
      it out of the viewport leaves it "visible" to a naive DOM scraper and invisible to a
      person. It is not a strong defence — nothing here is — but it costs nothing.
    - **`tabindex="-1"`** so keyboard users never land on it.
    - **`aria-hidden="true"`** so screen readers never announce it. A honeypot that a blind
      visitor fills in is a honeypot that blocks a blind visitor.
    - **`autocomplete="off"`** so a browser's saved-address autofill does not put a real URL
      in it and lock a real person out of the form.

    The name is a plausible one a bot wants to fill. `website` is the usual choice and is
    deliberately boring — the point is that it looks like a field worth spamming.

    Scope: this stops unsophisticated bots. It does nothing against a targeted attacker, and it
    is not a substitute for the rate limits on the routes. See `App\Support\Honeypot`.
--}}
<div aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">
    <label for="{{ \App\Support\Honeypot::FIELD }}-{{ $honeypotId ?? 'f' }}">Leave this field empty</label>
    <input
        type="text"
        id="{{ \App\Support\Honeypot::FIELD }}-{{ $honeypotId ?? 'f' }}"
        name="{{ \App\Support\Honeypot::FIELD }}"
        value=""
        tabindex="-1"
        autocomplete="off" />
</div>
