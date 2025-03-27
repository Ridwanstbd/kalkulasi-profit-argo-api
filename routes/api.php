<?php

use App\Http\Controllers\JWTAuthController;
use App\Http\Middleware\JWTMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('register',[JWTAuthController::class,'register']);
Route::post('login',[JWTAuthController::class,'login']);

Route::middleware(JWTMiddleware::class)->group(function(){
    // Authorization Route
    Route::post('logout',[JWTAuthController::class,'logout']);
    Route::post('refresh',[JWTAuthController::class,'refresh']);
    Route::get('me',[JWTAuthController::class,'me']);

    Route::middleware('role:user')->prefix('user')->group(function(){
        Route::get('dashboard', function() {
            return response()->json([
                'status' => 'success',
                'message' => 'User dashboard'
            ]);
        });
    });

    Route::middleware('role:admin')->prefix('admin')->group(function(){
        Route::get('dashboard', function() {
            return response()->json([
                'status' => 'success',
                'message' => 'Admin dashboard'
            ]);
        });

        Route::get('users', function() {
            return response()->json([
                'status' => 'success',
                'users' => \App\Models\User::with('roles')->get()
            ]);
        });
    });
});