<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KM 查询 - EVE ESI</title>
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
</head>
<body class="eve-bg min-h-screen text-white">
    <!-- 导航栏 -->
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
                    <a href="{{ route('assets.index') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all" title="我的资产"><div class="text-2xl">📦</div></a>
                    <a href="{{ route('characters.index') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all" title="角色管理"><div class="text-2xl">👥</div></a>
                    <a href="{{ route('killmails.index') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all bg-white/10" title="KM 查询"><div class="text-2xl">⚔️</div></a>
                    <form action="{{ route('auth.logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg text-sm ml-2">登出</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <h2 class="text-2xl font-semibold mb-6">⚔️ KM 查询</h2>

        <!-- 搜索区域 -->
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 mb-6 eve-glow">
            <div class="grid md:grid-cols-2 gap-6">
                <!-- 角色搜索 -->
                <div>
                    <h3 class="text-lg font-semibold mb-3 text-blue-200">搜索角色</h3>
                    <div class="flex gap-3">
                        <input type="text" id="searchInput" placeholder="输入角色名..."
                            class="flex-1 px-4 py-2 bg-black/40 border border-white/20 rounded-lg text-white placeholder-white/40 focus:outline-none focus:border-blue-500 transition"
                            onkeydown="if(event.key==='Enter')searchCharacter()">
                        <button onclick="searchCharacter()" id="searchBtn"
                            class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition font-semibold">
                            搜索
                        </button>
                    </div>
                </div>
                <!-- KM ID 直接查询 -->
                <div>
                    <h3 class="text-lg font-semibold mb-3 text-blue-200">直接查询 KM</h3>
                    <div class="flex gap-3">
                        <input type="text" id="kmIdInput" placeholder="输入 KM ID..."
                            class="flex-1 px-4 py-2 bg-black/40 border border-white/20 rounded-lg text-white placeholder-white/40 focus:outline-none focus:border-blue-500 transition"
                            onkeydown="if(event.key==='Enter')directKmLookup()">
                        <button onclick="directKmLookup()" id="kmLookupBtn"
                            class="px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition font-semibold">
                            查询
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 搜索结果 -->
        <div id="searchResults" class="mb-6"></div>

        <!-- KM 列表 -->
        <div id="killList" class="mb-6"></div>

        <!-- KM 详情 -->
        <div id="killDetail" class="mb-6"></div>
    </div>

    <!-- KM 详情模态框 -->
    <div id="killModal" class="hidden fixed inset-0 bg-black/70 flex items-center justify-center z-50" onclick="if(event.target===this)closeModal()">
        <div class="bg-gray-900/95 backdrop-blur-lg rounded-xl p-6 max-w-4xl w-full max-h-[85vh] overflow-y-auto border border-white/20 mx-4">
            <div class="flex justify-between items-center mb-4 border-b border-white/20 pb-3">
                <h3 class="text-xl font-semibold" id="modalTitle">KM 详情</h3>
                <button onclick="closeModal()" class="text-white/60 hover:text-white text-2xl leading-none">&times;</button>
            </div>
            <div id="modalContent">
                <div class="text-center py-12"><div class="spinner mx-auto mb-3"></div><div class="text-blue-300">加载中...</div></div>
            </div>
        </div>
    </div>

    <script>
    var currentPilotId = null;
    var currentPilotName = '';

    function escapeHtml(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function formatNumber(n) {
        if (!n) return '0';
        return new Intl.NumberFormat().format(n);
    }

    function formatIsk(n) {
        if (!n || n === 0) return '0 ISK';
        if (n >= 1e9) return (n / 1e9).toFixed(2) + 'B ISK';
        if (n >= 1e6) return (n / 1e6).toFixed(2) + 'M ISK';
        if (n >= 1e3) return (n / 1e3).toFixed(1) + 'K ISK';
        return formatNumber(n) + ' ISK';
    }

    function setLoading(elementId, text) {
        document.getElementById(elementId).innerHTML =
            '<div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 text-center">' +
            '<div class="spinner mx-auto mb-3"></div>' +
            '<div class="text-blue-300">' + escapeHtml(text) + '</div></div>';
    }

    function setError(elementId, text) {
        document.getElementById(elementId).innerHTML =
            '<div class="bg-red-900/20 border border-red-500/30 rounded-xl p-6 text-center">' +
            '<div class="text-red-400">' + escapeHtml(text) + '</div></div>';
    }

    // 搜索角色
    function searchCharacter() {
        var query = document.getElementById('searchInput').value.trim();
        if (!query || query.length < 2) {
            alert('请输入至少 2 个字符');
            return;
        }

        setLoading('searchResults', '搜索 "' + query + '" 中...');
        document.getElementById('killList').innerHTML = '';
        document.getElementById('killDetail').innerHTML = '';

        fetch('/api/killmails/search?q=' + encodeURIComponent(query))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var div = document.getElementById('searchResults');
                if (data.success && data.data && data.data.length > 0) {
                    var html = '<div class="bg-white/10 backdrop-blur-lg rounded-xl p-6">';
                    html += '<h3 class="text-lg font-semibold mb-4">搜索结果 (' + data.count + ' 个角色)</h3>';
                    html += '<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">';
                    data.data.forEach(function(c) {
                        html += '<div class="bg-black/30 rounded-lg p-3 cursor-pointer hover:bg-white/10 transition border border-white/10" ' +
                                'onclick="loadPilotKills(' + c.pilot_id + ',\'' + escapeHtml(c.name).replace(/'/g, "\\'") + '\')">';
                        html += '<div class="font-semibold text-blue-400">' + escapeHtml(c.name) + '</div>';
                        html += '<div class="text-xs text-white/50 mt-1">ID: ' + c.pilot_id + '</div>';
                        html += '</div>';
                    });
                    html += '</div></div>';
                    div.innerHTML = html;
                } else {
                    div.innerHTML = '<div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 text-center">' +
                        '<div class="text-white/50">未找到匹配的角色</div>' +
                        (data.message ? '<div class="text-xs text-white/30 mt-2">' + escapeHtml(data.message) + '</div>' : '') +
                        '</div>';
                }
            })
            .catch(function(e) {
                console.error(e);
                setError('searchResults', '搜索失败: ' + e.message);
            });
    }

    // 加载角色 KM 列表
    function loadPilotKills(pilotId, pilotName) {
        currentPilotId = pilotId;
        currentPilotName = pilotName;

        setLoading('killList', '加载 ' + pilotName + ' 的 KM 记录...');
        document.getElementById('killDetail').innerHTML = '';

        var div = document.getElementById('killList');
        div.scrollIntoView({ behavior: 'smooth', block: 'start' });

        fetch('/api/killmails/pilot/' + pilotId + '/kills?mode=all')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.data && data.data.length > 0) {
                    var html = '<div class="bg-white/10 backdrop-blur-lg rounded-xl p-6">';
                    html += '<div class="flex justify-between items-center mb-4">';
                    html += '<h3 class="text-lg font-semibold">' + escapeHtml(pilotName) + ' 的 KM 记录</h3>';
                    html += '<span class="text-sm text-white/50">共 ' + data.count + ' 条</span>';
                    html += '</div>';
                    html += '<div class="space-y-2 max-h-[500px] overflow-y-auto pr-2">';
                    data.data.forEach(function(kill) {
                        var victimHtml = kill.victim_name ? '<span class="text-red-400">' + escapeHtml(kill.victim_name) + '</span>' : '';
                        var shipHtml = kill.ship_name ? '<span class="text-purple-400">' + escapeHtml(kill.ship_name) + '</span>' : '';
                        var timeHtml = kill.kill_time ? '<span class="text-white/40">' + escapeHtml(kill.kill_time) + '</span>' : '';
                        var systemHtml = kill.system_name ? '<span class="text-green-400">' + escapeHtml(kill.system_name) + '</span>' : '';

                        html += '<div class="bg-black/30 rounded-lg p-3 cursor-pointer hover:bg-white/10 transition border border-white/10" ' +
                                'onclick="loadKillDetail(' + kill.kill_id + ')">';
                        html += '<div class="flex justify-between items-center">';
                        html += '<div class="flex items-center gap-3 min-w-0">';
                        html += '<span class="text-white font-mono text-sm shrink-0">#' + kill.kill_id + '</span>';
                        if (victimHtml) html += victimHtml;
                        if (shipHtml) html += shipHtml;
                        html += '</div>';
                        html += '<div class="flex items-center gap-3 shrink-0 ml-3">';
                        if (systemHtml) html += systemHtml;
                        if (timeHtml) html += timeHtml;
                        html += '</div>';
                        html += '</div></div>';
                    });
                    html += '</div></div>';
                    div.innerHTML = html;
                } else {
                    div.innerHTML = '<div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 text-center">' +
                        '<div class="text-white/50">暂无 KM 记录</div>' +
                        (data.message ? '<div class="text-xs text-white/30 mt-2">' + escapeHtml(data.message) + '</div>' : '') +
                        '</div>';
                }
            })
            .catch(function(e) {
                console.error(e);
                setError('killList', '加载 KM 列表失败: ' + e.message);
            });
    }

    // 直接 KM ID 查询
    function directKmLookup() {
        var kmId = document.getElementById('kmIdInput').value.trim();
        if (!kmId || !/^\d+$/.test(kmId)) {
            alert('请输入有效的 KM ID (纯数字)');
            return;
        }
        loadKillDetail(parseInt(kmId));
    }

    // 加载 KM 详情
    function loadKillDetail(killId) {
        var modal = document.getElementById('killModal');
        var content = document.getElementById('modalContent');
        var title = document.getElementById('modalTitle');

        title.textContent = 'KM #' + killId + ' 详情';
        content.innerHTML = '<div class="text-center py-12"><div class="spinner mx-auto mb-3"></div><div class="text-blue-300">加载详情...</div></div>';
        modal.classList.remove('hidden');

        fetch('/api/killmails/kill/' + killId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.data) {
                    renderKillDetail(data.data, killId);
                } else {
                    content.innerHTML = '<div class="text-center py-8 text-red-400">' +
                        escapeHtml(data.message || '加载失败') + '</div>';
                }
            })
            .catch(function(e) {
                console.error(e);
                content.innerHTML = '<div class="text-center py-8 text-red-400">加载失败: ' + escapeHtml(e.message) + '</div>';
            });
    }

    function renderKillDetail(km, killId) {
        var content = document.getElementById('modalContent');
        var html = '<div class="space-y-4">';

        // 基本信息
        html += '<div class="grid md:grid-cols-2 gap-4">';

        // 受害者
        html += '<div class="bg-red-900/20 rounded-lg p-4 border border-red-500/20">';
        html += '<div class="text-sm text-red-300 mb-2 font-semibold">受害者</div>';
        if (km.victim_name) html += '<div class="text-lg text-white font-bold">' + escapeHtml(km.victim_name) + '</div>';
        if (km.victim_corp) html += '<div class="text-sm text-white/60">' + escapeHtml(km.victim_corp) + '</div>';
        if (km.victim_alliance) html += '<div class="text-sm text-white/40">' + escapeHtml(km.victim_alliance) + '</div>';
        if (km.ship_name) html += '<div class="text-sm text-purple-400 mt-2">' + escapeHtml(km.ship_name) + '</div>';
        if (km.damage_taken) html += '<div class="text-sm text-red-400 mt-1">承受伤害: ' + formatNumber(km.damage_taken) + '</div>';
        html += '</div>';

        // 信息
        html += '<div class="bg-blue-900/20 rounded-lg p-4 border border-blue-500/20">';
        html += '<div class="text-sm text-blue-300 mb-2 font-semibold">击坠信息</div>';
        if (km.system_name) html += '<div class="text-sm"><span class="text-white/50">星系: </span><span class="text-green-400">' + escapeHtml(km.system_name) + '</span></div>';
        if (km.region_name) html += '<div class="text-sm"><span class="text-white/50">星域: </span><span class="text-white/80">' + escapeHtml(km.region_name) + '</span></div>';
        if (km.kill_time) html += '<div class="text-sm mt-2"><span class="text-white/50">时间: </span><span class="text-white/80">' + escapeHtml(km.kill_time) + '</span></div>';
        if (km.total_value) html += '<div class="text-sm mt-2"><span class="text-white/50">总价值: </span><span class="text-yellow-400 font-bold">' + formatIsk(km.total_value) + '</span></div>';
        if (km.attacker_count) html += '<div class="text-sm"><span class="text-white/50">参与人数: </span><span class="text-white/80">' + km.attacker_count + '</span></div>';
        html += '<div class="mt-3"><a href="https://kb.ceve-market.org/kill/' + killId + '/" target="_blank" class="text-xs text-blue-400 hover:text-blue-300 underline">在 KB 网查看 &rarr;</a></div>';
        html += '</div>';

        html += '</div>';

        // 攻击者列表
        if (km.attackers && km.attackers.length > 0) {
            html += '<div class="bg-white/5 rounded-lg p-4 border border-white/10">';
            html += '<div class="text-sm text-white/60 mb-3 font-semibold">攻击者 (' + km.attackers.length + ')</div>';
            html += '<div class="max-h-64 overflow-y-auto space-y-1">';
            km.attackers.forEach(function(atk, idx) {
                var isFinal = atk.final_blow ? ' <span class="text-yellow-400 text-xs">[最后一击]</span>' : '';
                html += '<div class="flex justify-between items-center text-sm py-1 border-b border-white/5">';
                html += '<div class="flex items-center gap-2 min-w-0">';
                html += '<span class="text-white/30 w-6 text-right shrink-0">' + (idx + 1) + '.</span>';
                html += '<span class="text-white truncate">' + escapeHtml(atk.name || '未知') + isFinal + '</span>';
                if (atk.ship) html += '<span class="text-purple-400/60 text-xs shrink-0">' + escapeHtml(atk.ship) + '</span>';
                html += '</div>';
                if (atk.damage > 0) html += '<span class="text-red-400 text-xs shrink-0 ml-2">' + formatNumber(atk.damage) + '</span>';
                html += '</div>';
            });
            html += '</div></div>';
        }

        // 物品
        if (km.items_dropped && km.items_dropped.length > 0) {
            html += '<div class="bg-green-900/10 rounded-lg p-4 border border-green-500/10">';
            html += '<div class="text-sm text-green-300 mb-3 font-semibold">掉落物品 (' + km.items_dropped.length + ')</div>';
            html += '<div class="max-h-48 overflow-y-auto space-y-1">';
            km.items_dropped.forEach(function(item) {
                html += '<div class="flex justify-between text-sm py-1 border-b border-white/5">';
                html += '<span class="text-white/80">' + escapeHtml(item.name) + (item.qty > 1 ? ' x' + item.qty : '') + '</span>';
                html += '</div>';
            });
            html += '</div></div>';
        }

        if (km.items_destroyed && km.items_destroyed.length > 0) {
            html += '<div class="bg-red-900/10 rounded-lg p-4 border border-red-500/10">';
            html += '<div class="text-sm text-red-300 mb-3 font-semibold">损毁物品 (' + km.items_destroyed.length + ')</div>';
            html += '<div class="max-h-48 overflow-y-auto space-y-1">';
            km.items_destroyed.forEach(function(item) {
                html += '<div class="flex justify-between text-sm py-1 border-b border-white/5">';
                html += '<span class="text-white/80">' + escapeHtml(item.name) + (item.qty > 1 ? ' x' + item.qty : '') + '</span>';
                html += '</div>';
            });
            html += '</div></div>';
        }

        html += '</div>';
        content.innerHTML = html;
    }

    function closeModal() {
        document.getElementById('killModal').classList.add('hidden');
    }

    // ESC 关闭模态框
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });
    </script>
</body>
</html>
