<?php

namespace App\Http\Requests;

use App\Models\Memorial;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The full memorial intake vocabulary — every field the shared form partials render.
 * One rule set for every door a memorial can be created through: the dashboard form
 * uses this directly, the reseller intake extends it with client and plan fields.
 */
class StoreMemorialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'relationship' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:255'],
            'primary_profession' => ['nullable', 'string', 'max:255'],
            'notable_title' => ['nullable', 'string', 'max:255'],
            'major_achievements' => ['nullable', 'string', 'max:2000'],
            'known_for' => ['nullable', 'string', 'max:500'],
            'active_year_start' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'active_year_end' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'date_of_birth' => ['nullable', 'date'],
            'date_of_passing' => ['nullable', 'date'],
            'birth_city' => ['nullable', 'string', 'max:255'],
            'birth_state' => ['nullable', 'string', 'max:255'],
            'birth_country' => ['nullable', 'string', 'max:255'],
            'death_city' => ['nullable', 'string', 'max:255'],
            'death_state' => ['nullable', 'string', 'max:255'],
            'death_country' => ['nullable', 'string', 'max:255'],
            'is_public' => ['nullable', 'boolean'],
            'biography' => ['nullable', 'string', 'max:50000'],
            'theme' => ['required', Rule::in(['free', 'premium', 'classic', 'modern', 'garden'])],
            'plan' => ['nullable', Rule::in(['free', 'paid'])],
            'companies' => ['nullable', 'array'],
            'companies.*.company_name' => ['nullable', 'string', 'max:255'],
            'co_founders' => ['nullable', 'array'],
            'co_founders.*.name' => ['nullable', 'string', 'max:255'],
            'children' => ['nullable', 'array'],
            'children.*.child_name' => ['nullable', 'string', 'max:255'],
            'children.*.birth_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'spouses' => ['nullable', 'array'],
            'spouses.*.spouse_name' => ['nullable', 'string', 'max:255'],
            'spouses.*.marriage_start_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'spouses.*.marriage_end_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'parents' => ['nullable', 'array'],
            'parents.*.parent_name' => ['nullable', 'string', 'max:255'],
            'parents.*.relationship_type' => ['nullable', Rule::in(['biological', 'adoptive'])],
            'siblings' => ['nullable', 'array'],
            'siblings.*.sibling_name' => ['nullable', 'string', 'max:255'],
            'education' => ['nullable', 'array'],
            'education.*.institution_name' => ['nullable', 'string', 'max:255'],
            'education.*.start_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'education.*.end_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'education.*.degree' => ['nullable', 'string', 'max:255'],
            'relationship_other' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Validated data with the form's split fields resolved into what the model
     * stores: "Other" relationships folded into `relationship`, and the unchecked-
     * checkbox convention for `is_public` defaulted to visible.
     */
    public function validatedPayload(): array
    {
        $payload = $this->validated();

        $payload['relationship'] = Memorial::resolveRelationship(
            $payload['relationship'] ?? null,
            $payload['relationship_other'] ?? null
        );
        unset($payload['relationship_other']);

        $payload['is_public'] = $this->boolean('is_public', true);

        return $payload;
    }
}
