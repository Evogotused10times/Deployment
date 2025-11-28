<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VirtualCandle extends Model
{
    use HasFactory;

    protected $fillable = [
        'plot_id',
        'name',
        'message',
        'ip_hash',
    ];

    // no 'lit_at' cast because the column doesn't exist;
    // we can treat created_at as the "lit at" timestamp.

    public function plot()
    {
        return $this->belongsTo(Plot::class);
    }
}
