<?php

namespace App\Http\Controllers;

class CapitalNavController extends BasePageController
{
    /**
     * 显示旗舰导航页面
     */
    public function index()
    {
        return $this->renderPage('capital-nav.index');
    }
}
