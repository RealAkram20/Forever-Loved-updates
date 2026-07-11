/**
 * Global real-time input validation and formatting, applied automatically to
 * every form on every page (including modals injected later).
 *
 * UX rules follow the "reward early, punish late" guidance (NN/g form
 * research): a field is validated live on every keystroke once it has been
 * touched (first blur), so users see mistakes the moment they happen but are
 * not shouted at while still typing a valid value. Native browser bubbles
 * (inconsistent, submit-time only) are suppressed in favor of styled inline
 * messages.
 *
 * Phones use libphonenumber-js (Google's libphonenumber): any format the
 * user types — 0700 123 456, +256-700-123456, 00256700123456 — is accepted
 * and auto-corrected to clean international formatting as they type.
 *
 * Opt out per-field with data-no-guard.
 */

import { AsYouType, parsePhoneNumberFromString } from 'libphonenumber-js';

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
const ERROR_CLASS = 'input-guard-error';
const ENHANCED = 'inputGuardEnhanced';
const TOUCHED = 'inputGuardTouched';

// ── messaging ───────────────────────────────────────────────────────────────

// Error elements are tied to their input directly (never located by DOM
// search): re-lookups can't miss and duplicate, and the message is inserted
// right after the field — only stepping over the input's IMMEDIATE .relative
// wrapper (icon/toggle overlays), never a distant positioned ancestor.
const errorEls = new WeakMap();

function errorEl(input) {
    const existing = errorEls.get(input);
    if (existing && existing.isConnected) return existing;

    const el = document.createElement('p');
    el.className = `${ERROR_CLASS} mt-1.5 text-sm text-red-500`;
    const parent = input.parentElement;
    const anchor = parent && parent.classList.contains('relative') ? parent : input;
    anchor.insertAdjacentElement('afterend', el);
    errorEls.set(input, el);

    return el;
}

function setError(input, message) {
    errorEl(input).textContent = message;
    input.classList.add('border-red-500');
    input.setAttribute('aria-invalid', 'true');
}

function clearError(input) {
    const el = errorEls.get(input);
    if (el) el.textContent = '';
    input.classList.remove('border-red-500');
    input.removeAttribute('aria-invalid');
}

// ── validators (return null when valid, message when not) ──────────────────

function validateEmail(input) {
    const value = input.value.trim();
    if (!value) return input.required ? 'Please enter your email address.' : null;
    if (!EMAIL_RE.test(value)) return 'That email address doesn’t look right — please check it.';
    return null;
}

function validatePhone(input) {
    const raw = input.value.trim();
    if (!raw) return input.required ? 'Please enter your phone number.' : null;

    const normalized = normalizePhone(raw);
    if (normalized.startsWith('+')) {
        const parsed = parsePhoneNumberFromString(normalized);
        if (!parsed || !parsed.isValid()) return 'That phone number doesn’t look valid — check the country code and digits.';
        return null;
    }

    const digits = normalized.replace(/\D/g, '');
    if (digits.length < 6 || digits.length > 15) return 'Please enter a valid phone number (6–15 digits).';
    return null;
}

function validateRequired(input) {
    if (input.required && !input.value.trim()) return 'This field is required.';
    return null;
}

function validateConfirmation(input) {
    const form = input.form;
    const original = form?.querySelector('input[name="password"]');
    if (original && input.value && input.value !== original.value) return 'Passwords don’t match yet.';
    if (input.required && !input.value) return 'Please confirm your password.';
    return null;
}

// ── phone normalization / as-you-type formatting ───────────────────────────

function normalizePhone(raw) {
    let v = raw.replace(/[^\d+]/g, '');
    if (v.startsWith('00')) v = '+' + v.slice(2); // 00-prefix international → +
    v = v[0] === '+' ? '+' + v.slice(1).replace(/\+/g, '') : v.replace(/\+/g, '');
    return v;
}

