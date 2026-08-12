{{--
    Birth. The country field carries :emitNationality, and the Identity partial listens
    for that event keyed on this exact id — keep create_birth_country in step with it.
--}}
<x-common.component-card :title="($step ?? null) ? $step.'. Birth Information' : 'Birth Information'">
    <div class="space-y-5">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Date of birth</label>
            <x-form.date-picker id="date_of_birth" name="date_of_birth" placeholder="Select date"
                :defaultDate="old('date_of_birth')" />
        </div>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div>
                <x-form.country-select id="create_birth_country" name="birth_country" label="Country"
                    :value="old('birth_country')" :autoDetect="true" :emitNationality="true" />
            </div>
            <div>
                <x-form.state-select id="create_birth_state" name="birth_state"
                    :value="old('birth_state')" countryFieldId="create_birth_country" />
            </div>
            <div>
                <x-form.city-select id="create_birth_city" name="birth_city"
                    :value="old('birth_city')" stateFieldId="create_birth_state" />
            </div>
        </div>
    </div>
</x-common.component-card>
