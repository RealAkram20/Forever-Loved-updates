{{--
    Who the memorial belongs to. Reseller staff build the page; the family owns it.

    Two modes, because the old screen had only one and got it subtly wrong: it matched
    an existing account by typed email and then ignored the name field beside it, so a
    correction typed there vanished. Picking an existing client by id can't do that,
    and the email path now only ever creates.
--}}
@php
    $hasClients = $clients->isNotEmpty();
    // Reopen on the mode that failed validation, so a corrected submission doesn't
    // land the operator back on the other tab with their input apparently gone.
    $initialMode = old('client_id') ? 'existing' : (old('client_email') || ! $hasClients ? 'new' : ($hasClients ? 'existing' : 'new'));
@endphp

<x-common.component-card :title="($step ?? null) ? $step.'. Client' : 'Client'"
    desc="The family member who will own this memorial. They're invited by email and sign in with a code — no password to pass along.">
    <div class="space-y-5" x-data="{ mode: '{{ $initialMode }}' }">
        @if ($hasClients)
            <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 dark:border-gray-700 dark:bg-white/[0.03]">
                @foreach ([['existing', 'Existing client'], ['new', 'New client']] as [$value, $label])
                    <button type="button" @click="mode = '{{ $value }}'"
                        :class="mode === '{{ $value }}'
                            ? 'bg-white text-gray-800 shadow-theme-xs dark:bg-gray-800 dark:text-white/90'
                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                        class="rounded-md px-3.5 py-1.5 text-sm font-medium transition-colors duration-150">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        @endif

        <div x-show="mode === 'existing'" x-cloak>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="client_id">Client</label>
            <select id="client_id" name="client_id" x-bind:disabled="mode !== 'existing'"
                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900/80 dark:text-white/90">
                <option value="">Select a client…</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}" {{ (int) old('client_id') === (int) $client->id ? 'selected' : '' }}>
                        {{ $client->name }} — {{ $client->email }}
                    </option>
                @endforeach
            </select>
            @error('client_id')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div x-show="mode === 'new'" x-cloak class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="client_name">Client name</label>
                <input type="text" id="client_name" name="client_name" value="{{ old('client_name') }}"
                    x-bind:disabled="mode !== 'new'"
                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                @error('client_name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="client_email">Client email</label>
                <input type="email" id="client_email" name="client_email" value="{{ old('client_email') }}"
                    x-bind:disabled="mode !== 'new'"
                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                @error('client_email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>
</x-common.component-card>
