@extends('layouts.app')

@section('page-title', '虫洞查询')

@push('styles')
    /* 下拉框选项样式 - 修复暗色主题下选项不可见问题 */
    select option {
        background-color: #1e293b;
        color: #e2e8f0;
    }
    .wh-tab {
        padding: 10px 24px; cursor: pointer; font-size: 14px; font-weight: 500;
        border-bottom: 2px solid transparent; transition: all 0.2s;
        color: rgba(255,255,255,0.5); border-radius: 8px 8px 0 0;
    }
    .wh-tab:hover { color: rgba(255,255,255,0.85); background: rgba(255,255,255,0.05); }
    .wh-tab.active { color: white; border-bottom-color: #60a5fa; background: rgba(255,255,255,0.08); }
    .wh-section { background: rgba(255,255,255,0.05); backdrop-filter: blur(12px); border-radius: 12px; padding: 24px; margin-bottom: 16px; border: 1px solid rgba(255,255,255,0.1); }
    .wh-label { font-size: 14px; color: #bfdbfe; margin-bottom: 4px; }
    .wh-value { color: white; font-weight: 500; }
    .wh-badge { display: inline-flex; align-items: center; padding: 2px 10px; border-radius: 9999px; font-size: 12px; font-weight: 500; }
    .wh-badge-c1 { background: rgba(59,130,246,0.2); color: #93c5fd; }
    .wh-badge-c2 { background: rgba(34,197,94,0.2); color: #86efac; }
    .wh-badge-c3 { background: rgba(234,179,8,0.2); color: #fde047; }
    .wh-badge-c4 { background: rgba(249,115,22,0.2); color: #fdba74; }
    .wh-badge-c5 { background: rgba(239,68,68,0.2); color: #fca5a5; }
    .wh-badge-c6 { background: rgba(168,85,247,0.2); color: #d8b4fe; }
    .wh-badge-effect { background: rgba(236,72,153,0.2); color: #f9a8d4; }
    .ac-dropdown {
        position: absolute; left: 0; right: 0; top: 100%;
        z-index: 50; max-height: 240px; overflow-y: auto;
        background: rgba(15, 23, 42, 0.98); border: 1px solid rgba(255,255,255,0.2);
        border-radius: 0 0 8px 8px; backdrop-filter: blur(12px);
    }
    .ac-dropdown .ac-item {
        padding: 8px 12px; cursor: pointer; font-size: 14px;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        color: rgba(255,255,255,0.8);
    }
    .ac-dropdown .ac-item:hover, .ac-dropdown .ac-item.active {
        background: rgba(59, 130, 246, 0.3); color: white;
    }
    .km-row { display: flex; align-items: center; justify-content: space-between; padding: 12px; background: rgba(255,255,255,0.05); border-radius: 8px; margin-bottom: 8px; cursor: pointer; transition: background 0.2s; }
    .km-row:hover { background: rgba(255,255,255,0.1); }
    @keyframes spin { to { transform: rotate(360deg); } }
    .spinner { border: 2px solid rgba(255,255,255,0.1); border-top-color: #60a5fa; border-radius: 50%; width: 20px; height: 20px; animation: spin 0.8s linear infinite; display: inline-block; }
@endpush

@section('title', '虫洞查询 - Tus Esi System')

@section('content')
<div class="container mx-auto px-4 py-6 max-w-7xl">
    <!-- 标签栏 - 纯JS切换，不操控URL -->
    <div class="bg-white/5 backdrop-blur rounded-xl border border-white/10 mb-6">
        <div class="flex px-4 pt-2 border-b border-white/10">
            <button id="wh-tab-search" class="wh-tab active" onclick="switchTab('search')">
                🔍 虫洞搜索
            </button>
            <button id="wh-tab-systems" class="wh-tab" onclick="switchTab('systems')">
                📋 虫洞检索
            </button>
            <button id="wh-tab-types" class="wh-tab" onclick="switchTab('types')">
                🕳️ 洞点快查
            </button>
        </div>
    </div>

    <!-- 标签栏一：虫洞搜索 -->
    <div id="content-search" class="tab-content">
        <!-- 搜索框 -->
        <div class="wh-section" style="position: relative; z-index: 20;">
            <h3 class="text-lg font-bold mb-4">搜索虫洞星系</h3>
            <div class="flex gap-4">
                <div class="relative flex-1">
                    <input type="text" id="searchInput" placeholder="输入虫洞星系编号（如 J100033 或 100033）" 
                           class="w-full bg-white/10 border border-white/20 rounded-lg px-4 py-3 text-white outline-none focus:border-blue-400" 
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
            @if($searchError ?? false)
                <div class="wh-section border-red-500/30 bg-red-500/10">
                    <div class="flex items-center gap-3 text-red-300">
                        <span class="text-xl">⚠️</span>
                        <span>{{ $searchError }}</span>
                    </div>
                </div>
            @endif
            @if($systemInfo)
                @include('wormhole.partials.system-detail', ['systemInfo' => $systemInfo, 'kills' => $kills ?? null])
            @endif
        </div>
    </div>

    <!-- 标签栏二：虫洞检索 -->
    <div id="content-systems" class="tab-content hidden">
        <div class="wh-section">
            <div class="flex flex-wrap items-center gap-4 mb-4">
                <div class="flex items-center gap-2">
                    <span class="text-sm text-blue-200">等级:</span>
                    <select id="filterClass" class="bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm text-white outline-none focus:border-blue-400" onchange="loadSystemsList()">
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
                    <select id="filterEffect" class="bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm text-white outline-none focus:border-blue-400" onchange="loadSystemsList()">
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
    <div id="content-types" class="tab-content hidden">
        <div class="wh-section">
            <div class="flex items-center gap-4 mb-4">
                <div class="flex items-center gap-2">
                    <span class="text-sm text-blue-200">目的地:</span>
                    <select id="filterDestination" class="bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm text-white outline-none focus:border-blue-400" onchange="loadTypesList()">
                        <option value="">全部</option>
                        <option value="hs">高安全区域</option>
                        <option value="ls">低安全区域</option>
                        <option value="ns">零安全区域</option>
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
var currentSystemPage = 1;
var searchTimeout = null;
var systemsLoaded = false;
var typesLoaded = false;

// 标签切换 - 纯JS，不操控URL参数
function switchTab(tab) {
    document.querySelectorAll('.wh-tab').forEach(function(b) { b.classList.remove('active'); });
    document.getElementById('wh-tab-' + tab).classList.add('active');
    document.getElementById('content-search').classList.add('hidden');
    document.getElementById('content-systems').classList.add('hidden');
    document.getElementById('content-types').classList.add('hidden');
    document.getElementById('content-' + tab).classList.remove('hidden');
    
    // 按需加载数据（首次切换时加载）
    if (tab === 'systems' && !systemsLoaded) {
        systemsLoaded = true;
        loadSystemsList();
    }
    if (tab === 'types' && !typesLoaded) {
        typesLoaded = true;
        loadTypesList();
    }
}

// 搜索系统 - 跳转到详情页（J前缀可选）
function searchSystem() {
    var query = document.getElementById('searchInput').value.trim();
    if (!query) return;
    // 去掉开头的J/j，再统一加回J前缀
    var num = query.toUpperCase().replace(/^J/, '');
    window.location.href = '/wormhole/J' + num;
}

// Enter键搜索
document.getElementById('searchInput').addEventListener('keydown', function(e) {
    var dropdown = document.getElementById('searchDropdown');
    if (e.key === 'Enter' && dropdown.classList.contains('hidden')) {
        searchSystem();
    }
});

// 自动补全
document.getElementById('searchInput').addEventListener('input', function(e) {
    var query = e.target.value.trim();
    var dropdown = document.getElementById('searchDropdown');
    
    clearTimeout(searchTimeout);
    if (query.length < 1) {
        dropdown.classList.add('hidden');
        return;
    }
    
    searchTimeout = setTimeout(function() {
        fetch('/api/public/wormhole/autocomplete?q=' + encodeURIComponent(query))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.length === 0) {
                    dropdown.classList.add('hidden');
                    return;
                }
                
                dropdown.innerHTML = data.map(function(s) {
                    return '<div class="ac-item" data-name="' + s.name + '">' +
                        '<span class="font-medium">' + s.name + '</span>' +
                        '<span class="wh-badge wh-badge-c' + s.class + ' ml-2">C' + s.class + '</span>' +
                        (s.effect_zh ? '<span class="wh-badge wh-badge-effect ml-1">' + s.effect_zh + '</span>' : '') +
                        '</div>';
                }).join('');
                dropdown.classList.remove('hidden');
                
                // 绑定点击事件
                dropdown.querySelectorAll('.ac-item').forEach(function(el) {
                    el.addEventListener('click', function() {
                        document.getElementById('searchInput').value = el.dataset.name;
                        dropdown.classList.add('hidden');
                        searchSystem();
                    });
                });
            });
    }, 300);
});

// 点击外部关闭下拉框
document.addEventListener('click', function(e) {
    var input = document.getElementById('searchInput');
    var dropdown = document.getElementById('searchDropdown');
    if (!input.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.classList.add('hidden');
    }
});

// 加载系统列表
function loadSystemsList(page) {
    page = page || 1;
    currentSystemPage = page;
    var classFilter = document.getElementById('filterClass').value;
    var effectFilter = document.getElementById('filterEffect').value;
    
    var url = '/api/public/wormhole/systems?page=' + page + '&per_page=30';
    if (classFilter) url += '&class=' + classFilter;
    if (effectFilter) url += '&effect=' + effectFilter;
    
    fetch(url).then(function(r) { return r.json(); }).then(function(data) {
        var tbody = document.getElementById('systemsTableBody');
        tbody.innerHTML = data.systems.map(function(s) {
            var statics = s.statics.map(function(st) {
                return '<span class="text-blue-300">' + st.type + '\u2192' + st.destination_zh + '</span>';
            }).join(', ') || '-';
            return '<tr class="border-b border-white/5 hover:bg-white/5">' +
                '<td class="py-3 px-4 font-medium">' + s.name + '</td>' +
                '<td class="py-3 px-4"><span class="wh-badge wh-badge-c' + s.class + '">C' + s.class + '</span></td>' +
                '<td class="py-3 px-4">' + (s.effect_zh || '-') + '</td>' +
                '<td class="py-3 px-4">' + statics + '</td>' +
                '<td class="py-3 px-4"><a href="/wormhole/' + s.name + '" class="text-blue-400 hover:text-blue-300">详情</a></td>' +
                '</tr>';
        }).join('');
        
        // 分页
        var p = data.pagination;
        document.getElementById('systemsInfo').textContent = '共 ' + p.total + ' 条，第 ' + p.page + '/' + p.total_pages + ' 页';
        
        var btns = '';
        if (p.page > 1) btns += '<button onclick="loadSystemsList(' + (p.page - 1) + ')" class="px-3 py-1 bg-white/10 rounded hover:bg-white/20">上一页</button>';
        if (p.page < p.total_pages) btns += '<button onclick="loadSystemsList(' + (p.page + 1) + ')" class="px-3 py-1 bg-white/10 rounded hover:bg-white/20">下一页</button>';
        document.getElementById('systemsPageButtons').innerHTML = btns;
    });
}

// 加载类型列表
function loadTypesList() {
    var dest = document.getElementById('filterDestination').value;
    var url = '/api/public/wormhole/types';
    if (dest) url += '?destination=' + dest;
    
    fetch(url).then(function(r) { return r.json(); }).then(function(data) {
        var tbody = document.getElementById('typesTableBody');
        tbody.innerHTML = data.types.map(function(t) {
            return '<tr class="border-b border-white/5 hover:bg-white/5">' +
                '<td class="py-3 px-4 font-medium text-blue-300">' + t.type + '</td>' +
                '<td class="py-3 px-4">' + t.destination_zh + '</td>' +
                '<td class="py-3 px-4">' + t.lifetime_hours + '小时</td>' +
                '<td class="py-3 px-4">' + formatMass(t.max_mass) + '</td>' +
                '<td class="py-3 px-4">' + formatMass(t.jump_mass) + '</td>' +
                '</tr>';
        }).join('');
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
</script>
@endpush
