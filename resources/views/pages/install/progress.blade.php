@extends('layouts.install')

@section('content')
    <div x-data="installRunner()">
        <h2 class="mb-1 text-lg font-semibold text-gray-900 dark:text-white">Installing</h2>
        <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">Please wait while the application is being set up. Do not close this page.</p>

        {{-- Progress Bar --}}
        <div class="mb-6">
            <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                <div class="h-full rounded-full bg-brand-500 transition-all duration-500 ease-out"
                    :style="'width: ' + progress + '%'"></div>
            </div>
            <p class="mt-2 text-center text-sm font-medium text-gray-600 dark:text-gray-300" x-text="progress + '%'"></p>
        </div>

        {{-- Steps Log --}}
        <div class="space-y-2">
            <template x-for="(step, i) in steps" :key="i">
                <div class="flex items-center gap-3 rounded-lg border px-4 py-2.5 transition-all"
                     :class="{
                        'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20': step.status === 'done',
                        'border-brand-200 bg-brand-50 dark:border-brand-800 dark:bg-brand-900/20': step.status === 'running',
                        'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20': step.status === 'error',
                     }">
                    <template x-if="step.status === 'done'">
                        <svg class="h-5 w-5 shrink-0 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </template>
                    <template x-if="step.status === 'running'">
                        <svg class="h-5 w-5 shrink-0 animate-spin text-brand-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </template>
                    <template x-if="step.status === 'error'">
                        <svg class="h-5 w-5 shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </template>
                    <span class="text-sm font-medium"
                          :class="{
                            'text-green-700 dark:text-green-400': step.status === 'done',
                            'text-brand-700 dark:text-brand-400': step.status === 'running',
                            'text-red-700 dark:text-red-400': step.status === 'error',
                          }" x-text="step.step"></span>
                </div>
            </template>
        </div>

        {{-- Waiting state before steps arrive --}}
        <template x-if="steps.length === 0 && !error">
            <div class="flex items-center justify-center gap-3 py-8">
                <svg class="h-5 w-5 animate-spin text-brand-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <span class="text-sm text-gray-500 dark:text-gray-400">Starting installation...</span>
            </div>
        </template>

        {{-- Error --}}
        <template x-if="error">
            <div class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/30">
                <p class="text-sm font-medium text-red-700 dark:text-red-400" x-text="error"></p>
                <a href="{{ route('install.requirements') }}"
                   class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
                    Restart Installation
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                </a>
            </div>
        </template>
    </div>

    <script>
        function installRunner() {
            return {
                steps: [],
                progress: 0,
                error: null,
                init() {
                    this.runInstall();
                },
                async runInstall() {
                    const stepDefs = [
                        { num: 1, label: 'Writing configuration file...' },
                        { num: 2, label: 'Generating application key...' },
                        { num: 3, label: 'Running database migrations...' },
                        { num: 4, label: 'Seeding data...' },
                        { num: 5, label: 'Creating admin account...' },
                        { num: 6, label: 'Creating storage link...' },
                        { num: 7, label: 'Optimizing application...' },
                        { num: 8, label: 'Finalizing installation...' },
                    ];

                    const lastCompleted = {{ (int) ($installLastCompletedStep ?? 0) }};
                    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                    const baseUrl = '{{ url("/install/execute") }}';

                    this.progress = Math.round((lastCompleted / stepDefs.length) * 100);

                    for (let i = 0; i < stepDefs.length; i++) {
                        const def = stepDefs[i];

                        if (def.num <= lastCompleted) {
                            this.steps.push({ step: def.label, status: 'done' });
                            continue;
                        }

                        this.steps.push({ step: def.label, status: 'running' });
                        this.progress = Math.round((i / stepDefs.length) * 100);

                        try {
                            const resp = await fetch(baseUrl + '/' + def.num, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': csrfToken,
                                    'Accept': 'application/json',
                                },
                            });

                            let data;
                            const contentType = resp.headers.get('content-type') || '';
                            if (contentType.includes('application/json')) {
                                data = await resp.json();
                            } else {
                                await resp.text();
                                data = { success: false, message: 'Server error (HTTP ' + resp.status + '). If this persists, check storage/logs/laravel.log.' };
                            }

                            if (!resp.ok || !data.success) {
                                this.steps[this.steps.length - 1].status = 'error';
                                this.error = data.message || 'Step failed: ' + def.label;
                                return;
                            }

                            this.steps[this.steps.length - 1].status = 'done';
                        } catch (e) {
                            this.steps[this.steps.length - 1].status = 'error';
                            this.error = 'Network error on step: ' + def.label + (e.message ? ' (' + e.message + ')' : '');
                            return;
                        }
                    }

                    this.progress = 100;
                    setTimeout(() => {
                        window.location.href = '{{ route("install.complete") }}';
                    }, 1500);
                }
            };
        }
    </script>
@endsection
