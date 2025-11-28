<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Interment;
use App\Models\Plot;
use App\Models\Application;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class IntermentAdminController extends Controller
{
    public function index(Request $request)
    {
        $q = Interment::query()
            ->with([
                'plot:id,lot_number,status',
                'application:id,applicant_name,deceased_name,assigned_plot_id,status',
                'reservation:id,plot_id',
            ]);

        // Filters
        if ($plotId = $request->integer('plot_id')) {
            $q->where('plot_id', $plotId);
        }

        if ($status = $request->string('status')->toString()) {
            $q->where('status', $status);
        }

        if ($from = $request->date('from')) {
            $q->where('start_at', '>=', $from->startOfDay());
        }

        // 👇 FIX: use "to" (matches Vue) instead of "end"
        if ($to = $request->date('to')) {
            $q->where('end_at', '<=', $to->endOfDay());
        }

        if ($x = trim($request->get('q', ''))) {
            $q->where(function ($w) use ($x) {
                $w->where('service_type', 'like', "%{$x}%")
                    ->orWhere('rites', 'like', "%{$x}%")
                    ->orWhere('notes', 'like', "%{$x}%")
                    ->orWhereHas('application', function ($qa) use ($x) {
                        $qa->where('applicant_name', 'like', "%{$x}%")
                            ->orWhere('deceased_name', 'like', "%{$x}%");
                    });
            });
        }

        // ❌ OLD: default to "next 30 days only" – hid past interments & some new entries
        // if (!$request->has('from') && !$request->has('to')) {
        //     $q->whereBetween('start_at', [now()->startOfDay(), now()->addDays(30)->endOfDay()]);
        // }
        // ✅ NEW: no automatic date window – show all by default

        return Inertia::render('Admin/Interments/Index', [
            'interments' => $q->orderBy('start_at')
                ->paginate(20)
                ->withQueryString(),

            // Only plots that are not yet used by any non-cancelled interment
            'availablePlots' => Plot::whereIn('status', ['vacant', 'reserved', 'occupied'])
                ->whereDoesntHave('interments', function ($q) {
                    $q->where('status', '!=', 'cancelled');
                })
                ->orderBy('lot_number')
                ->get(['id', 'lot_number', 'status']),

            'approvedApps' => Application::approved()
                ->whereNotNull('assigned_plot_id')
                // Do not show applications already used by any non-cancelled interment
                ->whereDoesntHave('interments', function ($q) {
                    $q->where('status', '!=', 'cancelled');
                })
                ->with(['assignedPlot:id,lot_number'])
                ->orderByDesc('id')
                ->limit(300)
                ->get([
                    'id',
                    'applicant_name',
                    'applicant_email',
                    'applicant_phone',
                    'deceased_name',
                    'assigned_plot_id',
                    'status',
                ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'application_id'    => [
                'required',
                Rule::exists('applications', 'id')->where(fn ($q) => $q->where('status', 'approved')),
            ],
            'plot_id'           => ['required', 'exists:plots,id'],
            'reservation_id'    => ['nullable', 'exists:reservations,id'],
            'start_at'          => ['required', 'date'],
            'end_at'            => ['required', 'date', 'after:start_at'],
            'status'            => ['required', 'in:scheduled,completed,cancelled'],
            'service_type'      => ['nullable', 'string', 'max:100'],
            'rites'             => ['nullable', 'string', 'max:100'],
            'officiant_name'    => ['nullable', 'string', 'max:100'],
            'officiant_contact' => ['nullable', 'string', 'max:100'],
            'notes'             => ['nullable', 'string'],
        ]);

        $app = Application::with('assignedPlot')->findOrFail($data['application_id']);

        // This application must not already have a non-cancelled interment
        $alreadyUsed = Interment::where('application_id', $app->id)
            ->where('status', '!=', 'cancelled')
            ->exists();

        if ($alreadyUsed) {
            return back()
                ->withErrors(['application_id' => 'This application already has an interment and cannot be used again.'])
                ->withInput();
        }

        // 👇 NEW: this plot must not already have a non-cancelled interment
        $plotInUse = Interment::where('plot_id', $data['plot_id'])
            ->where('status', '!=', 'cancelled')
            ->exists();

        if ($plotInUse) {
            return back()
                ->withErrors(['plot_id' => 'This plot already has an interment and cannot be used again.'])
                ->withInput();
        }

        // Enforce plot matches application's assigned plot if present
        if ($app->assigned_plot_id && (int) $data['plot_id'] !== (int) $app->assigned_plot_id) {
            return back()
                ->withErrors(['plot_id' => 'Plot must match the assigned plot of the approved application.'])
                ->withInput();
        }

        // If reservation is provided, it must belong to the same application and same plot
        if (!empty($data['reservation_id'])) {
            $res = Reservation::findOrFail($data['reservation_id']);

            if ((int) $res->application_id !== (int) $app->id) {
                return back()
                    ->withErrors(['reservation_id' => 'Reservation does not belong to the selected application.'])
                    ->withInput();
            }

            if ((int) $res->plot_id !== (int) $data['plot_id']) {
                return back()
                    ->withErrors(['reservation_id' => 'Reservation plot does not match the selected plot.'])
                    ->withInput();
            }
        }

        // Overlap check – still useful as defense-in-depth
        $overlap = Interment::query()
            ->where('plot_id', $data['plot_id'])
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($data) {
                $q->whereBetween('start_at', [$data['start_at'], $data['end_at']])
                    ->orWhereBetween('end_at', [$data['start_at'], $data['end_at']])
                    ->orWhere(function ($w) use ($data) {
                        $w->where('start_at', '<=', $data['start_at'])
                            ->where('end_at', '>=', $data['end_at']);
                    });
            })
            ->exists();

        if ($overlap) {
            return back()
                ->withErrors(['start_at' => 'This plot already has an interment overlapping this time window.'])
                ->withInput();
        }

        $interment = Interment::create($data);

        // If created directly as "completed", mark the plot as occupied
        if ($interment->status === 'completed') {
            $plot = Plot::find($interment->plot_id);
            if ($plot && $plot->status !== 'occupied') {
                $plot->status = 'occupied';
                $plot->save();
            }
        }

        return back()->with('success', 'Interment scheduled.');
    }

    public function updateStatus(Request $request, Interment $interment)
    {
        $data = $request->validate([
            'status' => 'required|in:scheduled,completed,cancelled',
        ]);

        $interment->status = $data['status'];
        $interment->save();

        if ($data['status'] === 'completed') {
            $plot = $interment->plot;
            if ($plot && $plot->status !== 'occupied') {
                $plot->status = 'occupied';
                $plot->save();
            }
        }

        return back()->with('success', 'Interment status updated.');
    }

    public function update(Request $request, Interment $interment)
    {
        $data = $request->validate([
            'application_id'    => [
                'required',
                Rule::exists('applications', 'id')->where(fn ($q) => $q->where('status', 'approved')),
            ],
            'plot_id'           => ['required', 'exists:plots,id'],
            'reservation_id'    => ['nullable', 'exists:reservations,id'],
            'start_at'          => ['required', 'date'],
            'end_at'            => ['required', 'date', 'after:start_at'],
            'status'            => ['required', 'in:scheduled,completed,cancelled'],
            'service_type'      => ['nullable', 'string', 'max:100'],
            'rites'             => ['nullable', 'string', 'max:100'],
            'officiant_name'    => ['nullable', 'string', 'max:100'],
            'officiant_contact' => ['nullable', 'string', 'max:100'],
            'notes'             => ['nullable', 'string'],
        ]);

        $app = Application::with('assignedPlot')->findOrFail($data['application_id']);

        // Prevent reusing the same application for another non-cancelled interment
        $alreadyUsed = Interment::where('application_id', $app->id)
            ->where('id', '!=', $interment->id)
            ->where('status', '!=', 'cancelled')
            ->exists();

        if ($alreadyUsed) {
            return back()
                ->withErrors(['application_id' => 'This application is already used by another interment and cannot be reused.'])
                ->withInput();
        }

        // 👇 NEW: prevent reusing plot for another non-cancelled interment
        $plotInUse = Interment::where('plot_id', $data['plot_id'])
            ->where('id', '!=', $interment->id)
            ->where('status', '!=', 'cancelled')
            ->exists();

        if ($plotInUse) {
            return back()
                ->withErrors(['plot_id' => 'This plot already has an interment and cannot be used again.'])
                ->withInput();
        }

        if ($app->assigned_plot_id && (int) $data['plot_id'] !== (int) $app->assigned_plot_id) {
            return back()
                ->withErrors(['plot_id' => 'Plot must match the assigned plot of the approved application.'])
                ->withInput();
        }

        if (!empty($data['reservation_id'])) {
            $res = Reservation::findOrFail($data['reservation_id']);

            if ((int) $res->application_id !== (int) $app->id) {
                return back()
                    ->withErrors(['reservation_id' => 'Reservation does not belong to the selected application.'])
                    ->withInput();
            }

            if ((int) $res->plot_id !== (int) $data['plot_id']) {
                return back()
                    ->withErrors(['reservation_id' => 'Reservation plot does not match the selected plot.'])
                    ->withInput();
            }
        }

        // Overlap check excluding this interment
        $overlap = Interment::query()
            ->where('plot_id', $data['plot_id'])
            ->where('id', '!=', $interment->id)
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($data) {
                $q->whereBetween('start_at', [$data['start_at'], $data['end_at']])
                    ->orWhereBetween('end_at', [$data['start_at'], $data['end_at']])
                    ->orWhere(function ($w) use ($data) {
                        $w->where('start_at', '<=', $data['start_at'])
                            ->where('end_at', '>=', $data['end_at']);
                    });
            })
            ->exists();

        if ($overlap) {
            return back()
                ->withErrors(['start_at' => 'This plot already has an interment overlapping this time window.'])
                ->withInput();
        }

        $interment->update($data);

        if ($interment->status === 'completed') {
            $plot = Plot::find($interment->plot_id);
            if ($plot && $plot->status !== 'occupied') {
                $plot->status = 'occupied';
                $plot->save();
            }
        }

        return back()->with('success', 'Interment updated.');
    }

    public function destroy(Interment $interment)
    {
        $interment->delete();
        return back()->with('success', 'Interment deleted.');
    }
}
