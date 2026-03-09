<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>技能队列 - EVE ESI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .eve-bg {
            background: linear-gradient(135deg, #0c1445 0%, #1a237e 50%, #283593 100%);
        }
        .eve-glow {
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.5);
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        details > summary {
            list-style: none;
        }
        details > summary::-webkit-details-marker {
            display: none;
        }
        details[open] summary ~ * {
            animation: sweep .3s ease-in-out;
        }
        @keyframes sweep {
            0%    {opacity: 0; transform: translateY(-10px)}
            100%  {opacity: 1; transform: translateY(0)}
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
                    <a href="{{ route('dashboard') }}" class="text-blue-200 hover:text-white">仪表盘</a>
                    <a href="{{ route('skills.index') }}" class="text-white font-semibold">技能队列</a>
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
        <!-- 技能点统计 -->
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 mb-6 eve-glow">
            <h2 class="text-2xl font-semibold mb-6">📚 技能信息</h2>
            <div class="grid md:grid-cols-3 gap-6">
                <div class="text-center">
                    <div class="text-4xl font-bold text-yellow-400 mb-2">
                        {{ number_format($totalSP) }}
                    </div>
                    <div class="text-blue-200">总技能点</div>
                </div>
                <div class="text-center">
                    <div class="text-4xl font-bold text-green-400 mb-2">
                        {{ number_format($unallocatedSP) }}
                    </div>
                    <div class="text-blue-200">未分配技能点</div>
                </div>
                <div class="text-center">
                    <div class="text-4xl font-bold text-blue-400 mb-2">
                        @php
                            $hours = floor($trainingTimeRemaining / 3600);
                            $days = floor($hours / 24);
                            $remainingHours = $hours % 24;
                        @endphp
                        {{ $days }}天{{ $remainingHours }}小时
                    </div>
                    <div class="text-blue-200">训练剩余时间</div>
                </div>
            </div>
        </div>

        <!-- 技能队列 -->
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 mb-6 eve-glow">
            <h2 class="text-2xl font-semibold mb-6">⏳ 技能队列</h2>
            
            @if(empty($skillQueue))
                <div class="text-center py-12">
                    <div class="text-6xl mb-4">😴</div>
                    <p class="text-xl text-blue-200">技能队列为空</p>
                    <p class="text-sm text-blue-300 mt-2">您目前没有正在训练的技能</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($skillQueue as $index => $queueItem)
                        <div class="bg-white/5 rounded-lg p-4 eve-glow">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <div class="flex items-center space-x-2">
                                        <span class="bg-blue-600 text-xs px-2 py-1 rounded">
                                            #{{ $index + 1 }}
                                        </span>
                                        <span class="text-lg font-semibold">
                                            {{ $queueItem['skill_name'] ?? '未知技能' }}
                                        </span>
                                    </div>
                                    <div class="text-sm text-blue-300 mt-1">
                                        等级：{{ $queueItem['finished_level'] ?? 0 }} / 5
                                    </div>
                                </div>
                                <div class="text-right">
                                    @php
                                        $startDate = isset($queueItem['start_date']) ? strtotime($queueItem['start_date']) : time();
                                        $finishDate = isset($queueItem['finish_date']) ? strtotime($queueItem['finish_date']) : time();
                                        $timeRemaining = max(0, $finishDate - time());
                                        $elapsed = time() - $startDate;
                                        $total = max(1, $finishDate - $startDate);
                                        $progress = min(100, ($elapsed / $total) * 100);
                                    @endphp
                                    
                                    @if($timeRemaining > 0)
                                        <div class="text-green-400 font-semibold">
                                            进行中
                                        </div>
                                        <div class="text-sm text-blue-300 mt-1">
                                            剩余：{{ gmdate('d\天 H\时 i\分', $timeRemaining) }}
                                        </div>
                                        <div class="text-xs text-blue-400 mt-1">
                                            完成时间：{{ date('Y-m-d H:i', $finishDate) }}
                                        </div>
                                    @else
                                        <div class="text-yellow-400 font-semibold">
                                            已完成
                                        </div>
                                        <div class="text-sm text-blue-300 mt-1">
                                            {{ date('Y-m-d H:i', $finishDate) }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                            
                            <!-- 进度条 -->
                            <div class="relative">
                                <div class="w-full bg-white/10 rounded-full h-4 overflow-hidden">
                                    <div class="bg-gradient-to-r from-blue-500 to-green-500 h-4 rounded-full transition-all duration-1000" 
                                         style="width: {{ $progress }}%"></div>
                                </div>
                                <div class="absolute right-0 top-0 text-xs text-blue-300 mt-1">
                                    {{ number_format($progress, 1) }}%
                                </div>
                            </div>
                            
                            <!-- 详细信息 -->
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 text-sm">
                                <div>
                                    <span class="text-blue-300">开始时间：</span>
                                    <span class="text-white">{{ date('Y-m-d H:i', $startDate) }}</span>
                                </div>
                                <div>
                                    <span class="text-blue-300">预计完成：</span>
                                    <span class="text-white">{{ date('Y-m-d H:i', $finishDate) }}</span>
                                </div>
                                <div>
                                    <span class="text-blue-300">已训练：</span>
                                    <span class="text-green-400">{{ gmdate('d\天 H\时 i\分', $elapsed) }}</span>
                                </div>
                                <div>
                                    <span class="text-blue-300">剩余时间：</span>
                                    <span class="text-yellow-400">{{ gmdate('d\天 H\时 i\分', $timeRemaining) }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- 已学技能（按分组展示） -->
        @if(isset($skillsData['skills_by_group']) && !empty($skillsData['skills_by_group']))
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 mb-6 eve-glow">
            <h2 class="text-2xl font-semibold mb-6">📖 已学技能（按分类展示）</h2>
            
            <div class="space-y-3">
                @foreach($skillsData['skills_by_group'] as $group)
                    <details class="bg-white/5 rounded-lg overflow-hidden">
                        <summary class="px-4 py-3 bg-white/10 cursor-pointer hover:bg-white/20 transition-colors flex justify-between items-center">
                            <div class="flex items-center space-x-3">
                                <span class="text-lg font-semibold">📚 {{ $group['group_name'] }}</span>
                                <span class="text-sm text-blue-300">({{ count($group['skills']) }} 个技能)</span>
                            </div>
                            <span class="text-blue-400 transform transition-transform duration-200">▼</span>
                        </summary>
                        <div class="p-4">
                            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach($group['skills'] as $skill)
                                    <div class="bg-white/5 rounded-lg p-3 hover:bg-white/10 transition-colors">
                                        <div class="mb-2">
                                            <div class="font-semibold text-white text-sm">
                                                {{ $skill['skill_name'] ?? '未知技能' }}
                                            </div>
                                            <div class="text-xs text-blue-400 mt-1">
                                                等级 {{ $skill['active_level'] ?? 0 }} / 5
                                            </div>
                                        </div>
                                        <div class="flex justify-between items-center text-xs">
                                            <span class="text-blue-300">
                                                {{ number_format($skill['skill_points_in_skill'] ?? $skill['trained_skill_points'] ?? 0) }} SP
                                            </span>
                                            @php $level = $skill['active_level'] ?? $skill['trained_level'] ?? 0; @endphp
                                            @if($level == 5)
                                                <span class="text-green-400">✅ 满级</span>
                                            @endif
                                        </div>
                                        <!-- 技能等级进度条 -->
                                        @php
                                            $spForLevel = [0, 0, 250, 1414, 8000, 45255];
                                            $currentLevel = $skill['active_level'] ?? $skill['trained_level'] ?? 0;
                                            $currentSP = $skill['skill_points_in_skill'] ?? $skill['trained_skill_points'] ?? 0;
                                            $nextLevelSP = $spForLevel[$currentLevel + 1] ?? 45255;
                                            $prevLevelSP = $spForLevel[$currentLevel] ?? 0;
                                            $denominator = $nextLevelSP - $prevLevelSP;
                                            $progress = $currentLevel >= 5 ? 100 : ($denominator > 0 ? (($currentSP - $prevLevelSP) / $denominator) * 100 : 0);
                                        @endphp
                                        <div class="mt-2 w-full bg-white/10 rounded-full h-1.5 overflow-hidden">
                                            <div class="bg-blue-500 h-1.5 rounded-full" style="width: {{ min(100, max(0, $progress)) }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </details>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    <!-- 自动刷新 -->
    <script>
        // 每 60 秒自动刷新页面
        setTimeout(function() {
            location.reload();
        }, 60000);
        
        // 为 details 添加展开/收起动画
        document.querySelectorAll('details').forEach(detail => {
            detail.addEventListener('toggle', () => {
                const summary = detail.querySelector('summary');
                const arrow = summary.querySelector('span:last-child');
                if (detail.open) {
                    arrow.style.transform = 'rotate(180deg)';
                } else {
                    arrow.style.transform = 'rotate(0deg)';
                }
            });
        });
    </script>
</body>
</html>
