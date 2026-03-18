@extends('layouts.admin')

@section('title', 'API 统计')
@section('page-title', 'API 统计')

@section('content')
<div class="space-y-6">
    <!-- 概览统计 -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="stat-card rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-sm">总调用次数</p>
                    <p class="text-3xl font-bold mt-1" id="total-calls">
                        <span class="skeleton inline-block w-16 h-8"></span>
                    </p>
                </div>
                <div class="text-4xl opacity-50">📊</div>
            </div>
        </div>
        
        <div class="stat-card rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-sm">端点数量</p>
                    <p class="text-3xl font-bold mt-1" id="endpoint-count">
                        <span class="skeleton inline-block w-12 h-8"></span>
                    </p>
                </div>
                <div class="text-4xl opacity-50">🔗</div>
            </div>
        </div>
        
        <div class="stat-card rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-sm">状态</p>
                    <p class="text-3xl font-bold mt-1 text-green-400">正常</p>
                </div>
                <div class="text-4xl opacity-50">✅</div>
            </div>
        </div>
    </div>

    <!-- 端点详情 -->
    <div class="stat-card rounded-xl p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center">
            <span class="mr-2">📈</span> API 端点调用详情
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-slate-400 text-sm border-b border-slate-700">
                        <th class="pb-3">端点路径</th>
                        <th class="pb-3">功能名称</th>
                        <th class="pb-3 text-right">调用次数</th>
                        <th class="pb-3 text-right">占比</th>
                    </tr>
                </thead>
                <tbody id="api-table-body">
                    @for ($i = 0; $i < 5; $i++)
                    <tr class="border-b border-slate-700/50">
                        <td class="py-3"><span class="skeleton inline-block w-48 h-4"></span></td>
                        <td class="py-3"><span class="skeleton inline-block w-24 h-4"></span></td>
                        <td class="py-3 text-right"><span class="skeleton inline-block w-16 h-4"></span></td>
                        <td class="py-3 text-right"><span class="skeleton inline-block w-12 h-4"></span></td>
                    </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    </div>

    <!-- 说明 -->
    <div class="stat-card rounded-xl p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center">
            <span class="mr-2">ℹ️</span> 说明
        </h3>
        <div class="text-slate-400 text-sm space-y-2">
            <p>• API 统计数据基于应用日志分析，每小时更新一次缓存</p>
            <p>• 调用次数统计的是日志中出现的 API 路径次数</p>
            <p>• 如需更精确的性能监控，建议接入专业的 APM 工具</p>
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
        const response = await fetch('/api/admin/api-stats');
        const result = await response.json();
        
        if (result.success) {
            renderStats(result.data);
        }
    } catch (error) {
        console.error('加载 API 统计失败:', error);
    }
}

function renderStats(stats) {
    const totalCalls = stats.total_calls || 0;
    const endpoints = stats.endpoints || [];
    
    document.getElementById('total-calls').textContent = totalCalls.toLocaleString();
    document.getElementById('endpoint-count').textContent = endpoints.length;
    
    const tbody = document.getElementById('api-table-body');
    
    if (endpoints.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="py-8 text-center text-slate-400">暂无 API 调用记录</td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = endpoints.map(endpoint => {
        const percent = totalCalls > 0 ? ((endpoint.calls / totalCalls) * 100).toFixed(1) : 0;
        return `
            <tr class="border-b border-slate-700/50 hover:bg-slate-800/30">
                <td class="py-3 font-mono text-sm text-blue-300">${endpoint.path}</td>
                <td class="py-3 text-slate-300">${endpoint.name}</td>
                <td class="py-3 text-right font-medium">${endpoint.calls.toLocaleString()}</td>
                <td class="py-3 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <div class="w-20 h-2 bg-slate-700 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-500 rounded-full" style="width: ${percent}%"></div>
                        </div>
                        <span class="text-sm text-slate-400">${percent}%</span>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}
</script>
@endpush
