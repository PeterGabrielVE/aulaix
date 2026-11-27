<?php

use App\Http\Controllers\Auth\AcceptInvitationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

// No public registration:
// people join an institution through an invitation (UserController), accepted
// on the central domain (routes/web.php).
Route::middleware('guest')->group(function () {
    // Forwards to the central login (IDG-R03.2).
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    // Password recovery is central too (IDG-R06.3); links sent before SPEC-004
    // still land there.
    Route::get('forgot-password', [PasswordResetLinkController::class, 'central'])
        ->name('tenant.password.request');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'central'])
        ->name('tenant.password.reset');

    // Invitation links sent before SPEC-004; they now open on the central domain.
    Route::get('invitation/{token}', [AcceptInvitationController::class, 'legacy'])
        ->name('invitation.legacy');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
