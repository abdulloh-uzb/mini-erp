<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Show the catalogue: active products, or archived ones with the filter, searchable by name or barcode.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();
        $archived = $request->boolean('archived');

        $products = Product::query()
            ->when($archived, fn ($query) => $query->archived(), fn ($query) => $query->active())
            ->when($search !== '', fn ($query) => $query->whereAny(['name', 'barcode'], 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'barcode' => $product->barcode,
                'pieces_per_block' => $product->pieces_per_block,
                'archived' => $product->isArchived(),
            ]);

        return Inertia::render('products/index', [
            'products' => $products,
            'filters' => ['search' => $search, 'archived' => $archived],
        ]);
    }

    /**
     * Add a product to the catalogue.
     */
    public function store(SaveProductRequest $request): RedirectResponse
    {
        Product::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __("Mahsulot qo'shildi.")]);

        return to_route('products.index');
    }

    /**
     * Change a product's name, barcode or pieces per block.
     */
    public function update(SaveProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mahsulot saqlandi.')]);

        return to_route('products.index');
    }

    /**
     * Hide a product from new documents, keeping it in history.
     */
    public function archive(Product $product): RedirectResponse
    {
        $product->archive();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mahsulot arxivlandi.')]);

        return to_route('products.index');
    }

    /**
     * Make an archived product available to new documents again.
     */
    public function unarchive(Product $product): RedirectResponse
    {
        $product->unarchive();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mahsulot arxivdan chiqarildi.')]);

        return to_route('products.index');
    }
}
