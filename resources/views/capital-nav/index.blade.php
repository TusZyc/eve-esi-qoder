@extends('layouts.app')

@section('title', '旗舰导航 - Tus Esi System')

@push('styles')
<style>
    .tab-btn {
        padding: 10px 24px; cursor: pointer; font-size: 14px; font-weight: 500;
        border-bottom: 2px solid transparent; transition: all 0.2s;
        color: rgba(255,255,255,0.5); border-radius: 8px 8px 0 0;
    }
    .tab-btn:hover { color: rgba(255,255,255,0.85); background: rgba(255,255,255,0.05); }
    .tab-btn.active { color: white; border-bottom-color: #60a5fa; background: rgba(255,255,255,0.08); }
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
    .sec-high { color: #4ade80; }
    .sec-low { color: #facc15; }
    .sec-null { color: #f87171; }
    .skill-desc { font-size: 11px; color: rgba(255,255,255,0.4); margin-top: 2px; line-height: 1.4; }
    .step-jump { border-left: 3px solid #3b82f6; }
    .step-gate { border-left: 3px solid #f59e0b; }
    /* Fix: select option text invisible on white/system background */
    select option { background-color: #1e293b; color: #e2e8f0; }
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

@section('content')
    <div class="container mx-auto px-4 py-6 max-w-7xl">
        <!-- 标签栏 -->
        <div class="bg-white/5 backdrop-blur rounded-xl border border-white/10 mb-6">
            <div class="px-4 py-3 border-b border-white/10 flex justify-between items-center">
                <h2 class="font-bold text-sm">旗舰导航</h2>
            </div>
            <div class="flex px-4 pt-2 border-b border-white/10">
                <button id="tab-distance" class="tab-btn active" onclick="switchTab('distance')">📏 星系距离</button>
                <button id="tab-reachable" class="tab-btn" onclick="switchTab('reachable')">🚀 一跳可达</button>
                <button id="tab-route" class="tab-btn" onclick="switchTab('route')">📍 路线规划</button>
            </div>

            <!-- ==================== Tab 1: 星系距离 ==================== -->
            <div id="content-distance" class="p-6">
                <div class="space-y-4 max-w-2xl">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-blue-200 mb-1">起始星系</label>
                            <div class="relative">
                                <input type="text" id="dist-from-input" placeholder="输入星系名称..." autocomplete="off"
                                    class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-400">
                                <input type="hidden" id="dist-from-id">
                                <div id="dist-from-dropdown" class="ac-dropdown hidden"></div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm text-blue-200 mb-1">目标星系</label>
                            <div class="relative">
                                <input type="text" id="dist-to-input" placeholder="输入星系名称..." autocomplete="off"
                                    class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-400">
                                <input type="hidden" id="dist-to-id">
                                <div id="dist-to-dropdown" class="ac-dropdown hidden"></div>
                            </div>
                        </div>
                    </div>
                    <button onclick="calcDistance()" id="dist-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-6 rounded-lg text-sm transition-all">计算距离</button>
                </div>
            </div>

            <!-- ==================== Tab 2: 一跳可达 ==================== -->
            <div id="content-reachable" class="hidden p-6">
                <div class="space-y-4">
                    <!-- 舰船和技能 -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm text-blue-200 mb-1">舰船类型</label>
                            <select id="reach-ship" onchange="onShipChange('reach')"
                                class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-400">
                                <option value="jump_freighter">战略货舰</option>
                                <option value="rorqual">长须鲸级</option>
                                <option value="black_ops">黑隐特勤舰</option>
                                <option value="carrier">航空母舰</option>
                                <option value="dreadnought">无畏舰</option>
                                <option value="supercarrier">超级航母</option>
                                <option value="titan">泰坦</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-blue-200 mb-1">跳跃引擎校对理论</label>
                            <select id="reach-jdc" onchange="updatePreview('reach')"
                                class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-400">
                                <option value="0">0 级</option><option value="1">1 级</option><option value="2">2 级</option>
                                <option value="3">3 级</option><option value="4">4 级</option><option value="5" selected>5 级</option>
                            </select>
                            <div class="skill-desc">每升一级，最大跳跃距离增加20%</div>
                        </div>
                        <div>
                            <label class="block text-sm text-blue-200 mb-1">跳跃引擎能耗效率研究</label>
                            <select id="reach-fuel-eff" onchange="updatePreview('reach')"
                                class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-400">
                                <option value="0">0 级</option><option value="1">1 级</option><option value="2">2 级</option>
                                <option value="3">3 级</option><option value="4">4 级</option><option value="5" selected>5 级</option>
                            </select>
                            <div class="skill-desc">每升一级，每光年的跳跃操作所消耗的冰矿产物减少10%</div>
                        </div>
                        <div id="reach-jf-wrap">
                            <label class="block text-sm text-blue-200 mb-1">战略货舰概论</label>
                            <select id="reach-jf" onchange="updatePreview('reach')"
                                class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-400">
                                <option value="0">0 级</option><option value="1">1 级</option><option value="2">2 级</option>
                                <option value="3">3 级</option><option value="4">4 级</option><option value="5" selected>5 级</option>
                            </select>
                            <div class="skill-desc">每升一级，战略货舰护盾、装甲和结构值各增加10%，跳跃燃料需求减少10%</div>
                        </div>
                    </div>
                    <!-- 起始星系 -->
                    <div class="max-w-md">
                        <label class="block text-sm text-blue-200 mb-1">起始星系</label>
                        <div class="relative">
                            <input type="text" id="reach-origin-input" placeholder="输入星系名称..." autocomplete="off"
                                class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-400">
                            <input type="hidden" id="reach-origin-id">
                            <div id="reach-origin-dropdown" class="ac-dropdown hidden"></div>
                        </div>
                    </div>
                    <!-- 预览 -->
                    <div id="reach-preview" class="text-xs text-white/50"></div>
                    <button onclick="queryReachable()" id="reach-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-6 rounded-lg text-sm transition-all">查询可达星系</button>
                </div>
            </div>

            <!-- ==================== Tab 3: 路线规划 ==================== -->
            <div id="content-route" class="hidden p-6">
                <div class="space-y-4">
                    <!-- 舰船和技能 -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm text-blue-200 mb-1">舰船类型</label>
                            <select id="route-ship" onchange="onShipChange('route')"
                                class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-400">
                                <option value="jump_freighter">战略货舰</option>
                                <option value="rorqual">长须鲸级</option>
                                <option value="black_ops">黑隐特勤舰</option>
                                <option value="carrier">航空母舰</option>
                                <option value="dreadnought">无畏舰</option>
                                <option value="supercarrier">超级航母</option>
                                <option value="titan">泰坦</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-blue-200 mb-1">跳跃引擎校对理论</label>
                            <select id="route-jdc" onchange="updatePreview('route')"
                                class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-400">
                                <option value="0">0 级</option><option value="1">1 级</option><option value="2">2 级</option>
                                <option value="3">3 级</option><option value="4">4 级</option><option value="5" selected>5 级</option>
                            </select>
                            <div class="skill-desc">每升一级，最大跳跃距离增加20%</div>
                        </div>
                        <div>
                            <label class="block text-sm text-blue-200 mb-1">跳跃引擎能耗效率研究</label>
                            <select id="route-fuel-eff" onchange="updatePreview('route')"
                                class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-400">
                                <option value="0">0 级</option><option value="1">1 级</option><option value="2">2 级</option>
                                <option value="3">3 级</option><option value="4">4 级</option><option value="5" selected>5 级</option>
                            </select>
                            <div class="skill-desc">每升一级，每光年的跳跃操作所消耗的冰矿产物减少10%</div>
                        </div>
                        <div id="route-jf-wrap">
                            <label class="block text-sm text-blue-200 mb-1">战略货舰概论</label>
                            <select id="route-jf" onchange="updatePreview('route')"
                                class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-400">
                                <option value="0">0 级</option><option value="1">1 级</option><option value="2">2 级</option>
                                <option value="3">3 级</option><option value="4">4 级</option><option value="5" selected>5 级</option>
                            </select>
                            <div class="skill-desc">每升一级，战略货舰护盾、装甲和结构值各增加10%，跳跃燃料需求减少10%</div>
                        </div>
                    </div>
                    <!-- 起始/目标星系 + 选项 -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-blue-200 mb-1">起始星系</label>
                            <div class="relative">
                                <input type="text" id="route-from-input" placeholder="输入星系名称..." autocomplete="off"
                                    class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-400">
                                <input type="hidden" id="route-from-id">
                                <div id="route-from-dropdown" class="ac-dropdown hidden"></div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm text-blue-200 mb-1">目标星系</label>
                            <div class="relative">
                                <input type="text" id="route-to-input" placeholder="输入星系名称..." autocomplete="off"
                                    class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-400">
                                <input type="hidden" id="route-to-id">
                                <div id="route-to-dropdown" class="ac-dropdown hidden"></div>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2 cursor-pointer text-sm">
                            <input type="checkbox" id="route-use-gates" class="rounded bg-white/10 border-white/30 text-blue-500 focus:ring-blue-400">
                            <span class="text-white/80">允许使用星门（减少燃料消耗）</span>
                        </label>
                    </div>
                    <div id="route-preview" class="text-xs text-white/50"></div>
                    <button onclick="planRoute()" id="route-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-6 rounded-lg text-sm transition-all">规划路线</button>
                </div>
            </div>
        </div>

        <!-- 距离结果 -->
        <div id="dist-result" class="hidden mb-6">
            <div class="bg-white/5 backdrop-blur rounded-xl p-6 border border-white/10 max-w-2xl">
                <div id="dist-result-content"></div>
            </div>
        </div>

        <!-- 可达结果 -->
        <div id="reach-result" class="hidden mb-6">
            <div class="bg-white/5 backdrop-blur rounded-xl border border-white/10 overflow-hidden">
                <div class="px-4 py-3 border-b border-white/10 flex justify-between items-center">
                    <h3 id="reach-title" class="font-bold text-sm">可达星系</h3>
                    <span id="reach-count" class="text-xs text-white/50"></span>
                </div>
                <div class="px-4 py-3 border-b border-white/10">
                    <div id="reach-summary" class="text-sm"></div>
                </div>
                <div class="px-4 py-2">
                    <input type="text" id="reach-filter" placeholder="搜索星系名或星域名..." oninput="filterReachable()"
                        class="w-full max-w-sm bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-400 placeholder-white/30">
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-white/60 text-xs border-b border-white/10">
                                <th class="text-left px-3 py-2">星系名</th>
                                <th class="text-right px-3 py-2">距离 (LY)</th>
                                <th class="text-right px-3 py-2">燃料</th>
                                <th class="text-left px-3 py-2">星域</th>
                                <th class="text-right px-3 py-2">安等</th>
                            </tr>
                        </thead>
                        <tbody id="reach-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 路线结果 -->
        <div id="route-result" class="hidden mb-6">
            <div class="bg-white/5 backdrop-blur rounded-xl border border-white/10 overflow-hidden">
                <div class="px-4 py-3 border-b border-white/10">
                    <h3 class="font-bold text-sm">路线规划结果</h3>
                </div>
                <div class="px-4 py-3 border-b border-white/10">
                    <div id="route-summary"></div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-white/60 text-xs border-b border-white/10">
                                <th class="text-left px-3 py-2">#</th>
                                <th class="text-left px-3 py-2">类型</th>
                                <th class="text-left px-3 py-2">星系名</th>
                                <th class="text-right px-3 py-2">距离 (LY)</th>
                                <th class="text-right px-3 py-2">燃料</th>
                                <th class="text-left px-3 py-2">星域</th>
                                <th class="text-right px-3 py-2">安等</th>
                            </tr>
                        </thead>
                        <tbody id="route-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Loading -->
        <div id="loadingArea" class="hidden mb-6">
            <div class="bg-white/5 backdrop-blur rounded-xl p-8 border border-white/10 text-center">
                <div class="spinner mx-auto mb-3"></div>
                <p id="loading-text" class="text-blue-200 text-sm">计算中...</p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
// ==================== 舰船数据 ====================
var SHIP_DATA = {
    'jump_freighter': { name: '战略货舰', baseRange: 5.0, baseFuel: 10000 },
    'rorqual':        { name: '长须鲸级', baseRange: 5.0, baseFuel: 4000 },
    'black_ops':      { name: '黑隐特勤舰', baseRange: 4.0, baseFuel: 700 },
    'carrier':        { name: '航空母舰', baseRange: 3.5, baseFuel: 3000 },
    'dreadnought':    { name: '无畏舰', baseRange: 3.5, baseFuel: 3000 },
    'supercarrier':   { name: '超级航母', baseRange: 3.0, baseFuel: 3000 },
    'titan':          { name: '泰坦', baseRange: 3.0, baseFuel: 3000 }
};

var debounceTimers = {};
var reachableData = [];

// ==================== 标签切换 ====================
function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
    document.getElementById('tab-' + tab).classList.add('active');
    document.getElementById('content-distance').classList.add('hidden');
    document.getElementById('content-reachable').classList.add('hidden');
    document.getElementById('content-route').classList.add('hidden');
    document.getElementById('content-' + tab).classList.remove('hidden');
    // 清除所有结果区域
    document.getElementById('dist-result').classList.add('hidden');
    document.getElementById('reach-result').classList.add('hidden');
    document.getElementById('route-result').classList.add('hidden');
    document.getElementById('loadingArea').classList.add('hidden');
}

// ==================== 工具函数 ====================
function escapeHtml(str) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(str || ''));
    return d.innerHTML;
}

function formatNumber(n) {
    if (n === null || n === undefined) return '-';
    return Number(n).toLocaleString();
}

function getSecClass(sec) {
    var d = Math.round(sec * 10) / 10;
    if (d >= 0.5) return 'sec-high';
    if (d > 0) return 'sec-low';
    return 'sec-null';
}

function formatSec(sec) {
    var d = Math.round(sec * 10) / 10;
    return d.toFixed(1);
}

function showLoading(text) {
    document.getElementById('loading-text').textContent = text || '计算中...';
    document.getElementById('loadingArea').classList.remove('hidden');
}

function hideLoading() {
    document.getElementById('loadingArea').classList.add('hidden');
}

// ==================== 前端跳跃计算 ====================
function calcJumpRange(shipType, jdcLevel) {
    var ship = SHIP_DATA[shipType];
    if (!ship) return 0;
    return ship.baseRange * (1 + 0.20 * jdcLevel);
}

function calcFuelRate(shipType, fuelEffLevel, jfLevel) {
    var ship = SHIP_DATA[shipType];
    if (!ship) return 0;
    var fuelEff = 1 - 0.10 * fuelEffLevel;
    var jfRed = (shipType === 'jump_freighter') ? (1 - 0.10 * jfLevel) : 1;
    return Math.ceil(ship.baseFuel * fuelEff * jfRed);
}

function onShipChange(prefix) {
    var shipType = document.getElementById(prefix + '-ship').value;
    var jfWrap = document.getElementById(prefix + '-jf-wrap');
    var jfSelect = document.getElementById(prefix + '-jf');
    if (shipType === 'jump_freighter') {
        jfWrap.style.opacity = '1';
        jfSelect.disabled = false;
    } else {
        jfWrap.style.opacity = '0.4';
        jfSelect.disabled = true;
        jfSelect.value = '0';
    }
    updatePreview(prefix);
}

function updatePreview(prefix) {
    var shipType = document.getElementById(prefix + '-ship').value;
    var jdc = parseInt(document.getElementById(prefix + '-jdc').value);
    var fuelEff = parseInt(document.getElementById(prefix + '-fuel-eff').value);
    var jf = parseInt(document.getElementById(prefix + '-jf').value);
    var range = calcJumpRange(shipType, jdc);
    var rate = calcFuelRate(shipType, fuelEff, jf);
    var el = document.getElementById(prefix + '-preview');
    if (el) {
        el.textContent = '跳跃能力：' + range.toFixed(1) + ' 光年 | 每光年燃料：' + formatNumber(rate) + ' 单位';
    }
}

// ==================== 自动补全 ====================
function setupAutocomplete(inputId, dropdownId, hiddenId) {
    var input = document.getElementById(inputId);
    var dropdown = document.getElementById(dropdownId);
    var hidden = document.getElementById(hiddenId);
    var activeIdx = -1;

    input.addEventListener('input', function() {
        var q = input.value.trim();
        hidden.value = '';
        if (q.length < 1) { dropdown.classList.add('hidden'); return; }
        clearTimeout(debounceTimers[inputId]);
        debounceTimers[inputId] = setTimeout(function() {
            fetch('/api/capital-nav/autocomplete?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success || !data.data.length) { dropdown.classList.add('hidden'); return; }
                    activeIdx = -1;
                    dropdown.innerHTML = data.data.map(function(item, i) {
                        return '<div class="ac-item" data-id="' + item.id + '" data-name="' + escapeHtml(item.name) + '">' + escapeHtml(item.name) + ' <span style="color:rgba(255,255,255,0.3);font-size:11px">(' + item.id + ')</span></div>';
                    }).join('');
                    dropdown.classList.remove('hidden');
                    dropdown.querySelectorAll('.ac-item').forEach(function(el) {
                        el.addEventListener('click', function() {
                            input.value = el.dataset.name;
                            hidden.value = el.dataset.id;
                            dropdown.classList.add('hidden');
                        });
                    });
                })
                .catch(function() { dropdown.classList.add('hidden'); });
        }, 300);
    });

    input.addEventListener('keydown', function(e) {
        var items = dropdown.querySelectorAll('.ac-item');
        if (!items.length || dropdown.classList.contains('hidden')) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIdx = Math.min(activeIdx + 1, items.length - 1);
            items.forEach(function(it, i) { it.classList.toggle('active', i === activeIdx); });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIdx = Math.max(activeIdx - 1, 0);
            items.forEach(function(it, i) { it.classList.toggle('active', i === activeIdx); });
        } else if (e.key === 'Enter' && activeIdx >= 0 && items[activeIdx]) {
            e.preventDefault();
            input.value = items[activeIdx].dataset.name;
            hidden.value = items[activeIdx].dataset.id;
            dropdown.classList.add('hidden');
        } else if (e.key === 'Escape') {
            dropdown.classList.add('hidden');
        }
    });

    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
}

// ==================== Tab 1: 星系距离 ====================
function calcDistance() {
    var fromId = document.getElementById('dist-from-id').value;
    var toId = document.getElementById('dist-to-id').value;
    if (!fromId || !toId) { alert('请选择起始和目标星系'); return; }

    var btn = document.getElementById('dist-btn');
    btn.disabled = true;
    btn.textContent = '计算中...';

    fetch('/api/capital-nav/distance?from=' + fromId + '&to=' + toId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.textContent = '计算距离';
            if (!data.success) { alert(data.message || '计算失败'); return; }
            var d = data.data;
            var html = '<div class="text-center">';
            html += '<div class="text-3xl font-bold text-blue-400 mb-3">' + d.distance_ly + ' <span class="text-lg text-white/60">光年</span></div>';
            html += '<div class="flex justify-center gap-8 text-sm">';
            html += '<div><span class="text-white/50">起始：</span><span class="text-white font-medium">' + escapeHtml(d.from.name) + '</span> <span class="' + getSecClass(d.from.security) + '">(' + formatSec(d.from.security) + ')</span>';
            if (d.from.region) html += ' <span class="text-white/40">' + escapeHtml(d.from.region) + '</span>';
            html += '</div>';
            html += '<div><span class="text-white/50">目标：</span><span class="text-white font-medium">' + escapeHtml(d.to.name) + '</span> <span class="' + getSecClass(d.to.security) + '">(' + formatSec(d.to.security) + ')</span>';
            if (d.to.region) html += ' <span class="text-white/40">' + escapeHtml(d.to.region) + '</span>';
            html += '</div>';
            html += '</div></div>';
            document.getElementById('dist-result-content').innerHTML = html;
            document.getElementById('dist-result').classList.remove('hidden');
        })
        .catch(function(err) {
            btn.disabled = false;
            btn.textContent = '计算距离';
            alert('网络错误: ' + err.message);
        });
}

// ==================== Tab 2: 一跳可达 ====================
function queryReachable() {
    var originId = document.getElementById('reach-origin-id').value;
    var shipType = document.getElementById('reach-ship').value;
    var jdc = document.getElementById('reach-jdc').value;
    var fuelEff = document.getElementById('reach-fuel-eff').value;
    var jf = document.getElementById('reach-jf').value;

    if (!originId) { alert('请选择起始星系'); return; }

    showLoading('正在查询可达星系...');
    var url = '/api/capital-nav/reachable?origin=' + originId + '&ship_type=' + shipType
        + '&jdc_level=' + jdc + '&fuel_eff_level=' + fuelEff + '&jf_level=' + jf;

    fetch(url)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            hideLoading();
            if (!data.success) { alert(data.message || '查询失败'); return; }
            var d = data.data;
            reachableData = d.systems;

            var html = '<div class="flex flex-wrap gap-x-6 gap-y-1">';
            html += '<span><span class="text-white/50">起始星系：</span><span class="text-white font-medium">' + escapeHtml(d.origin.name) + '</span></span>';
            html += '<span><span class="text-white/50">跳跃能力：</span><span class="text-blue-300 font-bold">' + d.jump_range + ' LY</span></span>';
            html += '<span><span class="text-white/50">每光年燃料：</span><span class="text-yellow-300">' + formatNumber(d.fuel_rate) + '</span></span>';
            html += '<span><span class="text-white/50">可达星系：</span><span class="text-green-400">' + d.count + ' 个</span></span>';
            html += '</div>';
            document.getElementById('reach-summary').innerHTML = html;
            document.getElementById('reach-count').textContent = d.count + ' 个星系';

            renderReachableTable(reachableData);
            document.getElementById('reach-filter').value = '';
            document.getElementById('reach-result').classList.remove('hidden');
        })
        .catch(function(err) {
            hideLoading();
            alert('网络错误: ' + err.message);
        });
}

