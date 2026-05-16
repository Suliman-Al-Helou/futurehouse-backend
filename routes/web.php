<?php

use Illuminate\Support\Facades\Route;

Route::get('/reset-password/{token}', function () {
    return 'Reset Password Page';
})->name('password.reset');