<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ApplicationStatusToApplicant;
use App\Models\Application;
use App\Models\Plot;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class ApplicationAdminController extends Controller
{
    /**
     * List applications + plots that can be assigned.
     * - Applications: latest, paginated.
     * - availablePlots: all vacant plots + any plot already assigned to a listed application
     *   (so existing assignments can still be resolved & shown by lot number).
     */
    public function index(Request $request)
    {
        $applications = Application::query()
            ->latest()
            ->paginate(10)
            ->withQueryString();

        // Vacant plots (scope available() is your existing one)
        $vacantPlots = Plot::available()
            ->orderBy('id')
            ->get(['id', 'lot_number', 'status']); // ⬅️ removed section_code

        // Any plots already assigned on the currently listed applications
        $assignedPlotIds = $applications->pluck('assigned_plot_id')->filter()->unique();

        $assignedPlots = $assignedPlotIds->isEmpty()
            ? collect()
            : Plot::whereIn('id', $assignedPlotIds)
                ->get(['id', 'lot_number', 'status']); // ⬅️ removed section_code

        // Merge and de-duplicate by id
        $plotOptions = $vacantPlots
            ->concat($assignedPlots)
            ->unique('id')
            ->values();

        return Inertia::render('Admin/Applications', [
            'applications'   => $applications,
            'availablePlots' => $plotOptions,
        ]);
    }

    public function show(Application $application)
    {
        return Inertia::render('Admin/Applications/Show', [
            'application' => $application->load('assignedPlot'),
            'plots'       => Plot::query()->get(['id', 'section_id', 'lot_number', 'status', 'geojson']),
        ]);
    }

    /**
     * Approve an application:
     * - Uses assigned_plot_id from the request if provided, otherwise falls back to the
     *   plot already stored on the application (e.g. chosen from the public map).
     * - Sets application to approved.
     * - Keeps the plot "reserved" (blocked) if not occupied.
     * - Fills occupant_name if empty, so the public map shows something meaningful.
     * - Sends email to applicant (guarded).
     * - Can only be changed within 12 hours of the last decision (see Application::isEditable()).
     */
    public function approve(Request $request, Application $application)
    {
        if (! $application->isEditable()) {
            return $this->errorResponse($request, 'This application is already locked and can no longer be changed.');
        }

        $data = $request->validate([
            // Now optional: we can also use the application’s existing assigned_plot_id
            'assigned_plot_id' => 'nullable|exists:plots,id',
            'admin_notes'      => 'nullable|string|max:255',
        ]);

        // Prefer fresh assignment from the form, otherwise use what the application already has
        $plotId = $data['assigned_plot_id'] ?? $application->assigned_plot_id;

        if (! $plotId) {
            return $this->errorResponse($request, 'Please assign a plot before approving this application.');
        }

        DB::transaction(function () use ($application, $data, $plotId) {
            // Lock the plot row to avoid race conditions
            $plot = Plot::lockForUpdate()->findOrFail($plotId);

            // Update application first
            $application->update([
                'status'           => 'approved',
                'assigned_plot_id' => $plot->id,
                'admin_notes'      => $data['admin_notes'] ?? null,
                'decision_at'      => now(),
            ]);

            // If there is no active reservation, keep/mark the plot as reserved (blocked),
            // unless it is already occupied.
            $hasActiveReservation = Reservation::query()
                ->where('plot_id', $plot->id)
                ->whereIn('status', ['reserved', 'confirmed'])
                ->exists();

            if (! $hasActiveReservation && $plot->status !== 'occupied') {
                // If it was vacant, mark as reserved
                if ($plot->status === 'vacant') {
                    $plot->status = 'reserved';
                }

                // Fill occupant_name for public map display if empty
                if (! $plot->occupant_name) {
                    $plot->occupant_name =
                        $application->deceased_name
                        ?: $application->applicant_name
                        ?: $plot->occupant_name;
                }

                $plot->save();
            }

            // Email (guarded)
            if (! empty($application->applicant_email)) {
                Mail::to($application->applicant_email)->send(
                    new ApplicationStatusToApplicant(
                        $application->fresh(),
                        [
                            'plot'        => $plot,
                            'reservation' => Reservation::where('plot_id', $plot->id)->latest()->first(),
                            'action'      => 'approved',
                        ]
                    )
                );
            }
        });

        return $this->okResponse($request, 'Application approved.');
    }

    /**
     * Deny an application:
     * - Denies and notifies (optional).
     * - Can only be changed within 12 hours of the last decision.
     */
    public function deny(Request $request, Application $application)
    {
        if (! $application->isEditable()) {
            return $this->errorResponse($request, 'This application is already locked and can no longer be changed.');
        }

        $data = $request->validate([
            'admin_notes' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($application, $data) {
            $application->update([
                'status'      => 'denied',
                'admin_notes' => $data['admin_notes'] ?? null,
                'decision_at' => now(),
            ]);

            if (! empty($application->applicant_email)) {
                Mail::to($application->applicant_email)->send(
                    new ApplicationStatusToApplicant($application->fresh(), ['action' => 'denied'])
                );
            }
        });

        return $this->okResponse($request, 'Application denied.');
    }

    /**
     * (Optional) Generic status update endpoint (kept for compatibility)
     */
    public function updateStatus(Request $request, Application $application)
    {
        $data = $request->validate([
            'status' => 'required|in:approved,denied',
        ]);

        if (! $application->isEditable()) {
            return $this->errorResponse($request, 'This application is already locked and can no longer be changed.');
        }

        $application->update([
            'status'      => $data['status'],
            'decision_at' => now(),
        ]);

        return $this->okResponse($request, 'Application status updated successfully.');
    }

    // ---- Standard CRUD ----

    public function store(Request $request)
    {
        $data = $request->validate([
            'applicant_name'  => 'required|string|max:255',
            'applicant_email' => 'required|email|max:255',
            'applicant_phone' => 'nullable|string|max:50',
            'service_type'    => 'required|in:Lawn Lot,Garden Lot,Mausoleum,Community Vaults',
            'status'          => 'nullable|string|in:pending,approved,denied',
            'remarks'         => 'nullable|string',
        ]);

        Application::create($data);

        return back()->with('success', 'Application created successfully.');
    }

    public function update(Request $request, Application $application)
    {
        $data = $request->validate([
            'applicant_name'  => 'required|string|max:255',
            'applicant_email' => 'required|email|max:255',
            'applicant_phone' => 'nullable|string|max:50',
            'service_type'    => 'required|in:Lawn Lot,Garden Lot,Mausoleum,Community Vaults',
            'status'          => 'nullable|string|in:pending,approved,denied',
            'remarks'         => 'nullable|string',
        ]);

        $application->update($data);

        return back()->with('success', 'Application updated successfully.');
    }

    public function destroy(Application $application)
    {
        // Allow delete only while the application is still editable
        if (! $application->isEditable()) {
            return back()->withErrors([
                'status' => 'This application is already locked and can no longer be deleted.',
            ]);
        }

        $application->delete();

        return back()->with('success', 'Application deleted successfully.');
    }

    /**
     * Lightweight JSON inspector for the modal viewer
     */
    public function inspect(Application $application)
    {
        $application->loadMissing(['assignedPlot.section']);

        return response()->json([
            'application' => [
                'id'               => $application->id,
                'status'           => $application->status,
                'applicant_name'   => $application->applicant_name,
                'applicant_email'  => $application->applicant_email,
                'applicant_phone'  => $application->applicant_phone,
                'applicant_address'=> $application->applicant_address,
                'service_type'     => $application->service_type,
                'schedule_date'    => $application->schedule_date ?? null,
                'schedule_time'    => $application->schedule_time ?? null,
                'deceased_name'    => $application->deceased_name,
                'remarks'          => $application->remarks,
                'created_at'       => $application->created_at,
                'updated_at'       => $application->updated_at,
                'assigned_plot_id' => $application->assigned_plot_id,
                'decision_at'      => $application->decision_at,
                'is_editable'      => $application->isEditable(),
                'plot' => $application->assignedPlot ? [
                    'lot_number' => $application->assignedPlot->lot_number,
                    'section'    => optional($application->assignedPlot->section)->name,
                    'block'      => $application->assignedPlot->block_level,
                ] : null,
                'documents' => [], // plug your relation if any
                'timeline'  => [], // optional
            ],
            'endpoints' => [
                'back'    => route('admin.applications.index'),
                'approve' => route('admin.applications.approve', $application),
                'deny'    => route('admin.applications.deny',    $application),
                'delete'  => route('admin.applications.destroy', $application),
            ],
        ]);
    }

    /* ------------------------ helpers ------------------------ */

    private function okResponse(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $message]);
        }
        return back()->with('success', $message);
    }

    private function errorResponse(Request $request, string $message, int $code = 422)
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'message' => $message], $code);
        }
        return back()->withErrors(['status' => $message]);
    }
}
