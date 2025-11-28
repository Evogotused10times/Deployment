<?php
// app/Http/Controllers/PublicController.php
// app/Http/Controllers/PublicController.php
namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Application;   // adjust model names if different
use App\Models\Reservation;
use Carbon\CarbonImmutable;

class PublicController extends Controller
{
    public function Landing()
{
    $start = CarbonImmutable::now('Asia/Manila')->startOfWeek(); // keep consistent with API

    $appsThisWeek = Application::where('created_at', '>=', $start)->count();

    // Option A: use the scope you defined
    $reservationsActive = Reservation::active()->count();

    // Option B: explicit list (same as scopeActive)
    // $reservationsActive = Reservation::whereIn('status', ['reserved', 'confirmed'])->count();

    return Inertia::render('Public/Landing', [
        'stats' => [
            'appsThisWeek'       => $appsThisWeek,
            'reservationsActive' => $reservationsActive,
        ],
    ]);
}


    // public function stats() // JSON endpoint for polling
    // {
    //     return response()->json($this->computeStats());
    // }

    // private function computeStats(): array
    // {
    //     // Use Sunday-start week (PH common); switch to startOfWeek() if you prefer Mon.
    //     $start = Carbon::now()->startOfWeek(Carbon::SUNDAY);
    //     $end   = Carbon::now()->endOfWeek(Carbon::SUNDAY);

    //     $appsThisWeek = Application::whereBetween('created_at', [$start, $end])->count();

    //     // “Active” = anything not done/cancelled. Adjust to your statuses.
    //     $reservationsActive = Reservation::whereNotIn('status', ['completed','cancelled','denied'])
    //         ->orWhereNull('status') // legacy rows
    //         ->count();

    //     return [
    //         'appsThisWeek'       => $appsThisWeek,
    //         'reservationsActive' => $reservationsActive,
    //     ];
    // }
}
