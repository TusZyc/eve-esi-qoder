<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>市场中心 - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .eve-bg {
            background: linear-gradient(135deg, #0c1445 0%, #1a237e 50%, #283593 100%);
        }
        .eve-glow {
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.5);
        }
        @keyframes shimmer {
            0% { background-position: -1000px 0; }
            100% { background-position: 1000px 0; }
        }
        .skeleton {
            background: linear-gradient(90deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.08) 50%, rgba(255,255,255,0.03) 100%);
            background-size: 1000px 100%;
            animation: shimmer 2s infinite;
            border-radius: 4px;
        }
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
        .tab-btn {
            transition: all 0.2s;
        }
        .tab-btn.active {
            background: rgba(59, 130, 246, 0.3);
            border-bottom: 2px solid #3b82f6;
        }
        .order-row:hover {
            background: rgba(255,255,255,0.05);
        }
    </style>
</head>
<body class="eve-bg min-h-screen text-white">
    <!-- 导航栏 -->
    <nav class="bg-white/10 backdrop-blur-lg border-b border-white/20">
        <div class="container mx-auto px-4 py-2">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <span class="text-2xl">📊</span>
                    <a href="{{ route('market.index') }}" class="text-xl font-bold">市场中心</a>
                    @if($isLoggedIn)
                        <span class="text-sm text-blue-200 ml-3">已授权</span>
                    @else
                        <span class="text-sm text-blue-200 ml-3">游客模式</span>
                    @endif
                </div>
                <div class="flex items-center space-x-2">
                    @if($isLoggedIn)
                        <a href="{{ route('dashboard') }}" class="p-2 hover:bg-white/10 rounded-lg transition-all" title="仪表盘">
                            <span class="text-xl">🏠</span>
                        </a>
                        <form action="{{ route('auth.logout') }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="bg-red-600 hover:bg-red-700 px-3 py-1.5 rounded-lg text-sm">
                                登出
                            </button>
                        </form>
                    @else
                        <a href="{{ route('guest.dashboard') }}" class="bg-white/10 hover:bg-white/20 px-3 py-1.5 rounded-lg text-sm transition-all">
                            返回游客中心
                        </a>
                        <a href="{{ route('auth.guide') }}" class="bg-blue-600 hover:bg-blue-700 px-3 py-1.5 rounded-lg text-sm transition-all">
                            授权登录
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </nav>

    <!-- 标签切换 -->
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
        <div class="flex gap-4">
            <!-- 左侧面板 -->
            <div class="w-1/3 flex-shrink-0">
                <div class="bg-white/10 backdrop-blur-lg rounded-xl p-4 eve-glow sticky top-4">
                    <!-- 区域选择 -->
                    <div class="mb-4">
                        <label class="text-xs text-blue-300 mb-1 block">区域选择</label>
                        <select id="region-selector" class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
                            @foreach($popularRegions as $id => $name)
                                <option value="{{ $id }}" {{ $id == $defaultRegion ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- 搜索框 -->
                    <div class="mb-4">
                        <label class="text-xs text-blue-300 mb-1 block">搜索物品</label>
                        <input type="text" id="type-search" placeholder="输入物品名称..."
                            class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
                    </div>

                    <!-- 市场分类树 -->
                    <div class="border-t border-white/10 pt-4">
                        <h3 class="text-sm font-medium text-blue-300 mb-2">市场分类</h3>
                        <div id="market-tree" class="max-h-96 overflow-y-auto">
                            <!-- 骨架屏 -->
                            <div class="space-y-2">
                                <div class="skeleton h-8 w-full"></div>
                                <div class="skeleton h-8 w-3/4"></div>
                                <div class="skeleton h-8 w-5/6"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 右侧面板 -->
            <div class="flex-1 space-y-4">
                <!-- 物品信息卡 -->
                <div id="type-info-card" class="bg-white/10 backdrop-blur-lg rounded-xl p-4 eve-glow hidden">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 id="type-name" class="text-xl font-bold text-blue-300">-</h2>
                            <p id="type-desc" class="text-xs text-blue-400 mt-1 max-w-xl">-</p>
                        </div>
                        <div class="text-right text-sm text-blue-300">
                            <div>体积: <span id="type-volume">-</span> m³</div>
                        </div>
                    </div>
                </div>

                <!-- 出售订单 -->
                <div class="bg-white/10 backdrop-blur-lg rounded-xl p-4 eve-glow">
                    <h3 class="text-lg font-semibold text-green-400 mb-3">💰 出售订单</h3>
                    <div id="sell-orders-content" class="overflow-x-auto">
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
                                <tr><td colspan="4" class="text-center py-8 text-blue-300/50">请选择物品查看订单</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 求购订单 -->
                <div class="bg-white/10 backdrop-blur-lg rounded-xl p-4 eve-glow">
                    <h3 class="text-lg font-semibold text-yellow-400 mb-3">📋 求购订单</h3>
                    <div id="buy-orders-content" class="overflow-x-auto">
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
                                <tr><td colspan="4" class="text-center py-8 text-blue-300/50">请选择物品查看订单</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 价格历史图表 -->
        <div id="price-chart-container" class="bg-white/10 backdrop-blur-lg rounded-xl p-4 eve-glow mt-4 hidden">
            <h3 class="text-lg font-semibold text-purple-400 mb-3">📈 价格历史（30天）</h3>
            <div class="h-64">
                <canvas id="price-chart"></canvas>
            </div>
        </div>
    </div>

    <!-- 我的订单标签内容 -->
    @if($isLoggedIn)
        <div id="content-myorders" class="container mx-auto px-4 py-4 hidden">
            <div class="bg-white/10 backdrop-blur-lg rounded-xl p-4 eve-glow">
                <!-- 筛选 -->
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
                            <tr><td colspan="6" class="text-center py-8 text-blue-300/50">加载中...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <script>
        // 全局状态
        let currentRegion = {{ $defaultRegion }};
        let currentTypeId = null;
        let myOrderIds = [];
        let marketGroups = [];
        let priceChart = null;

        // API 端点
        const API = {
            groups: '{{ route("api.public.market.groups") }}',
            orders: '{{ route("api.public.market.orders") }}',
            history: '{{ route("api.public.market.history") }}',
            typeDetail: function(id) { return '/api/public/market/types/' + id; },
            @if($isLoggedIn)
            myOrderIds: '{{ route("api.market.my-order-ids") }}',
            characterOrders: '{{ route("api.market.character-orders") }}',
            @endif
        };

        const isLoggedIn = {{ $isLoggedIn ? 'true' : 'false' }};

        // 工具函数
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

        // 标签切换
        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('tab-' + tab).classList.add('active');

            document.getElementById('content-browse').classList.toggle('hidden', tab !== 'browse');
            @if($isLoggedIn)
            document.getElementById('content-myorders').classList.toggle('hidden', tab !== 'myorders');
            if (tab === 'myorders') loadMyOrders();
            @endif
        }

        // 加载市场分组
        async function loadMarketGroups() {
            try {
                const resp = await fetch(API.groups);
                const result = await resp.json();
                if (result.success) {
                    marketGroups = result.data;
                    renderMarketTree();
                }
            } catch (e) {
                console.error('加载市场分组失败:', e);
            }
        }

        // 渲染市场树
        function renderMarketTree() {
            const container = document.getElementById('market-tree');
            container.innerHTML = renderTreeNodes(marketGroups);
        }

        function renderTreeNodes(nodes, level = 0) {
            if (!nodes || !nodes.length) return '';
            let html = '';
            nodes.forEach(node => {
                const hasChildren = node.children && node.children.length > 0;
                const hasTypes = node.types && node.types.length > 0;
                html += '<div class="tree-node" data-id="' + node.id + '">';
                html += '<div class="market-tree-item flex items-center gap-2 py-1.5 px-2 rounded" onclick="handleTreeClick(' + node.id + ', ' + hasChildren + ', event)">';
                if (hasChildren) {
                    html += '<span class="tree-toggle text-xs text-blue-400">▶</span>';
                } else {
                    html += '<span class="text-xs text-blue-400/30">●</span>';
                }
                html += '<span class="text-sm">' + node.name + '</span>';
                html += '</div>';
                if (hasChildren) {
                    html += '<div class="tree-children hidden">';
                    html += renderTreeNodes(node.children, level + 1);
                    html += '</div>';
                }
                html += '</div>';
            });
            return html;
        }

        // 处理树节点点击
        function handleTreeClick(id, hasChildren, event) {
            event.stopPropagation();
            const node = event.currentTarget.closest('.tree-node');
            const childrenContainer = node.querySelector('.tree-children');

            if (hasChildren && childrenContainer) {
                // 切换展开
                childrenContainer.classList.toggle('hidden');
                const toggle = node.querySelector('.tree-toggle');
                if (toggle) {
                    toggle.textContent = childrenContainer.classList.contains('hidden') ? '▶' : '▼';
                }
            }

            // 如果该分组有物品，加载第一个物品
            const group = findGroup(marketGroups, id);
            if (group && group.types && group.types.length > 0) {
                selectType(group.types[0]);
            }
        }

        function findGroup(nodes, id) {
            for (const node of nodes) {
                if (node.id === id) return node;
                if (node.children) {
                    const found = findGroup(node.children, id);
                    if (found) return found;
                }
            }
            return null;
        }

        // 选择物品
        async function selectType(typeId) {
            currentTypeId = typeId;

            // 更新选中状态
            document.querySelectorAll('.market-tree-item').forEach(el => el.classList.remove('selected'));

            // 加载物品详情
            try {
                const resp = await fetch(API.typeDetail(typeId));
                const result = await resp.json();
                if (result.success) {
                    document.getElementById('type-name').textContent = result.data.name;
                    document.getElementById('type-desc').textContent = result.data.description || '暂无描述';
                    document.getElementById('type-volume').textContent = formatNumber(result.data.volume);
                    document.getElementById('type-info-card').classList.remove('hidden');
                }
            } catch (e) {
                console.error('加载物品详情失败:', e);
            }

            // 加载订单
            loadOrders();
            loadHistory();
        }

        // 加载订单
        async function loadOrders() {
            if (!currentTypeId) return;

            const regionId = document.getElementById('region-selector').value;

            // 显示加载状态
            document.getElementById('sell-orders-body').innerHTML = '<tr><td colspan="4" class="text-center py-4"><span class="skeleton inline-block h-4 w-16"></span></td></tr>';
            document.getElementById('buy-orders-body').innerHTML = '<tr><td colspan="4" class="text-center py-4"><span class="skeleton inline-block h-4 w-16"></span></td></tr>';

            try {
                const resp = await fetch(API.orders + '?region_id=' + regionId + '&type_id=' + currentTypeId);
                const result = await resp.json();
                if (result.success) {
                    renderOrders(result.data.sell, 'sell');
                    renderOrders(result.data.buy, 'buy');
                }
            } catch (e) {
                console.error('加载订单失败:', e);
            }
        }

        // 渲染订单
        function renderOrders(orders, type) {
            const tbody = document.getElementById(type + '-orders-body');
            if (!orders || orders.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-blue-300/50">暂无订单</td></tr>';
                return;
            }

            let html = '';
            orders.slice(0, 50).forEach(order => {
                const isMyOrder = myOrderIds.includes(order.order_id);
                const rowClass = isMyOrder ? 'order-row my-order' : 'order-row';
                html += '<tr class="' + rowClass + ' border-b border-white/5">';
                html += '<td class="py-2 px-2 text-' + (type === 'sell' ? 'green' : 'yellow') + '-400">' + formatPrice(order.price) + '</td>';
                html += '<td class="py-2 px-2 text-right">' + formatNumber(order.volume_remain) + '</td>';
                html += '<td class="py-2 px-2 text-blue-300 text-xs">' + (order.location_name || order.location_id) + '</td>';
                html += '<td class="py-2 px-2 text-right text-xs text-blue-300/70">' + formatExpires(order.expires) + '</td>';
                html += '</tr>';
            });

            tbody.innerHTML = html;
        }

        // 加载价格历史
        async function loadHistory() {
            if (!currentTypeId) return;

            const regionId = document.getElementById('region-selector').value;

            try {
                const resp = await fetch(API.history + '?region_id=' + regionId + '&type_id=' + currentTypeId);
                const result = await resp.json();
                if (result.success && result.data && result.data.length > 0) {
                    renderChart(result.data.slice(-30)); // 最近30天
                } else {
                    document.getElementById('price-chart-container').classList.add('hidden');
                }
            } catch (e) {
                console.error('加载价格历史失败:', e);
            }
        }

        // 渲染图表
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
            const volumes = data.map(d => d.volume);

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
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            grid: { color: 'rgba(255,255,255,0.05)' },
                            ticks: { color: 'rgba(255,255,255,0.5)' }
                        },
                        y: {
                            grid: { color: 'rgba(255,255,255,0.05)' },
                            ticks: { color: 'rgba(255,255,255,0.5)' }
                        }
                    }
                }
            });
        }

        // 加载我的订单
        @if($isLoggedIn)
        async function loadMyOrders() {
            try {
                const resp = await fetch(API.characterOrders);
                const result = await resp.json();
                if (result.success) {
                    renderMyOrders(result.data);
                }
            } catch (e) {
                console.error('加载角色订单失败:', e);
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
                html += '<tr class="order-row border-b border-white/5">';
                html += '<td class="py-2 px-2">' + (order.type_id) + '</td>';
                html += '<td class="py-2 px-2 text-center ' + typeColor + '">' + typeLabel + '</td>';
                html += '<td class="py-2 px-2 text-right">' + formatPrice(order.price) + '</td>';
                html += '<td class="py-2 px-2 text-right">' + formatNumber(order.volume_remain) + '/' + formatNumber(order.volume_total) + '</td>';
                html += '<td class="py-2 px-2 text-xs text-blue-300">' + (order.location_name || order.location_id) + '</td>';
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
            // TODO: 实现筛选逻辑
        }

        async function loadMyOrderIds() {
            try {
                const resp = await fetch(API.myOrderIds);
                const result = await resp.json();
                if (result.success) {
                    myOrderIds = result.data;
                }
            } catch (e) {
                console.error('加载订单ID失败:', e);
            }
        }
        @endif

        // 搜索功能
        let searchTimer = null;
        function setupSearch() {
            const input = document.getElementById('type-search');
            input.addEventListener('input', function(e) {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    const keyword = e.target.value.toLowerCase().trim();
                    filterTree(keyword);
                }, 300);
            });
        }

        function filterTree(keyword) {
            // 简单实现：隐藏不匹配的节点
            document.querySelectorAll('.tree-node').forEach(node => {
                const text = node.textContent.toLowerCase();
                node.style.display = text.includes(keyword) ? '' : 'none';
            });
        }

        // 区域切换
        document.getElementById('region-selector').addEventListener('change', function() {
            currentRegion = this.value;
            if (currentTypeId) {
                loadOrders();
                loadHistory();
            }
        });

        // 初始化
        document.addEventListener('DOMContentLoaded', async function() {
            await loadMarketGroups();
            setupSearch();
            @if($isLoggedIn)
            await loadMyOrderIds();
            @endif
        });
    </script>
</body>
</html>