<?php

namespace App\Http\Controllers;

class StandingController extends BasePageController
{
    /**
     * 显示声望页面
     */
    public function index()
    {
        return $this->renderPage('standings.index');
    }
}
