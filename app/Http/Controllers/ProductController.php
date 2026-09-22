<?php

namespace App\Http\Controllers;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\Sector;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $products = Product::visible()->with('startup')
            ->when($q, fn ($query) => $query->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('summary', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhereHas('startup', fn ($s) => $s->where('name', 'like', "%{$q}%"));
            }))
            ->when(ProductType::tryFrom((string) $request->query('type')), fn ($query, $type) => $query->where('type', $type))
            ->when($request->query('sector'), fn ($query, $slug) => $query->whereHas('startup.sector', fn ($s) => $s->where('slug', $slug)))
            ->orderByDesc('is_featured')->latest('approved_at')
            ->paginate(24)->withQueryString();

        return view('site.products.index', [
            'products' => $products,
            'sectors' => Sector::orderBy('sort')->get(),
        ]);
    }

    public function show(Product $product)
    {
        abort_unless($product->is_published && $product->startup->is_published, 404);

        $product->increment('views');

        return $this->render($product);
    }

    public function preview(Request $request, Product $product)
    {
        $user = $request->user();
        abort_unless($user && ($user->isAdmin() || $user->startup_id === $product->startup_id), 403);

        $this->usePreviewLocale($request);

        return $this->render($product->withDraftApplied(), preview: true);
    }

    protected function render(Product $product, bool $preview = false)
    {
        $related = $product->startup->products()->published()
            ->whereKeyNot($product->id)->take(4)->get();

        return view('site.products.show', compact('product', 'related', 'preview'));
    }
}
