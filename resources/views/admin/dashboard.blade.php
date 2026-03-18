@extends('layouts.admin')

@section('title', '管理首页')
@section('page-title', '管理首页')

@push('styles')
<style>
    .user-card {
        transition: all 0.2s ease;
    }
    .user-card:hover {
        transform: translateY(-2px);
    }
    .token-valid { color: #22c55e; }
    .token-expiring { color: #fbbf24; }
    .token-expired { color: #ef4444; }
    .scroll-container {
        max-height: 400px;
        overflow-y: auto;
    }
    .scroll-container::-webkit-scrollbar {
        width: 6px;
    }
    .scroll-container::-webkit-scrollbar-track {
        background: rgba(255,255,255,0.05);
        border-radius: 3px;
    }
    .scroll-container::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.2);
        border-radius: 3px;
    }
</style>
@endpush

@section('content')
<div class="space-y-6">
    <!-- 统计卡片 -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="stat-card rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-sm">已授权用户</p>
                    <p class="text-3xl font-bold mt-1" id="stat-users">
                        <span class="skeleton inline-block w-12 h-8"></span>
                    </p>
                </div>
                <div class="text-4xl opacity-50">👥</div>
            </div>
        </div>
        
        <div class="stat-card rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-sm">今日错误</p>
                    <p class="text-3xl font-bold mt-1 text-red-400" id="stat-errors">
                        <span class="skeleton inline-block w-12 h-8"></span>
                    </p>
                </div>
                <div class="text-4xl opacity-50">⚠️</div>
            </div>
        </div>
        
        <div class="stat-card rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-sm">今日警告</p>
                    <p class="text-3xl font-bold mt-1 text-yellow-400" id="stat-warnings">
                        <span class="skeleton inline-block w-12 h-8"></span>
                    </p>
                </div>
                <div class="text-4xl opacity-50">⚡</div>
            </div>
        </div>
        
        <div class="stat-card rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-sm">日志大小</p>
                    <p class="text-3xl font-bold mt-1" id="stat-log-size">
                        <span class="skeleton inline-block w-16 h-8"></span>
                    </p>
                </div>
                <div class="text-4xl opacity-50">📁</div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- 已授权用户列表 -->
        <div class="stat-card rounded-xl p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center">
                <span class="mr-2">👥</span> 已授权用户
            </h3>
            <div class="scroll-container space-y-3" id="users-list">
                <!-- 骨架屏 -->
                @for ($i = 0; $i < 5; $i++)
                <div class="flex items-center space-x-3 p-3 bg-slate-800/50 rounded-lg">
                    <div class="skeleton w-10 h-10 rounded-full"></div>
                    <div class="flex-1">
                        <div class="skeleton w-32 h-4 mb-2"></div>
                        <div class="skeleton w-48 h-3"></div>
                    </div>
                </div>
                @endfor
            </div>
        </div>

        <!-- 最近错误 -->
        <div class="stat-card rounded-xl p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center">
                <span class="mr-2">🔴</span> 最近错误
            </h3>
            <div class="scroll-container space-y-3" id="errors-list">
                <!-- 骨架屏 -->
                @for ($i = 0; $i < 5; $i++)
                <div class="p-3 bg-slate-800/50 rounded-lg">
                    <div class="skeleton w-32 h-3 mb-2"></div>
                    <div class="skeleton w-full h-4"></div>
                </div>
                @endfor
            </div>
        </div>
    </div>

    <!-- API 调用统计 -->
    <div class="stat-card rounded-xl p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center">
            <span class="mr-2">📈</span> API 调用统计
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full" id="api-stats-table">
                <thead>
                    <tr class="text-left text-slate-400 text-sm border-b border-slate-700">
                        <th class="pb-3">端点</th>
                        <th class="pb-3">名称</th>
                        <th class="pb-3 text-right">调用次数</th>
                        <th class="pb-3 text-right">状态</th>
                    </tr>
                </thead>
                <tbody id="api-stats-body">
                    <!-- 骨架屏 -->
                    @for ($i = 0; $i < 5; $i++)
                    <tr class="border-b border-slate-700/50">
                        <td class="py-3"><span class="skeleton inline-block w-40 h-4"></span></td>
                        <td class="py-3"><span class="skeleton inline-block w-24 h-4"></span></td>
                        <td class="py-3 text-right"><span class="skeleton inline-block w-12 h-4"></span></td>
                        <td class="py-3 text-right"><span class="skeleton inline-block w-16 h-4"></span></td>
                    </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 加载管理首页数据
    loadDashboardData();
    loadApiStats();
});

async function loadDashboardData() {
    try {
        const response = await fetch('/api/admin/dashboard-data');
        const result = await response.json();
        
        if (result.success) {
            const data = result.data;
            
            // 更新统计数据
            document.getElementById('stat-users').textContent = data.user_count;
            document.getElementById('stat-errors').textContent = data.log_stats.error || 0;
            document.getElementById('stat-warnings').textContent = data.log_stats.warning || 0;
            document.getElementById('stat-log-size').textContent = formatBytes(data.log_stats.total_size || 0);
            
            // 更新用户列表
            renderUsersList(data.authorized_users);
            
            // 更新错误列表
            renderErrorsList(data.recent_errors);
        }
    } catch (error) {
        console.error('加载数据失败:', error);
    }
}

