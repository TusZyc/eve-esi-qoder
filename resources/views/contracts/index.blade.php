@extends('layouts.app')

@push('styles')
<style>
    .eve-glow { box-shadow: 0 0 20px rgba(59, 130, 246, 0.5); }
    @keyframes shimmer { 0% { background-position: -1000px 0; } 100% { background-position: 1000px 0; } }
    .skeleton { background: linear-gradient(90deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.08) 50%, rgba(255,255,255,0.03) 100%); background-size: 1000px 100%; animation: shimmer 2s infinite; border-radius: 4px; }
    .contract-row { cursor: pointer; transition: all 0.2s; }
    .contract-row:hover { background: rgba(255,255,255,0.1); }
    .contract-detail { display: none; background: rgba(30, 41, 59, 0.8); }
    .contract-detail.active { display: table-row; }
</style>
@endpush

@section('title', '合同 - Tus Esi System')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-2xl font-semibold">📜 合同</h2>
                <div class="text-xs text-slate-400 bg-slate-800/50 px-3 py-1.5 rounded-lg">
                    <span class="text-amber-400">⚠️</span> ESI限制：仅显示30天内或未完成的合同
                </div>
            </div>
            
            <!-- 筛选栏 -->
            <div class="flex flex-wrap gap-4 mb-6">
                <select id="filter-type" class="bg-slate-800/50 border border-slate-600/50 rounded-lg px-4 py-2 text-white text-sm focus:outline-none focus:border-blue-500">
                    <option value="">全部类型</option>
                    <option value="auction">拍卖</option>
                    <option value="item_exchange">物品交易</option>
                    <option value="courier">快递</option>
                </select>
                <select id="filter-status" class="bg-slate-800/50 border border-slate-600/50 rounded-lg px-4 py-2 text-white text-sm focus:outline-none focus:border-blue-500">
                    <option value="">全部状态</option>
                    <option value="outstanding">未决</option>
                    <option value="in_progress">正在进行</option>
                    <option value="finished">已结束</option>
                    <option value="attention">请关注</option>
                </select>
                <span id="filter-count" class="text-sm text-blue-300/70 self-center ml-auto"></span>
            </div>
            
            <div id="contracts-area">
                <div class="space-y-3">
                    <div class="skeleton h-12 w-full"></div>
                    <div class="skeleton h-12 w-full"></div>
                    <div class="skeleton h-12 w-full"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 物品详情模态框 -->
    <div id="items-modal" class="fixed inset-0 bg-black/70 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
        <div class="bg-gray-900/95 rounded-xl p-6 max-w-2xl w-full max-h-[80vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold">合同物品</h3>
                <button onclick="closeItemsModal()" class="text-gray-400 hover:text-white text-2xl">&times;</button>
            </div>
            <div id="items-content"></div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    var allContracts = [];
    var filteredContracts = [];
    var CONTRACTS_PER_PAGE = 10;
    var currentContractPage = 1;

    function formatNumber(n) { return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
    function formatIsk(n) { return formatNumber(Math.round(n)) + ' ISK'; }
    function formatDate(dateStr) {
        if (!dateStr) return '-';
        var d = new Date(dateStr);
        return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0') + ' ' + String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
    }
    function escapeHtml(s) { var div = document.createElement('div'); div.textContent = s || ''; return div.innerHTML; }

    function getStatusColor(status) {
        switch(status) {
            case 'outstanding': case 'in_progress': return 'text-yellow-400';
            case 'finished': case 'finished_issuer': case 'finished_contractor': return 'text-green-400';
            case 'cancelled': case 'rejected': case 'failed': case 'deleted': return 'text-red-400';
            default: return 'text-gray-400';
        }
    }

    function getStatusGroup(status) {
        if (status === 'outstanding' || status === 'in_progress') return status;
        if (status === 'finished' || status === 'finished_issuer' || status === 'finished_contractor') return 'finished';
        return 'attention';
    }

    function getTypeIcon(type) {
        switch(type) {
            case 'item_exchange': return '🔄';
            case 'auction': return '🔨';
            case 'courier': return '📦';
            default: return '📜';
        }
    }

    function getPriceDisplay(contract) {
        var parts = [];
        if (contract.price > 0) parts.push('<span class="text-yellow-400">' + formatIsk(contract.price) + '</span>');
        if (contract.reward > 0) parts.push('<span class="text-green-400">奖励 ' + formatIsk(contract.reward) + '</span>');
        if (contract.collateral > 0) parts.push('<span class="text-blue-300">抵押 ' + formatIsk(contract.collateral) + '</span>');
        return parts.length ? parts.join(' / ') : '-';
    }

    function filterContracts() {
        var typeFilter = document.getElementById('filter-type').value;
        var statusFilter = document.getElementById('filter-status').value;

        return allContracts.filter(function(c) {
            if (typeFilter && c.type !== typeFilter) return false;
            if (statusFilter && getStatusGroup(c.status) !== statusFilter) return false;
            return true;
        });
    }

    function renderContracts(contracts) {
        var area = document.getElementById('contracts-area');
        filteredContracts = contracts;
        currentContractPage = 1;
        
        var totalCount = filteredContracts.length;
        var displayCount = Math.min(CONTRACTS_PER_PAGE, totalCount);
        document.getElementById('filter-count').textContent = '显示 ' + displayCount + ' / ' + totalCount + ' 条';

        if (!contracts.length) {
            area.innerHTML = '<div class="text-center py-12"><div class="text-6xl mb-4">📭</div><p class="text-xl text-blue-200">暂无匹配的合同</p></div>';
            return;
        }

        var contractsToShow = contracts.slice(0, CONTRACTS_PER_PAGE);
        var html = '<div class="overflow-x-auto"><table class="w-full text-sm">';
        html += '<thead><tr class="text-white/60 text-xs border-b border-white/10">';
        html += '<th class="px-3 py-3 text-left">合同标题</th>';
        html += '<th class="px-3 py-3 text-left">类型</th>';
        html += '<th class="px-3 py-3 text-left">从 (发布者)</th>';
        html += '<th class="px-3 py-3 text-left">到 (接受者)</th>';
        html += '<th class="px-3 py-3 text-left">状态</th>';
        html += '<th class="px-3 py-3 text-left">完成日期</th>';
        html += '<th class="px-3 py-3 text-right">价格信息</th>';
        html += '</tr></thead><tbody id="contracts-tbody">';

        contractsToShow.forEach(function(c, idx) {
            html += renderContractRow(c);
        });

        html += '</tbody></table></div>';
        
        // 添加"加载更多"按钮
        if (totalCount > CONTRACTS_PER_PAGE) {
            html += '<div class="text-center mt-4">';
            html += '<button id="load-more-contracts" onclick="loadMoreContracts()" class="px-6 py-2 bg-blue-600/30 hover:bg-blue-600/50 border border-blue-400/30 rounded-lg text-blue-200 text-sm transition-all">';
            html += '加载更多 (已显示 ' + displayCount + ' / ' + totalCount + ')';
            html += '</button></div>';
        }
        
        area.innerHTML = html;
    }

    function renderContractRow(c) {
        var rowId = 'row-' + c.contract_id;
        var detailId = 'detail-' + c.contract_id;
        
        var html = '<tr id="' + rowId + '" class="contract-row border-b border-white/5" onclick="toggleDetail(' + c.contract_id + ')">';
        html += '<td class="px-3 py-3"><div class="font-medium">' + escapeHtml(c.title || c.type_name || '-') + '</div></td>';
        html += '<td class="px-3 py-3"><span class="mr-1">' + getTypeIcon(c.type) + '</span>' + escapeHtml(c.type_name) + '</td>';
        html += '<td class="px-3 py-3">' + escapeHtml(c.issuer_name || c.issuer_corporation_name || '-') + '</td>';
        html += '<td class="px-3 py-3">' + escapeHtml(c.assignee_name || c.assignee_corporation_name || '-') + '</td>';
        html += '<td class="px-3 py-3 ' + getStatusColor(c.status) + '">' + escapeHtml(c.status_name) + '</td>';
        html += '<td class="px-3 py-3 text-white/60">' + formatDate(c.date_completed || c.date_expired) + '</td>';
        html += '<td class="px-3 py-3 text-right text-xs">' + getPriceDisplay(c) + '</td>';
        html += '</tr>';

        // 详情行
        html += '<tr id="' + detailId + '" class="contract-detail"><td colspan="7" class="p-0">';
        html += '<div class="p-4 border-l-4 border-blue-500">';
        html += renderContractDetail(c);
        html += '</div></td></tr>';
        
        return html;
    }

    function loadMoreContracts() {
        var tbody = document.getElementById('contracts-tbody');
        var btn = document.getElementById('load-more-contracts');
        if (!tbody || !btn) return;
        
        var startIdx = currentContractPage * CONTRACTS_PER_PAGE;
        var endIdx = startIdx + CONTRACTS_PER_PAGE;
        var nextBatch = filteredContracts.slice(startIdx, endIdx);
        
        if (nextBatch.length === 0) return;
        
        var html = '';
        nextBatch.forEach(function(c) {
            html += renderContractRow(c);
        });
        
        tbody.insertAdjacentHTML('beforeend', html);
        currentContractPage++;
        
        var totalCount = filteredContracts.length;
        var displayCount = Math.min(currentContractPage * CONTRACTS_PER_PAGE, totalCount);
        document.getElementById('filter-count').textContent = '显示 ' + displayCount + ' / ' + totalCount + ' 条';
        
        if (displayCount >= totalCount) {
            btn.textContent = '已加载全部 (' + totalCount + ' 条)';
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');
            btn.classList.remove('hover:bg-blue-600/50');
        } else {
            btn.textContent = '加载更多 (已显示 ' + displayCount + ' / ' + totalCount + ')';
        }
    }

    function renderContractDetail(c) {
        var html = '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">';
        
        // 基本信息
        html += '<div class="space-y-2">';
        html += '<div class="font-semibold text-blue-300 mb-2">基本信息</div>';
        html += '<div><span class="text-white/50">合同 ID:</span> ' + c.contract_id + '</div>';
        html += '<div><span class="text-white/50">类型:</span> ' + getTypeIcon(c.type) + ' ' + escapeHtml(c.type_name) + '</div>';
        html += '<div><span class="text-white/50">状态:</span> <span class="' + getStatusColor(c.status) + '">' + escapeHtml(c.status_name) + '</span></div>';
        if (c.for_corporation) html += '<div><span class="text-white/50">军团合同:</span> 是</div>';
        html += '<div><span class="text-white/50">可见性:</span> ' + escapeHtml(c.availability || '-') + '</div>';
        html += '</div>';

        // 参与方
        html += '<div class="space-y-2">';
        html += '<div class="font-semibold text-blue-300 mb-2">参与方</div>';
        html += '<div><span class="text-white/50">发布者:</span> ' + escapeHtml(c.issuer_name || '-');
        if (c.issuer_corporation_name) html += ' <span class="text-white/40">[' + escapeHtml(c.issuer_corporation_name) + ']</span>';
        html += '</div>';
        html += '<div><span class="text-white/50">接受者:</span> ' + escapeHtml(c.assignee_name || c.assignee_corporation_name || '-') + '</div>';
        if (c.acceptor_name) html += '<div><span class="text-white/50">签约者:</span> ' + escapeHtml(c.acceptor_name) + '</div>';
        html += '</div>';

        // 时间
        html += '<div class="space-y-2">';
        html += '<div class="font-semibold text-blue-300 mb-2">时间</div>';
        html += '<div><span class="text-white/50">发布:</span> ' + formatDate(c.date_issued) + '</div>';
        html += '<div><span class="text-white/50">过期:</span> ' + formatDate(c.date_expired) + '</div>';
        if (c.date_accepted) html += '<div><span class="text-white/50">接受:</span> ' + formatDate(c.date_accepted) + '</div>';
        if (c.date_completed) html += '<div><span class="text-white/50">完成:</span> ' + formatDate(c.date_completed) + '</div>';
        html += '</div>';

        // 价格信息
        html += '<div class="space-y-2">';
        html += '<div class="font-semibold text-blue-300 mb-2">价格信息</div>';
        if (c.price > 0) html += '<div><span class="text-white/50">价格:</span> <span class="text-yellow-400">' + formatIsk(c.price) + '</span></div>';
        if (c.reward > 0) html += '<div><span class="text-white/50">奖励:</span> <span class="text-green-400">' + formatIsk(c.reward) + '</span></div>';
        if (c.collateral > 0) html += '<div><span class="text-white/50">抵押:</span> <span class="text-blue-300">' + formatIsk(c.collateral) + '</span></div>';
        if (c.buyout > 0) html += '<div><span class="text-white/50">一口价:</span> <span class="text-orange-400">' + formatIsk(c.buyout) + '</span></div>';
        html += '</div>';

        // 物流信息（快递合同）
        if (c.type === 'courier') {
            html += '<div class="space-y-2">';
            html += '<div class="font-semibold text-blue-300 mb-2">物流信息</div>';
            if (c.start_location_name) html += '<div><span class="text-white/50">起点:</span> ' + escapeHtml(c.start_location_name) + '</div>';
            if (c.end_location_name) html += '<div><span class="text-white/50">终点:</span> ' + escapeHtml(c.end_location_name) + '</div>';
            if (c.volume > 0) html += '<div><span class="text-white/50">体积:</span> ' + formatNumber(c.volume.toFixed(2)) + ' m³</div>';
            if (c.days_to_complete > 0) html += '<div><span class="text-white/50">完成天数:</span> ' + c.days_to_complete + ' 天</div>';
            html += '</div>';
        }

        html += '</div>';

        // 物品按钮
        if (c.type === 'item_exchange' || c.type === 'auction') {
            html += '<div class="mt-4 pt-4 border-t border-white/10">';
            html += '<button onclick="event.stopPropagation(); showItemsModal(' + c.contract_id + ')" class="px-4 py-2 bg-blue-600/30 hover:bg-blue-600/50 border border-blue-400/30 rounded-lg text-blue-200 text-sm transition-all">📦 查看合同物品</button>';
            html += '</div>';
        }

        return html;
    }

    function toggleDetail(contractId) {
        var detailRow = document.getElementById('detail-' + contractId);
        if (detailRow) {
            detailRow.classList.toggle('active');
        }
    }

    function showItemsModal(contractId) {
        var modal = document.getElementById('items-modal');
        var content = document.getElementById('items-content');
        content.innerHTML = '<div class="text-center py-8 text-blue-300">加载中...</div>';
        modal.classList.remove('hidden');
        
        fetch('/api/dashboard/contracts/' + contractId + '/items')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.error) {
                    content.innerHTML = '<div class="text-center py-8 text-red-400">' + escapeHtml(data.error) + '</div>';
                    return;
                }
                if (!data || data.length === 0) {
                    content.innerHTML = '<div class="text-center py-8 text-blue-200">无物品</div>';
                    return;
                }
                var html = '<div class="space-y-2">';
                data.forEach(function(item) {
                    var included = item.is_included ? '' : ' <span class="text-xs text-yellow-400">(需要)</span>';
                    html += '<div class="bg-white/5 rounded-lg p-3 flex justify-between items-center">';
                    html += '<span>' + escapeHtml(item.type_name) + included + '</span>';
                    html += '<span class="text-blue-300">x' + formatNumber(item.quantity || 1) + '</span>';
                    html += '</div>';
                });
                html += '</div>';
                content.innerHTML = html;
            })
            .catch(function() {
                content.innerHTML = '<div class="text-center py-8 text-red-400">加载失败</div>';
            });
    }

    function closeItemsModal() {
        document.getElementById('items-modal').classList.add('hidden');
    }

    function onFilterChange() {
        var filtered = filterContracts();
        renderContracts(filtered);
    }

    // 绑定筛选事件
    document.getElementById('filter-type').addEventListener('change', onFilterChange);
    document.getElementById('filter-status').addEventListener('change', onFilterChange);

    // 加载数据
    fetch('/api/dashboard/contracts')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var area = document.getElementById('contracts-area');
            if (data.error) {
                area.innerHTML = '<div class="text-center py-8 text-red-400">' + escapeHtml(data.error) + '</div>';
                return;
            }
            if (!data || data.length === 0) {
                area.innerHTML = '<div class="text-center py-12"><div class="text-6xl mb-4">📭</div><p class="text-xl text-blue-200">暂无合同</p></div>';
                return;
            }

            allContracts = data;
            renderContracts(allContracts);
        })
        .catch(function() {
            document.getElementById('contracts-area').innerHTML = '<div class="text-center py-8 text-red-400">加载失败</div>';
        });

    document.getElementById('items-modal').addEventListener('click', function(e) {
        if (e.target === this) closeItemsModal();
    });
</script>
@endpush
