<?php

namespace Tests\Feature\Security;

use App\Livewire\ApiTokenManager;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guest_pages_and_protected_pages(): void
    {
        foreach (['/login', '/register', '/forgot-password'] as $uri) {
            $this->get($uri)->assertOk();
        }
        foreach (['/dashboard', '/profile', '/api-tokens'] as $uri) {
            $this->get($uri)->assertRedirect('/login');
        }
        $this->getJson('/api/v1/user')->assertUnauthorized();
    }

    public function test_registration_hashes_password_and_sends_verification(): void
    {
        Notification::fake();
        $this->post('/register', ['name' => 'Student', 'email' => 'student@example.test', 'password' => 'StrongPassword!123', 'password_confirmation' => 'StrongPassword!123'])->assertRedirect('/dashboard');
        $user = User::where('email', 'student@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('StrongPassword!123', $user->password));
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_login_logout_and_throttle(): void
    {
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login', ['email' => 'bad@example.test', 'password' => 'incorrect'])->assertStatus(422);
        }
        $this->postJson('/login', ['email' => 'bad@example.test', 'password' => 'incorrect'])->assertStatus(429);
    }

    public function test_reset_password_and_email_verification(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
        $token = Password::createToken($user);
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'ChangedPassword!123', 'password_confirmation' => 'ChangedPassword!123'])->assertRedirect('/login');
        $this->assertTrue(Hash::check('ChangedPassword!123', $user->fresh()->password));
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->actingAs($user)->get($url)->assertRedirect('/dashboard?verified=1');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_profile_password_and_account_deletion_revoke_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('old');
        $this->actingAs($user)->get('/profile')->assertOk();
        $this->get('/dashboard')->assertOk();
        $this->get('/api-tokens')->assertOk();
        $this->put('/user/profile-information', ['name' => 'Updated', 'email' => 'new@example.test'])->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->email_verified_at);
        $this->put('/user/password', ['current_password' => 'wrong', 'password' => 'NewPassword!123', 'password_confirmation' => 'NewPassword!123'])->assertSessionHasErrorsIn('updatePassword');
        $this->put('/user/password', ['current_password' => 'password', 'password' => 'NewPassword!123', 'password_confirmation' => 'NewPassword!123'])->assertSessionHasNoErrors();
        $this->delete('/profile', ['password' => 'NewPassword!123'])->assertRedirect('/');
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertGuest();
    }

    public function test_token_management_and_bearer_authentication(): void
    {
        $user = User::factory()->create();
        $component = Livewire::actingAs($user)->test(ApiTokenManager::class)->set('createApiTokenForm.name', 'Postman')->set('createApiTokenForm.permissions', ['read', 'invalid'])->call('createApiToken')->assertHasNoErrors();
        $plain = $component->get('plainTextToken');
        $token = $user->tokens()->firstOrFail();
        $this->assertSame(['read'], $token->abilities);
        $this->assertNotSame($plain, $token->token);
        auth()->forgetGuards();
        $this->withToken($plain)->getJson('/api/v1/user')->assertOk()->assertJsonPath('id', $user->id)->assertJsonMissingPath('two_factor_secret');
        $component->call('manageApiTokenPermissions', $token->id)->set('updateApiTokenForm.permissions', ['read', 'update'])->call('updateApiToken');
        $this->assertSame(['read', 'update'], $token->fresh()->abilities);
        $component->call('clearPlainTextToken')->assertSet('plainTextToken', null)->call('confirmApiTokenDeletion', $token->id)->call('deleteApiToken');
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
        auth()->forgetGuards();
        $this->withToken($plain)->getJson('/api/v1/user')->assertUnauthorized();
    }

    public function test_other_users_tokens_cannot_be_changed_or_deleted(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $token = $owner->createToken('Private')->accessToken;
        try {
            Livewire::actingAs($other)->test(ApiTokenManager::class)->call('manageApiTokenPermissions', $token->id);
            $this->fail('Foreign token accessible');
        } catch (ModelNotFoundException $e) {
            $this->assertTrue(true);
        }
        try {
            Livewire::actingAs($other)->test(ApiTokenManager::class)->set('apiTokenIdBeingDeleted', $token->id)->call('deleteApiToken');
            $this->fail('Foreign token deleted');
        } catch (ModelNotFoundException $e) {
            $this->assertTrue(true);
        }
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->id]);
    }

    public function test_two_factor_enrollment_challenge_and_single_use_recovery(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/user/two-factor-authentication')->assertRedirect('/user/confirm-password');
        $this->withSession(['auth.password_confirmed_at' => time()])->post('/user/two-factor-authentication')->assertSessionHasNoErrors();
        $user->refresh();
        $secret = decrypt($user->two_factor_secret);
        $this->get('/profile')->assertOk()->assertSee('Finish enabling');
        $this->post('/user/confirmed-two-factor-authentication', ['code' => 'invalid'])->assertSessionHasErrors();
        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->post('/user/confirmed-two-factor-authentication', ['code' => $code])->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertTrue($user->hasEnabledTwoFactorAuthentication());
        $recovery = $user->recoveryCodes()[0];
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        $this->get('/two-factor-challenge')->assertOk();
        $this->post('/two-factor-challenge', ['recovery_code' => $recovery])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNotContains($recovery, $user->fresh()->recoveryCodes());
        $this->withSession(['auth.password_confirmed_at' => time()])->delete('/user/two-factor-authentication')->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->two_factor_secret);
    }
}