function renderReachableTable(systems) {
    var tbody = document.getElementById('reach-tbody');
    if (!systems.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-6 text-white/40">没有可达星系</td></tr>';
        return;
    }
    var html = '';
    systems.forEach(function(s) {
        html += '<tr class="border-b border-white/5 hover:bg-white/5 transition-all">';
        html += '<td class="px-3 py-2.5 text-white">' + escapeHtml(s.name) + '</td>';
        html += '<td class="px-3 py-2.5 text-right text-blue-300">' + s.distance_ly.toFixed(2) + '</td>';
        html += '<td class="px-3 py-2.5 text-right text-yellow-300">' + formatNumber(s.fuel) + '</td>';
        html += '<td class="px-3 py-2.5 text-white/60">' + escapeHtml(s.region_name || '-') + '</td>';
        html += '<td class="px-3 py-2.5 text-right"><span class="' + getSecClass(s.security) + '">' + formatSec(s.security) + '</span></td>';
        html += '</tr>';
    });
    tbody.innerHTML = html;
}

function filterReachable() {
    var q = document.getElementById('reach-filter').value.trim().toLowerCase();
    if (!q) {
        renderReachableTable(reachableData);
        return;
    }
    var filtered = reachableData.filter(function(s) {
        return (s.name && s.name.toLowerCase().indexOf(q) !== -1) ||
               (s.region_name && s.region_name.toLowerCase().indexOf(q) !== -1);
    });
    renderReachableTable(filtered);
}

