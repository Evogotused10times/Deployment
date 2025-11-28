<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plot extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_id',
        'section_code',
        'lot_number',
        'status',            // 'vacant','reserved','occupied'
        'block_level',
        'description',
        'price',
        'occupant_name',
        'occupant_contact',
        'geojson',

        // NEW: classify plot kind: 'lawn','garden','mausoleum','vault', etc.
        'plot_type',
    ];

    protected $casts = [
        'geojson'   => 'array',
        'price'     => 'decimal:2',
    ];

    /* -----------------------------------------------------------------
     |  Relationships
     | ----------------------------------------------------------------- */

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function interments()
    {
        return $this->hasMany(Interment::class);
    }

    /**
     * Latest approved application tied to this plot.
     */
    public function latestApprovedApplication()
    {
        return $this->hasOne(Application::class, 'assigned_plot_id')
            ->where('status', 'approved')
            ->latestOfMany();
    }

    /**
     * Latest active reservation (reserved / confirmed).
     */
    public function latestActiveReservation()
    {
        return $this->hasOne(Reservation::class)
            ->whereIn('status', ['reserved', 'confirmed'])
            ->latestOfMany();
    }

    /**
     * Virtual candles lit for this plot.
     */
    public function candles()
    {
        return $this->hasMany(\App\Models\VirtualCandle::class);
    }

    /* -----------------------------------------------------------------
     |  Scopes
     | ----------------------------------------------------------------- */

    /**
     * Scope: only vacant plots.
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'vacant');
    }

    /**
     * Scope by plot type (lawn, garden, mausoleum, vault, …).
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('plot_type', $type);
    }

    /* -----------------------------------------------------------------
     |  Helpers
     | ----------------------------------------------------------------- */

    /**
     * Mark the plot as reserved (unless already occupied).
     */
    public function markReserved(): void
    {
        if ($this->status !== 'occupied') {
            $this->status = 'reserved';
            $this->save();
        }
    }

    /**
     * Release the plot back to 'vacant' if no active reservations.
     */
    public function releaseIfNoActiveReservations(): void
    {
        $hasActive = $this->reservations()
            ->whereIn('status', ['reserved', 'confirmed'])
            ->exists();

        if (! $hasActive && $this->status === 'reserved') {
            $this->status = 'vacant';
            $this->save();
        }
    }
}
