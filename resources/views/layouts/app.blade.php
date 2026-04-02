<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Tus Esi System')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* 侧边栏 - 深邃渐变 + 微光效果 */
        .admin-sidebar {
            background: linear-gradient(180deg, #1a1f2e 0%, #0d1117 100%);
            box-shadow: 4px 0 24px rgba(0, 0, 0, 0.3);
            position: relative;
        }
        .admin-sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 1px;
            height: 100%;
            background: linear-gradient(180deg, rgba(59,130,246,0.3) 0%, rgba(139,92,246,0.1) 50%, transparent 100%);
        }
        
        /* 主内容区 - 优雅深蓝渐变 */
        .admin-content {
            background: linear-gradient(135deg, #0a0f1a 0%, #111827 50%, #0f172a 100%);
            position: relative;
        }
        .admin-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(ellipse at top right, rgba(59,130,246,0.08) 0%, transparent 50%),
                        radial-gradient(ellipse at bottom left, rgba(139,92,246,0.05) 0%, transparent 50%);
            pointer-events: none;
        }
        
        /* 侧边栏链接 - 流畅动画 */
        .sidebar-link {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-left: 3px solid transparent;
            position: relative;
            overflow: hidden;
        }
        .sidebar-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(59,130,246,0.1), transparent);
            transition: left 0.5s ease;
        }
        .sidebar-link:hover::before {
            left: 100%;
        }
        .sidebar-link:hover {
            background: linear-gradient(90deg, rgba(59,130,246,0.15) 0%, rgba(59,130,246,0.05) 100%);
            border-left-color: rgba(59,130,246,0.5);
            transform: translateX(4px);
        }
        .sidebar-link.active {
            background: linear-gradient(90deg, rgba(59,130,246,0.25) 0%, rgba(59,130,246,0.1) 100%);
            border-left-color: #3b82f6;
            box-shadow: inset 0 0 20px rgba(59,130,246,0.1);
        }
        .sidebar-link.active::after {
            content: '';
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 6px;
            height: 6px;
            background: #3b82f6;
            border-radius: 50%;
            box-shadow: 0 0 10px #3b82f6;
        }
        
        /* 统计卡片 - 玻璃拟态 */
        .stat-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow: 0 8px 32px rgba(0,0,0,0.3),
                        inset 0 1px 0 rgba(255,255,255,0.05);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            opacity: 0.5;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }
        .stat-card:hover::before {
            opacity: 0;
        }
        .stat-card:hover {
            background: linear-gradient(135deg, rgba(255,255,255,0.08) 0%, rgba(255,255,255,0.04) 100%);
            border-color: rgba(59,130,246,0.3);
            box-shadow: 0 12px 40px rgba(0,0,0,0.4),
                        0 0 0 1px rgba(59,130,246,0.1),
                        inset 0 1px 0 rgba(255,255,255,0.08);
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
            border-radius: 6px;
        }
        
        /* 日志级别颜色 */
        .log-error { color: #f87171; text-shadow: 0 0 10px rgba(248,113,113,0.3); }
        .log-warning { color: #fbbf24; text-shadow: 0 0 10px rgba(251,191,36,0.3); }
        .log-info { color: #60a5fa; text-shadow: 0 0 10px rgba(96,165,250,0.3); }
        .log-debug { color: #9ca3af; }
        
        /* 表格美化 */
        table thead tr {
            background: linear-gradient(90deg, rgba(59,130,246,0.1) 0%, transparent 100%);
        }
        table tbody tr {
            transition: all 0.2s ease;
        }
        table tbody tr:hover {
            background: rgba(59,130,246,0.08) !important;
        }
        
        /* 按钮增强 */
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            box-shadow: 0 4px 15px rgba(59,130,246,0.3);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            box-shadow: 0 6px 20px rgba(59,130,246,0.4);
            transform: translateY(-1px);
        }
        
        /* 滚动条美化 */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.02);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, rgba(59,130,246,0.3) 0%, rgba(139,92,246,0.3) 100%);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, rgba(59,130,246,0.5) 0%, rgba(139,92,246,0.5) 100%);
        }
        
        /* 输入框美化 */
        input, select {
            transition: all 0.3s ease;
        }
        input:focus, select:focus {
            box-shadow: 0 0 0 3px rgba(59,130,246,0.2);
        }

        /* 保留原有样式 - 子视图可能用到 */
        .eve-bg {
            background: linear-gradient(135deg, #0c1445 0%, #1a237e 50%, #283593 100%);
        }
        .eve-glow {
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.5);
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

        @stack('styles')
    </style>
    @stack('head-scripts')
</head>
<body class="bg-slate-900 text-white min-h-screen flex">
    <!-- 左侧边栏 -->
    <aside class="admin-sidebar w-64 min-h-screen flex flex-col shrink-0">
        <!-- Logo 区域 -->
        <div class="p-5 border-b border-slate-700/50">
            <div class="flex items-center space-x-3">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center shadow-lg shadow-blue-500/20">
                    <img src="/images/eve-logo.png" alt="Logo" class="w-8 h-8 object-contain">
                </div>
                <div>
                    <h1 class="font-bold text-lg bg-gradient-to-r from-white to-slate-300 bg-clip-text text-transparent">Tus Esi System</h1>
                    <p class="text-xs text-slate-500">EVE Online 数据工具平台</p>
                </div>
            </div>
        </div>

        <!-- 导航菜单 -->
        <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
            <!-- 第一分类：角色信息（需认证） -->
            @if($isLoggedIn ?? false)
            <p class="text-xs text-slate-500 uppercase tracking-wider px-4 py-2">角色信息</p>
            <a href="{{ route('dashboard') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'dashboard' ? 'active' : '' }}">
                <span class="text-xl w-6 text-center">🏠</span>
                <span>仪表盘</span>
            </a>
            <a href="{{ route('skills.index') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'skills' ? 'active' : '' }}">
                <span class="text-xl w-6 text-center">📚</span>
                <span>技能队列</span>
            </a>
            <a href="{{ route('assets.index') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'assets' ? 'active' : '' }}">
                <span class="text-xl w-6 text-center">📦</span>
                <span>我的资产</span>
            </a>
            <a href="{{ route('wallet.index') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'wallet' ? 'active' : '' }}">
                <span class="text-xl w-6 text-center">💰</span>
                <span>钱包</span>
            </a>
            <a href="{{ route('contacts.index') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'contacts' ? 'active' : '' }}">
                <span class="text-xl w-6 text-center">📇</span>
                <span>联系人</span>
            </a>
            <a href="{{ route('contracts.index') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'contracts' ? 'active' : '' }}">
                <span class="text-xl w-6 text-center">📜</span>
                <span>合同</span>
            </a>
            <a href="{{ route('fittings.index') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'fittings' ? 'active' : '' }}">
                <span class="text-xl w-6 text-center">🔧</span>
                <span>装配</span>
            </a>
            <a href="{{ route('character-killmails.index') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'character-killmails' ? 'active' : '' }}">
                <span class="text-xl w-6 text-center">💀</span>
                <span>击毁报告</span>
            </a>
            <a href="{{ route('standings.index') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'standings' ? 'active' : '' }}">
                <span class="text-xl w-6 text-center">⭐</span>
                <span>声望</span>
            </a>
            <a href="{{ route('mail.index') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'mail' ? 'active' : '' }}">
                <span class="text-xl w-6 text-center">📧</span>
                <span>邮件</span>
            </a>
            <a href="{{ route('characters.index') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'characters' ? 'active' : '' }}">
                <span class="text-xl w-6 text-center">👥</span>
                <span>角色管理</span>
            </a>
            <a href="{{ route('fleet.index') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'fleet' ? 'active' : '' }}">
                <span class="text-xl w-6 text-center">⚓</span>
                <span>舰队管理</span>
            </a>

            <div class="border-t border-slate-700/50 my-4"></div>
            @endif

            <!-- 第二分类：工具栏（所有用户可见） -->
            <p class="text-xs text-slate-500 uppercase tracking-wider px-4 py-2">工具栏</p>
            <a href="{{ route('market.index') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'market' ? 'active' : '' }}">
                <span class="text-xl w-6 text-center">📊</span>
                <span>市场中心</span>
            </a>
            <a href="{{ route('killmails.index') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'killmails' ? 'active' : '' }}">
                <span class="text-xl w-6 text-center">⚔️</span>
                <span>KM查询</span>
            </a>
            <a href="{{ route('capital-nav.index') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'capital-nav' ? 'active' : '' }}">
                <span class="text-xl w-6 text-center">📍</span>
                <span>旗舰导航</span>
            </a>
            <a href="{{ route('lp-store.index') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'lp-store' ? 'active' : '' }}">
                <span class="text-xl w-6 text-center">💎</span>
                <span>LP商店</span>
            </a>
            <a href="{{ route('wormhole.index') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'wormhole' ? 'active' : '' }}">
                <span class="text-xl w-6 text-center">🕳️</span>
                <span>虫洞查询</span>
            </a>
            <a href="{{ route('scout.index') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'scout' ? 'active' : '' }}">
                <span class="text-xl w-6 text-center">🔭</span>
                <span>斥候工具</span>
            </a>
        </nav>

        <!-- 用户信息（已登录时显示） -->
        @if($isLoggedIn ?? false)
        <div class="p-4 border-t border-slate-700/50 bg-slate-900/30">
            <div class="flex items-center space-x-3">
                <div class="relative">
                    <img src="https://image.evepc.163.com/Character/{{ $user->eve_character_id ?? 0 }}_64.jpg" 
                         alt="头像" class="w-11 h-11 rounded-full ring-2 ring-blue-500/30">
                    <div class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-green-500 rounded-full border-2 border-slate-900"></div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-medium truncate">{{ $user->name ?? '飞行员' }}</p>
                    @if(\App\Http\Middleware\EnsureSiteAdmin::isSiteAdmin($user))
                        <a href="{{ route('admin.dashboard') }}" class="text-xs text-blue-400 hover:text-blue-300">站点管理员 👑</a>
                    @else
                        <p class="text-xs text-blue-400">EVE 飞行员</p>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </aside>

    <!-- 主内容区 -->
    <main class="flex-1 admin-content min-w-0">
        <!-- 顶部栏 -->
        <header class="relative z-40 bg-slate-900/60 backdrop-blur-xl border-b border-slate-700/50 px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <h2 class="text-xl font-semibold bg-gradient-to-r from-white to-slate-400 bg-clip-text text-transparent">@yield('page-title', '仪表盘')</h2>
                </div>
                <div class="flex items-center space-x-6">
                    <div class="flex items-center space-x-2 text-sm text-slate-400">
                        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                        <span>系统正常</span>
                    </div>
                    <span class="text-sm text-slate-500">{{ now()->format('Y-m-d H:i') }}</span>
                    
                    <!-- 提醒图标 -->
                    @if($isLoggedIn ?? false)
                    <div class="relative" id="notification-bell">
                        <button onclick="toggleNotifications()" class="relative text-slate-400 hover:text-white transition-colors p-1">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                            <!-- 未读计数红点 -->
                            <span id="notification-count" class="hidden absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center">0</span>
                        </button>
                        
                        <!-- 下拉通知面板 -->
                        <div id="notification-panel" class="hidden absolute right-0 top-full mt-2 w-80 max-h-96 overflow-y-auto bg-slate-800/95 backdrop-blur-xl border border-slate-600/50 rounded-xl shadow-xl z-50">
                            <div class="p-3 border-b border-slate-700/50 flex justify-between items-center">
                                <span class="font-medium text-sm">通知提醒</span>
                                <a href="{{ route('notifications.index') }}" class="text-xs text-blue-400 hover:text-blue-300">查看全部</a>
                            </div>
                            <div id="notification-list" class="p-2">
                                <div class="text-center text-sm text-slate-500 py-4">加载中...</div>
                            </div>
                        </div>
                    </div>
                    @endif
                    
                    @if($isLoggedIn ?? false)
                    <form action="{{ route('auth.logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="text-sm text-red-400 hover:text-red-300 transition-colors flex items-center space-x-1">
                            <span>退出</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </header>

        <!-- 页面内容 -->
        <div class="p-6 relative z-10">
            @yield('content')
        </div>
    </main>

    @if($isLoggedIn ?? false)
    <script>
    let notificationsLoaded = false;

    function toggleNotifications() {
        const panel = document.getElementById('notification-panel');
        panel.classList.toggle('hidden');
        if (!notificationsLoaded) {
            loadNotifications();
        }
    }

    // 点击外部关闭
    document.addEventListener('click', function(e) {
        const bell = document.getElementById('notification-bell');
        if (bell && !bell.contains(e.target)) {
            document.getElementById('notification-panel').classList.add('hidden');
        }
    });

    // 简化的 YAML 解析（支持 anchor 引用）
    function parseYamlText(text) {
        if (!text) return {};
        var result = {};
        var anchors = {};
        var lines = text.split('\n');
        lines.forEach(function(line) {
            line = line.trim();
            if (!line || line.startsWith('#')) return;
            var colonIndex = line.indexOf(':');
            if (colonIndex > 0) {
                var key = line.substring(0, colonIndex).trim();
                var value = line.substring(colonIndex + 1).trim();
                // 处理 YAML anchor 定义：&id001 60005401
                var anchorMatch = value.match(/^&(\w+)\s+(.+)$/);
                if (anchorMatch) {
                    anchors[anchorMatch[1]] = anchorMatch[2].trim();
                    value = anchorMatch[2].trim();
                }
                // 处理 YAML anchor 引用：*id001
                var refMatch = value.match(/^\*(\w+)$/);
                if (refMatch) {
                    value = anchors[refMatch[1]] || value;
                }
                // 移除引号
                if ((value.startsWith('"') && value.endsWith('"')) || 
                    (value.startsWith("'") && value.endsWith("'"))) {
                    value = value.substring(1, value.length - 1);
                }
                result[key] = value;
            }
        });
        return result;
    }

    // 格式化 ISK 金额（简化版）
    function formatIskShort(value) {
        var num = parseFloat(value);
        if (isNaN(num)) return value;
        if (num >= 1000000000) return (num / 1000000000).toFixed(1) + 'B ISK';
        if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M ISK';
        if (num >= 1000) return (num / 1000).toFixed(1) + 'K ISK';
        return num.toLocaleString() + ' ISK';
    }

    // 获取通知预览文本
    function getNotificationPreview(notif) {
        var type = notif.type || '';
        var text = notif.text || '';
        var parsed = parseYamlText(text);
        
        // 克隆体激活
        if (type === 'CloneActivationMsg1' || type === 'CloneActivationMsg2') {
            return '🧬 克隆体已激活';
        }
        
        // 跳跃克隆删除
        if (type === 'JumpCloneDeletedMsg1' || type === 'JumpCloneDeletedMsg2') {
            return '⚠️ 跳跃克隆已删除';
        }
        
        // 击毁报告
        if (type === 'KillReportVictim') {
            return '💀 你被击毁了' + (parsed.killMailID ? ' #' + parsed.killMailID : '');
        }
        if (type === 'KillReportFinalBlow') {
            return '⚔️ 最后一击' + (parsed.killMailID ? ' #' + parsed.killMailID : '');
        }
        if (type.toLowerCase().indexOf('kill') >= 0 && parsed.killMailID) {
            return '💀 击毁报告 #' + parsed.killMailID;
        }
        
        // 赏金
        if (type.toLowerCase().indexOf('bounty') >= 0) {
            var amount = parsed.amount || parsed.bounty || parsed.payout;
            return '💰 赏金' + (amount ? ' ' + formatIskShort(amount) : '');
        }
        
        // 保险赔付
        if (type === 'InsurancePayoutMsg') {
            var payout = parsed.amount || parsed.payout;
            return '🛡️ 保险赔付' + (payout ? ' ' + formatIskShort(payout) : '');
        }
        if (type.toLowerCase().indexOf('insurance') >= 0) {
            return '🛡️ 保险通知';
        }
        
        // 军团申请
        if (type === 'CorpAppNewMsg') {
            return '📝 新的军团申请';
        }
        if (type.toLowerCase().indexOf('corpapp') >= 0) {
            return '📝 军团申请';
        }
        
        // 战争
        if (type === 'WarDeclared' || type.toLowerCase().indexOf('wardeclared') >= 0) {
            return '⚔️ 战争已宣布';
        }
        if (type === 'WarInvalid' || type.toLowerCase().indexOf('warinvalidated') >= 0) {
            return '🕊️ 战争已失效';
        }
        if (type.toLowerCase().indexOf('war') >= 0) {
            return '⚔️ 战争通知';
        }
        
        // 建筑
        if (type === 'StructureUnderAttack') {
            return '🚨 建筑被攻击！';
        }
        if (type.toLowerCase().indexOf('structure') >= 0) {
            return '🏗️ 建筑通知';
        }
        
        // 月矿
        if (type.toLowerCase().indexOf('moon') >= 0) {
            return '🌙 月矿通知';
        }
        
        // 主权
        if (type.toLowerCase().indexOf('sov') >= 0) {
            return '🏛️ 主权通知';
        }
        
        // 默认：显示类型名
        return notif.type_name || type || '通知';
    }

    function loadNotifications() {
        fetch('/api/dashboard/notifications/summary')
            .then(r => r.json())
            .then(data => {
                notificationsLoaded = true;
                if (data.error) {
                    document.getElementById('notification-list').innerHTML = '<div class="text-center text-sm text-red-400 py-4">加载失败</div>';
                    return;
                }

                // 更新未读数
                const unread = data.unread || 0;
                const countEl = document.getElementById('notification-count');
                if (unread > 0) {
                    countEl.textContent = unread > 99 ? '99+' : unread;
                    countEl.classList.remove('hidden');
                }

                // 渲染最近通知（只显示类型名+时间）
                const items = data.items || [];
                if (items.length === 0) {
                    document.getElementById('notification-list').innerHTML = '<div class="text-center text-sm text-slate-500 py-4">暂无通知</div>';
                    return;
                }

                let html = '';
                items.forEach(n => {
                    const isUnread = !n.is_read;
                    html += '<div class="p-2 rounded-lg hover:bg-slate-700/50 transition-colors ' + (isUnread ? 'bg-blue-500/5' : '') + '">';
                    html += '<div class="flex items-start gap-2">';
                    if (isUnread) html += '<div class="w-2 h-2 mt-1.5 bg-blue-500 rounded-full shrink-0"></div>';
                    else html += '<div class="w-2 h-2 mt-1.5 shrink-0"></div>';
                    html += '<div class="flex-1 min-w-0">';
                    html += '<div class="text-sm font-medium truncate">' + (n.type_name || '通知') + '</div>';
                    html += '<div class="text-xs text-slate-500 mt-1">' + formatTime(n.timestamp) + '</div>';
                    html += '</div></div></div>';
                });
                document.getElementById('notification-list').innerHTML = html;
            })
            .catch(() => {
                notificationsLoaded = true;
                document.getElementById('notification-list').innerHTML = '<div class="text-center text-sm text-red-400 py-4">加载失败，点击重试</div>';
            });
    }

    function formatTime(ts) {
        if (!ts) return '';
        const d = new Date(ts);
        return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0') + ' ' + String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
    }

    // 页面加载后检查未读数
    setTimeout(() => {
        fetch('/api/dashboard/notifications/summary')
            .then(r => r.json())
            .then(data => {
                if (data && !data.error) {
                    const unread = data.unread || 0;
                    const countEl = document.getElementById('notification-count');
                    if (unread > 0) {
                        countEl.textContent = unread > 99 ? '99+' : unread;
                        countEl.classList.remove('hidden');
                    }
                    notificationsLoaded = true;
                }
            }).catch(() => {});
    }, 2000);
    </script>
    @endif

    @stack('scripts')
</body>
</html>
