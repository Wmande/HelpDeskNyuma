<?php
// app/Models/ChatSession.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'ict_staff_id',
        'status',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /**
     * The ticket associated with this chat
     */
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * The user who initiated the chat
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The ICT staff member in the chat
     */
    public function ictStaff()
    {
        return $this->belongsTo(User::class, 'ict_staff_id');
    }

    /**
     * Messages in this chat session
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Scope for active sessions
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Check if session is active
     */
    public function isActive()
    {
        return $this->status === 'active';
    }
}