async function loadApiStats() {
    try {
        const response = await fetch('/api/admin/api-stats');
        const result = await response.json();
        
        if (result.success) {
            renderApiStats(result.data);
        }
    } catch (error) {
        console.error('加载 API 统计失败:', error);
    }
}

function renderUsersList(users) {
    const container = document.getElementById('users-list');
    
    if (!users || users.length === 0) {
        container.innerHTML = '<p class="text-slate-400 text-center py-4">暂无已授权用户</p>';
        return;
    }
    
    container.innerHTML = users.map(user => `
        <div class="user-card flex items-center space-x-3 p-3 bg-slate-800/50 rounded-lg hover:bg-slate-700/50">
            <img src="https://image.evepc.163.com/Character/${user.eve_character_id}_64.jpg" 
                 alt="${user.name}" class="w-10 h-10 rounded-full">
            <div class="flex-1 min-w-0">
                <p class="font-medium truncate">${user.name}</p>
                <p class="text-xs text-slate-400">
                    ID: ${user.eve_character_id}
                    <span class="mx-1">|</span>
                    授权: <span class="${getAuthStatusClass(user.auth_status)}">${user.auth_status_text}</span>
                    <span class="mx-1">|</span>
                    Token: <span class="${getTokenStatusClass(user.current_status)}">${user.current_status_text}</span>
                </p>
            </div>
            <div class="text-right text-xs text-slate-400">
                <p>最后活跃</p>
                <p>${formatTime(user.last_active)}</p>
            </div>
        </div>
    `).join('');
}

function renderErrorsList(errors) {
    const container = document.getElementById('errors-list');
    
    if (!errors || errors.length === 0) {
        container.innerHTML = '<p class="text-green-400 text-center py-4">✅ 暂无错误记录</p>';
        return;
    }
    
    container.innerHTML = errors.map(error => `
        <div class="p-3 bg-slate-800/50 rounded-lg hover:bg-slate-700/50 cursor-pointer" 
             title="${escapeHtml(error.full_message || error.message)}">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs text-slate-400">${error.time}</span>
                <span class="text-xs px-2 py-0.5 rounded ${getLevelClass(error.level)}">${error.level}</span>
            </div>
            <p class="text-sm text-red-300 line-clamp-2">${escapeHtml(error.message)}</p>
        </div>
    `).join('');
}

function renderApiStats(stats) {
    const tbody = document.getElementById('api-stats-body');
    
    if (!stats.endpoints || stats.endpoints.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="py-4 text-center text-slate-400">暂无 API 调用记录</td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = stats.endpoints.map(endpoint => `
        <tr class="border-b border-slate-700/50 hover:bg-slate-800/30">
            <td class="py-3 font-mono text-sm text-blue-300">${endpoint.path}</td>
            <td class="py-3 text-slate-300">${endpoint.name}</td>
            <td class="py-3 text-right font-medium">${endpoint.calls.toLocaleString()}</td>
            <td class="py-3 text-right">
                <span class="text-xs px-2 py-1 rounded bg-green-500/20 text-green-400">正常</span>
            </td>
        </tr>
    `).join('');
}

function getTokenStatusClass(status) {
    const classes = {
        'valid': 'token-valid',
        'expiring': 'token-expiring',
        'expired': 'token-expired'
    };
    return classes[status] || 'text-slate-400';
}

function getTokenStatusText(status) {
    const texts = {
        'valid': '有效',
        'expiring': '即将过期',
        'expired': '已过期'
    };
    return texts[status] || '未知';
}

function getAuthStatusClass(status) {
    const classes = {
        'valid': 'token-valid',
        'expired': 'token-expired',
        'none': 'text-slate-400',
        'unknown': 'text-slate-400'
    };
    return classes[status] || 'text-slate-400';
}

function getLevelClass(level) {
    const classes = {
        'ERROR': 'bg-red-500/20 text-red-400',
        'CRITICAL': 'bg-red-600/20 text-red-300',
        'WARNING': 'bg-yellow-500/20 text-yellow-400',
        'INFO': 'bg-blue-500/20 text-blue-400',
        'DEBUG': 'bg-slate-500/20 text-slate-400'
    };
    return classes[level] || 'bg-slate-500/20 text-slate-400';
}

function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function formatTime(timeStr) {
    if (!timeStr) return '-';
    const date = new Date(timeStr);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);
    
    if (diff < 60) return '刚刚';
    if (diff < 3600) return Math.floor(diff / 60) + '分钟前';
    if (diff < 86400) return Math.floor(diff / 3600) + '小时前';
    return Math.floor(diff / 86400) + '天前';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
@endpush
