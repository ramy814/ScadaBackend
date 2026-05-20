<?php

use Illuminate\Support\Facades\Route;
use Mog\ProjectHelper\AuthHelpers;

Route::middleware('web')->group(function () {
    Route::get('/auth/sso/login', [AuthHelpers::class, 'login'])->name('login');
    Route::get('/auth/sso/password', [AuthHelpers::class, 'change_password'])->name('change-password');
    Route::get('/auth/sso/callback', [AuthHelpers::class, 'callback'])->name('sso.callback');
    Route::get('/auth/sso/logout', [AuthHelpers::class, 'logout'])->name('logout');
});