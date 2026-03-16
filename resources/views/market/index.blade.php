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
    .type-item.no-orders {
        opacity: 0.35;
    }
    .search-result-item { cursor: pointer; transition: all 0.15s; }
    .search-result-item:hover { background: rgba(255,255,255,0.08); }
    .search-result-item.no-orders {
        opacity: 0.35;
    }
    .panel-scroll::-webkit-scrollbar { width: 4px; }
    .panel-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 2px; }
    .panel-scroll::-webkit-scrollbar-track { background: transparent; }
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
                    <h3 class="text-xs font-medium text-blue-300 mb-2">区域选择</h3>
                    <div id="region-list" class="flex-1 overflow-y-auto panel-scroll space-y-0.5">
                        <div class="skeleton h-6 w-full mb-1"></div>
                        <div class="skeleton h-6 w-3/4 mb-1"></div>
                        <div class="skeleton h-6 w-5/6"></div>
                    </div>
                </div>
            </div>

            <!-- 中栏：市场分类树 / 搜索结果 -->
            <div class="w-72 flex-shrink-0">
                <div class="bg-white/10 backdrop-blur-lg rounded-xl p-3 eve-glow h-full flex flex-col">
                    <div class="flex items-center justify-between mb-2">
                        <h3 id="middle-panel-title" class="text-xs font-medium text-blue-300">市场分类</h3>
                        <label id="order-filter-checkbox" class="hidden items-center gap-1 cursor-pointer">
                            <input type="checkbox" id="only-with-orders" class="w-3 h-3 accent-blue-500" onchange="onOrderFilterChange()">
                            <span class="text-[10px] text-blue-300/70 whitespace-nowrap">仅有订单</span>
                        </label>
                    </div>
                    <!-- 分类树模式 -->
                    <div id="market-tree-container" class="flex-1 overflow-y-auto panel-scroll">
                        <div id="market-tree">
                            <div class="space-y-2">
                                <div class="skeleton h-6 w-full"></div>
                                <div class="skeleton h-6 w-3/4"></div>
                                <div class="skeleton h-6 w-5/6"></div>
                            </div>
                        </div>
                    </div>
                    <!-- 搜索结果模式 -->
                    <div id="search-results-container" class="flex-1 overflow-y-auto panel-scroll hidden">
                        <div id="search-results"></div>
                    </div>
                </div>
            </div>

            <!-- 右栏：物品详情 + 订单 -->
            <div class="flex-1 overflow-y-auto panel-scroll space-y-4">
                <!-- 空状态提示 -->
                <div id="empty-state" class="bg-white/10 backdrop-blur-lg rounded-xl p-12 eve-glow text-center">
                    <div class="text-4xl mb-4">📦</div>
                    <p class="text-blue-300/70">请在左侧选择物品查看市场订单</p>
                    <p class="text-blue-300/40 text-xs mt-2">可通过顶部搜索框快速查找物品</p>
                </div>

                <!-- 物品信息卡（单行：名称 + 分类） -->
                <div id="type-info-card" class="bg-white/10 backdrop-blur-lg rounded-xl px-4 py-2.5 eve-glow hidden">
                    <div class="flex items-center gap-3">
                        <h2 id="type-name" class="text-lg font-bold text-blue-300 flex-shrink-0">-</h2>
                        <span id="type-category" class="text-xs text-blue-400/60 truncate">-</span>
                    </div>
                </div>

                <!-- 出售订单 -->
                <div id="sell-orders-card" class="bg-white/10 backdrop-blur-lg rounded-xl p-4 eve-glow hidden">
                    <h3 class="text-lg font-semibold text-green-400 mb-3">💰 出售订单</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-blue-300 border-b border-white/10">
                                    <th class="text-left py-2 px-2">价格 (ISK)</th>
                                    <th class="text-right py-2 px-2">数量</th>
                                    <th class="text-left py-2 px-2">位置</th>
                                    <th class="text-right py-2 px-2">到期时间</th>
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
                    <h3 class="text-lg font-semibold text-yellow-400 mb-3">📋 求购订单</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-blue-300 border-b border-white/10">
                                    <th class="text-left py-2 px-2">价格 (ISK)</th>
                                    <th class="text-right py-2 px-2">数量</th>
                                    <th class="text-left py-2 px-2">位置</th>
                                    <th class="text-right py-2 px-2">到期时间</th>
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
                    <h3 class="text-lg font-semibold text-purple-400 mb-3">📈 价格历史（30天）</h3>
                    <div class="h-64">
                        <canvas id="price-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 我的订单标签内容 -->
    @if($isLoggedIn)
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
    @endif
