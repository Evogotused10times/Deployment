<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Support\Facades\Log;

use Inertia\Inertia;

class AnnouncementController extends Controller
{

public function index()
    {
        $items = Announcement::query()
            // Treat “published” as: flag = true OR published_at is not null.
            // (Covers older rows and any future toggles.)
            ->where(function ($q) {
                $q->where('is_published', true)
                  ->orWhereNotNull('published_at');
            })
            ->orderByDesc('pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->get(['id','title','body','category','pinned','published_at','created_at','slug'])
            ->map(function ($a) {
                return [
                    'id'       => $a->id,
                    'title'    => $a->title,
                    'body'     => $a->body,
                    'category' => strtolower($a->category ?? 'news'),
                    'pinned'   => (bool) $a->pinned,
                    'date'     => optional($a->published_at ?? $a->created_at)->toIso8601String(),
                    'href'     => route('announcements.show', $a->slug ?? $a->id),
                ];
            });

        return Inertia::render('Public/Announcements', [
            'items'     => $items,
            'canManage' => false,
            'loading'   => false,
        ]);
    }

    public function show(Announcement $announcement)
    {
        // Only allow viewing if it’s published (flag or timestamp)
        if (!($announcement->is_published || $announcement->published_at)) {
            abort(404);
        }

        return Inertia::render('Public/AnnouncementShow', [
            'item' => [
                'id'       => $announcement->id,
                'title'    => $announcement->title,
                'body'     => $announcement->body,
                'category' => strtolower($announcement->category ?? 'news'),
                'pinned'   => (bool) $announcement->pinned,
                'date'     => optional($announcement->published_at ?? $announcement->created_at)->toIso8601String(),
            ],
        ]);
    }

}

