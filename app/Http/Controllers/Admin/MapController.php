<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Http\Controllers\Controller;

class MapController extends Controller
{
    public function showGL()
    {
        return Inertia::render('Admin/MapViewGL');
    }
}

