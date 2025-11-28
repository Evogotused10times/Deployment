<?php

namespace App\Http\Controllers;

use App\Models\Plot;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PlotController extends Controller
{
    /**
     * PUBLIC: Read-only GeoJSON of plots for the front-facing map.
     * Route: GET /api/public/plots
     */
    public function index(): JsonResponse
    {
        try {
            $plots = Plot::query()
                ->with([
                    'latestApprovedApplication',
                    'latestActiveReservation',
                    'section',
                    // no need to eager-load candles; we only need a count
                ])
                ->get();

            $features = $plots->map(function (Plot $plot) {
                $raw = $plot->geojson ?? null;
                $geom = null;

                if (is_string($raw)) {
                    $raw = trim($raw);
                    if ($raw !== '') {
                        $tmp = json_decode($raw, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $geom = $tmp;
                        }
                    }
                } elseif (is_array($raw)) {
                    $geom = $raw;
                }

                if (!is_array($geom) || !isset($geom['type'])) {
                    return null;
                }

                $application = $plot->latestApprovedApplication;
                $reservation = $plot->latestActiveReservation;

                $displayName =
                    $plot->occupant_name
                    ?? optional($reservation)->reserved_by_name
                    ?? optional($reservation)->applicant_name
                    ?? optional($application)->deceased_name
                    ?? optional($application)->deceased_full_name
                    ?? optional($application)->applicant_name
                    ?? optional($application)->applicant_full_name
                    ?? null;

                $sectionLabel = optional($plot->section)->code
                    ?? optional($plot->section)->name
                    ?? null;

                // Candle count (defensive)
                $candleCount = 0;
                try {
                    if (method_exists($plot, 'candles')) {
                        $candleCount = $plot->candles()->count();
                    }
                } catch (\Throwable $e) {
                    Log::warning('Count candles failed for plot ' . $plot->id . ': ' . $e->getMessage());
                }

                return [
                    'type'     => 'Feature',
                    'id'       => $plot->id,
                    'geometry' => $geom,
                    'properties' => [
                        'id'            => $plot->id,
                        'lot_number'    => $plot->lot_number,
                        'status'        => $plot->status,
                        'section'       => $sectionLabel,
                        'block'         => $plot->block_level,
                        'occupant_name' => $displayName,
                        'service_type'  => null,
                        'candle_count'  => $candleCount,
                    ],
                ];
            })->filter()->values();

            return response()->json([
                'type'     => 'FeatureCollection',
                'features' => $features,
            ]);
        } catch (\Throwable $e) {
            Log::error('GET /api/public/plots failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Server error'], 500);
        }
    }
}
