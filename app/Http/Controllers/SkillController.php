<?php

namespace App\Http\Controllers;

class SkillController extends BasePageController
{
    /**
     * 显示技能队列页面
     */
    public function index()
    {
        return $this->renderPage('skills.index');
    }
}
