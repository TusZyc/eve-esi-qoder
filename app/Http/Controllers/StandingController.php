<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class StandingController extends Controller
{
    /**
     * 显示声望页面
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $isLoggedIn = $user && $user->eve_character_id !== null;
        
        return view('standings.index', [
            'user' => $user,
            'isLoggedIn' => $isLoggedIn,
        ]);
    }
}
