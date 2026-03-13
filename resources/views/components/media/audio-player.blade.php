@props(['src', 'caption' => null, 'filename' => null])

@php
    $displayName = $caption ?: ($filename ?: basename(parse_url($src, PHP_URL_PATH)));
    $extension = strtoupper(pathinfo($displayName, PATHINFO_EXTENSION) ?: 'MP3');
@endphp

<div x-data="{
        playing: false,
        currentTime: 0,
        duration: 0,
        volume: 1,
        muted: false,
        loading: true,
        dragging: false,
        showVolume: false,
        get progress() { return this.duration ? (this.currentTime / this.duration) * 100 : 0 },
        formatTime(s) {
            if (isNaN(s) || s === 0) return '0:00';
            const m = Math.floor(s / 60);
            const sec = Math.floor(s % 60);
            return m + ':' + (sec < 10 ? '0' : '') + sec;
        },
        init() {
            const a = this.$refs.audio;
            a.addEventListener('loadedmetadata', () => { this.duration = a.duration; this.loading = false; });
            a.addEventListener('timeupdate', () => { if (!this.dragging) this.currentTime = a.currentTime; });
            a.addEventListener('ended', () => { this.playing = false; this.currentTime = 0; });
            a.addEventListener('canplay', () => { this.loading = false; });
        },
        toggle() {
            const a = this.$refs.audio;
            if (a.paused) { a.play(); this.playing = true; } else { a.pause(); this.playing = false; }
        },
        seek(e) {
            const rect = this.$refs.progressBar.getBoundingClientRect();
            const pct = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
            this.$refs.audio.currentTime = pct * this.duration;
            this.currentTime = this.$refs.audio.currentTime;
        },
        setVolume(e) {
            const rect = this.$refs.volumeBar.getBoundingClientRect();
            const pct = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
            this.volume = pct;
            this.$refs.audio.volume = pct;
            this.muted = pct === 0;
        },
        toggleMute() {
            this.muted = !this.muted;
            this.$refs.audio.muted = this.muted;
        },
        skip(sec) {
            const a = this.$refs.audio;
            a.currentTime = Math.max(0, Math.min(a.duration, a.currentTime + sec));
        }
    }"
    class="memorial-audio-player overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700/50 bg-gradient-to-r from-brand-50 via-white to-brand-50/50 dark:from-brand-950/50 dark:via-gray-900 dark:to-brand-950/30 shadow-sm">

    <audio x-ref="audio" preload="metadata">
        <source src="{{ $src }}">
    </audio>

    <div class="flex items-center gap-3 p-3">
        {{-- Album art / waveform icon --}}
        <div class="relative flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-brand-500 shadow-md shadow-brand-500/30">
            {{-- Equalizer bars (animated when playing) --}}
            <div class="flex items-end gap-[3px] h-5">
                <span class="w-[3px] rounded-full bg-white/90" :class="playing ? 'audio-eq-bar audio-eq-bar-1' : 'h-1'"></span>
                <span class="w-[3px] rounded-full bg-white/90" :class="playing ? 'audio-eq-bar audio-eq-bar-2' : 'h-2'"></span>
                <span class="w-[3px] rounded-full bg-white/90" :class="playing ? 'audio-eq-bar audio-eq-bar-3' : 'h-3'"></span>
                <span class="w-[3px] rounded-full bg-white/90" :class="playing ? 'audio-eq-bar audio-eq-bar-4' : 'h-1.5'"></span>
                <span class="w-[3px] rounded-full bg-white/90" :class="playing ? 'audio-eq-bar audio-eq-bar-5' : 'h-2.5'"></span>
            </div>
            {{-- Loading overlay --}}
            <div x-show="loading" x-cloak class="absolute inset-0 flex items-center justify-center rounded-lg bg-brand-600/50">
                <div class="h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-white"></div>
            </div>
        </div>

        {{-- Track info + controls --}}
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                <p class="truncate text-sm font-medium text-gray-900 dark:text-white/90">{{ $displayName }}</p>
                <span class="shrink-0 rounded bg-brand-100 dark:bg-brand-500/20 px-1.5 py-0.5 text-[10px] font-semibold text-brand-600 dark:text-brand-400">{{ $extension }}</span>
            </div>

            {{-- Progress bar --}}
            <div class="mt-1.5 flex items-center gap-2.5">
                <span class="w-8 text-right text-[11px] tabular-nums text-gray-500 dark:text-gray-400" x-text="formatTime(currentTime)"></span>
                <div x-ref="progressBar"
                    @mousedown.prevent="dragging = true; seek($event)"
                    @mousemove="if (dragging) seek($event)"
                    @mouseup="dragging = false"
                    @mouseleave="dragging = false"
                    class="group/bar relative h-1.5 flex-1 cursor-pointer rounded-full bg-gray-200 dark:bg-white/10 transition-all hover:h-2">
                    <div class="absolute left-0 h-full rounded-full bg-brand-500 transition-all"
                        :style="'width: ' + progress + '%'">
                        <div class="absolute -right-1 -top-[3px] h-3 w-3 rounded-full border-2 border-white dark:border-gray-900 bg-brand-500 shadow opacity-0 transition group-hover/bar:opacity-100"></div>
                    </div>
                </div>
                <span class="w-8 text-[11px] tabular-nums text-gray-500 dark:text-gray-400" x-text="formatTime(duration)"></span>
            </div>
        </div>

        {{-- Playback controls --}}
        <div class="flex shrink-0 items-center gap-1">
            {{-- Skip back --}}
            <button type="button" @click="skip(-10)" class="rounded-full p-1.5 text-gray-500 dark:text-gray-400 transition hover:bg-brand-100 dark:hover:bg-brand-500/20 hover:text-brand-600 dark:hover:text-brand-400" title="Back 10s">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0019 16V8a1 1 0 00-1.6-.8l-5.333 4zM4.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0011 16V8a1 1 0 00-1.6-.8l-5.334 4z"/></svg>
            </button>

            {{-- Play / Pause --}}
            <button type="button" @click="toggle()"
                class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-500 text-white shadow-md shadow-brand-500/30 transition hover:bg-brand-600 hover:shadow-lg hover:shadow-brand-500/40 active:scale-95">
                <template x-if="!playing">
                    <svg class="ml-0.5 h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                </template>
                <template x-if="playing">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                </template>
            </button>

            {{-- Skip forward --}}
            <button type="button" @click="skip(10)" class="rounded-full p-1.5 text-gray-500 dark:text-gray-400 transition hover:bg-brand-100 dark:hover:bg-brand-500/20 hover:text-brand-600 dark:hover:text-brand-400" title="Forward 10s">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.933 12.8a1 1 0 000-1.6L6.6 7.2A1 1 0 005 8v8a1 1 0 001.6.8l5.333-4zM19.933 12.8a1 1 0 000-1.6l-5.333-4A1 1 0 0013 8v8a1 1 0 001.6.8l5.333-4z"/></svg>
            </button>

            {{-- Volume (inline horizontal slider) --}}
            <div class="flex items-center" @mouseenter="showVolume = true" @mouseleave="showVolume = false">
                <button type="button" @click="toggleMute()" class="rounded-full p-1.5 text-gray-500 dark:text-gray-400 transition hover:bg-brand-100 dark:hover:bg-brand-500/20 hover:text-brand-600 dark:hover:text-brand-400">
                    <template x-if="muted || volume === 0">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/></svg>
                    </template>
                    <template x-if="!muted && volume > 0">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></svg>
                    </template>
                </button>
                <div x-show="showVolume" x-cloak x-transition class="ml-1 flex w-16 items-center">
                    <div x-ref="volumeBar" @click="setVolume($event)"
                        class="h-1.5 w-full cursor-pointer rounded-full bg-gray-200 dark:bg-white/10">
                        <div class="h-full rounded-full bg-brand-500 transition-all" :style="'width: ' + (muted ? 0 : volume * 100) + '%'"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
