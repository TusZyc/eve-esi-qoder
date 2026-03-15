<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Tus Esi System')</title>
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
        
        @stack('styles')
    </style>
</head>
<body class="eve-bg min-h-screen text-white">
    <!-- 导航栏 -->
    @include('layouts.partials.navbar', ['isLoggedIn' => $isLoggedIn ?? false])
    
    <!-- 页面内容区域 -->
    <div class="container mx-auto px-4 py-8">
        @yield('content')
    </div>
    
    @stack('scripts')
</body>
</html>
