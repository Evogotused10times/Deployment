<?php

namespace App\Http\Controllers;

use App\Models\Plot;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicMapController extends Controller
{
    public function index(Request $request)
    {
        // This list is kept very light; the actual map geometry comes from
        // /api/public/plots as GeoJSON. You can safely keep or remove this.
        $plots = Plot::query()
            ->orderBy('lot_number')
            ->get([
                'id',
                'lot_number',
                'status',
                'plot_type',   // so you can use it in Vue if ever needed
            ]);

        return Inertia::render('Public/Map', [
            'plots'        => $plots,
            'from'         => $request->query('from'),
            'service_type' => $request->query('service_type'),
        ]);
    }
}
