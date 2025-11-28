<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Plot;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ReservationAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = Reservation::query()
            ->with([
                'plot:id,lot_number,status',
                'application:id,applicant_name,applicant_email',
            ])
            ->latest();

        if ($status = trim((string) $request->get('status', ''))) {
            $query->where('status', $status);
        }

        if ($plotId = (int) $request->get('plot_id', 0)) {
            if ($plotId > 0) {
                $query->where('plot_id', $plotId);
            }
        }

        if ($q = trim((string) $request->get('q', ''))) {
            $query->where(function ($w) use ($q) {
                $w->where('reserved_by_name', 'like', "%{$q}%")
                  ->orWhere('reserved_by_email', 'like', "%{$q}%")
                  ->orWhere('reserved_by_phone', 'like', "%{$q}%");
            });
        }

        return Inertia::render('Admin/Reservations/Index', [
            'reservations'   => $query->paginate(15)->withQueryString(),
            'availablePlots' => Plot::available()->orderBy('id')->get(['id','lot_number','status']),
            // feed only approved apps (with a plot) to the UI select
            'approvedApps'   => Application::approved()
                ->whereNotNull('assigned_plot_id')
                ->with(['assignedPlot:id,lot_number'])
                ->orderByDesc('id')
                ->limit(300)
                ->get(['id','applicant_name','applicant_email','applicant_phone','deceased_name','assigned_plot_id']),
        ]);
    }

    /**
     * Create a reservation.
     *
     * - Requires a valid application_id (any status).
     * - UI still lists only approved+assigned apps, but the endpoint can be
     *   called from the Applications screen before approval is finalized.
     * - Identity (name/email/phone) is locked to the application, not free-typed.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'application_id'    => [
                'required',
                // Relaxed: just ensure the application exists.
                // This allows creating a reservation while the app is still pending.
                'exists:applications,id',
            ],
            'plot_id'           => ['required', 'exists:plots,id'],
            'start_date'        => ['nullable', 'date'],
            'expires_at'        => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes'             => ['nullable', 'string'],
            'status'            => ['nullable', 'in:pending,reserved,confirmed,cancelled,expired'],
        ]);

        $app = Application::with('assignedPlot')->findOrFail($data['application_id']);

        // If an application has an assigned plot, force plot consistency.
        if ($app->assigned_plot_id && (int) $data['plot_id'] !== (int) $app->assigned_plot_id) {
            return back()
                ->withErrors(['plot_id' => 'Selected plot must match the application’s assigned plot.'])
                ->withInput();
        }

        // Default status
        $data['status'] = $data['status'] ?? 'reserved';

        // Copy identity from the application—no free-typing.
        $payload = [
            'application_id'     => $app->id,
            'plot_id'            => $data['plot_id'] ?: $app->assigned_plot_id,
            'reserved_by_name'   => $app->applicant_name ?? $app->deceased_name ?? 'Unknown',
            'reserved_by_email'  => $app->applicant_email,
            'reserved_by_phone'  => $app->applicant_phone,
            'start_date'         => $data['start_date'] ?? null,
            'expires_at'         => $data['expires_at'] ?? null,
            'notes'              => $data['notes'] ?? null,
            'status'             => $data['status'],
        ];

        Reservation::create($payload);

        return back()->with('success', 'Reservation created.');
    }

    /** Update reservation status. */
    public function updateStatus(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,reserved,confirmed,cancelled,expired',
        ]);

        $reservation->update(['status' => $validated['status']]);

        return back()->with('success', 'Reservation status updated.');
    }

    /** Delete reservation. */
    public function destroy(Reservation $reservation)
    {
        $reservation->delete();

        return back()->with('success', 'Reservation deleted.');
    }
}
