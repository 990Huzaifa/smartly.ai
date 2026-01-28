<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Route::get('/optimize-clear', function () {
    Artisan::call('optimize:clear');
    return 'Optimization cache cleared!';
});


Route::post('signup', [AuthController::class, 'signup']);
Route::post('signin', [AuthController::class, 'signin']);
Route::post('social',[AuthController::class,'socialLoginSignup']);
Route::put('verify/{token}/{email}',[AuthController::class,'verification']);
Route::post('account-check',[AuthController::class,'accountCheck']);
Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);
Route::post('resend-code', [AuthController::class, 'resendCode']);

Route::post('/webhook/apple', [WebhookController::class, 'handleApple']);
Route::post('/webhook/google', [WebhookController::class, 'handleGoogle']);

Route::get('/test-google', [AuthController::class, 'testGAToken']);
Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('google/verify-payment', [PaymentController::class, 'verifyGoogle']);
    Route::controller(ProfileController::class)->group(function () {
        Route::get('/profile', 'profile');
        Route::post('/profile', 'updateProfile');
        Route::get('/check-plan', 'checkPlan');


    });

});
