<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plot;

class PlotApiController extends Controller
{
    public function index()
    {
        $plots = Plot::with('section')->get();

        $features = $plots->map(function (Plot $plot) {
            if (!$plot->geojson) {
                return null;
            }

            $geometry = json_decode($plot->geojson, true);
            if (!$geometry) {
                return null;
            }

            return [
                'type'       => 'Feature',
                'id'         => $plot->id,
                'properties' => [
                    'id'              => $plot->id,
                    'lot_number'      => $plot->lot_number,
                    'status'          => $plot->status,
                    'section'         => optional($plot->section)->name,
                    'plot_type'       => $plot->plot_type,        // ← important
                    'occupant_name'   => $plot->occupant_name,
                    'occupant_contact'=> $plot->occupant_contact,
                    // add more if admin map needs them
                ],
                'geometry'   => $geometry,
            ];
        })->filter()->values()->all();

        return response()->json([
            'type'     => 'FeatureCollection',
            'features' => $features,
        ]);
    }
}
