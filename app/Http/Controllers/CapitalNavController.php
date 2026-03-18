<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CapitalNavController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $isLoggedIn = $user && $user->eve_character_id !== null;

        return view('capital-nav.index', [
            'user' => $user,
            'isLoggedIn' => $isLoggedIn,
        ]);
    }
}
