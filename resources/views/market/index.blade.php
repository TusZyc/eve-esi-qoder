@extends('layouts.app')

@section('title', '市场中心 - ' . config('app.name'))

@push('head-scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endpush

@push('styles')
    .my-order {
        background: rgba(234, 179, 8, 0.1) !important;
        border-left: 3px solid #eab308 !important;
    }
    .market-tree-item {
        cursor: pointer;
        transition: all 0.15s;
    }
    .market-tree-item:hover {
        background: rgba(255,255,255,0.05);
    }
    .market-tree-item.selected {
        background: rgba(59, 130, 246, 0.2);
        border-left: 2px solid #3b82f6;
    }
    .tree-children {
        margin-left: 1rem;
        border-left: 1px solid rgba(255,255,255,0.1);
    }
    .tab-btn { transition: all 0.2s; }
    .tab-btn.active {
        background: rgba(59, 130, 246, 0.3);
        border-bottom: 2px solid #3b82f6;
    }
    .order-row:hover { background: rgba(255,255,255,0.05); }
    .region-item { cursor: pointer; transition: all 0.15s; }
    .region-item:hover { background: rgba(255,255,255,0.08); }
    .region-item.selected {
        background: rgba(59, 130, 246, 0.2);
        border-left: 2px solid #3b82f6;
    }
    .type-item { cursor: pointer; transition: all 0.15s; }
    .type-item:hover { background: rgba(255,255,255,0.08); }
    .type-item.selected {
        background: rgba(59, 130, 246, 0.15);
        color: #93c5fd;
    }
    .type-item.filtered-out {
        display: none;
    }
    .type-item.search-match {
        background: rgba(34, 197, 94, 0.15);
        color: #86efac;
    }
    .tree-node.filtered-out {
        display: none;
    }
    .search-result-item { cursor: pointer; transition: all 0.15s; }
    .search-result-item:hover { background: rgba(255,255,255,0.08); }
    .panel-scroll::-webkit-scrollbar { width: 4px; }
    .panel-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 2px; }
    .panel-scroll::-webkit-scrollbar-track { background: transparent; }
    .filter-status {
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 4px;
        background: rgba(59, 130, 246, 0.2);
        color: #93c5fd;
    }
@endpush

