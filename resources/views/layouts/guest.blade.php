<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Tus Esi System')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .eve-bg {
            background: linear-gradient(135deg, #0c1445 0%, #1a237e 50%, #283593 100%);
        }
        .eve-glow {
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.5);
        }
        .status-dot {
            width: 12px; height: 12px; border-radius: 50%;
            display: inline-block; margin-right: 6px;
        }
        .status-dot.online { background-color: #22c55e; box-shadow: 0 0 10px #22c55e; }
        .status-dot.maintenance { background-color: #eab308; box-shadow: 0 0 10px #eab308; }
        .status-dot.offline { background-color: #ef4444; box-shadow: 0 0 10px #ef4444; }

        /* 侧边栏样式 - 与 admin 完全一致 */
        .admin-sidebar {
            background: linear-gradient(180deg, #1a1f2e 0%, #0d1117 100%);
            box-shadow: 4px 0 24px rgba(0, 0, 0, 0.3);
            position: relative;
        }
        .admin-sidebar::before {
            content: '';
            position: absolute;
            top: 0; right: 0;
            width: 1px; height: 100%;
            background: linear-gradient(180deg, rgba(59,130,246,0.3) 0%, rgba(139,92,246,0.1) 50%, transparent 100%);
        }
        .admin-content {
            background: linear-gradient(135deg, #0a0f1a 0%, #111827 50%, #0f172a 100%);
            position: relative;
        }
        .admin-content::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: radial-gradient(ellipse at top right, rgba(59,130,246,0.08) 0%, transparent 50%),
                        radial-gradient(ellipse at bottom left, rgba(139,92,246,0.05) 0%, transparent 50%);
            pointer-events: none;
        }
        .sidebar-link {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-left: 3px solid transparent;
            position: relative;
            overflow: hidden;
        }
        .sidebar-link::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(59,130,246,0.1), transparent);
            transition: left 0.5s ease;
        }
        .sidebar-link:hover::before { left: 100%; }
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
            right: 12px; top: 50%;
            transform: translateY(-50%);
            width: 6px; height: 6px;
            background: #3b82f6;
            border-radius: 50%;
            box-shadow: 0 0 10px #3b82f6;
        }
        .stat-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow: 0 8px 32px rgba(0,0,0,0.3), inset 0 1px 0 rgba(255,255,255,0.05);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative; overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
        }
        .stat-card:hover {
            background: linear-gradient(135deg, rgba(255,255,255,0.08) 0%, rgba(255,255,255,0.04) 100%);
            border-color: rgba(59,130,246,0.3);
            transform: translateY(-2px);
        }
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
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            box-shadow: 0 4px 15px rgba(59,130,246,0.3);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            box-shadow: 0 6px 20px rgba(59,130,246,0.4);
            transform: translateY(-1px);
        }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: rgba(255,255,255,0.02); border-radius: 4px; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, rgba(59,130,246,0.3) 0%, rgba(139,92,246,0.3) 100%); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: linear-gradient(180deg, rgba(59,130,246,0.5) 0%, rgba(139,92,246,0.5) 100%); }
        input, select { transition: all 0.3s ease; }
        input:focus, select:focus { box-shadow: 0 0 0 3px rgba(59,130,246,0.2); }

        @stack('styles')
    </style>
    @stack('head-scripts')
</head>
<body class="bg-slate-900 text-white min-h-screen flex">
    <!-- 左侧边栏 -->
    <aside class="admin-sidebar w-64 min-h-screen flex flex-col">
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
            <p class="text-xs text-slate-500 uppercase tracking-wider px-4 py-2">导航</p>
            <a href="{{ route('guest.dashboard') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'dashboard' ? 'active' : '' }}">
                <span class="text-xl w-6 text-center">🏠</span>
                <span>仪表盘</span>
            </a>

            <div class="border-t border-slate-700/50 my-4"></div>
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

            <!-- 登录提示 -->
            <div class="border-t border-slate-700/50 my-4"></div>
            <a href="{{ route('auth.guide') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg text-blue-400 hover:text-white">
                <span class="text-xl w-6 text-center">🔑</span>
                <span>EVE SSO 登录</span>
            </a>
        </nav>

        <!-- 游客信息 -->
        <div class="p-4 border-t border-slate-700/50 bg-slate-900/30">
            <div class="flex items-center space-x-3">
                <div class="w-11 h-11 rounded-xl bg-slate-700/50 flex items-center justify-center">
                    <span class="text-xl">👤</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-medium truncate text-slate-400">未登录</p>
                    <p class="text-xs text-slate-500">游客模式</p>
                </div>
            </div>
        </div>
    </aside>

    <!-- 主内容区 -->
    <main class="flex-1 admin-content">
        <header class="relative bg-slate-900/60 backdrop-blur-xl border-b border-slate-700/50 px-6 py-4">
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
                    <a href="{{ route('auth.guide') }}" class="text-sm text-blue-400 hover:text-blue-300 transition-colors">登录</a>
                </div>
            </div>
        </header>
        <div class="p-6 relative z-10">
            @yield('content')
        </div>
    </main>

    @stack('scripts')
</body>
</html>
