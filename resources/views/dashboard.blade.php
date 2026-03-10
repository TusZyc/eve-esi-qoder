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
        
        /* 骨架屏动画 */
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
        .skeleton-circle {
            border-radius: 50%;
        }
        
        /* 加载动画 */
        .loading-spinner {
            border: 3px solid rgba(255,255,255,0.1);
            border-radius: 50%;
            border-top: 3px solid #60a5fa;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="eve-bg min-h-screen text-white">
    <!-- 导航栏 -->
    <nav class="bg-white/10 backdrop-blur-lg border-b border-white/20">
        <div class="container mx-auto px-4 py-3">
            <!-- 第一行：标题和欢迎语 -->
            <div class="flex justify-between items-center mb-3">
                <a href="{{ route('dashboard') }}" class="text-xl font-bold">🚀 EVE ESI</a>
                <div class="flex items-center space-x-4">
                    <span class="text-blue-200">欢迎，{{ $user->name }}</span>
                    <form action="{{ route('auth.logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg text-sm">
                            登出
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- 第二行：栏目导航 -->
            <div class="grid grid-cols-4 gap-3">
            <a href="{{ route('dashboard') }}" 
               class="bg-blue-600/20 backdrop-blur-lg border border-blue-500/50 rounded-xl p-6 text-center hover:bg-blue-600/30 transition-all eve-glow">
                <div class="text-3xl mb-2">📊</div>
                <div class="font-semibold">仪表盘</div>
            </a>
            <a href="{{ route('skills.index') }}" 
               class="bg-white/10 backdrop-blur-lg rounded-xl p-6 text-center hover:bg-white/20 transition-all eve-glow">
                <div class="text-3xl mb-2">📚</div>
                <div class="font-semibold">技能队列</div>
            </a>
            <a href="{{ route('assets.index') }}" 
               class="bg-white/10 backdrop-blur-lg rounded-xl p-6 text-center hover:bg-white/20 transition-all eve-glow">
                <div class="text-3xl mb-2">📦</div>
                <div class="font-semibold">我的资产</div>
            </a>
            <a href="{{ route('characters.index') }}" 
               class="bg-white/10 backdrop-blur-lg rounded-xl p-6 text-center hover:bg-white/20 transition-all eve-glow">
                <div class="text-3xl mb-2">👥</div>
                <div class="font-semibold">角色管理</div>
            </a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <!-- 服务器状态 -->
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 mb-6 eve-glow">
            <h2 class="text-xl font-semibold mb-4">📡 服务器状态</h2>
            <div id="server-status-content">
                <!-- 骨架屏 -->
                <div class="grid md:grid-cols-3 gap-4">
                    <div class="text-center">
                        <div class="skeleton h-10 w-24 mx-auto mb-2"></div>
                        <div class="skeleton h-4 w-20 mx-auto"></div>
                    </div>
                    <div class="text-center">
                        <div class="skeleton h-10 w-32 mx-auto mb-2"></div>
                        <div class="skeleton h-4 w-20 mx-auto"></div>
                    </div>
                    <div class="text-center">
                        <div class="skeleton h-10 w-24 mx-auto mb-2"></div>
                        <div class="skeleton h-4 w-20 mx-auto"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 角色信息 -->
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 mb-6 eve-glow">
            <h2 class="text-xl font-semibold mb-4">👤 角色信息</h2>
            <div id="character-info-content">
                <div class="grid grid-cols-3 gap-4">
                    <!-- 骨架屏 -->
                    <div>
                        <div class="skeleton h-4 w-20 mb-2"></div>
                        <div class="skeleton h-6 w-32"></div>
                    </div>
                    <div>
                        <div class="skeleton h-4 w-20 mb-2"></div>
                        <div class="skeleton h-6 w-32"></div>
                    </div>
                    <div>
                        <div class="skeleton h-4 w-20 mb-2"></div>
                        <div class="skeleton h-6 w-32"></div>
                    </div>
                    <div>
                        <div class="skeleton h-4 w-20 mb-2"></div>
                        <div class="skeleton h-6 w-32"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 技能信息 -->
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 mb-6 eve-glow">
            <h2 class="text-xl font-semibold mb-4">📚 技能信息</h2>
            <div id="skills-content">
                <!-- 骨架屏 -->
                <div class="grid md:grid-cols-3 gap-4">
                    <div class="text-center">
                        <div class="skeleton h-10 w-24 mx-auto mb-2"></div>
                        <div class="skeleton h-4 w-20 mx-auto"></div>
                    </div>
                    <div class="text-center">
                        <div class="skeleton h-10 w-24 mx-auto mb-2"></div>
                        <div class="skeleton h-4 w-20 mx-auto"></div>
                    </div>
                    <div class="text-center">
                        <div class="skeleton h-10 w-24 mx-auto mb-2"></div>
                        <div class="skeleton h-4 w-20 mx-auto"></div>
                    </div>
                </div>
            </div>
        </div>

                <!-- 骨架屏 -->
                <div class="space-y-3">
                    <div class="bg-white/5 rounded-lg p-4">
                        <div class="skeleton h-5 w-3/4 mb-2"></div>
                        <div class="skeleton h-2 w-full mb-1"></div>
                        <div class="skeleton h-2 w-1/2"></div>
                    </div>
                    <div class="bg-white/5 rounded-lg p-4">
                        <div class="skeleton h-5 w-3/4 mb-2"></div>
                        <div class="skeleton h-2 w-full mb-1"></div>
                        <div class="skeleton h-2 w-1/2"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript 异步加载数据 -->
    <script>
        // API 端点
        const API_ENDPOINTS = {
            serverStatus: '{{ route("api.dashboard.server-status") }}',
            skills: '{{ route("api.dashboard.skills") }}',
            characterInfo: '{{ route("api.dashboard.character-info") }}',
        };

        // 工具函数：格式化数字
        function formatNumber(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        // 工具函数：显示错误信息
        function showError(containerId, icon, title, message) {
            const container = document.getElementById(containerId);
            container.innerHTML = `
                <div class="text-center py-8">
                    <div class="text-5xl mb-4">${icon}</div>
                    <p class="text-blue-300 text-lg mb-2">${title}</p>
                    <p class="text-blue-400 text-sm">${message}</p>
                </div>
            `;
        }

        // 加载角色信息
        async function loadCharacterInfo() {
            try {
                const response = await fetch(API_ENDPOINTS.characterInfo, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
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
                    
                    // 联盟信息（可能为空）
                    const allianceDisplay = data.has_alliance 
                        ? `${data.alliance_name} <span class="text-sm text-blue-400">(ID: ${data.alliance_id})</span>`
                        : `<span class="text-blue-400">无联盟</span>`;
                    
                    container.innerHTML = `
                        <div class="grid grid-cols-3 gap-4">
                            <div class="text-center">
                                <div class="text-sm text-blue-200">角色</div>
                                <div class="text-lg">${data.character_name} <span class="text-sm text-blue-400">(ID: ${data.character_id})</span></div>
                            </div>
                            <div class="text-center">
                                <div class="text-sm text-blue-200">军团</div>
                                <div class="text-lg">${data.corporation_name} <span class="text-sm text-blue-400">(ID: ${data.corporation_id})</span></div>
                            </div>
                            <div class="text-center">
                                <div class="text-sm text-blue-200">联盟</div>
                                <div class="text-lg">${allianceDisplay}</div>
                            </div>
                        </div>
                    `;
                } else {
                    showError('character-info-content', '⚠️', '加载失败', result.message || '无法获取角色信息');
                }
            } catch (error) {
                console.error('加载角色信息失败:', error);
                showError('character-info-content', '⚠️', '加载失败', '网络错误，请刷新页面重试');
            }
        }

        // 加载服务器状态
        async function loadServerStatus() {
            try {
                const response = await fetch(API_ENDPOINTS.serverStatus, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin', // 重要！携带 Cookie（Session）
                });
                
                const result = await response.json();
                
                if (result.success) {
                    const data = result.data;
                    const container = document.getElementById('server-status-content');
                    
                    // 根据 VIP 状态和玩家数决定显示颜色
                    let playerColor = 'text-green-400';
                    let statusBadge = '';
                    
                    if (data.vip) {
                        // VIP 模式 - GM 测试中，未正式开服
                        playerColor = 'text-yellow-400';
                        statusBadge = `
                            <div class="text-center md:col-span-3 mt-4">
                                <span class="bg-yellow-500/20 text-yellow-300 px-4 py-2 rounded-lg text-sm border border-yellow-500/50">
                                    ⚠️ VIP 模式 - GM 测试中，未正式开服
                                </span>
                            </div>
                        `;
                    }
                    
                    container.innerHTML = `
                        <div class="grid md:grid-cols-3 gap-4">
                            <div class="text-center">
                                <div class="text-3xl font-bold ${playerColor}">${formatNumber(data.players)}</div>
                                <div class="text-sm text-blue-200">在线玩家</div>
                                ${data.vip ? '<div class="text-xs text-yellow-400 mt-1">VIP 模式</div>' : ''}
                            </div>
                            <div class="text-center">
                                <div class="text-3xl font-bold text-blue-400">${data.server_version}</div>
                                <div class="text-sm text-blue-200">服务器版本</div>
                            </div>
                            <div class="text-center">
                                <div class="text-3xl font-bold text-purple-400">${data.status_text || '已开服'}</div>
                                <div class="text-sm text-blue-200">服务器状态</div>
                            </div>
                        </div>
                        ${statusBadge}
                    `;
                } else {
                    let icon = '📡';
                    let title = '无法获取服务器状态';
                    let message = '请稍后再试';
                    
                    if (result.error === 'server_maintenance') {
                        icon = '⚙️';
                        title = '服务器维护中';
                        message = '国服每天 11:00 进行例行维护，API 可访问但服务器处于维护状态';
                    } else if (result.error === 'server_offline') {
                        icon = '🔄';
                        title = '服务器不在线';
                        message = '服务器正在重启中，请等待开服（通常维护后 5-15 分钟）';
                    } else if (result.error === 'gateway_timeout') {
                        icon = '⏱️';
                        title = '响应超时';
                        message = '请稍等片刻后刷新重试';
                    } else if (result.error === 'connection_timeout') {
                        icon = '🔄';
                        title = '连接超时';
                        message = '服务器可能正在重启，请等待后重试';
                    }
                    
                    showError('server-status-content', icon, title, message);
                }
            } catch (error) {
                console.error('加载服务器状态失败:', error);
                showError('server-status-content', '⚠️', '加载失败', '网络错误，请刷新页面重试');
            }
        }

        // 加载技能数据
        async function loadSkills() {
            try {
                const response = await fetch(API_ENDPOINTS.skills, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin', // 重要！携带 Cookie（Session）
                });
                
                // 检查是否是 401 未授权（Token 过期）
                if (response.status === 401) {
                    showError('skills-content', '🔐', '未授权', '会话已过期，请刷新页面重新登录');
                    return;
                }
                
                const result = await response.json();
                
                if (result.success) {
                    const data = result.data;
                    const container = document.getElementById('skills-content');
                    container.innerHTML = `
                        <div class="grid md:grid-cols-3 gap-4">
                            <div class="text-center">
                                <div class="text-3xl font-bold text-yellow-400">${formatNumber(data.total_sp)}</div>
                                <div class="text-sm text-blue-200">总技能点</div>
                            </div>
                            <div class="text-center">
                                <div class="text-3xl font-bold text-green-400">${formatNumber(data.unallocated_sp)}</div>
                                <div class="text-sm text-blue-200">未分配技能点</div>
                            </div>
                            <div class="text-center">
                                <div class="text-3xl font-bold text-blue-400">${data.skills ? data.skills.length : 0}</div>
                                <div class="text-sm text-blue-200">已学技能数</div>
                            </div>
                        </div>
                    `;
                } else {
                    let title = result.error === 'connection_timeout' ? '无法获取技能数据' : '技能数据暂时不可用';
                    let message = result.error === 'connection_timeout' ? '服务器重启期间，技能数据可能无法访问' : '请稍后再试';
                    showError('skills-content', '📚', title, message);
                }
            } catch (error) {
        }

        // 页面加载完成后开始异步加载数据
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 开始异步加载数据...');
            
            // 并行加载所有数据
            Promise.all([
                loadCharacterInfo(),
                loadServerStatus(),
                loadSkills(),
            ]).then(() => {
                console.log('✅ 所有数据加载完成');
            }).catch(error => {
                console.error('❌ 数据加载失败:', error);
            });
        });

        // 可选：定时刷新（每 30 秒刷新服务器状态）
        setInterval(() => {
            loadServerStatus();
        }, 30000);
    </script>
</body>
</html>
