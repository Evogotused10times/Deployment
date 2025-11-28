<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'applicant_name',
        'applicant_email',
        'applicant_phone',
        'applicant_address',
        'facebook_messenger',
        'next_of_kin',
        'next_of_kin_contact',

        'deceased_name',
        'deceased_dod',
        'deceased_age',

        'service_type',
        'preferred_plots',
        'terms',
        'remarks',

        'status',
        'admin_notes',
        'assigned_plot_id',

        // IA / workflow extras
        'decision_at',
        'privacy_consent',
        'client_ip',
        'user_agent',
    ];

    protected $casts = [
        'preferred_plots'   => 'array',
        'deceased_dod'      => 'date',
        'privacy_consent'   => 'boolean',
        'decision_at'       => 'datetime',
    ];

    protected $appends = ['is_editable'];

    /** Scope only approved applications */
    public function scopeApproved($q)
    {
        return $q->where('status', 'approved');
    }

    /** The plot assigned during approval */
    public function assignedPlot()
    {
        return $this->belongsTo(Plot::class, 'assigned_plot_id');
    }

    public function isEditable(): bool
    {
        if ($this->status === 'pending') {
            return true;
        }

        if (!$this->decision_at instanceof Carbon) {
            return true;
        }

        return now()->lt($this->decision_at->copy()->addHours(12));
    }

    public function getIsEditableAttribute(): bool
    {
        return $this->isEditable();
    }

    public function interments()
    {
        return $this->hasMany(\App\Models\Interment::class);
    }
}
