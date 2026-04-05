@extends('layouts.app')

@section('page-title', '击毁查询')

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
    .ac-dropdown .ac-item:hover, .ac-dropdown .ac-item.active {
        background: rgba(59, 130, 246, 0.3);
    }
    .sec-high { color: #4ade80; }
    .sec-low { color: #facc15; }
    .sec-null { color: #f87171; }
    .item-dropped { color: #4ade80; }
    .item-destroyed { color: #f87171; }
</style>
@endpush

@section('title', 'KM 查询 - Tus Esi System')

@section('content')
    <div class="container mx-auto px-4 py-6 max-w-7xl">
        <!-- 搜索区域 -->
        <div class="bg-white/5 backdrop-blur rounded-xl p-6 mb-6 border border-white/10">
            <h2 class="text-lg font-bold mb-4">KM 高级搜索</h2>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- 左侧: 高级搜索表单 -->
                <div class="space-y-4">
                    <!-- 玩家/军团/联盟 -->
                    <div>
                        <label class="block text-sm text-blue-200 mb-1">玩家 / 军团 / 联盟</label>
                        <div class="flex">
                            <select id="entityTypeSelect" class="bg-white/10 border border-white/20 rounded-l-lg px-3 py-2 text-sm outline-none" style="min-width:90px">
                                <option value="character" class="bg-gray-800">角色</option>
                                <option value="corporation" class="bg-gray-800">军团</option>
                                <option value="alliance" class="bg-gray-800">联盟</option>
                            </select>
                            <div class="relative flex-1">
                                <input id="entityInput" type="text" placeholder="输入名称搜索..." class="w-full bg-white/10 border border-white/20 border-l-0 rounded-r-lg px-3 py-2 text-sm outline-none focus:border-blue-400" autocomplete="off">
                                <div id="entityDropdown" class="ac-dropdown hidden"></div>
                            </div>
                        </div>
                        <input type="hidden" id="entityId" value="">
                    </div>

                    <!-- 舰船或类别 -->
                    <div>
                        <label class="block text-sm text-blue-200 mb-1">舰船或类别</label>
                        <div class="relative">
                            <input id="shipInput" type="text" placeholder="输入舰船名称..." class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-400" autocomplete="off">
                            <div id="shipDropdown" class="ac-dropdown hidden"></div>
                        </div>
                        <input type="hidden" id="shipId" value="">
                    </div>

                    <!-- 位置 -->
                    <div>
                        <label class="block text-sm text-blue-200 mb-1">位置</label>
                        <div class="relative">
                            <input id="systemInput" type="text" placeholder="输入星系名称..." class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-400" autocomplete="off">
                            <div id="systemDropdown" class="ac-dropdown hidden"></div>
                        </div>
                        <input type="hidden" id="systemId" value="">
                    </div>

                    <!-- 时间范围 -->
                    <div>
                        <label class="block text-sm text-blue-200 mb-1">时间范围</label>
                        <div class="flex items-center space-x-2">
                            <input id="timeStart" type="datetime-local" step="1" class="bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-400 flex-1">
                            <span class="text-white/50">~</span>
                            <input id="timeEnd" type="datetime-local" step="1" class="bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-400 flex-1">
                        </div>
                    </div>

                    <!-- 参与类型 -->
                    <div>
                        <label class="block text-sm text-blue-200 mb-1">参与类型</label>
                        <div class="flex flex-wrap gap-4">
                            <label class="flex items-center space-x-1 cursor-pointer text-sm">
                                <input type="radio" name="involvement" value="victim" class="accent-blue-500"> <span>受害者</span>
                            </label>
                            <label class="flex items-center space-x-1 cursor-pointer text-sm">
                                <input type="radio" name="involvement" value="finalblow" class="accent-blue-500"> <span>最后一击</span>
                            </label>
                            <label class="flex items-center space-x-1 cursor-pointer text-sm">
                                <input type="radio" name="involvement" value="attacker" class="accent-blue-500"> <span>参与者</span>
                            </label>
                        </div>
                    </div>

                    <button id="searchBtn" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 rounded-lg transition-all">
                        搜索
                    </button>
                </div>

                <!-- 右侧: 直接查询 -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm text-blue-200 mb-1">直接查询 KM</label>
                        <input id="directInput" type="text" placeholder="输入 KM ID 或 ESI 链接..." class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-400">
                    </div>
                    <button id="directBtn" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2.5 rounded-lg transition-all">
                        查询 KM 详情
                    </button>
                    <div class="text-xs text-white/40 mt-2">
                        支持格式：<br>
                        - KM ID: 如 22395435<br>
                        - ESI 链接: 包含 killmails/ID/hash 的链接
                    </div>

                    <!-- KM 图片获取 [Claude Code] 2026-04-04 -->
                    <div class="mt-4 pt-4 border-t border-white/10">
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <label class="text-sm text-purple-300 font-bold">生成 KM 图片</label>
                        </div>
                        <div class="flex gap-2">
                            <input id="imageKmId" type="text" placeholder="输入 KM ID..." class="flex-1 bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm outline-none focus:border-purple-400">
                            <button id="generateImageBtn" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-lg transition-all text-sm whitespace-nowrap flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                生成
                            </button>
                        </div>

                        <!-- 生成状态 -->
                        <div id="imageGenStatus" class="hidden mt-2 text-xs text-purple-300/70"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 加载状态 -->
        <div id="loadingArea" class="hidden mb-6">
            <div class="bg-white/5 backdrop-blur rounded-xl p-8 border border-white/10 text-center">
                <div class="spinner mx-auto mb-3"></div>
                <p id="loadingText" class="text-blue-200 text-sm">正在搜索...</p>
            </div>
        </div>

        <!-- KM 列表 -->
        <div id="killListArea" class="hidden mb-6">
            <div class="bg-white/5 backdrop-blur rounded-xl border border-white/10 overflow-hidden">
                <div class="px-4 py-3 border-b border-white/10 flex justify-between items-center">
                    <h3 id="listTitle" class="font-bold text-sm">搜索结果</h3>
                    <span id="listCount" class="text-xs text-white/50"></span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-white/60 text-xs border-b border-white/10">
                                <th class="px-3 py-2 text-left">KM ID</th>
                                <th class="px-3 py-2 text-left">舰船</th>
                                <th class="px-3 py-2 text-left">受害者</th>
                                <th class="px-3 py-2 text-left">最后一击</th>
                                <th class="px-3 py-2 text-left">地点</th>
                                <th class="px-3 py-2 text-right">价值</th>
                            </tr>
                        </thead>
                        <tbody id="killListBody"></tbody>
                    </table>
                </div>
                <div id="loadMoreArea" class="text-center py-4 hidden">
                    <button id="loadMoreBtn" class="px-6 py-2 bg-blue-600/30 hover:bg-blue-600/50 border border-blue-400/30 rounded-lg text-blue-200 text-sm transition-all">
                        加载更多
                    </button>
                </div>
            </div>
        </div>

        <!-- KM 图片模态框 -->
        <div id="imageModal" class="fixed inset-0 z-[9999] hidden flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/85" onclick="closeImageModal()"></div>
            <div class="relative flex flex-col items-center max-w-5xl w-full">
                <div id="imageModalLoading" class="hidden text-center py-16">
                    <div class="spinner mx-auto mb-3"></div>
                    <p class="text-purple-300 text-sm">正在生成图片，首次生成需下载头像资源...</p>
                </div>
                <div id="imageModalContent" class="hidden flex flex-col items-center" style="max-height:90vh">
                    <img id="imageModalImg" src="" alt="KM图片" class="block rounded-lg shadow-2xl" style="max-height:calc(90vh - 72px); max-width:100%; width:auto; height:auto;">
                    <div class="flex gap-3 mt-3 justify-center shrink-0">
                        <a id="imageModalDownload" href="#" download class="px-5 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg text-sm text-white font-bold transition-all">⬇️ 下载图片</a>
                        <button onclick="openKmImageInTab()" class="px-5 py-2 bg-white/10 hover:bg-white/20 rounded-lg text-sm text-white/80 transition-all">🔗 新窗口打开</button>
                        <button onclick="closeImageModal()" class="px-5 py-2 bg-white/10 hover:bg-white/20 rounded-lg text-sm text-white/80 transition-all">关闭</button>
                    </div>
                </div>
                <div id="imageModalError" class="hidden bg-red-500/20 border border-red-500/30 rounded-xl p-6 text-center">
                    <p id="imageModalErrorText" class="text-red-300 text-sm mb-4"></p>
                    <button onclick="closeImageModal()" class="px-4 py-2 bg-white/10 rounded-lg text-sm text-white/80">关闭</button>
                </div>
            </div>
        </div>

        <!-- KM 详情模态框 -->
        <div id="detailModal" class="fixed inset-0 z-[9999] hidden flex items-start justify-center overflow-y-auto">
            <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" onclick="closeDetail()"></div>
            <div class="relative mx-auto my-4 md:my-8 w-full max-w-5xl max-h-[90vh] bg-slate-900/95 backdrop-blur rounded-xl border border-white/20 overflow-hidden flex flex-col">
                <!-- 模态框头部 -->
                <div class="flex justify-between items-center px-6 py-3 border-b border-white/10 shrink-0">
                    <h3 id="detailTitle" class="font-bold">KM 详情</h3>
                    <button onclick="closeDetail()" class="text-white/50 hover:text-white text-xl">&times;</button>
                </div>
                <!-- 模态框内容 -->
                <div id="detailContent" class="flex-1 overflow-y-auto p-6">
                    <div class="text-center py-12"><div class="spinner mx-auto"></div></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
var BETA_KB_URL = 'https://beta.ceve-market.org';
var debounceTimers = {};
var selectedEntities = { entity: null, ship: null, system: null };
var allKills = [];
var displayedCount = 0;
var PAGE_SIZE = 10;

// ========== 自动补全 ==========

function setupAutocomplete(inputId, dropdownId, hiddenId, typeGetter) {
    var input = document.getElementById(inputId);
    var dropdown = document.getElementById(dropdownId);
    var hidden = document.getElementById(hiddenId);
    var activeIdx = -1;

    input.addEventListener('input', function() {
        var q = input.value.trim();
        hidden.value = '';
        selectedEntities[hiddenId.replace('Id', '')] = null;

        if (q.length < 2) { dropdown.classList.add('hidden'); return; }

        clearTimeout(debounceTimers[inputId]);
        debounceTimers[inputId] = setTimeout(function() {
            var type = typeof typeGetter === 'function' ? typeGetter() : typeGetter;
            fetch('/api/killmails/autocomplete?q=' + encodeURIComponent(q) + '&type=' + type)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success || !data.data.length) {
                        dropdown.classList.add('hidden');
                        return;
                    }
                    activeIdx = -1;
                    dropdown.innerHTML = data.data.map(function(item, i) {
                        return '<div class="ac-item" data-idx="' + i + '" data-id="' + item.id + '" data-name="' + escapeHtml(item.name) + '">' + escapeHtml(item.name) + '</div>';
                    }).join('');
                    dropdown.classList.remove('hidden');

                    dropdown.querySelectorAll('.ac-item').forEach(function(el) {
                        el.addEventListener('click', function() {
                            input.value = el.dataset.name;
                            hidden.value = el.dataset.id;
                            dropdown.classList.add('hidden');
                        });
                    });
                });
        }, 300);
    });

    input.addEventListener('keydown', function(e) {
        var items = dropdown.querySelectorAll('.ac-item');
        if (!items.length || dropdown.classList.contains('hidden')) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIdx = Math.min(activeIdx + 1, items.length - 1);
            items.forEach(function(el, i) { el.classList.toggle('active', i === activeIdx); });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIdx = Math.max(activeIdx - 1, 0);
            items.forEach(function(el, i) { el.classList.toggle('active', i === activeIdx); });
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (activeIdx >= 0 && items[activeIdx]) {
                input.value = items[activeIdx].dataset.name;
                hidden.value = items[activeIdx].dataset.id;
                dropdown.classList.add('hidden');
            }
        }
    });

    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
}

// 类型选择切换时清空
document.getElementById('entityTypeSelect').addEventListener('change', function() {
    document.getElementById('entityInput').value = '';
    document.getElementById('entityId').value = '';
    document.getElementById('entityDropdown').classList.add('hidden');
});

// 初始化自动补全
setupAutocomplete('entityInput', 'entityDropdown', 'entityId', function() {
    return document.getElementById('entityTypeSelect').value;
});
setupAutocomplete('shipInput', 'shipDropdown', 'shipId', 'ship');
setupAutocomplete('systemInput', 'systemDropdown', 'systemId', 'system');

// ========== 搜索 ==========

document.getElementById('searchBtn').addEventListener('click', doAdvancedSearch);

function doAdvancedSearch() {
    var entityType = document.getElementById('entityTypeSelect').value;
    var entityId = document.getElementById('entityId').value;
    var entityInput = document.getElementById('entityInput').value.trim();
    var shipId = document.getElementById('shipId').value;
    var systemId = document.getElementById('systemId').value;
    var systemInput = document.getElementById('systemInput').value.trim();
    var timeStart = document.getElementById('timeStart').value;
    var timeEnd = document.getElementById('timeEnd').value;
    var invEl = document.querySelector('input[name="involvement"]:checked');
    var involvement = invEl ? invEl.value : '';

    // entityType 映射: character -> pilot
    var apiEntityType = entityType === 'character' ? 'pilot' : entityType;

    // 如果没有选择主实体，但有舰船或星系，用那个作为主搜索
    if (!entityId) {
        if (shipId) {
            apiEntityType = 'ship';
            entityId = shipId;
        } else if (systemId) {
            apiEntityType = 'system';
            entityId = systemId;
        } else if (!timeStart && !timeEnd) {
            alert('请至少选择一个搜索条件');
            return;
        }
    }

    showLoading('正在搜索击杀记录...');

    var params = new URLSearchParams();
    if (entityId) {
        params.set('entity_type', apiEntityType);
        params.set('entity_id', entityId);
    }
    if (shipId && apiEntityType !== 'ship') params.set('ship_id', shipId);
    if (systemId && apiEntityType !== 'system') params.set('system_id', systemId);
    if (timeStart) params.set('time_start', timeStart);
    if (timeEnd) params.set('time_end', timeEnd);
    if (involvement) params.set('involvement', involvement);

    var titleLabel = entityInput || systemInput || '搜索结果';

    fetch('/api/killmails/advanced-search?' + params.toString())
        .then(function(r) { return r.json(); })
        .then(function(data) {
            hideLoading();
            if (data.success) {
                renderKillList(data.data, titleLabel);
            } else {
                alert('搜索失败: ' + (data.message || '未知错误'));
            }
        })
        .catch(function(err) {
            hideLoading();
            alert('网络错误: ' + err.message);
        });
}

// ========== 直接查询 ==========

document.getElementById('directBtn').addEventListener('click', doDirectQuery);

function doDirectQuery() {
    var input = document.getElementById('directInput').value.trim();
    if (!input) { alert('请输入 KM ID 或 ESI 链接'); return; }

    var killId = null, hash = null;
    var esiMatch = input.match(/killmails\/(\d+)\/([a-f0-9]+)/i);
    if (esiMatch) {
        killId = esiMatch[1];
        hash = esiMatch[2];
    } else if (/^\d+$/.test(input)) {
        killId = input;
    } else {
        alert('无法识别的格式');
        return;
    }

    openKillDetail(killId, hash);
}

// ========== KM 列表渲染 ==========

function renderSingleKm(km) {
    var secClass = getSecClass(km.system_sec);
    var secText = km.system_sec !== null && km.system_sec !== undefined ? '(' + km.system_sec.toFixed(1) + ')' : '';
    var region = km.region_name || '';
    var locationLine1 = (region ? region + ' / ' : '') + (km.system_name || '-');
    var locationLine2 = km.kill_time || '';

    return '<tr class="border-b border-white/5 hover:bg-white/5 cursor-pointer transition-all" onclick="openKillDetail(' + km.kill_id + ', ' + (km.esi_hash ? "'" + km.esi_hash + "'" : 'null') + ')">'
        + '<td class="px-3 py-2.5"><div class="text-blue-300 font-mono text-xs">#' + km.kill_id + '</div>'
        + (km.attacker_count ? '<div class="text-white/30 text-xs">' + km.attacker_count + '人</div>' : '') + '</td>'
        + '<td class="px-3 py-2.5"><div class="text-white font-medium">' + escapeHtml(km.ship_name || '-') + '</div></td>'
        + '<td class="px-3 py-2.5"><div class="text-white">' + escapeHtml(km.victim_name || '-') + '</div>'
        + '<div class="text-white/40 text-xs">' + escapeHtml(km.victim_corp || '') + (km.victim_alliance ? ' / ' + escapeHtml(km.victim_alliance) : '') + '</div></td>'
        + '<td class="px-3 py-2.5"><div class="text-white">' + escapeHtml(km.final_blow_name || '-') + '</div>'
        + '<div class="text-white/40 text-xs">' + escapeHtml(km.final_blow_corp || '') + (km.final_blow_alliance ? ' / ' + escapeHtml(km.final_blow_alliance) : '') + '</div></td>'
        + '<td class="px-3 py-2.5"><div>' + escapeHtml(locationLine1) + ' <span class="' + secClass + '">' + secText + '</span></div>'
        + '<div class="text-white/40 text-xs">' + escapeHtml(locationLine2) + '</div></td>'
        + '<td class="px-3 py-2.5 text-right"><div class="text-yellow-300 font-medium">' + formatIsk(km.total_value) + '</div></td>'
        + '</tr>';
}

function appendKills() {
    var body = document.getElementById('killListBody');
    var batch = allKills.slice(displayedCount, displayedCount + PAGE_SIZE);
    body.innerHTML += batch.map(renderSingleKm).join('');
    displayedCount += batch.length;

    var loadMoreArea = document.getElementById('loadMoreArea');
    if (displayedCount >= allKills.length) {
        loadMoreArea.classList.add('hidden');
    } else {
        loadMoreArea.classList.remove('hidden');
    }
    document.getElementById('listCount').textContent = '显示 ' + displayedCount + ' / ' + allKills.length + ' 条';
}

function renderKillList(kills, title) {
    var area = document.getElementById('killListArea');
    var body = document.getElementById('killListBody');
    document.getElementById('listTitle').textContent = title + ' 的击杀记录';

    if (!kills.length) {
        body.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-white/40">未找到击杀记录</td></tr>';
        document.getElementById('listCount').textContent = '0 条记录';
        document.getElementById('loadMoreArea').classList.add('hidden');
        area.classList.remove('hidden');
        return;
    }

    allKills = kills;
    displayedCount = 0;
    body.innerHTML = '';
    appendKills();
    area.classList.remove('hidden');
}

document.getElementById('loadMoreBtn').addEventListener('click', appendKills);

// ========== KM 详情 ==========

function openKillDetail(killId, hash) {
    var modal = document.getElementById('detailModal');
    var content = document.getElementById('detailContent');
    document.getElementById('detailTitle').textContent = 'KM #' + killId + ' 详情';
    content.innerHTML = '<div class="text-center py-12"><div class="spinner mx-auto mb-3"></div><p class="text-blue-200 text-sm">加载中...</p></div>';
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    // 尝试前端提取 hash
    var fetchDetail = function(h) {
        var url = '/api/killmails/kill/' + killId;
        if (h) url += '?hash=' + h;
        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    renderKillDetail(data.data);
                } else {
                    content.innerHTML = '<div class="text-center py-12 text-red-400">' + escapeHtml(data.message || '加载失败') + '</div>';
                }
            })
            .catch(function(err) {
                content.innerHTML = '<div class="text-center py-12 text-red-400">网络错误: ' + escapeHtml(err.message) + '</div>';
            });
    };

    if (hash) {
        fetchDetail(hash);
    } else {
        // 尝试从 beta KB 获取 hash
        fetch(BETA_KB_URL + '/app/kill/' + killId + '/info', { mode: 'cors', credentials: 'omit' })
            .then(function(r) { return r.text(); })
            .then(function(text) {
                var m = text.match(/[a-f0-9]{40}/);
                fetchDetail(m ? m[0] : null);
            })
            .catch(function() { fetchDetail(null); });
    }
}

