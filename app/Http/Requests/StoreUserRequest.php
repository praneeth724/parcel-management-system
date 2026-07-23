<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use App\Rules\SriLankanMobile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:191', Rule::unique('users', 'email')->withoutTrashed()],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', new SriLankanMobile],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $role = UserRole::tryFrom((string) $this->input('role'));

            if ($role === null) {
                return;
            }

            // Only a Super Admin operates outside a branch; every other role
            // needs one or their scoped queries would return nothing.
            if ($role->requiresBranch() && blank($this->input('branch_id'))) {
                $validator->errors()->add('branch_id', "A {$role->label()} must be assigned to a branch.");
            }

            // A Branch Manager may only create staff below their own rank.
            if (! $this->user()->isSuperAdmin()
                && ! in_array($role, [UserRole::Dispatcher, UserRole::Driver], strict: true)) {
                $validator->errors()->add('role', 'You may only create Dispatcher and Driver accounts.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['branch_id' => 'branch'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->email) ? mb_strtolower(trim($this->email)) : $this->email,
            'phone' => SriLankanMobile::normalize($this->input('phone')),
            'is_active' => $this->boolean('is_active', true),

            // A Branch Manager can only staff their own branch.
            'branch_id' => $this->user()->isSuperAdmin()
                ? $this->input('branch_id')
                : $this->user()->branch_id,
        ]);
    }
}
