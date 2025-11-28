<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Interment extends Model
{
    use HasFactory;

    protected $fillable = [
        'plot_id',
        'application_id',
        'reservation_id',
        'start_at',
        'end_at',
        'status',
        'service_type',
        'rites',
        'officiant_name',
        'officiant_contact',
        'notes',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at'   => 'datetime',
    ];

    public function plot()
    {
        return $this->belongsTo(Plot::class);
    }

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    /** Simple overlap check helper (not used by controller directly). */
    public function overlapsQuery()
    {
        return static::query()
            ->where('plot_id', $this->plot_id)
            ->where('id', '!=', $this->id ?? 0)
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->whereBetween('start_at', [$this->start_at, $this->end_at])
                  ->orWhereBetween('end_at',   [$this->start_at, $this->end_at])
                  ->orWhere(function ($w) {
                      $w->where('start_at', '<=', $this->start_at)
                        ->where('end_at',   '>=', $this->end_at);
                  });
            });
    }
}
