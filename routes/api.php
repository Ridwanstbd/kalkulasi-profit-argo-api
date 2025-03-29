<?php

use App\Http\Controllers\Admin\AdminSubscriptionPlanController;
use App\Http\Controllers\Admin\ManageUserSubscriptionController;
use App\Http\Controllers\Feature\HppController;
use App\Http\Controllers\JWTAuthController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\User\SubscriptionController;
use App\Http\Middleware\JWTMiddleware;
use Illuminate\Support\Facades\Route;

Route::post('register',[JWTAuthController::class,'register']);
Route::post('login',[JWTAuthController::class,'login']);
Route::apiResource('subscription-plans',SubscriptionPlanController::class)->only('index','show');
Route::middleware(JWTMiddleware::class)->group(function(){
    // Authorization Route
    Route::post('logout',[JWTAuthController::class,'logout']);
    Route::post('refresh',[JWTAuthController::class,'refresh']);
    Route::get('me',[JWTAuthController::class,'me']);

    Route::middleware('role:user')->prefix('user')->group(function(){
        Route::apiResource('subscriptions',SubscriptionController::class);
    });

    Route::middleware('role:admin')->prefix('admin')->group(function(){
        Route::apiResource('subscription-plan',AdminSubscriptionPlanController::class);
        Route::apiResource('manage-subscriber',ManageUserSubscriptionController::class)->only('index','show','update');
    });

    Route::middleware('feature:hhp_calculation')->group(function(){
        Route::apiResource('hpp',HppController::class);
    });

    Route::middleware('limit:product')->prefix('user')->group(function(){
        // Route untuk limitasi produk
    });

    Route::middleware('limit:material')->prefix('user')->group(function(){
        // Rounte untuk Limitasi Material
    });
});