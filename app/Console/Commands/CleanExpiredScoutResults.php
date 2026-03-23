<?php

namespace App\Console\Commands;

use App\Models\ScoutResult;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanExpiredScoutResults extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scout:clean-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '清理已过期的斥候工具扫描结果';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('开始清理过期的扫描结果...');
        
        // 查找所有已过期的记录
        $expiredCount = ScoutResult::where('expires_at', '<', now())
            ->whereNotNull('expires_at')
            ->delete();
        
        $message = "已清理 {$expiredCount} 条过期扫描结果";
        $this->info($message);
        Log::info($message);
        
        // 显示统计信息
        $totalCount = ScoutResult::count();
        $permanentCount = ScoutResult::whereNull('expires_at')->count();
        $this->info("当前共有 {$totalCount} 条记录，其中 {$permanentCount} 条为永久保存");
        
        return 0;
    }
}