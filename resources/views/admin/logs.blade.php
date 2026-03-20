@extends('layouts.admin')

@section('title', '系统日志')
@section('page-title', '系统日志')

@push('styles')
<style>
    .log-container {
        max-height: 700px;
        overflow-y: auto;
        overflow-x: hidden;
        max-width: 100%;
    }
    
    .log-entry {
        font-family: 'Consolas', 'Monaco', monospace;
        font-size: 13px;
        border-bottom: 1px solid rgba(51, 65, 85, 0.3);
        transition: background-color 0.15s;
        overflow: hidden;
        max-width: 100%;
    }
    
    .log-entry:hover {
        background-color: rgba(51, 65, 85, 0.2);
    }
    
    .log-entry.hidden-by-search {
        display: none;
    }
    
    .log-summary {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        cursor: pointer;
        user-select: none;
        max-width: 100%;
        overflow: hidden;
    }
    
    .log-summary .time {
        color: #64748b;
        font-size: 12px;
        white-space: nowrap;
        min-width: 65px;
    }
    
    .log-summary .level-badge {
        padding: 2px 8px;
        border-radius: 9999px;
        font-size: 11px;
        font-weight: 600;
        min-width: 70px;
        text-align: center;
    }
    
    .level-badge.error { background: rgba(239, 68, 68, 0.15); color: #f87171; }
    .level-badge.warning { background: rgba(234, 179, 8, 0.15); color: #facc15; }
    .level-badge.info { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
    .level-badge.debug { background: rgba(100, 116, 139, 0.15); color: #94a3b8; }
    
    .log-summary .tag-badge {
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 500;
    }
    
    /* 标签颜色 */
    .tag-API { background: rgba(168, 85, 247, 0.2); color: #c084fc; }
    .tag-Assets { background: rgba(34, 197, 94, 0.2); color: #4ade80; }
    .tag-AutoRefreshToken { background: rgba(249, 115, 22, 0.2); color: #fb923c; }
    .tag-Notifications { background: rgba(236, 72, 153, 0.2); color: #f472b6; }
    .tag-Admin { background: rgba(99, 102, 241, 0.2); color: #818cf8; }
    .tag-Market { background: rgba(20, 184, 166, 0.2); color: #2dd4bf; }
    .tag-Skills { background: rgba(251, 191, 36, 0.2); color: #fbbf24; }
    .tag-Contracts { background: rgba(139, 92, 246, 0.2); color: #a78bfa; }
    .tag-Wallet { background: rgba(34, 211, 238, 0.2); color: #22d3ee; }
    .tag-Killmails { background: rgba(244, 63, 94, 0.2); color: #fb7185; }
    .tag-default { background: rgba(100, 116, 139, 0.2); color: #94a3b8; }
    
    .log-summary .message {
        flex: 1;
        min-width: 0;
        color: #cbd5e1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .log-summary .expand-icon {
        color: #64748b;
        transition: transform 0.2s;
        font-size: 10px;
    }
    
    .log-entry.expanded .expand-icon {
        transform: rotate(90deg);
    }
    
    .log-detail {
        display: none;
        padding: 0 16px 16px 16px;
        margin-left: 77px;
        border-left: 2px solid rgba(59, 130, 246, 0.3);
    }
    
    .log-entry.expanded .log-detail {
        display: block;
    }
    
    .context-table {
        background: rgba(15, 23, 42, 0.5);
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 12px;
    }
    
    .context-row {
        display: flex;
        padding: 4px 0;
        border-bottom: 1px solid rgba(51, 65, 85, 0.2);
    }
    
    .context-row:last-child {
        border-bottom: none;
    }
    
    .context-key {
        color: #60a5fa;
        min-width: 150px;
        font-weight: 500;
    }
    
    .context-value {
        color: #94a3b8;
        word-break: break-all;
    }
    
    .full-message {
        background: rgba(15, 23, 42, 0.5);
        border-radius: 8px;
        padding: 12px;
        color: #94a3b8;
        white-space: pre-wrap;
        word-break: break-all;
        font-size: 12px;
        max-height: 300px;
        overflow-y: auto;
    }
    
    /* 自动刷新开关 */
    .toggle-switch {
        position: relative;
        width: 44px;
        height: 24px;
        background: #334155;
        border-radius: 12px;
        cursor: pointer;
        transition: background 0.2s;
    }
    
    .toggle-switch.active {
        background: linear-gradient(to right, #3b82f6, #06b6d4);
    }
    
    .toggle-switch::after {
        content: '';
        position: absolute;
        width: 20px;
        height: 20px;
        background: white;
        border-radius: 50%;
        top: 2px;
        left: 2px;
        transition: transform 0.2s;
    }
    
    .toggle-switch.active::after {
        transform: translateX(20px);
    }
    
    .search-highlight {
        background: rgba(250, 204, 21, 0.3);
        padding: 1px 2px;
        border-radius: 2px;
    }
</style>
@endpush

@section('content')
<div class="stat-card rounded-2xl p-6 max-w-full overflow-hidden">
    <!-- 筛选器 -->
    <div class="flex flex-wrap items-center gap-4 mb-6">
        <!-- 日期 -->
        <div class="flex items-center space-x-2">
            <label class="text-sm text-slate-400 font-medium">日期:</label>
            <input type="date" id="log-date" value="{{ $currentDate }}" 
                   class="bg-slate-800/50 border border-slate-600/50 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all">
        </div>
        
        <!-- 级别 -->
        <div class="flex items-center space-x-2">
            <label class="text-sm text-slate-400 font-medium">级别:</label>
            <select id="log-level" class="bg-slate-800/50 border border-slate-600/50 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all">
                <option value="all" {{ $currentLevel === 'all' ? 'selected' : '' }}>全部</option>
                <option value="error" {{ $currentLevel === 'error' ? 'selected' : '' }}>ERROR</option>
                <option value="warning" {{ $currentLevel === 'warning' ? 'selected' : '' }}>WARNING</option>
                <option value="info" {{ $currentLevel === 'info' ? 'selected' : '' }}>INFO</option>
                <option value="debug" {{ $currentLevel === 'debug' ? 'selected' : '' }}>DEBUG</option>
            </select>
        </div>
        
        <!-- 条目数 -->
        <div class="flex items-center space-x-2">
            <label class="text-sm text-slate-400 font-medium">条目:</label>
            <select id="log-limit" class="bg-slate-800/50 border border-slate-600/50 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all">
                <option value="50" {{ $currentLimit == 50 ? 'selected' : '' }}>50</option>
                <option value="100" {{ $currentLimit == 100 ? 'selected' : '' }}>100</option>
                <option value="200" {{ $currentLimit == 200 ? 'selected' : '' }}>200</option>
                <option value="500" {{ $currentLimit == 500 ? 'selected' : '' }}>500</option>
            </select>
        </div>
        
        <!-- 搜索 -->
        <div class="flex items-center space-x-2 flex-1 min-w-[200px]">
            <label class="text-sm text-slate-400 font-medium">搜索:</label>
            <input type="text" id="log-search" placeholder="输入关键字实时过滤..." 
                   class="flex-1 bg-slate-800/50 border border-slate-600/50 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all">
        </div>
        
        <!-- 自动刷新 -->
        <div class="flex items-center space-x-2">
            <label class="text-sm text-slate-400 font-medium">自动刷新:</label>
            <div id="auto-refresh-toggle" class="toggle-switch" onclick="toggleAutoRefresh()"></div>
            <span id="refresh-countdown" class="text-xs text-slate-500 w-8"></span>
        </div>
        
        <!-- 操作按钮 -->
        <button onclick="filterLogs()" class="px-4 py-2 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 rounded-lg text-sm font-medium transition-all shadow-lg shadow-blue-500/20">
            🔍 筛选
        </button>
        
        <button onclick="refreshLogs()" class="px-4 py-2 bg-slate-700/50 hover:bg-slate-600/50 border border-slate-600/50 rounded-lg text-sm font-medium transition-all">
            🔄 刷新
        </button>
    </div>
    
    <!-- 统计栏 -->
    <div class="flex items-center gap-4 mb-4 text-sm">
        <span class="text-slate-400">共 <span id="log-count" class="text-white font-semibold">{{ count($logs) }}</span> 条日志</span>
        <span class="text-slate-500">|</span>
        <span class="text-slate-400">显示 <span id="visible-count" class="text-white font-semibold">{{ count($logs) }}</span> 条</span>
    </div>
    
    <!-- 日志列表 -->
    <div class="log-container bg-slate-900/30 rounded-xl border border-slate-700/30">
        @forelse ($logs as $index => $log)
        @php
            $levelClass = match(true) {
                in_array($log['level'], ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY']) => 'error',
                $log['level'] === 'WARNING' => 'warning',
                $log['level'] === 'INFO' => 'info',
                default => 'debug'
            };
            $tagClass = $log['tag'] ? 'tag-' . $log['tag'] : 'tag-default';
            $timeOnly = substr($log['time'], 11); // 只显示时间部分
        @endphp
        <div class="log-entry" data-searchable="{{ strtolower($log['time'] . ' ' . $log['level'] . ' ' . ($log['tag'] ?? '') . ' ' . $log['message'] . ' ' . ($log['full_message'] ?? '')) }}">
            <div class="log-summary" onclick="toggleLog(this.parentElement)">
                <span class="time">{{ $timeOnly }}</span>
                <span class="level-badge {{ $levelClass }}">{{ $log['level'] }}</span>
                @if($log['tag'])
                <span class="tag-badge {{ $tagClass }}">{{ $log['tag'] }}</span>
                @endif
                <span class="message">{{ $log['message'] }}</span>
                <span class="expand-icon">▶</span>
            </div>
            <div class="log-detail">
                @if($log['context'] && count($log['context']) > 0)
                <div class="context-table">
                    <div class="text-xs text-slate-500 mb-2 font-semibold">📋 上下文数据</div>
                    @foreach($log['context'] as $key => $value)
                    <div class="context-row">
                        <span class="context-key">{{ $key }}</span>
                        <span class="context-value">{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value }}</span>
                    </div>
                    @endforeach
                </div>
                @endif
                <div class="full-message">{{ $log['full_message'] ?? $log['message'] }}</div>
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
let autoRefreshEnabled = false;
let refreshInterval = null;
let countdown = 30;

function filterLogs() {
    const date = document.getElementById('log-date').value;
    const level = document.getElementById('log-level').value;
    const limit = document.getElementById('log-limit').value;
    window.location.href = `{{ route('admin.logs') }}?date=${date}&level=${level}&limit=${limit}`;
}

function refreshLogs() {
    window.location.reload();
}

function toggleLog(entry) {
    entry.classList.toggle('expanded');
}

// 实时搜索过滤
function searchLogs() {
    const searchTerm = document.getElementById('log-search').value.toLowerCase().trim();
    const entries = document.querySelectorAll('.log-entry');
    let visibleCount = 0;
    
    entries.forEach(entry => {
        const searchable = entry.getAttribute('data-searchable');
        if (!searchTerm || searchable.includes(searchTerm)) {
            entry.classList.remove('hidden-by-search');
            visibleCount++;
        } else {
            entry.classList.add('hidden-by-search');
        }
    });
    
    document.getElementById('visible-count').textContent = visibleCount;
}

// 自动刷新
function toggleAutoRefresh() {
    autoRefreshEnabled = !autoRefreshEnabled;
    const toggle = document.getElementById('auto-refresh-toggle');
    const countdownEl = document.getElementById('refresh-countdown');
    
    if (autoRefreshEnabled) {
        toggle.classList.add('active');
        countdown = 30;
        updateCountdown();
        refreshInterval = setInterval(() => {
            countdown--;
            updateCountdown();
            if (countdown <= 0) {
                window.location.reload();
            }
        }, 1000);
    } else {
        toggle.classList.remove('active');
        countdownEl.textContent = '';
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
    }
}

function updateCountdown() {
    document.getElementById('refresh-countdown').textContent = countdown + 's';
}

// 事件绑定
document.getElementById('log-search').addEventListener('input', searchLogs);
document.getElementById('log-date').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') filterLogs();
});
document.getElementById('log-level').addEventListener('change', filterLogs);
document.getElementById('log-limit').addEventListener('change', filterLogs);
</script>
@endpush
