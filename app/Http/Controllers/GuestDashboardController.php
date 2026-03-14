<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GuestDashboardController extends Controller
{
    /**
     * 显示游客仪表盘（无需授权）
     */
    public function index()
    {
        return view('guest-dashboard');
    }
}
