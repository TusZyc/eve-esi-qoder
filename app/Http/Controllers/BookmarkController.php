<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BookmarkController extends Controller
{
    /**
     * 显示保存的地点页面
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $isLoggedIn = $user && $user->eve_character_id !== null;
        
        return view('bookmarks.index', [
            'user' => $user,
            'isLoggedIn' => $isLoggedIn,
        ]);
    }
}
