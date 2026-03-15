@extends('layouts.app')

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
    @keyframes spin { to { transform: rotate(360deg); } }
    .spinner {
        border: 2px solid rgba(255,255,255,0.1);
        border-top-color: #60a5fa;
        border-radius: 50%;
        width: 20px; height: 20px;
        animation: spin 0.8s linear infinite;
        display: inline-block;
    }
    .attr-card {
        background: rgba(255,255,255,0.05);
        border-radius: 8px;
        padding: 12px 16px;
        text-align: center;
    }
    .attr-value {
        font-size: 1.5rem;
        font-weight: bold;
        color: #60a5fa;
    }
    .attr-label {
        font-size: 0.75rem;
        color: #93c5fd;
        margin-top: 2px;
    }
    .timeline-item {
        position: relative;
        padding-left: 24px;
        padding-bottom: 16px;
        border-left: 2px solid rgba(255,255,255,0.1);
    }
    .timeline-item:last-child {
        border-left-color: transparent;
        padding-bottom: 0;
    }
    .timeline-item::before {
        content: '';
        position: absolute;
        left: -5px;
        top: 4px;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #60a5fa;
    }
    .timeline-item:first-child::before {
        background: #4ade80;
    }
</style>
@endpush

@section('title', '角色管理 - Tus Esi System')