@endsection

@push('scripts')
<script>
    // ==================== 全局状态 ====================
    const FORGE_REGION_ID = 10000002;
    let currentRegion = FORGE_REGION_ID; // 默认伏尔戈
    let currentTypeId = null;
    let currentTypeCategory = '';
    let myOrderIds = [];
    let allMyOrders = [];
    let marketGroups = [];
    let priceChart = null;
    let activeTypeIds = new Set();
    let activeTypesLoading = false;

    // 订单分页状态
    let currentSellOrders = [];
    let currentBuyOrders = [];
    let sellOrdersExpanded = false;
    let buyOrdersExpanded = false;
    const ORDER_PAGE_SIZE = 10;

    // 搜索结果缓存
    let lastSearchResults = [];
    let lastSearchKeyword = '';

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

    /** 判断字符串是否以中文字符开头 */
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
        @if($isLoggedIn)
        document.getElementById('content-myorders').classList.toggle('hidden', tab !== 'myorders');
        if (tab === 'myorders') loadMyOrders();
        @endif
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

        // 分离中文星域和其他星域，中文排前面
        const cnRegions = regions.filter(r => startsWithChinese(r.name));
        const otherRegions = regions.filter(r => !startsWithChinese(r.name));
        cnRegions.sort((a, b) => a.name.localeCompare(b.name, 'zh'));
        otherRegions.sort((a, b) => a.name.localeCompare(b.name));
        const sortedRegions = [...cnRegions, ...otherRegions];

        let html = '';

        // "伏尔戈（吉他）"快捷选项 — 默认选中
        html += '<div class="region-item selected rounded px-2 py-1.5 text-sm font-medium text-green-300 border-b border-white/10 mb-0.5" data-region="' + FORGE_REGION_ID + '" onclick="selectRegion(' + FORGE_REGION_ID + ', this)">';
        html += '⭐ 伏尔戈（吉他）';
        html += '</div>';

        // "全部"选项
        html += '<div class="region-item rounded px-2 py-1.5 text-sm font-medium text-yellow-300 border-b border-white/10 mb-1" data-region="all" onclick="selectRegion(\'all\', this)">';
        html += '🌌 全部';
        html += '</div>';

        // 所有星域（中文在前）
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

        // 切换区域时重新加载活跃物品类型
        loadActiveTypes();

        if (currentTypeId) {
            loadOrders();
            loadHistory();
        }
    }

    // ==================== 活跃物品类型 ====================
    async function loadActiveTypes() {
        activeTypesLoading = true;
        try {
            const resp = await fetch(API.activeTypes + '?region_id=' + currentRegion);
            const result = await resp.json();
            if (result.success) {
                activeTypeIds = new Set(result.data);
            }
        } catch (e) {
            console.error('加载活跃物品类型失败:', e);
            activeTypeIds = new Set();
        }
        activeTypesLoading = false;

        // 刷新当前显示的物品样式
        refreshItemStyles();

        // 如果有搜索结果且勾选了"仅有订单"，重新过滤
        if (!document.getElementById('search-results-container').classList.contains('hidden')) {
            applySearchFilter();
        }
    }

    /** 刷新树和搜索结果中的物品样式（有/无订单） */
    function refreshItemStyles() {
        document.querySelectorAll('.type-item[data-typeid]').forEach(el => {
            const tid = parseInt(el.dataset.typeid);
            el.classList.toggle('no-orders', activeTypeIds.size > 0 && !activeTypeIds.has(tid));
        });
        document.querySelectorAll('.search-result-item[data-typeid]').forEach(el => {
            const tid = parseInt(el.dataset.typeid);
            el.classList.toggle('no-orders', activeTypeIds.size > 0 && !activeTypeIds.has(tid));
        });
    }

    // ==================== 市场分组树 ====================
    async function loadMarketGroups() {
        try {
            const resp = await fetch(API.groups);
            const result = await resp.json();
            if (result.success && result.data && result.data.length > 0) {
                marketGroups = result.data;
                renderMarketTree();
            } else {
                document.getElementById('market-tree').innerHTML =
                    '<div class="text-xs text-blue-300/50 text-center py-4">分组数据准备中...<br>请稍后刷新</div>';
            }
        } catch (e) {
            console.error('加载市场分组失败:', e);
            document.getElementById('market-tree').innerHTML =
                '<div class="text-xs text-red-400 text-center py-4">加载失败</div>';
        }
    }

    function renderMarketTree() {
        const container = document.getElementById('market-tree');
        container.innerHTML = renderTreeNodes(marketGroups);
        refreshItemStyles();
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
                        const noOrders = activeTypeIds.size > 0 && !activeTypeIds.has(typeId);
                        html += '<div class="type-item py-1 px-2 rounded text-xs truncate' + (noOrders ? ' no-orders' : '') + '" data-typeid="' + typeId + '" onclick="selectType(' + typeId + ', event)" title="' + escapeHtml(typeName) + '">';
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

    // ==================== 物品选择 ====================
    async function selectType(typeId, event, category) {
        if (event) event.stopPropagation();
        currentTypeId = typeId;
        currentTypeCategory = category || '';

        // 更新选中状态
        document.querySelectorAll('.type-item').forEach(el => el.classList.remove('selected'));
        document.querySelectorAll('.search-result-item').forEach(el => el.classList.remove('selected'));
        const clicked = event ? event.currentTarget : document.querySelector('[data-typeid="' + typeId + '"]');
        if (clicked) clicked.classList.add('selected');

        // 显示订单面板，隐藏空状态
        document.getElementById('empty-state').classList.add('hidden');
        document.getElementById('type-info-card').classList.remove('hidden');
        document.getElementById('sell-orders-card').classList.remove('hidden');
        document.getElementById('buy-orders-card').classList.remove('hidden');

        // 加载物品详情（仅名称和分类）
        try {
            const resp = await fetch(API.typeDetail(typeId));
            const result = await resp.json();
            if (result.success) {
                document.getElementById('type-name').textContent = result.data.name || '-';
                // 分类信息：优先用搜索结果带回的 category，否则用 market_group
                if (currentTypeCategory) {
                    document.getElementById('type-category').textContent = currentTypeCategory;
                } else {
                    document.getElementById('type-category').textContent = result.data.market_group_id ? ('分组 #' + result.data.market_group_id) : '';
                }
            }
        } catch (e) {
            console.error('加载物品详情失败:', e);
        }

        // 加载订单和历史
        loadOrders();
        loadHistory();
    }

    // ==================== 订单加载 ====================
    async function loadOrders() {
        if (!currentTypeId) return;

        // 重置分页状态
        sellOrdersExpanded = false;
        buyOrdersExpanded = false;

        // 显示加载状态
        document.getElementById('sell-orders-body').innerHTML = '<tr><td colspan="4" class="text-center py-4"><span class="skeleton inline-block h-4 w-24"></span></td></tr>';
        document.getElementById('buy-orders-body').innerHTML = '<tr><td colspan="4" class="text-center py-4"><span class="skeleton inline-block h-4 w-24"></span></td></tr>';

        try {
            const resp = await fetch(API.orders + '?region_id=' + currentRegion + '&type_id=' + currentTypeId);
            const result = await resp.json();
            if (result.success) {
                currentSellOrders = result.data.sell || [];
                currentBuyOrders = result.data.buy || [];
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
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-blue-300/50">暂无订单</td></tr>';
            return;
        }

        const expanded = type === 'sell' ? sellOrdersExpanded : buyOrdersExpanded;
        const displayOrders = expanded ? orders : orders.slice(0, ORDER_PAGE_SIZE);

        let html = '';
        displayOrders.forEach(order => {
            const isMyOrder = myOrderIds.includes(order.order_id);
            const rowClass = isMyOrder ? 'order-row my-order' : 'order-row';
            html += '<tr class="' + rowClass + ' border-b border-white/5">';
            html += '<td class="py-1.5 px-2 text-' + (type === 'sell' ? 'green' : 'yellow') + '-400 whitespace-nowrap">' + formatPrice(order.price) + '</td>';
            html += '<td class="py-1.5 px-2 text-right whitespace-nowrap">' + formatNumber(order.volume_remain) + '</td>';
            html += '<td class="py-1.5 px-2 text-blue-300 text-xs truncate max-w-xs" title="' + escapeHtml(order.location_name || '') + '">' + escapeHtml(order.location_name || String(order.location_id)) + '</td>';
            html += '<td class="py-1.5 px-2 text-right text-xs text-blue-300/70 whitespace-nowrap">' + formatExpires(order.expires) + '</td>';
            html += '</tr>';
        });

        // 加载更多 / 折叠 按钮
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
        const avgPrices = data.map(d => d.average);

        priceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: '平均价格',
                    data: avgPrices,
                    borderColor: '#a855f7',
                    backgroundColor: 'rgba(168, 85, 247, 0.1)',
                    fill: true,
                    tension: 0.3,
                    yAxisID: 'y'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: 'rgba(255,255,255,0.5)' } },
                    y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: 'rgba(255,255,255,0.5)' } }
                }
            }
        });
    }

    // ==================== 搜索功能 ====================
    let searchTimer = null;
    function setupSearch() {
        const input = document.getElementById('type-search');
        input.addEventListener('input', function(e) {
            clearTimeout(searchTimer);
            const keyword = e.target.value.trim();

            if (keyword.length < 1) {
                showTreeMode();
                return;
            }

            document.getElementById('search-indicator').classList.remove('hidden');
            searchTimer = setTimeout(() => searchItems(keyword), 300);
        });
    }

    async function searchItems(keyword) {
        try {
            const resp = await fetch(API.search + '?q=' + encodeURIComponent(keyword));
            const result = await resp.json();
            document.getElementById('search-indicator').classList.add('hidden');

            if (result.success) {
                lastSearchResults = result.data;
                lastSearchKeyword = keyword;
                applySearchFilter();
            }
        } catch (e) {
            console.error('搜索失败:', e);
            document.getElementById('search-indicator').classList.add('hidden');
        }
    }

    /** 应用搜索过滤（考虑"仅有订单"勾选） */
    function applySearchFilter() {
        const onlyWithOrders = document.getElementById('only-with-orders').checked;
        let results = lastSearchResults;

        if (onlyWithOrders && activeTypeIds.size > 0) {
            results = results.filter(item => activeTypeIds.has(item.id));
        }

        showSearchMode(results, lastSearchKeyword);
    }

    function showSearchMode(results, keyword) {
        document.getElementById('market-tree-container').classList.add('hidden');
        document.getElementById('search-results-container').classList.remove('hidden');
        document.getElementById('middle-panel-title').textContent = '搜索结果 (' + results.length + ')';
        document.getElementById('order-filter-checkbox').classList.remove('hidden');
        document.getElementById('order-filter-checkbox').style.display = 'flex';

        const container = document.getElementById('search-results');
        if (results.length === 0) {
            container.innerHTML = '<div class="text-xs text-blue-300/50 text-center py-4">未找到匹配物品</div>';
            return;
        }

        let html = '';
        results.forEach(item => {
            const isSelected = item.id === currentTypeId;
            const noOrders = activeTypeIds.size > 0 && !activeTypeIds.has(item.id);
            html += '<div class="search-result-item py-1.5 px-2 rounded' + (isSelected ? ' selected' : '') + (noOrders ? ' no-orders' : '') + '" data-typeid="' + item.id + '" onclick="selectType(' + item.id + ', event, ' + JSON.stringify(item.category || '').replace(/"/g, '&quot;') + ')" title="' + escapeHtml(item.name) + '">';
            html += '<div class="text-xs truncate">' + escapeHtml(item.name) + '</div>';
            if (item.category) {
                html += '<div class="text-[10px] text-blue-400/40 truncate">' + escapeHtml(item.category) + '</div>';
            }
            html += '</div>';
        });
        container.innerHTML = html;
    }

    function showTreeMode() {
        document.getElementById('market-tree-container').classList.remove('hidden');
        document.getElementById('search-results-container').classList.add('hidden');
        document.getElementById('middle-panel-title').textContent = '市场分类';
        document.getElementById('order-filter-checkbox').classList.add('hidden');
        document.getElementById('search-indicator').classList.add('hidden');
    }

    /** "仅有订单"勾选变化 */
    function onOrderFilterChange() {
        applySearchFilter();
    }

    // ==================== 我的订单 ====================
    @if($isLoggedIn)
    async function loadMyOrders() {
        document.getElementById('my-orders-body').innerHTML = '<tr><td colspan="6" class="text-center py-8 text-blue-300/50">加载中...</td></tr>';
        try {
            const resp = await fetch(API.characterOrders);
            const result = await resp.json();
            if (result.success) {
                allMyOrders = result.data || [];
                renderMyOrders(allMyOrders);
            } else {
                document.getElementById('my-orders-body').innerHTML = '<tr><td colspan="6" class="text-center py-8 text-red-400">' + (result.message || '加载失败') + '</td></tr>';
            }
        } catch (e) {
            console.error('加载角色订单失败:', e);
            document.getElementById('my-orders-body').innerHTML = '<tr><td colspan="6" class="text-center py-8 text-red-400">加载失败</td></tr>';
        }
    }

    function renderMyOrders(orders) {
        const tbody = document.getElementById('my-orders-body');
        if (!orders || orders.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-blue-300/50">暂无订单</td></tr>';
            return;
        }

        let html = '';
        orders.forEach(order => {
            const typeLabel = order.is_buy_order ? '求购' : '出售';
            const typeColor = order.is_buy_order ? 'text-yellow-400' : 'text-green-400';
            const typeName = order.type_name || ('物品#' + order.type_id);
            html += '<tr class="order-row border-b border-white/5">';
            html += '<td class="py-2 px-2">' + escapeHtml(typeName) + '</td>';
            html += '<td class="py-2 px-2 text-center ' + typeColor + '">' + typeLabel + '</td>';
            html += '<td class="py-2 px-2 text-right">' + formatPrice(order.price) + '</td>';
            html += '<td class="py-2 px-2 text-right">' + formatNumber(order.volume_remain) + '/' + formatNumber(order.volume_total) + '</td>';
            html += '<td class="py-2 px-2 text-xs text-blue-300 truncate max-w-xs">' + escapeHtml(order.location_name || String(order.location_id)) + '</td>';
            html += '<td class="py-2 px-2 text-right text-xs text-blue-300/70">' + formatExpires(order.expires) + '</td>';
            html += '</tr>';
        });
        tbody.innerHTML = html;
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
            const resp = await fetch(API.myOrderIds);
            const result = await resp.json();
            if (result.success) {
                myOrderIds = result.data || [];
            }
        } catch (e) {
            console.error('加载订单ID失败:', e);
        }
    }
    @endif

    // ==================== 初始化 ====================
    document.addEventListener('DOMContentLoaded', async function() {
        // 并行加载区域、分组和活跃物品类型
        await Promise.all([
            loadRegions(),
            loadMarketGroups(),
            loadActiveTypes(),
        ]);
        setupSearch();
        @if($isLoggedIn)
        loadMyOrderIds();
        @endif
    });
</script>
@endpush
