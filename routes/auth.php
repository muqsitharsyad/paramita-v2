<?php

use App\Http\Controllers\Auth\AuthenticationController as AuthController;
use App\Http\Controllers\Auth\MicrosoftSsoController;
use Illuminate\Support\Facades\Route;

Route::view('/login', 'auth.login')->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::get('/auth/microsoft/redirect', [MicrosoftSsoController::class, 'redirect'])
    ->middleware('throttle:10,1')
    ->name('auth.microsoft.redirect');
Route::get('/auth/microsoft/callback', [MicrosoftSsoController::class, 'callback'])
    ->middleware('throttle:20,1')
    ->name('auth.microsoft.callback');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::view('/register', 'auth.register')->name('register');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:3,60');
Route::get('/registration/{user}/status', [AuthController::class, 'status'])->middleware('signed')->name('registration.status');
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
Route::view('/email/verify', 'auth.verify')->name('verification.notice');
Route::post('/email/verification-notification', [AuthController::class, 'resend'])->middleware('throttle:3,60')->name('verification.send');
Route::view('/forgot-password', 'auth.forgot')->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:3,1')->name('password.email');
Route::get('/reset-password/{token}', fn (string $token) => view('auth.reset', ['token' => $token, 'email' => request('email')]))->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
