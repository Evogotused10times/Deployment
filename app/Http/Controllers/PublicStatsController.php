<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\CarbonImmutable;

class PublicStatsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $weekStart = CarbonImmutable::now('Asia/Manila')->startOfWeek(); // Monday

            // Applications this week
            $appsThisWeek = 0;
            if (Schema::hasTable('applications')) {
                $q = DB::table('applications');
                if (Schema::hasColumn('applications','created_at')) {
                    $q->where('created_at', '>=', $weekStart);
                } elseif (Schema::hasColumn('applications','submitted_at')) {
                    $q->where('submitted_at', '>=', $weekStart);
                }
                $appsThisWeek = (int) $q->count();
            }

            // Active reservations = reserved + confirmed
            $reservationsActive = 0;
            if (Schema::hasTable('reservations')) {
                $r = DB::table('reservations');
                if (Schema::hasColumn('reservations','status')) {
                    $r->whereIn('status', ['reserved', 'confirmed']);
                }
                $reservationsActive = (int) $r->count();
            }

            return response()->json([
                'appsThisWeek'       => $appsThisWeek,
                'reservationsActive' => $reservationsActive,
            ]);
        } catch (\Throwable $e) {
            Log::error('PublicStats failed', [
                'msg'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'appsThisWeek'       => 0,
                'reservationsActive' => 0,
                'error'              => 'stats_unavailable',
            ], 200);
        }
    }
}
