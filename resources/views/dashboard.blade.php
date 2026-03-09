<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>仪表盘 - {{ config('app.name') }}</title>
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
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <a href="{{ route('dashboard') }}" class="text-xl font-bold">🚀 EVE ESI</a>
                <div class="flex items-center space-x-4">
                    <span class="text-blue-200">欢迎，{{ $user->name }}</span>
                    <form action="{{ route('auth.logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg text-sm">
                            登出
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <!-- 服务器状态 -->
        @if($serverStatus)
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 mb-6 eve-glow">
            <h2 class="text-xl font-semibold mb-4">📡 服务器状态</h2>
            <div class="grid md:grid-cols-3 gap-4">
                <div class="text-center">
                    <div class="text-3xl font-bold text-green-400">
                        {{ $serverStatus['players'] ?? 'N/A' }}
                    </div>
                    <div class="text-sm text-blue-200">在线玩家</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-blue-400">
                        {{ $serverStatus['server_version'] ?? 'N/A' }}
                    </div>
                    <div class="text-sm text-blue-200">服务器版本</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-purple-400">
                        {{ $serverStatus['vip'] ?? 'N/A' }}
                    </div>
                    <div class="text-sm text-blue-200">VIP</div>
                </div>
            </div>
        </div>
        @endif

        <!-- 角色信息 -->
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 mb-6 eve-glow">
            <h2 class="text-xl font-semibold mb-4">👤 角色信息</h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <div class="text-sm text-blue-200">角色名称</div>
                    <div class="text-lg">{{ $user->name }}</div>
                </div>
                <div>
                    <div class="text-sm text-blue-200">角色 ID</div>
                    <div class="text-lg">{{ $user->eve_character_id ?? '未绑定' }}</div>
                </div>
                <div>
                    <div class="text-sm text-blue-200">军团 ID</div>
                    <div class="text-lg">{{ $user->corporation_id ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-blue-200">联盟 ID</div>
                    <div class="text-lg">{{ $user->alliance_id ?? '-' }}</div>
                </div>
            </div>
        </div>

        <!-- 技能信息 -->
        @if($skillsData)
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 mb-6 eve-glow">
            <h2 class="text-xl font-semibold mb-4">📚 技能信息</h2>
            <div class="grid md:grid-cols-3 gap-4">
                <div class="text-center">
                    <div class="text-3xl font-bold text-yellow-400">
                        {{ $skillsData['total_sp'] ?? 0 }}
                    </div>
                    <div class="text-sm text-blue-200">总技能点</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-green-400">
                        {{ $skillsData['unallocated_sp'] ?? 0 }}
                    </div>
                    <div class="text-sm text-blue-200">未分配技能点</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-blue-400">
                        {{ count($skillsData['skills'] ?? []) }}
                    </div>
                    <div class="text-sm text-blue-200">已学技能数</div>
                </div>
            </div>
        </div>
        @endif

        <!-- 技能队列 -->
        @if($skillQueue && count($skillQueue) > 0)
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 mb-6 eve-glow">
            <h2 class="text-xl font-semibold mb-4">⏳ 技能队列</h2>
            <div class="space-y-3">
                @foreach(array_slice($skillQueue, 0, 5) as $index => $queueItem)
                <div class="bg-white/5 rounded-lg p-4">
                    <div class="flex justify-between items-center mb-2">
                        <span class="font-medium">
                            {{ $index + 1 }}. 技能 ID: {{ $queueItem['skill_id'] ?? 'N/A' }}
                        </span>
                        <span class="text-sm text-blue-200">
                            等级 {{ $queueItem['finished_level'] ?? 0 }}
                        </span>
                    </div>
                    <div class="w-full bg-white/10 rounded-full h-2">
                        @php
                            $progress = 0;
                            if (isset($queueItem['progress']) && isset($queueItem['completion'])) {
                                $progress = $queueItem['progress'] / $queueItem['completion'] * 100;
                            }
                        @endphp
                        <div class="bg-blue-500 h-2 rounded-full" style="width: {{ $progress }}%"></div>
                    </div>
                    <div class="text-xs text-blue-300 mt-1">
                        进度：{{ number_format($progress, 1) }}%
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- 快捷操作 -->
        <div class="grid md:grid-cols-3 gap-4">
            <a href="{{ route('characters.index') }}" 
               class="bg-white/10 backdrop-blur-lg rounded-xl p-6 text-center hover:bg-white/20 transition-all eve-glow">
                <div class="text-3xl mb-2">👥</div>
                <div class="font-semibold">角色管理</div>
            </a>
            <a href="{{ route('skills.queue') }}" 
               class="bg-white/10 backdrop-blur-lg rounded-xl p-6 text-center hover:bg-white/20 transition-all eve-glow">
                <div class="text-3xl mb-2">📖</div>
                <div class="font-semibold">完整技能队列</div>
            </a>
            <a href="{{ route('assets.index') }}" 
               class="bg-white/10 backdrop-blur-lg rounded-xl p-6 text-center hover:bg-white/20 transition-all eve-glow">
                <div class="text-3xl mb-2">📦</div>
                <div class="font-semibold">资产列表</div>
            </a>
        </div>
    </div>
</body>
</html>
