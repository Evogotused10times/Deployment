<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Models\Plot;
use App\Models\Section;
use App\Http\Controllers\PublicStatsController;
use App\Http\Controllers\PlotController;
use App\Http\Controllers\VirtualCandleController;
use App\Http\Controllers\CandleController;


// ✅ Virtual candles (public)
Route::get('/public/candles',  [CandleController::class, 'index']);
Route::post('/public/candles', [CandleController::class, 'store']);

/**
 * PUBLIC: List and create virtual candles for plots
 */
Route::get('/public/candles', [VirtualCandleController::class, 'index']);
Route::post('/public/candles', [VirtualCandleController::class, 'store'])
    ->middleware('throttle:20,1');
// ✅ PUBLIC, read-only plots for the front-facing map
Route::get('/public/plots', [PlotController::class, 'index']);

// ✅ PUBLIC stats for homepage hero
Route::get('/public/stats', [PublicStatsController::class, 'index']); // no auth

/**
 * ADMIN: Return plots as a GeoJSON FeatureCollection for the map (full data)
 */
Route::get('/plots', function () {
    try {
        // Eager load related data we can mine for a name
        $plots = Plot::with([
            'interments.application',
            'reservations',
            'latestApprovedApplication',
        ])->get();

        $features = $plots->map(function (Plot $plot) {
            // --- GEOMETRY NORMALIZATION (unchanged) ---
            $raw = $plot->geojson ?? $plot->geometry ?? null;

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
                return null; // skip invalid shapes
            }

            // --- RESOLVE OCCUPANT / RESERVED NAME ---
            // latest interment (by start_at)
            $latestInterment = $plot->interments
                ->sortByDesc('start_at')
                ->first();

            $applicationFromInterment = $latestInterment?->application;

            // latest active reservation (reserved/confirmed)
            $latestReservation = $plot->reservations
                ->whereIn('status', ['reserved', 'confirmed'])
                ->sortByDesc('created_at')
                ->first();

            // Try to find a human name in a sensible order
            $resolvedOccupant = $plot->occupant_name // manual override on plot
                ?? $applicationFromInterment?->deceased_name
                ?? $plot->latestApprovedApplication?->deceased_name
                ?? $latestReservation?->reserved_by_name;

            return [
                'type'     => 'Feature',
                'id'       => $plot->id, // for promoteId: 'id'
                'geometry' => $geom,
                'properties' => [
                    'id'               => $plot->id,
                    'lot_number'       => $plot->lot_number,
                    'status'           => $plot->status,
                    'section'          => $plot->section_code ?? null,
                    'occupant_name'    => $resolvedOccupant,
                    'occupant_contact' => $plot->occupant_contact,
                    'price'            => $plot->price,
                ],
            ];
        })->filter()->values();

        return response()->json([
            'type'     => 'FeatureCollection',
            'features' => $features,
        ]);
    } catch (\Throwable $e) {
        Log::error('GET /api/plots failed: '.$e->getMessage(), [
            'trace' => $e->getTraceAsString(),
        ]);
        return response()->json(['message' => 'Server error'], 500);
    }
});

/**
 * Create a single plot (from AddPlot form)
 * Accepts either:
 *  - geometry: { type, coordinates }
 *  - OR coordinates: [[[lng,lat],...]] with optional type (defaults to "Polygon")
 */
