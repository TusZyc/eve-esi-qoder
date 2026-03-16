@extends('layouts.app')

@push('styles')
<style>
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
    /* 状态指示灯 */
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
@endpush

@section('title', '仪表盘')

@section('content')
    <div class="container mx-auto px-4 py-8">
    <!-- 服务器状态 -->
    <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 mb-6 eve-glow">
        <h2 class="text-xl font-semibold mb-4">📡 服务器状态</h2>
        <div id="server-status-content">
            <!-- 骨架屏 -->
            <div class="grid grid-cols-5 gap-4 text-sm">
                <div class="text-center">
                    <div class="skeleton h-6 w-16 mx-auto mb-1"></div>
                    <div class="skeleton h-3 w-12 mx-auto"></div>
                </div>
                <div class="text-center">
                    <div class="skeleton h-6 w-16 mx-auto mb-1"></div>
                    <div class="skeleton h-3 w-12 mx-auto"></div>
                </div>
                <div class="text-center">
                    <div class="skeleton h-6 w-20 mx-auto mb-1"></div>
                    <div class="skeleton h-3 w-12 mx-auto"></div>
                </div>
                <div class="text-center">
                    <div class="skeleton h-6 w-24 mx-auto mb-1"></div>
                    <div class="skeleton h-3 w-12 mx-auto"></div>
                </div>
                <div class="text-center">
                    <div class="skeleton h-6 w-12 mx-auto mb-1"></div>
                    <div class="skeleton h-3 w-12 mx-auto"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 角色信息 -->
    <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 mb-6 eve-glow">
        <h2 class="text-xl font-semibold mb-4">👤 角色信息</h2>
        <div id="character-info-content">
            <!-- 骨架屏 -->
            <div class="grid md:grid-cols-5 gap-4">
                <div class="text-center">
                    <div class="skeleton h-3 w-16 mb-2"></div>
                    <div class="skeleton h-5 w-28"></div>
                </div>
                <div class="text-center">
                    <div class="skeleton h-3 w-16 mb-2"></div>
                    <div class="skeleton h-5 w-28"></div>
                </div>
                <div class="text-center">
                    <div class="skeleton h-3 w-16 mb-2"></div>
                    <div class="skeleton h-5 w-28"></div>
                </div>
                <div class="text-center">
                    <div class="skeleton h-3 w-16 mb-2"></div>
                    <div class="skeleton h-5 w-28"></div>
                </div>
                <div class="text-center">
                    <div class="skeleton h-3 w-16 mb-2"></div>
                    <div class="skeleton h-5 w-28"></div>
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
    </div>
@endsection

