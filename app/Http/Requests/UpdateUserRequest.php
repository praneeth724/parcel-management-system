<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Rules\SriLankanMobile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'email' => [
                'required', 'email:rfc', 'max:191',
                Rule::unique('users', 'email')->ignore($target->id)->withoutTrashed(),
            ],
            // Blank means "leave the password alone".
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', new SriLankanMobile],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $target = $this->route('user');
            $role = UserRole::tryFrom((string) $this->input('role'));

            if ($role === null) {
                return;
            }

            if ($role->requiresBranch() && blank($this->input('branch_id'))) {
                $validator->errors()->add('branch_id', "A {$role->label()} must be assigned to a branch.");
            }

            // Changing someone's role is a Super Admin action only.
            if ($role !== $target->role && ! $this->user()->can('assignRole', $target)) {
                $validator->errors()->add('role', 'You are not allowed to change this user\'s role.');
            }

            // Never let the last active Super Admin be demoted or switched off,
            // which would lock everyone out of user administration.
            if ($target->isSuperAdmin() && ($role !== UserRole::SuperAdmin || ! $this->boolean('is_active'))) {
                $remaining = \App\Models\User::query()
                    ->where('role', UserRole::SuperAdmin)
                    ->where('is_active', true)
                    ->whereKeyNot($target->id)
                    ->count();

                if ($remaining === 0) {
                    $validator->errors()->add(
                        'role',
                        'This is the last active Super Admin. Promote another user before changing this account.'
                    );
                }
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
            'is_active' => $this->boolean('is_active'),
            'branch_id' => $this->user()->isSuperAdmin()
                ? $this->input('branch_id')
                : $this->route('user')->branch_id,
        ]);
    }

    /**
     * Validated data without a blank password, so an untouched password field
     * does not overwrite the stored hash.
     *
     * @return array<string, mixed>
     */
    public function userData(): array
    {
        $data = $this->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        return $data;
    }
}
