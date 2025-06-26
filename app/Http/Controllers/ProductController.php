<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Component;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Mostra una lista di prodotti
     */
    public function index()
    {
        $products = Product::with('components')->withTrashed()->paginate(15);

        $components = Component::orderBy('code')->get();

        return view('pages.master-data.index-products', compact('products', 'components'));
    }

    /**
     * Genera un nuovo SKU per il prodotto: prefisso + 8 caratteri casuali
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateCode(): \Illuminate\Http\JsonResponse
    {
        $prefix = 'P-';

        do {
            // Genera 8 caratteri alfanumerici casuali in maiuscolo
            $randomPart = Str::upper(Str::random(8));

            // Concatena prefisso + random
            $sku = $prefix . $randomPart;

            // Controlla se esiste già in DB
            $exists = Product::where('sku', $sku)->exists();
        } while ($exists);

        // A questo punto $sku è sicuro di non esistere
        return response()->json(['code' => $sku]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        //
    }
}
