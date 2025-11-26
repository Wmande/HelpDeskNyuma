<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StAuthController;
use App\Http\Controllers\TicketController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::post('/stsignin', [StAuthController::class, 'signin']);
Route::post('/stregister', [StAuthController::class, 'register']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/signin', [AuthController::class, 'signin']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/


Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/users', [AuthController::class, 'index']);   // get all users
    Route::put('/users/{id}', [AuthController::class, 'update']);
    Route::delete('/users/{id}', [AuthController::class, 'destroy']);
    // UserProfile (Other Staff)
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile/update', [AuthController::class, 'updateProfile']);
    // Tickets (issues)
    Route::prefix('issues')->group(function () {
        Route::post('/anza', [TicketController::class, 'store']);       // create ticket
        Route::get('/leta', [TicketController::class, 'index']);        // list all tickets
        Route::get('/my', [TicketController::class, 'myTickets']);  // only current user's tickets
        Route::get('/{id}', [TicketController::class, 'show']);     // show single ticket
        Route::put('/{id}', [TicketController::class, 'update']);   // update ticket (status, description, assignment)
        Route::delete('/{id}', [TicketController::class, 'destroy']); // delete ticket        
    // New endpoint for ICT staff list
    });
     Route::middleware('auth:sanctum')->get('/users/ict-staff', [AuthController::class, 'getIctStaff']);
});
