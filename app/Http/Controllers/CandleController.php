<?php

namespace App\Http\Controllers;

use App\Models\Plot;
use App\Models\VirtualCandle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CandleController extends Controller
{
    /**
     * POST /api/public/candles
     * Body: { plot_id, name?, message? }
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'plot_id' => 'required|exists:plots,id',
            'name'    => 'nullable|string|max:100',
            'message' => 'nullable|string|max:255',
        ]);

        $plot = Plot::findOrFail($data['plot_id']);

        // Simple anti-abuse fingerprint (hash of IP + UA + plot)
        $ip = $request->ip() ?? 'unknown';
        $ua = substr((string) $request->userAgent(), 0, 200);
        $ipHash = hash('sha256', $ip . '|' . $ua . '|' . $plot->id);

        try {
            $candle = VirtualCandle::create([
                'plot_id' => $plot->id,
                'name'    => $data['name']    ?: null,
                'message' => $data['message'] ?: null,
                'ip_hash' => $ipHash,
            ]);
        } catch (\Throwable $e) {
            Log::error('Virtual candle failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ok'      => false,
                'message' => 'Could not save candle.',
            ], 500);
        }

        return response()->json([
            'ok'      => true,
            'message' => 'Candle lit.',
            'candle'  => [
                'id'      => $candle->id,
                'name'    => $candle->name,
                'message' => $candle->message,
                'lit_at'  => $candle->created_at,
            ],
        ]);
    }
}
