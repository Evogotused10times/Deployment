<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role', // <-- add role here
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Convenient helper to check admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