Route::post('/plots', function (Request $request) {
    $data = $request->validate([
        'section_id'       => 'nullable|exists:sections,id',
        'section_code'     => 'nullable|string|exists:sections,code',
        'lot_number'       => 'required|string|max:50',
        'status'           => 'required|in:vacant,reserved,occupied',
        'block_level'      => 'nullable|string|max:50',
        'description'      => 'nullable|string',
        'price'            => 'nullable|numeric',
        'occupant_name'    => 'nullable|string|max:255',
        'occupant_contact' => 'nullable|string|max:255',

        // geometry can be a PHP array OR a JSON string
        'geometry'         => 'nullable',
        // Optional alternative payload:
        'coordinates'      => 'nullable|array',
        'type'             => 'nullable|string|in:Polygon,MultiPolygon,Point,LineString,MultiLineString,MultiPoint',
    ]);

    // Resolve section id (allow id or code)
    $sectionId = $data['section_id']
        ?? Section::where('code', $data['section_code'] ?? null)->value('id');

    if (!$sectionId) {
        return response()->json([
            'errors' => [
                'section_code' => ['Section code not found.'],
            ],
        ], 422);
    }

    // Normalize geometry to array (or null)
    $geometry = $data['geometry'] ?? null;

    // geometry might be string JSON
    if (is_string($geometry) && $geometry !== '') {
        $decoded = json_decode($geometry, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'errors' => [
                    'geometry' => ['Invalid JSON.'],
                ],
            ], 422);
        }
        $geometry = $decoded;
    }

    // Or caller supplied coordinates + type
    if (!$geometry && !empty($data['coordinates'])) {
        $geometry = [
            'type'        => $data['type'] ?? 'Polygon',
            'coordinates' => $data['coordinates'],
        ];
    }

    if ($geometry !== null && (!is_array($geometry) || !isset($geometry['type'], $geometry['coordinates']))) {
        return response()->json([
            'errors' => [
                'geometry' => ['GeoJSON must contain "type" and "coordinates".'],
            ],
        ], 422);
    }

    $plot = new Plot([
        'section_id'       => $sectionId,
        'lot_number'       => $data['lot_number'],
        'status'           => $data['status'],
        'block_level'      => $data['block_level'] ?? null,
        'description'      => $data['description'] ?? null,
        'price'            => $data['price'] ?? null,
        'occupant_name'    => $data['occupant_name'] ?? null,
        'occupant_contact' => $data['occupant_contact'] ?? null,
        // If you store section_code directly on the plots table:
        'section_code'     => $data['section_code'] ?? null,
    ]);

    // Persist geometry according to Model casts (array for JSON column, string for TEXT)
    $casts = method_exists($plot, 'getCasts') ? $plot->getCasts() : [];
    if (($casts['geojson'] ?? null) === 'array') {
        $plot->geojson = $geometry;                      // JSON column (array)
    } else {
        $plot->geojson = $geometry ? json_encode($geometry) : null; // TEXT column
    }

    $plot->save();

    return response()->json(['ok' => true, 'plot' => $plot], 201);
});

/**
 * Update only the geometry of a plot
 * Accepts "geometry" (string or array) OR "coordinates"+"type" (type defaults to Polygon)
 */
Route::post('/plots/{plot}/assign-coordinates', function (Request $request, Plot $plot) {
    $payload = $request->validate([
        // Accept string or array to be flexible with callers
        'geometry'    => 'nullable',
        'coordinates' => 'nullable|array',
        'type'        => 'nullable|string|in:Polygon,MultiPolygon,Point,LineString,MultiLineString,MultiPoint',
    ]);

    $geometry = $payload['geometry'] ?? null;

    // If geometry is a JSON string, decode it
    if (is_string($geometry) && $geometry !== '') {
        $decoded = json_decode($geometry, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $geometry = $decoded;
        }
    }

    // Or build from coordinates + type
    if (!$geometry && !empty($payload['coordinates'])) {
        $geometry = [
            'type'        => $payload['type'] ?? 'Polygon',
            'coordinates' => $payload['coordinates'],
        ];
    }

    if (!$geometry || !is_array($geometry) || !isset($geometry['type'], $geometry['coordinates'])) {
        return response()->json(['message' => 'Invalid geometry payload'], 422);
    }

    // Persist according to casts
    $casts = method_exists($plot, 'getCasts') ? $plot->getCasts() : [];
    if (($casts['geojson'] ?? null) === 'array') {
        $plot->geojson = $geometry;               // JSON column (array)
    } else {
        $plot->geojson = json_encode($geometry);  // TEXT column
    }

    $plot->save();

    return response()->json(['ok' => true]);
});

/**
 * Quick search for admin sidebar/autocomplete
 */
Route::get('/plots/search', function (Request $request) {
    $q = trim((string) $request->query('q', ''));
    $query = Plot::query();

    if ($q !== '') {
        $query->where(function ($w) use ($q) {
            $w->where('lot_number', 'like', "%{$q}%")
              ->orWhere('occupant_name', 'like', "%{$q}%")
              ->orWhere('section_id', 'like', "%{$q}%");
        });
    }

    return $query->orderBy('section_id')
        ->orderBy('lot_number')
        ->limit(50)
        ->get(['id','section_id','lot_number','status','occupant_name']);
});

/**
 * Delete a plot
 */
Route::delete('/plots/{plot}', function (Plot $plot) {
    // Optional: protect against deleting OCCUPIED plots
    // if ($plot->status === 'occupied') {
    //     return response()->json(['ok'=>false,'message'=>'Cannot delete occupied plot.'], 422);
    // }
    $plot->delete();
    return response()->json(['ok' => true]);
});
