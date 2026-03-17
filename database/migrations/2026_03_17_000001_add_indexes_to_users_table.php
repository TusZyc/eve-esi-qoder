<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 运行迁移
     * 为 users 表添加常用查询字段的索引
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 军团查询索引
            $table->index('corporation_id');
            // 联盟查询索引
            $table->index('alliance_id');
            // Token 过期时间索引（用于批量刷新 Token 的定时任务）
            $table->index('token_expires_at');
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['corporation_id']);
            $table->dropIndex(['alliance_id']);
            $table->dropIndex(['token_expires_at']);
        });
    }
};
