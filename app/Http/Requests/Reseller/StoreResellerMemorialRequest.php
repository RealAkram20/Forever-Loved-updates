<?php

namespace App\Http\Requests\Reseller;

use App\Http\Requests\StoreMemorialRequest;
use Illuminate\Validation\Rule;

/**
 * Reseller intake = the full memorial vocabulary plus who it belongs to and which of
 * the reseller's plans covers it. Client and plan ids are validated against this
 * reseller's own records — the same tenant-scoping discipline the signup wizard uses
 * for plan_id, because a bare exists() would accept another tenant's ids.
 */
class StoreResellerMemorialRequest extends StoreMemorialRequest
{
    public function authorize(): bool
    {
        return $this->user()?->reseller_id !== null;
    }

    public function rules(): array
    {
        $resellerId = $this->user()->reseller_id;

        return array_merge(parent::rules(), [
            // Either an existing client is picked by id, or a new one is described by
            // name + email. Ids beat typed emails: picking from a list can never
            // silently rename or hijack an account the way retyping an email could.
            'client_id' => [
                'required_without:client_name',
                'nullable',
                Rule::exists('users', 'id')->where('reseller_id', $resellerId),
            ],
            'client_name' => ['required_without:client_id', 'nullable', 'string', 'max:255'],
            'client_email' => ['required_without:client_id', 'nullable', 'email', 'max:255'],
            'plan_id' => [
                'nullable',
                Rule::exists('subscription_plans', 'id')
                    ->where('reseller_id', $resellerId)
                    ->where('is_active', true),
            ],
        ]);
    }

    public function messages(): array
    {
        return [
            'client_id.exists' => 'That client could not be found in your organisation.',
            'client_id.required_without' => 'Select a client, or switch to "New client" to add one.',
            'client_name.required_without' => 'Enter the client\'s name, or pick an existing client.',
            'client_email.required_without' => 'Enter the client\'s email, or pick an existing client.',
            'plan_id.exists' => 'That plan is not one of your active plans.',
        ];
    }
}
