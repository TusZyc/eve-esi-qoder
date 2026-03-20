@extends('layouts.admin')

@section('title', 'API 统计')
@section('page-title', 'API 统计')

@push('styles')
<style>
    .hourly-row {
        display: flex;
        align-items: center;
        padding: 4px 0;
        gap: 12px;
    }
    
    .hourly-row:hover {
        background: rgba(59, 130, 246, 0.05);
    }
    
    .hour-label {
        font-size: 12px;
        color: #64748b;
        width: 40px;
        text-align: right;
        font-family: 'Consolas', 'Monaco', monospace;
    }
    
    .hour-bar-container {
        flex: 1;
        height: 20px;
        background: rgba(51, 65, 85, 0.3);
        border-radius: 4px;
        overflow: hidden;
        position: relative;
    }
    
    .hour-bar-fill {
        height: 100%;
        background: linear-gradient(to right, #3b82f6, #06b6d4);
        border-radius: 4px;
        transition: width 0.5s ease;
        min-width: 2px;
    }
    
    .hour-bar-fill.zero {
        background: rgba(100, 116, 139, 0.3);
        min-width: 2px;
        width: 2px !important;
    }
    
    .hour-count {
        font-size: 12px;
        color: #94a3b8;
        width: 50px;
        text-align: right;
        font-family: 'Consolas', 'Monaco', monospace;
    }
    
    .hour-count.active {
        color: #22d3ee;
        font-weight: 600;
    }
    
    .hourly-chart-container {
        max-height: 500px;
        overflow-y: auto;
    }
    
    .module-row {
        display: grid;
        grid-template-columns: 1fr 100px 80px 80px 80px 1fr;
        gap: 16px;
        align-items: center;
        padding: 16px;
        border-bottom: 1px solid rgba(51, 65, 85, 0.3);
        transition: background 0.15s;
    }
    
    .module-row:hover {
        background: rgba(59, 130, 246, 0.05);
    }
    
    .module-row:last-child {
        border-bottom: none;
    }
    
    .progress-bar {
        height: 8px;
        background: rgba(51, 65, 85, 0.5);
        border-radius: 4px;
        overflow: hidden;
    }
    
    .progress-fill {
        height: 100%;
        background: linear-gradient(to right, #3b82f6, #06b6d4);
        border-radius: 4px;
        transition: width 0.5s ease;
    }
    
    .count-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        border-radius: 9999px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .count-success { background: rgba(34, 197, 94, 0.15); color: #4ade80; }
    .count-warning { background: rgba(234, 179, 8, 0.15); color: #facc15; }
    .count-error { background: rgba(239, 68, 68, 0.15); color: #f87171; }
</style>
@endpush

@section('content')
<div class="space-y-6">
    <!-- 概览统计 -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="stat-card rounded-2xl p-5 group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-sm font-medium">今日调用总数</p>
                    <p class="text-3xl font-bold mt-2 bg-gradient-to-r from-blue-400 to-cyan-400 bg-clip-text text-transparent" id="total-calls">
                        <span class="skeleton inline-block w-20 h-8"></span>
                    </p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500/20 to-cyan-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <span class="text-3xl">📊</span>
                </div>
            </div>
        </div>
        
        <div class="stat-card rounded-2xl p-5 group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-sm font-medium">错误 / 警告</p>
                    <p class="text-3xl font-bold mt-2" id="error-warning">
                        <span class="skeleton inline-block w-24 h-8"></span>
                    </p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-red-500/20 to-orange-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <span class="text-3xl">⚠️</span>
                </div>
            </div>
        </div>
        
        <div class="stat-card rounded-2xl p-5 group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-sm font-medium">今日警告数</p>
                    <p class="text-3xl font-bold mt-2 bg-gradient-to-r from-yellow-400 to-amber-400 bg-clip-text text-transparent" id="warning-count">
                        <span class="skeleton inline-block w-16 h-8"></span>
                    </p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-yellow-500/20 to-amber-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <span class="text-3xl">⚡</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 按小时调用分布 - 水平条形图 -->
    <div class="stat-card rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold flex items-center">
                <span class="w-8 h-8 rounded-lg bg-cyan-500/20 flex items-center justify-center mr-3">
                    <span class="text-lg">📈</span>
                </span>
                <span class="bg-gradient-to-r from-white to-slate-400 bg-clip-text text-transparent">今日调用时间分布</span>
            </h3>
            <span class="text-sm text-slate-400" id="chart-max-label">最大: <span class="text-cyan-400 font-semibold">-</span> 次/小时</span>
        </div>
        <div class="hourly-chart-container bg-slate-900/30 rounded-xl p-4" id="hourly-chart">
            <!-- 24行水平条形图，JS填充 -->
            @for ($i = 0; $i < 24; $i++)
            <div class="hourly-row" data-hour="{{ $i }}">
                <span class="hour-label">{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}:00</span>
                <div class="hour-bar-container">
                    <div class="hour-bar-fill zero" style="width: 2px;"></div>
                </div>
                <span class="hour-count">0</span>
            </div>
            @endfor
        </div>
    </div>

    <!-- 功能模块调用排行 -->
    <div class="stat-card rounded-2xl p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center">
            <span class="w-8 h-8 rounded-lg bg-purple-500/20 flex items-center justify-center mr-3">
                <span class="text-lg">🏆</span>
            </span>
            <span class="bg-gradient-to-r from-white to-slate-400 bg-clip-text text-transparent">功能模块调用排行</span>
        </h3>
        
        <!-- 表头 -->
        <div class="module-row text-sm text-slate-400 font-medium" style="background: rgba(51, 65, 85, 0.2); border-radius: 8px;">
            <span>模块名称</span>
            <span class="text-center">调用次数</span>
            <span class="text-center">成功</span>
            <span class="text-center">警告</span>
            <span class="text-center">错误</span>
            <span>占比</span>
        </div>
        
        <div id="module-list">
            <!-- skeleton loading -->
            @for ($i = 0; $i < 5; $i++)
            <div class="module-row">
                <span><span class="skeleton inline-block w-32 h-4"></span></span>
                <span class="text-center"><span class="skeleton inline-block w-16 h-4"></span></span>
                <span class="text-center"><span class="skeleton inline-block w-12 h-4"></span></span>
                <span class="text-center"><span class="skeleton inline-block w-12 h-4"></span></span>
                <span class="text-center"><span class="skeleton inline-block w-12 h-4"></span></span>
                <span><span class="skeleton inline-block w-full h-2"></span></span>
            </div>
            @endfor
        </div>
    </div>

    <!-- 最近错误/警告列表 -->
    <div class="stat-card rounded-2xl p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center">
            <span class="w-8 h-8 rounded-lg bg-red-500/20 flex items-center justify-center mr-3">
                <span class="text-lg">🔴</span>
            </span>
            <span class="bg-gradient-to-r from-white to-slate-400 bg-clip-text text-transparent">最近错误/警告</span>
        </h3>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-slate-400 text-sm border-b border-slate-700">
                        <th class="pb-3 w-40">时间</th>
                        <th class="pb-3 w-24">级别</th>
                        <th class="pb-3 w-28">模块</th>
                        <th class="pb-3">消息摘要</th>
                    </tr>
                </thead>
                <tbody id="error-list">
                    <!-- 骨架屏 -->
                    @for ($i = 0; $i < 5; $i++)
                    <tr class="border-b border-slate-700/50">
                        <td class="py-3"><span class="skeleton inline-block w-32 h-4"></span></td>
                        <td class="py-3"><span class="skeleton inline-block w-16 h-4"></span></td>
                        <td class="py-3"><span class="skeleton inline-block w-20 h-4"></span></td>
                        <td class="py-3"><span class="skeleton inline-block w-full h-4"></span></td>
                    </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    </div>

    <!-- 说明 -->
    <div class="stat-card rounded-2xl p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center">
            <span class="w-8 h-8 rounded-lg bg-amber-500/20 flex items-center justify-center mr-3">
                <span class="text-lg">ℹ️</span>
            </span>
            <span class="bg-gradient-to-r from-white to-slate-400 bg-clip-text text-transparent">统计说明</span>
        </h3>
        <div class="text-slate-400 text-sm space-y-3">
            <div class="flex items-start space-x-3 p-3 bg-slate-800/30 rounded-xl">
                <span class="text-amber-400">•</span>
                <p>数据基于日志文件中的 <code class="text-blue-400">[TAG]</code> 标签统计，每 5 分钟更新缓存</p>
            </div>
            <div class="flex items-start space-x-3 p-3 bg-slate-800/30 rounded-xl">
                <span class="text-amber-400">•</span>
                <p>仅统计今日（当前日期）的日志记录</p>
            </div>
            <div class="flex items-start space-x-3 p-3 bg-slate-800/30 rounded-xl">
                <span class="text-amber-400">•</span>
                <p>时间分布图展示 24 小时内每小时的日志数量</p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    loadApiStats();
});

async function loadApiStats() {
    try {
        const response = await fetch('/admin/api/api-stats-data');
        const result = await response.json();
        
        if (result.success) {
            renderStats(result.data);
        }
    } catch (error) {
        console.error('加载 API 统计失败:', error);
        document.getElementById('total-calls').textContent = '加载失败';
    }
}

function renderStats(stats) {
    const totalCalls = stats.total_calls || 0;
    const errorCount = stats.error_count || 0;
    const warningCount = stats.warning_count || 0;
    const modules = stats.modules || [];
    const hourly = stats.hourly || [];
    const recentErrors = stats.recent_errors || [];
    
    // 渲染概览卡片
    document.getElementById('total-calls').textContent = totalCalls.toLocaleString();
    
    // 错误/警告显示
    const errorWarningEl = document.getElementById('error-warning');
    errorWarningEl.innerHTML = `
        <span class="text-red-400">${errorCount}</span>
        <span class="text-slate-500 text-xl mx-1">/</span>
        <span class="text-yellow-400">${warningCount}</span>
    `;
    
    // 今日警告数
    document.getElementById('warning-count').textContent = warningCount.toLocaleString();
    
    // 渲染小时分布图
    renderHourlyChart(hourly);
    
    // 渲染模块列表
    renderModuleList(modules, totalCalls);
    
    // 渲染错误列表
    renderErrorList(recentErrors);
}

function renderHourlyChart(hourly) {
    const maxCount = Math.max(...hourly, 1);
    const rows = document.querySelectorAll('.hourly-row');
    
    rows.forEach((row, index) => {
        const count = hourly[index] || 0;
        const barFill = row.querySelector('.hour-bar-fill');
        const countEl = row.querySelector('.hour-count');
        
        // 计算宽度百分比
        const percent = count > 0 ? (count / maxCount) * 100 : 0;
        
        if (count > 0) {
            barFill.classList.remove('zero');
            barFill.style.width = percent + '%';
            countEl.classList.add('active');
        } else {
            barFill.classList.add('zero');
            barFill.style.width = '2px';
            countEl.classList.remove('active');
        }
        
        countEl.textContent = count.toLocaleString();
    });
    
    // 更新最大值标签
    const maxLabel = document.getElementById('chart-max-label');
    if (maxLabel) {
        maxLabel.innerHTML = `最大: <span class="text-cyan-400 font-semibold">${maxCount.toLocaleString()}</span> 次/小时`;
    }
}

function renderModuleList(modules, totalCalls) {
    const container = document.getElementById('module-list');
    
    // 模块中文名映射
    const moduleNames = {
        'API': 'ESI API 调用',
        'Assets': '资产数据',
        'AutoRefreshToken': 'Token 自动刷新',
        'TokenRefresh': 'Token 刷新',
        'Notifications': '通知系统',
        'Admin': '管理后台',
        'Market': '市场数据',
        'Skills': '技能数据',
        'Contracts': '合同数据',
        'Wallet': '钱包数据',
        'Killmails': 'KM数据',
        'Contacts': '联系人数据',
        'Bookmarks': '书签数据',
        'Fittings': '装配数据',
        'LPStore': 'LP商店',
        'Character': '角色数据',
        'Corporation': '军团数据',
        'Alliance': '联盟数据',
    };
    
    if (modules.length === 0) {
        container.innerHTML = `
            <div class="text-center py-12">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-slate-700/30 flex items-center justify-center">
                    <span class="text-3xl">📭</span>
                </div>
                <p class="text-slate-400 font-medium">暂无模块调用记录</p>
                <p class="text-slate-500 text-sm mt-1">今日还没有产生带标签的日志</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = modules.map(mod => {
        const percent = totalCalls > 0 ? ((mod.calls / totalCalls) * 100).toFixed(1) : 0;
        const successCount = mod.info || 0;
        const displayName = moduleNames[mod.tag] || mod.name || mod.tag;
        
        return `
            <div class="module-row">
                <span class="font-medium text-white">${displayName}</span>
                <span class="text-center font-semibold text-lg text-white">${mod.calls.toLocaleString()}</span>
                <span class="text-center">
                    <span class="count-badge count-success">${successCount}</span>
                </span>
                <span class="text-center">
                    <span class="count-badge count-warning">${mod.warning || 0}</span>
                </span>
                <span class="text-center">
                    <span class="count-badge count-error">${mod.error || 0}</span>
                </span>
                <span>
                    <div class="flex items-center gap-3">
                        <div class="progress-bar flex-1">
                            <div class="progress-fill" style="width: ${percent}%"></div>
                        </div>
                        <span class="text-sm text-slate-400 w-12 text-right">${percent}%</span>
                    </div>
                </span>
            </div>
        `;
    }).join('');
}

function renderErrorList(errors) {
    const tbody = document.getElementById('error-list');
    
    // 模块中文名映射
    const moduleNames = {
        'API': 'ESI API',
        'Assets': '资产',
        'AutoRefreshToken': 'Token刷新',
        'TokenRefresh': 'Token刷新',
        'Notifications': '通知',
        'Admin': '管理后台',
        'Market': '市场',
        'Skills': '技能',
        'Contracts': '合同',
        'Wallet': '钱包',
        'Killmails': 'KM',
        'Contacts': '联系人',
        'Bookmarks': '书签',
        'Fittings': '装配',
        'LPStore': 'LP商店',
        'Character': '角色',
        'Corporation': '军团',
        'Alliance': '联盟',
    };
    
    if (!errors || errors.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="py-8 text-center">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-green-500/10 flex items-center justify-center">
                        <span class="text-2xl">✅</span>
                    </div>
                    <p class="text-green-400 font-medium">今日无错误或警告</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = errors.map(err => {
        const levelClass = err.level === 'WARNING' 
            ? 'bg-yellow-500/20 text-yellow-400' 
            : 'bg-red-500/20 text-red-400';
        const moduleName = moduleNames[err.module] || err.module;
        
        return `
            <tr class="border-b border-slate-700/30 hover:bg-slate-800/30 transition-colors">
                <td class="py-3 text-sm text-slate-400 font-mono">${err.time}</td>
                <td class="py-3">
                    <span class="text-xs px-2 py-1 rounded-full font-medium ${levelClass}">${err.level}</span>
                </td>
                <td class="py-3 text-sm text-slate-300">${moduleName}</td>
                <td class="py-3 text-sm text-slate-400 truncate max-w-md" title="${escapeHtml(err.message)}">${escapeHtml(err.message)}</td>
            </tr>
        `;
    }).join('');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
@endpush
