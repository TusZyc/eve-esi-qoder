@extends('layouts.app')

@section('title', '舰队行动')
@section('page-title', '舰队行动')

@section('content')
<div class="p-6">
    <!-- 页面标题 -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white">舰队行动</h1>
            <p class="text-gray-400 mt-1">管理舰队行动和出勤统计</p>
        </div>
        @if($isLoggedIn)
        <a href="{{ route('fleet.create') }}" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            创建行动
        </a>
        @endif
    </div>

    <!-- 筛选栏 -->
    <div class="bg-gray-800/50 rounded-lg p-4 mb-6 backdrop-blur-sm border border-gray-700/50">
        <div class="flex items-center gap-4">
            <span class="text-gray-400">状态筛选：</span>
            <div class="flex gap-2">
                <a href="?status=all" class="px-3 py-1 rounded-lg transition-colors {{ $statusFilter === 'all' ? 'bg-blue-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600' }}">
                    全部
                </a>
                <a href="?status=active" class="px-3 py-1 rounded-lg transition-colors {{ $statusFilter === 'active' ? 'bg-blue-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600' }}">
                    进行中
                </a>
                <a href="?status=ended" class="px-3 py-1 rounded-lg transition-colors {{ $statusFilter === 'ended' ? 'bg-blue-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600' }}">
                    已结束
                </a>
            </div>
        </div>
    </div>

    <!-- 行动列表 -->
    @if($operations->count() > 0)
    <div class="space-y-4">
        @foreach($operations as $operation)
        <div class="bg-gray-800/50 rounded-lg p-5 backdrop-blur-sm border border-gray-700/50 hover:border-blue-500/50 transition-colors">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center gap-3">
                        <h3 class="text-lg font-semibold text-white">{{ $operation->operation_name }}</h3>
                        @if($operation->status === 'active')
                        <span class="px-2 py-1 bg-green-500/20 text-green-400 text-xs rounded-full flex items-center gap-1">
                            <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                            进行中
                        </span>
                        @else
                        <span class="px-2 py-1 bg-gray-500/20 text-gray-400 text-xs rounded-full">
                            已结束
                        </span>
                        @endif
                    </div>
                    <div class="mt-2 text-sm text-gray-400">
                        <span>指挥官: {{ $operation->commander_name }}</span>
                        <span class="mx-2">|</span>
                        <span>开始: {{ $operation->started_at->format('Y-m-d H:i') }}</span>
                        @if($operation->ended_at)
                        <span class="mx-2">|</span>
                        <span>结束: {{ $operation->ended_at->format('Y-m-d H:i') }}</span>
                        @endif
                    </div>
                    <div class="mt-2 flex items-center gap-4 text-sm">
                        <span class="text-gray-400">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            {{ $operation->getParticipantCount() }} 人参与
                        </span>
                        <span class="text-gray-400">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            {{ $operation->getSnapshotCount() }} 次快照
                        </span>
                        <span class="text-gray-400">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            {{ $operation->getDurationMinutes() }} 分钟
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @if($operation->status === 'active')
                    <a href="{{ route('fleet.show', $operation->id) }}" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg transition-colors">
                        监控
                    </a>
                    @else
                    <a href="{{ route('fleet.report', $operation->id) }}" class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-sm rounded-lg transition-colors">
                        报告
                    </a>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <!-- 分页 -->
    @if($operations->hasPages())
    <div class="mt-6">
        {{ $operations->links() }}
    </div>
    @endif
    @else
    <div class="bg-gray-800/50 rounded-lg p-12 text-center backdrop-blur-sm border border-gray-700/50">
        <svg class="w-16 h-16 mx-auto text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
        </svg>
        <h3 class="text-lg font-medium text-gray-400 mb-2">暂无行动记录</h3>
        <p class="text-gray-500 mb-4">创建一个舰队行动来开始记录出勤数据</p>
        @if($isLoggedIn)
        <a href="{{ route('fleet.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            创建行动
        </a>
        @endif
    </div>
    @endif
</div>
@endsection