<?php

use App\Http\Controllers\API\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the RouteServiceProvider and all of them will
| be assigned the "api" middleware group. They are automatically prefixed
| with /api — so Route::post('/register') becomes POST /api/register.
|
*/

// -------------------------------------------------------------------------
// Public routes — no authentication token required
// -------------------------------------------------------------------------
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// -------------------------------------------------------------------------
// Protected routes — a valid Sanctum Bearer token must be sent in the
// Authorization header: Authorization: Bearer <your-token-here>
// -------------------------------------------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user',    [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
