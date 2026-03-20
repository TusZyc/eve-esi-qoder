<?php

namespace App\Http\Controllers;

class FittingController extends BasePageController
{
    /**
     * 显示装配页面
     */
    public function index()
    {
        return $this->renderPage('fittings.index');
    }
}
