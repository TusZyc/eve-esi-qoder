@extends('layouts.app')

@section('title', $operation->operation_name . ' - 出勤报告')
@section('page-title', '出勤报告')

@section('content')
<div class="p-6">
    <!-- 页面标题 -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white">{{ $operation->operation_name }}</h1>
            <p class="text-gray-400 mt-1">出勤报告</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('fleet.export', $operation->id) }}" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                导出 CSV
            </a>
            <a href="{{ route('fleet.index') }}" class="text-gray-400 hover:text-white transition-colors">
                返回列表
            </a>
        </div>
    </div>

    <!-- 行动概要 -->
    <div class="bg-gray-800/50 rounded-lg p-6 backdrop-blur-sm border border-gray-700/50 mb-6">
        <div class="grid grid-cols-4 gap-6">
            <div>
                <div class="text-gray-400 text-sm">行动时间</div>
                <div class="text-white mt-1">{{ $operation->started_at->format('Y-m-d H:i') }}</div>
                <div class="text-gray-500 text-sm">至 {{ $operation->ended_at?->format('Y-m-d H:i') ?? '进行中' }}</div>
            </div>
            <div>
                <div class="text-gray-400 text-sm">持续时长</div>
                <div class="text-white mt-1">{{ $operation->getDurationMinutes() }} 分钟</div>
            </div>
            <div>
                <div class="text-gray-400 text-sm">参与人数</div>
                <div class="text-white mt-1">{{ $statistics['total_participants'] }} 人</div>
            </div>
            <div>
                <div class="text-gray-400 text-sm">平均分数</div>
                <div class="text-white mt-1">{{ $statistics['average_score'] }}</div>
            </div>
        </div>
    </div>

    <!-- 评分分布 -->
    <div class="bg-gray-800/50 rounded-lg p-6 backdrop-blur-sm border border-gray-700/50 mb-6">
        <h2 class="text-lg font-semibold text-white mb-4">评分分布</h2>
        <div class="grid grid-cols-6 gap-4">
            @php
            $gradeColors = [
                'S' => 'bg-yellow-500',
                'A' => 'bg-green-500',
                'B' => 'bg-blue-500',
                'C' => 'bg-gray-500',
                'D' => 'bg-orange-500',
                'F' => 'bg-red-500',
            ];
            @endphp
            @foreach(['S', 'A', 'B', 'C', 'D', 'F'] as $grade)
            <div class="text-center">
                <div class="text-2xl font-bold {{ $gradeColors[$grade] }} bg-opacity-20 rounded-lg py-3 {{ $grade === 'S' ? 'text-yellow-400' : ($grade === 'A' ? 'text-green-400' : ($grade === 'B' ? 'text-blue-400' : ($grade === 'C' ? 'text-gray-400' : ($grade === 'D' ? 'text-orange-400' : 'text-red-400')))) }}">
                    {{ $statistics['grade_distribution'][$grade] ?? 0 }}
                </div>
                <div class="text-gray-400 text-sm mt-2">{{ $grade }} 级</div>
            </div>
            @endforeach
        </div>
    </div>

    <!-- 成员明细 -->
    <div class="bg-gray-800/50 rounded-lg backdrop-blur-sm border border-gray-700/50">
        <div class="p-4 border-b border-gray-700">
            <h2 class="text-lg font-semibold text-white">成员明细</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-gray-400 text-sm border-b border-gray-700">
                        <th class="px-4 py-3">角色</th>
                        <th class="px-4 py-3">公司</th>
                        <th class="px-4 py-3 text-center">总分</th>
                        <th class="px-4 py-3 text-center">等级</th>
                        <th class="px-4 py-3 text-center">出勤时长</th>
                        <th class="px-4 py-3 text-center">加入延迟</th>
                        <th class="px-4 py-3">主要舰船</th>
                        <th class="px-4 py-3 text-center">星系重叠</th>
                        <th class="px-4 py-3 text-center">平均距离</th>
                        <th class="px-4 py-3 text-center">在站比例</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($summaries as $summary)
                    <tr class="border-b border-gray-700/50 hover:bg-gray-700/30">
                        <td class="px-4 py-3">
                            <div class="text-white">{{ $summary->character_name }}</div>
                        </td>
                        <td class="px-4 py-3 text-gray-300">{{ $summary->corporation_name ?? '-' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="font-bold {{ $summary->getGradeColor() }}">{{ $summary->total_score }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-1 {{ $summary->getGradeBgColor() }} {{ $summary->getGradeColor() }} rounded font-bold">
                                {{ $summary->grade }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center text-gray-300">
                            {{ $summary->attendance_duration_minutes }} 分
                            <div class="text-xs text-gray-500">{{ $summary->getAttendancePercent() }}%</div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($summary->join_delay_minutes <= 0)
                            <span class="text-green-400">准时</span>
                            @elseif($summary->join_delay_minutes <= 15)
                            <span class="text-yellow-400">+{{ $summary->join_delay_minutes }}分</span>
                            @else
                            <span class="text-red-400">+{{ $summary->join_delay_minutes }}分</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-300">{{ $summary->primary_ship_name ?? '-' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="{{ $summary->system_overlap_percent >= 70 ? 'text-green-400' : 'text-yellow-400' }}">
                                {{ $summary->system_overlap_percent }}%
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($summary->avg_jumps_from_commander === null)
                            <span class="text-gray-500">N/A</span>
                            @else
                            <span class="{{ $summary->avg_jumps_from_commander <= 2 ? 'text-green-400' : 'text-yellow-400' }}">
                                {{ $summary->avg_jumps_from_commander }}跳
                            </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="{{ $summary->in_station_percent <= 20 ? 'text-green-400' : 'text-red-400' }}">
                                {{ $summary->in_station_percent }}%
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection