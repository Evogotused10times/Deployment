<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string)$request->get('q',''));

        $items = Announcement::query()
            ->when($q, fn($qq) => $qq->where(function($w) use($q){
                $w->where('title','like',"%$q%")->orWhere('body','like',"%$q%");
            }))
            ->orderByDesc('pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->get([
                'id','title','body','category','pinned','is_published',
                'published_at','created_at','slug','user_id'
            ])
            ->map(function ($a) {
                return [
                    'id'           => $a->id,
                    'title'        => $a->title,
                    'category'     => $a->category,
                    'pinned'       => (bool) $a->pinned,
                    'is_published' => (bool) $a->is_published,
                    'date'         => optional($a->published_at ?? $a->created_at)->toIso8601String(),
                    'href'         => route('announcements.show', $a->slug ?? $a->id),
                ];
            });

        return Inertia::render('Admin/Announcements/Index', [
            'items'   => $items,
            'filters' => ['q' => $q],
            'flash'   => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function store(Request $request)
{
    $data = $request->validate([
        'title'        => 'required|string|max:255',
        'body'         => 'required|string',
        'category'     => 'nullable|in:news,event,notice',
        'pinned'       => 'nullable|boolean',
        'publish_now'  => 'nullable|boolean',
    ]);

    // Unique slug (even across soft-deleted)
    $base = Str::slug($data['title']);
    $slug = $base ?: null;
    if ($slug) {
        $i = 2;
        while (Announcement::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }
    }

    $publish = !empty($data['publish_now']);

    Announcement::create([
        'title'        => $data['title'],
        'body'         => $data['body'],
        'category'     => strtolower($data['category'] ?? 'news'),
        'pinned'       => (bool) ($data['pinned'] ?? false),
        'is_published' => $publish,
        'published_at' => $publish ? now() : null,
        'slug'         => $slug,
        'user_id'      => optional($request->user())->id,
    ]);

    return back()->with('success', $publish ? 'Announcement published.' : 'Announcement saved as draft.');
}

public function togglePublish(Announcement $announcement)
{
    $isNow = !$announcement->is_published;

    $announcement->update([
        'is_published' => $isNow,
        'published_at' => $isNow
            ? ($announcement->published_at ?? now())   // set now if first time
            : null,                                     // clear on unpublish
    ]);

    return back()->with('success', $isNow ? 'Published.' : 'Unpublished.');
}

    public function update(Request $request, Announcement $announcement)
    {
        $data = $request->validate([
            'title'    => 'required|string|max:255',
            'body'     => 'nullable|string',
            'category' => 'nullable|in:news,event,notice',
            'pinned'   => 'nullable|boolean',
        ]);

        $announcement->update([
            'title'    => $data['title'],
            'body'     => $data['body'],
            'category' => strtolower($data['category'] ?? 'news'),
            'pinned'   => (bool) ($data['pinned'] ?? false),
        ]);

        return back()->with('success','Announcement updated.');
    }

    // public function togglePublish(Announcement $announcement)
    // {
    //     $isNow = !$announcement->is_published;
    //     $announcement->update([
    //         'is_published' => $isNow,
    //         'published_at' => $isNow ? ($announcement->published_at ?? now()) : null,
    //     ]);

    //     return back()->with('success', $isNow ? 'Published.' : 'Unpublished.');
    // }

    public function togglePin(Announcement $announcement)
    {
        $announcement->update(['pinned' => !$announcement->pinned]);
        return back()->with('success', $announcement->pinned ? 'Pinned.' : 'Unpinned.');
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();
        return back()->with('success','Announcement deleted.');
    }
}
