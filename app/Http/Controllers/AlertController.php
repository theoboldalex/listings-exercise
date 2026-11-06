<?php

namespace App\Http\Controllers;

use App\Http\Resources\AlertResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    /**
     * The user's alerts, newest first. Listings that have since gone under
     * offer or sold are still shown, with their current status.
     */
    public function index(Request $request): Response
    {
        $alerts = $request->user()->alerts()
            ->with('listing.branch')
            ->latest()
            ->orderByDesc('id')
            ->paginate(15);

        return Inertia::render('Alerts/Index', [
            'alerts' => AlertResource::collection($alerts),
        ]);
    }
}
