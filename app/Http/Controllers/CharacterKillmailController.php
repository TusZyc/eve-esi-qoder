<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CharacterKillmailController extends Controller
{
    /**
     * 显示角色击毁报告页面
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $isLoggedIn = $user && $user->eve_character_id !== null;
        
        return view('character-killmails.index', [
            'user' => $user,
            'isLoggedIn' => $isLoggedIn,
        ]);
    }
}