@section('content')
    <!-- 标签切换（仅登录用户显示我的订单） -->
    <div class="container mx-auto px-4 mt-4">
        <div class="flex border-b border-white/10">
            <button id="tab-browse" class="tab-btn active px-6 py-3 text-sm font-medium" onclick="switchTab('browse')">
                📊 市场浏览
            </button>
            @if($isLoggedIn)
                <button id="tab-myorders" class="tab-btn px-6 py-3 text-sm font-medium" onclick="switchTab('myorders')">
                    📋 我的订单
                </button>
            @endif
        </div>
    </div>

    <!-- 市场浏览标签内容 -->
    <div id="content-browse" class="container mx-auto px-4 py-4">
        <!-- 搜索框（全宽） -->
        <div class="mb-4">
            <div class="relative">
                <input type="text" id="type-search" placeholder="搜索物品名称..."
                    class="w-full bg-white/10 border border-white/20 rounded-lg px-4 py-3 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 placeholder-blue-300/50">
                <span id="search-indicator" class="absolute right-3 top-1/2 -translate-y-1/2 text-blue-300/50 text-xs hidden">搜索中...</span>
            </div>
        </div>

        <!-- 三栏布局 -->
        <div class="flex gap-4" style="height: calc(100vh - 220px);">
            <!-- 左栏：区域列表 -->
            <div class="w-44 flex-shrink-0">
                <div class="bg-white/10 backdrop-blur-lg rounded-xl p-3 eve-glow h-full flex flex-col">
                    <h3 class="text-xs font-medium text-blue-300 mb-2">区域选择 <span id="current-region-debug" class="text-[9px] text-blue-300/50"></span></h3>
                    <div id="region-list" class="flex-1 overflow-y-auto panel-scroll space-y-0.5">
                        <div class="skeleton h-6 w-full mb-1"></div>
                        <div class="skeleton h-6 w-3/4 mb-1"></div>
                        <div class="skeleton h-6 w-5/6"></div>
                    </div>
                </div>
            </div>

            <!-- 中栏：市场分类树 -->
            <div class="w-72 flex-shrink-0">
                <div class="bg-white/10 backdrop-blur-lg rounded-xl p-3 eve-glow h-full flex flex-col">
                    <div class="flex items-center justify-between mb-2">
                        <h3 id="middle-panel-title" class="text-xs font-medium text-blue-300">市场分类</h3>
                        <div class="flex items-center gap-2">
                            <button id="toggle-all-btn" class="text-[10px] px-2 py-1 rounded bg-white/10 hover:bg-white/20 text-blue-300/70 hover:text-blue-300 transition whitespace-nowrap" onclick="toggleAllCategories()" title="折叠/展开所有分类">
                                折叠全部
                            </button>
                            <label id="order-filter-checkbox" class="flex items-center gap-1 cursor-pointer">
                                <input type="checkbox" id="only-with-orders" class="w-3 h-3 accent-blue-500" onchange="onOrderFilterChange()">
                                <span class="text-[10px] text-blue-300/70 whitespace-nowrap">仅有订单</span>
                            </label>
                        </div>
                    </div>
                    <!-- 分类树 -->
                    <div id="market-tree-container" class="flex-1 overflow-y-auto panel-scroll">
                        <div id="market-tree">
                            <div class="space-y-2">
                                <div class="skeleton h-6 w-full"></div>
                                <div class="skeleton h-6 w-3/4"></div>
                                <div class="skeleton h-6 w-5/6"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 右栏：物品详情 + 订单 -->
            <div class="flex-1 flex flex-col">
                <!-- 顶部：物品信息卡（与中栏标题对齐） -->
                <div class="flex items-center justify-between mb-2 h-6">
                    <div id="type-info-card" class="hidden">
                        <div class="flex items-center gap-3">
                            <h2 id="type-name" class="text-sm font-bold text-blue-300">-</h2>
                            <span id="type-category" class="text-xs text-blue-400/60">-</span>
                        </div>
                    </div>
                </div>
                <!-- 内容区域 -->
                <div id="right-content" class="flex-1 overflow-y-auto panel-scroll space-y-4">
                    <!-- 空状态提示 -->
                    <div id="empty-state" class="bg-white/10 backdrop-blur-lg rounded-xl p-12 eve-glow text-center">
                        <div class="text-4xl mb-4">📦</div>
                        <p class="text-blue-300/70">请在左侧选择物品查看市场订单</p>
                        <p class="text-blue-300/40 text-xs mt-2">可通过顶部搜索框快速查找物品</p>
                    </div>

                    <!-- 出售订单 -->
                <div id="sell-orders-card" class="bg-white/10 backdrop-blur-lg rounded-xl p-4 eve-glow hidden">
                    <h3 class="text-base font-semibold text-green-400 mb-3">💰 出售订单</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-blue-300/70 border-b border-white/10 text-xs">
                                    <th class="text-left py-2 px-3">价格 (ISK)</th>
                                    <th class="text-left py-2 px-3">数量</th>
                                    <th class="text-left py-2 px-3">位置</th>
                                    <th class="text-left py-2 px-3">到期时间</th>
                                </tr>
                            </thead>
                            <tbody id="sell-orders-body">
                                <tr><td colspan="4" class="text-center py-4"><span class="skeleton inline-block h-4 w-16"></span></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 求购订单 -->
                <div id="buy-orders-card" class="bg-white/10 backdrop-blur-lg rounded-xl p-4 eve-glow hidden">
                    <h3 class="text-base font-semibold text-yellow-400 mb-3">📋 求购订单</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-blue-300/70 border-b border-white/10 text-xs">
                                    <th class="text-left py-2 px-3">价格 (ISK)</th>
                                    <th class="text-left py-2 px-3">数量</th>
                                    <th class="text-left py-2 px-3">位置</th>
                                    <th class="text-left py-2 px-3">到期时间</th>
                                </tr>
                            </thead>
                            <tbody id="buy-orders-body">
                                <tr><td colspan="4" class="text-center py-4"><span class="skeleton inline-block h-4 w-16"></span></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 价格历史图表 -->
                <div id="price-chart-container" class="bg-white/10 backdrop-blur-lg rounded-xl p-4 eve-glow hidden">
                    <h3 class="text-base font-semibold text-purple-400 mb-3">📈 价格历史（30天）</h3>
                    <div class="h-64">
                        <canvas id="price-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- 我的订单标签内容 -->
    <div id="content-myorders" class="container mx-auto px-4 py-4 hidden">
            <div class="bg-white/10 backdrop-blur-lg rounded-xl p-4 eve-glow">
                <div class="flex gap-2 mb-4">
                    <button class="order-filter active bg-blue-600/30 px-4 py-2 rounded-lg text-sm" data-filter="all" onclick="filterOrders('all')">全部</button>
                    <button class="order-filter bg-white/10 px-4 py-2 rounded-lg text-sm" data-filter="sell" onclick="filterOrders('sell')">出售中</button>
                    <button class="order-filter bg-white/10 px-4 py-2 rounded-lg text-sm" data-filter="buy" onclick="filterOrders('buy')">求购中</button>
                </div>
                <div id="my-orders-content" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-blue-300 border-b border-white/10">
                                <th class="text-left py-2 px-2">物品</th>
                                <th class="text-center py-2 px-2">类型</th>
                                <th class="text-right py-2 px-2">价格</th>
                                <th class="text-right py-2 px-2">数量</th>
                                <th class="text-left py-2 px-2">位置</th>
                                <th class="text-right py-2 px-2">到期</th>
                            </tr>
                        </thead>
                        <tbody id="my-orders-body">
                            <tr><td colspan="6" class="text-center py-8 text-blue-300/50">点击加载...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // ==================== 全局状态 ====================
    const FORGE_REGION_ID = 10000002;
    let currentRegion = FORGE_REGION_ID;
    let currentTypeId = null;
    let currentTypeCategory = '';
    let myOrderIds = [];
    let allMyOrders = [];
    let marketGroups = [];
    let priceChart = null;
    let activeTypeIds = new Set();

    // 订单分页状态
    let currentSellOrders = [];
    let currentBuyOrders = [];
    let sellOrdersExpanded = false;
    let buyOrdersExpanded = false;
    const ORDER_PAGE_SIZE = 10;

    // 筛选状态
    let searchKeyword = '';
    let searchResultIds = new Set();  // 搜索结果 type_id 集合
    let searchResultMap = {};  // 搜索结果详情 {id: {name, category}}
    let onlyWithOrders = false;

    // 分组名称映射
    let marketGroupNameMap = {};

    // API 端点
    const API = {
        groups: '{{ route("api.public.market.groups") }}',
        search: '{{ route("api.public.market.search") }}',
        regions: '{{ route("api.public.market.regions") }}',
        activeTypes: '{{ route("api.public.market.active-types") }}',
        orders: '{{ route("api.public.market.orders") }}',
        history: '{{ route("api.public.market.history") }}',
        typeDetail: function(id) { return '/api/public/market/types/' + id; },
        @if($isLoggedIn)
        myOrderIds: '{{ route("api.market.my-order-ids") }}',
        characterOrders: '{{ route("api.market.character-orders") }}',
        @endif
    };

    const isLoggedIn = {{ $isLoggedIn ? 'true' : 'false' }};

    // ==================== 工具函数 ====================
    function formatNumber(num) {
        if (!num && num !== 0) return '-';
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    function formatPrice(num) {
        if (!num && num !== 0) return '-';
        return num.toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatExpires(dateStr) {
        if (!dateStr) return '-';
        const expires = new Date(dateStr);
        const now = new Date();
        const diff = expires - now;
        if (diff <= 0) return '已过期';
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        if (days > 0) return days + '天' + hours + '小时';
        return hours + '小时';
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function startsWithChinese(str) {
        if (!str) return false;
        const code = str.charCodeAt(0);
        return code >= 0x4E00 && code <= 0x9FFF;
    }

    // ==================== 标签切换 ====================
    function switchTab(tab) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
        document.getElementById('content-browse').classList.toggle('hidden', tab !== 'browse');
        document.getElementById('content-myorders').classList.toggle('hidden', tab !== 'myorders');
        if (tab === 'myorders') loadMyOrders();
    }

    // ==================== 区域列表 ====================
    async function loadRegions() {
        try {
            const resp = await fetch(API.regions);
            const result = await resp.json();
            if (result.success) {
                renderRegionList(result.data);
            }
        } catch (e) {
            console.error('加载星域列表失败:', e);
            document.getElementById('region-list').innerHTML = '<div class="text-xs text-red-400">加载失败</div>';
        }
    }

    function renderRegionList(regions) {
        const container = document.getElementById('region-list');
        const cnRegions = regions.filter(r => startsWithChinese(r.name));
        const otherRegions = regions.filter(r => !startsWithChinese(r.name));
        cnRegions.sort((a, b) => a.name.localeCompare(b.name, 'zh'));
        otherRegions.sort((a, b) => a.name.localeCompare(b.name));
        const sortedRegions = [...cnRegions, ...otherRegions];

        let html = '';
        html += '<div class="region-item selected rounded px-2 py-1.5 text-sm font-medium text-green-300 border-b border-white/10 mb-0.5" data-region="' + FORGE_REGION_ID + '" onclick="selectRegion(' + FORGE_REGION_ID + ', this)">';
        html += '⭐ 伏尔戈（吉他）';
        html += '</div>';
        html += '<div class="region-item rounded px-2 py-1.5 text-sm font-medium text-yellow-300 border-b border-white/10 mb-1" data-region="all" onclick="selectRegion(\'all\', this)">';
        html += '🌌 全部';
        html += '</div>';

        sortedRegions.forEach(r => {
            const isForge = r.id === FORGE_REGION_ID;
            html += '<div class="region-item rounded px-2 py-1.5 text-xs' + (isForge ? ' text-green-300/70' : '') + '" data-region="' + r.id + '" onclick="selectRegion(' + r.id + ', this)">';
            html += escapeHtml(r.name);
            html += '</div>';
        });

        container.innerHTML = html;
    }

    function selectRegion(regionId, el) {
        currentRegion = regionId;
        document.querySelectorAll('.region-item').forEach(e => e.classList.remove('selected'));
        if (el) el.classList.add('selected');

        // 更新调试显示
        const debugEl = document.getElementById('current-region-debug');
        if (debugEl) {
            debugEl.textContent = '(ID: ' + regionId + ')';
        }

        // 加载该区域的活跃物品类型，然后重新应用筛选
        loadActiveTypes().then(() => {
            applyFilters();
            // 如果有选中的物品，重新加载订单
            if (currentTypeId) {
                loadOrders();
                loadHistory();
            }
        });
    }

    // ==================== 活跃物品类型（有订单的物品） ====================
    async function loadActiveTypes() {
        try {
            console.log('加载活跃物品类型，区域:', currentRegion);
            const resp = await fetch(API.activeTypes + '?region_id=' + currentRegion);
            const result = await resp.json();
            if (result.success) {
                activeTypeIds = new Set(result.data);
                console.log('活跃物品类型数量:', activeTypeIds.size);
                if (activeTypeIds.size > 0) {
                    console.log('前10个活跃物品ID:', [...activeTypeIds].slice(0, 10));
                }
            } else {
                console.error('获取活跃物品类型失败:', result.message);
                activeTypeIds = new Set();
            }
        } catch (e) {
            console.error('加载活跃物品类型失败:', e);
            activeTypeIds = new Set();
        }
    }

    // ==================== 市场分组树 ====================
    let marketGroupsRetryCount = 0;
    const MAX_RETRY = 3;

    async function loadMarketGroups() {
        try {
            const resp = await fetch(API.groups);
            const result = await resp.json();
            if (result.success && result.data && result.data.length > 0) {
                marketGroups = result.data;
                buildGroupNameMap(marketGroups, '');
                renderMarketTree();
                marketGroupsRetryCount = 0; // 成功后重置重试计数
            } else {
                // 数据为空，可能正在构建中
                marketGroupsRetryCount++;
                if (marketGroupsRetryCount < MAX_RETRY) {
                    document.getElementById('market-tree').innerHTML =
                        '<div class="text-xs text-blue-300/50 text-center py-4">' +
                        '<div class="animate-pulse mb-2">⏳</div>' +
                        '分类数据加载中...<br>' +
                        '<span class="text-[10px] text-blue-300/30">' + marketGroupsRetryCount + '/' + MAX_RETRY + ' 重试中，请稍候</span>' +
                        '</div>';
                    // 10 秒后重试
                    setTimeout(loadMarketGroups, 10000);
                } else {
                    document.getElementById('market-tree').innerHTML =
                        '<div class="text-xs text-yellow-400/70 text-center py-4">' +
                        '<div class="mb-2">⚠️</div>' +
                        '分类数据暂时不可用<br>' +
                        '<span class="text-[10px] text-blue-300/40">可能正在构建缓存，请稍后刷新页面</span>' +
                        '<button onclick="marketGroupsRetryCount=0;loadMarketGroups()" class="mt-2 block mx-auto text-[10px] px-2 py-1 bg-white/10 rounded hover:bg-white/20 transition">点击重试</button>' +
                        '</div>';
                }
            }
        } catch (e) {
            console.error('加载市场分组失败:', e);
            marketGroupsRetryCount++;
            if (marketGroupsRetryCount < MAX_RETRY) {
                document.getElementById('market-tree').innerHTML =
                    '<div class="text-xs text-blue-300/50 text-center py-4">' +
                    '<div class="animate-pulse mb-2">⏳</div>' +
                    '网络错误，重试中...<br>' +
                    '<span class="text-[10px] text-blue-300/30">' + marketGroupsRetryCount + '/' + MAX_RETRY + '</span>' +
                    '</div>';
                setTimeout(loadMarketGroups, 5000);
            } else {
                document.getElementById('market-tree').innerHTML =
                    '<div class="text-xs text-red-400 text-center py-4">' +
                    '<div class="mb-2">❌</div>' +
                    '加载失败<br>' +
                    '<span class="text-[10px] text-blue-300/40">' + e.message + '</span>' +
                    '<button onclick="marketGroupsRetryCount=0;loadMarketGroups()" class="mt-2 block mx-auto text-[10px] px-2 py-1 bg-white/10 rounded hover:bg-white/20 transition">点击重试</button>' +
                    '</div>';
            }
        }
    }

    function buildGroupNameMap(nodes, parentPath) {
        if (!nodes) return;
        nodes.forEach(node => {
            const currentPath = parentPath ? (parentPath + ' > ' + node.name) : node.name;
            marketGroupNameMap[node.id] = currentPath;
            if (node.types && node.types.length > 0) {
                node.types.forEach(t => {
                    const typeId = typeof t === 'object' ? t.id : t;
                    marketGroupNameMap[typeId] = currentPath;
                });
            }
            if (node.children) {
                buildGroupNameMap(node.children, currentPath);
            }
        });
    }

    function renderMarketTree() {
        const container = document.getElementById('market-tree');
        container.innerHTML = renderTreeNodes(marketGroups);
        // 渲染后应用筛选
        applyFilters();
    }

    function renderTreeNodes(nodes) {
        if (!nodes || !nodes.length) return '';
        let html = '';
        nodes.forEach(node => {
            const hasChildren = node.children && node.children.length > 0;
            const hasTypes = node.types && node.types.length > 0;
            const isExpandable = hasChildren || hasTypes;

            html += '<div class="tree-node" data-id="' + node.id + '">';
            html += '<div class="market-tree-item flex items-center gap-1 py-1 px-2 rounded text-xs" onclick="handleTreeClick(' + node.id + ', event)">';

            if (isExpandable) {
                html += '<span class="tree-toggle text-blue-400 flex-shrink-0" style="font-size:10px;">▶</span>';
            } else {
                html += '<span class="text-blue-400/30 flex-shrink-0" style="font-size:8px;">●</span>';
            }
            html += '<span class="truncate">' + escapeHtml(node.name) + '</span>';
            html += '</div>';

            if (hasChildren || hasTypes) {
                html += '<div class="tree-children hidden">';
                if (hasChildren) {
                    html += renderTreeNodes(node.children);
                }
                if (hasTypes) {
                    node.types.forEach(t => {
                        const typeId = typeof t === 'object' ? t.id : t;
                        const typeName = typeof t === 'object' ? t.name : ('物品#' + t);
                        html += '<div class="type-item py-1 px-2 rounded text-xs truncate" data-typeid="' + typeId + '" onclick="selectType(' + typeId + ', event)" title="' + escapeHtml(typeName) + '">';
                        html += escapeHtml(typeName);
                        html += '</div>';
                    });
                }
                html += '</div>';
            }

            html += '</div>';
        });
        return html;
    }

    function handleTreeClick(id, event) {
        event.stopPropagation();
        const treeItem = event.currentTarget;
        const node = treeItem.closest('.tree-node');
        const childrenContainer = node.querySelector(':scope > .tree-children');

        if (childrenContainer) {
            childrenContainer.classList.toggle('hidden');
            const toggle = treeItem.querySelector('.tree-toggle');
            if (toggle) {
                toggle.textContent = childrenContainer.classList.contains('hidden') ? '▶' : '▼';
            }
        }
    }

    // ==================== 核心筛选逻辑 ====================
    /**
     * 统一的筛选函数
     * 规则：
     * 1. 如果有搜索词，只显示搜索结果中的物品
     * 2. 如果勾选"仅有订单"，只显示当前区域有订单的物品
     * 3. 两个条件同时满足时，取交集
     * 4. 显示匹配物品及其父分类，隐藏其他
     */
    function applyFilters() {
        console.log('applyFilters 被调用, onlyWithOrders:', onlyWithOrders, 'activeTypeIds.size:', activeTypeIds.size, 'currentRegion:', currentRegion);

        // 清除之前的筛选状态
        document.querySelectorAll('.type-item.filtered-out, .type-item.search-match').forEach(el => {
            el.classList.remove('filtered-out', 'search-match');
        });
        document.querySelectorAll('.tree-node.filtered-out').forEach(el => {
            el.classList.remove('filtered-out');
        });

        // 收集应该显示的 type_id
        let visibleTypeIds = null;  // null 表示显示全部

        // 搜索筛选
        if (searchKeyword && searchResultIds.size > 0) {
            visibleTypeIds = new Set(searchResultIds);
        }

        // "仅有订单"筛选
        if (onlyWithOrders) {
            if (activeTypeIds.size === 0) {
                console.warn('勾选了仅有订单，但 activeTypeIds 为空，可能正在加载或数据不存在');
            }
            if (activeTypeIds.size > 0) {
                if (visibleTypeIds) {
                    // 取交集
                    visibleTypeIds = new Set([...visibleTypeIds].filter(id => activeTypeIds.has(id)));
                } else {
                    visibleTypeIds = new Set(activeTypeIds);
                }
            }
        }

        console.log('筛选结果: visibleTypeIds 数量 =', visibleTypeIds ? visibleTypeIds.size : '显示全部');

        // 更新标题
        updatePanelTitle(visibleTypeIds);

        // 如果没有任何筛选条件，显示全部
        if (visibleTypeIds === null) {
            return;
        }

        // 应用筛选：标记匹配的物品，隐藏不匹配的
        const matchedTypeIds = new Set();

        document.querySelectorAll('.type-item[data-typeid]').forEach(el => {
            const tid = parseInt(el.dataset.typeid);
            if (visibleTypeIds.has(tid)) {
                el.classList.add('search-match');
                matchedTypeIds.add(tid);
                // 展开父节点
                let parent = el.closest('.tree-children');
                while (parent) {
                    parent.classList.remove('hidden');
                    const toggle = parent.previousElementSibling?.querySelector('.tree-toggle');
                    if (toggle) toggle.textContent = '▼';
                    parent = parent.parentElement?.closest('.tree-children');
                }
            } else {
                el.classList.add('filtered-out');
            }
        });

        // 收集应该显示的分类节点
        const visibleNodes = new Set();
        document.querySelectorAll('.type-item.search-match').forEach(item => {
            let node = item.closest('.tree-node');
            while (node) {
                visibleNodes.add(node);
                node = node.parentElement?.closest('.tree-node');
            }
        });

        // 隐藏不包含匹配物品的分类
        document.querySelectorAll('.tree-node').forEach(node => {
            if (!visibleNodes.has(node)) {
                node.classList.add('filtered-out');
            }
        });
    }

    function updatePanelTitle(visibleTypeIds) {
        const title = document.getElementById('middle-panel-title');
        let text = '市场分类';
        let count = '';
        
        if (searchKeyword) {
            text = '搜索: ' + searchKeyword;
            count = visibleTypeIds ? ' (' + visibleTypeIds.size + ')' : ' (' + searchResultIds.size + ')';
        }
        
        if (onlyWithOrders) {
            text += ' [有订单]';
        }
        
        title.textContent = text + count;
    }

    // ==================== 搜索功能 ====================
    let searchTimer = null;

    function setupSearch() {
        const input = document.getElementById('type-search');
        input.addEventListener('input', function(e) {
            clearTimeout(searchTimer);
            const keyword = e.target.value.trim();

            if (keyword.length < 1) {
                // 清空搜索
                searchKeyword = '';
                searchResultIds = new Set();
                searchResultMap = {};
                document.getElementById('search-indicator').classList.add('hidden');
                applyFilters();
                return;
            }

            document.getElementById('search-indicator').classList.remove('hidden');
            searchTimer = setTimeout(() => performSearch(keyword), 300);
        });
    }

    async function performSearch(keyword) {
        try {
            const resp = await fetch(API.search + '?q=' + encodeURIComponent(keyword));
            const result = await resp.json();
            document.getElementById('search-indicator').classList.add('hidden');

            if (result.success) {
                searchKeyword = keyword;
                searchResultIds = new Set(result.data.map(r => r.id));
                searchResultMap = {};
                result.data.forEach(r => {
                    searchResultMap[r.id] = r;
                });
                applyFilters();
            }
        } catch (e) {
            console.error('搜索失败:', e);
            document.getElementById('search-indicator').classList.add('hidden');
        }
    }

    // "仅有订单"复选框变化
    function onOrderFilterChange() {
        onlyWithOrders = document.getElementById('only-with-orders').checked;
        applyFilters();
    }

    // ==================== 折叠/展开所有分类 ====================
    let allCategoriesExpanded = true;

    function toggleAllCategories() {
        const btn = document.getElementById('toggle-all-btn');
        const allChildrenContainers = document.querySelectorAll('.tree-children');
        const allToggles = document.querySelectorAll('.tree-toggle');

        if (allCategoriesExpanded) {
            // 折叠所有
            allChildrenContainers.forEach(container => {
                container.classList.add('hidden');
            });
            allToggles.forEach(toggle => {
                toggle.textContent = '▶';
            });
            btn.textContent = '展开全部';
            allCategoriesExpanded = false;
        } else {
            // 展开所有
            allChildrenContainers.forEach(container => {
                container.classList.remove('hidden');
            });
            allToggles.forEach(toggle => {
                toggle.textContent = '▼';
            });
            btn.textContent = '折叠全部';
            allCategoriesExpanded = true;
        }
    }

    // ==================== 物品选择 ====================
    async function selectType(typeId, event, category) {
        if (event) event.stopPropagation();
        currentTypeId = typeId;
        currentTypeCategory = category || searchResultMap[typeId]?.category || '';

        document.querySelectorAll('.type-item').forEach(el => el.classList.remove('selected'));
        const clicked = event ? event.currentTarget : document.querySelector('[data-typeid="' + typeId + '"]');
        if (clicked) clicked.classList.add('selected');

        document.getElementById('empty-state').classList.add('hidden');
        document.getElementById('type-info-card').classList.remove('hidden');
        document.getElementById('sell-orders-card').classList.remove('hidden');
        document.getElementById('buy-orders-card').classList.remove('hidden');

        // 加载物品详情
        try {
            const resp = await fetch(API.typeDetail(typeId));
            const result = await resp.json();
            if (result.success) {
                document.getElementById('type-name').textContent = result.data.name || '-';
                if (currentTypeCategory) {
                    document.getElementById('type-category').textContent = currentTypeCategory;
                } else if (marketGroupNameMap[typeId]) {
                    document.getElementById('type-category').textContent = marketGroupNameMap[typeId];
                } else if (result.data.market_group_id && marketGroupNameMap[result.data.market_group_id]) {
                    document.getElementById('type-category').textContent = marketGroupNameMap[result.data.market_group_id];
                } else {
                    document.getElementById('type-category').textContent = '';
                }
            }
        } catch (e) {
            console.error('加载物品详情失败:', e);
        }

        loadOrders();
        loadHistory();
    }

    // ==================== 订单加载 ====================
    async function loadOrders() {
        if (!currentTypeId) return;

        console.log('加载订单: type_id=' + currentTypeId + ', region_id=' + currentRegion);
        console.log('activeTypeIds 包含该物品?', activeTypeIds.has(currentTypeId));

        sellOrdersExpanded = false;
        buyOrdersExpanded = false;

        document.getElementById('sell-orders-body').innerHTML = '<tr><td colspan="4" class="text-center py-4"><span class="skeleton inline-block h-4 w-24"></span></td></tr>';
        document.getElementById('buy-orders-body').innerHTML = '<tr><td colspan="4" class="text-center py-4"><span class="skeleton inline-block h-4 w-24"></span></td></tr>';

        try {
            const resp = await fetch(API.orders + '?region_id=' + currentRegion + '&type_id=' + currentTypeId);
            const result = await resp.json();
            if (result.success) {
                currentSellOrders = result.data.sell || [];
                currentBuyOrders = result.data.buy || [];

                console.log('订单加载完成: 出售=' + currentSellOrders.length + ', 求购=' + currentBuyOrders.length);

                // 注：如果物品在activeTypeIds中但订单为空，可能是00区域无NPC空间站的情况
                // renderOrders会显示相应提示，无需特殊处理

                renderOrders(currentSellOrders, 'sell');
                renderOrders(currentBuyOrders, 'buy');
            }
        } catch (e) {
            console.error('加载订单失败:', e);
            document.getElementById('sell-orders-body').innerHTML = '<tr><td colspan="4" class="text-center py-4 text-red-400">加载失败</td></tr>';
            document.getElementById('buy-orders-body').innerHTML = '<tr><td colspan="4" class="text-center py-4 text-red-400">加载失败</td></tr>';
        }
    }

    function renderOrders(orders, type) {
        const tbody = document.getElementById(type + '-orders-body');
        if (!orders || orders.length === 0) {
            // 判断是否可能是00区域无NPC空间站的情况
            // 条件：物品在activeTypeIds中（说明ESI认为有订单），但实际获取不到订单
            if (activeTypeIds.has(currentTypeId)) {
                // 该物品在活跃列表中，说明区域内有此物品的交易，但ESI无法返回订单
                // 这通常发生在00区域，订单位于玩家建筑中，ESI公开API无法查询
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4">' +
                    '<div class="text-yellow-400/80 text-sm mb-1">📡 无法获取订单数据</div>' +
                    '<div class="text-blue-300/50 text-xs">该物品在此区域有交易，但订单可能位于玩家建筑中。<br>ESI公开API无法查询玩家建筑的订单，请通过游戏内市场查看。</div>' +
                    '</td></tr>';
            } else {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-blue-300/50">暂无订单</td></tr>';
            }
            return;
        }

        const expanded = type === 'sell' ? sellOrdersExpanded : buyOrdersExpanded;
        const displayOrders = expanded ? orders : orders.slice(0, ORDER_PAGE_SIZE);

        let html = '';
        displayOrders.forEach(order => {
            const isMyOrder = myOrderIds.includes(order.order_id);
            const rowClass = isMyOrder ? 'order-row my-order' : 'order-row';
            html += '<tr class="' + rowClass + ' border-b border-white/5">';
            html += '<td class="py-2 px-3 text-' + (type === 'sell' ? 'green' : 'yellow') + '-400 whitespace-nowrap">' + formatPrice(order.price) + '</td>';
            html += '<td class="py-2 px-3 text-left whitespace-nowrap">' + formatNumber(order.volume_remain) + '</td>';
            html += '<td class="py-2 px-3 text-blue-300 text-xs truncate max-w-[200px]" title="' + escapeHtml(order.location_name || '') + '">' + escapeHtml(order.location_name || String(order.location_id)) + '</td>';
            html += '<td class="py-2 px-3 text-left text-xs text-blue-300/70 whitespace-nowrap">' + formatExpires(order.expires) + '</td>';
            html += '</tr>';
        });

        if (orders.length > ORDER_PAGE_SIZE) {
            if (expanded) {
                html += '<tr><td colspan="4" class="text-center py-2">';
                html += '<button class="text-blue-400 hover:text-blue-300 text-xs px-3 py-1 rounded bg-white/5 hover:bg-white/10 transition" onclick="toggleOrderExpand(\'' + type + '\', false)">折叠 ▲</button>';
                html += '</td></tr>';
            } else {
                const remaining = orders.length - ORDER_PAGE_SIZE;
                html += '<tr><td colspan="4" class="text-center py-2">';
                html += '<button class="text-blue-400 hover:text-blue-300 text-xs px-3 py-1 rounded bg-white/5 hover:bg-white/10 transition" onclick="toggleOrderExpand(\'' + type + '\', true)">加载更多 (' + remaining + ' 条) ▼</button>';
                html += '</td></tr>';
            }
        }

        tbody.innerHTML = html;
    }

    function toggleOrderExpand(type, expand) {
        if (type === 'sell') {
            sellOrdersExpanded = expand;
            renderOrders(currentSellOrders, 'sell');
        } else {
            buyOrdersExpanded = expand;
            renderOrders(currentBuyOrders, 'buy');
        }
    }

    // ==================== 价格历史 ====================
    async function loadHistory() {
        if (!currentTypeId) return;

        try {
            const resp = await fetch(API.history + '?region_id=' + currentRegion + '&type_id=' + currentTypeId);
            const result = await resp.json();
            if (result.success && result.data && result.data.length > 0) {
                renderChart(result.data.slice(-30));
            } else {
                document.getElementById('price-chart-container').classList.add('hidden');
            }
        } catch (e) {
            console.error('加载价格历史失败:', e);
            document.getElementById('price-chart-container').classList.add('hidden');
        }
    }

    function renderChart(data) {
        const container = document.getElementById('price-chart-container');
        container.classList.remove('hidden');
        const ctx = document.getElementById('price-chart').getContext('2d');

        if (priceChart) priceChart.destroy();

        const labels = data.map(d => {
            const date = new Date(d.date);
            return (date.getMonth() + 1) + '/' + date.getDate();
        });
        const sellPrices = data.map(d => d.highest);
        const buyPrices = data.map(d => d.lowest);

        priceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: '出售订单（最高价）',
                        data: sellPrices,
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        fill: false,
                        tension: 0.3,
                        pointRadius: 2,
                        pointHoverRadius: 4
                    },
                    {
                        label: '求购订单（最低价）',
                        data: buyPrices,
                        borderColor: '#eab308',
                        backgroundColor: 'rgba(234, 179, 8, 0.1)',
                        fill: false,
                        tension: 0.3,
                        pointRadius: 2,
                        pointHoverRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: { color: 'rgba(255,255,255,0.6)', font: { size: 11 }, boxWidth: 12, padding: 15 }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleColor: '#fff',
                        bodyColor: 'rgba(255,255,255,0.8)',
                        borderColor: 'rgba(255,255,255,0.1)',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y.toLocaleString('zh-CN', {minimumFractionDigits: 2}) + ' ISK';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: { color: 'rgba(255,255,255,0.5)', font: { size: 10 } }
                    },
                    y: {
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: {
                            color: 'rgba(255,255,255,0.5)',
                            font: { size: 10 },
                            callback: function(value) {
                                if (value >= 1000000) return (value / 1000000).toFixed(1) + 'M';
                                if (value >= 1000) return (value / 1000).toFixed(1) + 'K';
                                return value;
                            }
                        }
                    }
                }
            }
        });
    }

    // ==================== 我的订单 ====================
    async function loadMyOrders() {
        console.log('loadMyOrders called, isLoggedIn:', isLoggedIn, 'API.characterOrders:', API.characterOrders);
        
        if (!isLoggedIn || !API.characterOrders) {
            document.getElementById('my-orders-body').innerHTML = '<tr><td colspan="6" class="text-center py-8 text-yellow-400/80">' +
                '<div class="flex flex-col items-center gap-2">' +
                '<div class="text-4xl">🔐</div>' +
                '<div>请先登录查看订单</div>' +
                '</div></td></tr>';
            return;
        }
        
        document.getElementById('my-orders-body').innerHTML = '<tr><td colspan="6" class="text-center py-8 text-blue-300/50">加载中...</td></tr>';
        try {
            const resp = await fetch(API.characterOrders, {
                credentials: 'same-origin',  // 确保 session cookie 被发送
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            console.log('API response status:', resp.status);
            
            if (resp.status === 401) {
                document.getElementById('my-orders-body').innerHTML = '<tr><td colspan="6" class="text-center py-8 text-yellow-400/80">' +
                    '<div class="flex flex-col items-center gap-2">' +
                    '<div class="text-4xl">🔐</div>' +
                    '<div>登录已过期，请重新登录</div>' +
                    '</div></td></tr>';
                return;
            }
            
            const result = await resp.json();
            console.log('API response:', result);
            
            if (result.success) {
                allMyOrders = result.data || [];
                renderMyOrders(allMyOrders);
            } else {
                document.getElementById('my-orders-body').innerHTML = '<tr><td colspan="6" class="text-center py-8 text-red-400">' + (result.message || '加载失败') + '</td></tr>';
            }
        } catch (e) {
            console.error('加载角色订单失败:', e);
            document.getElementById('my-orders-body').innerHTML = '<tr><td colspan="6" class="text-center py-8 text-red-400">加载失败: ' + e.message + '</td></tr>';
        }
    }

    function renderMyOrders(orders) {
        console.log('renderMyOrders called with orders:', orders);
        const tbody = document.getElementById('my-orders-body');
        if (!orders || orders.length === 0) {
            console.log('No orders to render');
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-12">' +
                '<div class="flex flex-col items-center gap-2">' +
                '<div class="text-4xl">📋</div>' +
                '<div class="text-blue-300/80 text-base">暂无订单</div>' +
                '<div class="text-blue-300/40 text-xs">您当前没有活跃的市场订单</div>' +
                '</div></td></tr>';
            return;
        }

        let html = '';
        try {
            orders.forEach((order, index) => {
                console.log('Rendering order ' + index + ':', order);
                // ESI API 角色订单中，is_buy_order 表示买卖类型
                const isBuyOrder = order.is_buy_order === true;
                const typeLabel = isBuyOrder ? '求购' : '出售';
                const typeColor = isBuyOrder ? 'text-yellow-400' : 'text-green-400';
                const typeName = order.type_name || ('物品#' + order.type_id);
                // ESI API 返回的字段可能是 volume_total 或 volume_entered
                const volumeTotal = order.volume_total || order.volume_entered || order.volume_remain || 0;
                html += '<tr class="order-row border-b border-white/5">';
                html += '<td class="py-2 px-2">' + escapeHtml(typeName) + '</td>';
                html += '<td class="py-2 px-2 text-center ' + typeColor + '">' + typeLabel + '</td>';
                html += '<td class="py-2 px-2 text-right">' + formatPrice(order.price) + '</td>';
                html += '<td class="py-2 px-2 text-right">' + formatNumber(order.volume_remain) + '/' + formatNumber(volumeTotal) + '</td>';
                html += '<td class="py-2 px-2 text-xs text-blue-300 truncate max-w-xs">' + escapeHtml(order.location_name || String(order.location_id)) + '</td>';
                html += '<td class="py-2 px-2 text-right text-xs text-blue-300/70">' + formatExpires(order.expires) + '</td>';
                html += '</tr>';
            });
            console.log('Generated HTML:', html.substring(0, 200) + '...');
            tbody.innerHTML = html;
            console.log('Orders rendered successfully');
            console.log('tbody content after render:', tbody.innerHTML.substring(0, 200));
        } catch (e) {
            console.error('渲染订单失败:', e);
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-red-400">渲染订单失败: ' + e.message + '</td></tr>';
        }
    }

    function filterOrders(filter) {
        document.querySelectorAll('.order-filter').forEach(b => {
            b.classList.toggle('active', b.dataset.filter === filter);
            b.classList.toggle('bg-blue-600/30', b.dataset.filter === filter);
            b.classList.toggle('bg-white/10', b.dataset.filter !== filter);
        });

        let filtered = allMyOrders;
        if (filter === 'sell') {
            filtered = allMyOrders.filter(o => !o.is_buy_order);
        } else if (filter === 'buy') {
            filtered = allMyOrders.filter(o => o.is_buy_order);
        }
        renderMyOrders(filtered);
    }

    async function loadMyOrderIds() {
        try {
            const resp = await fetch(API.myOrderIds, {
                credentials: 'same-origin'  // 确保 session cookie 被发送
            });
            const result = await resp.json();
            if (result.success) {
                myOrderIds = result.data || [];
            }
        } catch (e) {
            console.error('加载订单ID失败:', e);
        }
    }

    // ==================== 初始化 ====================
    document.addEventListener('DOMContentLoaded', async function() {
        // 先加载区域列表和活跃物品类型（并行）
        await Promise.all([
            loadRegions(),
            loadActiveTypes(),
        ]);
        // 然后加载市场分组（内部会调用 applyFilters，需要 activeTypeIds 已准备好）
        await loadMarketGroups();
        setupSearch();
        loadMyOrderIds();
    });
</script>
@endpush
