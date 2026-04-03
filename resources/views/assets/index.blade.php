@extends('layouts.app')

@section('page-title', '资产')

@push('styles')
<style>
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
    @keyframes spin { to { transform: rotate(360deg); } }
    .spinner {
        border: 2px solid rgba(255,255,255,0.1);
        border-top-color: #60a5fa;
        border-radius: 50%;
        width: 20px; height: 20px;
        animation: spin 0.8s linear infinite;
        display: inline-block;
    }
</style>
@endpush

@section('title', '我的资产 - Tus Esi System')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold mb-2">📦 我的资产</h1>
                <p class="text-blue-300">查看和管理你的角色资产</p>
            </div>
            <div>
                <input type="text" id="search-input" placeholder="搜索物品名称（至少2个字）..."
                       class="bg-white/10 border border-white/20 rounded-lg px-4 py-2 text-white placeholder-blue-300 focus:outline-none focus:border-blue-400 w-72">
            </div>
        </div>

        <div id="asset-container">
            <div id="skeleton-loader">
                <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow mb-4">
                    <div class="skeleton h-6 w-1/3 mb-4"></div>
                    <div class="skeleton h-4 w-full mb-2"></div>
                    <div class="skeleton h-4 w-3/4"></div>
                </div>
                <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow mb-4">
                    <div class="skeleton h-6 w-1/4 mb-4"></div>
                    <div class="skeleton h-4 w-full mb-2"></div>
                </div>
            </div>
            <div id="asset-list" class="hidden"></div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const API_LOCATIONS = '/api/dashboard/assets/locations';
    const API_LOCATION_ITEMS = '/api/dashboard/assets/location/';
    const API_SEARCH = '/api/dashboard/assets/search';

    let allSolarSystems = [];
    let searchResults = null;
    const locationItems = {};
    const locationLoading = {};
    const locationLoaded = {};
    const expandedSystems = new Set();
    const expandedLocations = new Set();
    const expandedItems = new Set();
    let searchKeyword = '';
    let debounceTimer = null;
    let isSearchLoading = false;

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

    function flagName(flag) { return FLAG_NAMES[flag] || flag; }
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

    function toggleSystem(systemId) {
        if (expandedSystems.has(systemId)) expandedSystems.delete(systemId);
        else expandedSystems.add(systemId);
        render();
    }

    function toggleLocation(locId) {
        if (expandedLocations.has(locId)) {
            expandedLocations.delete(locId);
        } else {
            expandedLocations.add(locId);
            if (!locationLoaded[locId] && !locationLoading[locId]) {
                loadLocationItems(locId);
            }
        }
        render();
    }

    function toggleItem(itemId) {
        if (expandedItems.has(itemId)) collapseItemRecursive(itemId);
        else expandedItems.add(itemId);
        render();
    }

    function collapseItemRecursive(itemId) {
        expandedItems.delete(itemId);
        const findChildren = (items) => {
            for (const item of items) {
                if (item.item_id === itemId && item.children) {
                    for (const child of item.children) collapseItemRecursive(child.item_id);
                    return;
                }
                if (item.children) findChildren(item.children);
            }
        };
        for (const locId of Object.keys(locationItems)) {
            if (locationItems[locId]) findChildren(locationItems[locId]);
        }
    }

    function renderHangarSections(items) {
        const ships = items.filter(i => i.category_id === 6);
        const others = items.filter(i => i.category_id !== 6);
        let html = '';

        const tableHead = '<table class="w-full text-sm"><thead><tr class="border-b border-white/10 text-blue-300">'
            + '<th class="py-2 px-3 text-left font-medium" style="min-width:100px">数量</th>'
            + '<th class="py-2 px-3 text-left font-medium">类型</th>'
            + '<th class="py-2 px-3 text-right font-medium" style="min-width:90px">体积</th>'
            + '<th class="py-2 px-3 text-left font-medium" style="min-width:100px">分组</th>'
            + '</tr></thead><tbody>';

        if (ships.length > 0) {
            html += '<div class="px-4 py-2 bg-indigo-500/10 border-b border-white/10 flex items-center gap-2">';
            html += '<span class="text-base">🚀</span><span class="text-sm font-medium text-indigo-300">舰船机库</span>';
            html += '<span class="text-xs text-indigo-300/60">(' + ships.length + ')</span>';
            html += '</div>';
            html += tableHead;
            html += renderItemRows(ships, 0);
            html += '</tbody></table>';
        }

        if (others.length > 0) {
            html += '<div class="px-4 py-2 bg-emerald-500/10 border-b border-white/10 flex items-center gap-2">';
            html += '<span class="text-base">📦</span><span class="text-sm font-medium text-emerald-300">物品机库</span>';
            html += '<span class="text-xs text-emerald-300/60">(' + others.length + ')</span>';
            html += '</div>';
            html += tableHead;
            html += renderItemRows(others, 0);
            html += '</tbody></table>';
        }

        return html;
    }

    function renderItemRows(items, depth) {
        let html = '';
        for (const item of items) {
            const hasChildren = item.children && item.children.length > 0;
            const isExpanded = expandedItems.has(item.item_id);
            const indent = depth * 24;
            const singletonTag = item.is_singleton ? '' : '<span class="text-yellow-500 text-xs ml-1">(packaged)</span>';

            html += '<tr class="border-b border-white/5 hover:bg-white/5 transition-all">';
            html += '<td class="py-2 px-3 whitespace-nowrap" style="padding-left:' + (12 + indent) + 'px">';
            if (hasChildren) {
                html += '<button onclick="toggleItem(' + item.item_id + ')" class="text-blue-400 hover:text-blue-300 mr-2 w-4 inline-block text-center font-bold">';
                html += isExpanded ? '\u2212' : '+';
                html += '</button>';
            } else if (depth > 0) {
                html += '<span class="mr-2 w-4 inline-block"></span>';
            }
            if (item.quantity > 1) html += '<span class="text-blue-300">' + formatNumber(item.quantity) + '</span>';
            else if (!hasChildren) html += '<span class="text-blue-300">1</span>';
            html += '</td>';
            html += '<td class="py-2 px-3">';
            html += '<span class="' + (hasChildren ? 'text-blue-400 font-medium' : '') + '">' + escapeHtml(item.type_name) + '</span>';
            html += singletonTag;
            if (depth > 0) html += '<span class="text-xs text-gray-400 ml-2">' + escapeHtml(flagName(item.location_flag)) + '</span>';
            html += '</td>';
            html += '<td class="py-2 px-3 text-right text-sm text-blue-300 whitespace-nowrap">' + formatVolume(item.volume) + '</td>';
            html += '<td class="py-2 px-3 text-sm text-gray-400">' + escapeHtml(item.group_name || '') + '</td>';
            html += '</tr>';

            if (hasChildren && isExpanded) html += renderItemRows(item.children, depth + 1);
        }
        return html;
    }

    async function loadLocationItems(locId) {
        if (locationLoaded[locId] || locationLoading[locId]) return;
        locationLoading[locId] = true;
        render();
        try {
            const response = await fetch(API_LOCATION_ITEMS + locId, {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const result = await response.json();
            locationItems[locId] = (result.success ? result.data.items : []) || [];
        } catch (e) {
            console.error('加载位置物品失败:', locId, e);
            locationItems[locId] = [];
        }
        locationLoaded[locId] = true;
        locationLoading[locId] = false;
        render();
    }

    async function searchFromBackend(keyword) {
        if (!keyword || keyword.length < 2) {
            searchResults = null;
            isSearchLoading = false;
            render();
            return;
        }
        isSearchLoading = true;
        render();
        try {
            const response = await fetch(API_SEARCH + '?q=' + encodeURIComponent(keyword), {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const result = await response.json();
            searchResults = (result.success ? result.data.results : []) || [];
        } catch (e) {
            console.error('搜索失败:', e);
            searchResults = [];
        }
        isSearchLoading = false;
        render();
    }

    function render() {
        const container = document.getElementById('asset-list');

        if (allSolarSystems.length === 0 && !isSearchLoading) {
            container.innerHTML = '<div class="text-center py-12"><div class="text-6xl mb-4">📦</div><p class="text-blue-300">暂无资产数据</p></div>';
            return;
        }

        let html = '';

        // 搜索加载中
        if (isSearchLoading) {
            html += '<div class="bg-blue-500/10 border border-blue-400/30 rounded-lg px-4 py-3 mb-4 flex items-center">';
            html += '<span class="spinner mr-3" style="width:16px;height:16px;"></span>';
            html += '<span class="text-blue-300 text-sm">正在搜索...</span>';
            html += '</div>';
        }

        // === 搜索结果模式 ===
        if (searchResults !== null) {
            if (searchResults.length === 0 && !isSearchLoading) {
                html += '<div class="text-center py-12"><div class="text-6xl mb-4">\uD83D\uDD0D</div><p class="text-blue-300">没有找到匹配的物品</p></div>';
            } else if (!isSearchLoading) {
                let totalTypes = 0;
                for (const r of searchResults) totalTypes += r.items.length;
                html += '<div class="text-sm text-blue-300/70 mb-3">在 ' + searchResults.length + ' 个位置找到 ' + totalTypes + ' 种匹配物品</div>';
                for (const loc of searchResults) {
                    const locId = loc.location_id;
                    const locExpanded = expandedLocations.has(locId);
                    const loaded = locationLoaded[locId];
                    const loading = locationLoading[locId];
                    const items = locationItems[locId] || [];

                    html += '<div class="bg-white/5 rounded-lg overflow-hidden mb-3">';
                    // 位置卡片头部（可折叠）
                    html += '<div onclick="toggleLocation(' + locId + ')" class="px-5 py-3 cursor-pointer hover:bg-white/10 transition-all flex justify-between items-center">';
                    html += '<div class="flex items-center">';
                    html += '<span class="mr-2 text-blue-400 font-mono text-xs">' + (locExpanded ? '\u25BC' : '\u25B6') + '</span>';
                    html += '<span class="font-medium">\uD83C\uDFDB\uFE0F ' + escapeHtml(loc.location_name) + '</span>';
                    html += '</div>';
                    html += '<div class="flex items-center gap-3">';
                    if (loading) html += '<span class="spinner" style="width:16px;height:16px;"></span>';
                    else if (loaded) html += '<span class="text-xs text-green-400">\u2713</span>';
                    html += '<span class="text-sm text-blue-300">找到 ' + loc.items.length + ' 种匹配</span>';
                    html += '</div></div>';

                    if (locExpanded) {
                        html += '<div class="border-t border-white/10">';
                        if (loading) {
                            html += '<div class="flex items-center justify-center py-6 text-blue-300"><span class="spinner mr-3"></span>首次加载或数量较多时加载会比较缓慢</div>';
                        } else if (loaded && items.length > 0) {
                            // 完整树形视图（按机库分组）
                            html += renderHangarSections(items);
                        } else if (loaded) {
                            html += '<div class="text-center py-4 text-blue-300/60 text-sm">该位置没有物品</div>';
                        } else {
                            html += '<div class="text-center py-4 text-blue-300/60 text-sm">点击展开加载</div>';
                        }
                        // 折叠态匹配摘要（在完整树下方显示匹配预览）
                        if (loaded && items.length > 0) {
                            html += '<div class="border-t border-white/10 px-5 py-2 bg-yellow-500/5">';
                            html += '<div class="text-xs text-yellow-300/70 mb-1">匹配物品预览：</div>';
                            html += '<div class="text-xs text-blue-300/70">';
                            const preview = loc.items.slice(0, 5);
                            html += preview.map(i => escapeHtml(i.type_name) + ' x' + formatNumber(i.quantity)).join('、');
                            if (loc.items.length > 5) html += '...等 ' + loc.items.length + ' 种';
                            html += '</div></div>';
                        }
                        html += '</div>';
                    } else {
                        // 折叠态下显示匹配物品简要预览
                        html += '<div class="px-5 py-2 border-t border-white/5 text-xs text-blue-300/50">';
                        const preview = loc.items.slice(0, 3);
                        html += preview.map(i => escapeHtml(i.type_name) + ' x' + formatNumber(i.quantity)).join('、');
                        if (loc.items.length > 3) html += '...';
                        html += '</div>';
                    }
                    html += '</div>';
                }
            }
            container.innerHTML = html;
            return;
        }

        // === 正常树形模式 ===
        let visibleCount = 0;

        for (const sys of allSolarSystems) {
            const sysExpanded = expandedSystems.has(sys.system_id);
            visibleCount++;

            html += '<div class="mb-4">';
            html += '<div onclick="toggleSystem(' + sys.system_id + ')" class="bg-white/10 backdrop-blur-lg rounded-xl px-6 py-4 eve-glow cursor-pointer hover:bg-white/15 transition-all flex justify-between items-center">';
            html += '<div class="flex items-center">';
            html += '<span class="mr-3 text-blue-400 font-mono text-sm">' + (sysExpanded ? '\u25BC' : '\u25B6') + '</span>';
            html += '<span class="text-lg font-semibold">\u2604\uFE0F ' + escapeHtml(sys.system_name) + '</span>';
            html += '<span class="ml-3 text-xs text-gray-400">(' + sys.locations.length + ' 个位置)</span>';
            html += '</div>';
            html += '<span class="text-sm text-blue-300">' + formatNumber(sys.total_items) + ' 件物品</span>';
            html += '</div>';

            if (sysExpanded) {
                html += '<div class="ml-6 mt-2 space-y-2">';
                for (const loc of sys.locations) {
                    const locId = loc.location_id;
                    const locExpanded = expandedLocations.has(locId);
                    const loaded = locationLoaded[locId];
                    const loading = locationLoading[locId];
                    const items = locationItems[locId] || [];

                    html += '<div class="bg-white/5 rounded-lg overflow-hidden">';
                    html += '<div onclick="toggleLocation(' + locId + ')" class="px-5 py-3 cursor-pointer hover:bg-white/10 transition-all flex justify-between items-center">';
                    html += '<div class="flex items-center">';
                    html += '<span class="mr-2 text-blue-400 font-mono text-xs">' + (locExpanded ? '\u25BC' : '\u25B6') + '</span>';
                    html += '<span class="font-medium">\uD83C\uDFDB\uFE0F ' + escapeHtml(loc.location_name) + '</span>';
                    html += '</div>';
                    html += '<div class="flex items-center gap-3">';
                    if (loading) html += '<span class="spinner" style="width:16px;height:16px;"></span>';
                    else if (loaded) html += '<span class="text-xs text-green-400">\u2713</span>';
                    html += '<span class="text-sm text-blue-300">' + formatNumber(loc.item_count) + ' 件</span>';
                    html += '</div></div>';

                    if (locExpanded) {
                        html += '<div class="border-t border-white/10">';
                        if (loading) {
                            html += '<div class="flex items-center justify-center py-6 text-blue-300"><span class="spinner mr-3"></span>首次加载或数量较多时加载会比较缓慢</div>';
                        } else if (loaded && items.length > 0) {
                            html += renderHangarSections(items);
                        } else if (loaded) {
                            html += '<div class="text-center py-4 text-blue-300/60 text-sm">该位置没有物品</div>';
                        } else {
                            html += '<div class="text-center py-4 text-blue-300/60 text-sm">点击展开加载</div>';
                        }
                        html += '</div>';
                    }
                    html += '</div>';
                }
                html += '</div>';
            }
            html += '</div>';
        }

        if (visibleCount === 0) {
            html = '<div class="text-center py-12"><div class="text-6xl mb-4">📦</div><p class="text-blue-300">暂无资产数据</p></div>';
        }
        container.innerHTML = html;
    }

    async function loadLocations() {
        try {
            const response = await fetch(API_LOCATIONS, {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (response.status === 401) {
                showError('\uD83D\uDD10', '未授权', '会话已过期，请刷新页面重新登录');
                return;
            }
            const result = await response.json();
            if (result.success) {
                allSolarSystems = result.data.solar_systems || [];
                document.getElementById('skeleton-loader').classList.add('hidden');
                document.getElementById('asset-list').classList.remove('hidden');

                if (allSolarSystems.length === 1) {
                    const sys = allSolarSystems[0];
                    expandedSystems.add(sys.system_id);
                    if (sys.locations.length === 1) {
                        expandedLocations.add(sys.locations[0].location_id);
                        loadLocationItems(sys.locations[0].location_id);
                    } else if (sys.locations.length > 1) {
                        const maxLoc = sys.locations.reduce((a, b) => a.item_count > b.item_count ? a : b);
                        if (maxLoc.item_count / sys.total_items > 0.6) {
                            expandedLocations.add(maxLoc.location_id);
                            loadLocationItems(maxLoc.location_id);
                        }
                    }
                }
                render();
            } else {
                showError('\u26A0\uFE0F', '加载失败', result.message || '请稍后再试');
            }
        } catch (error) {
            console.error('加载位置列表失败:', error);
            showError('\u26A0\uFE0F', '加载失败', '网络错误，请刷新页面重试');
        }
    }

    function showError(icon, title, message) {
        document.getElementById('skeleton-loader').classList.add('hidden');
        const container = document.getElementById('asset-list');
        container.classList.remove('hidden');
        container.innerHTML = '<div class="text-center py-12"><div class="text-6xl mb-4">' + icon + '</div><p class="text-xl text-blue-300 mb-2">' + escapeHtml(title) + '</p><p class="text-blue-400">' + escapeHtml(message) + '</p></div>';
    }

    function setupSearch() {
        const searchInput = document.getElementById('search-input');
        searchInput.addEventListener('input', (e) => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                searchKeyword = e.target.value.trim();
                if (searchKeyword && searchKeyword.length >= 2) {
                    searchFromBackend(searchKeyword);
                } else {
                    searchResults = null;
                    expandedItems.clear();
                    render();
                }
            }, 500);
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        loadLocations();
        setupSearch();
    });
    </script>
@endpush
