/**
 * The full-screen candle scene: a field of tealights that lights itself in a wave, under
 * a golden star field and a faint nebula, settling into an ambient loop.
 *
 * Canvas rather than DOM. At the counts this scene calls for — hundreds of candles, a
 * thousand embers, thousands of stars — one element per object is not a tuning problem,
 * it is the wrong tool: the browser would be laying out and compositing several thousand
 * nodes every frame. Everything here is drawn into a single canvas from a handful of
 * sprites that are rendered once at start-up, so a frame costs draw calls and nothing else.
 *
 * Loaded on demand. Nobody pays for this file until they light a candle.
 */

// How much faster the sequence runs than its natural spacing. The beats below are written
// at the pace they were designed at and divided by this, so the shape of the thing stays
// readable and the tempo is a single number to turn.
//
// This scales the narrative only. Flame flicker, the camera's breath and the drift of the
// stars keep their own rate: those are physical, and a candle does not flicker faster
// because the scene around it is shorter.
const SPEED = 4;
const beat = (ms) => ms / SPEED;

// The beats the scene is built around. Everything reads from elapsed time rather than
// firing on a schedule, so a dropped frame or a slow device shifts nothing out of step.
const T = {
    fadeStart: beat(200),      // the page dims
    heroIgnite: beat(600),     // the tapped candle catches
    embers: beat(1200),        // sparks start rising off it
    fieldStart: beat(1800),    // other candles begin appearing, one by one
    fieldSettled: beat(6200),  // the last of them is lit
    stars: beat(3500),
    nebula: beat(4200),
    // Held on the finished field for a moment before it leaves. The full scene is the
    // whole point of getting there, so it is worth more than the instant it took to
    // arrive — but it ends on its own rather than looping, so nobody has to find the way
    // out of it.
    complete: beat(10400),
};

// Straight from the brief; used top to bottom as the flame goes core to base.
const FLAME_COLOURS = ['#FFF8D8', '#FFD86B', '#FFAE33', '#FF8A00'];

const CAMERA_CYCLE = 40000; // one full breath in and back out

function makeCanvas(w, h) {
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.ceil(w));
    canvas.height = Math.max(1, Math.ceil(h));
    return canvas;
}

/** Warm halo. Drawn under every flame and additively, which is what gives the bloom. */
function makeGlowSprite(size) {
    const canvas = makeCanvas(size, size);
    const ctx = canvas.getContext('2d');
    const r = size / 2;
    const grad = ctx.createRadialGradient(r, r, 0, r, r, r);
    grad.addColorStop(0, 'rgba(255, 224, 160, 0.90)');
    grad.addColorStop(0.12, 'rgba(255, 186, 84, 0.42)');
    grad.addColorStop(0.34, 'rgba(255, 140, 30, 0.13)');
    grad.addColorStop(0.62, 'rgba(255, 116, 0, 0.035)');
    grad.addColorStop(1, 'rgba(255, 110, 0, 0)');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, size, size);
    return canvas;
}

function teardrop(ctx, cx, baseY, halfWidth, height, lean) {
    const tipX = cx + lean * height;
    ctx.beginPath();
    ctx.moveTo(tipX, baseY - height);
    ctx.bezierCurveTo(
        cx + halfWidth * 0.95, baseY - height * 0.58,
        cx + halfWidth, baseY - height * 0.16,
        cx, baseY,
    );
    ctx.bezierCurveTo(
        cx - halfWidth, baseY - height * 0.16,
        cx - halfWidth * 0.95, baseY - height * 0.58,
        tipX, baseY - height,
    );
    ctx.closePath();
}

/**
 * One flame, pre-rendered.
 *
 * A handful of these are baked at slightly different widths and leans, and each candle
 * walks its own way through them. Cycling baked frames costs a draw call, where filling
 * three beziers per candle per frame would not survive three hundred of them.
 */
