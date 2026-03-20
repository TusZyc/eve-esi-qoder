@extends('layouts.admin')

@section('title', '缓存管理')
@section('page-title', '缓存管理')

@push('styles')
<style>
    .category-row {
        cursor: pointer;
        transition: background 0.2s;
    }
    .category-row:hover {
        background: rgba(59, 130, 246, 0.05);
    }
    .keys-detail {
        display: none;
        background: rgba(15, 23, 42, 0.5);
        border-radius: 8px;
        margin-top: 8px;
        padding: 12px;
    }
    .keys-detail.expanded {
        display: block;
    }
    .key-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 12px;
        border-bottom: 1px solid rgba(51, 65, 85, 0.3);
        font-size: 13px;
    }
    .key-item:last-child {
        border-bottom: none;
    }
    .ttl-badge {
        font-size: 11px;
        padding: 2px 8px;
        border-radius: 9999px;
    }
    .ttl-expiring { background: rgba(239, 68, 68, 0.2); color: #f87171; }
    .ttl-normal { background: rgba(34, 197, 94, 0.2); color: #4ade80; }
    .ttl-long { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
    .ttl-no-ttl { background: rgba(148, 163, 184, 0.2); color: #94a3b8; }
    .expand-icon {
        transition: transform 0.2s;
    }
    .category-row.expanded .expand-icon {
        transform: rotate(90deg);
    }
</style>
@endpush

@section('content')
<div class="space-y-6">
    <!-- Redis 概览 -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="stat-card rounded-2xl p-5 group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-sm font-medium">内存使用</p>
                    <p class="text-3xl font-bold mt-2 bg-gradient-to-r from-blue-400 to-cyan-400 bg-clip-text text-transparent" id="stat-memory">
                        <span class="skeleton inline-block w-20 h-8"></span>
                    </p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500/20 to-cyan-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <span class="text-3xl">💾</span>
                </div>
            </div>
        </div>
        
        <div class="stat-card rounded-2xl p-5 group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-sm font-medium">缓存键数量</p>
                    <p class="text-3xl font-bold mt-2 bg-gradient-to-r from-green-400 to-emerald-400 bg-clip-text text-transparent" id="stat-keys">
                        <span class="skeleton inline-block w-16 h-8"></span>
                    </p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-green-500/20 to-emerald-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <span class="text-3xl">🔑</span>
                </div>
            </div>
        </div>
        
        <div class="stat-card rounded-2xl p-5 group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-sm font-medium">连接状态</p>
                    <p class="text-3xl font-bold mt-2" id="stat-status">
                        <span class="skeleton inline-block w-16 h-8"></span>
                    </p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-purple-500/20 to-violet-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <span class="text-3xl">🔌</span>
                </div>
            </div>
        </div>
    </div>

    <!-- TTL分布统计 -->
    <div class="stat-card rounded-2xl p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center">
            <span class="w-8 h-8 rounded-lg bg-amber-500/20 flex items-center justify-center mr-3">
                <span class="text-lg">⏱️</span>
            </span>
            <span class="bg-gradient-to-r from-white to-slate-400 bg-clip-text text-transparent">TTL 过期时间分布</span>
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4" id="ttl-distribution">
            <div class="bg-slate-800/30 rounded-xl p-4 text-center">
                <p class="text-red-400 text-2xl font-bold" id="ttl-expiring">-</p>
                <p class="text-slate-400 text-sm mt-1">即将过期 (&lt;1h)</p>
            </div>
            <div class="bg-slate-800/30 rounded-xl p-4 text-center">
                <p class="text-green-400 text-2xl font-bold" id="ttl-normal">-</p>
                <p class="text-slate-400 text-sm mt-1">正常 (1-24h)</p>
            </div>
            <div class="bg-slate-800/30 rounded-xl p-4 text-center">
                <p class="text-blue-400 text-2xl font-bold" id="ttl-long">-</p>
                <p class="text-slate-400 text-sm mt-1">长期 (&gt;24h)</p>
            </div>
            <div class="bg-slate-800/30 rounded-xl p-4 text-center">
                <p class="text-slate-400 text-2xl font-bold" id="ttl-no-ttl">-</p>
                <p class="text-slate-400 text-sm mt-1">永不过期</p>
            </div>
        </div>
    </div>

    <!-- 缓存分类统计 -->
    <div class="stat-card rounded-2xl p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center">
            <span class="w-8 h-8 rounded-lg bg-blue-500/20 flex items-center justify-center mr-3">
                <span class="text-lg">📊</span>
            </span>
            <span class="bg-gradient-to-r from-white to-slate-400 bg-clip-text text-transparent">缓存分类统计</span>
            <span class="text-slate-500 text-sm ml-2">(点击展开查看详情)</span>
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-slate-400 text-sm border-b border-slate-700">
                        <th class="pb-3 w-8"></th>
                        <th class="pb-3">分类名称</th>
                        <th class="pb-3 text-right">键数量</th>
                        <th class="pb-3 text-right">内存占用</th>
                        <th class="pb-3 text-right">占比</th>
                        <th class="pb-3 text-right">操作</th>
                    </tr>
                </thead>
                <tbody id="categories-body">
                    <!-- 骨架屏 -->
                    @for ($i = 0; $i < 5; $i++)
                    <tr class="border-b border-slate-700/50">
                        <td class="py-3"><span class="skeleton inline-block w-4 h-4"></span></td>
                        <td class="py-3"><span class="skeleton inline-block w-32 h-4"></span></td>
                        <td class="py-3 text-right"><span class="skeleton inline-block w-12 h-4"></span></td>
                        <td class="py-3 text-right"><span class="skeleton inline-block w-16 h-4"></span></td>
                        <td class="py-3 text-right"><span class="skeleton inline-block w-12 h-4"></span></td>
                        <td class="py-3 text-right"><span class="skeleton inline-block w-16 h-6"></span></td>
                    </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    </div>

    <!-- 操作区域 -->
    <div class="stat-card rounded-2xl p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center">
            <span class="w-8 h-8 rounded-lg bg-orange-500/20 flex items-center justify-center mr-3">
                <span class="text-lg">⚙️</span>
            </span>
            <span class="bg-gradient-to-r from-white to-slate-400 bg-clip-text text-transparent">全局操作</span>
        </h3>
        <div class="flex flex-wrap gap-3">
            <button onclick="clearCache('views')" 
                    class="px-4 py-2 rounded-lg bg-gradient-to-r from-purple-500 to-purple-600 text-white font-medium hover:from-purple-600 hover:to-purple-700 transition-all shadow-lg shadow-purple-500/20 hover:shadow-purple-500/30">
                🎨 清除视图缓存
            </button>
            <button onclick="clearAllCache()" 
                    class="px-4 py-2 rounded-lg bg-gradient-to-r from-red-500 to-red-600 text-white font-medium hover:from-red-600 hover:to-red-700 transition-all shadow-lg shadow-red-500/20 hover:shadow-red-500/30">
                🗑️ 清除全部缓存
            </button>
        </div>
        <p class="text-slate-500 text-sm mt-4">⚠️ 清除缓存后，系统将重新从 ESI 获取数据，可能会暂时影响响应速度。</p>
    </div>
</div>
@endsection

@push('scripts')
<script>
let totalKeys = 0;

document.addEventListener('DOMContentLoaded', function() {
    loadCacheData();
});

async function loadCacheData() {
    try {
        const response = await fetch('/admin/api/cache-data');
        const result = await response.json();
        
        if (result.success) {
            const data = result.data;
            
            // 更新统计数据
            document.getElementById('stat-memory').textContent = data.redis_memory;
            totalKeys = data.redis_keys;
            document.getElementById('stat-keys').textContent = totalKeys.toLocaleString();
            
            const statusEl = document.getElementById('stat-status');
            if (data.redis_status === 'online') {
                statusEl.innerHTML = '<span class="text-green-400">在线</span>';
            } else if (data.redis_status === 'error') {
                statusEl.innerHTML = '<span class="text-red-400">错误</span>';
            } else {
                statusEl.innerHTML = '<span class="text-slate-400">离线</span>';
            }
            
            // 更新TTL分布
            if (data.ttl_distribution) {
                document.getElementById('ttl-expiring').textContent = data.ttl_distribution.expiring.toLocaleString();
                document.getElementById('ttl-normal').textContent = data.ttl_distribution.normal.toLocaleString();
                document.getElementById('ttl-long').textContent = data.ttl_distribution.long.toLocaleString();
                document.getElementById('ttl-no-ttl').textContent = data.ttl_distribution.no_ttl.toLocaleString();
            }
            
            // 更新分类表格
            renderCategories(data.categories);
        }
    } catch (error) {
        console.error('加载缓存数据失败:', error);
    }
}

function renderCategories(categories) {
    const tbody = document.getElementById('categories-body');
    
    if (!categories || categories.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="py-8 text-center text-slate-400">暂无缓存数据</td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = categories.map((cat, index) => {
        const percent = totalKeys > 0 ? ((cat.count / totalKeys) * 100).toFixed(1) : 0;
        const keysHtml = renderKeysDetail(cat.keys || []);
        const clearType = getClearType(cat.prefix);
        
        return `
            <tr class="category-row border-b border-slate-700/30" data-index="${index}" onclick="toggleKeys(${index})">
                <td class="py-4 text-slate-500">
                    <span class="expand-icon inline-block">▶</span>
                </td>
                <td class="py-4">
                    <span class="font-medium text-slate-200">${cat.name}</span>
                    <span class="text-xs text-slate-500 ml-2">${cat.prefix === 'other' ? '' : cat.prefix}</span>
                </td>
                <td class="py-4 text-right font-semibold text-white">${cat.count.toLocaleString()}</td>
                <td class="py-4 text-right text-slate-300">${cat.memory_text || '-'}</td>
                <td class="py-4 text-right">
                    <span class="text-xs px-3 py-1.5 rounded-full bg-blue-500/15 text-blue-400 font-medium">${percent}%</span>
                </td>
                <td class="py-4 text-right" onclick="event.stopPropagation()">
                    <button onclick="clearCacheByPrefix('${clearType}', '${cat.name}')" 
                            class="text-xs px-3 py-1.5 rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20 transition-colors">
                        清除
                    </button>
                </td>
            </tr>
            <tr class="keys-row" data-index="${index}">
                <td colspan="6" class="p-0">
                    <div class="keys-detail" id="keys-detail-${index}">
                        ${keysHtml}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function renderKeysDetail(keys) {
    if (!keys || keys.length === 0) {
        return '<p class="text-slate-500 text-sm text-center py-4">暂无键详情</p>';
    }
    
    return `
        <div class="text-xs text-slate-500 mb-2">显示前 ${keys.length} 个键:</div>
        ${keys.map(k => `
            <div class="key-item">
                <span class="text-slate-300 font-mono truncate flex-1 mr-4" title="${k.full_key}">${k.key}</span>
                <span class="ttl-badge ${getTtlClass(k.ttl)}">${k.ttl_text}</span>
                <span class="text-slate-500 ml-3 w-20 text-right">${k.memory_text}</span>
            </div>
        `).join('')}
    `;
}

function getTtlClass(ttl) {
    if (ttl === -1) return 'ttl-no-ttl';
    if (ttl < 3600) return 'ttl-expiring';
    if (ttl <= 86400) return 'ttl-normal';
    return 'ttl-long';
}

function getClearType(prefix) {
    // 根据前缀返回清除类型
    const map = {
        'eve_esi_database_location:': 'locations',
        'eve_esi_database_market:': 'market',
        'eve_esi_database_character:': 'character',
        'eve_esi_database_corp:': 'corp',
        'eve_esi_database_alliance:': 'alliance',
        'eve_esi_database_universe:': 'universe',
        'eve_esi_database_esi:': 'esi',
        'eve_esi_database_admin:': 'admin',
        'eve_esi_database_lp:': 'lp',
    };
    return map[prefix] || prefix;
}

function toggleKeys(index) {
    const row = document.querySelector(`.category-row[data-index="${index}"]`);
    const detail = document.getElementById(`keys-detail-${index}`);
    
    if (row.classList.contains('expanded')) {
        row.classList.remove('expanded');
        detail.classList.remove('expanded');
    } else {
        row.classList.add('expanded');
        detail.classList.add('expanded');
    }
}

async function clearCacheByPrefix(type, name) {
    if (!confirm(`确定要清除 ${name} 缓存吗？`)) {
        return;
    }
    
    try {
        const response = await fetch('/admin/api/clear-cache', {
            method: 'POST',
            body: JSON.stringify({ type }),
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            loadCacheData();
        } else {
            alert('清除失败: ' + result.message);
        }
    } catch (error) {
        console.error('清除缓存失败:', error);
        alert('清除失败，请查看控制台');
    }
}

async function clearCache(type) {
    const typeNames = {
        'views': '视图'
    };
    
    if (!confirm(`确定要清除${typeNames[type] || type}缓存吗？`)) {
        return;
    }
    
    try {
        const response = await fetch('/admin/api/clear-cache', {
            method: 'POST',
            body: JSON.stringify({ type }),
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            loadCacheData();
        } else {
            alert('清除失败: ' + result.message);
        }
    } catch (error) {
        console.error('清除缓存失败:', error);
        alert('清除失败，请查看控制台');
    }
}

async function clearAllCache() {
    if (!confirm('⚠️ 确定要清除全部缓存吗？\n\n这将删除所有 Redis 缓存和视图缓存，可能会暂时影响系统性能。')) {
        return;
    }
    
    try {
        const response = await fetch('/admin/api/clear-cache', {
            method: 'POST',
            body: JSON.stringify({ type: 'all' }),
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            loadCacheData();
        } else {
            alert('清除失败: ' + result.message);
        }
    } catch (error) {
        console.error('清除缓存失败:', error);
        alert('清除失败，请查看控制台');
    }
}
</script>
@endpush
