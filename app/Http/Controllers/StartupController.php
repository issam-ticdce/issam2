<?php

namespace App\Http\Controllers;

use App\Enums\Need;
use App\Enums\Stage;
use App\Models\Sector;
use App\Models\Startup;
use Illuminate\Http\Request;

class StartupController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $startups = Startup::published()->with('sector')
            ->when($q, fn ($query) => $query->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('tagline', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            }))
            ->when($request->query('sector'), fn ($query, $slug) => $query->whereHas('sector', fn ($s) => $s->where('slug', $slug)))
            ->when(Stage::tryFrom((string) $request->query('stage')), fn ($query, $stage) => $query->where('stage', $stage))
            ->when(Need::tryFrom((string) $request->query('need')), fn ($query, $need) => $query->where('needs', 'like', '%"'.$need->value.'"%'))
            ->orderByDesc('is_featured')->orderBy('name')
            ->paginate(18)->withQueryString();

        return view('site.startups.index', [
            'startups' => $startups,
            'sectors' => Sector::orderBy('sort')->get(),
        ]);
    }

    public function show(Startup $startup)
    {
        abort_unless($startup->is_published, 404);

        $startup->increment('views');

        return $this->render($startup);
    }

    /** Aperçu de la version en attente (TICDCE ou membres de la startup). */
    public function preview(Request $request, Startup $startup)
    {
        $user = $request->user();
        abort_unless($user && ($user->isAdmin() || $user->startup_id === $startup->id), 403);

        $this->usePreviewLocale($request);

        return $this->render($startup->withDraftApplied(), preview: true);
    }

    protected function render(Startup $startup, bool $preview = false)
    {
        $products = $startup->products()->published()->get();

        return view('site.startups.show', compact('startup', 'products', 'preview'));
    }
}
