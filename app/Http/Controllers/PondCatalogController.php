<?php

namespace App\Http\Controllers;

use App\Models\Pond;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

/**
 * The public website: only balls the admin has chosen to show appear here.
 */
class PondCatalogController extends Controller
{
    public function home(): Response
    {
        return Inertia::render('pond/welcome', [
            'canRegister' => Features::enabled(Features::registration()),
        ]);
    }

    public function index(): Response
    {
        return Inertia::render('pond/ponds/index', [
            'ponds' => Pond::query()->onFrontend()->ordered()->get()->map->toCard(),
        ]);
    }

    public function show(Pond $pond): Response
    {
        abort_unless($pond->is_published && $pond->show_on_frontend, 404);

        return Inertia::render('pond/ponds/show', [
            'pond' => $pond->toCard(),
        ]);
    }
}
