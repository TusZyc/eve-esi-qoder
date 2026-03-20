<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', '站点管理') - Tus Esi System</title>
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
        }
        .stat-card:hover {
            background: linear-gradient(135deg, rgba(255,255,255,0.08) 0%, rgba(255,255,255,0.04) 100%);
            border-color: rgba(59,130,246,0.3);
            box-shadow: 0 12px 40px rgba(0,0,0,0.4),
                        0 0 0 1px rgba(59,130,246,0.1),
                        inset 0 1px 0 rgba(255,255,255,0.08);
            transform: translateY(-2px);
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
        
        @stack('styles')
    </style>
</head>
<body class="bg-slate-900 text-white min-h-screen flex">
    <!-- 左侧边栏 - flex-shrink-0 防止被压缩 -->
    <aside class="admin-sidebar w-64 min-w-[256px] flex-shrink-0 min-h-screen flex flex-col">
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
        <nav class="flex-1 p-4 space-y-1">
            <p class="text-xs text-slate-500 uppercase tracking-wider px-4 py-2">主菜单</p>
            <a href="{{ route('admin.dashboard') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'dashboard' ? 'active' : '' }}">
                <span class="text-xl w-6 text-center">📊</span>
                <span>管理首页</span>
            </a>
            <a href="{{ route('admin.users') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'users' ? 'active' : '' }}">
                <span class="text-xl w-6 text-center">👥</span>
                <span>用户管理</span>
            </a>
            <a href="{{ route('admin.logs') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'logs' ? 'active' : '' }}">
                <span class="text-xl w-6 text-center">📜</span>
                <span>系统日志</span>
            </a>
            <a href="{{ route('admin.api-stats') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'api-stats' ? 'active' : '' }}">
                <span class="text-xl w-6 text-center">📈</span>
                <span>API 统计</span>
            </a>
            <a href="{{ route('admin.cache') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'cache' ? 'active' : '' }}">
                <span class="text-xl w-6 text-center">💾</span>
                <span>缓存管理</span>
            </a>
            <a href="{{ route('admin.data') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'data' ? 'active' : '' }}">
                <span class="text-xl w-6 text-center">📦</span>
                <span>数据管理</span>
            </a>

            <div class="border-t border-slate-700/50 my-4"></div>
            <p class="text-xs text-slate-500 uppercase tracking-wider px-4 py-2">快捷操作</p>
            <a href="{{ route('dashboard') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                <span class="text-xl w-6 text-center">🏠</span>
                <span>返回仪表盘</span>
            </a>
        </nav>

        <!-- 用户信息 -->
        <div class="p-4 border-t border-slate-700/50 bg-slate-900/30">
            <div class="flex items-center space-x-3">
                <div class="relative">
                    <img src="https://image.evepc.163.com/Character/{{ $user->eve_character_id ?? 0 }}_64.jpg" 
                         alt="头像" class="w-11 h-11 rounded-full ring-2 ring-blue-500/30">
                    <div class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-green-500 rounded-full border-2 border-slate-900"></div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-medium truncate">{{ $user->name ?? '管理员' }}</p>
                    <p class="text-xs text-blue-400">站点管理员</p>
                </div>
            </div>
        </div>
    </aside>

    <!-- 主内容区 - min-w-0 和 overflow-x-hidden 防止内容撑开 -->
    <main class="flex-1 min-w-0 overflow-x-hidden admin-content">
        <!-- 顶部栏 -->
        <header class="relative bg-slate-900/60 backdrop-blur-xl border-b border-slate-700/50 px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <h2 class="text-xl font-semibold bg-gradient-to-r from-white to-slate-400 bg-clip-text text-transparent">@yield('page-title', '管理首页')</h2>
                </div>
                <div class="flex items-center space-x-6">
                    <div class="flex items-center space-x-2 text-sm text-slate-400">
                        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                        <span>系统正常</span>
                    </div>
                    <span class="text-sm text-slate-500">{{ now()->format('Y-m-d H:i') }}</span>
                    <form action="{{ route('auth.logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="text-sm text-red-400 hover:text-red-300 transition-colors flex items-center space-x-1">
                            <span>退出</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <!-- 页面内容 -->
        <div class="p-6 relative z-10">
            @yield('content')
        </div>
    </main>

    @stack('scripts')
</body>
</html>
