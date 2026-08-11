/**
 * The full-screen prayer scene: light breaks from a pair of praying hands, and phrases
 * are spoken out of it — word by word, at the pace someone would actually say them —
 * drifting up through gold dust and candle flames before the whole thing lifts away.
 *
 * Canvas, for the same reason as the candle scene: over a thousand particles, sixty
 * flames and a fan of light rays is not a number of DOM nodes to animate. Sprites are
 * baked once at start-up and a frame is draw calls.
 *
 * Kept separate from the candle scene rather than sharing a base. The two overlap in
 * their open/close lifecycle and little else, and folding them together would mean
 * reworking a scene that is already verified. Worth extracting if a fourth one appears.
 *
 * Loaded on demand — nobody pays for this file until they send a prayer.
 */

// As in the candle scene: the narrative runs faster than its natural spacing, the beats
// are written at the pace they were designed at, and this is the one number to turn.
//
// Speech is deliberately exempt. How long a phrase takes to say is a property of the
// phrase, not of the scene around it, and scaling it would turn the words into a flicker
// nobody can read — which is the one thing this scene cannot afford.
const SPEED = 2;
const beat = (ms) => ms / SPEED;

const T = {
    fadeStart: beat(120),
    particles: beat(300),
    firstPhrase: beat(700),
    flames: beat(900),
    lastPhrase: beat(5600),
    complete: beat(8000),
};

const PHRASES = [
    'Comfort our hearts, oh God.',
    'Carry us through grief.',
    'Rest in peace.',
    'May their memory be a blessing.',
];

// Matches the project's own display stack, so if Playfair is ever actually loaded the
// scene picks it up without a change here. Until then it renders in Georgia italic,
// which is the closest thing to the brief's calligraphy that is guaranteed present.
const PHRASE_FONT = "italic 600 {size}px 'Playfair Display', Georgia, 'Times New Roman', serif";

// Held as triplets rather than hex so the sprite builder can vary their alpha. The
// brief's three golds: near-white, warm, and deep.
const GOLD = [[255, 247, 214], [255, 214, 107], [255, 184, 74]];


const clamp01 = (t) => (t < 0 ? 0 : t > 1 ? 1 : t);
const easeOut = (t) => 1 - Math.pow(1 - t, 3);
const rand = (min, max) => min + Math.random() * (max - min);

function makeCanvas(w, h) {
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.ceil(w));
    canvas.height = Math.max(1, Math.ceil(h));
    return canvas;
}

/**
 * How long each word of a phrase takes to say, and therefore when the next one starts.
 *
 * Longer words take longer, which is what separates a spoken line from a typewriter: the
 * gaps are uneven. Trailing punctuation earns its pause the same way it would out loud.
 */
function scorePhrase(text) {
    const words = [];
    let at = 0;

    text.split(' ').filter(Boolean).forEach((word) => {
        const letters = word.replace(/[^A-Za-z]/g, '').length || 1;
        words.push({ text: word, onset: at, spoken: false });
        at += 130 + letters * 52 + (/[,.;:!?]$/.test(word) ? 90 : 0);
    });

    return { words, duration: at };
}

/**
 * Soft round mote. Every particle and speck of dust in the scene is one of these scaled.
 * The falloff has to be gradual all the way out — a dot that holds full opacity to a third
 * of its radius reads as a hard disc, which is what makes gold dust look like confetti.
 */
function makeMoteSprite(size, rgb) {
    const canvas = makeCanvas(size, size);
    const ctx = canvas.getContext('2d');
    const r = size / 2;
    const [red, green, blue] = rgb;
    const grad = ctx.createRadialGradient(r, r, 0, r, r, r);
    grad.addColorStop(0, `rgba(${red}, ${green}, ${blue}, 1)`);
    grad.addColorStop(0.22, `rgba(${red}, ${green}, ${blue}, 0.5)`);
    grad.addColorStop(0.5, `rgba(${red}, ${green}, ${blue}, 0.12)`);
    grad.addColorStop(1, `rgba(${red}, ${green}, ${blue}, 0)`);
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, size, size);
    return canvas;
}

