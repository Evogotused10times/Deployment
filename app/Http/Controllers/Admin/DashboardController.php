<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Reservation;
use App\Models\Plot;
use App\Models\Interment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\CarbonImmutable;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // ---- Applications ----
        $appsQuery = Application::query();
        $appsCounts = [
            'total'    => (clone $appsQuery)->count(),
            'pending'  => (clone $appsQuery)->where('status', 'pending')->count(),
            'approved' => (clone $appsQuery)->where('status', 'approved')->count(),
            'denied'   => (clone $appsQuery)->where('status', 'denied')->count(),
        ];

        $recentApplications = Application::latest()
            ->take(5)
            ->get([
                'id',
                'applicant_name',
                'applicant_email',
                'service_type',
                'status',
                'created_at',
                'assigned_plot_id',
            ]);

        // ---- Reservations ----
        $resQuery = Reservation::query();
        $resCounts = [
            'total'     => (clone $resQuery)->count(),
            'pending'   => (clone $resQuery)->where('status', 'pending')->count(),
            'reserved'  => (clone $resQuery)->where('status', 'reserved')->count(),
            'confirmed' => (clone $resQuery)->where('status', 'confirmed')->count(),
        ];

        $recentReservations = Reservation::with('plot:id,lot_number')
            ->latest()
            ->take(5)
            ->get([
                'id',
                'plot_id',
                'reserved_by_name',
                'status',
                'start_date',
                'expires_at',
                'created_at',
            ]);

        // ---- Plots ----
        $plotsQuery = Plot::query();
        $plotCounts = [
            'total'    => (clone $plotsQuery)->count(),
            'vacant'   => (clone $plotsQuery)->where('status', 'vacant')->count(),
            'reserved' => (clone $plotsQuery)->where('status', 'reserved')->count(),
            'occupied' => (clone $plotsQuery)->where('status', 'occupied')->count(),
            'unmapped' => (clone $plotsQuery)
                ->where(function ($q) {
                    $q->whereNull('geojson')
                      ->orWhere('geojson', '[]');
                })
                ->count(),
        ];

        // ---- Interments ----
        $now       = CarbonImmutable::now('Asia/Manila');
        $weekStart = $now->startOfWeek(); // Monday
        $weekEnd   = $now->endOfWeek();   // Sunday

        $interQuery = Interment::query();

        $intermentCounts = [
            // total rows in interments
            'total'    => (clone $interQuery)->count(),

            // Upcoming = starting today or later, and still scheduled
            'upcoming' => (clone $interQuery)
                ->whereDate('start_at', '>=', $now->toDateString())
                ->where('status', 'scheduled')
                ->count(),

            // This week (based on start_at)
            'week'     => (clone $interQuery)
                ->whereBetween('start_at', [
                    $weekStart->toDateString(),
                    $weekEnd->toDateString(),
                ])
                ->count(),

            // Completed interments
            'done'     => (clone $interQuery)
                ->where('status', 'completed')
                ->count(),
        ];

        return Inertia::render('Admin/Dashboard', [
            'appsCounts'          => $appsCounts,
            'resCounts'           => $resCounts,
            'plotCounts'          => $plotCounts,
            'intermentCounts'     => $intermentCounts,
            'recentApplications'  => $recentApplications,
            'recentReservations'  => $recentReservations,
        ]);
    }
}
