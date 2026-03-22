<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 运行迁移
     */
    public function up(): void
    {
        // 1. 行动记录表
        Schema::create('fleet_operations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fleet_id')->nullable()->comment('ESI 舰队 ID');
            $table->string('operation_name')->comment('行动名称');
            $table->unsignedBigInteger('commander_character_id')->comment('指挥官角色 ID');
            $table->string('commander_name')->comment('指挥官名称');
            $table->integer('snapshot_interval')->default(60)->comment('快照间隔（秒）');
            $table->boolean('auto_snapshot')->default(true)->comment('是否自动抓取');
            $table->timestamp('started_at')->comment('开始时间');
            $table->timestamp('ended_at')->nullable()->comment('结束时间');
            $table->enum('status', ['active', 'ended'])->default('active')->comment('状态');
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamps();
            
            $table->index('fleet_id');
            $table->index('commander_character_id');
            $table->index('status');
        });

        // 2. 考核配置表
        Schema::create('fleet_operation_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operation_id')->nullable()->comment('关联行动（NULL=默认模板）');
            
            // 在队时长配置
            $table->boolean('duration_enabled')->default(true);
            $table->decimal('duration_weight', 5, 2)->default(20.00)->comment('权重百分比');
            $table->integer('duration_min_percent')->default(60)->comment('最低在线时长百分比');
            
            // 加入时间配置
            $table->boolean('join_time_enabled')->default(true);
            $table->decimal('join_time_weight', 5, 2)->default(15.00);
            $table->integer('join_time_grace_minutes')->default(15)->comment('迟到宽限时间');
            
            // 舰船类型配置
            $table->boolean('ship_type_enabled')->default(true);
            $table->decimal('ship_type_weight', 5, 2)->default(20.00);
            $table->json('ship_type_required_ids')->nullable()->comment('期望船型ID列表');
            $table->integer('ship_type_penalty_percent')->default(50)->comment('不匹配扣分百分比');
            
            // 到访星系配置
            $table->boolean('systems_enabled')->default(true);
            $table->decimal('systems_weight', 5, 2)->default(20.00);
            $table->integer('systems_overlap_min_percent')->default(70)->comment('最低重叠百分比');
            
            // 离队距离配置
            $table->boolean('distance_enabled')->default(true);
            $table->decimal('distance_weight', 5, 2)->default(15.00);
            $table->integer('distance_max_jumps')->default(2)->comment('最大容忍跳跃数');
            
            // 在站次数配置
            $table->boolean('in_station_enabled')->default(true);
            $table->decimal('in_station_weight', 5, 2)->default(10.00);
            $table->integer('in_station_max_percent')->default(20)->comment('最大容忍在站百分比');
            
            $table->timestamps();
            
            $table->foreign('operation_id')->references('id')->on('fleet_operations')->onDelete('cascade');
        });

        // 3. 快照记录表
        Schema::create('fleet_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operation_id');
            $table->timestamp('snapshot_time')->comment('抓取时间');
            $table->integer('member_count')->default(0)->comment('成员数量');
            $table->text('fleet_motd')->nullable()->comment('舰队 MOTD');
            $table->integer('commander_system_id')->nullable()->comment('指挥官所在星系');
            $table->boolean('is_manual')->default(false)->comment('是否手动抓取');
            $table->timestamps();
            
            $table->foreign('operation_id')->references('id')->on('fleet_operations')->onDelete('cascade');
            $table->index('operation_id');
            $table->index('snapshot_time');
        });

        // 4. 成员快照详情表
        Schema::create('fleet_member_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('snapshot_id');
            $table->unsignedBigInteger('character_id')->comment('角色 ID');
            $table->string('character_name')->comment('角色名称');
            $table->unsignedBigInteger('corporation_id')->nullable()->comment('公司 ID');
            $table->string('corporation_name')->nullable()->comment('公司名称');
            
            // 位置和舰船信息
            $table->integer('solar_system_id')->comment('星系 ID');
            $table->string('solar_system_name')->nullable()->comment('星系名称');
            $table->integer('ship_type_id')->nullable()->comment('舰船类型 ID');
            $table->string('ship_type_name')->nullable()->comment('舰船名称');
            
            // 舰队结构信息
            $table->string('role')->nullable()->comment('舰队角色');
            $table->unsignedBigInteger('wing_id')->nullable();
            $table->unsignedBigInteger('squad_id')->nullable();
            $table->timestamp('join_time')->nullable()->comment('加入舰队时间');
            
            // 状态标记
            $table->boolean('takes_fleet_warp')->default(true)->comment('是否跟随舰队曲跃');
            $table->boolean('in_station')->default(false)->comment('是否在站内');
            $table->unsignedBigInteger('station_id')->nullable()->comment('空间站 ID');
            
            // 与指挥官的距离
            $table->integer('jumps_from_commander')->nullable()->comment('与指挥官跳跃距离');
            
            $table->timestamps();
            
            $table->foreign('snapshot_id')->references('id')->on('fleet_snapshots')->onDelete('cascade');
            $table->index('snapshot_id');
            $table->index('character_id');
        });

        // 5. 出勤汇总表
        Schema::create('fleet_attendance_summary', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operation_id');
            $table->unsignedBigInteger('character_id');
            $table->string('character_name');
            $table->unsignedBigInteger('corporation_id')->nullable();
            $table->string('corporation_name')->nullable();
            
            // 基础统计
            $table->timestamp('first_seen_at')->nullable()->comment('首次出现时间');
            $table->timestamp('last_seen_at')->nullable()->comment('最后出现时间');
            $table->integer('total_snapshots')->default(0)->comment('被抓取次数');
            $table->integer('attendance_duration_minutes')->default(0)->comment('出勤时长（分钟）');
            $table->integer('join_delay_minutes')->default(0)->comment('加入延迟（分钟）');
            
            // 舰船统计
            $table->json('ships_used')->nullable()->comment('使用过的舰船列表');
            $table->integer('primary_ship_id')->nullable()->comment('主要舰船 ID');
            $table->string('primary_ship_name')->nullable()->comment('主要舰船名称');
            
            // 位置统计
            $table->json('systems_visited')->nullable()->comment('到访星系列表');
            $table->decimal('system_overlap_percent', 5, 2)->default(0)->comment('与指挥官星系重叠百分比');
            $table->decimal('avg_jumps_from_commander', 5, 2)->default(0)->comment('平均跳跃距离');
            $table->decimal('jumps_qualified_percent', 5, 2)->default(0)->comment('跳跃距离合格百分比');
            
            // 在站统计
            $table->integer('in_station_snapshots')->default(0)->comment('在站快照数');
            $table->decimal('in_station_percent', 5, 2)->default(0)->comment('在站比例百分比');
            
            // 各维度得分
            $table->decimal('score_duration', 5, 2)->default(0)->comment('在队时长得分');
            $table->decimal('score_join_time', 5, 2)->default(0)->comment('加入时间得分');
            $table->decimal('score_ship_type', 5, 2)->default(0)->comment('舰船类型得分');
            $table->decimal('score_systems', 5, 2)->default(0)->comment('到访星系得分');
            $table->decimal('score_distance', 5, 2)->default(0)->comment('离队距离得分');
            $table->decimal('score_in_station', 5, 2)->default(0)->comment('在站次数得分');
            
            // 最终评分
            $table->decimal('total_score', 5, 2)->default(0)->comment('总分 (0-100)');
            $table->string('grade', 1)->default('F')->comment('等级: S/A/B/C/D/F');
            $table->boolean('is_full_participant')->default(false)->comment('是否全程参与');
            $table->text('notes')->nullable()->comment('备注');
            
            $table->timestamps();
            
            $table->foreign('operation_id')->references('id')->on('fleet_operations')->onDelete('cascade');
            $table->index('operation_id');
            $table->index('character_id');
            $table->unique(['operation_id', 'character_id']);
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('fleet_attendance_summary');
        Schema::dropIfExists('fleet_member_snapshots');
        Schema::dropIfExists('fleet_snapshots');
        Schema::dropIfExists('fleet_operation_configs');
        Schema::dropIfExists('fleet_operations');
    }
};