function makeGlowSprite(size) {
    const canvas = makeCanvas(size, size);
    const ctx = canvas.getContext('2d');
    const r = size / 2;
    const grad = ctx.createRadialGradient(r, r, 0, r, r, r);
    grad.addColorStop(0, 'rgba(255, 247, 214, 0.95)');
    grad.addColorStop(0.10, 'rgba(255, 214, 107, 0.55)');
    grad.addColorStop(0.30, 'rgba(255, 184, 74, 0.18)');
    grad.addColorStop(0.60, 'rgba(255, 150, 40, 0.05)');
    grad.addColorStop(1, 'rgba(255, 140, 0, 0)');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, size, size);
    return canvas;
}

/**
 * The fan of light. Individually drawn tapered rays rather than a blurred cone, because
 * what reads as volumetric is the streakiness — a smooth cone just looks like a gradient.
 * Baked at half the size it is drawn at; nothing here has an edge sharp enough to tell.
 */
function makeRaysSprite(size) {
    const canvas = makeCanvas(size, size);
    const ctx = canvas.getContext('2d');
    const c = size / 2;

    ctx.globalCompositeOperation = 'lighter';

    for (let i = 0; i < 150; i++) {
        const angle = Math.random() * Math.PI * 2;
        const length = c * rand(0.30, 1.0);
        const spread = rand(0.004, 0.020);
        const bright = rand(0.05, 0.20);

        const grad = ctx.createLinearGradient(c, c, c + Math.cos(angle) * length, c + Math.sin(angle) * length);
        grad.addColorStop(0, `rgba(255, 240, 190, ${bright})`);
        grad.addColorStop(0.35, `rgba(255, 205, 100, ${bright * 0.5})`);
        grad.addColorStop(1, 'rgba(255, 170, 40, 0)');

        ctx.beginPath();
        ctx.moveTo(c, c);
        ctx.lineTo(c + Math.cos(angle - spread) * length, c + Math.sin(angle - spread) * length);
        ctx.lineTo(c + Math.cos(angle + spread) * length, c + Math.sin(angle + spread) * length);
        ctx.closePath();
        ctx.fillStyle = grad;
        ctx.fill();
    }

    return canvas;
}

function makeFlameSprite(w, h) {
    const canvas = makeCanvas(w, h);
    const ctx = canvas.getContext('2d');
    const cx = w / 2;

    ctx.beginPath();
    ctx.moveTo(cx, 0);
    ctx.bezierCurveTo(cx + w * 0.45, h * 0.42, cx + w * 0.42, h * 0.84, cx, h);
    ctx.bezierCurveTo(cx - w * 0.42, h * 0.84, cx - w * 0.45, h * 0.42, cx, 0);
    ctx.closePath();
    const grad = ctx.createLinearGradient(0, h, 0, 0);
    grad.addColorStop(0, 'rgba(255, 140, 20, 0.85)');
    grad.addColorStop(0.45, '#FFD66B');
    grad.addColorStop(1, '#FFF7D6');
    ctx.fillStyle = grad;
    ctx.fill();

    return canvas;
}

/**
 * A warm darkening behind the light.
 *
 * The brief calls for a transparent background, and against the dark board it was drawn
 * on that works. On the memorial page it does not: this page is light, and additive gold
 * on white is invisible. So the page is dimmed rather than replaced — thinnest at the
 * hands, where the light is doing the work anyway, and deepest at the edges, where the
 * faintest gold has to read. The page stays visible through it.
 */
function makeScrim(w, h, sourceX, sourceY) {
    const canvas = makeCanvas(w, h);
    const ctx = canvas.getContext('2d');
    const grad = ctx.createRadialGradient(
        sourceX, sourceY, 0,
        sourceX, sourceY, Math.max(w, h) * 0.95,
    );
    // Violet rather than a warm near-black, to match the candle scene's ground and the
    // artwork the light comes out of. Still dark: everything above it is additive gold, and
    // this is what that gold is adding to.
    grad.addColorStop(0, 'rgba(48, 30, 70, 0.58)');
    grad.addColorStop(0.35, 'rgba(33, 20, 50, 0.88)');
    grad.addColorStop(1, 'rgba(17, 10, 27, 0.965)');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, w, h);
    return canvas;
}

let active = null;

/**
 * Open the scene. Returns a function that closes it; a no-op if one is already up.
 *
 * @param {{originX?: number, artSrc?: string|null}} opts  Where the tap landed, and the
 *        card's own artwork, so the light rises out of the same hands that were pressed.
 */