function makeFlameSprite(w, h, lean, squash) {
    const canvas = makeCanvas(w, h * 1.1);
    const ctx = canvas.getContext('2d');
    const cx = w / 2;
    const baseY = h * 1.02;
    const height = h * squash;

    // Outer envelope: the soft orange body of the flame.
    ctx.globalAlpha = 0.55;
    teardrop(ctx, cx, baseY, w * 0.44, height, lean);
    const outer = ctx.createLinearGradient(0, baseY, 0, baseY - height);
    outer.addColorStop(0, FLAME_COLOURS[3]);
    outer.addColorStop(0.55, FLAME_COLOURS[2]);
    outer.addColorStop(1, 'rgba(255, 174, 51, 0)');
    ctx.fillStyle = outer;
    ctx.fill();

    // Body.
    ctx.globalAlpha = 0.92;
    teardrop(ctx, cx, baseY, w * 0.27, height * 0.82, lean);
    const mid = ctx.createLinearGradient(0, baseY, 0, baseY - height * 0.82);
    mid.addColorStop(0, FLAME_COLOURS[2]);
    mid.addColorStop(0.5, FLAME_COLOURS[1]);
    mid.addColorStop(1, FLAME_COLOURS[0]);
    ctx.fillStyle = mid;
    ctx.fill();

    // Core. The eye reads a flame by its hot centre, so this stays near-white.
    ctx.globalAlpha = 1;
    teardrop(ctx, cx, baseY - height * 0.06, w * 0.12, height * 0.5, lean * 0.6);
    ctx.fillStyle = FLAME_COLOURS[0];
    ctx.fill();

    // The dim blue-dark pocket where the wick sits.
    ctx.globalAlpha = 0.5;
    ctx.beginPath();
    ctx.ellipse(cx, baseY - height * 0.08, w * 0.07, height * 0.09, 0, 0, Math.PI * 2);
    ctx.fillStyle = 'rgba(90, 50, 10, 0.9)';
    ctx.fill();

    return canvas;
}

/** The aluminium cup. The only thing in the scene not drawn additively. */
function makeCupSprite(w) {
    const h = w * 0.42;
    const canvas = makeCanvas(w, h);
    const ctx = canvas.getContext('2d');
    const rimY = h * 0.34;
    const rx = w * 0.48;
    const ry = h * 0.3;

    // Body, tapering slightly inwards towards the base like a real tealight.
    ctx.beginPath();
    ctx.moveTo(w * 0.02, rimY);
    ctx.lineTo(w * 0.09, h * 0.9);
    ctx.quadraticCurveTo(w * 0.5, h * 1.06, w * 0.91, h * 0.9);
    ctx.lineTo(w * 0.98, rimY);
    ctx.closePath();
    const body = ctx.createLinearGradient(0, 0, w, 0);
    body.addColorStop(0, '#1d150a');
    body.addColorStop(0.3, '#5c431f');
    body.addColorStop(0.52, '#936d34');
    body.addColorStop(0.75, '#4e391b');
    body.addColorStop(1, '#170f07');
    ctx.fillStyle = body;
    ctx.fill();

    // Wax, lit from the wick outward. Only the middle runs hot — a cup lit edge to edge
    // reads as a disc of paint rather than as metal holding a small pool of light.
    const wax = ctx.createRadialGradient(w * 0.5, rimY, 0, w * 0.5, rimY, rx);
    wax.addColorStop(0, '#ffe9b0');
    wax.addColorStop(0.35, '#d99441');
    wax.addColorStop(1, '#7a4f1c');
    ctx.beginPath();
    ctx.ellipse(w * 0.5, rimY, rx * 0.94, ry * 0.94, 0, 0, Math.PI * 2);
    ctx.fillStyle = wax;
    ctx.fill();

    // Rim catch-light along the top edge.
    ctx.beginPath();
    ctx.ellipse(w * 0.5, rimY, rx, ry, 0, Math.PI * 1.05, Math.PI * 1.95);
    ctx.strokeStyle = 'rgba(255, 214, 150, 0.75)';
    ctx.lineWidth = Math.max(1, w * 0.03);
    ctx.stroke();

    return canvas;
}

