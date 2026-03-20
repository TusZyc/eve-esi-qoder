<?php

namespace App\Http\Controllers;

class ContactController extends BasePageController
{
    /**
     * 显示联系人页面
     */
    public function index()
    {
        return $this->renderPage('contacts.index');
    }
}
