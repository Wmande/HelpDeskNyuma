<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    // Status constants (use these everywhere!)
    const STATUS_OPEN        = 'open';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED   = 'completed';
    const STATUS_ESCALATED   = 'escalated';
    const STATUS_CLOSED      = 'closed';

    // Default status when created
    protected $attributes = [
        'status' => self::STATUS_OPEN,
    ];

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'department',
        'phone_number',
        'room_number',
        'description',
        'status',
        'assigned_to',
    ];

    // Optional: Cast status to make it nicer in JSON
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The user who reported the ticket
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The ICT staff assigned to the ticket (if any)
     */
    public function assignedStaff()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Scope: Get only open/in_progress tickets
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            self::STATUS_OPEN,
            self::STATUS_IN_PROGRESS
        ]);
    }

    /**
     * Helper: Check if ticket is open
     */
    public function isOpen()
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Helper: Check if ticket is in progress
     */
    public function isInProgress()
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

     public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get unread message count for a specific user
     */
    public function unreadMessagesFor($userId)
    {
        return $this->messages()
            ->where('user_id', '!=', $userId)
            ->where('is_read', false)
            ->count();
    }
    
}