@section('content')
    <div class="container mx-auto px-4 py-8 max-w-5xl">
        <div class="mb-8">
            <h1 class="text-3xl font-bold mb-2">角色信息</h1>
            <p class="text-blue-300">查看角色详细信息</p>
        </div>

        @if(session('success'))
            <div class="bg-green-600/20 border border-green-500 text-green-300 px-4 py-3 rounded-lg mb-6">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-600/20 border border-red-500 text-red-300 px-4 py-3 rounded-lg mb-6">
                {{ session('error') }}
            </div>
        @endif

        @if($character)
        <!-- 基础信息卡片 -->
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow mb-6">
            <div class="flex flex-col md:flex-row gap-6">
                <!-- 左侧: 头像 + 名称 -->
                <div class="flex-shrink-0 text-center md:text-left">
                    <img src="https://image.evepc.163.com/Character/{{ $user->eve_character_id }}_256.jpg"
                         alt="角色头像"
                         class="w-32 h-32 rounded-xl border-2 border-blue-400/30 mx-auto md:mx-0"
                         onerror="this.style.display='none'">
                    <div class="mt-3">
                        <div class="text-2xl font-bold text-blue-400">{{ $character['name'] ?? $user->name }}</div>
                        <div class="text-sm text-blue-300/60 mt-1">ID: {{ $user->eve_character_id }}</div>
                    </div>
                </div>

                <!-- 右侧: 详细信息 -->
                <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @if(isset($character['birthday']))
                    <div>
                        <div class="text-xs text-blue-300/60">创建日期</div>
                        <div class="text-white">{{ $character['birthday_beijing'] ?? $character['birthday'] }}</div>
                    </div>
                    @endif

                    @if(isset($character['gender']))
                    <div>
                        <div class="text-xs text-blue-300/60">性别</div>
                        <div class="text-white">{{ $character['gender'] === 'male' ? '男' : '女' }}</div>
                    </div>
                    @endif

                    @if(isset($character['security_status']))
                    <div>
                        <div class="text-xs text-blue-300/60">安全等级</div>
                        <div class="font-bold {{ $character['security_status'] >= 0 ? 'text-green-400' : 'text-red-400' }}">
                            {{ number_format($character['security_status'], 2) }}
                        </div>
                    </div>
                    @endif

                    @if(isset($character['corporation_id']))
                    <div>
                        <div class="text-xs text-blue-300/60">军团</div>
                        <div class="text-purple-400">{{ $character['corporation_name'] ?? $character['corporation_id'] }}</div>
                    </div>
                    @endif

                    @if(isset($character['alliance_id']))
                    <div>
                        <div class="text-xs text-blue-300/60">联盟</div>
                        <div class="text-green-400">{{ $character['alliance_name'] ?? $character['alliance_id'] }}</div>
                    </div>
                    @endif

                    <div>
                        <div class="text-xs text-blue-300/60">Token 过期</div>
                        <div class="text-white">{{ $user->token_expires_at ? \Carbon\Carbon::parse($user->token_expires_at)->format('Y-m-d H:i:s') : '未知' }}</div>
                    </div>
                </div>
            </div>

            @if(!empty($character['description_html']))
            <div class="mt-6 pt-4 border-t border-white/10">
                <div class="text-xs text-blue-300/60 mb-2">角色描述</div>
                <div class="text-sm text-white/80 leading-relaxed">{!! $character['description_html'] !!}</div>
            </div>
            @endif
        </div>

        <!-- 属性卡片 -->
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow mb-6">
            <h2 class="text-lg font-semibold mb-4">角色属性</h2>
            <div id="attributes-content">
                <div class="flex items-center justify-center py-6 text-blue-300">
                    <span class="spinner mr-3"></span>加载中...
                </div>
            </div>
        </div>

        <!-- 植入体卡片 -->
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow mb-6">
            <h2 class="text-lg font-semibold mb-4">当前植入体</h2>
            <div id="implants-content">
                <div class="flex items-center justify-center py-6 text-blue-300">
                    <span class="spinner mr-3"></span>加载中...
                </div>
            </div>
        </div>

        <!-- 克隆体卡片 -->
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow mb-6">
            <h2 class="text-lg font-semibold mb-4">克隆体</h2>
            <div id="clones-content">
                <div class="flex items-center justify-center py-6 text-blue-300">
                    <span class="spinner mr-3"></span>加载中...
                </div>
            </div>
        </div>

        <!-- 雇佣历史卡片 -->
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow mb-6">
            <h2 class="text-lg font-semibold mb-4">雇佣历史</h2>
            <div id="corphistory-content">
                <div class="flex items-center justify-center py-6 text-blue-300">
                    <span class="spinner mr-3"></span>加载中...
                </div>
            </div>
        </div>

        @else
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow text-center py-12">
            <div class="text-6xl mb-4">&#x2753;</div>
            <p class="text-blue-300">无法获取角色信息</p>
        </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    function renderError(message) {
        return '<div class="text-center py-4 text-yellow-300/70 text-sm">' + escapeHtml(message) + '</div>';
    }

    const ATTR_NAMES = {
        'perception': '感知',
        'intelligence': '智力',
        'memory': '记忆',
        'willpower': '毅力',
        'charisma': '魅力'
    };

    async function loadAttributes() {
        const container = document.getElementById('attributes-content');
        if (!container) return;
        try {
            const resp = await fetch('/api/dashboard/character/attributes', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const result = await resp.json();
            if (!result.success) {
                container.innerHTML = renderError(result.message || '加载失败');
                return;
            }
            const d = result.data;
            let html = '<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3 mb-4">';
            for (const [key, label] of Object.entries(ATTR_NAMES)) {
                const val = d[key] || 0;
                html += '<div class="attr-card">';
                html += '<div class="attr-value">' + val + '</div>';
                html += '<div class="attr-label">' + escapeHtml(label) + '</div>';
                html += '</div>';
            }
            html += '</div>';

            // 重映射信息
            if (d.last_remap_date || d.accrued_remap_cooldown_date) {
                html += '<div class="text-xs text-blue-300/60 space-y-1">';
                if (d.last_remap_date) {
                    html += '<div>上次重映射: ' + escapeHtml(new Date(d.last_remap_date).toLocaleString('zh-CN', {timeZone: 'Asia/Shanghai'})) + '</div>';
                }
                if (d.accrued_remap_cooldown_date) {
                    html += '<div>下次可重映射: ' + escapeHtml(new Date(d.accrued_remap_cooldown_date).toLocaleString('zh-CN', {timeZone: 'Asia/Shanghai'})) + '</div>';
                }
                if (d.bonus_remaps !== undefined) {
                    html += '<div>剩余额外重映射次数: ' + d.bonus_remaps + '</div>';
                }
                html += '</div>';
            }

            container.innerHTML = html;
        } catch (e) {
            container.innerHTML = renderError('网络错误，请刷新重试');
        }
    }

    async function loadImplants() {
        const container = document.getElementById('implants-content');
        if (!container) return;
        try {
            const resp = await fetch('/api/dashboard/character/implants', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const result = await resp.json();
            if (!result.success) {
                container.innerHTML = renderError(result.message || '加载失败');
                return;
            }
            const implants = result.data;
            if (!implants || implants.length === 0) {
                container.innerHTML = '<div class="text-center py-4 text-blue-300/60 text-sm">未安装植入体</div>';
                return;
            }
            let html = '<div class="space-y-2">';
            implants.forEach(function(imp, idx) {
                html += '<div class="flex items-center gap-3 px-3 py-2 bg-white/5 rounded-lg">';
                html += '<span class="text-xs text-blue-300/40 w-6 text-right">' + (idx + 1) + '</span>';
                html += '<span class="text-sm text-white">' + escapeHtml(imp.name) + '</span>';
                html += '</div>';
            });
            html += '</div>';
            container.innerHTML = html;
        } catch (e) {
            container.innerHTML = renderError('网络错误，请刷新重试');
        }
    }

    async function loadClones() {
        const container = document.getElementById('clones-content');
        if (!container) return;
        try {
            const resp = await fetch('/api/dashboard/character/clones', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const result = await resp.json();
            if (!result.success) {
                container.innerHTML = renderError(result.message || '加载失败');
                return;
            }
            const d = result.data;
            let html = '';

            // 基地空间站
            if (d.home_location) {
                html += '<div class="mb-4 px-4 py-3 bg-white/5 rounded-lg">';
                html += '<div class="text-xs text-blue-300/60 mb-1">基地空间站</div>';
                html += '<div class="text-white font-medium">' + escapeHtml(d.home_location.location_name || 'ID: ' + d.home_location.location_id) + '</div>';
                html += '</div>';
            }

            // 上次跳跃克隆时间
            if (d.last_clone_jump_date) {
                html += '<div class="text-xs text-blue-300/60 mb-3">上次跳跃克隆: ' + escapeHtml(new Date(d.last_clone_jump_date).toLocaleString('zh-CN', {timeZone: 'Asia/Shanghai'})) + '</div>';
            }

            // 跳跃克隆列表
            const clones = d.jump_clones || [];
            if (clones.length === 0) {
                html += '<div class="text-center py-2 text-blue-300/60 text-sm">没有跳跃克隆</div>';
            } else {
                html += '<div class="text-xs text-blue-300/60 mb-2">跳跃克隆 (' + clones.length + ')</div>';
                html += '<div class="space-y-3">';
                clones.forEach(function(clone, idx) {
                    html += '<div class="bg-white/5 rounded-lg overflow-hidden">';
                    html += '<div class="px-4 py-2 border-b border-white/5 flex items-center justify-between">';
                    html += '<span class="text-sm font-medium text-blue-300">#' + (idx + 1) + ' ' + escapeHtml(clone.location_name || 'Unknown') + '</span>';
                    html += '<span class="text-xs text-blue-300/40">' + escapeHtml(clone.location_type) + '</span>';
                    html += '</div>';
                    if (clone.implants && clone.implants.length > 0) {
                        html += '<div class="px-4 py-2 space-y-1">';
                        clone.implants.forEach(function(imp) {
                            html += '<div class="text-xs text-white/70">  ' + escapeHtml(imp.name) + '</div>';
                        });
                        html += '</div>';
                    } else {
                        html += '<div class="px-4 py-2 text-xs text-blue-300/40">无植入体</div>';
                    }
                    html += '</div>';
                });
                html += '</div>';
            }

            container.innerHTML = html;
        } catch (e) {
            container.innerHTML = renderError('网络错误，请刷新重试');
        }
    }

    async function loadCorpHistory() {
        const container = document.getElementById('corphistory-content');
        if (!container) return;
        try {
            const resp = await fetch('/api/dashboard/character/corphistory', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const result = await resp.json();
            if (!result.success) {
                container.innerHTML = renderError(result.message || '加载失败');
                return;
            }
            const history = result.data;
            if (!history || history.length === 0) {
                container.innerHTML = '<div class="text-center py-4 text-blue-300/60 text-sm">无雇佣记录</div>';
                return;
            }
            let html = '<div class="pl-2">';
            history.forEach(function(item) {
                html += '<div class="timeline-item">';
                html += '<div class="text-sm text-white font-medium">' + escapeHtml(item.corporation_name) + '</div>';
                if (item.start_date) {
                    html += '<div class="text-xs text-blue-300/60 mt-0.5">加入时间: ' + escapeHtml(item.start_date) + '</div>';
                }
                if (item.is_deleted) {
                    html += '<div class="text-xs text-red-400/60 mt-0.5">已解散</div>';
                }
                html += '</div>';
            });
            html += '</div>';
            container.innerHTML = html;
        } catch (e) {
            container.innerHTML = renderError('网络错误，请刷新重试');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        loadAttributes();
        loadImplants();
        loadClones();
        loadCorpHistory();
    });
</script>
@endpush
