<?php

namespace App\Http\Controllers;

use App\Models\InformationPage;

class InformationPageController extends Controller
{
    public function show($slug)
    {
        $page = InformationPage::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return view('informatie.show', compact('page'));
    }
}
