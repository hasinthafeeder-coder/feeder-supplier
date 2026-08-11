<?php

use App\Http\Controllers\Registration\RegistrationController;
use App\Http\Controllers\Registration\RegistrationUploadController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/auth/register', [RegistrationController::class, 'create'])
        ->name('supplier.registration.create');

    Route::post('/auth/register/send-otp', [RegistrationController::class, 'sendOtp'])
        ->name('supplier.registration.send-otp');

    Route::post('/auth/register/verify-otp', [RegistrationController::class, 'verifyOtp'])
        ->name('supplier.registration.verify-otp');

    Route::post('/auth/register/user', [RegistrationController::class, 'registerUser'])
        ->name('supplier.registration.user');

    Route::post('/auth/register/personal', [RegistrationController::class, 'savePersonalDetails'])
        ->name('supplier.registration.personal');

    Route::post('/auth/register/company', [RegistrationController::class, 'saveCompanyDetails'])
        ->name('supplier.registration.company');

    Route::post('/auth/register/upload', [RegistrationUploadController::class, 'store'])
        ->name('supplier.registration.upload');

    Route::post('/auth/register/bank', [RegistrationController::class, 'saveBankDetails'])
        ->name('supplier.registration.bank');

    Route::get('/auth/register/draft/{uuid}', [RegistrationController::class, 'getDraft'])
        ->name('supplier.registration.draft');

    Route::post('/auth/register/submit', [RegistrationController::class, 'submitApplication'])
        ->name('supplier.registration.submit');
});
