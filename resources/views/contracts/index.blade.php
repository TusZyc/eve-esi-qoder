@extends('layouts.app')

@push('styles')
<style>
    .eve-glow { box-shadow: 0 0 20px rgba(59, 130, 246, 0.5); }
    @keyframes shimmer { 0% { background-position: -1000px 0; } 100% { background-position: 1000px 0; } }
    .skeleton { background: linear-gradient(90deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.08) 50%, rgba(255,255,255,0.03) 100%); background-size: 1000px 100%; animation: shimmer 2s infinite; border-radius: 4px; }
</style>
@endpush

@section('title', '合同 - Tus Esi System')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow">
            <h2 class="text-2xl font-semibold mb-6">📜 合同</h2>
            
            <div id="contracts-area">
                <div class="space-y-3">
                    <div class="skeleton h-20 w-full"></div>
                    <div class="skeleton h-20 w-full"></div>
                    <div class="skeleton h-20 w-full"></div>
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

    function getTypeIcon(type) {
        switch(type) {
            case 'item_exchange': return '🔄';
            case 'auction': return '🔨';
            case 'courier': return '📦';
            default: return '📜';
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

            var html = '<div class="space-y-3">';
            data.forEach(function(contract) {
                html += '<div class="bg-white/5 rounded-lg p-4 hover:bg-white/10 transition-colors">';
                html += '<div class="flex justify-between items-start mb-2">';
                html += '<div class="flex items-center space-x-2">';
                html += '<span class="text-xl">' + getTypeIcon(contract.type) + '</span>';
                html += '<div>';
                html += '<div class="font-medium">' + escapeHtml(contract.title || contract.type_name) + '</div>';
                html += '<div class="text-sm text-blue-300/70">' + escapeHtml(contract.type_name) + '</div>';
                html += '</div></div>';
                html += '<div class="text-right">';
                html += '<div class="' + getStatusColor(contract.status) + ' font-medium">' + escapeHtml(contract.status_name) + '</div>';
                html += '<div class="text-xs text-blue-300/50">' + formatDate(contract.date_issued) + '</div>';
                html += '</div></div>';
                
                html += '<div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">';
                if (contract.price > 0) html += '<div><span class="text-blue-300/70">价格: </span><span class="text-yellow-400">' + formatIsk(contract.price) + '</span></div>';
                if (contract.reward > 0) html += '<div><span class="text-blue-300/70">奖励: </span><span class="text-green-400">' + formatIsk(contract.reward) + '</span></div>';
                if (contract.collateral > 0) html += '<div><span class="text-blue-300/70">抵押: </span><span>' + formatIsk(contract.collateral) + '</span></div>';
                if (contract.volume > 0) html += '<div><span class="text-blue-300/70">体积: </span><span>' + formatNumber(contract.volume.toFixed(2)) + ' m³</span></div>';
                html += '</div>';
                
                if (contract.type === 'item_exchange' || contract.type === 'auction') {
                    html += '<div class="mt-3"><button onclick="showItemsModal(' + contract.contract_id + ')" class="text-sm text-blue-400 hover:text-blue-300">查看物品 →</button></div>';
                }
                html += '</div>';
            });
            html += '</div>';
            area.innerHTML = html;
        })
        .catch(function() {
            document.getElementById('contracts-area').innerHTML = '<div class="text-center py-8 text-red-400">加载失败</div>';
        });

    document.getElementById('items-modal').addEventListener('click', function(e) {
        if (e.target === this) closeItemsModal();
    });
</script>
@endpush
