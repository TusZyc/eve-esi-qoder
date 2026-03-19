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
<div class="stat-card rounded-2xl p-6">
    <!-- 筛选器 -->
    <div class="flex flex-wrap items-center gap-4 mb-6">
        <div class="flex items-center space-x-3">
            <label class="text-sm text-slate-400 font-medium">日期:</label>
            <input type="date" id="log-date" value="{{ $currentDate }}" 
                   class="bg-slate-800/50 border border-slate-600/50 rounded-lg px-4 py-2 text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all">
        </div>
        
        <div class="flex items-center space-x-3">
            <label class="text-sm text-slate-400 font-medium">级别:</label>
            <select id="log-level" class="bg-slate-800/50 border border-slate-600/50 rounded-lg px-4 py-2 text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all">
                <option value="all" {{ $currentLevel === 'all' ? 'selected' : '' }}>全部</option>
                <option value="error" {{ $currentLevel === 'error' ? 'selected' : '' }}>ERROR</option>
                <option value="warning" {{ $currentLevel === 'warning' ? 'selected' : '' }}>WARNING</option>
                <option value="info" {{ $currentLevel === 'info' ? 'selected' : '' }}>INFO</option>
                <option value="debug" {{ $currentLevel === 'debug' ? 'selected' : '' }}>DEBUG</option>
            </select>
        </div>
        
        <button onclick="filterLogs()" class="px-5 py-2 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 rounded-lg text-sm font-medium transition-all shadow-lg shadow-blue-500/20">
            🔍 筛选
        </button>
        
        <button onclick="refreshLogs()" class="px-5 py-2 bg-slate-700/50 hover:bg-slate-600/50 border border-slate-600/50 rounded-lg text-sm font-medium transition-all">
            🔄 刷新
        </button>
    </div>
    
    <!-- 日志列表 -->
    <div class="log-container bg-slate-900/30 rounded-xl p-4 border border-slate-700/30">
        @forelse ($logs as $log)
        <div class="log-line py-3 border-b border-slate-800/50 hover:bg-slate-800/30 rounded-lg px-3 transition-colors">
            <div class="flex items-start gap-4">
                <span class="text-slate-500 whitespace-nowrap text-sm">{{ $log['time'] }}</span>
                <span class="px-2.5 py-1 rounded-full text-xs font-medium
                    @if($log['level'] === 'ERROR' || $log['level'] === 'CRITICAL') bg-red-500/15 text-red-400
                    @elseif($log['level'] === 'WARNING') bg-yellow-500/15 text-yellow-400
                    @elseif($log['level'] === 'INFO') bg-blue-500/15 text-blue-400
                    @else bg-slate-500/15 text-slate-400
                    @endif">{{ $log['level'] }}</span>
                <span class="flex-1 text-slate-300 break-all text-sm">{{ $log['message'] }}</span>
            </div>
        </div>
        @empty
        <div class="text-center py-12">
            <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-slate-700/30 flex items-center justify-center">
                <span class="text-3xl">📭</span>
            </div>
            <p class="text-slate-400 font-medium">暂无日志记录</p>
            <p class="text-slate-500 text-sm mt-1">该日期没有符合条件的日志</p>
        </div>
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
