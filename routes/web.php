<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public controllers
use App\Http\Controllers\PublicController;
use App\Http\Controllers\Admin\ApplicationController as PublicApplyController;
use App\Http\Controllers\AnnouncementController as PublicAnnouncementController;
use App\Http\Controllers\PublicMapController;
use App\Http\Controllers\PlotController;

// Admin controllers
use App\Http\Controllers\Admin\ApplicationAdminController;
use App\Http\Controllers\Admin\PlotAdminController;
use App\Http\Controllers\Admin\ReservationAdminController;
use App\Http\Controllers\Admin\MapController;
use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Admin\IntermentAdminController;
use App\Http\Controllers\Admin\DashboardController;

// Auth
use App\Http\Controllers\Auth\AuthenticatedSessionController;

/*
|--------------------------------------------------------------------------
| Public Pages
|--------------------------------------------------------------------------
*/
Route::get('/', [PublicController::class, 'Landing'])->name('landing');
Route::get('/about', fn () => Inertia::render('Public/About'))->name('about');
Route::get('/legal', fn () => Inertia::render('Public/Legal'))->name('legal');
Route::get('/careers', fn () => Inertia::render('Public/Careers'))->name('careers');

/* Public Announcements */
Route::get('/announcements', [PublicAnnouncementController::class, 'index'])->name('announcements.index');
Route::get('/announcements/{announcement:slug?}', [PublicAnnouncementController::class, 'show'])->name('announcements.show');

/* Public Apply (both legacy /Public/Apply and lowercase /apply) */
Route::get('/Public/Apply',  [PublicApplyController::class, 'create'])->name('apply.create');
Route::post('/Public/Apply', [PublicApplyController::class, 'store'])
    ->name('apply.store')
    ->middleware('throttle:60,1');

Route::get('/apply',  [PublicApplyController::class, 'create']);
Route::post('/apply', [PublicApplyController::class, 'store'])
    ->middleware('throttle:60,1');
