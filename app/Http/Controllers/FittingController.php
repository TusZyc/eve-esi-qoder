<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FittingController extends Controller
{
    /**
     * 显示装配页面
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $isLoggedIn = $user && $user->eve_character_id !== null;
        
        return view('fittings.index', [
            'user' => $user,
            'isLoggedIn' => $isLoggedIn,
        ]);
    }
}