// ==================== Tab 3: 路线规划 ====================
function planRoute() {
    var fromId = document.getElementById('route-from-id').value;
    var toId = document.getElementById('route-to-id').value;
    var shipType = document.getElementById('route-ship').value;
    var jdc = document.getElementById('route-jdc').value;
    var fuelEff = document.getElementById('route-fuel-eff').value;
    var jf = document.getElementById('route-jf').value;
    var useGates = document.getElementById('route-use-gates').checked ? '1' : '0';

    if (!fromId || !toId) { alert('请选择起始和目标星系'); return; }

    showLoading('正在规划路线，请稍候...');
    var url = '/api/capital-nav/route?from=' + fromId + '&to=' + toId
        + '&ship_type=' + shipType + '&jdc_level=' + jdc + '&fuel_eff_level=' + fuelEff
        + '&jf_level=' + jf + '&use_stargates=' + useGates;

    fetch(url)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            hideLoading();
            if (!data.success) { alert(data.message || '规划失败'); return; }
            var d = data.data;

            if (!d.found) {
                document.getElementById('route-summary').innerHTML = '<div class="text-red-400 text-center py-4">' + escapeHtml(d.reason || '无法到达目标星系') + '</div>';
                document.getElementById('route-tbody').innerHTML = '';
                document.getElementById('route-result').classList.remove('hidden');
                return;
            }

            // 摘要
            var html = '<div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">';
            html += '<div class="text-center"><div class="text-white/50 text-xs">跳跃能力</div><div class="text-blue-400 font-bold text-lg">' + d.jump_range + ' LY</div></div>';
            html += '<div class="text-center"><div class="text-white/50 text-xs">总燃料</div><div class="text-yellow-300 font-bold text-lg">' + formatNumber(d.total_fuel) + '</div></div>';
            html += '<div class="text-center"><div class="text-white/50 text-xs">总距离</div><div class="text-blue-300 font-bold text-lg">' + d.total_distance + ' LY</div></div>';
            html += '<div class="text-center"><div class="text-white/50 text-xs">跳跃次数</div><div class="text-blue-400 font-bold text-lg">' + d.jumps + '</div></div>';
            html += '<div class="text-center"><div class="text-white/50 text-xs">过门次数</div><div class="text-amber-400 font-bold text-lg">' + d.gates + '</div></div>';
            html += '</div>';
            document.getElementById('route-summary').innerHTML = html;

            // 路线表格
            var tbody = document.getElementById('route-tbody');
            if (!d.path || !d.path.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-6 text-white/40">已在同一星系</td></tr>';
            } else {
                var rows = '';
                d.path.forEach(function(step, i) {
                    var typeLabel = step.type === 'jump'
                        ? '<span class="text-blue-400">&#x1f537; 跳跃</span>'
                        : '<span class="text-amber-400">&#x1f536; 星门</span>';
                    var rowClass = step.type === 'jump' ? 'step-jump' : 'step-gate';
                    rows += '<tr class="border-b border-white/5 hover:bg-white/5 transition-all ' + rowClass + '">';
                    rows += '<td class="px-3 py-2.5 text-white/50">' + (i + 1) + '</td>';
                    rows += '<td class="px-3 py-2.5">' + typeLabel + '</td>';
                    rows += '<td class="px-3 py-2.5 text-white">' + escapeHtml(step.from_name) + ' → ' + escapeHtml(step.to_name) + '</td>';
                    rows += '<td class="px-3 py-2.5 text-right text-blue-300">' + (step.distance_ly > 0 ? step.distance_ly.toFixed(2) : '-') + '</td>';
                    rows += '<td class="px-3 py-2.5 text-right text-yellow-300">' + (step.fuel > 0 ? formatNumber(step.fuel) : '-') + '</td>';
                    rows += '<td class="px-3 py-2.5 text-white/60">' + escapeHtml(step.region_name || '-') + '</td>';
                    rows += '<td class="px-3 py-2.5 text-right"><span class="' + getSecClass(step.security) + '">' + formatSec(step.security) + '</span></td>';
                    rows += '</tr>';
                });
                tbody.innerHTML = rows;
            }
            document.getElementById('route-result').classList.remove('hidden');
        })
        .catch(function(err) {
            hideLoading();
            alert('网络错误: ' + err.message);
        });
}

// ==================== 初始化 ====================
document.addEventListener('DOMContentLoaded', function() {
    setupAutocomplete('dist-from-input', 'dist-from-dropdown', 'dist-from-id');
    setupAutocomplete('dist-to-input', 'dist-to-dropdown', 'dist-to-id');
    setupAutocomplete('reach-origin-input', 'reach-origin-dropdown', 'reach-origin-id');
    setupAutocomplete('route-from-input', 'route-from-dropdown', 'route-from-id');
    setupAutocomplete('route-to-input', 'route-to-dropdown', 'route-to-id');

    updatePreview('reach');
    updatePreview('route');
    onShipChange('reach');
    onShipChange('route');
});
</script>
@endpush