function closeDetail() {
    document.getElementById('detailModal').classList.add('hidden');
    document.body.style.overflow = '';
}

function renderKillDetail(d) {
    var content = document.getElementById('detailContent');
    var v = d.victim || {};
    var secClass = getSecClass(d.system_sec);
    var secText = d.system_sec !== null && d.system_sec !== undefined ? '(' + d.system_sec.toFixed(1) + ')' : '';

    var html = '';

    // ESI 验证标记
    if (d.esi_verified) {
        html += '<div class="mb-3 text-xs"><span class="bg-green-600/30 text-green-300 px-2 py-0.5 rounded">ESI 官方数据</span></div>';
    } else {
        html += '<div class="mb-3 text-xs"><span class="bg-yellow-600/30 text-yellow-300 px-2 py-0.5 rounded">KB 降级数据</span></div>';
    }

    // 受害者/击毁信息 (扁平紧凑)
    html += '<div class="bg-white/5 rounded-lg p-4 mb-4 border border-red-500/20">';
    html += '<div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">';
    html += '<span class="text-lg font-bold text-red-300">' + escapeHtml(v.character_name || '未知') + '</span>';
    var victimOrg = [];
    if (v.corporation_name) victimOrg.push(v.corporation_name);
    if (v.alliance_name) victimOrg.push(v.alliance_name);
    if (victimOrg.length) html += '<span class="text-white/50 text-sm">[' + escapeHtml(victimOrg.join(' / ')) + ']</span>';
    html += '<span class="text-white/70">舰船: <span class="text-white font-medium">' + escapeHtml(v.ship_name || '未知') + '</span></span>';
    html += '<span class="text-white/70">伤害: <span class="text-orange-300">' + formatNum(v.damage_taken) + '</span></span>';
    html += '</div>';
    html += '<div class="flex flex-wrap gap-x-4 gap-y-1 mt-1 text-sm text-white/50">';
    html += '<span>星系: ' + escapeHtml(d.solar_system_name || '-') + ' <span class="' + secClass + '">' + secText + '</span>';
    if (d.region_name) html += ' / ' + escapeHtml(d.region_name);
    html += '</span>';
    html += '<span>时间: ' + escapeHtml(d.kill_time || '-') + ' (北京时间)</span>';
    html += '<span>参与: ' + d.attacker_count + '人</span>';
    html += '<a href="https://kb.ceve-market.org/kill/' + d.kill_id + '/" target="_blank" class="text-blue-400 hover:underline">KB 链接</a>';
    html += '</div></div>';

    // 两列布局: 攻击者 | 装配/物品 (3:7 比例)
    html += '<div class="grid grid-cols-1 lg:grid-cols-10 gap-4">';

    // 左侧: 攻击者 (占 3 份)
    html += '<div class="lg:col-span-3 bg-white/5 rounded-lg border border-white/10 overflow-hidden">';
    html += '<div class="px-4 py-2 border-b border-white/10 font-bold text-sm">攻击者 (' + d.attacker_count + ')</div>';
    html += '<div class="divide-y divide-white/5 max-h-[600px] overflow-y-auto">';

    var attackers = d.attackers || [];
    var finalBlowId = null;

    attackers.forEach(function(atk, idx) {
        var badge = '';
        if (atk.final_blow) {
            badge = '<span class="text-yellow-400 text-xs font-bold ml-1">&#11088; 最后一击</span>';
            finalBlowId = atk.character_id;
        } else if (idx === 1 && (!attackers[0] || !attackers[0].final_blow || attackers[0].character_id !== atk.character_id)) {
            // 排在第二位且非最后一击 = 最高伤害
            badge = '<span class="text-orange-400 text-xs font-bold ml-1">&#127942; 最高伤害</span>';
        } else if (idx === 0 && !atk.final_blow) {
            badge = '<span class="text-orange-400 text-xs font-bold ml-1">&#127942; 最高伤害</span>';
        }

        var atkOrg = '';
        if (atk.corporation_name) atkOrg = atk.corporation_name;
        if (atk.alliance_name) atkOrg += (atkOrg ? ' - ' : '') + atk.alliance_name;

        html += '<div class="px-4 py-2.5' + (atk.final_blow ? ' bg-yellow-500/5' : '') + '">';
        html += '<div class="font-medium text-sm">' + escapeHtml(atk.character_name || '未知') + badge + '</div>';
        if (atkOrg) html += '<div class="text-white/40 text-xs">' + escapeHtml(atkOrg) + '</div>';
        html += '<div class="text-white/50 text-xs">' + escapeHtml(atk.ship_name || '?') + ' - ' + escapeHtml(atk.weapon_name || '?') + '</div>';
        html += '<div class="text-orange-300 text-xs">伤害: ' + formatNum(atk.damage_done) + '</div>';
        html += '</div>';
    });

    if (!attackers.length) {
        html += '<div class="px-4 py-4 text-white/30 text-sm text-center">无攻击者数据</div>';
    }

    html += '</div></div>';

    // 右侧: 装配/物品 (占 7 份, 按槽位)
    html += '<div class="lg:col-span-7 bg-white/5 rounded-lg border border-white/10 overflow-hidden">';
    html += '<div class="px-4 py-2 border-b border-white/10 font-bold text-sm">装配 / 物品</div>';
    html += '<div class="max-h-[600px] overflow-y-auto">';

    var slots = d.items_by_slot || {};
    var slotOrder = ['高槽', '中槽', '低槽', '改装件', '子系统', '无人机舱', '弹药舱', '货柜舱', '其他'];
    var hasItems = false;

    slotOrder.forEach(function(slotName) {
        var slotItems = slots[slotName];
        if (!slotItems || !slotItems.length) return;
        hasItems = true;

        html += '<div class="px-4 py-1.5 bg-white/5 border-b border-white/10 text-xs font-bold text-blue-300">' + escapeHtml(slotName) + '</div>';
        slotItems.forEach(function(item) {
            var colorClass = item.status === 'dropped' ? 'item-dropped' : 'item-destroyed';
            var priceText = '';
            if (item.total_price && item.total_price > 0) {
                priceText = formatIsk(item.total_price);
            }
            html += '<div class="px-4 py-1 text-xs flex justify-between items-center border-b border-white/5">';
            html += '<span class="' + colorClass + '">';
            if (item.quantity > 1) html += '<span class="text-white/50">x' + item.quantity + '</span> ';
            html += escapeHtml(item.item_name) + '</span>';
            html += '<span class="text-yellow-300/80 whitespace-nowrap ml-2">' + (priceText || '-') + '</span>';
            html += '</div>';
        });
    });

    // 如果没有 items_by_slot，回退到 items 列表
    if (!hasItems && v.items && v.items.length) {
        v.items.forEach(function(item) {
            var colorClass = item.status === 'dropped' ? 'item-dropped' : 'item-destroyed';
            var priceText = '';
            if (item.total_price && item.total_price > 0) {
                priceText = formatIsk(item.total_price);
            }
            html += '<div class="px-4 py-1 text-xs flex justify-between items-center border-b border-white/5">';
            html += '<span class="' + colorClass + '">';
            if (item.quantity > 1) html += '<span class="text-white/50">x' + item.quantity + '</span> ';
            html += escapeHtml(item.item_name) + '</span>';
            html += '<span class="text-yellow-300/80 whitespace-nowrap ml-2">' + (priceText || '-') + '</span>';
            html += '</div>';
        });
    } else if (!hasItems) {
        html += '<div class="px-4 py-4 text-white/30 text-sm text-center">无物品数据</div>';
    }

    html += '</div></div>';
    html += '</div>'; // grid end

    content.innerHTML = html;
}

