<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * 显示提醒页面
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $isLoggedIn = $user && $user->eve_character_id !== null;
        
        return view('notifications.index', [
            'user' => $user,
            'isLoggedIn' => $isLoggedIn,
        ]);
    }
}
