<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title','body','category','pinned',
        'is_published','published_at','slug','user_id'
    ];

    protected $casts = [
        'pinned'       => 'boolean',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    // Helpful scopes
    public function scopePinnedFirst(Builder $q): Builder {
        return $q->orderByDesc('pinned')->orderByDesc('date')->orderByDesc('id');
    }

    public function scopeFilter(Builder $q, array $f): Builder {
        return $q
            ->when($f['category'] ?? null, fn($qq,$c) => $qq->where('category',$c))
            ->when($f['search'] ?? null,   fn($qq,$s) => $qq->where(function($x) use($s){
                $x->where('title','like',"%$s%")->orWhere('body','like',"%$s%");
            }));
    }

    public function user(){ return $this->belongsTo(User::class); }

    public function scopePublished(Builder $q): Builder
{
    return $q->where(function ($w) {
        $w->where('is_published', true)
          ->orWhereNotNull('published_at');
    });
}
}
