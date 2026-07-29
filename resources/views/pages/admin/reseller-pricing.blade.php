@extends('layouts.app')

@section('content')
    @php
        $blank = [
            'id' => null,
            'name' => '',
            'slug' => '',
            'sort_order' => $tiers->count(),
            'annual_price' => '0',
            'memorial_profile_allowance' => null,
            'price_per_additional_profile' => '0',
            'storage_limit_gb' => null,
            'feature_embedding' => false,
            'feature_domain_routing' => false,
            'feature_business_analytics' => false,
            'is_active' => true,
        ];
    @endphp

    <div x-data="{
            open: {{ $errors->any() ? 'true' : 'false' }},
            mode: 'create',
            action: '{{ route('settings.reseller-tiers.store') }}',
            form: @js($blank),
            startCreate() {
                this.form = @js($blank);
                this.mode = 'create';
                this.action = '{{ route('settings.reseller-tiers.store') }}';
                this.open = true;
            },
            startEdit(tier) {
                this.form = Object.assign({}, tier);
                this.mode = 'edit';
                this.action = '{{ url('/settings/reseller-tiers') }}/' + tier.id;
                this.open = true;
            }
        }">

        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Pricing</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    What the platform charges resellers, and what each tier unlocks for them. Separate from the plans resellers sell to their own clients.
                </p>
            </div>
            <button type="button" @click="startCreate()" class="btn btn-primary btn-md">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                New tier
            </button>
        </div>

        @if (session('success'))
            <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-700 dark:text-green-400">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3 text-sm text-red-700 dark:text-red-400">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3">
                <p class="mb-1 text-sm font-medium text-red-700 dark:text-red-400">That tier couldn't be saved:</p>
                <ul class="list-inside list-disc text-sm text-red-600 dark:text-red-400">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        @if ($tiers->isEmpty())
            <div class="rounded-2xl border border-dashed border-gray-300 dark:border-gray-700 px-6 py-16 text-center">
                <svg class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"><path d="M3.75 11.44V5.25c0-.83.67-1.5 1.5-1.5h6.19c.4 0 .78.16 1.06.44l7.31 7.31a1.5 1.5 0 0 1 0 2.12l-6.19 6.19a1.5 1.5 0 0 1-2.12 0l-7.31-7.31a1.5 1.5 0 0 1-.44-1.06Z"/><circle cx="8.25" cy="8.25" r="1.25" fill="currentColor" stroke="none"/></svg>
                <p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-300">No tiers yet</p>
                <p class="mx-auto mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">A tier sets a reseller's annual price, how many memorial profiles they get, and which platform features they can use.</p>
                <button type="button" @click="startCreate()" class="btn btn-primary btn-md mt-5">Create the first tier</button>
            </div>
        @else
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($tiers as $tier)
                    <div class="flex flex-col rounded-2xl border bg-white dark:bg-white/[0.03] p-6 transition-colors duration-150 {{ $tier->is_active ? 'border-gray-200 dark:border-gray-800' : 'border-dashed border-gray-300 dark:border-gray-700' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-base font-medium text-gray-800 dark:text-white/90">{{ $tier->name }}</h3>
                                <p class="mt-0.5 font-mono text-xs text-gray-400 dark:text-gray-500">{{ $tier->slug }}</p>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1.5">
                                @unless ($tier->is_active)
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[0.6875rem] font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">Inactive</span>
                                @endunless
                                @if ($defaultTierId === $tier->id)
                                    <span class="inline-flex items-center rounded-full bg-brand-50 px-2 py-0.5 text-[0.6875rem] font-medium text-brand-600 ring-1 ring-inset ring-brand-500/20 dark:bg-brand-500/10 dark:text-brand-400">Default</span>
                                @endif
                            </div>
                        </div>

                        <p class="mt-4 text-3xl font-semibold tabular-nums text-gray-800 dark:text-white/90">
                            {{ \App\Helpers\PriceHelper::format($tier->annual_price) }}<span class="ml-1 text-sm font-normal text-gray-400 dark:text-gray-500">/yr</span>
                        </p>

                        <dl class="mt-5 space-y-2.5 border-t border-gray-100 dark:border-gray-800 pt-5 text-sm">
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-gray-500 dark:text-gray-400">Profiles included</dt>
                                <dd class="tabular-nums font-medium text-gray-800 dark:text-white/90">{{ $tier->memorial_profile_allowance !== null ? number_format($tier->memorial_profile_allowance) : 'Unlimited' }}</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-gray-500 dark:text-gray-400">Each additional</dt>
                                <dd class="tabular-nums font-medium text-gray-800 dark:text-white/90">{{ \App\Helpers\PriceHelper::format($tier->price_per_additional_profile) }}</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-gray-500 dark:text-gray-400">Storage</dt>
                                <dd class="tabular-nums font-medium text-gray-800 dark:text-white/90">{{ $tier->storage_limit_gb !== null ? $tier->storage_limit_gb.' GB' : 'Unlimited' }}</dd>
                            </div>
                        </dl>

                        <ul class="mt-5 space-y-2 border-t border-gray-100 dark:border-gray-800 pt-5 text-sm">
                            @foreach ([
                                'feature_embedding' => 'Embeddable memorial widget',
                                'feature_domain_routing' => 'Custom domain routing',
                                'feature_business_analytics' => 'Business analytics',
                            ] as $field => $label)
                                <li class="flex items-center gap-2.5 {{ $tier->{$field} ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400 dark:text-gray-600' }}">
                                    @if ($tier->{$field})
                                        <svg class="h-4 w-4 shrink-0 text-green-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                                    @else
                                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 12h12"/></svg>
                                    @endif
                                    {{ $label }}
                                </li>
                            @endforeach
                        </ul>

                        <div class="mt-6 flex items-center justify-between gap-3 border-t border-gray-100 dark:border-gray-800 pt-5">
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $tier->resellers_count }} {{ \Illuminate\Support\Str::plural('reseller', $tier->resellers_count) }}
                            </span>
                            <div class="flex items-center gap-2">
                                @php
                                    // Cast the decimals to strings so x-model round-trips them into
                                    // number inputs without Alpine reformatting the value.
                                    $payload = [
                                        'id' => $tier->id,
                                        'name' => $tier->name,
                                        'slug' => $tier->slug,
                                        'sort_order' => $tier->sort_order,
                                        'annual_price' => (string) $tier->annual_price,
                                        'memorial_profile_allowance' => $tier->memorial_profile_allowance,
                                        'price_per_additional_profile' => (string) $tier->price_per_additional_profile,
                                        'storage_limit_gb' => $tier->storage_limit_gb,
                                        'feature_embedding' => $tier->feature_embedding,
                                        'feature_domain_routing' => $tier->feature_domain_routing,
                                        'feature_business_analytics' => $tier->feature_business_analytics,
                                        'is_active' => $tier->is_active,
                                    ];
                                @endphp
                                <button type="button" @click="startEdit({{ \Illuminate\Support\Js::from($payload) }})" class="btn btn-secondary btn-sm">Edit</button>

                                @if ($tier->resellers_count === 0)
                                    <form action="{{ route('settings.reseller-tiers.destroy', $tier) }}" method="POST" onsubmit="return confirm('Delete the {{ addslashes($tier->name) }} tier?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="rounded-lg p-2 text-gray-400 transition-colors duration-150 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400" title="Delete tier">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M9.5 7V5.5A1.5 1.5 0 0 1 11 4h2a1.5 1.5 0 0 1 1.5 1.5V7m3.5 0-.7 12.1a1.5 1.5 0 0 1-1.5 1.4H8.2a1.5 1.5 0 0 1-1.5-1.4L6 7"/></svg>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- One modal serves both create and edit; it's populated from the card that
             opened it, so adding a tier doesn't mean maintaining a second form. --}}
        <div x-show="open" x-cloak @keydown.escape.window="open = false" class="fixed inset-0 z-99999 flex items-start justify-center overflow-y-auto p-4 sm:p-6">
            <div x-show="open" x-transition:enter="transition-opacity duration-200 ease-out" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity duration-150 ease-out" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                @click="open = false" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm"></div>

            <div x-show="open"
                x-transition:enter="transition duration-200 ease-[cubic-bezier(0.23,1,0.32,1)]"
                x-transition:enter-start="opacity-0 scale-[0.97]" x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition duration-150 ease-out"
                x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-[0.98]"
                class="relative my-auto w-full max-w-2xl rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-xl">

                <div class="flex items-start justify-between gap-4 px-6 py-5">
                    <div>
                        <h3 class="text-base font-medium text-gray-800 dark:text-white/90" x-text="mode === 'create' ? 'New tier' : 'Edit tier'"></h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Price, included quota, and the platform features this tier unlocks.</p>
                    </div>
                    <button type="button" @click="open = false" class="-mr-2 -mt-1 rounded-lg p-2 text-gray-400 transition-colors duration-150 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form :action="action" method="POST" class="border-t border-gray-100 dark:border-gray-800 p-6">
                    @csrf
                    <template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                            <input type="text" name="name" x-model="form.name" required placeholder="Professional"
                                class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Slug
                                <span x-show="mode === 'edit'" class="font-normal text-gray-400">(locked)</span>
                            </label>
                            {{-- Disabled on edit: the slug is an identity, and renaming it would
                                 silently orphan anything referencing the old one. --}}
                            <input type="text" name="slug" x-model="form.slug" :required="mode === 'create'" :disabled="mode === 'edit'" placeholder="professional"
                                class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 font-mono text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden disabled:opacity-50" />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Annual price</label>
                            <input type="number" name="annual_price" x-model="form.annual_price" step="0.01" min="0" required
                                class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 text-sm tabular-nums text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Price per additional profile</label>
                            <input type="number" name="price_per_additional_profile" x-model="form.price_per_additional_profile" step="0.01" min="0" required
                                class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 text-sm tabular-nums text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Profiles included</label>
                            <input type="number" name="memorial_profile_allowance" x-model="form.memorial_profile_allowance" min="0" placeholder="Leave blank for unlimited"
                                class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 text-sm tabular-nums text-gray-800 dark:text-white/90 placeholder:text-gray-400 placeholder:tabular-nums focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Storage limit (GB)</label>
                            <input type="number" name="storage_limit_gb" x-model="form.storage_limit_gb" min="0" placeholder="Leave blank for unlimited"
                                class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 text-sm tabular-nums text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                        </div>

                        <div class="sm:col-span-2">
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Sort order</label>
                            <input type="number" name="sort_order" x-model="form.sort_order" min="0" required
                                class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 text-sm tabular-nums text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden sm:w-40" />
                            <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Low numbers come first — usually cheapest to most expensive.</p>
                        </div>

                        <div class="space-y-3 border-t border-gray-100 dark:border-gray-800 pt-5 sm:col-span-2">
                            @foreach ([
                                ['feature_embedding', 'Embeddable memorial widget', "Lets them drop a memorial onto their own website with an iframe."],
                                ['feature_domain_routing', 'Custom domain routing', 'Lets them serve memorials from their own domain instead of a subdomain.'],
                                ['feature_business_analytics', 'Business analytics', 'Visitor and engagement reporting across their memorials.'],
                                ['is_active', 'Available for new resellers', 'Turn off to retire a tier without affecting whoever is already on it.'],
                            ] as [$field, $label, $help])
                                <label class="flex cursor-pointer items-start gap-3">
                                    <input type="hidden" name="{{ $field }}" value="0">
                                    <input type="checkbox" name="{{ $field }}" value="1" x-model="form.{{ $field }}"
                                        class="mt-0.5 h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                    <span>
                                        <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</span>
                                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $help }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" @click="open = false" class="btn btn-secondary btn-md">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-md" x-text="mode === 'create' ? 'Create tier' : 'Save changes'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