// ========== 工具函数 ==========

function escapeHtml(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function formatIsk(val) {
    if (val === null || val === undefined) return '-';
    return Math.round(val).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',') + ' ISK';
}

function formatNum(n) {
    if (n === null || n === undefined) return '0';
    return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function getSecClass(sec) {
    if (sec === null || sec === undefined) return '';
    if (sec >= 0.5) return 'sec-high';
    if (sec > 0.0) return 'sec-low';
    return 'sec-null';
}

function showLoading(text) {
    document.getElementById('loadingText').textContent = text || '加载中...';
    document.getElementById('loadingArea').classList.remove('hidden');
    document.getElementById('killListArea').classList.add('hidden');
}

function hideLoading() {
    document.getElementById('loadingArea').classList.add('hidden');
}

// ESC 关闭模态框
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDetail();
        closeImageModal();
    }
});

// ========== KM 图片生成 [Claude Code] 2026-04-04 ==========

var currentImageUrl = null;
var currentImageKmId = null;

document.getElementById('generateImageBtn').addEventListener('click', doGenerateImage);
document.getElementById('imageKmId').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') doGenerateImage();
});

function doGenerateImage() {
    var input = document.getElementById('imageKmId').value.trim();
    if (!input) { showImageError('请输入 KM ID'); return; }

    // 支持输入KM ID 或 ESI链接
    var killId = null, hash = null;
    var esiMatch = input.match(/killmails\/(\d+)\/([a-f0-9]+)/i);
    if (esiMatch) {
        killId = esiMatch[1];
        hash = esiMatch[2];
    } else if (/^\d+$/.test(input)) {
        killId = input;
    } else {
        showImageError('无法识别的格式，请输入纯数字KM ID');
        return;
    }

    hideImageResult();
    showImageLoading();

    var url = '/api/killmails/kill/' + killId + '/image';
    if (hash) url += '?hash=' + hash;

    // 先构建图片URL，通过img标签加载检测结果
    var testImg = new Image();
    var imgUrl = url;
    currentImageUrl = imgUrl;
    currentImageKmId = killId;

    testImg.onload = function() {
        hideImageLoading();
        showImageResult(imgUrl, killId);
    };

    testImg.onerror = function() {
        // 图片加载失败，尝试获取错误信息
        hideImageLoading();
        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                showImageError(data.message || '图片生成失败，请检查 KM ID 是否正确');
            })
            .catch(function() {
                showImageError('生成失败，可能是 KM ID 无效或服务暂时不可用');
            });
    };

    testImg.src = imgUrl + (hash ? '' : '') + '&t=' + Date.now();
    // 实际设置（避免缓存干扰测试）
    testImg.src = imgUrl;
}

