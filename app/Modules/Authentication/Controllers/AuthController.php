<?php

namespace App\Modules\Authentication\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Modules\Authentication\Requests\ForgotPasswordRequest;
use App\Modules\Authentication\Requests\LoginRequest;
use App\Modules\Authentication\Requests\ResetPasswordRequest;
use App\Modules\Authentication\Requests\UpdatePasswordRequest;
use App\Modules\Authentication\Resources\UserResource;
use App\Modules\Authentication\Services\AuthService;
use App\Modules\Authentication\Services\PasswordResetService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Authentication')]
class AuthController extends BaseController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly PasswordResetService $passwordResetService
    ) {}

    /**
     * Login
     *
     * Authenticate a user with their email and password and return an access token.
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Login successful.",
     *   "data": {
     *     "user": {},
     *     "token": "string",
     *     "token_type": "Bearer",
     *     "expires_at": "2026-09-03T10:00:00+00:00",
     *     "must_change_password": false
     *   }
     * }
     * @response 401 {"success": false, "message": "Invalid credentials.", "error_code": "INVALID_CREDENTIALS"}
     * @response 403 {"success": false, "message": "Your account has been deactivated.", "error_code": "ACCOUNT_DEACTIVATED"}
     * @response 429 {"success": false, "message": "Too many failed login attempts. Account is temporarily locked.", "error_code": "TOO_MANY_LOGIN_ATTEMPTS"}
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->validated('email'),
            $request->validated('password'),
            $request->ip(),
            $request->userAgent()
        );

        activity('auth')
            ->performedOn($result['user'])
            ->event('login')
            ->withProperties(['ip' => $request->ip()])
            ->log('User logged in');

        return $this->successResponse([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'token_type' => $result['token_type'],
            'expires_at' => $result['expires_at'],
            'must_change_password' => $result['must_change_password'] ?? false,
        ], __('auth_messages.logged_in'));
    }

    /**
     * Get current user
     *
     * Return the authenticated user's profile and permissions.
     *
     * @response 200 {"success": true, "message": "User retrieved successfully.", "data": {}}
     * @response 401 {"success": false, "message": "Unauthenticated.", "error_code": "UNAUTHENTICATED"}
     * @response 403 {"success": false, "message": "Your account has been deactivated.", "error_code": "ACCOUNT_DEACTIVATED"}
     */
    public function me(Request $request): JsonResponse
    {
        return $this->resourceResponse(
            new UserResource($request->user()),
            __('auth_messages.user_retrieved')
        );
    }

    /**
     * Logout
     *
     * Revoke the current access token.
     *
     * @response 200 {"success": true, "message": "Logged out successfully."}
     * @response 401 {"success": false, "message": "Unauthenticated.", "error_code": "UNAUTHENTICATED"}
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout(
            $request->user(),
            $request->ip(),
            $request->userAgent()
        );

        activity('auth')
            ->performedOn($request->user())
            ->event('logout')
            ->withProperties(['ip' => $request->ip()])
            ->log('User logged out');

        return $this->successResponse(message: __('auth_messages.logged_out'));
    }

    /**
     * Logout from all devices
     *
     * Revoke all access tokens for the authenticated user.
     *
     * @response 200 {"success": true, "message": "Logged out from all devices."}
     * @response 401 {"success": false, "message": "Unauthenticated.", "error_code": "UNAUTHENTICATED"}
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $this->authService->logoutAll(
            $request->user(),
            $request->ip(),
            $request->userAgent()
        );

        activity('auth')
            ->performedOn($request->user())
            ->event('logout_all')
            ->withProperties(['ip' => $request->ip()])
            ->log('User logged out from all devices');

        return $this->successResponse(message: __('auth_messages.logged_out_all'));
    }

    /**
     * Update password
     *
     * Change the password for the authenticated user using the current password.
     *
     * @response 200 {"success": true, "message": "Password updated successfully."}
     * @response 401 {"success": false, "message": "Unauthenticated.", "error_code": "UNAUTHENTICATED"}
     * @response 422 {"success": false, "message": "Current password is incorrect.", "error_code": "CURRENT_PASSWORD_INCORRECT"}
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $this->authService->updatePassword(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password'),
            $request->ip(),
            $request->userAgent()
        );

        activity('auth')
            ->performedOn($request->user())
            ->event('password_updated')
            ->log('Password updated');

        return $this->successResponse(message: __('auth_messages.password_updated'));
    }

    /**
     * Forgot password
     *
     * Send a password reset link to the given email. Always returns success to avoid user enumeration.
     *
     * @response 200 {"success": true, "message": "If the email exists, a password reset link has been sent."}
     * @response 422 {"success": false, "message": "Validation failed.", "error_code": "VALIDATION_ERROR", "errors": {}}
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->sendResetLink($request->validated('email'));

        return $this->successResponse(message: __('auth_messages.reset_link_sent'));
    }

    /**
     * Reset password
     *
     * Reset the password using the email, token and new password.
     *
     * @response 200 {"success": true, "message": "Password has been reset successfully."}
     * @response 400 {"success": false, "message": "This password reset token is invalid or expired.", "error_code": "INVALID_RESET_TOKEN"}
     * @response 422 {"success": false, "message": "Validation failed.", "error_code": "VALIDATION_ERROR", "errors": {}}
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->reset(
            $request->validated('email'),
            $request->validated('token'),
            $request->validated('password')
        );

        activity('auth')
            ->event('password_reset')
            ->withProperties(['email' => $request->validated('email')])
            ->log('Password reset via token');

        return $this->successResponse(message: __('auth_messages.password_reset'));
    }
}
