<?php

namespace App\Http\Controllers;

class CharacterKillmailController extends BasePageController
{
    /**
     * 显示角色击毁报告页面
     */
    public function index()
    {
        return $this->renderPage('character-killmails.index');
    }
}
