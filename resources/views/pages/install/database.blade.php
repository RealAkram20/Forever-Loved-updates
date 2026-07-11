@extends('layouts.install')

@section('content')
    <h2 class="mb-1 text-lg font-semibold text-gray-900 dark:text-white">Database Configuration</h2>
    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">Enter your MySQL database credentials. The database will be created if it doesn't exist.</p>

    <form method="POST" action="{{ route('install.database.store') }}" id="dbForm" x-data="dbForm()">
        @csrf
        <div class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="host" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Database Host</label>
                    <input type="text" name="host" id="host" x-model="host" value="{{ old('host', $db['host']) }}"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-sm focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:border-brand-800" />
                    @error('host') <span class="mt-1 text-xs text-red-500">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="port" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Port</label>
                    <input type="number" name="port" id="port" x-model="port" value="{{ old('port', $db['port']) }}"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-sm focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:border-brand-800" />
                    @error('port') <span class="mt-1 text-xs text-red-500">{{ $message }}</span> @enderror
                </div>
            </div>
            <div>
                <label for="database" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Database Name</label>
                <input type="text" name="database" id="database" x-model="database" value="{{ old('database', $db['database']) }}"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-sm focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:border-brand-800" />
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Will be created automatically if it doesn't exist.</p>
                @error('database') <span class="mt-1 text-xs text-red-500">{{ $message }}</span> @enderror
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="username" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
                    <input type="text" name="username" id="username" x-model="username" value="{{ old('username', $db['username']) }}"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-sm focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:border-brand-800" />
                    @error('username') <span class="mt-1 text-xs text-red-500">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                    <input type="password" name="password" id="password" x-model="password" value="{{ old('password', $db['password']) }}"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-sm focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:border-brand-800" />
                    @error('password') <span class="mt-1 text-xs text-red-500">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- Test Connection --}}
        <div class="mt-5">
            <button type="button" @click="testConnection()"
                :disabled="testing"
                class="btn btn-secondary btn-md disabled:opacity-50">
                <svg class="h-4 w-4" :class="testing && 'animate-spin'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" /></svg>
                <span x-text="testing ? 'Testing...' : 'Test Connection'"></span>
            </button>
            <template x-if="testResult !== null">
                <p class="mt-2 text-sm" :class="testResult.success ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'" x-text="testResult.message"></p>
            </template>
        </div>

        {{-- Actions --}}
        <div class="mt-6 flex items-center justify-between border-t border-gray-200 pt-5 dark:border-gray-700">
            <a href="{{ route('install.requirements') }}"
               class="btn btn-secondary btn-md">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                Back
            </a>
            <button type="submit"
                class="btn btn-primary btn-md">
                Continue
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
            </button>
        </div>
    </form>

    <script>
        function dbForm() {
            return {
                host: '{{ old("host", $db["host"]) }}',
                port: '{{ old("port", $db["port"]) }}',
                database: '{{ old("database", $db["database"]) }}',
                username: '{{ old("username", $db["username"]) }}',
                password: '{{ old("password", $db["password"]) }}',
                testing: false,
                testResult: null,
                async testConnection() {
                    this.testing = true;
                    this.testResult = null;
                    try {
                        const resp = await fetch('{{ route("install.database.validate") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                host: this.host,
                                port: this.port,
                                database: this.database,
                                username: this.username,
                                password: this.password,
                            }),
                        });
                        this.testResult = await resp.json();
                    } catch (e) {
                        this.testResult = { success: false, message: 'Connection test failed: ' + e.message };
                    }
                    this.testing = false;
                }
            };
        }
    </script>
@endsection
