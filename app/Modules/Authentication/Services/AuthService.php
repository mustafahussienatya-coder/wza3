<?php

namespace App\Modules\Authentication\Services;

use App\Modules\Authentication\Enums\LoginEvent;
use App\Modules\Authentication\Exceptions\AccountDeactivatedException;
use App\Modules\Authentication\Exceptions\CurrentPasswordIncorrectException;
use App\Modules\Authentication\Exceptions\InvalidCredentialsException;
use App\Modules\Authentication\Exceptions\InvalidPasswordResetTokenException;
use App\Modules\Authentication\Exceptions\TooManyLoginAttemptsException;
use App\Modules\Authentication\Models\LoginHistory;
use App\Modules\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    private const MAX_FAILED_ATTEMPTS = 5;

    private const LOCKOUT_MINUTES = 15;

    private const TOKEN_HOURS = 24;

    public function login(string $email, string $password, ?string $ip = null, ?string $userAgent = null): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            $this->recordFailedLogin($email, $ip, $userAgent);
            throw new InvalidCredentialsException;
        }

        $this->assertNotLocked($user, $email, $ip, $userAgent);

        if (! $user->is_active) {
            $this->recordLoginHistory($user, LoginEvent::FAILED_LOGIN, $ip, $userAgent);
            throw new AccountDeactivatedException;
        }

        $mustChangePassword = (bool) $user->must_change_password;

        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        $token = $user->createToken(
            'auth-token',
            ['*'],
            now()->addHours(self::TOKEN_HOURS)
        );

        $this->recordLoginHistory($user, LoginEvent::LOGIN, $ip, $userAgent);

        $data = [
            'user' => $user,
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => Carbon::now()->addHours(self::TOKEN_HOURS)->toISOString(),
            'must_change_password' => $mustChangePassword,
        ];

        return $data;
    }

    public function logout(User $user, ?string $ip = null, ?string $userAgent = null): void
    {
        $user->currentAccessToken()?->delete();
        $this->recordLoginHistory($user, LoginEvent::LOGOUT, $ip, $userAgent);
    }

    public function logoutAll(User $user, ?string $ip = null, ?string $userAgent = null): void
    {
        $user->tokens()->delete();
        $this->recordLoginHistory($user, LoginEvent::LOGOUT_ALL, $ip, $userAgent);
    }

    public function updatePassword(User $user, string $currentPassword, string $newPassword, ?string $ip = null, ?string $userAgent = null): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw new CurrentPasswordIncorrectException;
        }

        $user->update([
            'password' => $newPassword,
            'must_change_password' => false,
        ]);

        $this->recordLoginHistory($user, LoginEvent::PASSWORD_CHANGE, $ip, $userAgent);
    }

    public function resetPassword(string $email, string $token, string $newPassword, ?string $ip = null, ?string $userAgent = null): void
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            throw new InvalidCredentialsException;
        }

        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (! $resetRecord || ! Hash::check($token, $resetRecord->token)) {
            throw new InvalidPasswordResetTokenException;
        }

        if (Carbon::parse($resetRecord->created_at)->addMinutes(config('auth.passwords.users.expire', 60))->isPast()) {
            throw new InvalidPasswordResetTokenException;
        }

        $user->update([
            'password' => $newPassword,
            'must_change_password' => false,
        ]);

        DB::table('password_reset_tokens')
            ->where('email', $email)
            ->delete();

        $this->recordLoginHistory($user, LoginEvent::PASSWORD_RESET, $ip, $userAgent);
    }

    public static function isLocked(User $user): bool
    {
        return $user->locked_until !== null && now()->lessThan($user->locked_until);
    }

    private function assertNotLocked(User $user, string $email, ?string $ip, ?string $userAgent): void
    {
        if (self::isLocked($user)) {
            throw new TooManyLoginAttemptsException;
        }
    }

    private function recordFailedLogin(string $email, ?string $ip, ?string $userAgent): void
    {
        $user = User::where('email', $email)->first();

        if ($user) {
            $user->increment('failed_login_attempts');

            if ($user->failed_login_attempts >= self::MAX_FAILED_ATTEMPTS) {
                $user->forceFill([
                    'locked_until' => now()->addMinutes(self::LOCKOUT_MINUTES),
                ])->save();
            }

            $this->recordLoginHistory($user, LoginEvent::FAILED_LOGIN, $ip, $userAgent);
        } else {
            LoginHistory::create([
                'email' => $email,
                'event' => LoginEvent::FAILED_LOGIN->value,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'occurred_at' => now(),
            ]);
        }
    }

    private function recordLoginHistory(User $user, LoginEvent $event, ?string $ip, ?string $userAgent): void
    {
        LoginHistory::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'event' => $event->value,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'occurred_at' => now(),
        ]);
    }
}
