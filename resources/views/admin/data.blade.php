@extends('layouts.admin')

@section('title', '数据管理')
@section('page-title', '数据管理')

@push('styles')
<style>
    .file-row {
        cursor: pointer;
        transition: background 0.2s;
    }
    .file-row:hover {
        background: rgba(59, 130, 246, 0.05);
    }
    .preview-detail {
        display: none;
        background: rgba(15, 23, 42, 0.5);
        border-radius: 8px;
        margin-top: 8px;
        padding: 12px;
    }
    .preview-detail.expanded {
        display: block;
    }
    .preview-json {
        background: rgba(0, 0, 0, 0.3);
        border-radius: 6px;
        padding: 12px;
        font-family: 'Consolas', 'Monaco', monospace;
        font-size: 12px;
        color: #94a3b8;
        overflow-x: auto;
        white-space: pre-wrap;
        word-break: break-all;
        max-height: 300px;
        overflow-y: auto;
    }
    .expand-icon {
        transition: transform 0.2s;
    }
    .file-row.expanded .expand-icon {
        transform: rotate(90deg);
    }
    .stale-warning {
        color: #f87171;
    }
    .freshness-badge {
        font-size: 11px;
        padding: 2px 8px;
        border-radius: 9999px;
    }
    .freshness-fresh { background: rgba(34, 197, 94, 0.2); color: #4ade80; }
    .freshness-normal { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
    .freshness-stale { background: rgba(239, 68, 68, 0.2); color: #f87171; }
</style>
@endpush

@section('content')
<div class="space-y-6">
    <!-- 概览卡片 -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="stat-card rounded-2xl p-5 group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-sm font-medium">数据文件总数</p>
                    <p class="text-3xl font-bold mt-2 bg-gradient-to-r from-blue-400 to-cyan-400 bg-clip-text text-transparent" id="stat-file-count">
                        <span class="skeleton inline-block w-12 h-8"></span>
                    </p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500/20 to-cyan-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <span class="text-3xl">📁</span>
                </div>
            </div>
        </div>
        
        <div class="stat-card rounded-2xl p-5 group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-sm font-medium">总大小</p>
                    <p class="text-3xl font-bold mt-2 bg-gradient-to-r from-green-400 to-emerald-400 bg-clip-text text-transparent" id="stat-total-size">
                        <span class="skeleton inline-block w-20 h-8"></span>
                    </p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-green-500/20 to-emerald-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <span class="text-3xl">💾</span>
                </div>
            </div>
        </div>
        
        <div class="stat-card rounded-2xl p-5 group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-sm font-medium">最后更新</p>
                    <p class="text-2xl font-bold mt-2 bg-gradient-to-r from-purple-400 to-violet-400 bg-clip-text text-transparent" id="stat-last-update">
                        <span class="skeleton inline-block w-28 h-8"></span>
                    </p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-purple-500/20 to-violet-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <span class="text-3xl">🕐</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 数据文件列表 -->
    <div class="stat-card rounded-2xl p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center">
            <span class="w-8 h-8 rounded-lg bg-blue-500/20 flex items-center justify-center mr-3">
                <span class="text-lg">📦</span>
            </span>
            <span class="bg-gradient-to-r from-white to-slate-400 bg-clip-text text-transparent">数据文件列表</span>
            <span class="text-slate-500 text-sm ml-2">(点击查看数据预览)</span>
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-slate-400 text-sm border-b border-slate-700">
                        <th class="pb-3 w-8"></th>
                        <th class="pb-3">文件名</th>
                        <th class="pb-3">用途说明</th>
                        <th class="pb-3 text-right">记录数</th>
                        <th class="pb-3 text-right">文件大小</th>
                        <th class="pb-3 text-right">数据新鲜度</th>
                    </tr>
                </thead>
                <tbody id="files-body">
                    <!-- 骨架屏 -->
                    @for ($i = 0; $i < 8; $i++)
                    <tr class="border-b border-slate-700/50">
                        <td class="py-3"><span class="skeleton inline-block w-4 h-4"></span></td>
                        <td class="py-3"><span class="skeleton inline-block w-40 h-4"></span></td>
                        <td class="py-3"><span class="skeleton inline-block w-48 h-4"></span></td>
                        <td class="py-3 text-right"><span class="skeleton inline-block w-16 h-4"></span></td>
                        <td class="py-3 text-right"><span class="skeleton inline-block w-16 h-4"></span></td>
                        <td class="py-3 text-right"><span class="skeleton inline-block w-20 h-4"></span></td>
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
            <span class="bg-gradient-to-r from-white to-slate-400 bg-clip-text text-transparent">数据说明</span>
        </h3>
        <div class="text-slate-400 text-sm space-y-3">
            <div class="flex items-start space-x-3 p-3 bg-slate-800/30 rounded-xl">
                <span class="text-amber-400">•</span>
                <p>数据文件存储在 <code class="text-blue-400">data/</code> 目录下，包含游戏静态数据</p>
            </div>
            <div class="flex items-start space-x-3 p-3 bg-slate-800/30 rounded-xl">
                <span class="text-amber-400">•</span>
                <p><span class="text-red-400">红色标记</span> 表示数据超过 7 天未更新，可能需要刷新</p>
            </div>
            <div class="flex items-start space-x-3 p-3 bg-slate-800/30 rounded-xl">
                <span class="text-amber-400">•</span>
                <p>点击文件行可以预览前 3 条数据记录</p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let filesData = [];

document.addEventListener('DOMContentLoaded', function() {
    loadDataInfo();
});

async function loadDataInfo() {
    try {
        const response = await fetch('/admin/api/data-info');
        const result = await response.json();
        
        if (result.success) {
            const data = result.data;
            filesData = data.files;
            
            // 更新统计数据
            document.getElementById('stat-file-count').textContent = data.file_count;
            document.getElementById('stat-total-size').textContent = formatBytes(data.total_size);
            document.getElementById('stat-last-update').textContent = data.latest_update || '-';
            
            // 更新文件列表
            renderFilesList(data.files);
        }
    } catch (error) {
        console.error('加载数据信息失败:', error);
    }
}

function renderFilesList(files) {
    const tbody = document.getElementById('files-body');
    
    if (!files || files.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="py-8 text-center text-slate-400">暂无数据文件</td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = files.map((file, index) => {
        const freshnessClass = getFreshnessClass(file.is_stale, file.freshness);
        const staleClass = file.is_stale ? 'stale-warning' : '';
        
        return `
            <tr class="file-row border-b border-slate-700/30" data-index="${index}" onclick="togglePreview(${index})">
                <td class="py-4 text-slate-500">
                    <span class="expand-icon inline-block">▶</span>
                </td>
                <td class="py-4">
                    <span class="font-mono text-sm text-blue-400">${file.name}</span>
                </td>
                <td class="py-4 text-slate-300 text-sm">${file.description || '未知用途'}</td>
                <td class="py-4 text-right font-semibold text-white">${typeof file.records === 'number' ? file.records.toLocaleString() : file.records}</td>
                <td class="py-4 text-right text-slate-300">${formatBytes(file.size)}</td>
                <td class="py-4 text-right">
                    <span class="freshness-badge ${freshnessClass} ${staleClass}">${file.freshness}</span>
                    ${file.is_stale ? '<span class="ml-1 text-red-400" title="数据可能过期">⚠️</span>' : ''}
                </td>
            </tr>
            <tr class="preview-row" data-index="${index}">
                <td colspan="6" class="p-0">
                    <div class="preview-detail" id="preview-detail-${index}">
                        <div class="text-xs text-slate-500 mb-2">数据预览（前 3 条记录）:</div>
                        <div class="preview-json">${formatPreview(file.preview)}</div>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function getFreshnessClass(isStale, freshness) {
    if (isStale) return 'freshness-stale';
    if (freshness.includes('小时') || freshness.includes('分钟') || freshness === '刚刚') {
        return 'freshness-fresh';
    }
    return 'freshness-normal';
}

function formatPreview(preview) {
    if (!preview || preview.length === 0) {
        return '无预览数据';
    }
    try {
        return JSON.stringify(preview, null, 2)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    } catch (e) {
        return '预览数据格式错误';
    }
}

function togglePreview(index) {
    const row = document.querySelector(`.file-row[data-index="${index}"]`);
    const detail = document.getElementById(`preview-detail-${index}`);
    
    if (row.classList.contains('expanded')) {
        row.classList.remove('expanded');
        detail.classList.remove('expanded');
    } else {
        row.classList.add('expanded');
        detail.classList.add('expanded');
    }
}

function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}
</script>
@endpush