@push('scripts')
<script>
    // API 端点
    const API_ENDPOINTS = {
        serverStatus: '{{ route("api.dashboard.server-status") }}',
        skills: '{{ route("api.dashboard.skills") }}',
        characterInfo: '{{ route("api.dashboard.character-info") }}',
        characterLocation: '{{ route("api.dashboard.character-location") }}',
        characterOnline: '{{ route("api.dashboard.character-online") }}',
    };

    // 工具函数：格式化数字
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    // 工具函数：格式化时间
    function formatTime(isoString) {
        if (!isoString) return 'N/A';
        const date = new Date(isoString);
        return date.toLocaleString('zh-CN', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
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

    // 加载服务器状态
    async function loadServerStatus() {
        try {
            const response = await fetch(API_ENDPOINTS.serverStatus, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            
            const result = await response.json();
            
            if (result.success) {
                const data = result.data;
                const container = document.getElementById('server-status-content');
                
                // 判断状态
                let statusText = '在线';
                let statusClass = 'online';
                let statusColor = 'text-green-500';
                
                if (data.is_maintenance) {
                    statusText = '调试中';
                    statusClass = 'maintenance';
                    statusColor = 'text-yellow-500';
                } else if (!data.is_online) {
                    statusText = '离线';
                    statusClass = 'offline';
                    statusColor = 'text-red-500';
                }
                
                // VIP 模式显示
                const vipText = data.vip ? '是' : '否';
                const vipColor = data.vip ? 'text-yellow-500' : 'text-green-500';
                
                container.innerHTML = `
                    <div class="grid grid-cols-5 gap-4">
                        <div class="text-center">
                            <div class="text-lg font-bold ${statusColor}">
                                <span class="status-dot ${statusClass}"></span>${statusText}
                            </div>
                            <div class="text-xs text-blue-300 mt-1">状态</div>
                        </div>
                        <div class="text-center">
                            <div class="text-lg font-bold text-green-500">${formatNumber(data.players)}</div>
                            <div class="text-xs text-blue-300 mt-1">在线玩家</div>
                        </div>
                        <div class="text-center">
                            <div class="text-lg font-bold text-blue-500">${data.server_version}</div>
                            <div class="text-xs text-blue-300 mt-1">服务器版本</div>
                        </div>
                        <div class="text-center">
                            <div class="text-lg font-bold text-purple-500">${formatTime(data.start_time)}</div>
                            <div class="text-xs text-blue-300 mt-1">启动时间</div>
                        </div>
                        <div class="text-center">
                            <div class="text-lg font-bold ${vipColor}">${vipText}</div>
                            <div class="text-xs text-blue-300 mt-1">VIP 模式</div>
                        </div>
                    </div>
                `;
            } else {
                let icon = '📡';
                let title = '无法获取服务器状态';
                let message = '请稍后再试';
                
                if (result.error === 'server_maintenance') {
                    icon = '⚙️';
                    title = '服务器维护中';
                    message = '国服每天 11:00 进行例行维护';
                } else if (result.error === 'server_offline') {
                    icon = '🔄';
                    title = '服务器不在线';
                    message = '服务器正在重启中';
                }
                
                showError('server-status-content', icon, title, message);
            }
        } catch (error) {
            console.error('加载服务器状态失败:', error);
            showError('server-status-content', '⚠️', '加载失败', '网络错误，请刷新页面重试');
        }
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
                
                const allianceDisplay = data.has_alliance 
                    ? `${data.alliance_name}<span class="text-xs text-blue-400 ml-1">(ID: ${data.alliance_id})</span>`
                    : `<span class="text-blue-400">无联盟</span>`;
                
                container.innerHTML = `
                    <div class="grid md:grid-cols-5 gap-4">
                        <div class="text-center">
                            <div class="text-xs text-blue-300 mb-1">角色</div>
                            <div class="text-lg font-bold text-blue-400">${data.character_name}<span class="text-xs text-blue-400 ml-1">(ID: ${data.character_id})</span></div>
                        </div>
                        <div class="text-center">
                            <div class="text-xs text-blue-300 mb-1">军团</div>
                            <div class="text-lg font-bold text-purple-400">${data.corporation_name}<span class="text-xs text-purple-400 ml-1">(ID: ${data.corporation_id})</span></div>
                        </div>
                        <div class="text-center">
                            <div class="text-xs text-blue-300 mb-1">联盟</div>
                            <div class="text-lg font-bold text-green-400">${allianceDisplay}</div>
                        </div>
                        <div class="text-center" id="location-content">
                            <div class="text-xs text-blue-300 mb-1">当前位置</div>
                            <div class="loading-spinner inline-block"></div>
                        </div>
                        <div class="text-center" id="online-content">
                            <div class="text-xs text-blue-300 mb-1">在线状态</div>
                            <div class="loading-spinner inline-block"></div>
                        </div>
                    </div>
                `;
                
                // 加载位置信息
                loadCharacterLocation();
                
                // 加载在线状态
                loadCharacterOnline();
            } else {
                showError('character-info-content', '⚠️', '加载失败', result.message || '无法获取角色信息');
            }
        } catch (error) {
            console.error('加载角色信息失败:', error);
            showError('character-info-content', '⚠️', '加载失败', '网络错误，请刷新页面重试');
        }
    }

    // 加载角色位置信息
    async function loadCharacterLocation() {
        try {
            const response = await fetch(API_ENDPOINTS.characterLocation, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            
            const result = await response.json();
            const container = document.getElementById('location-content');
            
            if (result.success && result.data) {
                const locationText = result.data.location_display || '未停靠';
                container.innerHTML = `
                    <div class="text-xs text-blue-300 mb-1">当前位置</div>
                    <div class="text-lg font-bold text-yellow-400">${locationText}</div>
                `;
            } else {
                container.innerHTML = `
                    <div class="text-xs text-blue-300 mb-1">当前位置</div>
                    <div class="text-lg font-bold text-blue-400">未停靠</div>
                `;
            }
        } catch (error) {
            console.error('加载位置信息失败:', error);
            const container = document.getElementById('location-content');
            container.innerHTML = `
                <div class="text-xs text-blue-300 mb-1">当前位置</div>
                <div class="text-lg font-bold text-blue-400">未停靠</div>
            `;
        }
    }

    // 加载角色在线状态
    async function loadCharacterOnline() {
        try {
            const response = await fetch(API_ENDPOINTS.characterOnline, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            
            const result = await response.json();
            const container = document.getElementById('online-content');
            
            if (result.success && result.data) {
                const isOnline = result.data.is_online;
                const displayText = result.data.display_text || (isOnline ? '在线' : '离线');
                const colorClass = isOnline ? 'text-green-400' : 'text-red-400';
                const indicatorClass = isOnline ? 'online' : 'offline';
                
                container.innerHTML = `
                    <div class="text-xs text-blue-300 mb-1">在线状态</div>
                    <div class="text-lg font-bold ${colorClass}">
                        <span class="status-dot ${indicatorClass}"></span>${displayText}
                    </div>
                `;
            } else {
                container.innerHTML = `
                    <div class="text-xs text-blue-300 mb-1">在线状态</div>
                    <div class="text-lg font-bold text-blue-400">未知</div>
                `;
            }
        } catch (error) {
            console.error('加载在线状态失败:', error);
            const container = document.getElementById('online-content');
            container.innerHTML = `
                <div class="text-xs text-blue-300 mb-1">在线状态</div>
                <div class="text-lg font-bold text-blue-400">未知</div>
            `;
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
                container.innerHTML = `
                    <div class="grid md:grid-cols-3 gap-4">
                        <div class="text-center">
                            <div class="text-xl font-bold text-yellow-400">${formatNumber(data.total_sp)}</div>
                            <div class="text-xs text-blue-300 mt-1">总技能点</div>
                        </div>
                        <div class="text-center">
                            <div class="text-xl font-bold text-green-400">${formatNumber(data.unallocated_sp)}</div>
                            <div class="text-xs text-blue-300 mt-1">未分配技能点</div>
                        </div>
                        <div class="text-center">
                            <div class="text-xl font-bold text-blue-400">${data.skills ? data.skills.length : 0}</div>
                            <div class="text-xs text-blue-300 mt-1">已学技能数</div>
                        </div>
                    </div>
                `;
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

    // 页面加载完成后开始异步加载数据
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 开始异步加载数据...');
        
        // 并行加载所有数据
        Promise.all([
            loadServerStatus(),
            loadCharacterInfo(),
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
@endpush
