<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的资产 - EVE ESI</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
    </style>
</head>
<body class="eve-bg min-h-screen text-white">
    <!-- 导航栏（与 dashboard 一致） -->
    <nav class="bg-white/10 backdrop-blur-lg border-b border-white/20">
        <div class="container mx-auto px-4 py-2">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <span class="text-2xl">🚀</span>
                    <div>
                        <a href="{{ route('dashboard') }}" class="text-xl font-bold">EVE ESI</a>
                        <span class="text-sm text-blue-200 ml-3">欢迎，{{ ->name }}</span>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <a href="{{ route('dashboard') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all" title="仪表盘">
                        <div class="text-2xl">🏠</div>
                    </a>
                    <a href="{{ route('skills.index') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all" title="技能队列">
                        <div class="text-2xl">📚</div>
                    </a>
                    <a href="{{ route('assets.index') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all bg-white/10" title="我的资产">
                        <div class="text-2xl">📦</div>
                    </a>
                    <a href="{{ route('characters.index') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all" title="角色管理">
                        <div class="text-2xl">👥</div>
                    </a>
                    <form action="{{ route('auth.logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg text-sm ml-2">
                            登出
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold mb-2">📦 我的资产</h1>
            <p class="text-blue-300">查看和管理你的角色资产</p>
        </div>

        <!-- 统计信息 -->
        <div id="asset-summary" class="grid md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow"><div class="skeleton h-8 w-20 mb-2"></div><div class="skeleton h-4 w-32"></div></div>
            <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow"><div class="skeleton h-8 w-20 mb-2"></div><div class="skeleton h-4 w-32"></div></div>
            <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow"><div class="skeleton h-8 w-20 mb-2"></div><div class="skeleton h-4 w-32"></div></div>
            <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow"><div class="skeleton h-8 w-20 mb-2"></div><div class="skeleton h-4 w-32"></div></div>
        </div>

        <!-- 资产列表 -->
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold">资产列表</h2>
                <div class="flex items-center space-x-4">
                    <input type="text" id="search-input" placeholder="搜索物品名称..."
                           class="bg-white/10 border border-white/20 rounded-lg px-4 py-2 text-white placeholder-blue-300 focus:outline-none focus:border-blue-400">
                </div>
            </div>
            <div id="asset-list" class="space-y-3">
                <div class="bg-white/5 rounded-lg p-4"><div class="skeleton h-5 w-3/4 mb-2"></div><div class="skeleton h-4 w-1/2"></div></div>
                <div class="bg-white/5 rounded-lg p-4"><div class="skeleton h-5 w-3/4 mb-2"></div><div class="skeleton h-4 w-1/2"></div></div>
                <div class="bg-white/5 rounded-lg p-4"><div class="skeleton h-5 w-3/4 mb-2"></div><div class="skeleton h-4 w-1/2"></div></div>
            </div>
        </div>
    </div>

    <script>
        const API_ENDPOINT = '/api/dashboard/assets';
        let allAssets = [];
        let filteredAssets = [];

        function formatNumber(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        function createEl(tag, className, textContent) {
            const el = document.createElement(tag);
            if (className) el.className = className;
            if (textContent !== undefined) el.textContent = textContent;
            return el;
        }

        function showError(containerId, icon, title, message) {
            const container = document.getElementById(containerId);
            container.textContent = '';
            const wrapper = createEl('div', 'text-center py-12');
            wrapper.appendChild(createEl('div', 'text-6xl mb-4', icon));
            wrapper.appendChild(createEl('p', 'text-xl text-blue-300 mb-2', title));
            wrapper.appendChild(createEl('p', 'text-blue-400', message));
            container.appendChild(wrapper);
        }

        async function loadAssets() {
            try {
                const response = await fetch(API_ENDPOINT, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });

                if (response.status === 401) {
                    showError('asset-list', '🔐', '未授权', '会话已过期，请刷新页面重新登录');
                    return;
                }

                const result = await response.json();

                if (result.success) {
                    allAssets = result.data.assets || [];
                    filteredAssets = allAssets;
                    renderSummary(result.data.summary);
                    renderAssetList(filteredAssets);
                } else {
                    let icon = '⚠️', title = '加载失败', message = result.message || '请稍后再试';
                    if (result.error === 'connection_timeout') {
                        icon = '🔄'; title = '连接超时'; message = 'EVE API 可能不可用，请稍后再试';
                    } else if (result.error === 'token_expired') {
                        icon = '🔐'; title = 'Token 过期'; message = '请刷新 Token 或重新授权';
                    }
                    showError('asset-list', icon, title, message);
                }
            } catch (error) {
                console.error('加载资产失败:', error);
                showError('asset-list', '⚠️', '加载失败', '网络错误，请刷新页面重试');
            }
        }

        function renderSummary(summary) {
            const container = document.getElementById('asset-summary');
            container.textContent = '';

            const items = [
                { value: formatNumber(summary.total_assets), label: '物品种类', color: 'text-blue-400' },
                { value: formatNumber(summary.total_quantity || 0), label: '物品总数', color: 'text-green-400' },
                { value: formatNumber(summary.locations_count), label: '存放位置', color: 'text-yellow-400' },
                { value: summary.total_value > 0 ? formatNumber(summary.total_value) + ' ISK' : '计算中', label: '总价值', color: 'text-purple-400' },
            ];

            items.forEach(item => {
                const card = createEl('div', 'bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow text-center');
                card.appendChild(createEl('div', 'text-3xl font-bold mb-2 ' + item.color, item.value));
                card.appendChild(createEl('div', 'text-sm text-blue-200', item.label));
                container.appendChild(card);
            });
        }

        function renderAssetList(assets) {
            const container = document.getElementById('asset-list');
            container.textContent = '';

            if (assets.length === 0) {
                const wrapper = createEl('div', 'text-center py-12');
                wrapper.appendChild(createEl('div', 'text-6xl mb-4', '📦'));
                wrapper.appendChild(createEl('p', 'text-blue-300', '暂无资产数据'));
                wrapper.appendChild(createEl('p', 'text-blue-400 text-sm mt-2', '你的角色目前没有资产'));
                container.appendChild(wrapper);
                return;
            }

            assets.forEach(asset => {
                const row = createEl('div', 'bg-white/5 rounded-lg p-4 hover:bg-white/10 transition-all');
                const flex = createEl('div', 'flex justify-between items-start');

                const left = createEl('div', 'flex-1');
                left.appendChild(createEl('div', 'font-medium text-lg', asset.type_name));
                const meta = createEl('div', 'text-sm text-blue-300 mt-1');
                meta.textContent = '📍 ' + asset.location_name + ' • 📦 ' + asset.location_flag;
                left.appendChild(meta);

                const right = createEl('div', 'text-right');
                right.appendChild(createEl('div', 'text-xl font-bold text-blue-400', 'x' + formatNumber(asset.quantity)));
                if (asset.total_value > 0) {
                    right.appendChild(createEl('div', 'text-sm text-yellow-400', formatNumber(asset.total_value) + ' ISK'));
                }

                flex.appendChild(left);
                flex.appendChild(right);
                row.appendChild(flex);
                container.appendChild(row);
            });
        }

        function setupSearch() {
            const searchInput = document.getElementById('search-input');
            searchInput.addEventListener('input', (e) => {
                const keyword = e.target.value.toLowerCase().trim();
                filteredAssets = !keyword ? allAssets : allAssets.filter(asset =>
                    asset.type_name.toLowerCase().includes(keyword) ||
                    asset.location_name.toLowerCase().includes(keyword)
                );
                renderAssetList(filteredAssets);
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            loadAssets();
            setupSearch();
        });
    </script>
</body>
</html>
