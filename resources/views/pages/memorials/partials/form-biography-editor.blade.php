{{--
    Rich-text biography. Optional at intake: if it is left empty, the creation service
    generates a structured one from the facts above, so no memorial is ever published
    with an empty story.
--}}
<x-common.component-card :title="($step ?? null) ? $step.'. Biography' : 'Biography'" desc="Write or paste a biography. You can also use AI to generate one after creation.">
    <div>
        <input type="hidden" name="biography" id="biography-hidden" value="{{ old('biography') }}" />
        <div id="create-biography-editor" class="min-h-[200px] rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900"></div>
        <p class="mt-2 text-xs text-gray-500">AI-generated biography options will be available after creating the memorial.</p>
    </div>
</x-common.component-card>
