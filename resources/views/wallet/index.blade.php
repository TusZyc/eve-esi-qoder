@extends('layouts.app')

@push('styles')
<style>
    .eve-glow { box-shadow: 0 0 20px rgba(59, 130, 246, 0.5); }
    @keyframes shimmer { 0% { background-position: -1000px 0; } 100% { background-position: 1000px 0; } }
    .skeleton { background: linear-gradient(90deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.08) 50%, rgba(255,255,255,0.03) 100%); background-size: 1000px 100%; animation: shimmer 2s infinite; border-radius: 4px; }
</style>
@endpush

@section('title', '钱包 - Tus Esi System')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow">
            <h2 class="text-2xl font-semibold mb-6">💰 钱包</h2>
            
            <!-- 余额显示 -->
            <div class="bg-white/5 rounded-lg p-6 mb-6 text-center">
                <div class="text-sm text-blue-300 mb-2">当前余额</div>
                <div class="text-4xl font-bold text-yellow-400" id="balance-display"><div class="skeleton h-10 w-48 mx-auto"></div></div>
                <div class="text-sm text-blue-300 mt-1">ISK</div>
            </div>
            
            <!-- 标签切换 -->
            <div class="flex space-x-4 mb-6 border-b border-white/20 pb-4">
                <button onclick="showTab('journal')" id="tab-journal" class="tab-btn px-4 py-2 rounded-lg bg-blue-600/50 text-white">流水</button>
                <button onclick="showTab('transactions')" id="tab-transactions" class="tab-btn px-4 py-2 rounded-lg bg-white/5 hover:bg-white/10 text-blue-200">交易</button>
                <button onclick="showTab('loyalty')" id="tab-loyalty" class="tab-btn px-4 py-2 rounded-lg bg-white/5 hover:bg-white/10 text-blue-200">LP点数</button>
            </div>
            
            <div id="wallet-area">
                <div class="space-y-3">
                    <div class="skeleton h-16 w-full"></div>
                    <div class="skeleton h-16 w-full"></div>
                    <div class="skeleton h-16 w-full"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    var currentTab = 'journal';
    var walletData = { journal: [], transactions: [], loyalty: [] };

    function formatNumber(n) { return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
    function formatIsk(n) { return formatNumber(Math.round(n)) + ' ISK'; }
    function formatDate(dateStr) {
        if (!dateStr) return '-';
        var d = new Date(dateStr);
        return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0') + ' ' + String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
    }
    function escapeHtml(s) { var div = document.createElement('div'); div.textContent = s || ''; return div.innerHTML; }

    function showTab(tab) {
        currentTab = tab;
        document.querySelectorAll('.tab-btn').forEach(function(btn) {
            btn.classList.remove('bg-blue-600/50', 'text-white');
            btn.classList.add('bg-white/5', 'text-blue-200');
        });
        document.getElementById('tab-' + tab).classList.remove('bg-white/5', 'text-blue-200');
        document.getElementById('tab-' + tab).classList.add('bg-blue-600/50', 'text-white');
        loadTabData(tab);
    }

    function loadTabData(tab) {
        var area = document.getElementById('wallet-area');
        area.innerHTML = '<div class="space-y-3"><div class="skeleton h-16 w-full"></div><div class="skeleton h-16 w-full"></div></div>';
        
        var endpoint = '/api/dashboard/wallet/' + tab;
        fetch(endpoint)
            .then(function(r) { return r.json(); })
            .then(function(data) { renderTab(tab, data); })
            .catch(function() { area.innerHTML = '<div class="text-center py-8 text-red-400">加载失败</div>'; });
    }

    function renderTab(tab, data) {
        var area = document.getElementById('wallet-area');
        
        if (tab === 'journal') {
            var entries = data.data || data;
            if (!entries || entries.length === 0) {
                area.innerHTML = '<div class="text-center py-12 text-blue-200">暂无流水记录</div>';
                return;
            }
            var html = '<div class="space-y-2">';
            entries.slice(0, 50).forEach(function(entry) {
                var amount = entry.amount || 0;
                var amountClass = amount >= 0 ? 'text-green-400' : 'text-red-400';
                var amountStr = (amount >= 0 ? '+' : '') + formatIsk(amount);
                html += '<div class="bg-white/5 rounded-lg p-3 hover:bg-white/10 transition-colors">';
                html += '<div class="flex justify-between items-center">';
                html += '<div><div class="font-medium text-sm">' + escapeHtml(entry.ref_type_name || entry.ref_type) + '</div>';
                html += '<div class="text-xs text-blue-300/70">' + formatDate(entry.date) + '</div></div>';
                html += '<div class="text-right"><div class="' + amountClass + ' font-bold">' + amountStr + '</div>';
                html += '<div class="text-xs text-blue-300/70">余额: ' + formatIsk(entry.balance || 0) + '</div></div>';
                html += '</div></div>';
            });
            html += '</div>';
            area.innerHTML = html;
        } else if (tab === 'transactions') {
            if (!data || data.length === 0) {
                area.innerHTML = '<div class="text-center py-12 text-blue-200">暂无交易记录</div>';
                return;
            }
            var html = '<div class="space-y-2">';
            data.slice(0, 50).forEach(function(tx) {
                var isBuy = tx.is_buy;
                var total = (tx.unit_price || 0) * (tx.quantity || 0);
                var typeClass = isBuy ? 'text-red-400' : 'text-green-400';
                var typeLabel = isBuy ? '购买' : '出售';
                html += '<div class="bg-white/5 rounded-lg p-3 hover:bg-white/10 transition-colors">';
                html += '<div class="flex justify-between items-center">';
                html += '<div><div class="font-medium text-sm">' + escapeHtml(tx.type_name) + ' x' + tx.quantity + '</div>';
                html += '<div class="text-xs text-blue-300/70">' + formatDate(tx.date) + '</div></div>';
                html += '<div class="text-right"><span class="' + typeClass + ' font-bold">' + typeLabel + '</span>';
                html += '<div class="text-sm">' + formatIsk(total) + '</div></div>';
                html += '</div></div>';
            });
            html += '</div>';
            area.innerHTML = html;
        } else if (tab === 'loyalty') {
            if (!data || data.length === 0) {
                area.innerHTML = '<div class="text-center py-12 text-blue-200">暂无LP点数</div>';
                return;
            }
            var totalLP = data.reduce(function(sum, item) { return sum + (item.loyalty_points || 0); }, 0);
            var html = '<div class="bg-white/5 rounded-lg p-4 mb-4 text-center"><span class="text-blue-300">总计 LP: </span><span class="text-xl font-bold text-yellow-400">' + formatNumber(totalLP) + '</span></div>';
            html += '<div class="grid gap-3">';
            data.forEach(function(item) {
                html += '<div class="bg-white/5 rounded-lg p-4 hover:bg-white/10 transition-colors flex justify-between items-center">';
                html += '<span class="font-medium">' + escapeHtml(item.corporation_name) + '</span>';
                html += '<span class="text-yellow-400 font-bold">' + formatNumber(item.loyalty_points || 0) + ' LP</span>';
                html += '</div>';
            });
            html += '</div>';
            area.innerHTML = html;
        }
    }

    // 加载余额
    fetch('/api/dashboard/wallet/balance')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById('balance-display').textContent = formatNumber(Math.round(data.balance || 0));
        })
        .catch(function() {
            document.getElementById('balance-display').textContent = '-';
        });

    // 默认加载流水
    loadTabData('journal');
</script>
@endpush
