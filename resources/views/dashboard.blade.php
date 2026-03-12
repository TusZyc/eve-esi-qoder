<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>仪表盘 - {{ config('app.name') }}</title>
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
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .loading-spinner {
            border: 3px solid rgba(255,255,255,0.1);
            border-radius: 50%;
            border-top: 3px solid #60a5fa;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
        }
        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }
        .status-dot.online {
            background-color: #22c55e;
            box-shadow: 0 0 10px #22c55e;
        }
        .status-dot.maintenance {
            background-color: #eab308;
            box-shadow: 0 0 10px #eab308;
        }
        .status-dot.offline {
            background-color: #ef4444;
            box-shadow: 0 0 10px #ef4444;
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
                        <span class="text-sm text-blue-200 ml-3">欢迎，{{ ->name }}</span>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <a href="{{ route('skills.index') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all" title="技能队列">
                        <div class="text-2xl">📚</div>
                    </a>
                    <a href="{{ route('assets.index') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all" title="我的资产">
                        <div class="text-2xl">📦</div>
                    </a>
                    <a href="{{ route('characters.index') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all" title="角色管理">
                        <div class="text-2xl">👥</div>
                    </a>
                    <form action="{{ route('auth.logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg text-sm ml-2">
                            登出
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <!-- 服务器状态 -->
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 mb-6 eve-glow">
            <h2 class="text-xl font-semibold mb-4">📡 服务器状态</h2>
            <div id="server-status-content">
                <div class="grid grid-cols-5 gap-4 text-sm">
                    <div class="text-center"><div class="skeleton h-6 w-16 mx-auto mb-1"></div><div class="skeleton h-3 w-12 mx-auto"></div></div>
                    <div class="text-center"><div class="skeleton h-6 w-16 mx-auto mb-1"></div><div class="skeleton h-3 w-12 mx-auto"></div></div>
                    <div class="text-center"><div class="skeleton h-6 w-20 mx-auto mb-1"></div><div class="skeleton h-3 w-12 mx-auto"></div></div>
                    <div class="text-center"><div class="skeleton h-6 w-24 mx-auto mb-1"></div><div class="skeleton h-3 w-12 mx-auto"></div></div>
                    <div class="text-center"><div class="skeleton h-6 w-12 mx-auto mb-1"></div><div class="skeleton h-3 w-12 mx-auto"></div></div>
                </div>
            </div>
        </div>

        <!-- 角色信息 -->
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 mb-6 eve-glow">
            <h2 class="text-xl font-semibold mb-4">👤 角色信息</h2>
            <div id="character-info-content">
                <div class="grid md:grid-cols-5 gap-4">
                    <div class="text-center"><div class="skeleton h-3 w-16 mb-2"></div><div class="skeleton h-5 w-28"></div></div>
                    <div class="text-center"><div class="skeleton h-3 w-16 mb-2"></div><div class="skeleton h-5 w-28"></div></div>
                    <div class="text-center"><div class="skeleton h-3 w-16 mb-2"></div><div class="skeleton h-5 w-28"></div></div>
                    <div class="text-center"><div class="skeleton h-3 w-16 mb-2"></div><div class="skeleton h-5 w-28"></div></div>
                    <div class="text-center"><div class="skeleton h-3 w-16 mb-2"></div><div class="skeleton h-5 w-28"></div></div>
                </div>
            </div>
        </div>

        <!-- 技能信息 -->
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 mb-6 eve-glow">
            <h2 class="text-xl font-semibold mb-4">📚 技能信息</h2>
            <div id="skills-content">
                <div class="grid md:grid-cols-3 gap-4">
                    <div class="text-center"><div class="skeleton h-10 w-24 mx-auto mb-2"></div><div class="skeleton h-4 w-20 mx-auto"></div></div>
                    <div class="text-center"><div class="skeleton h-10 w-24 mx-auto mb-2"></div><div class="skeleton h-4 w-20 mx-auto"></div></div>
                    <div class="text-center"><div class="skeleton h-10 w-24 mx-auto mb-2"></div><div class="skeleton h-4 w-20 mx-auto"></div></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const API_ENDPOINTS = {
            serverStatus: '{{ route("api.dashboard.server-status") }}',
            skills: '{{ route("api.dashboard.skills") }}',
            characterInfo: '{{ route("api.dashboard.character-info") }}',
            characterLocation: '{{ route("api.dashboard.character-location") }}',
            characterOnline: '{{ route("api.dashboard.character-online") }}',
        };

        // XSS 防护：转义 HTML 实体
        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            const div = document.createElement('div');
            div.textContent = String(str);
            return div.innerHTML;
        }

        function formatNumber(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        function formatTime(isoString) {
            if (!isoString) return 'N/A';
            const date = new Date(isoString);
            return date.toLocaleString('zh-CN', {
                year: 'numeric', month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit'
            });
        }

        // 安全的错误显示（使用 DOM API）
        function showError(containerId, icon, title, message) {
            const container = document.getElementById(containerId);
            container.textContent = '';
            const wrapper = document.createElement('div');
            wrapper.className = 'text-center py-8';

            const iconDiv = document.createElement('div');
            iconDiv.className = 'text-5xl mb-4';
            iconDiv.textContent = icon;

            const titleP = document.createElement('p');
            titleP.className = 'text-blue-300 text-lg mb-2';
            titleP.textContent = title;

            const msgP = document.createElement('p');
            msgP.className = 'text-blue-400 text-sm';
            msgP.textContent = message;

            wrapper.appendChild(iconDiv);
            wrapper.appendChild(titleP);
            wrapper.appendChild(msgP);
            container.appendChild(wrapper);
        }

        // 安全创建元素的辅助函数
        function createEl(tag, className, textContent) {
            const el = document.createElement(tag);
            if (className) el.className = className;
            if (textContent !== undefined) el.textContent = textContent;
            return el;
        }

        async function loadServerStatus() {
            try {
                const response = await fetch(API_ENDPOINTS.serverStatus, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const result = await response.json();

                if (result.success) {
                    const data = result.data;
                    const container = document.getElementById('server-status-content');

                    let statusText = '在线', statusClass = 'online', statusColor = 'text-green-500';
                    if (data.is_maintenance) {
                        statusText = '调试中'; statusClass = 'maintenance'; statusColor = 'text-yellow-500';
                    } else if (!data.is_online) {
                        statusText = '离线'; statusClass = 'offline'; statusColor = 'text-red-500';
                    }

                    const vipText = data.vip ? '是' : '否';
                    const vipColor = data.vip ? 'text-yellow-500' : 'text-green-500';

                    // 使用安全的 DOM 构建
                    const grid = createEl('div', 'grid grid-cols-5 gap-4');

                    // 状态
                    const col1 = createEl('div', 'text-center');
                    const statusDiv = createEl('div', 'text-lg font-bold ' + statusColor);
                    const dot = createEl('span', 'status-dot ' + statusClass);
                    statusDiv.appendChild(dot);
                    statusDiv.appendChild(document.createTextNode(statusText));
                    col1.appendChild(statusDiv);
                    col1.appendChild(createEl('div', 'text-xs text-blue-300 mt-1', '状态'));
                    grid.appendChild(col1);

                    // 在线玩家
                    const col2 = createEl('div', 'text-center');
                    col2.appendChild(createEl('div', 'text-lg font-bold text-green-500', formatNumber(data.players)));
                    col2.appendChild(createEl('div', 'text-xs text-blue-300 mt-1', '在线玩家'));
                    grid.appendChild(col2);

                    // 服务器版本
                    const col3 = createEl('div', 'text-center');
                    col3.appendChild(createEl('div', 'text-lg font-bold text-blue-500', String(data.server_version)));
                    col3.appendChild(createEl('div', 'text-xs text-blue-300 mt-1', '服务器版本'));
                    grid.appendChild(col3);

                    // 启动时间
                    const col4 = createEl('div', 'text-center');
                    col4.appendChild(createEl('div', 'text-lg font-bold text-purple-500', formatTime(data.start_time)));
                    col4.appendChild(createEl('div', 'text-xs text-blue-300 mt-1', '启动时间'));
                    grid.appendChild(col4);

                    // VIP
                    const col5 = createEl('div', 'text-center');
                    col5.appendChild(createEl('div', 'text-lg font-bold ' + vipColor, vipText));
                    col5.appendChild(createEl('div', 'text-xs text-blue-300 mt-1', 'VIP 模式'));
                    grid.appendChild(col5);

                    container.textContent = '';
                    container.appendChild(grid);
                } else {
                    let icon = '📡', title = '无法获取服务器状态', message = '请稍后再试';
                    if (result.error === 'server_maintenance') {
                        icon = '⚙️'; title = '服务器维护中'; message = '国服每天 11:00 进行例行维护';
                    } else if (result.error === 'server_offline') {
                        icon = '🔄'; title = '服务器不在线'; message = '服务器正在重启中';
                    }
                    showError('server-status-content', icon, title, message);
                }
            } catch (error) {
                console.error('加载服务器状态失败:', error);
                showError('server-status-content', '⚠️', '加载失败', '网络错误，请刷新页面重试');
            }
        }

        async function loadCharacterInfo() {
            try {
                const response = await fetch(API_ENDPOINTS.characterInfo, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });

                if (response.status === 401) {
                    showError('character-info-content', '🔐', '未授权', '会话已过期，请刷新页面重新登录');
                    return;
                }

                const result = await response.json();

                if (result.success) {
                    const data = result.data;
                    const container = document.getElementById('character-info-content');

                    const grid = createEl('div', 'grid md:grid-cols-5 gap-4');

                    // 角色
                    const col1 = createEl('div', 'text-center');
                    col1.appendChild(createEl('div', 'text-xs text-blue-300 mb-1', '角色'));
                    const charName = createEl('div', 'text-lg font-bold text-blue-400');
                    charName.textContent = data.character_name;
                    const charIdSpan = createEl('span', 'text-xs text-blue-400 ml-1', '(ID: ' + escapeHtml(data.character_id) + ')');
                    charName.appendChild(charIdSpan);
                    col1.appendChild(charName);
                    grid.appendChild(col1);

                    // 军团
                    const col2 = createEl('div', 'text-center');
                    col2.appendChild(createEl('div', 'text-xs text-blue-300 mb-1', '军团'));
                    const corpName = createEl('div', 'text-lg font-bold text-purple-400');
                    corpName.textContent = data.corporation_name;
                    const corpIdSpan = createEl('span', 'text-xs text-purple-400 ml-1', '(ID: ' + escapeHtml(data.corporation_id) + ')');
                    corpName.appendChild(corpIdSpan);
                    col2.appendChild(corpName);
                    grid.appendChild(col2);

                    // 联盟
                    const col3 = createEl('div', 'text-center');
                    col3.appendChild(createEl('div', 'text-xs text-blue-300 mb-1', '联盟'));
                    const allianceDiv = createEl('div', 'text-lg font-bold text-green-400');
                    if (data.has_alliance) {
                        allianceDiv.textContent = data.alliance_name;
                        const allyIdSpan = createEl('span', 'text-xs text-blue-400 ml-1', '(ID: ' + escapeHtml(data.alliance_id) + ')');
                        allianceDiv.appendChild(allyIdSpan);
                    } else {
                        allianceDiv.textContent = '无联盟';
                        allianceDiv.classList.add('text-blue-400');
                    }
                    col3.appendChild(allianceDiv);
                    grid.appendChild(col3);

                    // 位置（占位）
                    const col4 = createEl('div', 'text-center');
                    col4.id = 'location-content';
                    col4.appendChild(createEl('div', 'text-xs text-blue-300 mb-1', '当前位置'));
                    const locSpinner = createEl('div', 'loading-spinner inline-block');
                    col4.appendChild(locSpinner);
                    grid.appendChild(col4);

                    // 在线状态（占位）
                    const col5 = createEl('div', 'text-center');
                    col5.id = 'online-content';
                    col5.appendChild(createEl('div', 'text-xs text-blue-300 mb-1', '在线状态'));
                    const onlineSpinner = createEl('div', 'loading-spinner inline-block');
                    col5.appendChild(onlineSpinner);
                    grid.appendChild(col5);

                    container.textContent = '';
                    container.appendChild(grid);

                    loadCharacterLocation();
                    loadCharacterOnline();
                } else {
                    showError('character-info-content', '⚠️', '加载失败', result.message || '无法获取角色信息');
                }
            } catch (error) {
                console.error('加载角色信息失败:', error);
                showError('character-info-content', '⚠️', '加载失败', '网络错误，请刷新页面重试');
            }
        }

        async function loadCharacterLocation() {
            try {
                const response = await fetch(API_ENDPOINTS.characterLocation, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const result = await response.json();
                const container = document.getElementById('location-content');
                if (!container) return;
                container.textContent = '';

                container.appendChild(createEl('div', 'text-xs text-blue-300 mb-1', '当前位置'));
                const locationText = (result.success && result.data) ? (result.data.location_display || '未停靠') : '未停靠';
                container.appendChild(createEl('div', 'text-lg font-bold text-yellow-400', locationText));
            } catch (error) {
                console.error('加载位置信息失败:', error);
                const container = document.getElementById('location-content');
                if (!container) return;
                container.textContent = '';
                container.appendChild(createEl('div', 'text-xs text-blue-300 mb-1', '当前位置'));
                container.appendChild(createEl('div', 'text-lg font-bold text-blue-400', '未停靠'));
            }
        }

        async function loadCharacterOnline() {
            try {
                const response = await fetch(API_ENDPOINTS.characterOnline, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const result = await response.json();
                const container = document.getElementById('online-content');
                if (!container) return;
                container.textContent = '';

                container.appendChild(createEl('div', 'text-xs text-blue-300 mb-1', '在线状态'));

                if (result.success && result.data) {
                    const isOnline = result.data.is_online;
                    const displayText = result.data.display_text || (isOnline ? '在线' : '离线');
                    const colorClass = isOnline ? 'text-green-400' : 'text-red-400';
                    const indicatorClass = isOnline ? 'online' : 'offline';

                    const statusDiv = createEl('div', 'text-lg font-bold ' + colorClass);
                    const dot = createEl('span', 'status-dot ' + indicatorClass);
                    statusDiv.appendChild(dot);
                    statusDiv.appendChild(document.createTextNode(displayText));
                    container.appendChild(statusDiv);
                } else {
                    container.appendChild(createEl('div', 'text-lg font-bold text-blue-400', '未知'));
                }
            } catch (error) {
                console.error('加载在线状态失败:', error);
                const container = document.getElementById('online-content');
                if (!container) return;
                container.textContent = '';
                container.appendChild(createEl('div', 'text-xs text-blue-300 mb-1', '在线状态'));
                container.appendChild(createEl('div', 'text-lg font-bold text-blue-400', '未知'));
            }
        }

        async function loadSkills() {
            try {
                const response = await fetch(API_ENDPOINTS.skills, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });

                if (response.status === 401) {
                    showError('skills-content', '🔐', '未授权', '会话已过期，请刷新页面重新登录');
                    return;
                }

                const result = await response.json();

                if (result.success) {
                    const data = result.data;
                    const container = document.getElementById('skills-content');
                    container.textContent = '';

                    const grid = createEl('div', 'grid md:grid-cols-3 gap-4');

                    const col1 = createEl('div', 'text-center');
                    col1.appendChild(createEl('div', 'text-xl font-bold text-yellow-400', formatNumber(data.total_sp)));
                    col1.appendChild(createEl('div', 'text-xs text-blue-300 mt-1', '总技能点'));
                    grid.appendChild(col1);

                    const col2 = createEl('div', 'text-center');
                    col2.appendChild(createEl('div', 'text-xl font-bold text-green-400', formatNumber(data.unallocated_sp)));
                    col2.appendChild(createEl('div', 'text-xs text-blue-300 mt-1', '未分配技能点'));
                    grid.appendChild(col2);

                    const col3 = createEl('div', 'text-center');
                    col3.appendChild(createEl('div', 'text-xl font-bold text-blue-400', String(data.skills ? data.skills.length : 0)));
                    col3.appendChild(createEl('div', 'text-xs text-blue-300 mt-1', '已学技能数'));
                    grid.appendChild(col3);

                    container.appendChild(grid);
                } else {
                    let title = result.error === 'connection_timeout' ? '无法获取技能数据' : '技能数据暂时不可用';
                    let message = result.error === 'connection_timeout' ? '服务器重启期间，技能数据可能无法访问' : '请稍后再试';
                    showError('skills-content', '📚', title, message);
                }
            } catch (error) {
                console.error('加载技能数据失败:', error);
                showError('skills-content', '📚', '加载失败', '网络错误，请刷新页面重试');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            Promise.all([
                loadServerStatus(),
                loadCharacterInfo(),
                loadSkills(),
            ]).then(() => {
                console.log('所有数据加载完成');
            }).catch(error => {
                console.error('数据加载失败:', error);
            });
        });

        setInterval(() => { loadServerStatus(); }, 30000);
    </script>
</body>
</html>
