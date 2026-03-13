<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>角色详情 - EVE ESI</title>
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
        <div class="mb-6">
            <a href="{{ route('characters.index') }}" class="text-blue-400 hover:text-blue-300">&larr; 返回角色列表</a>
        </div>

        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow">
            <h2 class="text-2xl font-semibold mb-6">👤 角色详情</h2>
            
            @if(!empty($character))
            <div class="grid md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    @if(isset($character['name']))
                    <div>
                        <div class="text-sm text-blue-300">角色名称</div>
                        <div class="text-xl font-bold text-blue-400">{{ $character['name'] }}</div>
                    </div>
                    @endif
                    @if(isset($character['birthday']))
                    <div>
                        <div class="text-sm text-blue-300">创建日期</div>
                        <div class="text-lg text-white">{{ $character['birthday_beijing'] ?? $character['birthday'] }} <span class="text-xs text-blue-300/60">北京时间</span></div>
                    </div>
                    @endif
                    @if(isset($character['gender']))
                    <div>
                        <div class="text-sm text-blue-300">性别</div>
                        <div class="text-lg text-white">{{ $character['gender'] === 'male' ? '男' : '女' }}</div>
                    </div>
                    @endif
                    @if(isset($character['security_status']))
                    <div>
                        <div class="text-sm text-blue-300">安全等级</div>
                        <div class="text-lg font-bold {{ $character['security_status'] >= 0 ? 'text-green-400' : 'text-red-400' }}">
                            {{ number_format($character['security_status'], 2) }}
                        </div>
                    </div>
                    @endif
                </div>
                <div class="space-y-4">
                    @if(isset($character['corporation_id']))
                    <div>
                        <div class="text-sm text-blue-300">军团</div>
                        <div class="text-lg text-purple-400">{{ $character['corporation_name'] ?? $character['corporation_id'] }}</div>
                    </div>
                    @endif
                    @if(isset($character['alliance_id']))
                    <div>
                        <div class="text-sm text-blue-300">联盟</div>
                        <div class="text-lg text-green-400">{{ $character['alliance_name'] ?? $character['alliance_id'] }}</div>
                    </div>
                    @endif
                    @if(isset($character['description']))
                    <div>
                        <div class="text-sm text-blue-300">角色描述</div>
                        <div class="text-sm text-white/80 mt-1">{!! $character['description_html'] ?? e($character['description']) !!}</div>
                    </div>
                    @endif
                </div>
            </div>
            @else
            <div class="text-center py-12">
                <div class="text-6xl mb-4">❓</div>
                <p class="text-blue-300">无法获取角色详情</p>
            </div>
            @endif
        </div>
    </div>
</body>
</html>