function formatPhoneLive(raw) {
    const normalized = normalizePhone(raw);
    if (!normalized) return '';
    if (normalized.startsWith('+')) {
        return new AsYouType().input(normalized);
    }
    // national format, country unknown: group digits gently, force nothing
    return normalized.replace(/(\d{4})(?=\d)/g, '$1 ');
}

// ── field wiring ────────────────────────────────────────────────────────────

function validatorFor(input) {
    if (input.type === 'email') return validateEmail;
    if (input.type === 'tel') return validatePhone;
    if (input.name === 'password_confirmation') return validateConfirmation;
    return validateRequired;
}

function runValidation(input) {
    const message = validatorFor(input)(input);
    message ? setError(input, message) : clearError(input);
    return !message;
}

function wireField(input) {
    const isPhone = input.type === 'tel';

    if (input.type === 'email') {
        if (!input.getAttribute('autocomplete')) input.setAttribute('autocomplete', 'email');
        input.setAttribute('autocapitalize', 'none');
        input.setAttribute('spellcheck', 'false');
        if (!input.getAttribute('inputmode')) input.setAttribute('inputmode', 'email');
    }
    if (isPhone) {
        if (!input.getAttribute('autocomplete')) input.setAttribute('autocomplete', 'tel');
        input.setAttribute('inputmode', 'tel');
    }

    input.addEventListener('input', () => {
        if (isPhone) {
            const before = input.value;
            const formatted = formatPhoneLive(before);
            if (formatted !== before) {
                // keep the caret at the end only when we actually reformatted
                const atEnd = input.selectionEnd === before.length;
                input.value = formatted;
                if (!atEnd) input.setSelectionRange(input.value.length, input.value.length);
            }
        }
        // real-time: once touched, re-validate on every keystroke
        if (input.dataset[TOUCHED]) runValidation(input);
        else clearError(input);
    });

    input.addEventListener('blur', () => {
        if (!input.value && !input.dataset[TOUCHED]) return; // never scold an untouched empty field
        input.dataset[TOUCHED] = '1';
        if (isPhone && input.value) input.value = formatPhoneLive(input.value);
        runValidation(input);
    });
}

// ── form wiring: suppress native bubbles, validate on submit ───────────────

function wireForm(form) {
    if (form.dataset[ENHANCED]) return;
    form.dataset[ENHANCED] = '1';
    form.setAttribute('novalidate', 'novalidate');

    form.addEventListener('submit', (e) => {
        let firstInvalid = null;
        form.querySelectorAll('input[data-input-guard-enhanced]').forEach((input) => {
            input.dataset[TOUCHED] = '1';
            if (!runValidation(input) && !firstInvalid) firstInvalid = input;
        });
        if (firstInvalid) {
            e.preventDefault();
            e.stopImmediatePropagation();
            firstInvalid.focus();
            return;
        }
        // novalidate turned off native checks for fields we don't enhance
        // (selects, number inputs) — keep those protected the native way.
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopImmediatePropagation();
            form.reportValidity();
        }
    }, true);
}

// ── bootstrapping ───────────────────────────────────────────────────────────

const SELECTOR = [
    'input[type="email"]',
    'input[type="tel"]',
    'input[name="password_confirmation"]',
    'input[required][type="text"]',
    'input[required][type="password"]',
].map((s) => `${s}:not([data-no-guard])`).join(', ');

function enhance(root) {
    const scope = root instanceof Element || root instanceof Document ? root : document;
    if (!scope.querySelectorAll) return;
    scope.querySelectorAll(SELECTOR).forEach((input) => {
        if (input.dataset[ENHANCED]) return;
        input.dataset[ENHANCED] = '1';
        wireField(input);
        if (input.form) wireForm(input.form);
    });
}

export function registerInputGuards() {
    enhance(document);
    new MutationObserver((mutations) => {
        for (const m of mutations) {
            for (const node of m.addedNodes) {
                if (node instanceof Element) enhance(node);
            }
        }
    }).observe(document.body, { childList: true, subtree: true });
}