function makeEmberSprite(size) {
    const canvas = makeCanvas(size, size);
    const ctx = canvas.getContext('2d');
    const r = size / 2;
    const grad = ctx.createRadialGradient(r, r, 0, r, r, r);
    grad.addColorStop(0, 'rgba(255, 248, 216, 1)');
    grad.addColorStop(0.25, 'rgba(255, 200, 96, 0.8)');
    grad.addColorStop(0.6, 'rgba(255, 150, 30, 0.22)');
    grad.addColorStop(1, 'rgba(255, 140, 0, 0)');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, size, size);
    return canvas;
}

/**
 * The star field, baked once.
 *
 * Two and a half thousand stars redrawn every frame would be the most expensive thing on
 * screen and no one would see the difference, because most of a star field is static.
 * The bulk is painted into a tile that only drifts; a few hundred live twinklers are
 * drawn on top, and those are what the eye actually catches.
 */
function makeStarField(w, h, count) {
    const canvas = makeCanvas(w, h);
    const ctx = canvas.getContext('2d');

    for (let i = 0; i < count; i++) {
        const x = Math.random() * w;
        const y = Math.random() * h;
        // Denser towards the top: the horizon end of the field is where the candles are.
        if (Math.random() < (y / h) * 0.55) continue;

        const size = Math.random() < 0.86 ? 1 : 2;
        const warmth = Math.random();
        const alpha = 0.18 + Math.random() * 0.62;
        ctx.fillStyle = warmth < 0.55
            ? `rgba(255, 246, 214, ${alpha})`
            : warmth < 0.85
                ? `rgba(255, 206, 130, ${alpha})`
                : `rgba(255, 168, 74, ${alpha})`;
        ctx.fillRect(x, y, size, size);
    }

    return canvas;
}

/**
 * A soft diagonal nebula. Built from overlapping blurred blobs along one axis rather than
 * from a noise texture, which keeps it to a few dozen fills at start-up and nothing after.
 */
function makeNebula(w, h) {
    // Half resolution: it is the softest thing on screen, so nobody can tell.
    const canvas = makeCanvas(w / 2, h / 2);
    const ctx = canvas.getContext('2d');
    const W = canvas.width;
    const H = canvas.height;

    ctx.globalCompositeOperation = 'lighter';

    // Held to the sky. Left to cover the frame it drifts down over the candle field,
    // where each blob reads as a lens smudge on the glass rather than as depth behind it.
    const skyLimit = H * 0.62;
    ctx.save();
    ctx.beginPath();
    ctx.rect(0, 0, W, skyLimit);
    ctx.clip();

    // Many small overlapping blobs rather than a few large ones: at a few dozen the
    // individual circles stay legible as circles no matter how low the alpha goes.
    for (let i = 0; i < 150; i++) {
        const t = Math.random();
        const drift = (Math.random() - 0.5) * H * 0.30;
        const x = W * (0.02 + t * 0.98) + drift * 0.5;
        const y = skyLimit * (1.02 - t * 0.98) + drift;
        const r = (W * 0.055) * (0.45 + Math.random());

        const grad = ctx.createRadialGradient(x, y, 0, x, y, r);
        grad.addColorStop(0, 'rgba(255, 178, 92, 0.055)');
        grad.addColorStop(0.5, 'rgba(255, 140, 50, 0.022)');
        grad.addColorStop(1, 'rgba(255, 120, 30, 0)');
        ctx.fillStyle = grad;
        ctx.beginPath();
        ctx.arc(x, y, r, 0, Math.PI * 2);
        ctx.fill();
    }

    // A scatter of brighter motes through the band, so it has grain rather than being
    // an even wash.
    for (let i = 0; i < 500; i++) {
        const t = Math.random();
        const spread = (Math.random() - 0.5) * H * 0.24;
        const x = W * (0.02 + t * 0.98) + spread * 0.5;
        const y = skyLimit * (1.02 - t * 0.98) + spread;
        if (x < 0 || x > W || y < 0 || y > skyLimit) continue;
        ctx.fillStyle = `rgba(255, 200, 120, ${0.08 + Math.random() * 0.26})`;
        ctx.fillRect(x, y, 1, 1);
    }

    ctx.restore();

    // Feather the lower edge back out. The clip alone leaves a seam straight across the
    // frame, which on a night sky is the most conspicuous thing in the scene.
    const fadeTop = skyLimit * 0.66;
    const fade = ctx.createLinearGradient(0, fadeTop, 0, skyLimit);
    fade.addColorStop(0, 'rgba(0, 0, 0, 0)');
    fade.addColorStop(1, 'rgba(0, 0, 0, 1)');
    ctx.globalCompositeOperation = 'destination-out';
    ctx.fillStyle = fade;
    ctx.fillRect(0, fadeTop, W, skyLimit - fadeTop);

    return canvas;
}

