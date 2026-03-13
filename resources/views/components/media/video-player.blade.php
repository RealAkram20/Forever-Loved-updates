@props(['src', 'caption' => null])

<div x-data="{
        playing: false,
        muted: false,
        volume: 1,
        currentTime: 0,
        duration: 0,
        buffered: 0,
        loading: true,
        fullscreen: false,
        showControls: true,
        controlTimeout: null,
        dragging: false,
        showVolume: false,
        get progress() { return this.duration ? (this.currentTime / this.duration) * 100 : 0 },
        get bufferProgress() { return this.duration ? (this.buffered / this.duration) * 100 : 0 },
        formatTime(s) {
            if (isNaN(s)) return '0:00';
            const m = Math.floor(s / 60);
            const sec = Math.floor(s % 60);
            return m + ':' + (sec < 10 ? '0' : '') + sec;
        },
        init() {
            const v = this.$refs.video;
            v.addEventListener('loadedmetadata', () => { this.duration = v.duration; this.loading = false; });
            v.addEventListener('timeupdate', () => { if (!this.dragging) this.currentTime = v.currentTime; });
            v.addEventListener('progress', () => {
                if (v.buffered.length > 0) this.buffered = v.buffered.end(v.buffered.length - 1);
            });
            v.addEventListener('ended', () => { this.playing = false; });
            v.addEventListener('waiting', () => { this.loading = true; });
            v.addEventListener('canplay', () => { this.loading = false; });
        },
        toggle() {
            const v = this.$refs.video;
            if (v.paused) { v.play(); this.playing = true; } else { v.pause(); this.playing = false; }
        },
        seek(e) {
            const rect = this.$refs.progressBar.getBoundingClientRect();
            const pct = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
            this.$refs.video.currentTime = pct * this.duration;
            this.currentTime = this.$refs.video.currentTime;
        },
        setVolume(e) {
            const rect = this.$refs.volumeBar.getBoundingClientRect();
            const pct = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
            this.volume = pct;
            this.$refs.video.volume = pct;
            this.muted = pct === 0;
        },
        toggleMute() {
            this.muted = !this.muted;
            this.$refs.video.muted = this.muted;
        },
        toggleFullscreen() {
            const el = this.$refs.container;
            if (!document.fullscreenElement) {
                el.requestFullscreen?.() || el.webkitRequestFullscreen?.();
                this.fullscreen = true;
            } else {
                document.exitFullscreen?.() || document.webkitExitFullscreen?.();
                this.fullscreen = false;
            }
        },
        scheduleHide() {
            clearTimeout(this.controlTimeout);
            this.showControls = true;
            if (this.playing) {
                this.controlTimeout = setTimeout(() => { this.showControls = false; }, 2500);
            }
        }
    }"
    x-ref="container"
    @mousemove="scheduleHide()" @mouseleave="if (playing) showControls = false"
    @fullscreenchange.window="fullscreen = !!document.fullscreenElement"
    class="memorial-video-player group relative overflow-hidden rounded-xl bg-gray-900 shadow-lg">

    {{-- Video element --}}
    <video x-ref="video" preload="metadata" playsinline
        @click="toggle()" @dblclick="toggleFullscreen()"
        class="aspect-video w-full cursor-pointer object-contain bg-black">
        <source src="{{ $src }}" type="video/mp4">
    </video>

    {{-- Loading spinner --}}
    <div x-show="loading" x-cloak class="absolute inset-0 flex items-center justify-center bg-black/30 pointer-events-none">
        <div class="h-10 w-10 animate-spin rounded-full border-3 border-white/30 border-t-brand-400"></div>
    </div>

    {{-- Big center play button (when paused & controls visible) --}}
    <div x-show="!playing && showControls" x-cloak
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="scale-75 opacity-0" x-transition:enter-end="scale-100 opacity-100"
        @click="toggle()"
        class="absolute inset-0 flex cursor-pointer items-center justify-center">
        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-brand-500/90 text-white shadow-xl backdrop-blur-sm transition hover:bg-brand-600 hover:scale-110">
            <svg class="ml-1 h-7 w-7" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
        </div>
    </div>

    {{-- Bottom controls --}}
    <div x-show="showControls || !playing"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-full opacity-0"
        class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent px-3 pb-3 pt-10">

        {{-- Progress bar --}}
        <div x-ref="progressBar"
            @mousedown.prevent="dragging = true; seek($event)"
            @mousemove="if (dragging) seek($event)"
            @mouseup="dragging = false"
            @mouseleave="dragging = false"
            class="group/progress mb-2.5 flex h-1.5 cursor-pointer items-center rounded-full bg-white/20 transition-all hover:h-2.5">
            <div class="pointer-events-none absolute left-0 h-full rounded-full bg-white/10" :style="'width: ' + bufferProgress + '%'"></div>
            <div class="pointer-events-none relative h-full rounded-full bg-brand-400 transition-all"
                :style="'width: ' + progress + '%'">
                <div class="absolute -right-1.5 -top-0.5 h-3.5 w-3.5 rounded-full border-2 border-white bg-brand-500 opacity-0 shadow transition group-hover/progress:opacity-100"></div>
            </div>
        </div>

        <div class="flex items-center gap-3 text-white">
            {{-- Play / Pause --}}
            <button type="button" @click="toggle()" class="shrink-0 transition hover:text-brand-300">
                <template x-if="!playing">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                </template>
                <template x-if="playing">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                </template>
            </button>

            {{-- Time --}}
            <span class="min-w-[80px] text-xs font-medium tabular-nums text-white/80" x-text="formatTime(currentTime) + ' / ' + formatTime(duration)"></span>

            <div class="flex-1"></div>

            {{-- Volume --}}
            <div class="relative flex items-center" @mouseenter="showVolume = true" @mouseleave="showVolume = false">
                <button type="button" @click="toggleMute()" class="shrink-0 transition hover:text-brand-300">
                    <template x-if="muted || volume === 0">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/></svg>
                    </template>
                    <template x-if="!muted && volume > 0">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072M18.364 5.636a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></svg>
                    </template>
                </button>
                <div x-show="showVolume" x-cloak x-transition class="ml-2 flex w-20 items-center">
                    <div x-ref="volumeBar" @click="setVolume($event)"
                        class="h-1 w-full cursor-pointer rounded-full bg-white/20">
                        <div class="h-full rounded-full bg-brand-400" :style="'width: ' + (muted ? 0 : volume * 100) + '%'"></div>
                    </div>
                </div>
            </div>

            {{-- Fullscreen --}}
            <button type="button" @click="toggleFullscreen()" class="shrink-0 transition hover:text-brand-300">
                <template x-if="!fullscreen">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                </template>
                <template x-if="fullscreen">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9V4H4m0 0l5 5M9 15v5H4m0 0l5-5m6-6V4h5m0 0l-5 5m5 6v5h-5m0 0l5-5"/></svg>
                </template>
            </button>
        </div>
    </div>

    {{-- Caption --}}
    @if ($caption)
        <div class="bg-gray-50 dark:bg-white/[0.03] px-3 py-2">
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $caption }}</p>
        </div>
    @endif
</div>
