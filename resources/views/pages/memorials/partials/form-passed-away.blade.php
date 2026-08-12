{{-- Passing. Same cascading country/state/city trio as birth, without the nationality hint. --}}
<x-common.component-card :title="($step ?? null) ? $step.'. Passed Away' : 'Passed Away'">
    <div class="space-y-5">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Date of passing</label>
            <x-form.date-picker id="date_of_passing" name="date_of_passing" placeholder="Select date"
                :defaultDate="old('date_of_passing')" />
        </div>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div>
                <x-form.country-select id="create_death_country" name="death_country" label="Country"
                    :value="old('death_country')" />
            </div>
            <div>
                <x-form.state-select id="create_death_state" name="death_state"
                    :value="old('death_state')" countryFieldId="create_death_country" />
            </div>
            <div>
                <x-form.city-select id="create_death_city" name="death_city"
                    :value="old('death_city')" stateFieldId="create_death_state" />
            </div>
        </div>
    </div>
</x-common.component-card>