/**
 * The ground the whole scene is painted on.
 *
 * Violet rather than black, so the field sits in the brand's colour instead of in a void.
 * Deepest at the very bottom and again at the top, with the horizon band left lightest —
 * that is where the candles crowd, and letting the ground lift there reads as their own
 * light hanging in the air rather than as a flat backdrop behind them.
 *
 * It stays dark on purpose. Every flame, halo and star in this scene composites additively,
 * so the ground is what they are adding to: lift it much further and the gold stops
 * separating from it.
 */
function makeGround(w, h, horizon) {
    const canvas = makeCanvas(w, h);
    const ctx = canvas.getContext('2d');
    const grad = ctx.createLinearGradient(0, 0, 0, h);
    grad.addColorStop(0, '#1b1226');
    grad.addColorStop(Math.max(0.02, horizon / h - 0.10), '#2a1b3d');
    grad.addColorStop(Math.min(0.98, horizon / h + 0.06), '#33204a');
    grad.addColorStop(0.72, '#241733');
    grad.addColorStop(1, '#150e1e');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, w, h);
    return canvas;
}

function makeVignette(w, h) {
    const canvas = makeCanvas(w, h);
    const ctx = canvas.getContext('2d');
    const grad = ctx.createRadialGradient(
        w / 2, h * 0.56, Math.min(w, h) * 0.22,
        w / 2, h * 0.56, Math.max(w, h) * 0.72,
    );
    // Tinted rather than neutral black: a pure black vignette over a violet ground drains
    // the colour back out of exactly the edges the ground was added for.
    grad.addColorStop(0, 'rgba(14, 8, 22, 0)');
    grad.addColorStop(0.62, 'rgba(14, 8, 22, 0.24)');
    grad.addColorStop(1, 'rgba(11, 6, 17, 0.86)');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, w, h);
    return canvas;
}

const easeOut = (t) => 1 - Math.pow(1 - t, 3);
const clamp01 = (t) => (t < 0 ? 0 : t > 1 ? 1 : t);

let active = null;

/**
 * Open the scene. Returns a function that closes it, and is a no-op if one is already up.
 *
 * @param {{originX?: number, originY?: number}} opts  Where the tap landed, so the first
 *        candle lights roughly under the finger rather than somewhere unrelated.
 */
