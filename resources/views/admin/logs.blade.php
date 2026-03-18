@extends('layouts.admin')

@section('title', '系统日志')
@section('page-title', '系统日志')

@push('styles')
<style>
    .log-line {
        font-family: 'Consolas', 'Monaco', monospace;
        font-size: 13px;
    }
    .log-container {
        max-height: 600px;
        overflow-y: auto;
    }
</style>
@endpush

@section('content')
<div class="stat-card rounded-xl p-6">
    <!-- 筛选器 -->
    <div class="flex flex-wrap items-center gap-4 mb-6">
        <div class="flex items-center space-x-2">
            <label class="text-sm text-slate-400">日期:</label>
            <input type="date" id="log-date" value="{{ $currentDate }}" 
                   class="bg-slate-800 border border-slate-600 rounded px-3 py-1.5 text-sm focus:outline-none focus:border-blue-500">
        </div>
        
        <div class="flex items-center space-x-2">
            <label class="text-sm text-slate-400">级别:</label>
            <select id="log-level" class="bg-slate-800 border border-slate-600 rounded px-3 py-1.5 text-sm focus:outline-none focus:border-blue-500">
                <option value="all" {{ $currentLevel === 'all' ? 'selected' : '' }}>全部</option>
                <option value="error" {{ $currentLevel === 'error' ? 'selected' : '' }}>ERROR</option>
                <option value="warning" {{ $currentLevel === 'warning' ? 'selected' : '' }}>WARNING</option>
                <option value="info" {{ $currentLevel === 'info' ? 'selected' : '' }}>INFO</option>
                <option value="debug" {{ $currentLevel === 'debug' ? 'selected' : '' }}>DEBUG</option>
            </select>
        </div>
        
        <button onclick="filterLogs()" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 rounded text-sm transition">
            筛选
        </button>
        
        <button onclick="refreshLogs()" class="px-4 py-1.5 bg-slate-600 hover:bg-slate-500 rounded text-sm transition">
            刷新
        </button>
    </div>
    
    <!-- 日志列表 -->
    <div class="log-container bg-slate-900/50 rounded-lg p-4">
        @forelse ($logs as $log)
        <div class="log-line py-2 border-b border-slate-800 hover:bg-slate-800/50">
            <div class="flex items-start gap-3">
                <span class="text-slate-500 whitespace-nowrap">{{ $log['time'] }}</span>
                <span class="px-2 py-0.5 rounded text-xs font-medium
                    @if($log['level'] === 'ERROR' || $log['level'] === 'CRITICAL') bg-red-500/20 text-red-400
                    @elseif($log['level'] === 'WARNING') bg-yellow-500/20 text-yellow-400
                    @elseif($log['level'] === 'INFO') bg-blue-500/20 text-blue-400
                    @else bg-slate-500/20 text-slate-400
                    @endif">{{ $log['level'] }}</span>
                <span class="flex-1 text-slate-300 break-all">{{ $log['message'] }}</span>
            </div>
        </div>
        @empty
        <p class="text-center text-slate-400 py-8">该日期没有符合条件的日志</p>
        @endforelse
    </div>
</div>
@endsection

@push('scripts')
<script>
function filterLogs() {
    const date = document.getElementById('log-date').value;
    const level = document.getElementById('log-level').value;
    window.location.href = `{{ route('admin.logs') }}?date=${date}&level=${level}`;
}

function refreshLogs() {
    window.location.reload();
}

// 回车键筛选
document.getElementById('log-date').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') filterLogs();
});
document.getElementById('log-level').addEventListener('change', filterLogs);
</script>
@endpush
