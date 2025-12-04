<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    /**
     * Get all messages for a specific ticket
     * 
     * @param int $ticketId
     * @return \Illuminate\Http\JsonResponse
     */
    public function index($ticketId)
    {
        try {
            $ticket = Ticket::findOrFail($ticketId);
            $user = Auth::user();
            
            // Authorization check
            if (!$this->canAccessTicket($ticket, $user)) {
                return response()->json([
                    'message' => 'You do not have access to this ticket'
                ], 403);
            }

            // Fetch messages with user details
            $messages = Message::where('ticket_id', $ticketId)
                ->with(['user' => function($query) {
                    $query->select('id', 'first_name', 'last_name', 'role', 'email');
                }])
                ->orderBy('created_at', 'asc')
                ->get();

            // Mark messages as read for current user (only messages from others)
            Message::where('ticket_id', $ticketId)
                ->where('user_id', '!=', $user->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            return response()->json($messages);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Ticket not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error fetching messages: ' . $e->getMessage(), [
                'ticket_id' => $ticketId,
                'user_id' => Auth::id()
            ]);
            return response()->json([
                'message' => 'Failed to fetch messages'
            ], 500);
        }
    }

    /**
     * Send a new message
     * 
     * @param Request $request
     * @param int $ticketId
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $ticketId)
    {
        try {
            // Validate input
            $validated = $request->validate([
                'message' => 'required|string|max:1000|min:1',
            ]);

            $ticket = Ticket::findOrFail($ticketId);
            $user = Auth::user();

            // Authorization check
            if (!$this->canAccessTicket($ticket, $user)) {
                return response()->json([
                    'message' => 'You do not have access to this ticket'
                ], 403);
            }

            // Create message
            $message = Message::create([
                'ticket_id' => $ticketId,
                'user_id' => $user->id,
                'message' => trim($validated['message']),
                'is_read' => false,
            ]);

            // Load user relationship
            $message->load(['user' => function($query) {
                $query->select('id', 'first_name', 'last_name', 'role', 'email');
            }]);

            return response()->json([
                'message' => 'Message sent successfully',
                'data' => $message
            ], 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Ticket not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error sending message: ' . $e->getMessage(), [
                'ticket_id' => $ticketId,
                'user_id' => Auth::id()
            ]);
            return response()->json([
                'message' => 'Failed to send message'
            ], 500);
        }
    }

    /**
     * Get unread message count for a specific ticket
     * 
     * @param int $ticketId
     * @return \Illuminate\Http\JsonResponse
     */
    public function unreadCount($ticketId)
    {
        try {
            $user = Auth::user();
            
            // Count unread messages from other users
            $count = Message::where('ticket_id', $ticketId)
                ->where('user_id', '!=', $user->id)
                ->where('is_read', false)
                ->count();

            return response()->json(['count' => $count]);
            
        } catch (\Exception $e) {
            Log::error('Error getting unread count: ' . $e->getMessage(), [
                'ticket_id' => $ticketId,
                'user_id' => Auth::id()
            ]);
            return response()->json([
                'message' => 'Failed to get unread count'
            ], 500);
        }
    }

    /**
     * Get total unread messages across all tickets for current user
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function totalUnread()
    {
        try {
            $user = Auth::user();
            
            $count = 0;

            if ($user->role === 'ict_staff' || $user->role === 'admin') {
                // For staff/admin: count unread from tickets assigned to them or all tickets
                $ticketIds = Ticket::where(function($query) use ($user) {
                    if ($user->role === 'ict_staff') {
                        // ICT staff only sees assigned tickets
                        $query->where('assigned_to', $user->id);
                    }
                    // Admin sees all tickets (no additional filter)
                })->pluck('id');

                $count = Message::whereIn('ticket_id', $ticketIds)
                    ->where('user_id', '!=', $user->id)
                    ->where('is_read', false)
                    ->count();
                    
            } else {
                // For regular users: count unread from their own tickets
                $ticketIds = Ticket::where('user_id', $user->id)->pluck('id');
                
                $count = Message::whereIn('ticket_id', $ticketIds)
                    ->where('user_id', '!=', $user->id)
                    ->where('is_read', false)
                    ->count();
            }

            return response()->json(['count' => $count]);
            
        } catch (\Exception $e) {
            Log::error('Error getting total unread: ' . $e->getMessage(), [
                'user_id' => Auth::id()
            ]);
            return response()->json([
                'message' => 'Failed to get unread count'
            ], 500);
        }
    }

    /**
     * Mark specific message as read
     * 
     * @param int $messageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsRead($messageId)
    {
        try {
            $message = Message::findOrFail($messageId);
            $user = Auth::user();

            // Can only mark messages you received (not your own)
            if ($message->user_id === $user->id) {
                return response()->json([
                    'message' => 'Cannot mark your own message as read'
                ], 400);
            }

            $message->update(['is_read' => true]);

            return response()->json([
                'message' => 'Message marked as read',
                'data' => $message
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Message not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error marking message as read: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to mark message as read'
            ], 500);
        }
    }

    /**
     * Delete a message
     * 
     * @param int $messageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($messageId)
    {
        try {
            $message = Message::findOrFail($messageId);
            $user = Auth::user();

            // Only sender or admin can delete
            if ($message->user_id !== $user->id && $user->role !== 'admin') {
                return response()->json([
                    'message' => 'You can only delete your own messages'
                ], 403);
            }

            $message->delete();
            
            return response()->json([
                'message' => 'Message deleted successfully'
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Message not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting message: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete message'
            ], 500);
        }
    }

    /**
     * Get chat history/summary for a ticket
     * 
     * @param int $ticketId
     * @return \Illuminate\Http\JsonResponse
     */
    public function history($ticketId)
    {
        try {
            $ticket = Ticket::findOrFail($ticketId);
            $user = Auth::user();

            if (!$this->canAccessTicket($ticket, $user)) {
                return response()->json([
                    'message' => 'You do not have access to this ticket'
                ], 403);
            }

            $summary = DB::table('messages')
                ->where('ticket_id', $ticketId)
                ->selectRaw('
                    COUNT(*) as total_messages,
                    COUNT(DISTINCT user_id) as participants,
                    MIN(created_at) as first_message,
                    MAX(created_at) as last_message
                ')
                ->first();

            return response()->json([
                'ticket_id' => $ticketId,
                'summary' => $summary
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error getting chat history: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to get chat history'
            ], 500);
        }
    }

    /**
     * Check if user can access a ticket's chat
     * 
     * @param Ticket $ticket
     * @param User $user
     * @return bool
     */
    private function canAccessTicket($ticket, $user)
    {
        // Admin can access all tickets
        if ($user->role === 'admin') {
            return true;
        }

        // ICT staff can access assigned tickets
        if ($user->role === 'ict_staff' && $ticket->assigned_to === $user->id) {
            return true;
        }

        // Users can access their own tickets
        if ($ticket->user_id === $user->id) {
            return true;
        }

        return false;
    }
}