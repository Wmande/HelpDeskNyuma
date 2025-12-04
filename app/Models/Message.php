<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_session_id',
        'ticket_id',
        'user_id',
        'message',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The chat session this message belongs to
     */
    public function chatSession()
    {
        return $this->belongsTo(ChatSession::class);
    }

    /**
     * The ticket (for legacy support)
     */
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * The user who sent the message
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: Get only unread messages
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Mark message as read
     */
    public function markAsRead()
    {
        $this->update(['is_read' => true]);
        return $this;
    }
}