Route::get('/map', [PublicMapController::class, 'index'])
    ->name('public.map');

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
*/
Route::get('/login',  [AuthenticatedSessionController::class, 'create'])->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.attempt');
Route::match(['post','get'], '/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

/*
|--------------------------------------------------------------------------
| Admin (protected)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->as('admin.')->middleware(['auth','verified','admin'])->group(function () {
    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Applications
    Route::get   ('/applications',                 [ApplicationAdminController::class, 'index'])->name('applications.index');
    Route::get   ('/applications/{application}',   [ApplicationAdminController::class, 'show'])->name('applications.show');
    Route::post  ('/applications',                 [ApplicationAdminController::class, 'store'])->name('applications.store');
    Route::patch ('/applications/{application}',   [ApplicationAdminController::class, 'update'])->name('applications.update');
    Route::delete('/applications/{application}',   [ApplicationAdminController::class, 'destroy'])->name('applications.destroy');

    // Applications: Approve / Deny
    Route::post('/applications/{application}/approved', [ApplicationAdminController::class, 'approve'])->name('applications.approve');
    Route::post('/applications/{application}/denied',   [ApplicationAdminController::class, 'deny'])->name('applications.deny');

    // Applications: Inspect (modal data)
    Route::get('/applications/{application}/inspect', [ApplicationAdminController::class, 'inspect'])->name('applications.inspect');

    // Reservations
    Route::get   ('/reservations',                       [ReservationAdminController::class, 'index'])->name('reservations.index');
    Route::post  ('/reservations',                       [ReservationAdminController::class, 'store'])->name('reservations.store');
    Route::patch ('/reservations/{reservation}/status',  [ReservationAdminController::class, 'updateStatus'])->name('reservations.status');
    Route::post  ('/reservations/{reservation}/status',  [ReservationAdminController::class, 'updateStatus']); // fallback for method-spoof
    Route::delete('/reservations/{reservation}',         [ReservationAdminController::class, 'destroy'])->name('reservations.destroy');

    // Plots
    Route::get   ('/plots',           [PlotAdminController::class, 'index'])->name('plots.index');
    Route::get   ('/plots/create',    fn () => Inertia::render('Admin/AddPlot'))->name('plots.create');
    Route::post  ('/plots/import',    [PlotAdminController::class, 'import'])->name('plots.import');
    Route::post  ('/plots',           [PlotAdminController::class, 'store'])->name('plots.store');
    Route::patch ('/plots/{plot}',    [PlotAdminController::class, 'update'])->name('plots.update');
    Route::post ('/plots/{plot}',     [PlotAdminController::class, 'update']);
    Route::delete('/plots/{plot}',    [PlotAdminController::class, 'destroy'])->name('plots.destroy');

    // Map (admin)
    Route::get('/map',    fn () => Inertia::render('Admin/MapView'))->name('map');
    Route::get('/map-gl', [MapController::class, 'showGL'])->name('map.gl');

    // Admin Announcements
    Route::get   ('/announcements',                              [AdminAnnouncementController::class, 'index'])->name('announcements.index');
    Route::post  ('/announcements',                              [AdminAnnouncementController::class, 'store'])->name('announcements.store');
    Route::patch ('/announcements/{announcement}',               [AdminAnnouncementController::class, 'update'])->name('announcements.update');
    Route::post  ('/announcements/{announcement}/toggle-publish',[AdminAnnouncementController::class, 'togglePublish'])->name('announcements.toggle-publish');
    Route::post  ('/announcements/{announcement}/toggle-pin',    [AdminAnnouncementController::class, 'togglePin'])->name('announcements.toggle-pin');
    Route::delete('/announcements/{announcement}',               [AdminAnnouncementController::class, 'destroy'])->name('announcements.destroy');

    // Interments
    Route::get   ('/interments',                      [IntermentAdminController::class, 'index'])->name('interments.index');
    Route::post  ('/interments',                      [IntermentAdminController::class, 'store'])->name('interments.store');
    Route::patch ('/interments/{interment}',          [IntermentAdminController::class, 'update'])->name('interments.update');
    Route::patch ('/interments/{interment}/status',   [IntermentAdminController::class, 'updateStatus'])->name('interments.status');
    Route::post  ('/interments/{interment}/status',   [IntermentAdminController::class, 'updateStatus']); // method-spoof fallback
    Route::delete('/interments/{interment}',          [IntermentAdminController::class, 'destroy'])->name('interments.destroy');
});

/*
|--------------------------------------------------------------------------
| Brochure (public)
|--------------------------------------------------------------------------
*/
Route::get('/brochure', fn () => Inertia::render('Brochure', [
    'products' => [
        [
            'key' => 'mausoleum',
            'title' => 'Mausoleum',
            'summary' => 'Private family mausoleums with premium materials and serene placement.',
            'bullets' => ['Family-capacity', 'Custom design options', 'Prime locations'],
            'cta' => route('apply.create') . '?service_type=Mausoleum',
        ],
        [
            'key' => 'lawn',
            'title' => 'Lawn Lot',
            'summary' => 'Dignified single plots with uniform lawn-level markers.',
            'bullets' => ['Standard sizes', 'Orderly rows', 'Staff-assigned option'],
            'cta' => route('apply.create') . '?service_type=Lawn%20Lot',
        ],
        [
            'key' => 'vaults',
            'title' => 'Community Vaults',
            'summary' => 'Shared vault systems with secure and respectful arrangements.',
            'bullets' => ['Efficient', 'Secure', 'Community-managed'],
            'cta' => route('apply.create') . '?service_type=Community%20Vaults',
        ],
        [
            'key' => 'garden',
            'title' => 'Garden Lot',
            'summary' => 'Select garden plots with thoughtful landscaping and access.',
            'bullets' => ['Upgraded locations', 'Landscape proximity', 'Visitor-friendly'],
            'cta' => route('apply.create') . '?service_type=Garden%20Lot',
        ],
    ]
]))->name('brochure');

/* Redirect legacy /dashboard to admin dashboard */
Route::get('/dashboard', fn () => redirect()->route('admin.dashboard'))->name('dashboard');
