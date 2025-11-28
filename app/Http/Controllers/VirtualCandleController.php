<?php

namespace App\Http\Controllers;

use App\Models\Candle;
use App\Models\Plot;
use Illuminate\Http\Request;

class VirtualCandleController extends Controller
{
    /**
     * GET /api/public/candles
     * Params:
     *   - plot_id (required)
     *   - limit (optional)
     */
    public function index(Request $request)
    {
        $plotId = $request->query('plot_id');
        if (! $plotId) {
            return response()->json(['message' => 'plot_id is required'], 422);
        }

        $plot = Plot::find($plotId);
        if (! $plot) {
            return response()->json(['message' => 'Plot not found'], 404);
        }

        $limit = (int) $request->query('limit', 20);
        $limit = max(1, min($limit, 100));

        $candles = Candle::query()
            ->where('plot_id', $plot->id)
            ->active()
            ->orderByDesc('lit_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function (Candle $candle) {
                return [
                    'id'        => $candle->id,
                    'name'      => $candle->name ?: 'Anonymous',
                    'message'   => $candle->message,
                    'lit_at'    => optional($candle->lit_at)->toIso8601String(),
                    'created_at'=> $candle->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'plot_id' => $plot->id,
            'candles' => $candles,
        ]);
    }

    /**
     * POST /api/public/candles
     * Payload:
     *  - plot_id (required)
     *  - name (optional)
     *  - message (optional, but at least one of name/message required)
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'plot_id' => 'required|exists:plots,id',
            'name'    => 'nullable|string|max:255',
            'message' => 'nullable|string|max:1000',
        ]);

        if (! $data['name'] && ! $data['message']) {
            return response()->json([
                'message' => 'Please provide a name or a short message.',
            ], 422);
        }

        $plot = Plot::findOrFail($data['plot_id']);
        if ($plot->status !== 'occupied') {
            return response()->json([
                'message' => 'You can only light a candle for occupied plots.',
            ], 422);
        }

        // Lifespan: 7 days from now (you can adjust)
        $now = now();
        $ttlDays = 7;

        $candle = Candle::create([
            'plot_id'    => $plot->id,
            'name'       => $data['name'] ?: null,
            'message'    => $data['message'] ?: null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'lit_at'     => $now,
            'expires_at' => $now->copy()->addDays($ttlDays),
        ]);

        return response()->json([
            'ok'      => true,
            'message' => 'Candle lit successfully.',
            'candle'  => [
                'id'        => $candle->id,
                'name'      => $candle->name ?: 'Anonymous',
                'message'   => $candle->message,
                'lit_at'    => $candle->lit_at->toIso8601String(),
                'expires_at'=> optional($candle->expires_at)->toIso8601String(),
            ],
        ], 201);
    }
}
