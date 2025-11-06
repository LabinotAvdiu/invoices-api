<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceLineController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\QuoteLineController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'API is running',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes - require authentication
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [UserController::class, 'show']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Companies routes 
    Route::apiResource('companies', CompanyController::class);
    
    // Quotes routes (imbriquées sous companies)
    Route::apiResource('companies.quotes', QuoteController::class)
        ->scoped()
        ->middleware('company.access');
    
    // Quote lines routes (imbriquées sous quotes)
    Route::apiResource('companies.quotes.lines', QuoteLineController::class)
        ->scoped()
        ->middleware('company.access');
    
    // Invoices routes (imbriquées sous companies)
    Route::apiResource('companies.invoices', InvoiceController::class)
        ->scoped()
        ->middleware('company.access');
    
    // Invoice lines routes (imbriquées sous invoices)
    Route::apiResource('companies.invoices.lines', InvoiceLineController::class)
        ->scoped()
        ->middleware('company.access');
});
