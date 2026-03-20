@extends('layouts.app')

@push('styles')
<style>
    .eve-glow { box-shadow: 0 0 20px rgba(59, 130, 246, 0.5); }
    @keyframes shimmer { 0% { background-position: -1000px 0; } 100% { background-position: 1000px 0; } }
    .skeleton { background: linear-gradient(90deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.08) 50%, rgba(255,255,255,0.03) 100%); background-size: 1000px 100%; animation: shimmer 2s infinite; border-radius: 4px; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .spinner { border: 2px solid rgba(255,255,255,0.1); border-top-color: #60a5fa; border-radius: 50%; width: 20px; height: 20px; animation: spin 0.8s linear infinite; display: inline-block; }
    .km-card { transition: all 0.2s; cursor: pointer; }
    .km-card:hover { transform: translateY(-2px); }
    .km-card-kill { border-left: 4px solid #22c55e; }
    .km-card-loss { border-left: 4px solid #ef4444; }
    .sec-high { color: #4ade80; }
    .sec-low { color: #facc15; }
    .sec-null { color: #f87171; }
    .item-dropped { color: #4ade80; }
    .item-destroyed { color: #f87171; }
</style>
@endpush

@section('title', '击毁报告 - Tus Esi System')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow">
            <h2 class="text-2xl font-semibold mb-6">⚔️ 我的击毁报告</h2>
            
            <div id="killmails-area">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                        <div class="skeleton h-24 w-full mb-3"></div>
                        <div class="skeleton h-24 w-full mb-3"></div>
                    </div>
                    <div>
                        <div class="skeleton h-24 w-full mb-3"></div>
                        <div class="skeleton h-24 w-full mb-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KM 详情模态框 -->
    <div id="detailModal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto">
        <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" onclick="closeDetail()"></div>
        <div class="relative mx-auto my-4 md:my-8 w-full max-w-5xl max-h-[90vh] bg-slate-900/95 backdrop-blur rounded-xl border border-white/20 overflow-hidden flex flex-col">
            <div class="flex justify-between items-center px-6 py-3 border-b border-white/10 shrink-0">
                <h3 id="detailTitle" class="font-bold">KM 详情</h3>
                <button onclick="closeDetail()" class="text-white/50 hover:text-white text-xl">&times;</button>
            </div>
            <div id="detailContent" class="flex-1 overflow-y-auto p-6">
                <div class="text-center py-12"><div class="spinner mx-auto"></div></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    var BETA_KB_URL = 'https://beta.ceve-market.org';
    var KB_URL = 'https://kb.ceve-market.org';
    
    var allKillmails = [];
    var allKills = [];
    var allLosses = [];
    var KMS_PER_LOAD = 5;
    var displayedKills = 0;
    var displayedLosses = 0;

    function formatNumber(n) { return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
    function formatIsk(val) {
        if (val === null || val === undefined) return '-';
        return Math.round(val).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',') + ' ISK';
    }
    function formatNum(n) {
        if (n === null || n === undefined) return '0';
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    function formatDate(dateStr) {
        if (!dateStr) return '-';
        var d = new Date(dateStr);
        return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0') + ' ' + String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
    }
    function escapeHtml(s) { var div = document.createElement('div'); div.textContent = s || ''; return div.innerHTML; }
    function getSecClass(sec) {
        if (sec === null || sec === undefined) return '';
        if (sec >= 0.5) return 'sec-high';
        if (sec > 0.0) return 'sec-low';
        return 'sec-null';
    }

    function renderKmCard(km, isLoss) {
        var cardClass = isLoss ? 'km-card-loss' : 'km-card-kill';
        var killId = km.killmail_id;
        
        var html = '<div class="km-card bg-white/5 rounded-lg p-4 hover:bg-white/10 ' + cardClass + '" onclick="openKillDetail(' + killId + ')">';
        
        // 舰船名称
        var shipName = km.victim && km.victim.ship_type_name ? km.victim.ship_type_name : '未知舰船';
        html += '<div class="font-bold text-lg mb-2">' + escapeHtml(shipName) + '</div>';
        
        // 受害者/目标名称
        if (km.victim) {
            var targetLabel = isLoss ? '我的角色' : '击毁目标';
            var targetName = km.victim.character_name || 'NPC';
            html += '<div class="text-sm mb-1"><span class="text-white/50">' + targetLabel + ':</span> <span class="' + (isLoss ? 'text-red-300' : 'text-green-300') + '">' + escapeHtml(targetName) + '</span></div>';
        }
        
        // 击杀者（仅损失时显示）
        if (isLoss && km.final_blow) {
            var killerName = km.final_blow.character_name || 'NPC';
            var killerShip = km.final_blow.ship_type_name || '';
            html += '<div class="text-sm mb-1"><span class="text-white/50">击杀者:</span> ' + escapeHtml(killerName);
            if (killerShip) html += ' <span class="text-white/40">(' + escapeHtml(killerShip) + ')</span>';
            html += '</div>';
        }
        
        // 最后一击（仅击杀时显示）
        if (!isLoss && km.final_blow) {
            var fbName = km.final_blow.character_name || 'NPC';
            html += '<div class="text-sm mb-1"><span class="text-white/50">最后一击:</span> ' + escapeHtml(fbName) + '</div>';
        }
        
        // 星系和时间
        html += '<div class="text-sm text-white/60 mb-1">📍 ' + escapeHtml(km.solar_system_name || '未知') + '</div>';
        html += '<div class="text-sm text-white/50 mb-2">🕐 ' + formatDate(km.killmail_time) + '</div>';
        
        // 参与人数和链接
        html += '<div class="flex justify-between items-center text-xs">';
        html += '<span class="text-white/50">参与人数: ' + (km.attacker_count || 1) + '</span>';
        var kbUrl = KB_URL + '/kill/' + killId + '/';
        html += '<a href="' + kbUrl + '" target="_blank" onclick="event.stopPropagation()" class="text-blue-400 hover:text-blue-300">KB →</a>';
        html += '</div>';
        
        html += '</div>';
        return html;
    }

    function renderKillmails(data) {
        var area = document.getElementById('killmails-area');
        
        if (!data || data.length === 0) {
            area.innerHTML = '<div class="text-center py-12"><div class="text-6xl mb-4">🕊️</div><p class="text-xl text-blue-200">暂无击毁报告</p><p class="text-sm text-blue-300/70 mt-2">需要重新授权获取击毁报告权限</p></div>';
            return;
        }
        
        // 保存全局数据
        allKillmails = data;
        allKills = [];
        allLosses = [];
        displayedKills = 0;
        displayedLosses = 0;
        
        // 分离击杀和损失
        data.forEach(function(km) {
            if (km.is_victim) {
                allLosses.push(km);
            } else {
                allKills.push(km);
            }
        });
        
        var html = '<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">';
        
        // 左列：击杀
        html += '<div>';
        html += '<h3 class="text-lg font-semibold text-green-400 mb-4 flex items-center">';
        html += '<span class="mr-2">⚔️</span> 击杀 <span class="ml-2 text-sm text-white/50">(' + allKills.length + ')</span>';
        html += '</h3>';
        if (allKills.length === 0) {
            html += '<div class="bg-white/5 rounded-lg p-6 text-center text-white/50">暂无击杀记录</div>';
        } else {
            html += '<div id="kills-container" class="space-y-3">';
            var killsToShow = allKills.slice(0, KMS_PER_LOAD);
            killsToShow.forEach(function(km) {
                html += renderKmCard(km, false);
            });
            displayedKills = killsToShow.length;
            html += '</div>';
            
            // 击杀"加载更多"按钮
            if (allKills.length > KMS_PER_LOAD) {
                html += '<div class="text-center mt-3">';
                html += '<button id="load-more-kills" onclick="loadMoreKills()" class="px-4 py-2 bg-green-600/30 hover:bg-green-600/50 border border-green-400/30 rounded-lg text-green-200 text-sm transition-all">';
                html += '加载更多 (已显示 ' + displayedKills + ' / ' + allKills.length + ')';
                html += '</button></div>';
            }
        }
        html += '</div>';
        
        // 右列：损失
        html += '<div>';
        html += '<h3 class="text-lg font-semibold text-red-400 mb-4 flex items-center">';
        html += '<span class="mr-2">💀</span> 损失 <span class="ml-2 text-sm text-white/50">(' + allLosses.length + ')</span>';
        html += '</h3>';
        if (allLosses.length === 0) {
            html += '<div class="bg-white/5 rounded-lg p-6 text-center text-white/50">暂无损失记录</div>';
        } else {
            html += '<div id="losses-container" class="space-y-3">';
            var lossesToShow = allLosses.slice(0, KMS_PER_LOAD);
            lossesToShow.forEach(function(km) {
                html += renderKmCard(km, true);
            });
            displayedLosses = lossesToShow.length;
            html += '</div>';
            
            // 损失"加载更多"按钮
            if (allLosses.length > KMS_PER_LOAD) {
                html += '<div class="text-center mt-3">';
                html += '<button id="load-more-losses" onclick="loadMoreLosses()" class="px-4 py-2 bg-red-600/30 hover:bg-red-600/50 border border-red-400/30 rounded-lg text-red-200 text-sm transition-all">';
                html += '加载更多 (已显示 ' + displayedLosses + ' / ' + allLosses.length + ')';
                html += '</button></div>';
            }
        }
        html += '</div>';
        
        html += '</div>';
        area.innerHTML = html;
    }

    function loadMoreKills() {
        var container = document.getElementById('kills-container');
        var btn = document.getElementById('load-more-kills');
        if (!container || !btn) return;
        
        var nextBatch = allKills.slice(displayedKills, displayedKills + KMS_PER_LOAD);
        if (nextBatch.length === 0) return;
        
        var html = '';
        nextBatch.forEach(function(km) {
            html += renderKmCard(km, false);
        });
        
        container.insertAdjacentHTML('beforeend', html);
        displayedKills += nextBatch.length;
        
        if (displayedKills >= allKills.length) {
            btn.textContent = '已加载全部 (' + allKills.length + ' 条)';
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');
            btn.classList.remove('hover:bg-green-600/50');
        } else {
            btn.textContent = '加载更多 (已显示 ' + displayedKills + ' / ' + allKills.length + ')';
        }
    }

    function loadMoreLosses() {
        var container = document.getElementById('losses-container');
        var btn = document.getElementById('load-more-losses');
        if (!container || !btn) return;
        
        var nextBatch = allLosses.slice(displayedLosses, displayedLosses + KMS_PER_LOAD);
        if (nextBatch.length === 0) return;
        
        var html = '';
        nextBatch.forEach(function(km) {
            html += renderKmCard(km, true);
        });
        
        container.insertAdjacentHTML('beforeend', html);
        displayedLosses += nextBatch.length;
        
        if (displayedLosses >= allLosses.length) {
            btn.textContent = '已加载全部 (' + allLosses.length + ' 条)';
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');
            btn.classList.remove('hover:bg-red-600/50');
        } else {
            btn.textContent = '加载更多 (已显示 ' + displayedLosses + ' / ' + allLosses.length + ')';
        }
    }

    // KM 详情
    function openKillDetail(killId) {
        var modal = document.getElementById('detailModal');
        var content = document.getElementById('detailContent');
        document.getElementById('detailTitle').textContent = 'KM #' + killId + ' 详情';
        content.innerHTML = '<div class="text-center py-12"><div class="spinner mx-auto mb-3"></div><p class="text-blue-200 text-sm">加载中...</p></div>';
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';

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

        // 尝试从 beta KB 获取 hash
        fetch(BETA_KB_URL + '/app/kill/' + killId + '/info', { mode: 'cors', credentials: 'omit' })
            .then(function(r) { return r.text(); })
            .then(function(text) {
                var m = text.match(/[a-f0-9]{40}/);
                fetchDetail(m ? m[0] : null);
            })
            .catch(function() { fetchDetail(null); });
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

        // 受害者信息
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
        html += '<a href="' + KB_URL + '/kill/' + d.kill_id + '/" target="_blank" class="text-blue-400 hover:underline">KB 链接</a>';
        html += '</div></div>';

        // 两列布局: 攻击者 | 装配/物品
        html += '<div class="grid grid-cols-1 lg:grid-cols-10 gap-4">';

        // 左侧: 攻击者
        html += '<div class="lg:col-span-3 bg-white/5 rounded-lg border border-white/10 overflow-hidden">';
        html += '<div class="px-4 py-2 border-b border-white/10 font-bold text-sm">攻击者 (' + d.attacker_count + ')</div>';
        html += '<div class="divide-y divide-white/5 max-h-[600px] overflow-y-auto">';

        var attackers = d.attackers || [];
        attackers.forEach(function(atk, idx) {
            var badge = '';
            if (atk.final_blow) {
                badge = '<span class="text-yellow-400 text-xs font-bold ml-1">⭐ 最后一击</span>';
            } else if (idx === 0 && !atk.final_blow) {
                badge = '<span class="text-orange-400 text-xs font-bold ml-1">🏆 最高伤害</span>';
            } else if (idx === 1 && attackers[0] && attackers[0].final_blow) {
                badge = '<span class="text-orange-400 text-xs font-bold ml-1">🏆 最高伤害</span>';
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

        // 右侧: 装配/物品
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
        html += '</div>';

        content.innerHTML = html;
    }

    // 加载数据
    fetch('/api/dashboard/killmails')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) {
                document.getElementById('killmails-area').innerHTML = '<div class="text-center py-8 text-red-400">' + escapeHtml(data.error) + '</div>';
                return;
            }
            renderKillmails(data);
        })
        .catch(function() {
            document.getElementById('killmails-area').innerHTML = '<div class="text-center py-8 text-red-400">加载失败</div>';
        });

    // ESC 关闭模态框
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeDetail();
    });
</script>
@endpush
