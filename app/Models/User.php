<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasApiTokens, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     * 
     */
    protected $attributes = [
    'role' => 'other_staff', // Default for normal users via public register
];
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'role',
        'designation',
        'department',
        'extension_number',
        'remember_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * A user can have many tickets.
     */
    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

  public function activeChatSessions()
    {
        return $this->hasMany(ChatSession::class, 'ict_staff_id')
            ->where('status', 'active');
    }

    /**
     * All messages sent by this user in chat sessions
     */
    public function chatMessages()
    {
        return $this->hasMany(Message::class);
    }
}
