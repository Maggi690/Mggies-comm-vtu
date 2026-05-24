<?php

namespace App\Http\Controllers\Auth;

use App\DTOs\Auth\RegisterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\SetPinRequest;
use App\Http\Requests\Auth\ResetPinRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Http\Resources\User\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Authentication
 *
 * APIs for user registration, login, logout, email verification, and password reset.
 */
class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    /**
     * Register a new user
     *
     * @bodyParam first_name string required User's first name. Example: John
     * @bodyParam last_name string required User's last name. Example: Doe
     * @bodyParam email string required Valid email address. Example: john@example.com
     * @bodyParam phone string required Nigerian phone number. Example: 08012345678
     * @bodyParam password string required Minimum 8 characters. Example: SecurePass123!
     * @bodyParam password_confirmation string required Must match password.
     * @bodyParam referral_code string optional Referral code from existing user.
     * @response 201 {"success":true,"message":"Registration successful","data":{"user":{},"token":"..."}}
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $dto    = RegisterDTO::fromArray($request->validated());
        $result = $this->authService->register($dto);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Please verify your email.',
            'data'    => [
                'user'  => new UserResource($result['user']),
                'token' => $result['token'],
            ],
        ], 201);
    }

    /**
     * Login user
     *
     * @bodyParam email string required Example: john@example.com
     * @bodyParam password string required Example: SecurePass123!
     * @response 200 {"success":true,"data":{"user":{},"token":"..."}}
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->email,
            $request->password,
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data'    => [
                'user'  => new UserResource($result['user']),
                'token' => $result['token'],
            ],
        ]);
    }

    /**
     * Logout user
     *
     * @authenticated
     * @response 200 {"success":true,"message":"Logged out successfully."}
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Verify email address
     *
     * @bodyParam token string required Email verification token.
     * @bodyParam email string required User email.
     * @response 200 {"success":true,"message":"Email verified successfully."}
     */
    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        $user = \App\Models\User::where('email', $request->email)->firstOrFail();

        if (!hash_equals((string) $request->token, sha1($user->email))) {
            return response()->json(['success' => false, 'message' => 'Invalid verification token.'], 422);
        }

        $user->markEmailAsVerified();

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully.',
        ]);
    }

    /**
     * Request password reset
     *
     * @bodyParam email string required Example: john@example.com
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $status = \Password::sendResetLink($request->only('email'));

        return response()->json([
            'success' => true,
            'message' => 'If your email exists, a reset link has been sent.',
        ]);
    }

    /**
     * Reset password
     *
     * @bodyParam token string required Reset token from email.
     * @bodyParam email string required Example: john@example.com
     * @bodyParam password string required New password.
     * @bodyParam password_confirmation string required Must match password.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = \Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->update(['password' => \Hash::make($password)]);
                $user->tokens()->delete();
            }
        );

        if ($status !== \Password::PASSWORD_RESET) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired reset token.'], 422);
        }

        return response()->json(['success' => true, 'message' => 'Password reset successful.']);
    }

    /**
     * Set transaction PIN
     *
     * @authenticated
     * @bodyParam pin string required 4-digit PIN. Example: 1234
     * @bodyParam pin_confirmation string required Must match PIN. Example: 1234
     */
    public function setPin(SetPinRequest $request): JsonResponse
    {
        $this->authService->setTransactionPin(
            $request->user(),
            $request->pin,
            $request->pin_confirmation,
        );

        return response()->json(['success' => true, 'message' => 'Transaction PIN set successfully.']);
    }

    /**
     * Reset transaction PIN
     *
     * @authenticated
     * @bodyParam current_password string required Current account password.
     * @bodyParam new_pin string required New 4-digit PIN.
     */
    public function resetPin(ResetPinRequest $request): JsonResponse
    {
        $this->authService->resetTransactionPin(
            $request->user(),
            $request->current_password,
            $request->new_pin,
        );

        return response()->json(['success' => true, 'message' => 'Transaction PIN reset successfully.']);
    }
}
