<?php

namespace App\Http\Controllers;

use App\Models\Product;

class ProductController extends Controller
{
    public function index()
    {
        return redirect()->route('tours.index', ['tab' => 'shop'], 301);
    }

    public function show($slug)
    {
        $product = Product::active()
            ->where('slug', $slug)
            ->firstOrFail();

        $relatedProducts = Product::active()
            ->where('id', '!=', $product->id)
            ->where(function ($q) use ($product) {
                $q->where('category', $product->category);
            })
            ->limit(4)
            ->get();

        return view('public.shop.show', compact('product', 'relatedProducts'));
    }
}
