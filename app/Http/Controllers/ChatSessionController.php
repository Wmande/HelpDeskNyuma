<?php

namespace App\Http\Controllers;

use App\Models\ChatSession;
use App\Models\Message;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ChatSessionController extends Controller
{
    /**
     * Get available ICT staff (not in active chat)
     */
    public function getAvailableStaff()
    {
        try {
            $allStaff = User::where('role', 'ict_staff')->get();
            
            $availableStaff = $allStaff->filter(function ($user) {
                $activeCount = ChatSession::where('ict_staff_id', $user->id)
                    ->where('status', 'active')
                    ->count();
                return $activeCount === 0;
            })->values();

            return response()->json([
                'staff' => $availableStaff->map(fn($u) => [
                    'id' => $u->id,
                    'first_name' => $u->first_name,
                    'last_name' => $u->last_name,
                    'email' => $u->email,
                ])
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching available staff: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch available staff', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get user's tickets for chat selection
     */
    public function getUserTickets()
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $tickets = Ticket::where('user_id', $user->id)
                ->whereIn('status', ['open', 'in_progress'])
                ->select('id', 'description', 'status', 'room_number')
                ->get();

            return response()->json(['tickets' => $tickets]);
        } catch (\Exception $e) {
            Log::error('Error fetching user tickets: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch tickets', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Start chat session WITHOUT staff assignment
     */
    public function startChatWithoutStaff(Request $request)
    {
        try {
            $validated = $request->validate([
                'ticket_id' => 'required|exists:tickets,id',
            ]);

            $user = Auth::user();
            $ticket = Ticket::findOrFail($validated['ticket_id']);

            if ($ticket->user_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized ticket access'], 403);
            }

            $chatSession = ChatSession::create([
                'ticket_id' => $validated['ticket_id'],
                'user_id' => $user->id,
                'ict_staff_id' => null,
                'status' => 'active',
                'started_at' => now(),
                'admin_participants' => json_encode([]),
            ]);

            Log::info('Chat session created without staff', ['session_id' => $chatSession->id]);

            return response()->json([
                'message' => 'Chat session started',
                'session' => $chatSession
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error starting chat: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to start chat', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Start a new chat session (with staff assignment)
     */
    public function startChat(Request $request)
    {
        try {
            $validated = $request->validate([
                'ticket_id' => 'required|exists:tickets,id',
                'ict_staff_id' => 'required|exists:users,id',
            ]);

            $user = Auth::user();
            $ticket = Ticket::findOrFail($validated['ticket_id']);
            $ictStaff = User::findOrFail($validated['ict_staff_id']);

            if ($ticket->user_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized ticket access'], 403);
            }

            if ($ictStaff->role !== 'ict_staff') {
                return response()->json(['message' => 'Invalid staff selection'], 422);
            }

            $activeSession = ChatSession::where('ict_staff_id', $ictStaff->id)
                ->where('status', 'active')
                ->first();

            if ($activeSession) {
                return response()->json(['message' => 'This staff member is currently unavailable'], 409);
            }

            $chatSession = ChatSession::create([
                'ticket_id' => $validated['ticket_id'],
                'user_id' => $user->id,
                'ict_staff_id' => $ictStaff->id,
                'status' => 'active',
                'started_at' => now(),
                'admin_participants' => json_encode([]),
            ]);

            Log::info('Chat session created', ['session_id' => $chatSession->id]);

            return response()->json([
                'message' => 'Chat session started',
                'session' => $chatSession,
                'staff' => [
                    'id' => $ictStaff->id,
                    'first_name' => $ictStaff->first_name,
                    'last_name' => $ictStaff->last_name,
                    'email' => $ictStaff->email,
                ]
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error starting chat: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to start chat', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get active chat session details
     */
    public function getActiveSession($sessionId)
    {
        try {
            $user = Auth::user();
            $session = ChatSession::findOrFail($sessionId);

            if ($session->status !== 'active') {
                return response()->json(['message' => 'Session is not active'], 404);
            }

            // Allow admins to view any session
            if ($session->user_id !== $user->id && 
                $session->ict_staff_id !== $user->id && 
                $user->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            return response()->json(['session' => $session]);
        } catch (\Exception $e) {
            Log::error('Error fetching session: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch session', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get all messages in a chat session
     */
    public function getMessages($sessionId)
    {
        try {
            $session = ChatSession::findOrFail($sessionId);
            $user = Auth::user();

            // Check if user is: participant, assigned staff, or admin participant
            $adminParticipants = json_decode($session->admin_participants, true) ?? [];

            if ($session->user_id !== $user->id && 
                $session->ict_staff_id !== $user->id && 
                !in_array($user->id, $adminParticipants) &&
                $user->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $messages = Message::where('chat_session_id', $sessionId)
                ->with('user')
                ->orderBy('created_at', 'asc')
                ->get();

            // Mark messages as read (but not for admins viewing)
            if ($user->role !== 'admin') {
                Message::where('chat_session_id', $sessionId)
                    ->where('user_id', '!=', $user->id)
                    ->where('is_read', false)
                    ->update(['is_read' => true]);
            }

            return response()->json(['messages' => $messages]);
        } catch (\Exception $e) {
            Log::error('Error fetching messages: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch messages', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Send message in chat session (UPDATED - Admin can send messages)
     */
    public function sendMessage(Request $request, $sessionId)
    {
        try {
            $validated = $request->validate([
                'message' => 'required|string|max:1000|min:1',
            ]);

            $session = ChatSession::findOrFail($sessionId);
            $user = Auth::user();

            // Check if user is: customer, assigned staff, or admin participant
            $adminParticipants = json_decode($session->admin_participants, true) ?? [];
            
            if ($session->user_id !== $user->id && 
                $session->ict_staff_id !== $user->id && 
                !in_array($user->id, $adminParticipants) &&
                $user->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $message = Message::create([
                'chat_session_id' => $sessionId,
                'user_id' => $user->id,
                'message' => trim($validated['message']),
                'is_read' => false,
            ]);

            $message->load('user');

            return response()->json([
                'message' => 'Message sent',
                'data' => $message
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error sending message: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to send message', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Transfer chat to ICT staff while retaining admin access
     */
    public function transferToStaff(Request $request, $sessionId)
    {
        try {
            $validated = $request->validate([
                'ict_staff_id' => 'required|exists:users,id',
            ]);

            $user = Auth::user();
            $session = ChatSession::findOrFail($sessionId);

            // Only admin can transfer
            if ($user->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized - Only admins can transfer chats'], 403);
            }

            $ictStaff = User::findOrFail($validated['ict_staff_id']);

            if ($ictStaff->role !== 'ict_staff') {
                return response()->json(['message' => 'Invalid staff selection'], 422);
            }

            // Add admin to participants if not already there
            $adminParticipants = json_decode($session->admin_participants, true) ?? [];
            if (!in_array($user->id, $adminParticipants)) {
                $adminParticipants[] = $user->id;
            }

            $session->update([
                'ict_staff_id' => $ictStaff->id,
                'transferred_to' => $ictStaff->id,
                'transferred_by' => $user->id,
                'transferred_at' => now(),
                'admin_participants' => json_encode($adminParticipants),
            ]);

            Log::info('Chat transferred', [
                'session_id' => $sessionId,
                'transferred_to' => $ictStaff->id,
                'transferred_by' => $user->id
            ]);

            return response()->json([
                'message' => 'Chat transferred successfully',
                'session' => $session->load(['user', 'ictStaff'])
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error transferring chat: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to transfer chat', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * End chat session
     */
    public function endSession($sessionId)
    {
        try {
            $session = ChatSession::findOrFail($sessionId);
            $user = Auth::user();

            // Check if user is authorized to end session
            $adminParticipants = json_decode($session->admin_participants, true) ?? [];

            if ($session->user_id !== $user->id && 
                $session->ict_staff_id !== $user->id && 
                !in_array($user->id, $adminParticipants)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $session->update([
                'status' => 'closed',
                'ended_at' => now(),
            ]);

            Log::info('Chat session ended', ['session_id' => $sessionId]);

            return response()->json(['message' => 'Chat session ended']);
        } catch (\Exception $e) {
            Log::error('Error ending session: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to end session', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get chat history for a ticket
     */
    public function getChatHistory($ticketId)
    {
        try {
            $user = Auth::user();
            $ticket = Ticket::findOrFail($ticketId);

            if ($ticket->user_id !== $user->id && $user->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $sessions = ChatSession::where('ticket_id', $ticketId)
                ->orderBy('started_at', 'desc')
                ->get();

            return response()->json(['sessions' => $sessions]);
        } catch (\Exception $e) {
            Log::error('Error fetching chat history: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch history', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get all active chat sessions (for admin inbox) - UPDATED with transfer data
     */
    public function getAllActiveSessions()
    {
        try {
            $user = Auth::user();

            if ($user->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $sessions = ChatSession::where('status', 'active')
                ->with([
                    'user:id,first_name,last_name,email',
                    'ictStaff:id,first_name,last_name,email',
                    'ticket:id,description'
                ])
                ->orderBy('started_at', 'desc')
                ->get()
                ->map(function ($session) {
                    return [
                        'id' => $session->id,
                        'ticket_id' => $session->ticket_id,
                        'user_id' => $session->user_id,
                        'ict_staff_id' => $session->ict_staff_id,
                        'transferred_to' => $session->transferred_to,
                        'transferred_by' => $session->transferred_by,
                        'transferred_at' => $session->transferred_at,
                        'admin_participants' => json_decode($session->admin_participants, true) ?? [],
                        'status' => $session->status,
                        'started_at' => $session->started_at,
                        'user' => $session->user,
                        'ictStaff' => $session->ictStaff,
                        'ticket' => $session->ticket,
                    ];
                });

            return response()->json(['sessions' => $sessions]);
        } catch (\Exception $e) {
            Log::error('Error fetching all sessions: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch sessions', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Assign ICT staff to a chat session (admin function)
     */
    public function assignStaffToSession(Request $request, $sessionId)
    {
        try {
            $validated = $request->validate([
                'ict_staff_id' => 'required|exists:users,id',
            ]);

            $user = Auth::user();

            if ($user->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $session = ChatSession::findOrFail($sessionId);
            $ictStaff = User::findOrFail($validated['ict_staff_id']);

            if ($ictStaff->role !== 'ict_staff') {
                return response()->json(['message' => 'Invalid staff selection'], 422);
            }

            $session->update([
                'ict_staff_id' => $ictStaff->id,
            ]);

            Log::info('Staff assigned to session', [
                'session_id' => $sessionId,
                'staff_id' => $ictStaff->id
            ]);

            return response()->json([
                'message' => 'Staff assigned successfully',
                'session' => $session->load(['user', 'ictStaff', 'ticket'])
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error assigning staff: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to assign staff', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get active sessions for ICT staff
     */
    public function getStaffSessions()
    {
        try {
            $user = Auth::user();

            if ($user->role !== 'ict_staff') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $sessions = ChatSession::where('ict_staff_id', $user->id)
                ->where('status', 'active')
                ->with([
                    'user:id,first_name,last_name,email',
                    'ticket:id,description'
                ])
                ->get()
                ->map(function ($session) {
                    return [
                        'id' => $session->id,
                        'ticket_id' => $session->ticket_id,
                        'user_id' => $session->user_id,
                        'ict_staff_id' => $session->ict_staff_id,
                        'transferred_to' => $session->transferred_to,
                        'transferred_by' => $session->transferred_by,
                        'transferred_at' => $session->transferred_at,
                        'admin_participants' => json_decode($session->admin_participants, true) ?? [],
                        'status' => $session->status,
                        'started_at' => $session->started_at,
                        'user' => $session->user,
                        'ticket' => $session->ticket,
                    ];
                });

            return response()->json(['sessions' => $sessions]);
        } catch (\Exception $e) {
            Log::error('Error fetching staff sessions: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch staff sessions', 'error' => $e->getMessage()], 500);
        }
    }
}