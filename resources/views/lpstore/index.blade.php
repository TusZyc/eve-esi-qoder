@extends('layouts.app')

@section('title', 'LP 商店 - Tus Esi System')
@section('page-title', 'LP 商店')

@push('styles')
    /* 下拉框选项样式 - 修复暗色主题下选项不可见问题 */
    select option {
        background-color: #0f1a3e;
        color: #ffffff;
    }
    .lp-table { table-layout: fixed; }
    .lp-table th { cursor: pointer; user-select: none; position: relative; }
    .lp-table th:hover { background: rgba(255,255,255,0.05); }
    .lp-table th.sortable::after { content: ''; margin-left: 4px; }
    .lp-table th.sort-asc::after { content: '▲'; color: #60a5fa; }
    .lp-table th.sort-desc::after { content: '▼'; color: #60a5fa; }
    .lp-table tbody tr:hover { background: rgba(255,255,255,0.1); }
    /* 列宽拖拽手柄 */
    .column-resizer {
        position: absolute; right: 0; top: 0; bottom: 0; width: 5px;
        cursor: col-resize; background: transparent; z-index: 10;
    }
    .column-resizer:hover, .column-resizer.resizing { background: rgba(96, 165, 250, 0.5); }
    .mini-spinner {
        display: inline-block; width: 14px; height: 14px;
        border: 2px solid rgba(255,255,255,0.1);
        border-top-color: #60a5fa;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .tooltip-trigger { position: relative; cursor: help; }
    .material-tooltip {
        position: fixed; z-index: 99999;
        background: rgba(15, 23, 42, 0.98); border: 1px solid rgba(96, 165, 250, 0.4);
        border-radius: 8px; padding: 12px 16px; font-size: 12px;
        backdrop-filter: blur(12px); box-shadow: 0 8px 32px rgba(0,0,0,0.6);
        min-width: 300px; pointer-events: none;
    }
    .modal-backdrop {
        position: fixed; inset: 0; background: rgba(0,0,0,0.7);
        backdrop-filter: blur(4px); z-index: 100;
    }
    .modal-content {
        position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
        background: rgba(15, 23, 42, 0.98); border: 1px solid rgba(255,255,255,0.2);
        border-radius: 12px; z-index: 101; max-width: 900px; width: 95%;
        max-height: 85vh; overflow: hidden; display: flex; flex-direction: column;
    }
    .page-btn { min-width: 36px; }
    .page-btn.active { background: rgba(59, 130, 246, 0.5); }
@endpush

@section('content')
    <div class="container mx-auto px-4 py-6 max-w-7xl">
        <!-- 标题区 -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-blue-300">LP 忠诚点利润计算器</h1>
            <p class="text-sm text-blue-200/60 mt-1">选择势力和公司，查看 LP 商店物品的利润分析</p>
            <p class="text-xs text-yellow-400/70 mt-1">ℹ️ 价格基于全服加权均价计算，实际交易价格可能略有差异</p>
        </div>

        <!-- 选择器区 -->
        <div class="bg-white/5 backdrop-blur rounded-xl p-4 mb-4 border border-white/10">
            <div class="flex flex-wrap items-center gap-4">
                <!-- 第一行：势力和公司选择 -->
                <div class="flex items-center gap-3 flex-1">
                    <div class="flex-1 max-w-xs">
                        <label class="block text-xs text-blue-200/70 mb-1">势力</label>
                        <select id="factionSelect" class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm text-white outline-none focus:border-blue-400">
                            <option value="">-- 选择势力 --</option>
                        </select>
                    </div>
                    <div class="text-white/30 pt-5">→</div>
                    <div class="flex-1 max-w-xs">
                        <label class="block text-xs text-blue-200/70 mb-1">公司</label>
                        <select id="corporationSelect" class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm text-white outline-none focus:border-blue-400" disabled>
                            <option value="">-- 先选择势力 --</option>
                        </select>
                    </div>
                </div>
                <!-- 星域选择 -->
                <div class="w-48 relative z-20">
                    <label class="block text-xs text-blue-200/70 mb-1">星域（价格来源）</label>
                    <select id="regionSelect" class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm text-white outline-none focus:border-blue-400 relative z-20">
                        @foreach($marketRegions ?? [] as $regionId => $regionName)
                            <option value="{{ $regionId }}" {{ ($defaultRegion ?? 10000002) == $regionId ? 'selected' : '' }}>{{ $regionName }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <!-- 第二行：价格计算方式选项 -->
            <div class="flex flex-wrap items-center gap-4 mt-3 pt-3 border-t border-white/10">
                <div class="w-48">
                    <label class="block text-xs text-blue-200/70 mb-1">材料成本计算方式</label>
                    <select id="materialPriceMode" class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm text-white outline-none focus:border-blue-400">
                        <option value="default">默认（全服均价）</option>
                        <option value="buy">收单价（吉他4最高买单）</option>
                        <option value="sell">卖单价（吉他4最低卖单）</option>
                    </select>
                </div>
                <div class="w-48">
                    <label class="block text-xs text-blue-200/70 mb-1">产出单价计算方式</label>
                    <select id="outputPriceMode" class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm text-white outline-none focus:border-blue-400">
                        <option value="default">默认（全服均价）</option>
                        <option value="buy">收单价（吉他4最高买单）</option>
                        <option value="sell">卖单价（吉他4最低卖单）</option>
                    </select>
                </div>
                <div class="flex-1 min-w-[200px]">
                    <p class="text-xs text-yellow-400/80 leading-relaxed pt-5">
                        ⚠️ 若材料/产出在吉他没有收单/卖单，计算结果可能有较大误差，建议使用默认模式以均价参考
                    </p>
                </div>
            </div>
        </div>

        <!-- 搜索区 -->
        <div class="mb-4">
            <input type="text" id="searchInput" placeholder="搜索物品名称..." 
                class="w-full bg-white/10 border border-white/20 rounded-lg px-4 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 placeholder-blue-300/50">
        </div>

        <!-- 初始状态提示 -->
        <div id="emptyState" class="bg-white/5 backdrop-blur rounded-xl p-12 border border-white/10 text-center">
            <div class="text-5xl mb-4">💎</div>
            <p class="text-blue-200/70 text-lg">请选择势力和公司</p>
            <p class="text-blue-200/40 text-sm mt-2">选择后将显示该公司 LP 商店的物品利润分析</p>
        </div>

        <!-- 加载状态 -->
        <div id="loadingState" class="hidden">
            <div class="bg-white/5 backdrop-blur rounded-xl p-4 border border-white/10">
                <div class="space-y-3">
                    <div class="skeleton h-10 w-full"></div>
                    <div class="skeleton h-8 w-full"></div>
                    <div class="skeleton h-8 w-full"></div>
                    <div class="skeleton h-8 w-full"></div>
                    <div class="skeleton h-8 w-full"></div>
                    <div class="skeleton h-8 w-3/4"></div>
                </div>
            </div>
        </div>

        <!-- 数据表格 -->
        <div id="tableContainer" class="hidden">
            <div class="bg-white/5 backdrop-blur rounded-xl border border-white/10" style="overflow: visible;">
                <div class="overflow-x-auto" style="overflow-y: visible;">
                    <table class="w-full text-sm lp-table" id="lpTable">
                        <thead>
                            <tr class="text-blue-300 border-b border-white/10 text-xs">
                                <th class="px-2 py-3 text-left sortable" data-sort="item_name" style="width:200px">物品</th>
                                <th class="px-2 py-3 text-right sortable" data-sort="quantity" style="width:60px">数量</th>
                                <th class="px-2 py-3 text-right sortable" data-sort="lp_cost" style="width:80px">LP成本</th>
                                <th class="px-2 py-3 text-right sortable" data-sort="isk_cost" style="width:90px">ISK成本</th>
                                <th class="px-2 py-3 text-right sortable" data-sort="material_cost" style="width:90px">材料成本</th>
                                <th class="px-2 py-3 text-right sortable" data-sort="unit_price" style="width:90px">单价</th>
                                <th class="px-2 py-3 text-right sortable" data-sort="revenue" style="width:90px">收入</th>
                                <th class="px-2 py-3 text-right sortable" data-sort="profit" style="width:90px">利润</th>
                                <th class="px-2 py-3 text-right sortable" data-sort="volume_7d" style="width:90px">交易量(7d)</th>
                                <th class="px-2 py-3 text-right sortable" data-sort="turnover_7d" style="width:90px">出货指数</th>
                                <th class="px-2 py-3 text-right sortable sort-desc" data-sort="lp_ratio" style="width:80px">LP比例</th>
                                <th class="px-2 py-3 text-center" style="width:60px">订单</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>
            </div>

            <!-- 分页 -->
            <div class="flex items-center justify-between mt-4 text-sm">
                <div id="pageInfo" class="text-blue-200/60"></div>
                <div id="pagination" class="flex items-center gap-1"></div>
            </div>
        </div>
    </div>

    <!-- 订单弹窗 -->
    <div id="orderModal" class="hidden">
        <div class="modal-backdrop" onclick="closeOrderModal()"></div>
        <div class="modal-content">
            <div class="flex items-center justify-between px-5 py-3 border-b border-white/10">
                <h3 id="modalTitle" class="font-bold text-lg">物品订单</h3>
                <button onclick="closeOrderModal()" class="text-white/50 hover:text-white text-2xl leading-none">&times;</button>
            </div>
            <div id="modalContent" class="flex-1 overflow-y-auto p-5">
                <div class="text-center py-8"><div class="loading-spinner mx-auto"></div></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
// ==================== 全局状态 ====================
let factionsData = [];
let offersData = [];
let filteredData = [];
let historyData = {};
let currentSort = { field: 'lp_ratio', order: 'desc' };
let currentPage = 1;
const PAGE_SIZE = 25;
let currentRegionId = {{ $defaultRegion ?? 10000002 }};
let currentCorporationId = null;
let currentMaterialPriceMode = 'default';
let currentOutputPriceMode = 'default';

// 无订单物品集合（用于灰色按钮样式）
let noOrderTypeIds = new Set();

// 星域名称映射
const regionNames = @json($marketRegions ?? []);

// ==================== 工具函数 ====================
function formatNumber(num) {
    if (num === null || num === undefined) return '-';
    return Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function formatIsk(num) {
    if (num === null || num === undefined) return '-';
    const absNum = Math.abs(num);
    if (absNum >= 1e9) return (num / 1e9).toFixed(1) + 'B';
    if (absNum >= 1e6) return (num / 1e6).toFixed(1) + 'M';
    if (absNum >= 1e3) return (num / 1e3).toFixed(1) + 'K';
    return formatNumber(num);
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('zh-CN') + ' ' + d.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' });
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ==================== API 请求 ====================
async function loadFactions() {
    try {
        const resp = await fetch('/api/public/lp-store/factions');
        const result = await resp.json();
        if (result.success && result.data && result.data.factions) {
            factionsData = result.data.factions;
            renderFactionSelect();
        }
    } catch (e) {
        console.error('加载势力数据失败:', e);
    }
}

async function loadOffers() {
    if (!currentCorporationId) return;
    
    showLoading();
    
    try {
        let url = `/api/public/lp-store/offers?corporation_id=${currentCorporationId}&region_id=${currentRegionId}`;
        if (currentMaterialPriceMode !== 'default') {
            url += `&material_price_mode=${currentMaterialPriceMode}`;
        }
        if (currentOutputPriceMode !== 'default') {
            url += `&output_price_mode=${currentOutputPriceMode}`;
        }
        
        const resp = await fetch(url);
        const result = await resp.json();
        if (result.success) {
            offersData = result.data || [];
            // 计算单价
            offersData.forEach(item => {
                item.unit_price = item.quantity > 0 ? item.revenue / item.quantity : 0;
                item.volume_7d = null; // 待异步加载
                item.turnover_7d = null;
            });
            filterAndRender();
            showTable();
            // 异步加载交易数据
            loadHistoryData();
        } else {
            showEmpty();
        }
    } catch (e) {
        console.error('加载 LP 商店数据失败:', e);
        showEmpty();
    }
}

async function loadHistoryData() {
    if (!offersData.length) return;
    
    const typeIds = [...new Set(offersData.map(item => item.type_id))];
    if (!typeIds.length) return;
    
    try {
        const resp = await fetch(`/api/public/lp-store/history?region_id=${currentRegionId}&type_ids=${typeIds.join(',')}`);
        const result = await resp.json();
        if (result.success && result.data) {
            historyData = result.data;
            // 更新数据
            offersData.forEach(item => {
                const history = historyData[item.type_id];
                if (history) {
                    item.volume_7d = history.volume_7d || 0;
                    item.turnover_7d = history.turnover_7d || 0;
                }
            });
            // 重新渲染表格（仅更新交易量列）
            updateHistoryColumns();
        }
    } catch (e) {
        console.error('加载交易历史失败:', e);
    }
}

async function loadOrders(typeId, itemName) {
    const modal = document.getElementById('orderModal');
    const content = document.getElementById('modalContent');
    const title = document.getElementById('modalTitle');
    
    title.textContent = `${itemName} - ${regionNames[currentRegionId] || '未知星域'}`;
    content.innerHTML = '<div class="text-center py-8"><div class="loading-spinner mx-auto"></div></div>';
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    try {
        const resp = await fetch(`/api/public/lp-store/orders?region_id=${currentRegionId}&type_id=${typeId}`);
        const result = await resp.json();
        if (result.success) {
            const data = result.data;
            const sellOrders = data.sell_orders || [];
            const buyOrders = data.buy_orders || [];
            
            // 检查是否有订单，如果没有则标记并更新按钮样式
            if (sellOrders.length === 0 && buyOrders.length === 0) {
                noOrderTypeIds.add(typeId);
                updateOrderButtonStyle(typeId, true);
            }
            
            renderOrderModal(data);
        } else {
            content.innerHTML = '<div class="text-center py-8 text-red-400">加载失败</div>';
        }
    } catch (e) {
        console.error('加载订单失败:', e);
        content.innerHTML = '<div class="text-center py-8 text-red-400">网络错误</div>';
    }
}

// 更新订单按钮样式（无订单时变灰）
function updateOrderButtonStyle(typeId, hasNoOrder) {
    const btn = document.querySelector(`.order-btn[data-typeid="${typeId}"]`);
    if (btn) {
        if (hasNoOrder) {
            btn.className = 'order-btn px-3 py-1 bg-gray-600/50 text-gray-400 rounded text-xs cursor-default';
            btn.textContent = '无订单';
        } else {
            btn.className = 'order-btn px-3 py-1 bg-blue-600 hover:bg-blue-500 rounded text-xs transition-all';
            btn.textContent = '订单';
        }
    }
}

// ==================== 渲染函数 ====================
function renderFactionSelect() {
    const select = document.getElementById('factionSelect');
    let html = '<option value="">-- 选择势力 --</option>';
    factionsData.forEach(faction => {
        html += `<option value="${faction.faction_id}">${escapeHtml(faction.name)}</option>`;
    });
    select.innerHTML = html;
}

function renderCorporationSelect(factionId) {
    const select = document.getElementById('corporationSelect');
    if (!factionId) {
        select.innerHTML = '<option value="">-- 先选择势力 --</option>';
        select.disabled = true;
        return;
    }
    
    const faction = factionsData.find(f => f.faction_id == factionId);
    if (!faction || !faction.corporations) {
        select.innerHTML = '<option value="">-- 无可用公司 --</option>';
        select.disabled = true;
        return;
    }
    
    let html = '<option value="">-- 选择公司 --</option>';
    faction.corporations.forEach(corp => {
        html += `<option value="${corp.corporation_id}">${escapeHtml(corp.name)}</option>`;
    });
    select.innerHTML = html;
    select.disabled = false;
}

function renderTable() {
    const tbody = document.getElementById('tableBody');
    const start = (currentPage - 1) * PAGE_SIZE;
    const end = start + PAGE_SIZE;
    const pageData = filteredData.slice(start, end);
    
    if (!pageData.length) {
        tbody.innerHTML = '<tr><td colspan="12" class="text-center py-8 text-blue-200/50">暂无数据</td></tr>';
        return;
    }
    
    let html = '';
    pageData.forEach(item => {
        const profitClass = item.profit >= 0 ? 'text-green-400' : 'text-red-400';
        
        html += `<tr class="border-b border-white/5">`;
        // 物品（带图标）- 移除 truncate 和 max-w 限制，让文字跟随列宽
        html += `<td class="px-2 py-2">
            <div class="flex items-center gap-2">
                <img src="https://images.evetech.net/types/${item.type_id}/icon?size=32" class="w-6 h-6 rounded flex-shrink-0" alt="" onerror="this.style.display='none'">
                <span style="display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${escapeHtml(item.item_name)}">${escapeHtml(item.item_name)}</span>
            </div>
        </td>`;
        // 数量
        html += `<td class="px-2 py-2 text-right">${formatNumber(item.quantity)}</td>`;
        // LP成本
        html += `<td class="px-2 py-2 text-right">${formatNumber(item.lp_cost)}</td>`;
        // ISK成本
        html += `<td class="px-2 py-2 text-right">${formatNumber(item.isk_cost)}</td>`;
        // 材料成本（带tooltip）
        const materialData = renderMaterialTooltip(item.materials_detail);
        html += `<td class="px-2 py-2 text-right">
            <span class="tooltip-trigger" ${materialData}>${formatNumber(item.material_cost)}</span>
        </td>`;
        // 单价
        html += `<td class="px-2 py-2 text-right">${formatNumber(item.unit_price)}</td>`;
        // 收入
        html += `<td class="px-2 py-2 text-right">${formatNumber(item.revenue)}</td>`;
        // 利润
        html += `<td class="px-2 py-2 text-right ${profitClass}">${formatNumber(item.profit)}</td>`;
        // 交易量(7d)
        html += `<td class="px-2 py-2 text-right" data-typeid="${item.type_id}" data-field="volume">${item.volume_7d !== null ? formatNumber(item.volume_7d) : '<span class="mini-spinner"></span>'}</td>`;
        // 出货指数
        html += `<td class="px-2 py-2 text-right" data-typeid="${item.type_id}" data-field="turnover">${item.turnover_7d !== null ? formatIsk(item.turnover_7d) : '<span class="mini-spinner"></span>'}</td>`;
        // LP比例
        html += `<td class="px-2 py-2 text-right text-yellow-300 font-medium">${formatNumber(item.lp_ratio)}</td>`;
        // 订单按钮 - 使用 data 属性避免转义问题，无订单时显示灰色样式
        const hasNoOrder = noOrderTypeIds.has(item.type_id);
        const btnClass = hasNoOrder 
            ? 'order-btn px-3 py-1 bg-gray-600/50 text-gray-400 rounded text-xs cursor-default' 
            : 'order-btn px-3 py-1 bg-blue-600 hover:bg-blue-500 rounded text-xs transition-all';
        const btnText = hasNoOrder ? '无订单' : '订单';
        html += `<td class="px-2 py-2 text-center">
            <button class="${btnClass}" data-typeid="${item.type_id}" data-itemname="${escapeHtml(item.item_name)}">${btnText}</button>
        </td>`;
        html += '</tr>';
    });
    
    tbody.innerHTML = html;
    renderPagination();
}

function renderMaterialTooltip(materials) {
    if (!materials || !materials.length) return '';
    // 将材料数据存入 data 属性，由 JS 事件处理显示
    return `data-materials='${JSON.stringify(materials).replace(/'/g, "&apos;")}'`;
}

function renderPagination() {
    const totalPages = Math.ceil(filteredData.length / PAGE_SIZE);
    const pageInfo = document.getElementById('pageInfo');
    const pagination = document.getElementById('pagination');
    
    const start = (currentPage - 1) * PAGE_SIZE + 1;
    const end = Math.min(currentPage * PAGE_SIZE, filteredData.length);
    pageInfo.textContent = `显示 ${start}-${end} 条，共 ${filteredData.length} 条`;
    
    let html = '';
    // 上一页
    html += `<button class="page-btn px-3 py-1 rounded bg-white/10 hover:bg-white/20 transition-all ${currentPage === 1 ? 'opacity-50 cursor-not-allowed' : ''}" onclick="goToPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>上一页</button>`;
    
    // 页码
    const maxVisible = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let endPage = Math.min(totalPages, startPage + maxVisible - 1);
    if (endPage - startPage < maxVisible - 1) {
        startPage = Math.max(1, endPage - maxVisible + 1);
    }
    
    if (startPage > 1) {
        html += `<button class="page-btn px-3 py-1 rounded bg-white/10 hover:bg-white/20 transition-all" onclick="goToPage(1)">1</button>`;
        if (startPage > 2) html += '<span class="px-2 text-white/30">...</span>';
    }
    
    for (let i = startPage; i <= endPage; i++) {
        html += `<button class="page-btn px-3 py-1 rounded transition-all ${i === currentPage ? 'active' : 'bg-white/10 hover:bg-white/20'}" onclick="goToPage(${i})">${i}</button>`;
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) html += '<span class="px-2 text-white/30">...</span>';
        html += `<button class="page-btn px-3 py-1 rounded bg-white/10 hover:bg-white/20 transition-all" onclick="goToPage(${totalPages})">${totalPages}</button>`;
    }
    
    // 下一页
    html += `<button class="page-btn px-3 py-1 rounded bg-white/10 hover:bg-white/20 transition-all ${currentPage === totalPages || totalPages === 0 ? 'opacity-50 cursor-not-allowed' : ''}" onclick="goToPage(${currentPage + 1})" ${currentPage === totalPages || totalPages === 0 ? 'disabled' : ''}>下一页</button>`;
    
    pagination.innerHTML = html;
}

function renderOrderModal(data) {
    const content = document.getElementById('modalContent');
    let html = '';
    
    // 卖出订单
    html += '<div class="mb-6">';
    html += '<h4 class="text-green-400 font-bold mb-3 text-base">💰 卖出订单</h4>';
    if (data.sell_orders && data.sell_orders.length) {
        html += '<div class="overflow-x-auto"><table class="w-full text-sm">';
        html += '<thead><tr class="text-blue-300 border-b border-white/10 text-xs"><th class="px-3 py-2 text-right">价格</th><th class="px-3 py-2 text-right">数量</th><th class="px-3 py-2 text-left">空间站</th><th class="px-3 py-2 text-right">过期时间</th></tr></thead>';
        html += '<tbody>';
        data.sell_orders.sort((a, b) => a.price - b.price).forEach(order => {
            html += `<tr class="border-b border-white/5 hover:bg-white/5">
                <td class="px-3 py-2 text-right text-green-400">${formatNumber(order.price)}</td>
                <td class="px-3 py-2 text-right">${formatNumber(order.volume_remain)}</td>
                <td class="px-3 py-2 text-left text-xs text-blue-200/70 truncate max-w-[250px]" title="${escapeHtml(order.location_name)}">${escapeHtml(order.location_name || '-')}</td>
                <td class="px-3 py-2 text-right text-xs text-blue-200/50">${formatDate(order.expires_at)}</td>
            </tr>`;
        });
        html += '</tbody></table></div>';
    } else {
        html += '<p class="text-blue-200/50 text-sm">暂无卖出订单</p>';
    }
    html += '</div>';
    
    // 买入订单
    html += '<div>';
    html += '<h4 class="text-blue-400 font-bold mb-3 text-base">📋 买入订单</h4>';
    if (data.buy_orders && data.buy_orders.length) {
        html += '<div class="overflow-x-auto"><table class="w-full text-sm">';
        html += '<thead><tr class="text-blue-300 border-b border-white/10 text-xs"><th class="px-3 py-2 text-right">价格</th><th class="px-3 py-2 text-right">数量</th><th class="px-3 py-2 text-left">空间站</th><th class="px-3 py-2 text-right">过期时间</th></tr></thead>';
        html += '<tbody>';
        data.buy_orders.sort((a, b) => b.price - a.price).forEach(order => {
            html += `<tr class="border-b border-white/5 hover:bg-white/5">
                <td class="px-3 py-2 text-right text-blue-400">${formatNumber(order.price)}</td>
                <td class="px-3 py-2 text-right">${formatNumber(order.volume_remain)}</td>
                <td class="px-3 py-2 text-left text-xs text-blue-200/70 truncate max-w-[250px]" title="${escapeHtml(order.location_name)}">${escapeHtml(order.location_name || '-')}</td>
                <td class="px-3 py-2 text-right text-xs text-blue-200/50">${formatDate(order.expires_at)}</td>
            </tr>`;
        });
        html += '</tbody></table></div>';
    } else {
        html += '<p class="text-blue-200/50 text-sm">暂无买入订单</p>';
    }
    html += '</div>';
    
    content.innerHTML = html;
}

function updateHistoryColumns() {
    offersData.forEach(item => {
        const volumeCell = document.querySelector(`td[data-typeid="${item.type_id}"][data-field="volume"]`);
        const turnoverCell = document.querySelector(`td[data-typeid="${item.type_id}"][data-field="turnover"]`);
        if (volumeCell && item.volume_7d !== null) {
            volumeCell.textContent = formatNumber(item.volume_7d);
        }
        if (turnoverCell && item.turnover_7d !== null) {
            turnoverCell.textContent = formatIsk(item.turnover_7d);
        }
    });
}

// ==================== 状态切换 ====================
function showEmpty() {
    document.getElementById('emptyState').classList.remove('hidden');
    document.getElementById('loadingState').classList.add('hidden');
    document.getElementById('tableContainer').classList.add('hidden');
}

function showLoading() {
    document.getElementById('emptyState').classList.add('hidden');
    document.getElementById('loadingState').classList.remove('hidden');
    document.getElementById('tableContainer').classList.add('hidden');
}

function showTable() {
    document.getElementById('emptyState').classList.add('hidden');
    document.getElementById('loadingState').classList.add('hidden');
    document.getElementById('tableContainer').classList.remove('hidden');
    // 初始化列宽拖拽
    setTimeout(initColumnResize, 50);
}

// ==================== 排序和过滤 ====================
function filterAndRender() {
    const keyword = document.getElementById('searchInput').value.trim().toLowerCase();
    
    // 过滤
    if (keyword) {
        filteredData = offersData.filter(item => 
            item.item_name && item.item_name.toLowerCase().includes(keyword)
        );
    } else {
        filteredData = [...offersData];
    }
    
    // 排序
    sortData();
    
    // 重置页码
    currentPage = 1;
    renderTable();
}

function sortData() {
    const { field, order } = currentSort;
    
    filteredData.sort((a, b) => {
        let va = a[field];
        let vb = b[field];
        
        // 处理 null 值
        if (va === null || va === undefined) va = -Infinity;
        if (vb === null || vb === undefined) vb = -Infinity;
        
        // 字符串比较
        if (typeof va === 'string') {
            return order === 'asc' ? va.localeCompare(vb) : vb.localeCompare(va);
        }
        
        // 数字比较
        return order === 'asc' ? va - vb : vb - va;
    });
}

function handleSort(field) {
    const headers = document.querySelectorAll('.lp-table th.sortable');
    
    // 更新排序状态
    if (currentSort.field === field) {
        // 切换顺序：asc → desc → none → asc
        if (currentSort.order === 'asc') {
            currentSort.order = 'desc';
        } else if (currentSort.order === 'desc') {
            currentSort = { field: 'lp_ratio', order: 'desc' }; // 回到默认
        }
    } else {
        currentSort = { field, order: 'asc' };
    }
    
    // 更新表头样式
    headers.forEach(th => {
        th.classList.remove('sort-asc', 'sort-desc');
        if (th.dataset.sort === currentSort.field) {
            th.classList.add(currentSort.order === 'asc' ? 'sort-asc' : 'sort-desc');
        }
    });
    
    filterAndRender();
}

function goToPage(page) {
    const totalPages = Math.ceil(filteredData.length / PAGE_SIZE);
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    renderTable();
}

function closeOrderModal() {
    document.getElementById('orderModal').classList.add('hidden');
    document.body.style.overflow = '';
}

// ==================== 事件绑定 ====================
document.getElementById('factionSelect').addEventListener('change', function() {
    renderCorporationSelect(this.value);
    currentCorporationId = null;
    showEmpty();
});

document.getElementById('corporationSelect').addEventListener('change', function() {
    currentCorporationId = this.value;
    if (currentCorporationId) {
        loadOffers();
    } else {
        showEmpty();
    }
});

document.getElementById('regionSelect').addEventListener('change', function() {
    currentRegionId = this.value;
    // 切换星域时清空无订单记录（不同星域订单不同）
    noOrderTypeIds.clear();
    if (currentCorporationId) {
        loadOffers();
    }
});

document.getElementById('materialPriceMode').addEventListener('change', function() {
    currentMaterialPriceMode = this.value;
    if (currentCorporationId) {
        loadOffers();
    }
});

document.getElementById('outputPriceMode').addEventListener('change', function() {
    currentOutputPriceMode = this.value;
    if (currentCorporationId) {
        loadOffers();
    }
});

document.getElementById('searchInput').addEventListener('input', function() {
    filterAndRender();
});

// 表头排序点击
document.querySelectorAll('.lp-table th.sortable').forEach(th => {
    th.addEventListener('click', function() {
        handleSort(this.dataset.sort);
    });
});

// ESC 关闭弹窗
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeOrderModal();
});

// 订单按钮事件委托（避免 onclick 转义问题）
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.order-btn');
    if (btn) {
        const typeId = parseInt(btn.dataset.typeid, 10);
        const itemName = btn.dataset.itemname || '';
        loadOrders(typeId, itemName);
    }
});

// 初始化列宽拖拽功能
function initColumnResize() {
    const table = document.getElementById('lpTable');
    if (!table) return;
    
    const headers = table.querySelectorAll('thead th');
    headers.forEach((th, index) => {
        // 不给最后一列添加拖拽手柄
        if (index === headers.length - 1) return;
        
        // 检查是否已添加
        if (th.querySelector('.column-resizer')) return;
        
        const resizer = document.createElement('div');
        resizer.className = 'column-resizer';
        th.appendChild(resizer);
        
        let startX, startWidth, isResizing = false;
        
        resizer.addEventListener('mousedown', function(e) {
            startX = e.pageX;
            startWidth = th.offsetWidth;
            isResizing = true;
            resizer.classList.add('resizing');
            
            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
            e.preventDefault();
            e.stopPropagation();
        });
        
        function onMouseMove(e) {
            if (!isResizing) return;
            const newWidth = Math.max(40, startWidth + e.pageX - startX);
            th.style.width = newWidth + 'px';
        }
        
        function onMouseUp() {
            isResizing = false;
            resizer.classList.remove('resizing');
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
        }
    });
}

// ==================== 材料成本 Tooltip ====================
let materialTooltipEl = null;

function showMaterialTooltip(e, materials) {
    if (!materials || !materials.length) return;
    
    // 创建或获取 tooltip 元素
    if (!materialTooltipEl) {
        materialTooltipEl = document.createElement('div');
        materialTooltipEl.className = 'material-tooltip';
        document.body.appendChild(materialTooltipEl);
    }
    
    // 构建内容
    let html = '<div class="text-blue-300 font-medium mb-2 border-b border-white/20 pb-2">📦 材料明细</div>';
    html += '<table class="w-full">';
    materials.forEach(m => {
        html += `<tr>
            <td class="py-1 pr-4">${escapeHtml(m.name)} <span class="text-blue-300/70">x${formatNumber(m.quantity)}</span></td>
            <td class="py-1 text-right text-yellow-300">${formatNumber(m.total_cost)}</td>
        </tr>`;
    });
    html += '</table>';
    const total = materials.reduce((sum, m) => sum + (m.total_cost || 0), 0);
    html += `<div class="border-t border-white/20 mt-2 pt-2 text-right">
        <span class="text-blue-200/70">合计:</span> <span class="text-yellow-400 font-bold">${formatNumber(total)}</span>
    </div>`;
    
    materialTooltipEl.innerHTML = html;
    materialTooltipEl.style.display = 'block';
    
    // 计算位置 - 在鼠标右侧显示
    const rect = e.target.getBoundingClientRect();
    let left = rect.right + 10;
    let top = rect.top - 10;
    
    // 如果右侧空间不足，显示在左侧
    if (left + 320 > window.innerWidth) {
        left = rect.left - 320;
    }
    // 如果下方空间不足，向上调整
    if (top + materialTooltipEl.offsetHeight > window.innerHeight) {
        top = window.innerHeight - materialTooltipEl.offsetHeight - 10;
    }
    // 确保不超出顶部
    if (top < 10) top = 10;
    
    materialTooltipEl.style.left = left + 'px';
    materialTooltipEl.style.top = top + 'px';
}

function hideMaterialTooltip() {
    if (materialTooltipEl) {
        materialTooltipEl.style.display = 'none';
    }
}

// 材料成本 tooltip 事件委托
document.addEventListener('mouseover', function(e) {
    const trigger = e.target.closest('.tooltip-trigger[data-materials]');
    if (trigger) {
        try {
            const materials = JSON.parse(trigger.dataset.materials.replace(/&apos;/g, "'"));
            showMaterialTooltip(e, materials);
        } catch (err) {
            console.error('解析材料数据失败:', err);
        }
    }
});

document.addEventListener('mouseout', function(e) {
    const trigger = e.target.closest('.tooltip-trigger[data-materials]');
    if (trigger) {
        hideMaterialTooltip();
    }
});

// ==================== 初始化 ====================
document.addEventListener('DOMContentLoaded', function() {
    loadFactions();
});
</script>
@endpush
