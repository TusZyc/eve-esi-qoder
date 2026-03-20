<?php

namespace App\Http\Controllers;

class ContractController extends BasePageController
{
    /**
     * 显示合同页面
     */
    public function index()
    {
        return $this->renderPage('contracts.index');
    }
}
