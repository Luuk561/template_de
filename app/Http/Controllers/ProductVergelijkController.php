<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductVergelijkController extends Controller
{
    public function index(Request $request)
    {
        $eans = explode(',', $request->get('eans', ''));
        $products = Product::whereIn('ean', $eans)->get();

        return view('vergleichen', compact('products'));
    }
}