export function playPrayerScene(opts = {}) {
    if (active) return active.close;

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const root = document.createElement('div');
    root.className = 'memorial-prayer-scene';
    root.setAttribute('role', 'dialog');
    root.setAttribute('aria-modal', 'true');
    root.setAttribute('aria-label', 'A prayer sent in memory');
    root.tabIndex = -1;

    const canvas = document.createElement('canvas');
    canvas.setAttribute('aria-hidden', 'true');
    root.appendChild(canvas);

    // The phrases are the meaning of this scene, and they exist only as pixels on a
    // canvas. Anyone not watching it gets them here instead.
    // Each carries its own lang, so a screen reader switches voice per line instead of
    // reading Arabic and Hindi through an English one.
    const spoken = document.createElement('p');
    spoken.className = 'sr-only';
    spoken.textContent = PHRASES.join(' ');
    root.appendChild(spoken);

    document.body.appendChild(root);

    const ctx = canvas.getContext('2d');

    const prevOverflow = document.documentElement.style.overflow;
    document.documentElement.style.overflow = 'hidden';
    const lastFocus = document.activeElement;

    let W = 0;
    let H = 0;
    let dpr = 1;
    let quality = 1;
    let sourceX = 0;
    let sourceY = 0;

    let sprites = null;
    let hands = null;
    let motes = [];
    let flames = [];
    let phrases = [];
    let pending = [];

    let started = null;
    let last = null;
    let raf = 0;
    let closing = 0;
    let slowFrames = 0;

    const wide = window.innerWidth >= 1024;
    const mid = window.innerWidth >= 640;
    const COUNTS = {
        motes: wide ? 1200 : mid ? 760 : 420,
        flames: wide ? 60 : mid ? 40 : 24,
        phrases: wide ? 18 : mid ? 14 : 10,
    };

    if (opts.artSrc) {
        const img = new Image();
        img.onload = () => { hands = img; };
        img.src = opts.artSrc;
        // The card that was just tapped is displaying this exact file, so it is already
        // decoded. Taking it synchronously puts the hands in the first frame; waiting on
        // onload would hold them back a frame or two into a scene this short, and the
        // light would visibly arrive before the thing it comes out of.
        if (img.complete && img.naturalWidth) hands = img;
    }

    function layout() {
        W = window.innerWidth;
        H = window.innerHeight;
        dpr = Math.min(window.devicePixelRatio || 1, 2) * quality;
        canvas.width = Math.ceil(W * dpr);
        canvas.height = Math.ceil(H * dpr);
        canvas.style.width = `${W}px`;
        canvas.style.height = `${H}px`;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        // The hands sit at the foot of the screen and everything radiates from them.
        sourceX = W / 2;
        sourceY = H * 0.86;

        sprites = {
            glow: makeGlowSprite(512),
            rays: makeRaysSprite(1024),
            flame: makeFlameSprite(24, 44),
            scrim: makeScrim(W, H, sourceX, sourceY),
            motes: GOLD.map(hex => makeMoteSprite(24, hex)),
        };
    }

    function buildField() {
        motes = [];
        for (let i = 0; i < COUNTS.motes; i++) motes.push(makeMote(true));

        flames = [];
        for (let i = 0; i < COUNTS.flames; i++) flames.push(makeFlame(true));

        phrases = [];
        pending = [];
        const window_ = T.lastPhrase - T.firstPhrase;
        for (let i = 0; i < COUNTS.phrases; i++) {
            pending.push({
                at: T.firstPhrase + (i / COUNTS.phrases) * window_ + rand(-60, 60),
                text: PHRASES[i % PHRASES.length],
            });
        }
    }

    function makeMote(seeded) {
        return {
            x: sourceX + rand(-W * 0.5, W * 0.5),
            // Seeded ones start scattered up the screen so the field is already alive
            // when the light arrives, rather than sweeping up from the floor.
            y: seeded ? rand(0, H) : sourceY + rand(-40, 40),
            vx: rand(-0.10, 0.10),
            vy: rand(-0.34, -0.08),
            size: rand(1.2, 5),
            sprite: Math.floor(Math.random() * 3),
            phase: Math.random() * Math.PI * 2,
            twinkle: rand(0.0012, 0.004),
            alpha: rand(0.35, 1),
        };
    }

    function makeFlame(seeded) {
        return {
            x: rand(0, W),
            y: seeded ? rand(0, H) : H + 30,
            vy: rand(-0.20, -0.07),
            // Small. These are meant to be candle flames seen at a distance, drifting
            // between the words; at any size where the shape resolves they stop reading
            // as flames and start reading as falling leaves.
            size: rand(0.20, 0.52),
            phase: Math.random() * Math.PI * 2,
            speed: rand(0.006, 0.013),
            sway: rand(0.10, 0.34),
        };
    }

    /**
     * Place a phrase somewhere it can be read.
     *
     * Positions fan outward from the hands, which is what makes the words look like they
     * came out of the light rather than being laid over it. Candidates are rejected
     * against everything currently on screen — overlapping calligraphy is unreadable, and
     * two phrases crossing at angles reads as a mistake.
     */
    function placePhrase(text, now) {
        let size = rand(0.6, 1.3) * Math.min(W, H) * 0.052;
        ctx.font = PHRASE_FONT.replace('{size}', size.toFixed(1));

        const score = scorePhrase(text);
        const measure = () => {
            const gap = ctx.measureText(' ').width;
            const widths = score.words.map((w) => ctx.measureText(w.text).width);
            const total = widths.reduce((a, b) => a + b, 0) + gap * Math.max(0, widths.length - 1);
            return { widths, gap, total };
        };

        let { widths, gap, total: lineWidth } = measure();

        // Shrink anything too wide to place, rather than letting it fail. Held well inside
        // the frame on purpose: a line allowed to run nearly edge to edge can only ever sit
        // dead centre, which leaves it nowhere to go — and on a narrow screen the longest
        // phrase is then dropped every time, because every candidate position crosses a
        // margin and gets rejected.
        const maxWidth = W * 0.72;
        if (lineWidth > maxWidth) {
            size *= maxWidth / lineWidth;
            ctx.font = PHRASE_FONT.replace('{size}', size.toFixed(1));
            ({ widths, gap, total: lineWidth } = measure());
        }

        let cursor = 0;
        score.words.forEach((word, i) => {
            word.x = cursor;
            cursor += widths[i] + gap;
        });

        const halfW = lineWidth / 2;
        const halfH = size * 0.7;

        const minX = W * 0.03 + halfW;
        const maxX = W * 0.97 - halfW;

        for (let attempt = 0; attempt < 14; attempt++) {
            const angle = rand(-1.32, 1.32);            // fanned either side of straight up
            const reach = rand(0.22, 0.86) * H;
            // Pulled back inside the frame rather than rejected for leaving it. A long line
            // fits entirely on screen only within a narrow band of x, and throwing away
            // every candidate outside that band means the longest phrase almost never
            // places at all.
            const x = Math.min(maxX, Math.max(minX, sourceX + Math.sin(angle) * reach * 0.92));
            const y = sourceY - Math.cos(angle) * reach;

            if (y - halfH < H * 0.04 || y + halfH > H * 0.80) continue;

            const clash = phrases.some((p) => (
                Math.abs(p.x - x) < (p.halfW + halfW) * 1.06
                && Math.abs(p.y - y) < (p.halfH + halfH) * 2.4
            ));
            if (clash) continue;

            return {
                text,
                words: score.words,
                speech: score.duration,
                lineWidth,
                size,
                x,
                y,
                halfW,
                halfH,
                // Static, set once. Rotation that animates makes a line you are still
                // reading move under you.
                rotation: rand(-0.24, 0.24),
                bornAt: now,
                // Hold and release are narrative, so they run on the scene's clock; the
                // speech they follow does not.
                life: score.duration + beat(600) + beat(1200),
                release: beat(1200),
                drift: rand(-0.02, 0.02),
            };
        }

        return null;
    }

    function puff(x, y, count) {
        for (let i = 0; i < count; i++) {
            motes.push({
                x: x + rand(-10, 10),
                y: y + rand(-8, 8),
                vx: rand(-0.22, 0.22),
                vy: rand(-0.42, -0.10),
                size: rand(1.5, 4.5),
                sprite: Math.floor(Math.random() * 3),
                phase: Math.random() * Math.PI * 2,
                twinkle: rand(0.002, 0.005),
                alpha: rand(0.5, 1),
            });
        }
    }

    function drawLight(now, elapsed) {
        const swell = easeOut(clamp01(elapsed / beat(800)));

        ctx.globalCompositeOperation = 'lighter';

        // Rays, turning almost imperceptibly. Anything faster reads as a spinning wheel.
        const raySize = Math.max(W, H) * 2.1 * (0.82 + swell * 0.18);
        ctx.save();
        ctx.translate(sourceX, sourceY);
        if (!reduced) ctx.rotate(now * 0.000024);
        ctx.globalAlpha = 0.85 * swell;
        ctx.drawImage(sprites.rays, -raySize / 2, -raySize / 2, raySize, raySize);
        ctx.restore();

        // Core bloom, breathing very slightly.
        const pulse = reduced ? 1 : 1 + Math.sin(now * 0.0011) * 0.05;
        const glowSize = Math.min(W, H) * 1.5 * swell * pulse;
        ctx.globalAlpha = 0.85;
        ctx.drawImage(sprites.glow, sourceX - glowSize / 2, sourceY - glowSize / 2, glowSize, glowSize);
    }

    function drawHands(elapsed) {
        if (!hands) return;
        const swell = easeOut(clamp01(elapsed / beat(700)));
        const w = Math.min(W * 0.30, H * 0.34);
        const h = w;
        ctx.globalCompositeOperation = 'source-over';
        ctx.globalAlpha = swell;
        ctx.drawImage(hands, sourceX - w / 2, sourceY - h * 0.44, w, h);
    }

    function drawMotes(dt, elapsed, now) {
        if (elapsed < T.particles) return;
        ctx.globalCompositeOperation = 'lighter';

        for (let i = motes.length - 1; i >= 0; i--) {
            const m = motes[i];
            if (!reduced) {
                m.x += (m.vx + Math.sin(now * 0.0004 + m.phase) * 0.08) * dt * 0.06 * SPEED;
                m.y += m.vy * dt * 0.06 * SPEED;
            }

            if (m.y < -20) {
                // Recycled rather than removed, so the field never thins out.
                motes[i] = makeMote(false);
                continue;
            }

            const twinkle = reduced ? 1 : 0.55 + 0.45 * Math.sin(m.phase + now * m.twinkle);
            ctx.globalAlpha = m.alpha * twinkle * 0.85;
            const s = m.size * 2.8;
            ctx.drawImage(sprites.motes[m.sprite], m.x - s / 2, m.y - s / 2, s, s);
        }
    }

    function drawFlames(dt, elapsed, now) {
        if (elapsed < T.flames) return;
        ctx.globalCompositeOperation = 'lighter';

        for (let i = 0; i < flames.length; i++) {
            const f = flames[i];
            if (!reduced) {
                f.y += f.vy * dt * 0.06 * SPEED;
                f.x += Math.sin(now * 0.0005 + f.phase) * f.sway * dt * 0.012 * SPEED;
            }
            if (f.y < -40) {
                flames[i] = makeFlame(false);
                continue;
            }

            const flicker = reduced ? 1 : 0.78 + 0.22 * (Math.sin(f.phase + now * f.speed) * 0.6 + Math.sin(now * f.speed * 2.7) * 0.4 + 0.4);
            const w = 24 * f.size * (0.94 + flicker * 0.08);
            const h = 44 * f.size * (0.9 + flicker * 0.14);

            // Its own small halo, or it reads as a paper cut-out rather than a flame.
            ctx.globalAlpha = 0.22 * flicker;
            const halo = h * 2.2;
            ctx.drawImage(sprites.glow, f.x - halo / 2, f.y - halo / 2, halo, halo);

            ctx.globalAlpha = 0.95 * flicker;
            ctx.drawImage(sprites.flame, f.x - w / 2, f.y - h, w, h);
        }
    }

    /**
     * Draw a phrase as it is being said.
     *
     * Each word arrives on its own beat: it fades up over a fifth of a second, rises the
     * last few pixels into place, and its glow spikes and settles. Nothing about the line
     * moves once a word has landed — a line that keeps shifting is a line you cannot read
     * — so the sense of being spoken comes from the timing of the arrivals and from the
     * gold dust each one throws off, not from motion of the text itself.
     */
    function drawPhrase(p, now) {
        const age = now - p.bornAt;
        const releasing = clamp01((age - (p.life - p.release)) / p.release);

        ctx.save();
        ctx.globalCompositeOperation = 'lighter';
        // Once said, the whole line lifts and fades, carrying its words with it.
        ctx.translate(p.x - p.lineWidth / 2, p.y - releasing * H * 0.10);
        ctx.rotate(p.rotation + p.drift * releasing);
        ctx.font = PHRASE_FONT.replace('{size}', p.size.toFixed(1));
        ctx.textBaseline = 'middle';
        ctx.shadowColor = 'rgba(255, 200, 90, 0.95)';

        for (let i = 0; i < p.words.length; i++) {
            const word = p.words[i];
            const said = age - word.onset;
            if (said < 0) break;                       // not spoken yet

            const arrive = easeOut(clamp01(said / 200));

            if (!word.spoken && !reduced) {
                word.spoken = true;
                // The visible part of the sound: a small breath of dust at the word.
                puff(p.x - p.lineWidth / 2 + word.x + 10, p.y, 5);
            }

            // Bright on the attack, settling as the word is held.
            ctx.shadowBlur = 14 + 30 * (1 - arrive);
            ctx.globalAlpha = arrive * (1 - releasing) * 0.95;
            ctx.fillStyle = '#FFD66B';
            ctx.fillText(word.text, word.x, (1 - arrive) * 5);

            // A second pass in near-white for the core of the letterforms. Kept light —
            // additive over the gold beneath it, any more and the words read white and
            // lose the warmth the rest of the scene is built on.
            ctx.shadowBlur = 6;
            ctx.globalAlpha = arrive * (1 - releasing) * 0.32;
            ctx.fillStyle = '#FFF7D6';
            ctx.fillText(word.text, word.x, (1 - arrive) * 5);
        }

        ctx.restore();
    }

    function frame(now) {
        raf = requestAnimationFrame(frame);

        if (started === null) started = now;
        const elapsed = now - started;
        const dt = last === null ? 16 : Math.min(now - last, 50);
        last = now;

        if (quality > 0.7 && dt > 24) {
            if (++slowFrames > 40) {
                quality = 0.7;
                slowFrames = 0;
                layout();
            }
        } else if (dt <= 24) {
            slowFrames = 0;
        }

        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.globalCompositeOperation = 'source-over';
        ctx.globalAlpha = 1;
        ctx.clearRect(0, 0, W, H);
        ctx.drawImage(sprites.scrim, 0, 0, W, H);

        drawLight(now, elapsed);
        drawMotes(dt, elapsed, now);
        drawFlames(dt, elapsed, now);

        while (pending.length && pending[0].at <= elapsed) {
            const next = pending.shift();
            const placed = placePhrase(next.text, now);
            if (placed) phrases.push(placed);
        }

        for (let i = phrases.length - 1; i >= 0; i--) {
            if (now - phrases[i].bornAt >= phrases[i].life) {
                phrases.splice(i, 1);
                continue;
            }
            drawPhrase(phrases[i], now);
        }

        drawHands(elapsed);

        if (elapsed >= T.complete) close();
    }

    function onResize() {
        layout();
    }

    function onKey(e) {
        if (e.key === 'Escape') close();
    }

    function onVisibility() {
        if (document.hidden) {
            cancelAnimationFrame(raf);
            raf = 0;
        } else if (!raf && !closing) {
            last = null;
            raf = requestAnimationFrame(frame);
        }
    }

    function close() {
        if (closing) return;
        closing = 1;
        root.classList.add('is-closing');
        window.setTimeout(() => {
            cancelAnimationFrame(raf);
            window.removeEventListener('resize', onResize);
            window.removeEventListener('keydown', onKey);
            document.removeEventListener('visibilitychange', onVisibility);
            root.remove();
            document.documentElement.style.overflow = prevOverflow;
            if (lastFocus && lastFocus.focus) lastFocus.focus();
            active = null;
        }, 1100);
    }

    layout();
    buildField();

    root.addEventListener('click', close);
    window.addEventListener('resize', onResize);
    window.addEventListener('keydown', onKey);
    document.addEventListener('visibilitychange', onVisibility);

    // The dim finishes before the first phrase is spoken, so no word is read through a
    // half-transparent page. Floored so it stays a fade rather than becoming a cut.
    const entryFade = Math.min(700, Math.max(250, T.firstPhrase - T.fadeStart));
    root.style.setProperty('--memorial-scene-in', `${entryFade}ms`);

    window.setTimeout(() => root.classList.add('is-lit'), T.fadeStart);
    root.focus({ preventScroll: true });
    raf = requestAnimationFrame(frame);

    active = { close };
    return close;
}
