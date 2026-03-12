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
        .tree-line {
            border-left: 1px dashed rgba(255,255,255,0.15);
        }
        details > summary { cursor: pointer; list-style: none; }
        details > summary::-webkit-details-marker { display: none; }
    </style>
</head>
<body class="eve-bg min-h-screen text-white">
    <nav class="bg-white/10 backdrop-blur-lg border-b border-white/20">
        <div class="container mx-auto px-4 py-2">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <span class="text-2xl">🚀</span>
                    <div>
                        <a href="{{ route('dashboard') }}" class="text-xl font-bold">EVE ESI</a>
                        <span class="text-sm text-blue-200 ml-3">欢迎，{{ $user->name }}</span>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <a href="{{ route('dashboard') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all" title="仪表盘"><div class="text-2xl">🏠</div></a>
                    <a href="{{ route('skills.index') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all" title="技能队列"><div class="text-2xl">📚</div></a>
                    <a href="{{ route('assets.index') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all bg-white/10" title="我的资产"><div class="text-2xl">📦</div></a>
                    <a href="{{ route('characters.index') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all" title="角色管理"><div class="text-2xl">👥</div></a>
                    <form action="{{ route('auth.logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg text-sm ml-2">登出</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold mb-2">📦 我的资产</h1>
                <p class="text-blue-300">查看和管理你的角色资产</p>
            </div>
            <div>
                <input type="text" id="search-input" placeholder="搜索物品名称..."
                       class="bg-white/10 border border-white/20 rounded-lg px-4 py-2 text-white placeholder-blue-300 focus:outline-none focus:border-blue-400 w-64">
            </div>
        </div>

        <div id="asset-container">
            <!-- 骨架屏 -->
            <div id="skeleton-loader">
                <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow mb-4">
                    <div class="skeleton h-6 w-1/3 mb-4"></div>
                    <div class="skeleton h-4 w-full mb-2"></div>
                    <div class="skeleton h-4 w-full mb-2"></div>
                    <div class="skeleton h-4 w-3/4"></div>
                </div>
                <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow mb-4">
                    <div class="skeleton h-6 w-1/4 mb-4"></div>
                    <div class="skeleton h-4 w-full mb-2"></div>
                    <div class="skeleton h-4 w-full mb-2"></div>
                </div>
            </div>

            <div id="asset-list" class="hidden"></div>
        </div>
    </div>

    <script>
        const API_ENDPOINT = '/api/dashboard/assets';
        let allLocations = [];
        const expandedSet = new Set();
        let searchKeyword = '';
        let debounceTimer = null;

        const FLAG_NAMES = {
            'Hangar':'机库','CargoHold':'货柜仓','DroneBay':'无人机仓',
            'FighterBay':'舰载机仓','FleetHangar':'舰队机库','ShipHangar':'舰船维护阵列',
            'Deliveries':'交付仓','AssetSafety':'资产安全','Unlocked':'未锁定',
            'AutoFit':'自动装配','CorpseBay':'尸体仓','FrigateEscape':'护卫逃生仓',
            'BoosterBay':'助推器仓','SubSystemBay':'子系统仓',
            'HiSlot0':'高槽1','HiSlot1':'高槽2','HiSlot2':'高槽3','HiSlot3':'高槽4',
            'HiSlot4':'高槽5','HiSlot5':'高槽6','HiSlot6':'高槽7','HiSlot7':'高槽8',
            'MedSlot0':'中槽1','MedSlot1':'中槽2','MedSlot2':'中槽3','MedSlot3':'中槽4',
            'MedSlot4':'中槽5','MedSlot5':'中槽6','MedSlot6':'中槽7','MedSlot7':'中槽8',
            'LoSlot0':'低槽1','LoSlot1':'低槽2','LoSlot2':'低槽3','LoSlot3':'低槽4',
            'LoSlot4':'低槽5','LoSlot5':'低槽6','LoSlot6':'低槽7','LoSlot7':'低槽8',
            'RigSlot0':'改装件1','RigSlot1':'改装件2','RigSlot2':'改装件3',
        };

        function flagName(flag) {
            return FLAG_NAMES[flag] || flag;
        }

        function formatNumber(num) {
            if (num === null || num === undefined) return '0';
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        function formatVolume(vol) {
            if (!vol || vol === 0) return '0 m\u00B3';
            if (vol >= 1000) return formatNumber(Math.round(vol)) + ' m\u00B3';
            if (vol >= 0.1) return vol.toFixed(1) + ' m\u00B3';
            return vol.toFixed(2) + ' m\u00B3';
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = String(str);
            return div.innerHTML;
        }

        // 树形搜索：返回过滤后的 locations
        function filterLocations(locations, keyword) {
            if (!keyword) return locations;
            const kw = keyword.toLowerCase();
            const result = [];

            for (const loc of locations) {
                const filteredItems = filterItems(loc.items, kw);
                if (filteredItems.length > 0 || loc.location_name.toLowerCase().includes(kw)) {
                    result.push({
                        ...loc,
                        items: filteredItems.length > 0 ? filteredItems : loc.items,
                    });
                }
            }
            return result;
        }

        function filterItems(items, kw) {
            const result = [];
            for (const item of items) {
                const nameMatch = item.type_name.toLowerCase().includes(kw);
                const groupMatch = item.group_name && item.group_name.toLowerCase().includes(kw);
                const childFiltered = item.children ? filterItems(item.children, kw) : [];

                if (nameMatch || groupMatch) {
                    result.push(item); // 父匹配，保留整个子树
                } else if (childFiltered.length > 0) {
                    result.push({ ...item, children: childFiltered }); // 子匹配，保留路径
                    expandedSet.add(item.item_id); // 自动展开
                }
            }
            return result;
        }

        function toggleItem(itemId) {
            if (expandedSet.has(itemId)) {
                collapseRecursive(itemId);
            } else {
                expandedSet.add(itemId);
            }
            render();
        }

        function collapseRecursive(itemId) {
            expandedSet.delete(itemId);
            // 找到此 item 的 children 并递归收起
            const findAndCollapse = (items) => {
                for (const item of items) {
                    if (item.item_id === itemId && item.children) {
                        for (const child of item.children) {
                            collapseRecursive(child.item_id);
                        }
                        return;
                    }
                    if (item.children) findAndCollapse(item.children);
                }
            };
            for (const loc of allLocations) {
                findAndCollapse(loc.items);
            }
        }

        function renderItemRows(items, depth) {
            let html = '';
            for (const item of items) {
                const hasChildren = item.children && item.children.length > 0;
                const isExpanded = expandedSet.has(item.item_id);
                const indent = depth * 24;
                const isSingleton = item.is_singleton;
                const singletonTag = isSingleton ? '' : '<span class="text-yellow-500 text-xs ml-1">(packaged)</span>';

                html += '<tr class="border-b border-white/5 hover:bg-white/5 transition-all">';

                // 展开按钮 + 数量
                html += '<td class="py-2 px-3 whitespace-nowrap" style="padding-left:' + (12 + indent) + 'px">';
                if (hasChildren) {
                    html += '<button onclick="toggleItem(' + item.item_id + ')" class="text-blue-400 hover:text-blue-300 mr-2 w-4 inline-block text-center font-bold">';
                    html += isExpanded ? '−' : '+';
                    html += '</button>';
                } else if (depth > 0) {
                    html += '<span class="mr-2 w-4 inline-block"></span>';
                }
                if (item.quantity > 1) {
                    html += '<span class="text-blue-300">' + formatNumber(item.quantity) + '</span>';
                } else if (!hasChildren) {
                    html += '<span class="text-blue-300">1</span>';
                }
                html += '</td>';

                // 类型名
                html += '<td class="py-2 px-3">';
                html += '<span class="' + (hasChildren ? 'text-blue-400 font-medium' : '') + '">' + escapeHtml(item.type_name) + '</span>';
                html += singletonTag;
                if (depth > 0) {
                    html += '<span class="text-xs text-gray-400 ml-2">' + escapeHtml(flagName(item.location_flag)) + '</span>';
                }
                html += '</td>';

                // 体积
                html += '<td class="py-2 px-3 text-right text-sm text-blue-300 whitespace-nowrap">' + formatVolume(item.volume) + '</td>';

                // 分组
                html += '<td class="py-2 px-3 text-sm text-gray-400">' + escapeHtml(item.group_name || '') + '</td>';

                html += '</tr>';

                // 子节点
                if (hasChildren && isExpanded) {
                    html += renderItemRows(item.children, depth + 1);
                }
            }
            return html;
        }

        function render() {
            const container = document.getElementById('asset-list');
            const locations = searchKeyword ? filterLocations(allLocations, searchKeyword) : allLocations;

            if (locations.length === 0) {
                container.innerHTML = '';
                const emptyDiv = document.createElement('div');
                emptyDiv.className = 'text-center py-12';
                const icon = document.createElement('div');
                icon.className = 'text-6xl mb-4';
                icon.textContent = searchKeyword ? '🔍' : '📦';
                const msg = document.createElement('p');
                msg.className = 'text-blue-300';
                msg.textContent = searchKeyword ? '没有找到匹配的物品' : '暂无资产数据';
                emptyDiv.appendChild(icon);
                emptyDiv.appendChild(msg);
                container.appendChild(emptyDiv);
                return;
            }

            let html = '';
            for (const loc of locations) {
                html += '<details open class="mb-4">';
                html += '<summary class="bg-white/10 backdrop-blur-lg rounded-xl px-6 py-4 eve-glow flex justify-between items-center hover:bg-white/15 transition-all">';
                html += '<div class="flex items-center">';
                html += '<span class="mr-2 text-blue-400 font-mono">▼</span>';
                html += '<span class="text-lg font-semibold">📍 ' + escapeHtml(loc.location_name) + '</span>';
                html += '</div>';
                html += '<span class="text-sm text-blue-300">' + loc.item_count + ' 件物品</span>';
                html += '</summary>';

                html += '<div class="bg-white/5 rounded-b-xl mt-0 overflow-x-auto">';
                html += '<table class="w-full text-sm">';
                html += '<thead><tr class="border-b border-white/10 text-blue-300">';
                html += '<th class="py-2 px-3 text-left font-medium" style="min-width:100px">数量</th>';
                html += '<th class="py-2 px-3 text-left font-medium">类型</th>';
                html += '<th class="py-2 px-3 text-right font-medium" style="min-width:90px">体积</th>';
                html += '<th class="py-2 px-3 text-left font-medium" style="min-width:100px">分组</th>';
                html += '</tr></thead>';
                html += '<tbody>';
                html += renderItemRows(loc.items, 0);
                html += '</tbody></table>';
                html += '</div>';

                html += '</details>';
            }

            container.innerHTML = html;

            // 修正 details 的 summary 箭头旋转
            container.querySelectorAll('details').forEach(det => {
                const arrow = det.querySelector('summary .font-mono');
                if (!arrow) return;
                const update = () => { arrow.textContent = det.open ? '▼' : '▶'; };
                det.addEventListener('toggle', update);
                update();
            });
        }

        async function loadAssets() {
            try {
                const response = await fetch(API_ENDPOINT, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });

                if (response.status === 401) {
                    showError('🔐', '未授权', '会话已过期，请刷新页面重新登录');
                    return;
                }

                const result = await response.json();

                if (result.success) {
                    allLocations = result.data.locations || [];
                    document.getElementById('skeleton-loader').classList.add('hidden');
                    document.getElementById('asset-list').classList.remove('hidden');
                    render();
                } else {
                    let icon = '⚠️', title = '加载失败', message = result.message || '请稍后再试';
                    if (result.error === 'connection_timeout') { icon = '🔄'; title = '连接超时'; message = 'EVE API 可能不可用，请稍后再试'; }
                    else if (result.error === 'token_expired') { icon = '🔐'; title = 'Token 过期'; message = '请刷新 Token 或重新授权'; }
                    showError(icon, title, message);
                }
            } catch (error) {
                console.error('加载资产失败:', error);
                showError('⚠️', '加载失败', '网络错误，请刷新页面重试');
            }
        }

        function showError(icon, title, message) {
            document.getElementById('skeleton-loader').classList.add('hidden');
            const container = document.getElementById('asset-list');
            container.classList.remove('hidden');
            container.innerHTML = '';
            const wrapper = document.createElement('div');
            wrapper.className = 'text-center py-12';
            const iconEl = document.createElement('div');
            iconEl.className = 'text-6xl mb-4';
            iconEl.textContent = icon;
            const titleEl = document.createElement('p');
            titleEl.className = 'text-xl text-blue-300 mb-2';
            titleEl.textContent = title;
            const msgEl = document.createElement('p');
            msgEl.className = 'text-blue-400';
            msgEl.textContent = message;
            wrapper.appendChild(iconEl);
            wrapper.appendChild(titleEl);
            wrapper.appendChild(msgEl);
            container.appendChild(wrapper);
        }

        function setupSearch() {
            const searchInput = document.getElementById('search-input');
            searchInput.addEventListener('input', (e) => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    const oldKw = searchKeyword;
                    searchKeyword = e.target.value.trim();
                    if (!searchKeyword && oldKw) {
                        expandedSet.clear(); // 清除搜索时恢复折叠
                    }
                    render();
                }, 300);
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            loadAssets();
            setupSearch();
        });
    </script>
</body>
</html>
