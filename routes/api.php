<?php

use App\Http\Controllers\Api\AccountSettingsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);

// ── Auth / user ───────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);
    Route::get('/user', fn (Request $r) => $r->user());
    Route::get('/auth/account', [AuthController::class, 'account']);
});

// ===================================
// CART

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::delete('cart/clear', [CartController::class, 'clear']);
    Route::get('/cart-count', [CartController::class, 'count']);
    Route::put('/cart/{productId}', [CartController::class, 'update']);
    Route::delete('/cart/{productId}', [CartController::class, 'destroy']);

    // Users management
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}/status', [UserController::class, 'updateStatus']);
    Route::get('/users/statistics', [UserController::class, 'statistics']);
    Route::put('/users/{id}/deactivate', [UserController::class, 'deactivate']);
    Route::put('/users/{id}/reactivate', [UserController::class, 'reactivate']);

});

// ===================================
// PRODUCTS (admin)

// Products - GET is public, write actions are admin-protected
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);  // For PUT requests
    Route::post('/products/{id}', [ProductController::class, 'update']);  // For POST with _method=PUT
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
});

// DASHBOARD
Route::middleware('auth:sanctum')->group(function () {
    Route::get('user/dashboard', [DashboardController::class, 'userIndex']);
    Route::get('admin/dashboard', [DashboardController::class, 'adminIndex']);
});

// CONTACTS
Route::post('contacts', [ContactController::class, 'store']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('contacts', [ContactController::class, 'index']);
});

// Account Settings
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/account', [AccountSettingsController::class, 'getAccount']);
    Route::put('/account/profile', [AccountSettingsController::class, 'updateProfile']);
    Route::put('/account/shipping', [AccountSettingsController::class, 'updateShipping']);
    Route::put('/account/password', [AccountSettingsController::class, 'updatePassword']);
});
