<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MarketController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        // 只有用户有EVE角色ID时才算已授权
        $isLoggedIn = $user && $user->eve_character_id !== null;
        
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
