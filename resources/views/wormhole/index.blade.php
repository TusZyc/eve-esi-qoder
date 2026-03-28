@extends('layouts.app')

@push('styles')
<style>
    .wh-tab { @apply px-6 py-3 text-sm font-medium cursor-pointer border-b-2 border-transparent transition-all; }
    .wh-tab.active { @apply border-blue-500 text-blue-400 bg-white/5; }
    .wh-tab:hover:not(.active) { @apply text-white/80 bg-white/5; }
    .wh-section { @apply bg-white/5 backdrop-blur rounded-xl p-6 mb-4 border border-white/10; }
    .wh-label { @apply text-sm text-blue-200 mb-1; }
    .wh-value { @apply text-white font-medium; }
    .wh-badge { @apply inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium; }
    .wh-badge-c1 { @apply bg-blue-500/20 text-blue-300; }
    .wh-badge-c2 { @apply bg-green-500/20 text-green-300; }
    .wh-badge-c3 { @apply bg-yellow-500/20 text-yellow-300; }
    .wh-badge-c4 { @apply bg-orange-500/20 text-orange-300; }
    .wh-badge-c5 { @apply bg-red-500/20 text-red-300; }
    .wh-badge-c6 { @apply bg-purple-500/20 text-purple-300; }
    .wh-badge-effect { @apply bg-pink-500/20 text-pink-300; }
    .ac-dropdown {
        position: absolute; left: 0; right: 0; top: 100%;
        z-index: 50; max-height: 240px; overflow-y: auto;
        background: rgba(15, 23, 42, 0.98); border: 1px solid rgba(255,255,255,0.2);
        border-radius: 0 0 8px 8px; backdrop-filter: blur(12px);
    }
    .ac-dropdown .ac-item {
        padding: 8px 12px; cursor: pointer; font-size: 14px;
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .ac-dropdown .ac-item:hover { background: rgba(59, 130, 246, 0.3); }
    .km-row { @apply flex items-center justify-between p-3 bg-white/5 rounded-lg mb-2 hover:bg-white/10 transition-colors cursor-pointer; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .spinner { border: 2px solid rgba(255,255,255,0.1); border-top-color: #60a5fa; border-radius: 50%; width: 20px; height: 20px; animation: spin 0.8s linear infinite; display: inline-block; }
</style>
@endpush

@section('title', '虫洞查询 - Tus Esi System')

@section('content')
<div class="container mx-auto px-4 py-6 max-w-7xl">
    <!-- 标签栏 -->
    <div class="flex border-b border-white/10 mb-6">
        <button class="wh-tab {{ $tab === 'search' ? 'active' : '' }}" data-tab="search" onclick="switchTab('search')">
            🔍 虫洞搜索
        </button>
        <button class="wh-tab {{ $tab === 'systems' ? 'active' : '' }}" data-tab="systems" onclick="switchTab('systems')">
            📋 虫洞检索
        </button>
        <button class="wh-tab {{ $tab === 'types' ? 'active' : '' }}" data-tab="types" onclick="switchTab('types')">
            🕳️ 洞点快查
        </button>
    </div>

    <!-- 标签栏一：虫洞搜索 -->
    <div id="tab-search" class="tab-content {{ $tab !== 'search' ? 'hidden' : '' }}">
        <!-- 搜索框 -->
        <div class="wh-section">
            <h3 class="text-lg font-bold mb-4">搜索虫洞星系</h3>
            <div class="flex gap-4">
                <div class="relative flex-1">
                    <input type="text" id="searchInput" placeholder="输入虫洞星系编号（如 J100033 或 100033）" 
                           class="w-full bg-white/10 border border-white/20 rounded-lg px-4 py-3 outline-none focus:border-blue-400" 
                           value="{{ $searchQuery ?? '' }}" autocomplete="off">
                    <div id="searchDropdown" class="ac-dropdown hidden"></div>
                </div>
                <button onclick="searchSystem()" class="bg-blue-600 hover:bg-blue-700 px-6 py-3 rounded-lg font-medium transition-colors">
                    搜索
                </button>
            </div>
        </div>

        <!-- 搜索结果 -->
        <div id="systemResult">
            @if($systemInfo)
                @include('wormhole.partials.system-detail', ['systemInfo' => $systemInfo, 'kills' => $kills ?? null])
            @endif
        </div>
    </div>

    <!-- 标签栏二：虫洞检索 -->
    <div id="tab-systems" class="tab-content {{ $tab !== 'systems' ? 'hidden' : '' }}">
        <div class="wh-section">
            <div class="flex flex-wrap items-center gap-4 mb-4">
                <div class="flex items-center gap-2">
                    <span class="text-sm text-blue-200">等级:</span>
                    <select id="filterClass" class="bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm" onchange="loadSystemsList()">
                        <option value="">全部</option>
                        <option value="1">C1</option>
                        <option value="2">C2</option>
                        <option value="3">C3</option>
                        <option value="4">C4</option>
                        <option value="5">C5</option>
                        <option value="6">C6</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-blue-200">效果:</span>
                    <select id="filterEffect" class="bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm" onchange="loadSystemsList()">
                        <option value="">全部</option>
                        @foreach($effects as $effect)
                            <option value="{{ $effect['key'] }}">{{ $effect['name_zh'] }}</option>
                        @endforeach
                    </select>
                </div>
                <button onclick="loadSystemsList()" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg text-sm transition-colors">
                    刷新
                </button>
            </div>

            <!-- 系统列表 -->
            <div id="systemsList" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/10 text-left">
                            <th class="py-3 px-4">星系编号</th>
                            <th class="py-3 px-4">等级</th>
                            <th class="py-3 px-4">效果</th>
                            <th class="py-3 px-4">静态连接</th>
                            <th class="py-3 px-4">操作</th>
                        </tr>
                    </thead>
                    <tbody id="systemsTableBody">
                        <tr><td colspan="5" class="py-8 text-center text-white/50">加载中...</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- 分页 -->
            <div id="systemsPagination" class="mt-4 flex items-center justify-between">
                <span id="systemsInfo" class="text-sm text-white/50"></span>
                <div class="flex gap-2" id="systemsPageButtons"></div>
            </div>
        </div>
    </div>

    <!-- 标签栏三：洞点快查 -->
    <div id="tab-types" class="tab-content {{ $tab !== 'types' ? 'hidden' : '' }}">
        <div class="wh-section">
            <div class="flex items-center gap-4 mb-4">
                <div class="flex items-center gap-2">
                    <span class="text-sm text-blue-200">目的地:</span>
                    <select id="filterDestination" class="bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm" onchange="loadTypesList()">
                        <option value="">全部</option>
                        <option value="high_sec">高安全区域</option>
                        <option value="low_sec">低安全区域</option>
                        <option value="null_sec">零安全区域</option>
                        <option value="c1">C1 虫洞</option>
                        <option value="c2">C2 虫洞</option>
                        <option value="c3">C3 虫洞</option>
                        <option value="c4">C4 虫洞</option>
                        <option value="c5">C5 虫洞</option>
                        <option value="c6">C6 虫洞</option>
                        <option value="thera">塞拉</option>
                    </select>
                </div>
            </div>

            <!-- 类型列表 -->
            <div id="typesList" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/10 text-left">
                            <th class="py-3 px-4">类型代码</th>
                            <th class="py-3 px-4">目的地</th>
                            <th class="py-3 px-4">寿命</th>
                            <th class="py-3 px-4">最大质量</th>
                            <th class="py-3 px-4">跳跃质量</th>
                        </tr>
                    </thead>
                    <tbody id="typesTableBody">
                        <tr><td colspan="5" class="py-8 text-center text-white/50">加载中...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let currentSystemPage = 1;
let searchTimeout = null;

// 标签切换
function switchTab(tab) {
    document.querySelectorAll('.wh-tab').forEach(t => t.classList.remove('active'));
    document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
    document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
    document.getElementById(`tab-${tab}`).classList.remove('hidden');
    
    // 更新URL
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    url.searchParams.delete('system');
    window.history.pushState({}, '', url);
    
    // 加载数据
    if (tab === 'systems') loadSystemsList();
    if (tab === 'types') loadTypesList();
}

// 搜索系统
function searchSystem() {
    const query = document.getElementById('searchInput').value.trim();
    if (!query) return;
    
    // 更新URL
    const url = new URL(window.location);
    url.searchParams.set('system', query);
    url.searchParams.set('tab', 'search');
    window.location.href = url.toString();
}

// 自动补全
document.getElementById('searchInput')?.addEventListener('input', function(e) {
    const query = e.target.value.trim();
    const dropdown = document.getElementById('searchDropdown');
    
    clearTimeout(searchTimeout);
    if (query.length < 1) {
        dropdown.classList.add('hidden');
        return;
    }
    
    searchTimeout = setTimeout(() => {
        fetch(`/api/public/wormhole/autocomplete?q=${encodeURIComponent(query)}`)
            .then(r => r.json())
            .then(data => {
                if (data.length === 0) {
                    dropdown.classList.add('hidden');
                    return;
                }
                
                dropdown.innerHTML = data.map(s => `
                    <div class="ac-item" onclick="selectSystem('${s.name}')">
                        <span class="font-medium">${s.name}</span>
                        <span class="wh-badge wh-badge-c${s.class} ml-2">C${s.class}</span>
                        ${s.effect_zh ? `<span class="wh-badge wh-badge-effect ml-1">${s.effect_zh}</span>` : ''}
                    </div>
                `).join('');
                dropdown.classList.remove('hidden');
            });
    }, 300);
});

function selectSystem(name) {
    document.getElementById('searchInput').value = name;
    document.getElementById('searchDropdown').classList.add('hidden');
    searchSystem();
}

// 加载系统列表
function loadSystemsList(page = 1) {
    currentSystemPage = page;
    const classFilter = document.getElementById('filterClass').value;
    const effectFilter = document.getElementById('filterEffect').value;
    
    let url = `/api/public/wormhole/systems?page=${page}&per_page=30`;
    if (classFilter) url += `&class=${classFilter}`;
    if (effectFilter) url += `&effect=${effectFilter}`;
    
    fetch(url).then(r => r.json()).then(data => {
        const tbody = document.getElementById('systemsTableBody');
        tbody.innerHTML = data.systems.map(s => `
            <tr class="border-b border-white/5 hover:bg-white/5">
                <td class="py-3 px-4 font-medium">${s.name}</td>
                <td class="py-3 px-4"><span class="wh-badge wh-badge-c${s.class}">C${s.class}</span></td>
                <td class="py-3 px-4">${s.effect_zh || '-'}</td>
                <td class="py-3 px-4">${s.statics.map(st => `<span class="text-blue-300">${st.type}→${st.destination_zh}</span>`).join(', ') || '-'}</td>
                <td class="py-3 px-4">
                    <a href="/wormhole/${s.name}" class="text-blue-400 hover:text-blue-300">详情</a>
                </td>
            </tr>
        `).join('');
        
        // 分页
        const p = data.pagination;
        document.getElementById('systemsInfo').textContent = `共 ${p.total} 条，第 ${p.page}/${p.total_pages} 页`;
        
        let btns = '';
        if (p.page > 1) btns += `<button onclick="loadSystemsList(${p.page - 1})" class="px-3 py-1 bg-white/10 rounded hover:bg-white/20">上一页</button>`;
        if (p.page < p.total_pages) btns += `<button onclick="loadSystemsList(${p.page + 1})" class="px-3 py-1 bg-white/10 rounded hover:bg-white/20">下一页</button>`;
        document.getElementById('systemsPageButtons').innerHTML = btns;
    });
}

// 加载类型列表
function loadTypesList() {
    const dest = document.getElementById('filterDestination').value;
    let url = '/api/public/wormhole/types';
    if (dest) url += `?destination=${dest}`;
    
    fetch(url).then(r => r.json()).then(data => {
        const tbody = document.getElementById('typesTableBody');
        tbody.innerHTML = data.types.map(t => `
            <tr class="border-b border-white/5 hover:bg-white/5">
                <td class="py-3 px-4 font-medium text-blue-300">${t.type}</td>
                <td class="py-3 px-4">${t.destination_zh}</td>
                <td class="py-3 px-4">${t.lifetime_hours}小时</td>
                <td class="py-3 px-4">${formatMass(t.max_mass)}</td>
                <td class="py-3 px-4">${formatMass(t.jump_mass)}</td>
            </tr>
        `).join('');
    });
}

// 格式化质量
function formatMass(kg) {
    if (!kg) return '-';
    if (kg >= 1e12) return (kg / 1e12).toFixed(1) + 'T';
    if (kg >= 1e9) return (kg / 1e9).toFixed(1) + 'B';
    if (kg >= 1e6) return (kg / 1e6).toFixed(1) + 'M';
    if (kg >= 1e3) return (kg / 1e3).toFixed(1) + 'K';
    return kg;
}

// 初始化
document.addEventListener('DOMContentLoaded', function() {
    const tab = '{{ $tab }}';
    if (tab === 'systems') loadSystemsList();
    if (tab === 'types') loadTypesList();
});
</script>
@endpush