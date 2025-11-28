<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'plot_id',
        'reserved_by_name',
        'reserved_by_email',
        'reserved_by_phone',
        'application_id',
        'status',        // pending|reserved|confirmed|cancelled|expired
        'start_date',
        'expires_at',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'expires_at' => 'date',
    ];

    // ---------------- Relationships ----------------
    public function plot()
    {
        return $this->belongsTo(Plot::class);
    }

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    // ---------------- Accessors / Scopes ----------------
    /** Active = contributes to blocking the plot */
    public function getIsActiveAttribute(): bool
    {
        return in_array($this->status, ['reserved', 'confirmed'], true);
    }

    /** Optional convenience scope */
    public function scopeActive($q)
    {
        return $q->whereIn('status', ['reserved', 'confirmed']);
    }

    // ---------------- Lifecycle Hooks ----------------
    protected static function booted(): void
    {
        // Before create: backfill reserved_by_* from Application if linked
        static::creating(function (Reservation $res) {
            // Default status if not provided
            if (!$res->status) {
                $res->status = 'reserved';
            }

            if ($res->application_id && (empty($res->reserved_by_name) || empty($res->reserved_by_email))) {
                /** @var \App\Models\Application|null $app */
                $app = $res->application()->first();
                if ($app) {
                    // Adjust field names if your Application columns differ
                    $res->reserved_by_name  = $res->reserved_by_name  ?: ($app->applicant_name ?? $app->deceased_name ?? 'Unknown');
                    $res->reserved_by_email = $res->reserved_by_email ?: ($app->applicant_email ?? null);
                    $res->reserved_by_phone = $res->reserved_by_phone ?: ($app->applicant_phone ?? null);
                }
            }
        });

        // After create/update: sync plot status with reservation state
        static::saved(function (Reservation $res) {
            $plot = $res->plot;
            if (!$plot) return;

            if ($res->is_active) {
                // Reserve the plot unless it's occupied already
                $plot->markReserved();
            } else {
                // If not active, see if we should release the plot
                $plot->releaseIfNoActiveReservations();
            }
        });

        // After delete: release plot if no other active reservations remain
        static::deleted(function (Reservation $res) {
            $plot = $res->plot()->with('reservations')->first();
            if ($plot) {
                $plot->releaseIfNoActiveReservations();
            }
        });
    }
}
