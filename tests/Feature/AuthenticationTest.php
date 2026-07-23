<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The authentication features listed in the specification: registration,
 * login, logout, forgot password, change password and email verification.
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
    }

    // -----------------------------------------------------------------
    // Registration
    // -----------------------------------------------------------------

    #[Test]
    public function a_visitor_can_register_and_lands_on_their_dashboard(): void
    {
        Notification::fake();

        $response = $this->post(route('register.store'), [
            'name' => 'Nimal Perera',
            'email' => 'nimal@swifttrack.lk',
            'phone' => '0771234567',
            'branch_id' => $this->branch->id,
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'terms' => '1',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $user = User::where('email', 'nimal@swifttrack.lk')->firstOrFail();

        // Self-registration always lands on the least privileged role.
        $this->assertSame(UserRole::Dispatcher, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    #[Test]
    public function registration_rejects_a_weak_password_and_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@swifttrack.lk']);

        $this->post(route('register.store'), [
            'name' => 'Nimal Perera',
            'email' => 'taken@swifttrack.lk',
            'password' => 'weak',
            'password_confirmation' => 'weak',
            'terms' => '1',
        ])->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    #[Test]
    public function registration_requires_the_terms_to_be_accepted(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Nimal Perera',
            'email' => 'nimal@swifttrack.lk',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertSessionHasErrors('terms');

        $this->assertGuest();
    }

    #[Test]
    public function registration_stores_the_mobile_number_in_the_local_format(): void
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'name' => 'Nimal Perera',
            'email' => 'nimal@swifttrack.lk',
            'phone' => '+94771234567',
            'branch_id' => $this->branch->id,
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'terms' => '1',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'nimal@swifttrack.lk',
            'phone' => '0771234567',
        ]);
    }

    // -----------------------------------------------------------------
    // Login and logout
    // -----------------------------------------------------------------

    #[Test]
    public function a_user_can_sign_in_with_correct_credentials(): void
    {
        $user = User::factory()->forBranch($this->branch)->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        // Sign-in is recorded for the audit trail.
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    #[Test]
    public function signing_in_with_the_wrong_password_fails(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function a_deactivated_account_is_told_why_it_cannot_sign_in(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrorsIn('default', ['email']);

        $this->assertGuest();

        $this->assertStringContainsString(
            'deactivated',
            session('errors')->first('email')
        );
    }

    #[Test]
    public function repeated_failed_attempts_are_throttled(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password', // correct, but locked out
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function a_user_can_sign_out(): void
    {
        $user = User::factory()->forBranch($this->branch)->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    #[Test]
    public function deactivating_a_user_mid_session_ends_that_session(): void
    {
        $user = User::factory()->forBranch($this->branch)->create();

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect();

        $user->update(['is_active' => false]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    // -----------------------------------------------------------------
    // Forgot / reset password
    // -----------------------------------------------------------------

    #[Test]
    public function a_reset_link_is_emailed_for_a_known_address(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    #[Test]
    public function an_unknown_address_gets_the_same_answer_so_accounts_cannot_be_enumerated(): void
    {
        Notification::fake();

        $known = User::factory()->create();

        $knownResponse = $this->post(route('password.email'), ['email' => $known->email]);
        $unknownResponse = $this->post(route('password.email'), ['email' => 'nobody@nowhere.lk']);

        $this->assertSame(
            $knownResponse->getSession()->get('status'),
            $unknownResponse->getSession()->get('status'),
            'The response must not reveal whether an email address is registered.'
        );

        Notification::assertNothingSentTo(new class extends User
        {
            public function routeNotificationForMail(): string
            {
                return 'nobody@nowhere.lk';
            }
        });
    }

    #[Test]
    public function a_password_can_be_reset_with_a_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        $token = null;

        Notification::assertSentTo($user, ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            });

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'BrandNew123',
            'password_confirmation' => 'BrandNew123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('BrandNew123', $user->fresh()->password));
    }

    #[Test]
    public function an_invalid_reset_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->post(route('password.update'), [
            'token' => 'not-a-real-token',
            'email' => $user->email,
            'password' => 'BrandNew123',
            'password_confirmation' => 'BrandNew123',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    // -----------------------------------------------------------------
    // Change password
    // -----------------------------------------------------------------

    #[Test]
    public function a_signed_in_user_can_change_their_password(): void
    {
        $user = User::factory()->forBranch($this->branch)->create();

        $this->actingAs($user)
            ->put(route('password.change.update'), [
                'current_password' => 'password',
                'password' => 'BrandNew123',
                'password_confirmation' => 'BrandNew123',
            ])
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('BrandNew123', $user->fresh()->password));
    }

    #[Test]
    public function changing_a_password_requires_the_current_one(): void
    {
        $user = User::factory()->forBranch($this->branch)->create();

        $this->actingAs($user)
            ->put(route('password.change.update'), [
                'current_password' => 'not-my-password',
                'password' => 'BrandNew123',
                'password_confirmation' => 'BrandNew123',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    #[Test]
    public function the_new_password_must_differ_from_the_current_one(): void
    {
        $user = User::factory()->forBranch($this->branch)->create();

        $this->actingAs($user)
            ->put(route('password.change.update'), [
                'current_password' => 'password',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertSessionHasErrors('password');
    }

    #[Test]
    public function changing_a_password_revokes_every_api_token(): void
    {
        $user = User::factory()->forBranch($this->branch)->create();
        $user->createToken('phone');
        $user->createToken('tablet');

        $this->assertSame(2, $user->tokens()->count());

        $this->actingAs($user)->put(route('password.change.update'), [
            'current_password' => 'password',
            'password' => 'BrandNew123',
            'password_confirmation' => 'BrandNew123',
        ]);

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    // -----------------------------------------------------------------
    // Email verification (bonus)
    // -----------------------------------------------------------------

    #[Test]
    public function a_signed_verification_link_verifies_the_address(): void
    {
        $user = User::factory()->unverified()->forBranch($this->branch)->create();

        $this->assertFalse($user->hasVerifiedEmail());

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)
            ->get($url)
            ->assertRedirect(route('dashboard'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    #[Test]
    public function a_tampered_verification_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->forBranch($this->branch)->create();

        // Correct route, wrong hash — and no valid signature.
        $this->actingAs($user)
            ->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1('someone@else.lk')]))
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    #[Test]
    public function a_verification_email_can_be_resent(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->forBranch($this->branch)->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertSessionHas('status');

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    #[Test]
    public function guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('parcels.index'))->assertRedirect(route('login'));
        $this->get(route('reports.index'))->assertRedirect(route('login'));
    }

    #[Test]
    public function a_signed_in_user_is_bounced_away_from_the_login_page(): void
    {
        $user = User::factory()->forBranch($this->branch)->create();

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect(route('dashboard'));
    }
}
