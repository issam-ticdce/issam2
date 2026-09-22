<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sector;
use App\Models\Startup;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /** "/" : redirige vers la langue du navigateur. */
    public function redirect(Request $request)
    {
        $locale = $request->getPreferredLanguage(config('ticdce.locales')) ?? config('ticdce.locales')[0];

        return redirect()->route('home', ['locale' => $locale]);
    }

    public function index()
    {
        $featured = Startup::published()->with('sector')
            ->orderByDesc('is_featured')->latest('approved_at')->take(6)->get();

        $products = Product::visible()->with('startup')
            ->orderByDesc('is_featured')->latest('approved_at')->take(8)->get();

        $sectors = Sector::withCount(['startups' => fn ($q) => $q->published()])
            ->orderBy('sort')->get()->where('startups_count', '>', 0);

        $stats = [
            'startups' => Startup::published()->count(),
            'products' => Product::visible()->count(),
            'sectors' => $sectors->count(),
        ];

        return view('site.home', compact('featured', 'products', 'sectors', 'stats'));
    }
}