function showImageLoading() {
    document.getElementById('generateImageBtn').disabled = true;
    document.getElementById('generateImageBtn').classList.add('opacity-60');
    var status = document.getElementById('imageGenStatus');
    status.textContent = '生成中...';
    status.classList.remove('hidden');
    // 打开图片 modal，显示 loading
    var modal = document.getElementById('imageModal');
    modal.classList.remove('hidden');
    document.getElementById('imageModalLoading').classList.remove('hidden');
    document.getElementById('imageModalContent').classList.add('hidden');
    document.getElementById('imageModalError').classList.add('hidden');
    document.body.style.overflow = 'hidden';
}

function hideImageLoading() {
    document.getElementById('generateImageBtn').disabled = false;
    document.getElementById('generateImageBtn').classList.remove('opacity-60');
    document.getElementById('imageGenStatus').classList.add('hidden');
    document.getElementById('imageModalLoading').classList.add('hidden');
}

function showImageResult(imgUrl, killId) {
    hideImageLoading();
    var img = document.getElementById('imageModalImg');
    var dl = document.getElementById('imageModalDownload');
    img.src = imgUrl;
    dl.href = imgUrl;
    dl.download = 'km_' + killId + '.png';
    document.getElementById('imageModalContent').classList.remove('hidden');
}

function hideImageResult() {}

function closeImageModal() {
    document.getElementById('imageModal').classList.add('hidden');
    document.getElementById('imageModalLoading').classList.add('hidden');
    document.getElementById('imageModalContent').classList.add('hidden');
    document.getElementById('imageModalError').classList.add('hidden');
    document.body.style.overflow = '';
}

function showImageError(msg) {
    hideImageLoading();
    document.getElementById('imageModalErrorText').textContent = msg;
    document.getElementById('imageModalError').classList.remove('hidden');
}

function openKmImageInTab() {
    if (currentImageUrl) {
        window.open(currentImageUrl, '_blank');
    }
}

// 把两个 modal 挂到 body，彻底绕开布局堆叠上下文干扰
document.addEventListener('DOMContentLoaded', function() {
    ['detailModal', 'imageModal'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el && el.parentNode !== document.body) {
            document.body.appendChild(el);
        }
    });
});
</script>
@endpush
