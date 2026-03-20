<?php

namespace App\Http\Controllers;

class GuestDashboardController extends BasePageController
{
    /**
     * 显示游客仪表盘（无需授权）
     */
    public function index()
    {
        return $this->renderPage('guest-dashboard');
    }
}
