/**
 * Alpine.js Color Picker component.
 *
 * Provides an HSV-based color picker with:
 *  - Saturation/brightness canvas
 *  - Hue slider
 *  - Hex code input
 *  - Active colors (from sibling pickers on the page)
 *  - Color history (localStorage)
 */

const HISTORY_KEY = 'fl_color_picker_history';
const MAX_HISTORY = 16;

function hexToHsv(hex) {
    hex = hex.replace('#', '');
    if (hex.length === 3) hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
    const r = parseInt(hex.substring(0, 2), 16) / 255;
    const g = parseInt(hex.substring(2, 4), 16) / 255;
    const b = parseInt(hex.substring(4, 6), 16) / 255;

    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const d = max - min;

    let h = 0;
    if (d !== 0) {
        if (max === r) h = ((g - b) / d + 6) % 6;
        else if (max === g) h = (b - r) / d + 2;
        else h = (r - g) / d + 4;
        h *= 60;
    }

    const s = max === 0 ? 0 : d / max;
    return { h, s, v: max };
}

function hsvToHex(h, s, v) {
    const c = v * s;
    const x = c * (1 - Math.abs(((h / 60) % 2) - 1));
    const m = v - c;
    let r, g, b;

    if (h < 60)       { r = c; g = x; b = 0; }
    else if (h < 120)  { r = x; g = c; b = 0; }
    else if (h < 180)  { r = 0; g = c; b = x; }
    else if (h < 240)  { r = 0; g = x; b = c; }
    else if (h < 300)  { r = x; g = 0; b = c; }
    else               { r = c; g = 0; b = x; }

    const toHex = (n) => {
        const val = Math.round((n + m) * 255);
        return val.toString(16).padStart(2, '0');
    };

    return '#' + toHex(r) + toHex(g) + toHex(b);
}

function getHistory() {
    try {
        const raw = localStorage.getItem(HISTORY_KEY);
        return raw ? JSON.parse(raw) : [];
    } catch { return []; }
}

function pushHistory(hex) {
    const list = getHistory().filter(c => c.toLowerCase() !== hex.toLowerCase());
    list.unshift(hex);
    if (list.length > MAX_HISTORY) list.length = MAX_HISTORY;
    try { localStorage.setItem(HISTORY_KEY, JSON.stringify(list)); } catch {}
}

function getActiveColors() {
    const inputs = document.querySelectorAll('input[data-color-picker-input]');
    const set = new Set();
    inputs.forEach(i => {
        const v = (i.value || '').trim();
        if (/^#[0-9a-f]{6}$/i.test(v)) set.add(v.toLowerCase());
    });
    return [...set];
}

export function registerColorPicker(Alpine) {
    Alpine.data('colorPicker', (initialColor = '#465fff', inputName = '') => ({
        open: false,
        hex: initialColor,
        hue: 0,
        sat: 1,
        val: 1,
        canvasW: 256,
        canvasH: 160,
        draggingCanvas: false,
        draggingHue: false,
        history: [],
        activeColors: [],

        init() {
            const hsv = hexToHsv(this.hex);
            this.hue = hsv.h;
            this.sat = hsv.s;
            this.val = hsv.v;
            this.history = getHistory();

            this.$watch('hex', (val) => {
                if (/^#[0-9a-f]{6}$/i.test(val)) {
                    const hsv = hexToHsv(val);
                    this.hue = hsv.h;
                    this.sat = hsv.s;
                    this.val = hsv.v;
                    this.syncHiddenInput();
                    this.drawCanvas();
                }
            });
        },

        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.activeColors = getActiveColors();
                this.history = getHistory();
                this.$nextTick(() => this.drawCanvas());
            } else {
                pushHistory(this.hex);
            }
        },

        close() {
            if (this.open) {
                this.open = false;
                pushHistory(this.hex);
            }
        },

        syncHiddenInput() {
            const hidden = this.$refs.hiddenInput;
            if (hidden) hidden.value = this.hex;
        },

        updateFromHsv() {
            this.hex = hsvToHex(this.hue, this.sat, this.val);
            this.syncHiddenInput();
        },

        drawCanvas() {
            const canvas = this.$refs.svCanvas;
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            const w = canvas.width;
            const h = canvas.height;

            const hueColor = hsvToHex(this.hue, 1, 1);

            // White-to-hue horizontal gradient
            const gradH = ctx.createLinearGradient(0, 0, w, 0);
            gradH.addColorStop(0, '#ffffff');
            gradH.addColorStop(1, hueColor);
            ctx.fillStyle = gradH;
            ctx.fillRect(0, 0, w, h);

            // Transparent-to-black vertical gradient
            const gradV = ctx.createLinearGradient(0, 0, 0, h);
            gradV.addColorStop(0, 'rgba(0,0,0,0)');
            gradV.addColorStop(1, 'rgba(0,0,0,1)');
            ctx.fillStyle = gradV;
            ctx.fillRect(0, 0, w, h);
        },

        onCanvasPointer(e) {
            const canvas = this.$refs.svCanvas;
            if (!canvas) return;
            const rect = canvas.getBoundingClientRect();
            const x = Math.max(0, Math.min(e.clientX - rect.left, rect.width));
            const y = Math.max(0, Math.min(e.clientY - rect.top, rect.height));
            this.sat = x / rect.width;
            this.val = 1 - y / rect.height;
            this.updateFromHsv();
        },

        onCanvasDown(e) {
            this.draggingCanvas = true;
            this.onCanvasPointer(e);
        },

        onHuePointer(e) {
            const bar = this.$refs.hueBar;
            if (!bar) return;
            const rect = bar.getBoundingClientRect();
            const x = Math.max(0, Math.min(e.clientX - rect.left, rect.width));
            this.hue = (x / rect.width) * 360;
            this.updateFromHsv();
            this.drawCanvas();
        },

        onHueDown(e) {
            this.draggingHue = true;
            this.onHuePointer(e);
        },

        onWindowMove(e) {
            if (this.draggingCanvas) this.onCanvasPointer(e);
            if (this.draggingHue) this.onHuePointer(e);
        },

        onWindowUp() {
            this.draggingCanvas = false;
            this.draggingHue = false;
        },

        onHexInput(e) {
            let v = e.target.value.trim();
            if (!v.startsWith('#')) v = '#' + v;
            if (/^#[0-9a-f]{6}$/i.test(v)) {
                this.hex = v;
            }
        },

        pickColor(color) {
            this.hex = color;
            this.syncHiddenInput();
        },

        get cursorX() { return this.sat * 100; },
        get cursorY() { return (1 - this.val) * 100; },
        get huePercent() { return (this.hue / 360) * 100; },
    }));
}
