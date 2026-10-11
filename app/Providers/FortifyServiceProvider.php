<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\Watchers\MailWatcher;
use Laravel\Telescope\Watchers\NotificationWatcher;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (class_exists(Telescope::class)) {
            Telescope::hideRequestHeaders(['authorization', 'cookie', 'x-csrf-token', 'x-xsrf-token']);
            Telescope::hideRequestParameters(['_token', 'password', 'password_confirmation', 'current_password', 'token', 'code', 'recovery_code']);
            Telescope::hideResponseParameters(['token', 'plainTextToken', 'two_factor_secret', 'two_factor_recovery_codes']);
            config(['telescope.ignore_paths' => array_merge(config('telescope.ignore_paths', []), [
                'profile', 'api-tokens', 'livewire/*', 'user/two-factor*', 'user/confirmed-two-factor*',
                'user/two-factor-qr-code', 'user/two-factor-secret-key', 'user/two-factor-recovery-codes',
                'reset-password/*', 'email/verify/*',
            ])]);
            config(['telescope.watchers.'.MailWatcher::class => false]);
            config(['telescope.watchers.'.NotificationWatcher::class => false]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip()
            );
        });
    }
}
