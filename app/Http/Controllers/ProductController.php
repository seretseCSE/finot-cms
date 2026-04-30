<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::active()
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        $products = $query->paginate(12)->withQueryString();

        $categories = Product::active()
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->filter()
            ->values();

        $totalProducts = Product::active()->count();
        $inStockProducts = Product::active()->where('stock_quantity', '>', 0)->count();

        return view('public.shop.index', compact('products', 'categories', 'totalProducts', 'inStockProducts'));
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
