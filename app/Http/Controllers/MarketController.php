<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MarketController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $isLoggedIn = $user !== null;
        $popularRegions = config('market.popular_regions', []);
        $defaultRegion = config('market.default_region', 10000002);
        return view('market.index', [
            'user' => $user,
            'isLoggedIn' => $isLoggedIn,
            'popularRegions' => $popularRegions,
            'defaultRegion' => $defaultRegion,
        ]);
    }
}
