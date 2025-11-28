<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Candle extends Model
{
    use HasFactory;

    protected $fillable = [
        'plot_id',
        'name',
        'message',
        'ip_address',
        'user_agent',
        'lit_at',
        'expires_at',
    ];

    protected $casts = [
        'lit_at'     => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function plot()
    {
        return $this->belongsTo(Plot::class);
    }

    /**
     * Scope for active candles (not expired).
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }
}
