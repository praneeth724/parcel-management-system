<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        $this->ensureRegistrationIsEnabled();

        return view('auth.register', [
            'branches' => Branch::active()->orderBy('name')->get(['id', 'name', 'code', 'city']),
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $this->ensureRegistrationIsEnabled();

        $requiresApproval = (bool) config('courier.registration.requires_approval');

        $user = DB::transaction(fn (): User => User::create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
            'phone' => $request->input('phone'),
            'branch_id' => $request->input('branch_id'),
            // Self-registration always lands on the least privileged role; a
            // Super Admin promotes the account afterwards.
            'role' => UserRole::from((string) config('courier.registration.default_role')),
            'is_active' => ! $requiresApproval,
        ]));

        event(new Registered($user));

        if ($requiresApproval) {
            return redirect()
                ->route('login')
                ->with('status', 'Your account has been created and is awaiting administrator approval.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('dashboard')
            ->with('success', "Welcome aboard, {$user->name}! Please verify your email address to unlock every feature.");
    }

    private function ensureRegistrationIsEnabled(): void
    {
        if (! config('courier.registration.enabled')) {
            throw new NotFoundHttpException('Self-registration is disabled.');
        }
    }
}
