<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EveDataService;

class UpdateEveData extends Command
{
    protected $signature = 'eve:update-data';
    protected $description = '从 ceve-market.org 更新 EVE 数据（物品、星系、空间站名称）';

    public function handle()
    {
        $this->info('开始更新 EVE 数据...');

        $service = app(EveDataService::class);

        $result = $service->updateData();

        if ($result) {
            $meta = $service->getDataMeta();
            $total = $meta['total_entries'] ?? $meta['item_count'] ?? 0;
            $this->info("更新成功！共 {$total} 条记录");
            $this->info('更新时间: ' . ($meta['last_updated'] ?? '未知'));
            return 0;
        }

        $this->error('更新失败，请查看日志');
        return 1;
    }
}
