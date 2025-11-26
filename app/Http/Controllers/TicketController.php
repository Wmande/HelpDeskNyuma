<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\TicketCreatedMail;

class TicketController extends Controller
{
    // Store a new ticket
   public function store(Request $request)
{
    try {
        $validated = $request->validate([
            'phone_number' => 'required|string|max:20',
            'room_number'  => 'required|string|max:50',
            'description'  => 'required|string',
        ]);

        $user = Auth::user();

        // Check if user exists
        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized',
                'errors' => ['auth' => ['Please log in again']]
            ], 401);
        }

        $ticket = Ticket::create([
            'user_id'      => $user->id,
            'first_name'   => $user->first_name,
            'last_name'    => $user->last_name,
            'department'   => $user->department ?? 'Not specified',
            'phone_number' => $validated['phone_number'],
            'room_number'  => $validated['room_number'],
            'description'  => $validated['description'],
            'status'       => 'open',
            'assigned_to'  => null,
        ]);

        return response()->json([
            'message' => 'Ticket created successfully!',
            'ticket'  => $ticket
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        Log::error('Ticket creation error: ' . $e->getMessage());
        return response()->json([
            'message' => 'Failed to create ticket',
            'errors' => ['general' => [$e->getMessage()]]
        ], 500);
    }
}

    // Get all tickets (Admin use-case)
    public function index()
    {
        return response()->json(
            Ticket::with(['user:id,first_name,last_name,email,department', 'assignedStaff:id,first_name,last_name,email']) // ✅ prevent exposing sensitive fields
                ->latest()
                ->get()
        );
    }

    // Get only the current user's tickets
    public function myTickets()
    {
        $tickets = Ticket::with(['user:id,first_name,last_name,email,department', 'assignedStaff:id,first_name,last_name,email'])
            ->where('user_id', Auth::id())
            ->latest()
            ->get();


        return response()->json($tickets);
    }

    // Show a single ticket
    public function show($id)
    {
       $ticket = Ticket::with(['user:id,first_name,last_name,email,department', 'assignedStaff:id,first_name,last_name,email'])->findOrFail($id);


        // ✅ Ensure user can only see their own ticket (unless admin)
        if ($ticket->user_id !== Auth::id() && !Auth::user()->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($ticket);
    }

    // Update ticket (e.g., change status)
// Update ticket (complete / escalate / status changes)
public function update(Request $request, $id)
{
    $ticket = Ticket::findOrFail($id);

    $validated = $request->validate([
        'status'       => 'nullable|in:open,in_progress,closed,completed,escalated',
        'description'  => 'nullable|string',
        'assigned_to'  => 'nullable|exists:users,id',
    ]);

    // If assigning a staff member, ensure they are ICT staff
    if (!empty($validated['assigned_to'])) {
        $staff = \App\Models\User::find($validated['assigned_to']);
        if (!$staff || $staff->role !== 'ict_staff') {
            return response()->json(['error' => 'Invalid staff assignment'], 422);
        }
    }

    // ✅ ICT staff actions
    if ($request->action === 'complete') {
        $ticket->status = 'completed';
    } elseif ($request->action === 'escalate') {
        $ticket->status = 'escalated';
    } else {
        $ticket->fill($validated);
    }

    $ticket->save();

    return response()->json([
        'message' => 'Ticket updated successfully',
        'ticket'  => $ticket->load(['user', 'assignedStaff']),
    ]);
}



    // Delete ticket
    public function destroy($id)
    {
        $ticket = Ticket::findOrFail($id);

        // ✅ Ensure only owner or admin can delete
        if ($ticket->user_id !== Auth::id() && !Auth::user()->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $ticket->delete();

        return response()->json(['message' => 'Ticket deleted successfully']);
    }
}
