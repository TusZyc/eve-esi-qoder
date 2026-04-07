@extends('layouts.app')

@section('page-title', '战场报告')

@push('styles')
<style>
    .faction-box {
        min-height: 200px;
        border-radius: 8px;
        overflow: hidden;
    }
    .faction-box.red {
        background: linear-gradient(135deg, #7f1d1d 0%, #450a0a 100%);
        border: 1px solid rgba(239, 68, 68, 0.3);
    }
    .faction-box.blue {
        background: linear-gradient(135deg, #1e3a5f 0%, #0c1929 100%);
        border: 1px solid rgba(59, 130, 246, 0.3);
    }
    .faction-box.neutral {
        background: linear-gradient(135deg, #374151 0%, #1f2937 100%);
        border: 1px solid rgba(107, 114, 128, 0.3);
    }
    .faction-item {
        padding: 8px 12px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        cursor: pointer;
        transition: background 0.2s;
    }
    .faction-item:hover {
        background: rgba(255,255,255,0.1);
    }
    .faction-item.selected {
        background: rgba(59, 130, 246, 0.3);
    }
    .move-btn {
        padding: 8px 16px;
        border-radius: 6px;
        font-weight: 500;
        transition: all 0.2s;
    }
    .move-btn:hover {
        transform: scale(1.05);
    }
    .stat-card {
        background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 12px;
        padding: 16px;
    }
    .stat-value {
        font-size: 1.5rem;
        font-weight: 700;
    }
    .ac-dropdown {
        position: absolute;
        left: 0;
        right: 0;
        top: 100%;
        z-index: 9999;
        max-height: 240px;
        overflow-y: auto;
        background: rgba(15, 23, 42, 0.98);
        border: 1px solid rgba(255,255,255,0.2);
        border-radius: 0 0 8px 8px;
    }
    .ac-dropdown .ac-item {
        padding: 8px 12px;
        cursor: pointer;
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .ac-dropdown .ac-item:hover {
        background: rgba(59, 130, 246, 0.3);
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .spinner {
        border: 2px solid rgba(255,255,255,0.1);
        border-top-color: #60a5fa;
        border-radius: 50%;
        width: 20px; height: 20px;
        animation: spin 0.8s linear infinite;
        display: inline-block;
    }
    .sec-high { color: #4ade80; }
    .sec-low { color: #facc15; }
    .sec-null { color: #f87171; }
    .item-dropped { color: #4ade80; }
    .item-destroyed { color: #f87171; }
</style>
@endpush

@section('title', '战场报告 - Tus Esi System')

@section('content')
<div class="container mx-auto px-4 py-6 max-w-7xl">
    <!-- 搜索区域 -->
    <div class="bg-white/5 backdrop-blur rounded-xl p-6 mb-6 border border-white/10 overflow-visible">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold">🎯 战场报告</h2>
            <a href="{{ route('killmails.index') }}" class="text-blue-400 hover:text-blue-300 text-sm">
                ← 返回KM查询
            </a>
        </div>
        <p class="text-slate-400 text-sm mb-4">搜索指定星系和时间范围内的KM，生成对战报告</p>
        
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
            <!-- 星系 -->
            <div class="relative">
                <label class="block text-sm text-slate-400 mb-1">星系名称</label>
                <input type="text" id="systemInput" placeholder="输入星系名称..."
                    class="w-full bg-slate-800/50 border border-slate-600 rounded-lg px-3 py-2 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500">
                <input type="hidden" id="systemId">
                <div id="systemDropdown" class="ac-dropdown hidden"></div>
            </div>
            
            <!-- 开始时间 -->
            <div>
                <label class="block text-sm text-slate-400 mb-1">开始时间</label>
                <input type="datetime-local" id="startTime"
                    class="w-full bg-slate-800/50 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-blue-500">
            </div>
            
            <!-- 结束时间 -->
            <div>
                <label class="block text-sm text-slate-400 mb-1">结束时间</label>
                <input type="datetime-local" id="endTime"
                    class="w-full bg-slate-800/50 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-blue-500">
            </div>
            
            <!-- 搜索按钮 -->
            <div class="flex items-end gap-2">
                <label class="flex items-center text-sm text-slate-400 cursor-pointer">
                    <input type="checkbox" id="includeNearby" class="mr-2 rounded">
                    包含邻近星系
                </label>
                <button id="searchBtn" class="flex-1 bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-lg transition-colors">
                    搜索
                </button>
            </div>
        </div>
    </div>

    <!-- 加载状态 -->
    <div id="loadingArea" class="hidden text-center py-12">
        <div class="spinner mx-auto mb-3"></div>
        <p class="text-slate-400">正在搜索KM...</p>
    </div>

    <!-- 阵营选择区域 -->
    <div id="factionSelectArea" class="hidden bg-white/5 backdrop-blur rounded-xl p-6 mb-6 border border-white/10">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold">选择对战双方</h3>
            <span id="totalKillsInfo" class="text-sm text-slate-400"></span>
        </div>
        
        <div class="grid grid-cols-5 gap-4">
            <!-- 红方 -->
            <div class="faction-box red p-3">
                <h4 class="text-red-400 font-medium mb-2 text-center">🔴 红方</h4>
                <div id="redList" class="max-h-64 overflow-y-auto"></div>
            </div>
            
            <!-- 移动按钮左 -->
            <div class="flex flex-col items-center justify-center gap-2">
                <button onclick="moveToFaction('red')" class="move-btn bg-red-600/80 hover:bg-red-500 text-white text-sm">
                    ← 加入红方
                </button>
                <button onclick="moveToFaction('neutral')" class="move-btn bg-slate-600/80 hover:bg-slate-500 text-white text-sm">
                    移回中间 →
                </button>
            </div>
            
            <!-- 所有阵营 -->
            <div class="faction-box neutral p-3">
                <h4 class="text-slate-300 font-medium mb-2 text-center">所有阵营</h4>
                <div id="neutralList" class="max-h-64 overflow-y-auto"></div>
            </div>
            
            <!-- 移动按钮右 -->
            <div class="flex flex-col items-center justify-center gap-2">
                <button onclick="moveToFaction('blue')" class="move-btn bg-blue-600/80 hover:bg-blue-500 text-white text-sm">
                    加入蓝方 →
                </button>
                <button onclick="moveToFaction('neutral')" class="move-btn bg-slate-600/80 hover:bg-slate-500 text-white text-sm">
                    ← 移回中间
                </button>
            </div>
            
            <!-- 蓝方 -->
            <div class="faction-box blue p-3">
                <h4 class="text-blue-400 font-medium mb-2 text-center">🔵 蓝方</h4>
                <div id="blueList" class="max-h-64 overflow-y-auto"></div>
            </div>
        </div>
        
        <div class="mt-4 text-center">
            <button id="generateBtn" class="bg-green-600 hover:bg-green-500 text-white px-6 py-2 rounded-lg transition-colors font-medium">
                生成战场报告
            </button>
        </div>
    </div>

    <!-- 战场报告区域 -->
    <div id="reportArea" class="hidden">
        <!-- 总览统计 -->
        <div class="grid grid-cols-2 gap-6 mb-6">
            <!-- 红方统计 -->
            <div class="stat-card border-red-500/30">
                <h3 class="text-red-400 font-bold text-lg mb-4 text-center">🔴 红方</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div class="text-center">
                        <div class="text-slate-400 text-sm">损失ISK</div>
                        <div id="redLossIsk" class="stat-value text-red-400">-</div>
                    </div>
                    <div class="text-center">
                        <div class="text-slate-400 text-sm">损失舰船</div>
                        <div id="redLossShips" class="stat-value text-red-400">-</div>
                    </div>
                    <div class="text-center">
                        <div class="text-slate-400 text-sm">参与人数</div>
                        <div id="redParticipants" class="stat-value text-white">-</div>
                    </div>
                    <div class="text-center">
                        <div class="text-slate-400 text-sm">击杀数</div>
                        <div id="redKills" class="stat-value text-green-400">-</div>
                    </div>
                </div>
            </div>
            
            <!-- 蓝方统计 -->
            <div class="stat-card border-blue-500/30">
                <h3 class="text-blue-400 font-bold text-lg mb-4 text-center">🔵 蓝方</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div class="text-center">
                        <div class="text-slate-400 text-sm">损失ISK</div>
                        <div id="blueLossIsk" class="stat-value text-blue-400">-</div>
                    </div>
                    <div class="text-center">
                        <div class="text-slate-400 text-sm">损失舰船</div>
                        <div id="blueLossShips" class="stat-value text-blue-400">-</div>
                    </div>
                    <div class="text-center">
                        <div class="text-slate-400 text-sm">参与人数</div>
                        <div id="blueParticipants" class="stat-value text-white">-</div>
                    </div>
                    <div class="text-center">
                        <div class="text-slate-400 text-sm">击杀数</div>
                        <div id="blueKills" class="stat-value text-green-400">-</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 参与阵营 -->
        <div class="grid grid-cols-2 gap-6 mb-6">
            <div class="bg-white/5 backdrop-blur rounded-xl p-4 border border-white/10">
                <h4 class="text-red-400 font-medium mb-3">红方参与阵营</h4>
                <div id="redFactionsList" class="space-y-2"></div>
            </div>
            <div class="bg-white/5 backdrop-blur rounded-xl p-4 border border-white/10">
                <h4 class="text-blue-400 font-medium mb-3">蓝方参与阵营</h4>
                <div id="blueFactionsList" class="space-y-2"></div>
            </div>
        </div>

        <!-- 舰船统计 - 对称布局 -->
        <div class="bg-white/5 backdrop-blur rounded-xl p-4 mb-6 border border-white/10">
            <h4 class="font-medium mb-3">舰船损失统计</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-slate-400 border-b border-slate-700">
                            <th class="text-right py-2 px-3 text-red-400 w-[25%]">红方损失 / ISK</th>
                            <th class="text-center py-2 px-3 w-[50%]">舰船类型</th>
                            <th class="text-left py-2 px-3 text-blue-400 w-[25%]">蓝方损失 / ISK</th>
                        </tr>
                    </thead>
                    <tbody id="shipStatsBody"></tbody>
                </table>
            </div>
        </div>

        <!-- KM列表 - 对称布局 -->
        <div class="bg-white/5 backdrop-blur rounded-xl p-4 border border-white/10">
            <h4 class="font-medium mb-3">击毁报告列表 (<span id="kmCount">0</span>条)</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-slate-400 border-b border-slate-700">
                            <th class="text-right py-2 px-3 text-red-400 w-[20%]">红方损失</th>
                            <th class="text-center py-2 px-3 w-[40%]">舰船 / 星系 / 时间</th>
                            <th class="text-left py-2 px-3 text-blue-400 w-[20%]">蓝方损失</th>
                            <th class="text-center py-2 px-3 w-[20%]">操作</th>
                        </tr>
                    </thead>
                    <tbody id="kmListBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 提示信息 -->
    <div id="emptyMessage" class="hidden text-center py-12 text-slate-400">
        <p>请在上方输入搜索条件，开始生成战场报告</p>
    </div>

    <!-- KM详情弹窗 -->
    <div id="detailModal" class="fixed inset-0 z-[9999] hidden flex items-start justify-center overflow-y-auto">
        <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" onclick="closeDetail()"></div>
        <div class="relative mx-auto my-4 md:my-8 w-full max-w-5xl max-h-[90vh] bg-slate-900/95 backdrop-blur rounded-xl border border-white/20 overflow-hidden flex flex-col">
            <!-- 模态框头部 -->
            <div class="flex justify-between items-center px-6 py-3 border-b border-white/10 shrink-0">
                <h3 id="detailTitle" class="font-bold">KM 详情</h3>
                <button onclick="closeDetail()" class="text-white/50 hover:text-white text-xl">&times;</button>
            </div>
            <!-- 模态框内容 -->
            <div id="detailContent" class="flex-1 overflow-y-auto p-6">
                <div class="text-center py-12"><div class="spinner mx-auto"></div></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    'use strict';

    var debounceTimers = {};
    var selectedSystemId = null;
    var allFactions = [];
    var factionAssignment = {}; // factionId -> 'red'/'blue'/'neutral'

    // 初始化时间（默认最近24小时）
    function initTimeInputs() {
        var now = new Date();
        var yesterday = new Date(now.getTime() - 24 * 60 * 60 * 1000);
        
        document.getElementById('endTime').value = formatDateTimeLocal(now);
        document.getElementById('startTime').value = formatDateTimeLocal(yesterday);
    }

    function formatDateTimeLocal(date) {
        return date.toISOString().slice(0, 16);
    }

    // 星系自动补全
    document.getElementById('systemInput').addEventListener('input', function() {
        var q = this.value.trim();
        selectedSystemId = null;
        
        if (q.length < 2) {
            document.getElementById('systemDropdown').classList.add('hidden');
            return;
        }

        clearTimeout(debounceTimers['system']);
        debounceTimers['system'] = setTimeout(function() {
            fetch('/api/killmails/autocomplete?q=' + encodeURIComponent(q) + '&type=system')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success || !data.data.length) {
                        document.getElementById('systemDropdown').classList.add('hidden');
                        return;
                    }

                    var html = data.data.map(function(item) {
                        return '<div class="ac-item" data-id="' + item.id + '" data-name="' + escapeHtml(item.name) + '">' + escapeHtml(item.name) + '</div>';
                    }).join('');

                    document.getElementById('systemDropdown').innerHTML = html;
                    document.getElementById('systemDropdown').classList.remove('hidden');

                    document.querySelectorAll('#systemDropdown .ac-item').forEach(function(el) {
                        el.addEventListener('click', function() {
                            document.getElementById('systemInput').value = el.dataset.name;
                            selectedSystemId = parseInt(el.dataset.id);
                            document.getElementById('systemDropdown').classList.add('hidden');
                        });
                    });
                });
        }, 300);
    });

    // 点击外部关闭下拉
    document.addEventListener('click', function(e) {
        if (!document.getElementById('systemInput').contains(e.target)) {
            document.getElementById('systemDropdown').classList.add('hidden');
        }
    });

    // 搜索按钮
    document.getElementById('searchBtn').addEventListener('click', doSearch);

    function doSearch() {
        if (!selectedSystemId) {
            alert('请选择一个星系');
            return;
        }

        var startTime = document.getElementById('startTime').value;
        var endTime = document.getElementById('endTime').value;

        if (!startTime || !endTime) {
            alert('请选择时间范围');
            return;
        }

        var includeNearby = document.getElementById('includeNearby').checked;

        // 显示加载
        document.getElementById('loadingArea').classList.remove('hidden');
        document.getElementById('factionSelectArea').classList.add('hidden');
        document.getElementById('reportArea').classList.add('hidden');
        document.getElementById('emptyMessage').classList.add('hidden');

        fetch('/api/battlereport/search', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                system_id: selectedSystemId,
                start_time: startTime,
                end_time: endTime,
                include_nearby: includeNearby
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById('loadingArea').classList.add('hidden');

            if (!data.success) {
                alert(data.message || '搜索失败');
                return;
            }

            if (data.data.total_kills === 0) {
                document.getElementById('emptyMessage').classList.remove('hidden');
                document.getElementById('emptyMessage').innerHTML = '<p>该时间段内未检测到战斗记录</p>';
                return;
            }

            allFactions = data.data.factions;
            factionAssignment = {};

            // 初始化阵营分配
            allFactions.forEach(function(f) {
                factionAssignment[f.id] = 'neutral';
            });

            document.getElementById('totalKillsInfo').textContent = '共 ' + data.data.total_kills + ' 条KM';

            renderFactionLists();
            document.getElementById('factionSelectArea').classList.remove('hidden');
        })
        .catch(function(err) {
            document.getElementById('loadingArea').classList.add('hidden');
            alert('请求失败: ' + err.message);
        });
    }

    // 渲染阵营列表
    function renderFactionLists() {
        var redHtml = '';
        var blueHtml = '';
        var neutralHtml = '';

        allFactions.forEach(function(f) {
            var itemHtml = '<div class="faction-item" data-faction-id="' + f.id + '" onclick="selectFaction(' + f.id + ')">' +
                '<div class="font-medium text-sm">' + escapeHtml(f.name) + '</div>' +
                '<div class="text-xs text-slate-400">损失: ' + formatIsk(f.loss_isk) + ' | 舰船: ' + f.loss_ships + ' | 参与: ' + Object.keys(f.participants).length + '人</div>' +
                '</div>';

            var side = factionAssignment[f.id];
            if (side === 'red') redHtml += itemHtml;
            else if (side === 'blue') blueHtml += itemHtml;
            else neutralHtml += itemHtml;
        });

        document.getElementById('redList').innerHTML = redHtml || '<div class="text-slate-500 text-sm text-center py-4">点击中间阵营加入红方</div>';
        document.getElementById('blueList').innerHTML = blueHtml || '<div class="text-slate-500 text-sm text-center py-4">点击中间阵营加入蓝方</div>';
        document.getElementById('neutralList').innerHTML = neutralHtml || '<div class="text-slate-500 text-sm text-center py-4">无阵营</div>';
    }

    // 选择阵营
    window.selectFaction = function(factionId) {
        var current = factionAssignment[factionId];
        // 切换选中状态用于移动按钮
        document.querySelectorAll('.faction-item').forEach(function(el) {
            el.classList.remove('selected');
        });
        document.querySelector('.faction-item[data-faction-id="' + factionId + '"]')?.classList.add('selected');
    };

    // 移动阵营
    window.moveToFaction = function(targetSide) {
        var selectedEl = document.querySelector('.faction-item.selected');
        if (!selectedEl && targetSide !== 'neutral') {
            alert('请先点击选择一个阵营');
            return;
        }

        if (selectedEl) {
            var factionId = parseInt(selectedEl.dataset.factionId);
            factionAssignment[factionId] = targetSide;
        }

        renderFactionLists();
    };

    // 生成报告
    document.getElementById('generateBtn').addEventListener('click', generateReport);

    function generateReport() {
        // 验证星系是否已选择
        if (!selectedSystemId) {
            alert('请先选择一个星系并点击"搜索"按钮');
            return;
        }

        var redFactions = [];
        var blueFactions = [];

        allFactions.forEach(function(f) {
            if (factionAssignment[f.id] === 'red') redFactions.push(f.id);
            if (factionAssignment[f.id] === 'blue') blueFactions.push(f.id);
        });

        if (redFactions.length === 0 || blueFactions.length === 0) {
            alert('请为红方和蓝方各选择至少一个阵营');
            return;
        }

        var startTime = document.getElementById('startTime').value;
        var endTime = document.getElementById('endTime').value;
        var includeNearby = document.getElementById('includeNearby').checked;

        document.getElementById('loadingArea').classList.remove('hidden');

        fetch('/api/battlereport/generate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                system_id: selectedSystemId,
                start_time: startTime,
                end_time: endTime,
                include_nearby: includeNearby,
                red_factions: redFactions,
                blue_factions: blueFactions
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById('loadingArea').classList.add('hidden');

            if (!data.success) {
                alert(data.message || '生成失败');
                return;
            }

            renderReport(data.data);
        })
        .catch(function(err) {
            document.getElementById('loadingArea').classList.add('hidden');
            alert('请求失败: ' + err.message);
        });
    }

    // 渲染报告
    function renderReport(report) {
        document.getElementById('factionSelectArea').classList.add('hidden');
        document.getElementById('reportArea').classList.remove('hidden');

        // 统计数据
        document.getElementById('redLossIsk').textContent = formatIsk(report.stats.red.loss_isk);
        document.getElementById('redLossShips').textContent = report.stats.red.loss_ships;
        document.getElementById('redParticipants').textContent = report.stats.red.participant_count;
        document.getElementById('redKills').textContent = report.stats.red.kills || '-';

        document.getElementById('blueLossIsk').textContent = formatIsk(report.stats.blue.loss_isk);
        document.getElementById('blueLossShips').textContent = report.stats.blue.loss_ships;
        document.getElementById('blueParticipants').textContent = report.stats.blue.participant_count;
        document.getElementById('blueKills').textContent = report.stats.blue.kills || '-';

        // 参与阵营
        var redFactionsHtml = report.red_factions.map(function(f) {
            return '<div class="flex justify-between text-sm py-1 border-b border-slate-700/50">' +
                '<span>' + escapeHtml(f.name) + '</span>' +
                '<span class="text-slate-400">' + f.participant_count + '人 | ' + formatIsk(f.loss_isk) + '</span>' +
                '</div>';
        }).join('');
        document.getElementById('redFactionsList').innerHTML = redFactionsHtml || '<div class="text-slate-500 text-sm">无</div>';

        var blueFactionsHtml = report.blue_factions.map(function(f) {
            return '<div class="flex justify-between text-sm py-1 border-b border-slate-700/50">' +
                '<span>' + escapeHtml(f.name) + '</span>' +
                '<span class="text-slate-400">' + f.participant_count + '人 | ' + formatIsk(f.loss_isk) + '</span>' +
                '</div>';
        }).join('');
        document.getElementById('blueFactionsList').innerHTML = blueFactionsHtml || '<div class="text-slate-500 text-sm">无</div>';

        // 舰船统计 - 对称布局
        var shipHtml = report.ship_stats.map(function(s) {
            var redLossText = s.red_loss > 0 ? '<span class="text-red-400 font-medium">' + s.red_loss + '</span>' : '<span class="text-white/30">-</span>';
            var redIskText = s.red_value > 0 ? '<span class="text-slate-400 text-xs">' + formatIsk(s.red_value) + '</span>' : '';
            var blueLossText = s.blue_loss > 0 ? '<span class="text-blue-400 font-medium">' + s.blue_loss + '</span>' : '<span class="text-white/30">-</span>';
            var blueIskText = s.blue_value > 0 ? '<span class="text-slate-400 text-xs">' + formatIsk(s.blue_value) + '</span>' : '';

            return '<tr class="border-b border-slate-700/50 hover:bg-slate-800/30">' +
                '<td class="py-2 px-3 text-right">' +
                    '<div class="flex flex-col items-end gap-1">' +
                        '<div>' + redLossText + '</div>' +
                        '<div>' + redIskText + '</div>' +
                    '</div>' +
                '</td>' +
                '<td class="py-2 px-3 text-center font-medium">' + escapeHtml(s.ship_name) + '</td>' +
                '<td class="py-2 px-3 text-left">' +
                    '<div class="flex flex-col items-start gap-1">' +
                        '<div>' + blueLossText + '</div>' +
                        '<div>' + blueIskText + '</div>' +
                    '</div>' +
                '</td>' +
                '</tr>';
        }).join('');
        document.getElementById('shipStatsBody').innerHTML = shipHtml;

        // KM列表 - 对称布局
        document.getElementById('kmCount').textContent = report.km_list.length;
        var kmHtml = report.km_list.map(function(km) {
            var isRed = km.side === 'red';
            
            // 红方损失列：只在红方损失时显示受害者/ISK
            var redCellHtml = isRed ?
                '<div class="flex flex-col items-end gap-1">' +
                    '<div class="text-red-300 font-medium">' + escapeHtml(km.victim_name || '-') + '</div>' +
                    '<div class="text-xs text-slate-400">' + formatIsk(km.total_value) + '</div>' +
                '</div>' :
                '<span class="text-white/20">-</span>';

            // 蓝方损失列：只在蓝方损失时显示受害者/ISK
            var blueCellHtml = !isRed ?
                '<div class="flex flex-col items-start gap-1">' +
                    '<div class="text-blue-300 font-medium">' + escapeHtml(km.victim_name || '-') + '</div>' +
                    '<div class="text-xs text-slate-400">' + formatIsk(km.total_value) + '</div>' +
                '</div>' :
                '<span class="text-white/20">-</span>';

            return '<tr class="border-b border-slate-700/50 hover:bg-slate-800/30' + (isRed ? ' bg-red-500/5' : ' bg-blue-500/5') + '">' +
                '<td class="py-3 px-3 text-right">' + redCellHtml + '</td>' +
                '<td class="py-3 px-3 text-center">' +
                    '<div class="flex flex-col items-center gap-1">' +
                        '<div class="font-medium">' + escapeHtml(km.ship_name || '-') + '</div>' +
                        '<div class="text-xs text-slate-400">' + escapeHtml(km.system_name || '-') + '</div>' +
                        '<div class="text-xs text-white/50">' + (km.kill_time || '-') + '</div>' +
                    '</div>' +
                '</td>' +
                '<td class="py-3 px-3 text-left">' + blueCellHtml + '</td>' +
                '<td class="py-3 px-3 text-center">' +
                    '<button onclick="openKmDetail(' + km.kill_id + ', ' + (km.esi_hash ? "'" + km.esi_hash + "'" : 'null') + ')" class="text-blue-400 hover:text-blue-300 text-sm px-2 py-1 rounded hover:bg-blue-500/10 transition-all">详情</button>' +
                '</td>' +
                '</tr>';
        }).join('');
        document.getElementById('kmListBody').innerHTML = kmHtml;
    }

    // 辅助函数
    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function formatIsk(value) {
        if (!value || value === 0) return '0 ISK';
        // 千位分隔符格式显示完整金额
        return Math.round(value).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',') + ' ISK';
    }

    function formatNum(n) {
        if (n === null || n === undefined) return '0';
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // ========== KM 详情弹窗 ==========

    var BETA_KB_URL = 'https://beta.ceve-market.org';

    window.openKmDetail = function(killId, hash) {
        var modal = document.getElementById('detailModal');
        var content = document.getElementById('detailContent');
        document.getElementById('detailTitle').textContent = 'KM #' + killId + ' 详情';
        content.innerHTML = '<div class="text-center py-12"><div class="spinner mx-auto mb-3"></div><p class="text-blue-200 text-sm">加载中...</p></div>';
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';

        var fetchDetail = function(h) {
            var url = '/api/killmails/kill/' + killId;
            if (h) url += '?hash=' + h;
            fetch(url)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        renderKillDetail(data.data);
                    } else {
                        content.innerHTML = '<div class="text-center py-12 text-red-400">' + escapeHtml(data.message || '加载失败') + '</div>';
                    }
                })
                .catch(function(err) {
                    content.innerHTML = '<div class="text-center py-12 text-red-400">网络错误: ' + escapeHtml(err.message) + '</div>';
                });
        };

        if (hash) {
            fetchDetail(hash);
        } else {
            fetch(BETA_KB_URL + '/app/kill/' + killId + '/info', { mode: 'cors', credentials: 'omit' })
                .then(function(r) { return r.text(); })
                .then(function(text) {
                    var m = text.match(/[a-f0-9]{40}/);
                    fetchDetail(m ? m[0] : null);
                })
                .catch(function() { fetchDetail(null); });
        }
    };

    window.closeDetail = function() {
        document.getElementById('detailModal').classList.add('hidden');
        document.body.style.overflow = '';
    };

    function renderKillDetail(d) {
        var content = document.getElementById('detailContent');
        var v = d.victim || {};
        var secClass = getSecClass(d.system_sec);
        var secText = d.system_sec !== null && d.system_sec !== undefined ? '(' + d.system_sec.toFixed(1) + ')' : '';

        var html = '';

        // ESI 验证标记
        if (d.esi_verified) {
            html += '<div class="mb-3 text-xs"><span class="bg-green-600/30 text-green-300 px-2 py-0.5 rounded">ESI 官方数据</span></div>';
        } else {
            html += '<div class="mb-3 text-xs"><span class="bg-yellow-600/30 text-yellow-300 px-2 py-0.5 rounded">KB 降级数据</span></div>';
        }

        // 受害者/击毁信息
        html += '<div class="bg-white/5 rounded-lg p-4 mb-4 border border-red-500/20">';
        html += '<div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">';
        html += '<span class="text-lg font-bold text-red-300">' + escapeHtml(v.character_name || '未知') + '</span>';
        var victimOrg = [];
        if (v.corporation_name) victimOrg.push(v.corporation_name);
        if (v.alliance_name) victimOrg.push(v.alliance_name);
        if (victimOrg.length) html += '<span class="text-white/50 text-sm">[' + escapeHtml(victimOrg.join(' / ')) + ']</span>';
        html += '<span class="text-white/70">舰船: <span class="text-white font-medium">' + escapeHtml(v.ship_name || '未知') + '</span></span>';
        html += '<span class="text-white/70">伤害: <span class="text-orange-300">' + formatNum(v.damage_taken) + '</span></span>';
        html += '</div>';
        html += '<div class="flex flex-wrap gap-x-4 gap-y-1 mt-1 text-sm text-white/50">';
        html += '<span>星系: ' + escapeHtml(d.solar_system_name || '-') + ' <span class="' + secClass + '">' + secText + '</span>';
        if (d.region_name) html += ' / ' + escapeHtml(d.region_name);
        html += '</span>';
        html += '<span>时间: ' + escapeHtml(d.kill_time || '-') + ' (北京时间)</span>';
        html += '<span>参与: ' + d.attacker_count + '人</span>';
        html += '<a href="https://kb.ceve-market.org/kill/' + d.kill_id + '/" target="_blank" class="text-blue-400 hover:underline">KB 链接</a>';
        html += '</div></div>';

        // 两列布局: 攻击者 | 装配/物品
        html += '<div class="grid grid-cols-1 lg:grid-cols-10 gap-4">';
        html += '<div class="lg:col-span-3 bg-white/5 rounded-lg border border-white/10 overflow-hidden">';
        html += '<div class="px-4 py-2 border-b border-white/10 font-bold text-sm">攻击者 (' + d.attacker_count + ')</div>';
        html += '<div class="divide-y divide-white/5 max-h-[600px] overflow-y-auto">';

        var attackers = d.attackers || [];
        attackers.forEach(function(atk, idx) {
            var badge = '';
            if (atk.final_blow) {
                badge = '<span class="text-yellow-400 text-xs font-bold ml-1">&#11088; 最后一击</span>';
            } else if (idx === 1 && (!attackers[0] || !attackers[0].final_blow || attackers[0].character_id !== atk.character_id)) {
                badge = '<span class="text-orange-400 text-xs font-bold ml-1">&#127942; 最高伤害</span>';
            } else if (idx === 0 && !atk.final_blow) {
                badge = '<span class="text-orange-400 text-xs font-bold ml-1">&#127942; 最高伤害</span>';
            }

            var atkOrg = '';
            if (atk.corporation_name) atkOrg = atk.corporation_name;
            if (atk.alliance_name) atkOrg += (atkOrg ? ' - ' : '') + atk.alliance_name;

            html += '<div class="px-4 py-2.5' + (atk.final_blow ? ' bg-yellow-500/5' : '') + '">';
            html += '<div class="font-medium text-sm">' + escapeHtml(atk.character_name || '未知') + badge + '</div>';
            if (atkOrg) html += '<div class="text-white/40 text-xs">' + escapeHtml(atkOrg) + '</div>';
            html += '<div class="text-white/50 text-xs">' + escapeHtml(atk.ship_name || '?') + ' - ' + escapeHtml(atk.weapon_name || '?') + '</div>';
            html += '<div class="text-orange-300 text-xs">伤害: ' + formatNum(atk.damage_done) + '</div>';
            html += '</div>';
        });

        if (!attackers.length) {
            html += '<div class="px-4 py-4 text-white/30 text-sm text-center">无攻击者数据</div>';
        }

        html += '</div></div>';

        // 装配/物品
        html += '<div class="lg:col-span-7 bg-white/5 rounded-lg border border-white/10 overflow-hidden">';
        html += '<div class="px-4 py-2 border-b border-white/10 font-bold text-sm">装配 / 物品</div>';
        html += '<div class="max-h-[600px] overflow-y-auto">';

        var slots = d.items_by_slot || {};
        var slotOrder = ['高槽', '中槽', '低槽', '改装件', '子系统', '无人机舱', '弹药舱', '货柜舱', '其他'];
        var hasItems = false;

        slotOrder.forEach(function(slotName) {
            var slotItems = slots[slotName];
            if (!slotItems || !slotItems.length) return;
            hasItems = true;
            html += '<div class="px-4 py-1.5 bg-white/5 border-b border-white/10 text-xs font-bold text-blue-300">' + escapeHtml(slotName) + '</div>';
            slotItems.forEach(function(item) {
                html += renderItemRow(item, true);
            });
        });

        if (!hasItems && v.items && v.items.length) {
            v.items.forEach(function(item) {
                html += renderItemRow(item, true);
            });
        } else if (!hasItems) {
            html += '<div class="px-4 py-4 text-white/30 text-sm text-center">无物品数据</div>';
        }

        html += '</div></div>';
        html += '</div>';

        content.innerHTML = html;
    }

    function getSecClass(sec) {
        if (sec === null || sec === undefined) return '';
        if (sec >= 0.5) return 'sec-high';
        if (sec > 0.0) return 'sec-low';
        return 'sec-null';
    }

    function renderItemRow(item, showPrice) {
        var colorClass = item.status === 'dropped' ? 'item-dropped' : 'item-destroyed';
        var qtyText = item.quantity > 1 ? '<span class="text-white/50">x' + item.quantity + '</span> ' : '';
        var priceText = '';
        if (showPrice && item.total_price && item.total_price > 0) {
            priceText = formatIsk(item.total_price);
        }

        return '<div class="px-4 py-1 text-xs flex justify-between items-center border-b border-white/5">' +
               '<span class="' + colorClass + '">' + qtyText + escapeHtml(item.item_name) + '</span>' +
               '<span class="text-yellow-300/80 whitespace-nowrap ml-2">' + (priceText || '-') + '</span>' +
               '</div>';
    }

    // ESC 关闭弹窗
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDetail();
        }
    });

    // 初始化
    initTimeInputs();
})();
</script>
@endpush