<?php

use App\Http\Controllers\Feature\CostComponentController;
use App\Http\Controllers\Feature\ExpenseCategoryController;
use App\Http\Controllers\Feature\OperationalExpenseController;
use App\Http\Controllers\Feature\PriceSchemeController;
use App\Http\Controllers\Feature\SalesRecordController;
use App\Http\Controllers\Feature\ServiceCostController;
use App\Http\Controllers\Feature\StatsController;
use App\Http\Controllers\JWTAuthController;
use App\Http\Controllers\User\ProfileController;
use App\Http\Controllers\User\ServiceController;
use App\Http\Middleware\JWTMiddleware;
use Illuminate\Support\Facades\Route;

Route::post('login',[JWTAuthController::class,'login']);
Route::post('forgot-password',[JWTAuthController::class, 'forgotPassword'])->name('password.forgot');
Route::post('reset-password',[JWTAuthController::class, 'resetPassword'])->name('password.reset');

Route::middleware(JWTMiddleware::class)->group(function(){
    Route::post('logout',[JWTAuthController::class,'logout']);
    Route::post('refresh',[JWTAuthController::class,'refresh']);
    Route::get('me',[JWTAuthController::class,'me']);
    Route::get('stats',[StatsController::class,'stats']);
    Route::apiResource('profile', ProfileController::class)->only('show','update');
    Route::apiResource('expense-categories', ExpenseCategoryController::class);
    Route::apiResource('operational-expenses', OperationalExpenseController::class);    
    Route::apiResource('cost-components',CostComponentController::class);
    Route::apiResource('service-cost',ServiceCostController::class);
    Route::apiResource('sales',SalesRecordController::class);
    Route::apiResource('price-schemes',PriceSchemeController::class);
    Route::apiResource('services',ServiceController::class);
});