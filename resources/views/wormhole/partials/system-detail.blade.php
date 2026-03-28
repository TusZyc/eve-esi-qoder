<!-- 虫洞系统详情组件 -->
<div class="space-y-4">
    <!-- 第一栏：基本信息 -->
    <div class="wh-section">
        <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
            <span>📊 基本信息</span>
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <div class="wh-label">星系编号</div>
                <div class="wh-value text-xl">{{ $systemInfo['name'] }}</div>
            </div>
            <div>
                <div class="wh-label">虫洞等级</div>
                <div class="wh-value"><span class="wh-badge wh-badge-c{{ $systemInfo['class'] }}">{{ $systemInfo['class_name'] }}</span></div>
            </div>
            <div>
                <div class="wh-label">特殊效果</div>
                <div class="wh-value">
                    @if($systemInfo['effect_zh'])
                        <span class="wh-badge wh-badge-effect">{{ $systemInfo['effect_zh'] }}</span>
                    @else
                        <span class="text-white/50">无</span>
                    @endif
                </div>
            </div>
            <div>
                <div class="wh-label">系统大小</div>
                <div class="wh-value">{{ $systemInfo['system_radius_au'] ? $systemInfo['system_radius_au'] . ' AU' : '未知' }}</div>
            </div>
            <div>
                <div class="wh-label">星座</div>
                <div class="wh-value">{{ $systemInfo['constellation_name'] ?? '未知' }}</div>
            </div>
            <div>
                <div class="wh-label">星域</div>
                <div class="wh-value">{{ $systemInfo['region_name'] ?? '未知' }}</div>
            </div>
            <div>
                <div class="wh-label">安全等级</div>
                <div class="wh-value">{{ $systemInfo['security'] }}</div>
            </div>
            <div>
                <div class="wh-label">系统ID</div>
                <div class="wh-value text-white/60">{{ $systemInfo['system_id'] }}</div>
            </div>
        </div>
        
        <!-- 效果详情 -->
        @if($systemInfo['effect_detail'])
        <div class="mt-4 p-4 bg-white/5 rounded-lg">
            <div class="text-sm text-pink-300 mb-2">{{ $systemInfo['effect_detail']['name_en'] }}</div>
            <div class="text-sm text-white/70 mb-2">{{ $systemInfo['effect_detail']['description'] }}</div>
            <div class="flex flex-wrap gap-2">
                @foreach($systemInfo['effect_detail']['effects'] as $key => $value)
                    <span class="text-xs bg-white/10 px-2 py-1 rounded">{{ $key }}: {{ $value }}</span>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    <!-- 第二栏：连接信息 -->
    <div class="wh-section">
        <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
            <span>🔗 连接信息</span>
        </h3>
        
        <!-- 静态连接 -->
        <div class="mb-4">
            <div class="text-sm text-blue-200 mb-2">静态连接</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                @forelse($systemInfo['statics'] as $static)
                    <div class="flex items-center gap-2 p-3 bg-blue-500/10 rounded-lg">
                        <span class="font-bold text-blue-300">{{ $static['type'] }}</span>
                        <span class="text-white/60">→</span>
                        <span class="text-white">{{ $static['destination_zh'] }}</span>
                        <span class="text-xs text-white/40 ml-auto">{{ $static['lifetime_hours'] }}h / {{ number_format($static['max_mass'] / 1e6) }}M kg</span>
                    </div>
                @empty
                    <div class="text-white/50">暂无数据</div>
                @endforelse
            </div>
        </div>
        
        <!-- 游走虫洞 -->
        <div>
            <div class="text-sm text-blue-200 mb-2">可能出现的游走虫洞</div>
            <div class="flex flex-wrap gap-2">
                @forelse($systemInfo['wandering'] as $wh)
                    <span class="text-xs bg-white/10 px-2 py-1 rounded">
                        {{ $wh['type'] }} → {{ $wh['destination_zh'] }}
                    </span>
                @empty
                    <span class="text-white/50">暂无数据</span>
                @endforelse
            </div>
        </div>
    </div>

    <!-- 第三栏：天体信息 -->
    <div class="wh-section">
        <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
            <span>🌍 天体信息</span>
        </h3>
        @if(count($systemInfo['planets']) > 0)
            <div class="space-y-2">
                @foreach($systemInfo['planets'] as $planet)
                    <div class="p-3 bg-white/5 rounded-lg">
                        <div class="font-medium text-white">{{ $planet['name'] ?? '行星 ' . $loop->iteration }}</div>
                        @if(count($planet['moons']) > 0)
                            <div class="ml-4 mt-2 space-y-1">
                                @foreach($planet['moons'] as $moon)
                                    <div class="text-sm text-white/60">└─ {{ $moon['name'] ?? '卫星 ' . $loop->iteration }}</div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-sm text-white/40 ml-4">└─ 无卫星</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-white/50">暂无天体数据</div>
        @endif
    </div>

    <!-- 第四栏：异常空间 -->
    <div class="wh-section">
        <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
            <span>⚠️ 可能的异常空间</span>
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach(['combat' => '战斗地点', 'relic' => '遗迹地点', 'data' => '数据地点', 'gas' => '气云地点', 'ore' => '矿石地点'] as $type => $label)
                <div>
                    <div class="text-sm text-blue-200 mb-2">{{ $label }}</div>
                    @if(isset($systemInfo['anomalies'][$type]))
                        <div class="space-y-1">
                            @if(is_array($systemInfo['anomalies'][$type]) && isset($systemInfo['anomalies'][$type]['tier_zh']))
                                <div class="text-xs text-white/60 mb-1">{{ $systemInfo['anomalies'][$type]['tier_zh'] }}</div>
                                @foreach($systemInfo['anomalies'][$type]['sites'] ?? [] as $site)
                                    <div class="text-sm text-white/80">• {{ $site }}</div>
                                @endforeach
                            @else
                                @foreach($systemInfo['anomalies'][$type] ?? [] as $site)
                                    @if(is_string($site))
                                        <div class="text-sm text-white/80">• {{ $site }}</div>
                                    @endif
                                @endforeach
                            @endif
                        </div>
                    @else
                        <div class="text-sm text-white/40">暂无数据</div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <!-- 第五栏：最近击杀 -->
    <div class="wh-section">
        <h3 class="text-lg font-bold mb-4 flex items-center justify-between">
            <span>💀 最近击杀</span>
            @if($kills && isset($kills['kb_url']))
                <a href="{{ $kills['kb_url'] }}" target="_blank" class="text-sm text-blue-400 hover:text-blue-300">
                    查看更多 → KB网站
                </a>
            @endif
        </h3>
        <div id="killsList">
            @if($kills && count($kills['kills']) > 0)
                <div class="space-y-2">
                    @foreach($kills['kills'] as $kill)
                        <div class="km-row" onclick="window.open('https://kb.ceve-market.org/kill/{{ $kill['kill_id'] }}', '_blank')">
                            <div class="flex items-center gap-4">
                                <div class="text-sm text-white/60">{{ $kill['kill_time'] ?? '' }}</div>
                                <div class="font-medium">{{ $kill['victim_name'] ?? '未知' }}</div>
                                <div class="text-blue-300">{{ $kill['ship_name'] ?? '未知舰船' }}</div>
                            </div>
                            <div class="text-sm text-yellow-400">
                                @if($kill['total_value']){{ number_format($kill['total_value'], 2) }} ISK @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="text-sm text-white/40 mt-2">共 {{ $kills['total'] ?? 0 }} 条记录</div>
            @else
                <div class="text-white/50 text-center py-4">暂无击杀记录</div>
            @endif
        </div>
    </div>
</div>