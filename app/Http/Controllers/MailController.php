<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MailController extends Controller
{
    /**
     * 显示邮件页面
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $isLoggedIn = $user && $user->eve_character_id !== null;
        
        return view('mail.index', [
            'user' => $user,
            'isLoggedIn' => $isLoggedIn,
            'activePage' => 'mail',
        ]);
    }
}