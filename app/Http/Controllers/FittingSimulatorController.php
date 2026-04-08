<?php

namespace App\Http\Controllers;

use App\Services\FittingDataService;

/**
 * 装配模拟器控制器（公开访问）
 */
class FittingSimulatorController extends BasePageController
{
    private FittingDataService $fittingService;

    public function __construct(FittingDataService $fittingService)
    {
        $this->fittingService = $fittingService;
    }

    /**
     * 显示装配模拟器页面
     */
    public function index()
    {
        // 获取舰船分类结构
        $categories = $this->fittingService->getShipCategories();

        return $this->renderPage('fitting-simulator.index', [
            'categories' => $categories,
        ]);
    }
}