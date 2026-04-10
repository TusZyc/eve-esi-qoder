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
        $shipTree = $this->fittingService->getShipCategoryTree();

        return $this->renderPage('fitting-simulator.index', [
            'shipTree' => $shipTree,
        ]);
    }
}
