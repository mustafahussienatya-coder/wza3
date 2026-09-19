<?php

namespace App\Modules\Authentication\Services;

use App\Modules\Authentication\Exceptions\AccountDeactivatedException;
use App\Modules\Authentication\Exceptions\InvalidPasswordResetTokenException;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\Password;

class PasswordResetService
{
    public function sendResetLink(string $email): bool
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            return true;
        }

        if (! $user->is_active) {
            throw new AccountDeactivatedException;
        }

        $status = Password::sendResetLink(['email' => $email]);

        return $status === Password::RESET_LINK_SENT;
    }

    public function reset(string $email, string $token, string $newPassword): void
    {
        $status = Password::reset(
            [
                'email' => $email,
                'token' => $token,
                'password' => $newPassword,
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'must_change_password' => false,
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new InvalidPasswordResetTokenException;
        }
    }
}
