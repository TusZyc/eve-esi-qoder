<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>角色管理 - EVE ESI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .eve-bg {
            background: linear-gradient(135deg, #0c1445 0%, #1a237e 50%, #283593 100%);
        }
        .eve-glow {
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.5);
        }
    </style>
</head>
<body class="eve-bg min-h-screen text-white">
    <!-- 导航栏 -->
    <nav class="bg-white/10 backdrop-blur-lg border-b border-white/20">
        <div class="container mx-auto px-4 py-2">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <span class="text-2xl">🚀</span>
                    <div>
                        <a href="{{ route('dashboard') }}" class="text-xl font-bold">EVE ESI</a>
                        <span class="text-sm text-blue-200 ml-3">欢迎，{{ $user->name }}</span>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <a href="{{ route('dashboard') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all" title="仪表盘"><div class="text-2xl">🏠</div></a>
                    <a href="{{ route('skills.index') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all" title="技能队列"><div class="text-2xl">📚</div></a>
                    <a href="{{ route('assets.index') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all" title="我的资产"><div class="text-2xl">📦</div></a>
                    <a href="{{ route('characters.index') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all bg-white/10" title="角色管理"><div class="text-2xl">👥</div></a>
                    <a href="{{ route('killmails.index') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all" title="KM 查询"><div class="text-2xl">⚔️</div></a>
                    <form action="{{ route('auth.logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg text-sm ml-2">
                            登出
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold mb-2">👥 角色管理</h1>
            <p class="text-blue-300">管理你的 EVE 角色</p>
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

        <!-- 当前角色 -->
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow">
            <h2 class="text-xl font-semibold mb-4">当前角色</h2>
            <div class="bg-white/5 rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-blue-400">{{ $user->name }}</div>
                        <div class="text-sm text-blue-300 mt-2">
                            角色 ID：{{ $user->eve_character_id }}
                        </div>
                        @if($user->corporation_id)
                        <div class="text-sm text-blue-300 mt-1">
                            军团 ID：{{ $user->corporation_id }}
                        </div>
                        @endif
                        @if($user->alliance_id)
                        <div class="text-sm text-blue-300 mt-1">
                            联盟 ID：{{ $user->alliance_id }}
                        </div>
                        @endif
                        <div class="text-sm text-blue-300 mt-1">
                            Token 过期：{{ $user->token_expires_at ? \Carbon\Carbon::parse($user->token_expires_at)->format('Y-m-d H:i:s') : '未知' }}
                        </div>
                    </div>
                    <div class="flex space-x-2">
                        <a href="{{ route('characters.show', $user->eve_character_id) }}" 
                           class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg text-sm">
                            查看详情
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
