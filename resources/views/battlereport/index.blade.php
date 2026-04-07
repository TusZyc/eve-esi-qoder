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
        z-index: 50;
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
</style>
@endpush

@section('title', '战场报告 - Tus Esi System')

@section('content')
<div class="container mx-auto px-4 py-6 max-w-7xl">
    <!-- 搜索区域 -->
    <div class="bg-white/5 backdrop-blur rounded-xl p-6 mb-6 border border-white/10">
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

        <!-- 舰船统计 -->
        <div class="bg-white/5 backdrop-blur rounded-xl p-4 mb-6 border border-white/10">
            <h4 class="font-medium mb-3">舰船损失统计</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-slate-400 border-b border-slate-700">
                            <th class="text-left py-2 px-3">舰船类型</th>
                            <th class="text-center py-2 px-3 text-red-400">红方损失</th>
                            <th class="text-center py-2 px-3 text-blue-400">蓝方损失</th>
                            <th class="text-right py-2 px-3">红方ISK</th>
                            <th class="text-right py-2 px-3">蓝方ISK</th>
                        </tr>
                    </thead>
                    <tbody id="shipStatsBody"></tbody>
                </table>
            </div>
        </div>

        <!-- KM列表 -->
        <div class="bg-white/5 backdrop-blur rounded-xl p-4 border border-white/10">
            <h4 class="font-medium mb-3">击毁报告列表 (<span id="kmCount">0</span>条)</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-slate-400 border-b border-slate-700">
                            <th class="text-left py-2 px-2">时间</th>
                            <th class="text-left py-2 px-2">受害者</th>
                            <th class="text-left py-2 px-2">舰船</th>
                            <th class="text-left py-2 px-2">星系</th>
                            <th class="text-right py-2 px-2">ISK</th>
                            <th class="text-center py-2 px-2">损失方</th>
                            <th class="text-center py-2 px-2">操作</th>
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

        // 舰船统计
        var shipHtml = report.ship_stats.map(function(s) {
            return '<tr class="border-b border-slate-700/50 hover:bg-slate-800/30">' +
                '<td class="py-2 px-3">' + escapeHtml(s.ship_name) + '</td>' +
                '<td class="text-center py-2 px-3 text-red-400">' + s.red_loss + '</td>' +
                '<td class="text-center py-2 px-3 text-blue-400">' + s.blue_loss + '</td>' +
                '<td class="text-right py-2 px-3 text-slate-400">' + formatIsk(s.red_value) + '</td>' +
                '<td class="text-right py-2 px-3 text-slate-400">' + formatIsk(s.blue_value) + '</td>' +
                '</tr>';
        }).join('');
        document.getElementById('shipStatsBody').innerHTML = shipHtml;

        // KM列表
        document.getElementById('kmCount').textContent = report.km_list.length;
        var kmHtml = report.km_list.map(function(km) {
            var sideClass = km.side === 'red' ? 'text-red-400' : 'text-blue-400';
            var sideText = km.side === 'red' ? '红方' : '蓝方';
            return '<tr class="border-b border-slate-700/50 hover:bg-slate-800/30">' +
                '<td class="py-2 px-2 text-slate-400">' + (km.kill_time || '-') + '</td>' +
                '<td class="py-2 px-2">' + escapeHtml(km.victim_name || '-') + '</td>' +
                '<td class="py-2 px-2">' + escapeHtml(km.ship_name || '-') + '</td>' +
                '<td class="py-2 px-2">' + escapeHtml(km.system_name || '-') + '</td>' +
                '<td class="py-2 px-2 text-right">' + formatIsk(km.total_value) + '</td>' +
                '<td class="py-2 px-2 text-center ' + sideClass + '">' + sideText + '</td>' +
                '<td class="py-2 px-2 text-center">' +
                    '<a href="/killmails?kill_id=' + km.kill_id + '" target="_blank" class="text-blue-400 hover:text-blue-300">详情</a>' +
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
        if (value >= 1000000000) return (value / 1000000000).toFixed(2) + 'B';
        if (value >= 1000000) return (value / 1000000).toFixed(2) + 'M';
        if (value >= 1000) return (value / 1000).toFixed(2) + 'K';
        return value.toFixed(2) + ' ISK';
    }

    // 初始化
    initTimeInputs();
})();
</script>
@endpush