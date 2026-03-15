@extends('layouts.guest')

@push('styles')
<style>
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

@section('title', '游客仪表盘 - Tus Esi System')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <!-- 三服务器状态卡片 -->
        <div id="server-status-wrapper" class="bg-white/10 backdrop-blur-lg rounded-xl overflow-hidden mb-6 border border-white/10">
            <div class="text-center py-3 border-b border-cyan-400/30 bg-cyan-900/20">
                <h2 class="text-lg font-semibold text-cyan-300 tracking-wider">服务器状态</h2>
            </div>
            <div id="server-status-content" class="p-4">
                <!-- 骨架屏 -->
                <div class="grid grid-cols-3 gap-4">
                    <div class="text-center p-4">
                        <div class="skeleton h-5 w-16 mx-auto mb-3"></div>
                        <div class="skeleton h-8 w-20 mx-auto mb-2"></div>
                        <div class="skeleton h-3 w-24 mx-auto mb-1"></div>
                        <div class="skeleton h-3 w-16 mx-auto"></div>
                    </div>
                    <div class="text-center p-4">
                        <div class="skeleton h-5 w-16 mx-auto mb-3"></div>
                        <div class="skeleton h-8 w-20 mx-auto mb-2"></div>
                        <div class="skeleton h-3 w-24 mx-auto mb-1"></div>
                        <div class="skeleton h-3 w-16 mx-auto"></div>
                    </div>
                    <div class="text-center p-4">
                        <div class="skeleton h-5 w-16 mx-auto mb-3"></div>
                        <div class="skeleton h-8 w-20 mx-auto mb-2"></div>
                        <div class="skeleton h-3 w-24 mx-auto mb-1"></div>
                        <div class="skeleton h-3 w-16 mx-auto"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 提示授权卡片 -->
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 mb-6 eve-glow border border-yellow-500/30">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="text-4xl">🔐</div>
                    <div>
                        <h3 class="text-lg font-semibold text-yellow-300">授权解锁更多功能</h3>
                        <p class="text-blue-300 text-sm mt-1">授权后可查看：角色信息、技能队列、资产、钱包等</p>
                    </div>
                </div>
                <a href="{{ route('auth.guide') }}" class="bg-yellow-600 hover:bg-yellow-700 px-6 py-3 rounded-lg font-medium transition-all whitespace-nowrap">
                    立即授权
                </a>
            </div>
        </div>

        <!-- 功能预览卡片 -->
        <div class="grid md:grid-cols-3 gap-6 mb-6">
            <!-- 角色信息预览 -->
            <div class="bg-white/5 backdrop-blur-lg rounded-xl p-6 border border-white/10 relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-blue-900/20 to-transparent"></div>
                <div class="relative">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold">👤 角色信息</h2>
                        <span class="text-2xl opacity-50">🔒</span>
                    </div>
                    <div class="space-y-2 text-sm text-blue-300">
                        <p>• 角色名称与 ID</p>
                        <p>• 军团与联盟信息</p>
                        <p>• 当前位置</p>
                        <p>• 在线状态</p>
                    </div>
                </div>
            </div>

            <!-- 技能信息预览 -->
            <div class="bg-white/5 backdrop-blur-lg rounded-xl p-6 border border-white/10 relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-purple-900/20 to-transparent"></div>
                <div class="relative">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold">📚 技能信息</h2>
                        <span class="text-2xl opacity-50">🔒</span>
                    </div>
                    <div class="space-y-2 text-sm text-blue-300">
                        <p>• 总技能点数</p>
                        <p>• 未分配技能点</p>
                        <p>• 技能队列</p>
                        <p>• 全技能列表</p>
                    </div>
                </div>
            </div>

            <!-- 资产信息预览 -->
            <div class="bg-white/5 backdrop-blur-lg rounded-xl p-6 border border-white/10 relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-green-900/20 to-transparent"></div>
                <div class="relative">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold">📦 资产信息</h2>
                        <span class="text-2xl opacity-50">🔒</span>
                    </div>
                    <div class="space-y-2 text-sm text-blue-300">
                        <p>• 物品位置列表</p>
                        <p>• 树形物品展示</p>
                        <p>• 物品搜索</p>
                        <p>• 舰船/机库分类</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 底部提示 -->
        <div class="text-center text-blue-300/60 text-sm py-4">
            <p>游客模式仅能查看公开的服务器状态信息</p>
            <p class="mt-1">授权后即可解锁全部个人数据查询功能</p>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // 工具函数：格式化数字
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    // 工具函数：格式化启动时间
    function formatStartTime(isoStr) {
        if (!isoStr) return '-';
        const d = new Date(isoStr);
        return d.getFullYear() + '-' +
            String(d.getMonth()+1).padStart(2,'0') + '-' +
            String(d.getDate()).padStart(2,'0') + ' ' +
            String(d.getHours()).padStart(2,'0') + ':' +
            String(d.getMinutes()).padStart(2,'0');
    }

    // 加载服务器状态
    async function loadServerStatus() {
        try {
            const response = await fetch('{{ route("api.public.server-status") }}', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                },
            });

            const data = await response.json();
            const container = document.getElementById('server-status-content');

            if (data && data.length > 0) {
                let html = '<div class="grid grid-cols-3 gap-4">';

                data.forEach(server => {
                    const statusClass = server.is_online ? 'online' : 'offline';
                    const statusText = server.is_online ? '在线' : '离线';
                    const statusColor = server.is_online ? 'text-green-500' : 'text-red-500';
                    const playersColor = server.is_online ? 'text-green-400' : 'text-gray-500';

                    html += `
                        <div class="text-center p-4 bg-white/5 rounded-lg">
                            <div class="text-sm font-medium text-cyan-300 mb-2">${server.name}</div>
                            <div class="text-lg font-bold ${statusColor} mb-2">
                                <span class="status-dot ${statusClass}"></span>${statusText}
                            </div>
                            <div class="text-lg font-bold ${playersColor}">${formatNumber(server.players)}</div>
                            <div class="text-xs text-blue-400/70">在线玩家</div>
                            ${server.is_online ? `
                                <div class="mt-2 text-xs text-blue-300/50">${formatStartTime(server.start_time)}</div>
                            ` : ''}
                        </div>
                    `;
                });

                html += '</div>';
                container.innerHTML = html;
            } else {
                container.innerHTML = `
                    <div class="text-center py-8">
                        <p class="text-blue-300/60">暂时无法获取服务器状态</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('加载服务器状态失败:', error);
            const container = document.getElementById('server-status-content');
            container.innerHTML = `
                <div class="text-center py-8">
                    <p class="text-blue-300/60">加载失败，请刷新页面重试</p>
                </div>
            `;
        }
    }

    // 页面加载完成后开始异步加载数据
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 开始加载服务器状态...');
        loadServerStatus();
    });

    // 每 60 秒刷新一次服务器状态
    setInterval(() => {
        loadServerStatus();
    }, 60000);
</script>
@endpush
