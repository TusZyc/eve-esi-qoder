@extends('layouts.app')

@section('title', $operation->operation_name . ' - 实时监控')
@section('page-title', '实时监控')

@section('content')
<div class="p-6">
    <!-- 页面标题 -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-white">{{ $operation->operation_name }}</h1>
                @if($operation->status === 'active')
                <span class="px-2 py-1 bg-green-500/20 text-green-400 text-xs rounded-full flex items-center gap-1">
                    <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                    进行中
                </span>
                @endif
            </div>
            <p class="text-gray-400 mt-1">指挥官: {{ $operation->commander_name }}</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('fleet.index') }}" class="text-gray-400 hover:text-white transition-colors">
                返回列表
            </a>
        </div>
    </div>

    <!-- 状态概览 -->
    <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="bg-gray-800/50 rounded-lg p-4 backdrop-blur-sm border border-gray-700/50">
            <div class="text-gray-400 text-sm">开始时间</div>
            <div class="text-white text-lg mt-1">{{ $operation->started_at->format('H:i') }}</div>
            <div class="text-gray-500 text-xs">{{ $operation->started_at->format('Y-m-d') }}</div>
        </div>
        <div class="bg-gray-800/50 rounded-lg p-4 backdrop-blur-sm border border-gray-700/50">
            <div class="text-gray-400 text-sm">持续时间</div>
            <div class="text-white text-lg mt-1" id="duration">{{ $operation->getDurationMinutes() }}</div>
            <div class="text-gray-500 text-xs">分钟</div>
        </div>
        <div class="bg-gray-800/50 rounded-lg p-4 backdrop-blur-sm border border-gray-700/50">
            <div class="text-gray-400 text-sm">当前成员</div>
            <div class="text-white text-lg mt-1" id="memberCount">{{ $latestSnapshot?->member_count ?? 0 }}</div>
            <div class="text-gray-500 text-xs">人</div>
        </div>
        <div class="bg-gray-800/50 rounded-lg p-4 backdrop-blur-sm border border-gray-700/50">
            <div class="text-gray-400 text-sm">快照次数</div>
            <div class="text-white text-lg mt-1">{{ $operation->getSnapshotCount() }}</div>
            <div class="text-gray-500 text-xs">次</div>
        </div>
    </div>

    <!-- 操作按钮 -->
    <div class="flex items-center gap-4 mb-6">
        <button id="snapshotBtn" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            手动抓取
        </button>
        @if($operation->commander_character_id === ($user->eve_character_id ?? null))
        <button id="endBtn" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"></path>
            </svg>
            结束行动
        </button>
        @endif
        
        <div class="ml-auto text-sm text-gray-400">
            @if($operation->auto_snapshot)
            自动抓取中 | 间隔: {{ $operation->snapshot_interval }}秒
            @else
            仅手动抓取模式
            @endif
        </div>
    </div>

    <!-- 当前成员列表 -->
    <div class="bg-gray-800/50 rounded-lg backdrop-blur-sm border border-gray-700/50 mb-6">
        <div class="p-4 border-b border-gray-700">
            <h2 class="text-lg font-semibold text-white">当前成员列表</h2>
            <p class="text-gray-400 text-sm mt-1">
                最近快照: {{ $latestSnapshot?->snapshot_time?->format('H:i:s') ?? '暂无' }}
                @if($latestSnapshot)
                ({{ $latestSnapshot->snapshot_time->diffForHumans() }})
                @endif
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-gray-400 text-sm border-b border-gray-700">
                        <th class="px-4 py-3">角色</th>
                        <th class="px-4 py-3">公司</th>
                        <th class="px-4 py-3">舰船</th>
                        <th class="px-4 py-3">星系</th>
                        <th class="px-4 py-3">距离</th>
                        <th class="px-4 py-3">状态</th>
                    </tr>
                </thead>
                <tbody id="memberTable">
                    @if($latestSnapshot)
                    @foreach($latestSnapshot->members as $member)
                    <tr class="border-b border-gray-700/50 hover:bg-gray-700/30">
                        <td class="px-4 py-3">
                            <div class="text-white">{{ $member->character_name }}</div>
                            <div class="text-gray-500 text-xs">{{ $member->getRoleDisplayName() }}</div>
                        </td>
                        <td class="px-4 py-3 text-gray-300">{{ $member->corporation_name ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-300">{{ $member->ship_type_name ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-300">{{ $member->solar_system_name ?? '-' }}</td>
                        <td class="px-4 py-3">
                            @if($member->jumps_from_commander === null)
                            <span class="text-gray-500">无法到达</span>
                            @elseif($member->jumps_from_commander === 0)
                            <span class="text-green-400">同星系</span>
                            @else
                            <span class="{{ $member->jumps_from_commander <= 2 ? 'text-green-400' : 'text-yellow-400' }}">
                                {{ $member->jumps_from_commander }}跳
                            </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($member->location_status === 'in_station' || ($member->station_id && !$member->location_status))
                            <span class="px-2 py-1 bg-yellow-500/20 text-yellow-400 text-xs rounded">在站内</span>
                            @elseif($member->location_status === 'unauthorized')
                            <span class="px-2 py-1 bg-gray-500/20 text-gray-400 text-xs rounded">角色未授权</span>
                            @else
                            <span class="px-2 py-1 bg-green-500/20 text-green-400 text-xs rounded">太空中</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                    @else
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400">
                            暂无快照数据，点击"手动抓取"开始记录
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <!-- 快照历史 -->
    <div class="bg-gray-800/50 rounded-lg backdrop-blur-sm border border-gray-700/50">
        <div class="p-4 border-b border-gray-700">
            <h2 class="text-lg font-semibold text-white">快照历史</h2>
        </div>
        <div class="p-4">
            <div class="space-y-2">
                @forelse($snapshotHistory as $snapshot)
                <div class="flex items-center justify-between p-3 bg-gray-700/30 rounded-lg">
                    <div class="flex items-center gap-4">
                        <span class="text-white">{{ $snapshot->snapshot_time->format('H:i:s') }}</span>
                        <span class="text-gray-400">{{ $snapshot->member_count }} 人</span>
                        @if($snapshot->is_manual)
                        <span class="px-2 py-0.5 bg-blue-500/20 text-blue-400 text-xs rounded">手动</span>
                        @else
                        <span class="px-2 py-0.5 bg-gray-500/20 text-gray-400 text-xs rounded">自动</span>
                        @endif
                    </div>
                    <div class="text-gray-400 text-sm">
                        指挥官位置: {{ $snapshot->getCommanderSystemName() }}
                    </div>
                </div>
                @empty
                <div class="text-center text-gray-400 py-4">
                    暂无快照记录
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<script>
// 手动抓取
document.getElementById('snapshotBtn')?.addEventListener('click', async () => {
    const btn = document.getElementById('snapshotBtn');
    btn.disabled = true;
    btn.textContent = '抓取中...';
    
    try {
        const response = await fetch('{{ route("fleet.snapshot", $operation->id) }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            location.reload();
        } else {
            alert('抓取失败: ' + result.error);
        }
    } catch (error) {
        alert('抓取失败: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg> 手动抓取';
    }
});

// 结束行动
document.getElementById('endBtn')?.addEventListener('click', async () => {
    if (!confirm('确定要结束此行动吗？结束后将无法继续抓取数据。')) {
        return;
    }
    
    const btn = document.getElementById('endBtn');
    btn.disabled = true;
    btn.textContent = '处理中...';
    
    try {
        const response = await fetch('{{ route("fleet.end", $operation->id) }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            window.location.href = result.redirect;
        } else {
            alert('结束失败: ' + result.error);
        }
    } catch (error) {
        alert('结束失败: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"></path></svg> 结束行动';
    }
});

// 自动刷新（每60秒）
@if($operation->status === 'active' && $operation->auto_snapshot)
setTimeout(() => location.reload(), {{ $operation->snapshot_interval * 1000 }});
@endif
</script>
@endsection