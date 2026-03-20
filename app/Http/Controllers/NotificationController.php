<?php

namespace App\Http\Controllers;

class NotificationController extends BasePageController
{
    /**
     * 显示提醒页面
     */
    public function index()
    {
        return $this->renderPage('notifications.index');
    }
}
