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
    <!-- 导航栏（与 dashboard 一致） -->
    <nav class="bg-white/10 backdrop-blur-lg border-b border-white/20">
        <div class="container mx-auto px-4 py-2">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <span class="text-2xl">🚀</span>
                    <div>
                        <a href="{{ route('dashboard') }}" class="text-xl font-bold">EVE ESI</a>
                        <span class="text-sm text-blue-200 ml-3">欢迎，{{ ->name }}</span>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <a href="{{ route('dashboard') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all" title="仪表盘">
                        <div class="text-2xl">🏠</div>
                    </a>
                    <a href="{{ route('skills.index') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all bg-white/10" title="技能队列">
                        <div class="text-2xl">📚</div>
                    </a>
                    <a href="{{ route('assets.index') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all" title="我的资产">
                        <div class="text-2xl">📦</div>
                    </a>
                    <a href="{{ route('characters.index') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all" title="角色管理">
                        <div class="text-2xl">👥</div>
                    </a>
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
        <!-- 技能点统计 -->
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 mb-6 eve-glow">
            <h2 class="text-2xl font-semibold mb-6">📚 技能信息</h2>
            <div class="grid md:grid-cols-3 gap-6">
                <div class="text-center">
                    <div class="text-4xl font-bold text-yellow-400 mb-2">
                        {{ number_format() }}
                    </div>
                    <div class="text-blue-200">总技能点</div>
                </div>
                <div class="text-center">
                    <div class="text-4xl font-bold text-green-400 mb-2">
                        {{ number_format() }}
                    </div>
                    <div class="text-blue-200">未分配技能点</div>
                </div>
                <div class="text-center">
                    <div class="text-4xl font-bold text-blue-400 mb-2">
                        @php
                             = floor( / 3600);
                             = floor( / 24);
                             =  % 24;
                        @endphp
                        {{  }}天{{  }}小时
                    </div>
                    <div class="text-blue-200">训练剩余时间</div>
                </div>
            </div>
        </div>

        <!-- 技能队列 -->
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 mb-6 eve-glow">
            <h2 class="text-2xl font-semibold mb-6">⏳ 技能队列</h2>
            
            @if(empty())
                <div class="text-center py-12">
                    <div class="text-6xl mb-4">😴</div>
                    <p class="text-xl text-blue-200">技能队列为空</p>
                    <p class="text-sm text-blue-300 mt-2">您目前没有正在训练的技能</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach( as  => )
                        <div class="bg-white/5 rounded-lg p-4 eve-glow">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <div class="flex items-center space-x-2">
                                        <span class="bg-blue-600 text-xs px-2 py-1 rounded">
                                            #{{  + 1 }}
                                        </span>
                                        <span class="text-lg font-semibold">
                                            {{ ['skill_name'] ?? '未知技能' }}
                                        </span>
                                    </div>
                                    <div class="text-sm text-blue-300 mt-1">
                                        等级：{{ ['finished_level'] ?? 0 }} / 5
                                    </div>
                                </div>
                                <div class="text-right">
                                    @php
                                         = time();
                                         = isset(['start_date']) ? strtotime(['start_date']) : ;
                                         = isset(['finish_date']) ? strtotime(['finish_date']) : ;
                                         = max(1,  - );
                                        
                                        if ( < ) {
                                             = 0;
                                             =  - ;
                                             = 'waiting';
                                        } elseif ( > ) {
                                             = ;
                                             = 0;
                                             = 'completed';
                                        } else {
                                             =  - ;
                                             =  - ;
                                             = 'training';
                                        }
                                        
                                         = min(100, max(0, ( / ) * 100));
                                    @endphp
                                    
                                    @if( === 'training')
                                        <div class="text-green-400 font-semibold">训练中</div>
                                        <div class="text-sm text-blue-300 mt-1">
                                            剩余：{{ gmdate('d\天 H\时 i\分', ) }}
                                        </div>
                                        <div class="text-xs text-blue-400 mt-1">
                                            完成：{{ date('Y-m-d H:i', ) }}
                                        </div>
                                    @elseif( === 'waiting')
                                        <div class="text-blue-400 font-semibold">等待中</div>
                                        <div class="text-sm text-blue-300 mt-1">
                                            开始：{{ date('Y-m-d H:i', ) }}
                                        </div>
                                        <div class="text-xs text-blue-400 mt-1">
                                            完成：{{ date('Y-m-d H:i', ) }}
                                        </div>
                                    @else
                                        <div class="text-yellow-400 font-semibold">已完成</div>
                                        <div class="text-sm text-blue-300 mt-1">
                                            {{ date('Y-m-d H:i', ) }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                            
                            <!-- 进度条 -->
                            <div class="relative">
                                <div class="w-full bg-white/10 rounded-full h-4 overflow-hidden">
                                    <div class="bg-gradient-to-r from-blue-500 to-green-500 h-4 rounded-full transition-all duration-1000" 
                                         style="width: {{  }}%"></div>
                                </div>
                                <div class="absolute right-0 top-0 text-xs text-blue-300 mt-1">
                                    {{ number_format(, 1) }}%
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 text-sm">
                                <div>
                                    <span class="text-blue-300">开始时间：</span>
                                    <span class="text-white">{{ date('Y-m-d H:i', ) }}</span>
                                </div>
                                <div>
                                    <span class="text-blue-300">预计完成：</span>
                                    <span class="text-white">{{ date('Y-m-d H:i', ) }}</span>
                                </div>
                                <div>
                                    <span class="text-blue-300">已训练：</span>
                                    <span class="text-green-400">{{  > 0 ? gmdate('d\天 H\时 i\分', ) : '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-blue-300">剩余时间：</span>
                                    <span class="text-yellow-400">{{  > 0 ? gmdate('d\天 H\时 i\分', ) : '-' }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- 已学技能 -->
        @if(isset(['skills_by_group']) && !empty(['skills_by_group']))
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 mb-6 eve-glow">
            <h2 class="text-2xl font-semibold mb-6">📖 已学技能（按分类展示）</h2>
            
            <div class="space-y-3">
                @foreach(['skills_by_group'] as )
                    <details class="bg-white/5 rounded-lg overflow-hidden">
                        <summary class="px-4 py-3 bg-white/10 cursor-pointer hover:bg-white/20 transition-colors flex justify-between items-center">
                            <div class="flex items-center space-x-3">
                                <span class="text-lg font-semibold">📚 {{ ['group_name'] }}</span>
                                <span class="text-sm text-blue-300">({{ count(['skills']) }} 个技能)</span>
                            </div>
                            <span class="text-blue-400 transform transition-transform duration-200">▼</span>
                        </summary>
                        <div class="p-4">
                            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach(['skills'] as )
                                    <div class="bg-white/5 rounded-lg p-3 hover:bg-white/10 transition-colors">
                                        <div class="mb-2">
                                            <div class="font-semibold text-white text-sm">
                                                {{ ['skill_name'] ?? '未知技能' }}
                                            </div>
                                            <div class="text-xs text-blue-400 mt-1">
                                                等级 {{ ['active_level'] ?? 0 }} / 5
                                            </div>
                                        </div>
                                        <div class="flex justify-between items-center text-xs">
                                            <span class="text-blue-300">
                                                {{ number_format(['skill_points_in_skill'] ?? ['trained_skill_points'] ?? 0) }} SP
                                            </span>
                                            @php  = ['active_level'] ?? ['trained_level'] ?? 0; @endphp
                                            @if( == 5)
                                                <span class="text-green-400">✅ 满级</span>
                                            @endif
                                        </div>
                                        @php
                                             = [0, 0, 250, 1414, 8000, 45255];
                                             = ['active_level'] ?? ['trained_level'] ?? 0;
                                             = ['skill_points_in_skill'] ?? ['trained_skill_points'] ?? 0;
                                             = [ + 1] ?? 45255;
                                             = [] ?? 0;
                                             =  - ;
                                             =  >= 5 ? 100 : ( > 0 ? (( - ) / ) * 100 : 0);
                                        @endphp
                                        <div class="mt-2 w-full bg-white/10 rounded-full h-1.5 overflow-hidden">
                                            <div class="bg-blue-500 h-1.5 rounded-full" style="width: {{ min(100, max(0, )) }}%"></div>
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

    <script>
        setTimeout(function() { location.reload(); }, 60000);
        
        document.querySelectorAll('details').forEach(detail => {
            detail.addEventListener('toggle', () => {
                const arrow = detail.querySelector('summary span:last-child');
                arrow.style.transform = detail.open ? 'rotate(180deg)' : 'rotate(0deg)';
            });
        });
    </script>
</body>
</html>
