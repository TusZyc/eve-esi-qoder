<?php

namespace App\Http\Controllers;

class WalletController extends BasePageController
{
    /**
     * 显示钱包页面
     */
    public function index()
    {
        return $this->renderPage('wallet.index');
    }
}
