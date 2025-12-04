<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StAuthController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\ChatSessionController;
use App\Http\Controllers\MessageController;

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

// ════════════════════════════════════════════════════════════════
// PUBLIC ROUTES (No Authentication Required)
// ════════════════════════════════════════════════════════════════

Route::post('/stsignin', [StAuthController::class, 'signin']);
Route::post('/stregister', [StAuthController::class, 'register']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/signin', [AuthController::class, 'signin']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// ════════════════════════════════════════════════════════════════
// PROTECTED ROUTES (Requires Authentication)
// ════════════════════════════════════════════════════════════════

Route::middleware('auth:sanctum')->group(function () {

    // ─────────────────────────────────────────────────────────────
    // AUTH ROUTES
    // ─────────────────────────────────────────────────────────────
    
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile/update', [AuthController::class, 'updateProfile']);
    
    // Admin user management
    Route::get('/users', [AuthController::class, 'index']);
    Route::put('/users/{id}', [AuthController::class, 'update']);
    Route::delete('/users/{id}', [AuthController::class, 'destroy']);
    
    // ICT Staff list (two ways to access it)
    Route::get('/ict-staff', [AuthController::class, 'getIctStaff']);
    Route::get('/users/ict-staff', [AuthController::class, 'getIctStaff']); // Alternative endpoint

    // ─────────────────────────────────────────────────────────────
    // TICKET/ISSUES ROUTES
    // ─────────────────────────────────────────────────────────────
    
    Route::prefix('issues')->group(function () {
        Route::post('/anza', [TicketController::class, 'store']);        // Create ticket
        Route::get('/leta', [TicketController::class, 'index']);         // List all tickets (admin)
        Route::get('/my', [TicketController::class, 'myTickets']);       // Current user's tickets
        Route::get('/{id}', [TicketController::class, 'show']);          // Show single ticket
        Route::put('/{id}', [TicketController::class, 'update']);        // Update ticket
        Route::delete('/{id}', [TicketController::class, 'destroy']);    // Delete ticket
    });

    // ─────────────────────────────────────────────────────────────
    // CHAT SESSION ROUTES (UPDATED WITH TRANSFER FEATURE)
    // ─────────────────────────────────────────────────────────────
    
    Route::prefix('chat')->group(function () {
        // Chat staff and tickets
        Route::get('/available-staff', [ChatSessionController::class, 'getAvailableStaff']);
        Route::get('/user-tickets', [ChatSessionController::class, 'getUserTickets']);
        
        // Chat session creation
        Route::post('/start', [ChatSessionController::class, 'startChat']);
        Route::post('/start-without-staff', [ChatSessionController::class, 'startChatWithoutStaff']);
        
        // Chat session management
        Route::get('/session/{sessionId}', [ChatSessionController::class, 'getActiveSession']);
        Route::get('/session/{sessionId}/messages', [ChatSessionController::class, 'getMessages']);
        Route::post('/session/{sessionId}/message', [ChatSessionController::class, 'sendMessage']);
        Route::post('/session/{sessionId}/end', [ChatSessionController::class, 'endSession']);
        Route::post('/session/{sessionId}/assign-staff', [ChatSessionController::class, 'assignStaffToSession']);
        
        // NEW: Chat transfer route (admin only)
        Route::post('/session/{sessionId}/transfer', [ChatSessionController::class, 'transferToStaff']);
        
        // Chat history and active sessions
        Route::get('/history/{ticketId}', [ChatSessionController::class, 'getChatHistory']);
        Route::get('/all-active-sessions', [ChatSessionController::class, 'getAllActiveSessions']); // Admin inbox
        Route::get('/staff/sessions', [ChatSessionController::class, 'getStaffSessions']);
    });

    // ─────────────────────────────────────────────────────────────
    // MESSAGE ROUTES (LEGACY - KEPT FOR BACKWARD COMPATIBILITY)
    // ─────────────────────────────────────────────────────────────
    
    Route::prefix('messages')->group(function () {
        Route::get('/unread/total', [MessageController::class, 'totalUnread']);
        Route::get('/{messageId}/read', [MessageController::class, 'markAsRead']);
        Route::delete('/{messageId}', [MessageController::class, 'destroy']);
    });

    // Ticket-specific messages (legacy)
    Route::prefix('tickets/{ticketId}')->group(function () {
        Route::get('/messages', [MessageController::class, 'index']);
        Route::post('/messages', [MessageController::class, 'store']);
        Route::get('/messages/unread', [MessageController::class, 'unreadCount']);
    });

});