@props(['resellers', 'current' => '', 'auto' => false])

{{-- The bare control behind x-admin.reseller-filter. Kept separate so the standalone and
     embedded forms share one option list and can't drift apart. --}}
<select name="reseller" @if ($auto) id="reseller-filter" onchange="this.form.submit()" @endif
    {{ $attributes->merge(['class' => 'h-11 w-full sm:w-auto rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden']) }}>
    <option value="">All owners</option>
    <option value="direct" @selected($current === 'direct')>Direct (platform)</option>
    @foreach ($resellers as $reseller)
        <option value="{{ $reseller->id }}" @selected($current === (string) $reseller->id)>{{ $reseller->name }}</option>
    @endforeach
</select>
