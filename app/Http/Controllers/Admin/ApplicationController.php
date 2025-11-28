<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ApplicationSubmissionReceived;
use App\Mail\ApplicationSubmittedToAdmin;
use App\Models\Application;
use App\Models\Plot;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class ApplicationController extends Controller
{
    /**
     * Public application form.
     * Accepts optional query params:
     *  - assigned_plot_id or plot_id
     *  - selected_lot or lot_number
     *  - service_type
     *  - from=apply (when returning from map picker)
     */
    public function create(Request $request)
    {
        $plotId    = $request->query('assigned_plot_id') ?? $request->query('plot_id');
        $lotNumber = $request->query('selected_lot') ?? $request->query('lot_number');

        return Inertia::render('Public/Apply', [
            'preselectedPlot' => [
                'id'         => $plotId,
                'lot_number' => $lotNumber,
            ],
            'from'         => $request->query('from'),
            'service_type' => $request->query('service_type'),
        ]);
    }

    public function store(Request $request)
    {
        // 1) Validate input (extended with assigned_plot_id)
        $data = $request->validate([
            'applicant_name'       => 'required|string|max:255',
            'applicant_email'      => 'required|email',
            'applicant_phone'      => 'nullable|string|max:50',
            'applicant_address'    => 'nullable|string',
            'facebook_messenger'   => 'nullable|string',
            'next_of_kin'          => 'nullable|string|max:255',
            'next_of_kin_contact'  => 'nullable|string|max:255',

            'deceased_name'        => 'required|string|max:255',
            'deceased_dod'         => 'nullable|date',
            'deceased_age'         => 'nullable|integer|min:0|max:120',

            'service_type'         => 'required|in:Lawn Lot,Garden Lot,Mausoleum,Community Vaults',
            'preferred_plots'      => 'nullable|array',
            'preferred_plots.*.lot_number' => 'nullable|string',

            'terms'                => 'nullable|string',
            'remarks'              => 'nullable|string',

            'allow_auto_assign'    => 'nullable|boolean',
            'selection_mode'       => 'nullable|in:staff-guided,map,near-family',
            'family_reference'     => 'nullable|string|max:255',
            'rites'                => 'nullable|string|max:50',
            'rites_notes'          => 'nullable|string',
            'wake_pref'            => 'nullable|string|max:100',
            'interment_window'     => 'nullable|string|max:100',

            // selected plot coming from the public map (optional)
            'assigned_plot_id'     => 'nullable|exists:plots,id',

            'privacy_consent'      => 'nullable|boolean',
        ]);

        // Normalise booleans
        $data['allow_auto_assign'] = (bool)($data['allow_auto_assign'] ?? false);
        $data['privacy_consent']   = (bool)($data['privacy_consent'] ?? false);

        // 2) Anti-spam: cap submissions by email
        $email = strtolower($request->input('applicant_email'));
        $since = Carbon::now()->subDays(3);   // 3-day window

        // Max 2 submissions in the last 3 days (per email)
        $submissionsWindow = Application::query()
            ->whereRaw('LOWER(applicant_email) = ?', [$email])
            ->where('created_at', '>=', $since)
            ->count();

        if ($submissionsWindow >= 2) {
            return back()
                ->withErrors([
                    'applicant_email' =>
                        'You’ve reached the limit of 2 applications within 3 days. Please try again later.',
                ])
                ->withInput();
        }

        // Optional guard: at most 2 total PENDING for the same email
        $pendingTotal = Application::query()
            ->whereRaw('LOWER(applicant_email) = ?', [$email])
            ->where('status', 'pending')
            ->count();

        if ($pendingTotal >= 2) {
            return back()
                ->withErrors([
                    'applicant_email' =>
                        'You already have 2 pending applications. Please wait for a decision before submitting more.',
                ])
                ->withInput();
        }

        // 3) If a specific plot was chosen, ensure it's still vacant
        $plotId = $data['assigned_plot_id'] ?? null;
        if ($plotId) {
            $plot = Plot::where('id', $plotId)->first();

            if (!$plot || $plot->status !== 'vacant') {
                return back()
                    ->withErrors([
                        'assigned_plot_id' =>
                            'The selected plot is no longer vacant. Please choose another available plot.',
                    ])
                    ->withInput();
            }
        }

        // 4) Create the application + reserve the plot in a transaction
        $app = null;

        DB::transaction(function () use (&$app, $data, $plotId) {
            // 4a) Create the application (status default 'pending')
            $app = Application::create($data);

            // 4b) If a vacant plot was selected, mark it as reserved
            if ($plotId) {
                $plot = Plot::lockForUpdate()->find($plotId);
                if ($plot && $plot->status === 'vacant') {
                    $plot->status = 'reserved';

                    // show a meaningful name on the map — usually deceased name
                    $plot->occupant_name = $data['deceased_name'] ?? $data['applicant_name'];

                    $plot->save();
                }
            }
        });

        // 5) Notify admin + acknowledge applicant
        if ($app) {
            try {
                if (config('memorabeth.admin_email')) {
                    Mail::to(config('memorabeth.admin_email'))->send(new ApplicationSubmittedToAdmin($app));
                }
                Mail::to($app->applicant_email)->send(new ApplicationSubmissionReceived($app));
            } catch (\Throwable $e) {
                // Non-fatal: still accept the submission
                report($e);
            }
        }

        // 6) Redirect with success flash
        return redirect()
            ->route('landing')
            ->with('success', 'Application submitted. Check your email for confirmation.');
    }
}
