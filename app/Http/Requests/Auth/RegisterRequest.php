<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Rules\SriLankanMobile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            // `rfc` only, not `rfc,dns`: a DNS lookup makes registration depend
            // on network availability and rejects valid addresses on domains
            // without an MX record. The verification email is what actually
            // proves the address is reachable.
            'email' => ['required', 'string', 'email:rfc', 'max:191', Rule::unique('users', 'email')->withoutTrashed()],
            'phone' => ['nullable', 'string', new SriLankanMobile],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->whereNull('deleted_at')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'terms.accepted' => 'You must accept the terms of use to create an account.',
            'email.unique' => 'An account with this email address already exists.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'branch_id' => 'branch',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->email) ? mb_strtolower(trim($this->email)) : $this->email,
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'phone' => SriLankanMobile::normalize($this->input('phone')),
        ]);
    }
}
