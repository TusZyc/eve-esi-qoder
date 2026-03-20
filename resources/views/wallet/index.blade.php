@extends('layouts.app')

@push('styles')
<style>
    .eve-glow { box-shadow: 0 0 20px rgba(59, 130, 246, 0.5); }
    @keyframes shimmer { 0% { background-position: -1000px 0; } 100% { background-position: 1000px 0; } }
    .skeleton { background: linear-gradient(90deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.08) 50%, rgba(255,255,255,0.03) 100%); background-size: 1000px 100%; animation: shimmer 2s infinite; border-radius: 4px; }
    .stat-card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); }
    .stat-card:hover { background: rgba(255,255,255,0.08); }
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th { text-align: left; padding: 12px; color: #93c5fd; font-weight: 500; border-bottom: 1px solid rgba(255,255,255,0.1); font-size: 0.875rem; }
    .data-table td { padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.875rem; }
    .data-table tbody tr:hover { background: rgba(255,255,255,0.05); }
    .positive { color: #4ade80; }
    .negative { color: #f87171; }
    .corp-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 50; }
    .corp-modal.active { display: flex; align-items: center; justify-content: center; }
    .corp-modal-content { background: #1e293b; border: 1px solid rgba(255,255,255,0.1); border-radius: 1rem; max-width: 800px; width: 90%; max-height: 80vh; overflow-y: auto; }
</style>
@endpush

@section('title', '钱包 - Tus Esi System')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow">
            <!-- 顶部：标题 + 军团钱包按钮 -->
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-semibold">💰 钱包</h2>
                <button id="corp-wallet-btn" onclick="openCorpWallet()" class="hidden px-4 py-2 bg-blue-600/50 hover:bg-blue-600/70 rounded-lg text-sm transition-colors">
                    🏢 军团账户
                </button>
            </div>
            
            <!-- 余额区域：双卡片 -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <!-- 左卡片：当前余额 -->
                <div class="stat-card rounded-xl p-6">
                    <div class="text-sm text-blue-300 mb-2">当前余额</div>
                    <div class="text-3xl font-bold text-yellow-400" id="balance-display">
                        <div class="skeleton h-9 w-48"></div>
                    </div>
                    <div class="text-sm text-blue-300/70 mt-1">ISK</div>
                </div>
                
                <!-- 右卡片：30天收支统计 -->
                <div class="stat-card rounded-xl p-6">
                    <div class="text-sm text-blue-300 mb-2">最近 30 天收支</div>
                    <div id="summary-display" class="space-y-1">
                        <div class="skeleton h-6 w-full"></div>
                        <div class="skeleton h-6 w-full"></div>
                        <div class="skeleton h-6 w-full"></div>
                    </div>
                </div>
            </div>
            
            <!-- 标签切换 -->
            <div class="flex space-x-4 mb-6 border-b border-white/20 pb-4">
                <button onclick="showTab('journal')" id="tab-journal" class="tab-btn px-4 py-2 rounded-lg bg-blue-600/50 text-white">流水</button>
                <button onclick="showTab('transactions')" id="tab-transactions" class="tab-btn px-4 py-2 rounded-lg bg-white/5 hover:bg-white/10 text-blue-200">交易</button>
                <button onclick="showTab('loyalty')" id="tab-loyalty" class="tab-btn px-4 py-2 rounded-lg bg-white/5 hover:bg-white/10 text-blue-200">LP点数</button>
            </div>
            
            <div id="wallet-area">
                <div class="space-y-3">
                    <div class="skeleton h-12 w-full"></div>
                    <div class="skeleton h-12 w-full"></div>
                    <div class="skeleton h-12 w-full"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 军团钱包模态框 -->
    <div id="corp-modal" class="corp-modal">
        <div class="corp-modal-content p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold">🏢 <span id="corp-name">军团钱包</span></h3>
                <button onclick="closeCorpWallet()" class="text-gray-400 hover:text-white text-2xl">&times;</button>
            </div>
            <!-- 军团钱包标签切换 -->
            <div class="flex space-x-4 mb-4 border-b border-white/20 pb-3">
                <button onclick="showCorpTab('balance')" id="corp-tab-balance" class="corp-tab-btn px-4 py-2 rounded-lg bg-blue-600/50 text-white">余额</button>
                <button onclick="showCorpTab('journal')" id="corp-tab-journal" class="corp-tab-btn px-4 py-2 rounded-lg bg-white/5 hover:bg-white/10 text-blue-200">交易</button>
            </div>
            <!-- 部门选择器（仅在交易标签页显示） -->
            <div id="corp-division-selector" class="hidden mb-4">
                <label class="text-sm text-blue-300 mr-2">部门:</label>
                <select id="corp-division-select" onchange="loadCorpJournal()" class="bg-slate-800/80 text-white border border-white/20 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-blue-500 appearance-none cursor-pointer" style="background-image: url('data:image/svg+xml;utf8,<svg fill=\"white\" viewBox=\"0 0 24 24\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M7 10l5 5 5-5z\"/></svg>'); background-repeat: no-repeat; background-position: right 8px center; background-size: 16px; padding-right: 28px;">
                    <option style="background: #1e293b; color: white;" value="1">主账户</option>
                    <option style="background: #1e293b; color: white;" value="2">部门 2</option>
                    <option style="background: #1e293b; color: white;" value="3">部门 3</option>
                    <option style="background: #1e293b; color: white;" value="4">部门 4</option>
                    <option style="background: #1e293b; color: white;" value="5">部门 5</option>
                    <option style="background: #1e293b; color: white;" value="6">部门 6</option>
                    <option style="background: #1e293b; color: white;" value="7">部门 7</option>
                </select>
            </div>
            <div id="corp-wallet-content">
                <div class="space-y-3">
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
        area.innerHTML = '<div class="space-y-3"><div class="skeleton h-12 w-full"></div><div class="skeleton h-12 w-full"></div></div>';
        
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
            var html = '<div class="overflow-x-auto"><table class="data-table">';
            html += '<thead><tr><th>日期</th><th>类型</th><th>数量</th><th>余额</th><th>描述</th></tr></thead>';
            html += '<tbody>';
            entries.slice(0, 50).forEach(function(entry) {
                var amount = entry.amount || 0;
                var amountClass = amount >= 0 ? 'positive' : 'negative';
                var amountStr = (amount >= 0 ? '+' : '') + formatIsk(amount);
                var desc = entry.reason || entry.description || '-';
                html += '<tr>';
                html += '<td class="text-blue-300/70">' + formatDate(entry.date) + '</td>';
                html += '<td>' + escapeHtml(entry.ref_type_name || entry.ref_type) + '</td>';
                html += '<td class="' + amountClass + ' font-medium">' + amountStr + '</td>';
                html += '<td class="text-yellow-400/80">' + formatIsk(entry.balance || 0) + '</td>';
                html += '<td class="text-gray-400 max-w-xs truncate">' + escapeHtml(desc) + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            area.innerHTML = html;
        } else if (tab === 'transactions') {
            var txList = Array.isArray(data) ? data : (data.data || []);
            if (!txList || txList.length === 0) {
                area.innerHTML = '<div class="text-center py-12 text-blue-200">暂无交易记录</div>';
                return;
            }
            var html = '<div class="overflow-x-auto"><table class="data-table">';
            html += '<thead><tr><th>日期</th><th>类型</th><th>数量</th><th>单价</th><th>总价</th><th>物品</th></tr></thead>';
            html += '<tbody>';
            txList.slice(0, 50).forEach(function(tx) {
                var isBuy = tx.is_buy;
                var total = (tx.unit_price || 0) * (tx.quantity || 0);
                var typeClass = isBuy ? 'negative' : 'positive';
                var typeLabel = isBuy ? '购买' : '出售';
                html += '<tr>';
                html += '<td class="text-blue-300/70">' + formatDate(tx.date) + '</td>';
                html += '<td class="' + typeClass + ' font-medium">' + typeLabel + '</td>';
                html += '<td>' + formatNumber(tx.quantity || 0) + '</td>';
                html += '<td class="text-blue-200">' + formatIsk(tx.unit_price || 0) + '</td>';
                html += '<td class="' + typeClass + ' font-medium">' + formatIsk(total) + '</td>';
                html += '<td>' + escapeHtml(tx.type_name || '未知物品') + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            area.innerHTML = html;
        } else if (tab === 'loyalty') {
            var lpList = Array.isArray(data) ? data : (data.data || []);
            if (!lpList || lpList.length === 0) {
                area.innerHTML = '<div class="text-center py-12 text-blue-200">暂无LP点数</div>';
                return;
            }
            var html = '<div class="overflow-x-auto"><table class="data-table">';
            html += '<thead><tr><th>军团名称</th><th class="text-right">LP 数量</th></tr></thead>';
            html += '<tbody>';
            lpList.forEach(function(item) {
                html += '<tr>';
                html += '<td>' + escapeHtml(item.corporation_name) + '</td>';
                html += '<td class="text-right text-yellow-400 font-bold">' + formatNumber(item.loyalty_points || 0) + ' LP</td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            area.innerHTML = html;
        }
    }

    // 加载余额和30天统计
    fetch('/api/dashboard/wallet/balance')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById('balance-display').textContent = formatNumber(Math.round(data.balance || 0));
            
            // 显示30天统计
            var summary = data.summary_30d || {};
            var summaryHtml = '';
            summaryHtml += '<div class="flex justify-between"><span class="text-blue-300/70">收入:</span><span class="positive font-medium">+' + formatIsk(summary.income || 0) + '</span></div>';
            summaryHtml += '<div class="flex justify-between"><span class="text-blue-300/70">支出:</span><span class="negative font-medium">-' + formatIsk(summary.expense || 0) + '</span></div>';
            var netClass = (summary.net || 0) >= 0 ? 'positive' : 'negative';
            var netPrefix = (summary.net || 0) >= 0 ? '+' : '';
            summaryHtml += '<div class="flex justify-between border-t border-white/10 pt-1 mt-1"><span class="text-blue-300/70">净收入:</span><span class="' + netClass + ' font-bold">' + netPrefix + formatIsk(summary.net || 0) + '</span></div>';
            document.getElementById('summary-display').innerHTML = summaryHtml;
        })
        .catch(function() {
            document.getElementById('balance-display').textContent = '-';
            document.getElementById('summary-display').innerHTML = '<div class="text-red-400">加载失败</div>';
        });

    // 检查军团钱包权限
    fetch('/api/dashboard/wallet/corp-permission')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.has_permission) {
                document.getElementById('corp-wallet-btn').classList.remove('hidden');
            }
        })
        .catch(function() {});

    // 军团钱包功能
    var corpCurrentTab = 'balance';
    
    function openCorpWallet() {
        document.getElementById('corp-modal').classList.add('active');
        corpCurrentTab = 'balance';
        updateCorpTabUI();
        loadCorpWallet();
    }

    function closeCorpWallet() {
        document.getElementById('corp-modal').classList.remove('active');
    }
    
    function showCorpTab(tab) {
        corpCurrentTab = tab;
        updateCorpTabUI();
        // 控制部门选择器显示/隐藏
        var divisionSelector = document.getElementById('corp-division-selector');
        if (tab === 'journal') {
            divisionSelector.classList.remove('hidden');
            loadCorpJournal();
        } else {
            divisionSelector.classList.add('hidden');
            loadCorpWallet();
        }
    }
    
    function updateCorpTabUI() {
        document.querySelectorAll('.corp-tab-btn').forEach(function(btn) {
            btn.classList.remove('bg-blue-600/50', 'text-white');
            btn.classList.add('bg-white/5', 'text-blue-200');
        });
        document.getElementById('corp-tab-' + corpCurrentTab).classList.remove('bg-white/5', 'text-blue-200');
        document.getElementById('corp-tab-' + corpCurrentTab).classList.add('bg-blue-600/50', 'text-white');
    }

    function loadCorpWallet() {
        var content = document.getElementById('corp-wallet-content');
        content.innerHTML = '<div class="space-y-3"><div class="skeleton h-16 w-full"></div><div class="skeleton h-16 w-full"></div></div>';
        
        fetch('/api/dashboard/wallet/corporation')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.error) {
                    content.innerHTML = '<div class="text-center py-8 text-red-400">' + escapeHtml(data.error) + '</div>';
                    return;
                }
                
                document.getElementById('corp-name').textContent = data.corporation_name || '军团钱包';
                
                var wallets = data.wallets || [];
                if (wallets.length === 0) {
                    content.innerHTML = '<div class="text-center py-8 text-blue-200">暂无军团钱包数据</div>';
                    return;
                }
                
                var html = '<div class="overflow-x-auto"><table class="data-table">';
                html += '<thead><tr><th>部门</th><th class="text-right">余额</th></tr></thead>';
                html += '<tbody>';
                var totalBalance = 0;
                wallets.forEach(function(w) {
                    totalBalance += w.balance || 0;
                    html += '<tr>';
                    html += '<td>' + escapeHtml(w.division_name) + '</td>';
                    html += '<td class="text-right text-yellow-400 font-medium">' + formatIsk(w.balance || 0) + '</td>';
                    html += '</tr>';
                });
                html += '<tr class="border-t border-white/20"><td class="font-bold">总计</td><td class="text-right text-yellow-400 font-bold">' + formatIsk(totalBalance) + '</td></tr>';
                html += '</tbody></table></div>';
                
                content.innerHTML = html;
            })
            .catch(function() {
                content.innerHTML = '<div class="text-center py-8 text-red-400">加载失败</div>';
            });
    }
    
    function loadCorpJournal() {
        var content = document.getElementById('corp-wallet-content');
        content.innerHTML = '<div class="space-y-3"><div class="skeleton h-12 w-full"></div><div class="skeleton h-12 w-full"></div><div class="skeleton h-12 w-full"></div></div>';
        
        var division = document.getElementById('corp-division-select').value;
        fetch('/api/dashboard/wallet/corporation/journal?division=' + division)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.error) {
                    content.innerHTML = '<div class="text-center py-8 text-red-400">' + escapeHtml(data.error) + '</div>';
                    return;
                }
                
                var journalList = data.data || [];
                if (journalList.length === 0) {
                    content.innerHTML = '<div class="text-center py-8 text-blue-200">暂无流水记录</div>';
                    return;
                }
                
                var html = '<div class="overflow-x-auto"><table class="data-table">';
                html += '<thead><tr><th>日期</th><th>类型</th><th>金额</th><th>余额</th><th>描述</th></tr></thead>';
                html += '<tbody>';
                journalList.slice(0, 50).forEach(function(entry) {
                    var amount = entry.amount || 0;
                    var amountClass = amount >= 0 ? 'positive' : 'negative';
                    var amountStr = (amount >= 0 ? '+' : '') + formatIsk(amount);
                    var desc = entry.reason || entry.description || '-';
                    html += '<tr>';
                    html += '<td class="text-blue-300/70">' + formatDate(entry.date) + '</td>';
                    html += '<td>' + escapeHtml(entry.ref_type_name || entry.ref_type) + '</td>';
                    html += '<td class="' + amountClass + ' font-medium">' + amountStr + '</td>';
                    html += '<td class="text-yellow-400/80">' + formatIsk(entry.balance || 0) + '</td>';
                    html += '<td class="text-gray-400 max-w-xs truncate">' + escapeHtml(desc) + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
                content.innerHTML = html;
            })
            .catch(function() {
                content.innerHTML = '<div class="text-center py-8 text-red-400">加载失败</div>';
            });
    }

    // 点击模态框外部关闭
    document.getElementById('corp-modal').addEventListener('click', function(e) {
        if (e.target === this) closeCorpWallet();
    });

    // 默认加载流水
    loadTabData('journal');
</script>
@endpush
