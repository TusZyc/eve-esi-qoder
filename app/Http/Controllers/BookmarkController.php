<?php

namespace App\Http\Controllers;

class BookmarkController extends BasePageController
{
    /**
     * 显示保存的地点页面
     */
    public function index()
    {
        return $this->renderPage('bookmarks.index');
    }
}
