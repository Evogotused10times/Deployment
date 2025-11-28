<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plot;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PlotAdminController extends Controller
{
    public function index(Request $request)
    {
        // Sections for the dropdown
        $sections = Section::withCount('plots')->get();

        // Base query with relationships
        $query = Plot::with(['section', 'latestApprovedApplication']);

        // Filter by status
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        // Filter by section
        if ($sectionId = $request->get('section_id')) {
            $query->where('section_id', $sectionId);
        }

        // Filter by plot_id (from Map GL → "Open Details")
        if ($plotId = $request->get('plot_id')) {
            $query->where('id', $plotId);
        }

        // Search (lot_number / description / occupant / linked application)
        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($w) use ($search) {
                $w->where('lot_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('occupant_name', 'like', "%{$search}%")
                  ->orWhereHas('latestApprovedApplication', function ($qa) use ($search) {
                      $qa->where('applicant_name', 'like', "%{$search}%")
                         ->orWhere('deceased_name', 'like', "%{$search}%");
                  });
            });
        }

        $plots = $query
            ->orderBy('section_id')
            ->orderBy('lot_number')
            ->paginate(50)
            ->withQueryString();

        return inertia('Admin/Plots/Index', [
            'sections' => $sections,
            'plots'    => $plots,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'section_id'   => ['required','exists:sections,id'],
            'lot_number'   => [
                'required','string',
                Rule::unique('plots', 'lot_number')->where(fn($q) =>
                    $q->where('section_id', $request->input('section_id'))
                ),
            ],
            'block_level'  => ['nullable','string'],
            'description'  => ['nullable','string'],
            'price'        => ['nullable','numeric'],
            'geojson'      => ['nullable','json'],
            'status'       => ['nullable','in:vacant,reserved,occupied'],
        ]);

        Plot::create($data);

        return back()->with('success', 'Plot created.');
    }

    public function update(Request $request, Plot $plot)
    {
        $data = $request->validate([
            'section_id'   => ['required','exists:sections,id'],
            'lot_number'   => [
                'required','string',
                Rule::unique('plots', 'lot_number')
                    ->where(fn($q) => $q->where('section_id', $request->input('section_id')))
                    ->ignore($plot->id),
            ],
            'block_level'      => ['nullable','string'],
            'description'      => ['nullable','string'],
            'price'            => ['nullable','numeric'],
            'geojson'          => ['nullable','json'],
            'status'           => ['nullable','in:vacant,reserved,occupied'],
            'occupant_name'    => ['nullable','string'],
            'occupant_contact' => ['nullable','string'],
        ]);

        // Only keys present in $data are updated, so since the Vue
        // form no longer sends occupant_* fields, those are not touched.
        $plot->update($data);

        return back()->with('success', 'Plot updated.');
    }

    public function destroy(Plot $plot)
    {
        $plot->delete();
        return back()->with('success', 'Plot deleted.');
    }

    public function import(Request $request)
{
    $v = Validator::make($request->all(), [
        'geojson' => 'required|file|mimes:json,geojson,txt'
    ]);

    if ($v->fails()) {
        return back()->withErrors($v)->withInput();
    }

    $content = file_get_contents($request->file('geojson')->getRealPath());
    $geo = json_decode($content, true);

    if (!$geo || !isset($geo['features'])) {
        return back()->with('error', 'Invalid GeoJSON file.');
    }

    // 1) Get the 4 sections (blocks) we want to distribute into,
    //    ordered by id. Adjust "take(4)" if you ever add more blocks.
    $sectionIds = Section::orderBy('id')
        ->pluck('id')
        ->take(4)        // 👈 assumes you have 4 blocks/sections
        ->values()
        ->all();

    if (count($sectionIds) === 0) {
        return back()->with('error', 'No sections found. Create sections first.');
    }

    $created = 0;
    $i = 0; // used to rotate through section IDs

    foreach ($geo['features'] as $feature) {
        if (!isset($feature['geometry'])) continue;
        $props = $feature['properties'] ?? [];

        // basic required fields fallback
        $lotNumber = $props['lot_number'] ?? uniqid('L-');

        // 2) Decide section_id for this plot
        if (isset($props['section_id']) && in_array($props['section_id'], $sectionIds)) {
            // If the GeoJSON already has a valid section_id, respect it
            $sectionId = (int) $props['section_id'];
        } else {
            // Otherwise, assign in round-robin over the 4 sections
            $sectionId = $sectionIds[$i % count($sectionIds)];
            $i++;
        }

        // avoid duplicate lot_number per section
        $exists = Plot::where('section_id', $sectionId)
            ->where('lot_number', $lotNumber)
            ->first();
        if ($exists) continue;

        Plot::create([
            'section_id'  => $sectionId,
            'lot_number'  => $lotNumber,
            'block_level' => $props['block_level'] ?? null,
            'description' => $props['description'] ?? null,
            'price'       => $props['price'] ?? null,
            'status'      => $props['status'] ?? 'vacant',
            'geojson'     => json_encode($feature['geometry']),
        ]);

        $created++;
    }

    return back()->with('success', "Imported {$created} plots.");
}

}