export function playCandleScene(opts = {}) {
    if (active) return active.close;

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const root = document.createElement('div');
    root.className = 'memorial-candle-scene';
    root.setAttribute('role', 'dialog');
    root.setAttribute('aria-modal', 'true');
    root.setAttribute('aria-label', 'A candle lit in memory');
    root.tabIndex = -1;

    const canvas = document.createElement('canvas');
    canvas.setAttribute('aria-hidden', 'true');
    root.appendChild(canvas);

    document.body.appendChild(root);

    const ctx = canvas.getContext('2d', { alpha: false });

    // Scroll would carry the page around underneath a fixed full-screen scene, so it is
    // held for as long as the scene is up and put back exactly as it was.
    const prevOverflow = document.documentElement.style.overflow;
    document.documentElement.style.overflow = 'hidden';
    const lastFocus = document.activeElement;

    let W = 0;
    let H = 0;
    let dpr = 1;
    let quality = 1;
    let horizon = 0;
    let perspective = 0;
    let baseCupWidth = 0;

    let sprites = null;
    let candles = [];
    let embers = [];
    let twinklers = [];

    // Explicitly null rather than 0: a zero timestamp is a legal first frame, and testing
    // it for truthiness would re-stamp the start on every frame and freeze elapsed time.
    let started = null;
    let raf = 0;
    let closing = 0;
    let slowFrames = 0;

    const wide = window.innerWidth >= 1024;
    const mid = window.innerWidth >= 640;
    // The brief's counts, held on a full-size screen and scaled back where the pixels and
    // the GPU are smaller. Embers come down hardest: they are the cheapest to lose and
    // the most numerous.
    const COUNTS = {
        candles: wide ? 300 : mid ? 200 : 130,
        embers: wide ? 900 : mid ? 480 : 260,
        stars: wide ? 2500 : mid ? 1700 : 1100,
        twinklers: wide ? 260 : mid ? 180 : 120,
    };

    function layout() {
        W = window.innerWidth;
        H = window.innerHeight;
        dpr = Math.min(window.devicePixelRatio || 1, 2) * quality;
        canvas.width = Math.ceil(W * dpr);
        canvas.height = Math.ceil(H * dpr);
        canvas.style.width = `${W}px`;
        canvas.style.height = `${H}px`;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        horizon = H * 0.28;
        // Ties depth to screen position: a candle's distance below the horizon, and its
        // size, are both this over its depth. One number keeps them consistent.
        perspective = H * 0.78;
        baseCupWidth = Math.min(W, H * 1.3) * 0.19;

        sprites = {
            glow: makeGlowSprite(256),
            cup: makeCupSprite(256),
            ember: makeEmberSprite(24),
            flames: [
                makeFlameSprite(96, 150, 0.00, 1.00),
                makeFlameSprite(96, 150, 0.05, 0.94),
                makeFlameSprite(96, 150, -0.04, 1.05),
                makeFlameSprite(96, 150, 0.02, 0.90),
                makeFlameSprite(96, 150, -0.06, 0.98),
            ],
            stars: makeStarField(W, H, COUNTS.stars),
            nebula: makeNebula(W, H),
            ground: makeGround(W, H, horizon),
            vignette: makeVignette(W, H),
        };
    }

    function buildField() {
        candles = [];

        // The first candle sits under the tap, at the front of the field, so the scene
        // grows out of the thing that was actually pressed.
        const originX = typeof opts.originX === 'number' ? opts.originX : W * 0.5;
        // Far enough back that the whole cup sits on screen. At depth 1 it lands under the
        // bottom edge and only its flame shows.
        const heroDepth = 1.35;
        const heroY = horizon + perspective / heroDepth;
        candles.push(makeCandle(originX, heroDepth, heroY, true));

        for (let i = 0; i < COUNTS.candles - 1; i++) {
            // Depth is sampled evenly, and screen position falls out of it as one over
            // depth. That is what produces a field that thins and shrinks towards the
            // horizon on its own — an even spread of *screen* positions instead puts a
            // quarter of the candles in the front rows at full size, where they overlap
            // and their haloes stack additively into a wall of light.
            //
            // Starting behind the hero leaves it alone at the front of the field.
            const depth = 1.7 + Math.random() * 24;
            const y = horizon + perspective / depth;
            // Screen x rather than world x, so the frame stays evenly filled at every
            // depth instead of the distant rows bunching towards the centre.
            const x = -W * 0.12 + Math.random() * W * 1.24;
            candles.push(makeCandle(x, depth, y, false));
        }

        // Painter's order, far to near, so nearer candles and their glow sit in front.
        candles.sort((a, b) => b.depth - a.depth);

        // The wave: nearest lights first and the light travels away from the viewer. The
        // exponent starts it slow — a few candles, one at a time — before it floods.
        const lit = candles.filter(c => !c.hero).sort((a, b) => a.depth - b.depth);
        const span = T.fieldSettled - T.fieldStart;
        lit.forEach((candle, i) => {
            const u = i / Math.max(1, lit.length - 1);
            candle.igniteAt = T.fieldStart + span * Math.pow(u, 0.7) + (Math.random() - 0.5) * 220;
        });

        twinklers = [];
        for (let i = 0; i < COUNTS.twinklers; i++) {
            twinklers.push({
                x: Math.random() * W,
                y: Math.random() * H * 0.8,
                size: Math.random() < 0.8 ? 1 : 2,
                speed: 0.0009 + Math.random() * 0.0022,
                phase: Math.random() * Math.PI * 2,
            });
        }

        embers = [];
    }

    function makeCandle(x, depth, y, hero) {
        return {
            // Held in world terms so a resize re-projects rather than reshuffles.
            wx: (x - W / 2) * depth,
            depth,
            y,
            hero,
            igniteAt: hero ? T.heroIgnite : 0,
            phase: Math.random() * Math.PI * 2,
            // Each flame runs at its own rate. Shared timing across a field this size
            // reads immediately as one animation copied three hundred times.
            speed: 0.0045 + Math.random() * 0.006,
            frame: Math.floor(Math.random() * 5),
            sway: 0.85 + Math.random() * 0.3,
        };
    }

    function spawnEmber(x, y, scale) {
        if (embers.length >= COUNTS.embers) return;
        embers.push({
            x,
            y,
            vx: (Math.random() - 0.5) * 0.16,
            vy: -(0.14 + Math.random() * 0.34) * (0.5 + scale),
            life: 0,
            span: 2600 + Math.random() * 4200,
            size: (2.5 + Math.random() * 5) * (0.55 + scale * 0.5),
            drift: Math.random() * Math.PI * 2,
        });
    }

    function drawCandle(candle, now, elapsed) {
        const age = elapsed - candle.igniteAt;
        if (age < 0) return;

        // Bloom: the flame comes up out of nothing rather than switching on.
        const bloom = easeOut(clamp01(age / beat(candle.hero ? 900 : 520)));
        if (bloom <= 0) return;

        const camera = 1 - 0.05 * (0.5 - 0.5 * Math.cos((now % CAMERA_CYCLE) / CAMERA_CYCLE * Math.PI * 2));
        const depth = candle.depth * camera;
        const scale = 1 / depth;
        const y = horizon + perspective * scale;
        const x = W / 2 + candle.wx * scale;
        const cupW = baseCupWidth * scale;

        if (y < horizon - 4 || y - cupW * 4 > H || x < -cupW * 3 || x > W + cupW * 3) return;

        const f = candle.phase + now * candle.speed;
        // Three frequencies rather than one: a single sine reads as a pulse, this reads
        // as a flame. Reduced motion takes the steady middle of it.
        const flicker = reduced ? 1
            : 0.80 + 0.20 * (Math.sin(f) * 0.55 + Math.sin(f * 2.3) * 0.3 + Math.sin(f * 5.7) * 0.15 + 0.35);
        const lean = reduced ? 0 : Math.sin(f * 0.6) * 0.5 * candle.sway;

        const flameH = cupW * 0.80 * bloom * (0.94 + flicker * 0.08);
        const flameW = cupW * 0.30 * (0.95 + flicker * 0.06);
        const flameY = y - cupW * 0.12;

        ctx.globalCompositeOperation = 'lighter';

        // Halo. Kept deliberately faint: these composite additively and a dense field puts
        // dozens on the same pixel, so what looks right on one candle saturates on fifty.
        const glowSize = cupW * 3.0 * (0.9 + flicker * 0.15);
        ctx.globalAlpha = 0.30 * bloom * flicker;
        ctx.drawImage(sprites.glow, x - glowSize / 2, flameY - flameH * 0.55 - glowSize / 2, glowSize, glowSize);

        // The pool of light the candle throws onto the surface it stands on.
        ctx.globalAlpha = 0.09 * bloom * flicker;
        ctx.drawImage(sprites.glow, x - cupW * 1.5, y - cupW * 0.4, cupW * 3, cupW * 1.1);

        // Cup. The one thing that occludes rather than adds.
        ctx.globalCompositeOperation = 'source-over';
        ctx.globalAlpha = Math.min(1, bloom * 1.4);
        const cupH = cupW * 0.42;
        ctx.drawImage(sprites.cup, x - cupW / 2, y - cupH * 0.34, cupW, cupH);

        // Flame, and its reflection in the cup's rim below it.
        ctx.globalCompositeOperation = 'lighter';
        const sprite = sprites.flames[(candle.frame + (reduced ? 0 : Math.floor(now * candle.speed * 0.9))) % 5];

        ctx.globalAlpha = 0.14 * bloom * flicker;
        ctx.save();
        ctx.translate(x + lean * 0.4, y + cupH * 0.2);
        ctx.scale(1, -0.45);
        ctx.drawImage(sprite, -flameW / 2, -flameH, flameW, flameH);
        ctx.restore();

        ctx.globalAlpha = Math.min(1, bloom * flicker * 1.05);
        ctx.drawImage(sprite, x - flameW / 2 + lean, flameY - flameH, flameW, flameH);

        // Sparks lift off the bigger, nearer flames only — a spark from a candle four
        // rows back would be a pixel nobody sees, at the same cost as one that lands.
        if (!reduced && elapsed > T.embers && cupW > baseCupWidth * 0.16 && Math.random() < 0.05 * bloom) {
            spawnEmber(x + lean, flameY - flameH * 0.9, scale);
        }
    }

    function drawEmbers(dt, elapsed) {
        if (elapsed < T.embers) return;
        ctx.globalCompositeOperation = 'lighter';

        for (let i = embers.length - 1; i >= 0; i--) {
            const e = embers[i];
            // Sparks run on the sequence's clock too, so they still complete a rise and a
            // fade inside a scene that is half as long.
            e.life += dt * SPEED;
            if (e.life >= e.span) {
                embers.splice(i, 1);
                continue;
            }

            const u = e.life / e.span;
            e.drift += dt * 0.0012 * SPEED;
            e.x += (e.vx + Math.sin(e.drift) * 0.16) * dt * 0.06 * SPEED;
            e.y += e.vy * dt * 0.06 * SPEED;

            // Up bright, then fading as it cools and rises out of the light. The twinkle
            // rides the ember's own drift phase — rolling a fresh random each frame would
            // strobe rather than shimmer.
            ctx.globalAlpha = 0.6 * Math.sin(u * Math.PI) * (0.62 + 0.38 * Math.sin(e.drift * 7));
            const size = e.size * (1 - u * 0.35);
            ctx.drawImage(sprites.ember, e.x - size / 2, e.y - size / 2, size, size);
        }
    }

    function drawSky(now, elapsed) {
        const starsIn = clamp01((elapsed - T.stars) / beat(2200));
        const nebulaIn = clamp01((elapsed - T.nebula) / beat(2600));

        ctx.globalCompositeOperation = 'lighter';

        if (nebulaIn > 0) {
            ctx.globalAlpha = nebulaIn;
            ctx.drawImage(sprites.nebula, 0, 0, W, H);
        }

        if (starsIn > 0) {
            // The whole field lifts, wrapping on itself, so the sky is never still.
            const drift = reduced ? 0 : (now * 0.0035) % H;
            ctx.globalAlpha = starsIn;
            ctx.drawImage(sprites.stars, 0, -drift);
            ctx.drawImage(sprites.stars, 0, H - drift);

            if (!reduced) {
                for (let i = 0; i < twinklers.length; i++) {
                    const s = twinklers[i];
                    const a = 0.35 + 0.65 * Math.sin(s.phase + now * s.speed);
                    if (a <= 0.05) continue;
                    let y = s.y - drift;
                    if (y < 0) y += H;
                    ctx.globalAlpha = a * starsIn;
                    ctx.fillStyle = '#ffe9b8';
                    ctx.fillRect(s.x, y, s.size, s.size);
                }
            }
        }
    }

    let last = null;

    function frame(now) {
        raf = requestAnimationFrame(frame);

        if (started === null) started = now;
        const elapsed = now - started;
        const dt = last === null ? 16 : Math.min(now - last, 50);
        last = now;

        // If the device cannot hold a frame, give back resolution before giving back the
        // scene. Halving the buffer is invisible next to dropping to fifteen frames.
        if (quality > 0.7 && dt > 24) {
            if (++slowFrames > 40) {
                quality = 0.7;
                slowFrames = 0;
                layout();
            }
        } else if (dt <= 24) {
            slowFrames = 0;
        }

        ctx.globalCompositeOperation = 'source-over';
        ctx.globalAlpha = 1;
        ctx.drawImage(sprites.ground, 0, 0, W, H);

        drawSky(now, elapsed);
        for (let i = 0; i < candles.length; i++) drawCandle(candles[i], now, elapsed);
        drawEmbers(dt, elapsed);

        ctx.globalCompositeOperation = 'source-over';
        ctx.globalAlpha = 1;
        ctx.drawImage(sprites.vignette, 0, 0, W, H);

        // Leaves by itself once the field is complete. A tap or Escape still works at any
        // point before that, for anyone who does not want to wait it out.
        if (elapsed >= T.complete) close();
    }

    function onResize() {
        layout();
        // Depth is stored in world terms, so the field re-projects onto the new viewport
        // instead of being scattered again. Only the sky-space twinklers need pulling
        // back inside the new bounds.
        twinklers.forEach(s => {
            s.x = Math.min(s.x, W);
            s.y = Math.min(s.y, H * 0.8);
        });
    }

    function onKey(e) {
        if (e.key === 'Escape') close();
    }

    function onVisibility() {
        if (document.hidden) {
            cancelAnimationFrame(raf);
            raf = 0;
        } else if (!raf && !closing) {
            last = 0;
            raf = requestAnimationFrame(frame);
        }
    }

    function close() {
        if (closing) return;
        closing = 1;
        root.classList.add('is-closing');
        // Kept in step with the CSS fade, and the scene keeps drawing all the way through
        // it — tearing down on the first frame of the fade would leave a black rectangle
        // dissolving instead of a candlelit one.
        window.setTimeout(() => {
            cancelAnimationFrame(raf);
            window.removeEventListener('resize', onResize);
            window.removeEventListener('keydown', onKey);
            document.removeEventListener('visibilitychange', onVisibility);
            root.remove();
            document.documentElement.style.overflow = prevOverflow;
            if (lastFocus && lastFocus.focus) lastFocus.focus();
            active = null;
        }, 900);
    }

    layout();
    buildField();

    root.addEventListener('click', close);
    window.addEventListener('resize', onResize);
    window.addEventListener('keydown', onKey);
    document.addEventListener('visibilitychange', onVisibility);

    // The dim has to be finished by the time the field starts lighting, or the opening —
    // one flame alone in the dark — plays out behind a page that is still half visible
    // through it. At a gentle tempo there is room for the full seven hundred; as SPEED
    // rises the gap closes and the fade tightens to fit rather than swallowing the first
    // act. Floored so it stays a fade and never becomes a cut.
    const entryFade = Math.min(700, Math.max(250, T.fieldStart - T.fadeStart));
    root.style.setProperty('--memorial-scene-in', `${entryFade}ms`);

    // Held back so the tap's own burst is still on screen as the page dims around it.
    window.setTimeout(() => root.classList.add('is-lit'), T.fadeStart);
    root.focus({ preventScroll: true });
    raf = requestAnimationFrame(frame);

    active = { close };
    return close;
}
