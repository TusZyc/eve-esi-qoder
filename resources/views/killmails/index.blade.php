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
                <!-- KM ID / ESI链接 查询 -->
                <div>
                    <h3 class="text-lg font-semibold mb-3 text-blue-200">直接查询 KM</h3>
                    <div class="flex gap-3">
                        <input type="text" id="kmIdInput" placeholder="KM ID 或 ESI 链接..."
                            class="flex-1 px-4 py-2 bg-black/40 border border-white/20 rounded-lg text-white placeholder-white/40 focus:outline-none focus:border-blue-500 transition"
                            onkeydown="if(event.key==='Enter')directKmLookup()">
                        <button onclick="directKmLookup()" id="kmLookupBtn"
                            class="px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition font-semibold">
                            查询
                        </button>
                    </div>
                    <div class="text-xs text-white/30 mt-2">支持输入 KM ID、KB链接 或 ESI完整链接</div>
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
    var KB_URL = 'https://kb.ceve-market.org';
    var BETA_KB_URL = 'https://beta.ceve-market.org';
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

    // ================================================
    // 前端代理: 从 Beta KB 提取 ESI hash
    // ================================================

    /**
     * 从 KB 提取 ESI hash（优先 Beta KB API，降级旧 KB）
     */
    function fetchEsiHashFromKb(killId) {
        // 优先: Beta KB API (protobuf 响应中包含 40 位 hex hash)
        return fetch(BETA_KB_URL + '/app/kill/' + killId + '/info', {
            mode: 'cors',
            credentials: 'omit'
        })
        .then(function(r) {
            if (!r.ok) throw new Error('Beta KB HTTP ' + r.status);
            return r.text();
        })
        .then(function(text) {
            var match = text.match(/[a-f0-9]{40}/);
            if (match) return match[0];
            throw new Error('Beta KB 响应中未找到 hash');
        })
        .catch(function(e) {
            console.log('Beta KB hash 提取失败: ' + e.message + ', 尝试旧 KB...');
            // 降级: 旧 KB HTML 页面
            return fetch(KB_URL + '/kill/' + killId + '/', {
                mode: 'cors',
                credentials: 'omit',
                headers: { 'Accept': 'text/html' }
            })
            .then(function(r) {
                if (!r.ok) throw new Error('旧 KB HTTP ' + r.status);
                return r.text();
            })
            .then(function(html) {
                var match = html.match(/ali-esi\.evepc\.163\.com\/latest\/killmails\/\d+\/([a-f0-9]+)/i);
                if (match) return match[1];
                return null;
            });
        });
    }

    // ================================================
    // 搜索角色（后端处理：KB自动补全 + ESI universe/ids）
    // ================================================
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
                        '<div class="text-white/50">未找到匹配的角色</div></div>';
                }
            })
            .catch(function(e) {
                console.error(e);
                setError('searchResults', '搜索失败: ' + e.message);
            });
    }

    // ================================================
    // 加载角色 KM 列表
    // 后端通过 Beta KB API 直接获取（含 ESI hash + ISK 价值）
    // ================================================
    function loadPilotKills(pilotId, pilotName) {
        currentPilotId = pilotId;
        currentPilotName = pilotName;

        setLoading('killList', '加载 ' + pilotName + ' 的 KM 记录...');
        document.getElementById('killDetail').innerHTML = '';

        var div = document.getElementById('killList');
        div.scrollIntoView({ behavior: 'smooth', block: 'start' });

        // 后端调用 Beta KB API，数据已包含 hash 和 ISK
        fetch('/api/killmails/pilot/' + pilotId + '/kills?mode=all')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.data && data.data.length > 0) {
                    renderKillList(data.data, pilotName);
                } else {
                    showKbFallbackLink(pilotId, pilotName);
                }
            })
            .catch(function(e) {
                console.error('后端加载失败:', e);
                showKbFallbackLink(pilotId, pilotName);
            });
    }

    function showKbFallbackLink(pilotId, pilotName) {
        var div = document.getElementById('killList');
        var kbUrl = BETA_KB_URL + '/pilot/' + pilotId + '/';
        var html = '<div class="bg-white/10 backdrop-blur-lg rounded-xl p-6">';
        html += '<div class="text-center">';
        html += '<div class="text-white/60 mb-4">无法自动获取 KM 列表，请在 KB 网查看后复制 KM ID 回来查询</div>';
        html += '<a href="' + kbUrl + '" target="_blank" class="inline-block px-6 py-3 bg-blue-600 hover:bg-blue-700 rounded-lg text-white font-semibold transition">';
        html += '打开 ' + escapeHtml(pilotName) + ' 的 KB 页面 &rarr;</a>';
        html += '<div class="text-xs text-white/30 mt-3">在 KB 页面找到 KM，复制 KM ID 到上方「直接查询 KM」输入框即可</div>';
        html += '</div></div>';
        div.innerHTML = html;
    }

    function renderKillList(kills, pilotName) {
        var div = document.getElementById('killList');
        var html = '<div class="bg-white/10 backdrop-blur-lg rounded-xl p-6">';
        html += '<div class="flex justify-between items-center mb-4">';
        html += '<h3 class="text-lg font-semibold">' + escapeHtml(pilotName) + ' 的 KM 记录</h3>';
        html += '<span class="text-sm text-white/50">共 ' + kills.length + ' 条</span>';
        html += '</div>';
        html += '<div class="space-y-2 max-h-[500px] overflow-y-auto pr-2">';
        kills.forEach(function(kill) {
            var hashAttr = kill.esi_hash ? 'data-hash="' + kill.esi_hash + '"' : '';
            html += '<div class="bg-black/30 rounded-lg p-3 cursor-pointer hover:bg-white/10 transition border border-white/10" ' +
                    hashAttr + ' onclick="onKillClick(this,' + kill.kill_id + ')">';
            html += '<div class="flex justify-between items-center flex-wrap gap-2">';
            html += '<div class="flex items-center gap-3 min-w-0">';
            html += '<span class="text-white font-mono text-sm shrink-0">#' + kill.kill_id + '</span>';
            if (kill.victim_name) html += '<span class="text-red-400 truncate">' + escapeHtml(kill.victim_name) + '</span>';
            if (kill.ship_name) html += '<span class="text-purple-400 text-sm truncate">' + escapeHtml(kill.ship_name) + '</span>';
            html += '</div>';
            html += '<div class="flex items-center gap-3 shrink-0">';
            if (kill.total_value) html += '<span class="text-yellow-400 text-sm font-semibold">' + formatIsk(kill.total_value) + '</span>';
            if (kill.system_name) html += '<span class="text-green-400 text-sm">' + escapeHtml(kill.system_name) + '</span>';
            if (kill.kill_time) html += '<span class="text-white/40 text-sm">' + escapeHtml(kill.kill_time) + '</span>';
            html += '</div>';
            html += '</div></div>';
        });
        html += '</div></div>';
        div.innerHTML = html;
    }

    /**
     * KM 列表点击：如果已有 ESI hash 则直接加载，否则走常规流程
     */
    function onKillClick(el, killId) {
        var hash = el.getAttribute('data-hash');
        if (hash) {
            loadKillDetailWithHash(killId, hash);
        } else {
            loadKillDetail(killId);
        }
    }

    // ================================================
    // KM ID / ESI链接 解析
    // ================================================
    function directKmLookup() {
        var input = document.getElementById('kmIdInput').value.trim();
        if (!input) {
            alert('请输入 KM ID 或 ESI 链接');
            return;
        }

        // 解析 ESI 完整链接: https://ali-esi.evepc.163.com/latest/killmails/22395435/e4b9f27.../ 
        var esiMatch = input.match(/killmails\/(\d+)\/([a-f0-9]+)/i);
        if (esiMatch) {
            loadKillDetailWithHash(parseInt(esiMatch[1]), esiMatch[2]);
            return;
        }

        // 解析 KB 链接: https://kb.ceve-market.org/kill/22395435/
        var kbMatch = input.match(/kill\/(\d+)/);
        if (kbMatch) {
            loadKillDetail(parseInt(kbMatch[1]));
            return;
        }

        // 纯数字
        if (/^\d+$/.test(input)) {
            loadKillDetail(parseInt(input));
            return;
        }

        alert('无法识别的输入格式。请输入 KM ID、KB链接 或 ESI链接');
    }

    // ================================================
    // 加载 KM 详情
    // 策略: 前端尝试从 KB 提取 hash → 后端 ESI 处理
    // ================================================
    function loadKillDetail(killId) {
        var modal = document.getElementById('killModal');
        var content = document.getElementById('modalContent');
        var title = document.getElementById('modalTitle');

        title.textContent = 'KM #' + killId + ' 详情';
        content.innerHTML = '<div class="text-center py-12"><div class="spinner mx-auto mb-3"></div><div class="text-blue-300">正在从 KB 获取数据...</div></div>';
        modal.classList.remove('hidden');

        // 策略 1: 前端直接从 KB 提取 ESI hash
        fetchEsiHashFromKb(killId)
            .then(function(hash) {
                if (hash) {
                    content.innerHTML = '<div class="text-center py-12"><div class="spinner mx-auto mb-3"></div><div class="text-blue-300">已获取 ESI 认证，正在加载详情...</div></div>';
                    return fetchKillDetailFromBackend(killId, hash);
                }
                // 没找到 hash，直接走后端（后端也会尝试）
                console.log('前端未找到 hash，走后端');
                return fetchKillDetailFromBackend(killId, null);
            })
            .catch(function(e) {
                console.log('前端 KB 请求失败 (CORS?): ', e.message);
                // CORS 失败，走后端
                content.innerHTML = '<div class="text-center py-12"><div class="spinner mx-auto mb-3"></div><div class="text-blue-300">加载中...</div></div>';
                fetchKillDetailFromBackend(killId, null);
            });
    }

    function loadKillDetailWithHash(killId, hash) {
        var modal = document.getElementById('killModal');
        var content = document.getElementById('modalContent');
        var title = document.getElementById('modalTitle');

        title.textContent = 'KM #' + killId + ' 详情';
        content.innerHTML = '<div class="text-center py-12"><div class="spinner mx-auto mb-3"></div><div class="text-blue-300">加载 ESI 数据...</div></div>';
        modal.classList.remove('hidden');

        fetchKillDetailFromBackend(killId, hash);
    }

    function fetchKillDetailFromBackend(killId, hash) {
        var url = '/api/killmails/kill/' + killId;
        if (hash) url += '?hash=' + encodeURIComponent(hash);

        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var content = document.getElementById('modalContent');
                if (data.success && data.data) {
                    renderKillDetail(data.data, killId);
                } else {
                    // 后端也失败，显示手动输入选项
                    showManualHashInput(killId, data.message || '加载失败');
                }
            })
            .catch(function(e) {
                console.error(e);
                showManualHashInput(killId, '请求失败: ' + e.message);
            });
    }

    /**
     * 显示手动输入 ESI 链接的界面
     */
    function showManualHashInput(killId, errorMsg) {
        var content = document.getElementById('modalContent');
        var kbUrl = BETA_KB_URL + '/kill/' + killId + '/';
        var html = '<div class="space-y-4">';
        html += '<div class="text-center text-yellow-400 text-sm">' + escapeHtml(errorMsg) + '</div>';
        html += '<div class="bg-yellow-900/20 border border-yellow-500/20 rounded-lg p-4">';
        html += '<div class="text-sm text-yellow-300 mb-3 font-semibold">手动获取 ESI 数据</div>';
        html += '<ol class="text-sm text-white/70 space-y-2 list-decimal list-inside mb-4">';
        html += '<li>点击下方按钮在 KB 网打开此 KM</li>';
        html += '<li>在 KB 页面找到「官方API已认证」链接</li>';
        html += '<li>右键复制该链接地址，粘贴到下方输入框</li>';
        html += '</ol>';
        html += '<a href="' + kbUrl + '" target="_blank" class="inline-block px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg text-white text-sm font-semibold transition mb-3">打开 KB 页面 &rarr;</a>';
        html += '<div class="flex gap-2 mt-2">';
        html += '<input type="text" id="manualEsiInput" placeholder="粘贴 ESI 链接..." class="flex-1 px-3 py-2 bg-black/40 border border-white/20 rounded-lg text-white text-sm placeholder-white/40 focus:outline-none focus:border-blue-500">';
        html += '<button onclick="submitManualHash(' + killId + ')" class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded-lg text-white text-sm font-semibold">提交</button>';
        html += '</div>';
        html += '</div></div>';
        content.innerHTML = html;
    }

    function submitManualHash(killId) {
        var input = document.getElementById('manualEsiInput').value.trim();
        var match = input.match(/killmails\/(\d+)\/([a-f0-9]+)/i);
        if (match) {
            var content = document.getElementById('modalContent');
            content.innerHTML = '<div class="text-center py-12"><div class="spinner mx-auto mb-3"></div><div class="text-blue-300">加载 ESI 数据...</div></div>';
            fetchKillDetailFromBackend(parseInt(match[1]), match[2]);
        } else {
            alert('无法识别 ESI 链接格式。请复制完整的「官方API已认证」链接');
        }
    }

    // ================================================
    // 渲染 KM 详情
    // ================================================
    function renderKillDetail(km, killId) {
        var content = document.getElementById('modalContent');
        var html = '<div class="space-y-4">';

        var victim = km.victim || {};
        var isEsi = km.esi_verified === true;

        // ESI 验证标记
        if (isEsi) {
            html += '<div class="flex items-center gap-2 text-xs text-green-400 bg-green-900/20 rounded-lg px-3 py-1.5 border border-green-500/20 w-fit">';
            html += '<span class="w-2 h-2 bg-green-400 rounded-full"></span> ESI 官方数据';
            html += '</div>';
        } else {
            html += '<div class="flex items-center gap-2 text-xs text-yellow-400 bg-yellow-900/20 rounded-lg px-3 py-1.5 border border-yellow-500/20 w-fit">';
            html += '<span class="w-2 h-2 bg-yellow-400 rounded-full"></span> KB 网页解析 (降级模式)';
            html += '</div>';
        }

        // 基本信息 - 两列
        html += '<div class="grid md:grid-cols-2 gap-4">';

        // 受害者信息
        html += '<div class="bg-red-900/20 rounded-lg p-4 border border-red-500/20">';
        html += '<div class="text-sm text-red-300 mb-2 font-semibold">受害者</div>';
        if (victim.character_name) html += '<div class="text-lg text-white font-bold">' + escapeHtml(victim.character_name) + '</div>';
        if (victim.corporation_name) html += '<div class="text-sm text-white/60">' + escapeHtml(victim.corporation_name) + '</div>';
        if (victim.alliance_name) html += '<div class="text-sm text-white/40">' + escapeHtml(victim.alliance_name) + '</div>';
        if (victim.ship_name) html += '<div class="text-sm text-purple-400 mt-2">舰船: ' + escapeHtml(victim.ship_name) + '</div>';
        if (victim.damage_taken) html += '<div class="text-sm text-red-400 mt-1">承受伤害: ' + formatNumber(victim.damage_taken) + '</div>';
        html += '</div>';

        // 击坠信息
        html += '<div class="bg-blue-900/20 rounded-lg p-4 border border-blue-500/20">';
        html += '<div class="text-sm text-blue-300 mb-2 font-semibold">击坠信息</div>';
        if (km.solar_system_name) html += '<div class="text-sm"><span class="text-white/50">星系: </span><span class="text-green-400">' + escapeHtml(km.solar_system_name) + '</span></div>';
        if (km.kill_time) {
            var timeStr = km.kill_time;
            if (timeStr.indexOf('T') > -1) timeStr = timeStr.replace('T', ' ').replace('Z', '') + ' UTC';
            html += '<div class="text-sm mt-1"><span class="text-white/50">时间: </span><span class="text-white/80">' + escapeHtml(timeStr) + '</span></div>';
        }
        if (km.attacker_count) html += '<div class="text-sm mt-1"><span class="text-white/50">参与人数: </span><span class="text-white/80">' + km.attacker_count + '</span></div>';
        html += '<div class="mt-3"><a href="' + BETA_KB_URL + '/kill/' + killId + '/" target="_blank" class="text-xs text-blue-400 hover:text-blue-300 underline">在 KB 网查看 &rarr;</a></div>';
        html += '</div>';

        html += '</div>'; // end grid

        // 攻击者列表
        var attackers = km.attackers || [];
        if (attackers.length > 0) {
            html += '<div class="bg-white/5 rounded-lg p-4 border border-white/10">';
            html += '<div class="text-sm text-white/60 mb-3 font-semibold">攻击者 (' + attackers.length + ')</div>';
            html += '<div class="max-h-72 overflow-y-auto">';
            html += '<table class="w-full text-sm">';
            html += '<thead class="text-white/40 text-xs border-b border-white/10"><tr>';
            html += '<th class="text-left py-1 pr-2">#</th>';
            html += '<th class="text-left py-1 pr-2">角色</th>';
            html += '<th class="text-left py-1 pr-2">军团</th>';
            html += '<th class="text-left py-1 pr-2">舰船</th>';
            html += '<th class="text-left py-1 pr-2">武器</th>';
            html += '<th class="text-right py-1">伤害</th>';
            html += '</tr></thead><tbody>';

            attackers.forEach(function(atk, idx) {
                var rowClass = atk.final_blow ? 'bg-yellow-900/20' : '';
                html += '<tr class="border-b border-white/5 ' + rowClass + '">';
                html += '<td class="text-white/30 py-1.5 pr-2">' + (idx + 1) + '</td>';
                html += '<td class="py-1.5 pr-2">';
                html += '<span class="text-white">' + escapeHtml(atk.character_name || '未知') + '</span>';
                if (atk.final_blow) html += ' <span class="text-yellow-400 text-xs">[最后一击]</span>';
                html += '</td>';
                html += '<td class="text-white/50 py-1.5 pr-2 text-xs">' + escapeHtml(atk.corporation_name || '') + '</td>';
                html += '<td class="text-purple-400/80 py-1.5 pr-2 text-xs">' + escapeHtml(atk.ship_name || '') + '</td>';
                html += '<td class="text-cyan-400/60 py-1.5 pr-2 text-xs">' + escapeHtml(atk.weapon_name || '') + '</td>';
                html += '<td class="text-red-400 py-1.5 text-right text-xs">' + formatNumber(atk.damage_done) + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            html += '</div></div>';
        }

        // 物品 - 分为掉落和损毁
        var victimItems = victim.items || [];
        if (victimItems.length > 0) {
            var dropped = victimItems.filter(function(i) { return i.status === 'dropped'; });
            var destroyed = victimItems.filter(function(i) { return i.status === 'destroyed'; });

            if (dropped.length > 0) {
                html += '<div class="bg-green-900/10 rounded-lg p-4 border border-green-500/10">';
                html += '<div class="text-sm text-green-300 mb-3 font-semibold">掉落物品 (' + dropped.length + ')</div>';
                html += '<div class="max-h-48 overflow-y-auto">';
                html += '<table class="w-full text-sm">';
                html += '<thead class="text-white/40 text-xs border-b border-white/10"><tr>';
                html += '<th class="text-left py-1">物品</th>';
                html += '<th class="text-right py-1 pr-3">数量</th>';
                html += '<th class="text-left py-1">槽位</th>';
                html += '</tr></thead><tbody>';
                dropped.forEach(function(item) {
                    html += '<tr class="border-b border-white/5">';
                    html += '<td class="text-green-300/80 py-1">' + escapeHtml(item.item_name) + '</td>';
                    html += '<td class="text-white/60 py-1 text-right pr-3">' + (item.quantity > 1 ? 'x' + item.quantity : '1') + '</td>';
                    html += '<td class="text-white/30 py-1 text-xs">' + escapeHtml(item.flag_name || '') + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
                html += '</div></div>';
            }

            if (destroyed.length > 0) {
                html += '<div class="bg-red-900/10 rounded-lg p-4 border border-red-500/10">';
                html += '<div class="text-sm text-red-300 mb-3 font-semibold">损毁物品 (' + destroyed.length + ')</div>';
                html += '<div class="max-h-48 overflow-y-auto">';
                html += '<table class="w-full text-sm">';
                html += '<thead class="text-white/40 text-xs border-b border-white/10"><tr>';
                html += '<th class="text-left py-1">物品</th>';
                html += '<th class="text-right py-1 pr-3">数量</th>';
                html += '<th class="text-left py-1">槽位</th>';
                html += '</tr></thead><tbody>';
                destroyed.forEach(function(item) {
                    html += '<tr class="border-b border-white/5">';
                    html += '<td class="text-red-300/80 py-1">' + escapeHtml(item.item_name) + '</td>';
                    html += '<td class="text-white/60 py-1 text-right pr-3">' + (item.quantity > 1 ? 'x' + item.quantity : '1') + '</td>';
                    html += '<td class="text-white/30 py-1 text-xs">' + escapeHtml(item.flag_name || '') + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
                html += '</div></div>';
            }
        }

        html += '</div>';
        content.innerHTML = html;
    }

    function closeModal() {
        document.getElementById('killModal').classList.add('hidden');
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });
    </script>
</body>
</html>
