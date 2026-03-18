<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', '站点管理') - Tus Esi System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .admin-sidebar {
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
        }
        .admin-content {
            background: linear-gradient(135deg, #0c1445 0%, #1a237e 50%, #283593 100%);
        }
        .sidebar-link {
            transition: all 0.2s ease;
        }
        .sidebar-link:hover {
            background: rgba(59, 130, 246, 0.2);
        }
        .sidebar-link.active {
            background: rgba(59, 130, 246, 0.3);
            border-left: 3px solid #3b82f6;
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .stat-card:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(59, 130, 246, 0.3);
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
        .log-error { color: #f87171; }
        .log-warning { color: #fbbf24; }
        .log-info { color: #60a5fa; }
        .log-debug { color: #9ca3af; }
        @stack('styles')
    </style>
</head>
<body class="bg-slate-900 text-white min-h-screen flex">
    <!-- 左侧边栏 -->
    <aside class="admin-sidebar w-64 min-h-screen flex flex-col">
        <!-- Logo 区域 -->
        <div class="p-4 border-b border-slate-700">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-lg bg-blue-600 flex items-center justify-center">
                    <span class="text-xl">⚙️</span>
                </div>
                <div>
                    <h1 class="font-bold text-lg">站点管理</h1>
                    <p class="text-xs text-slate-400">Site Admin</p>
                </div>
            </div>
        </div>

        <!-- 导航菜单 -->
        <nav class="flex-1 p-4 space-y-2">
            <a href="{{ route('admin.dashboard') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'dashboard' ? 'active' : '' }}">
                <span class="text-xl">📊</span>
                <span>管理首页</span>
            </a>
            <a href="{{ route('admin.users') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'users' ? 'active' : '' }}">
                <span class="text-xl">👥</span>
                <span>用户管理</span>
            </a>
            <a href="{{ route('admin.logs') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'logs' ? 'active' : '' }}">
                <span class="text-xl">📜</span>
                <span>系统日志</span>
            </a>
            <a href="{{ route('admin.api-stats') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg {{ ($activePage ?? '') === 'api-stats' ? 'active' : '' }}">
                <span class="text-xl">📈</span>
                <span>API 统计</span>
            </a>

            <div class="border-t border-slate-700 my-4"></div>

            <a href="{{ route('dashboard') }}" 
               class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                <span class="text-xl">🏠</span>
                <span>返回仪表盘</span>
            </a>
        </nav>

        <!-- 用户信息 -->
        <div class="p-4 border-t border-slate-700">
            <div class="flex items-center space-x-3">
                <img src="https://image.evepc.163.com/Character/{{ $user->eve_character_id ?? 0 }}_64.jpg" 
                     alt="头像" class="w-10 h-10 rounded-full">
                <div class="flex-1 min-w-0">
                    <p class="font-medium truncate">{{ $user->name ?? '管理员' }}</p>
                    <p class="text-xs text-slate-400">站点管理员</p>
                </div>
            </div>
        </div>
    </aside>

    <!-- 主内容区 -->
    <main class="flex-1 admin-content">
        <!-- 顶部栏 -->
        <header class="bg-slate-800/50 backdrop-blur border-b border-slate-700 px-6 py-4">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">@yield('page-title', '管理首页')</h2>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-slate-400">{{ now()->format('Y-m-d H:i') }}</span>
                    <form action="{{ route('auth.logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="text-sm text-red-400 hover:text-red-300">
                            退出登录
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <!-- 页面内容 -->
        <div class="p-6">
            @yield('content')
        </div>
    </main>

    @stack('scripts')
</body>
</html>
