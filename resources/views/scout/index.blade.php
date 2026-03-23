@extends('layouts.app')

@section('title', '斥候工具 - 扫描分析')
@section('page-title', '🔭 斥候工具')

@section('content')
<style>
    /* 斥候工具专用样式 - 避免stat-card的白光线问题 */
    .scout-panel {
        background: rgba(30, 41, 59, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 16px;
    }
    .scout-panel:hover {
        border-color: rgba(255, 255, 255, 0.12);
    }
    .scout-input {
        background: rgba(15, 23, 42, 0.6);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 12px;
        color: #e2e8f0;
        transition: all 0.2s ease;
    }
    .scout-input:focus {
        border-color: rgba(59, 130, 246, 0.5);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        outline: none;
    }
    .scout-select {
        background: rgba(15, 23, 42, 0.6);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 12px;
        color: #e2e8f0;
        padding: 12px 16px;
        transition: all 0.2s ease;
    }
    .scout-select:focus {
        border-color: rgba(59, 130, 246, 0.5);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        outline: none;
    }
    .scout-btn {
        background: rgba(59, 130, 246, 0.15);
        border: 1px solid rgba(59, 130, 246, 0.3);
        border-radius: 12px;
        color: #60a5fa;
        padding: 12px 24px;
        font-weight: 500;
        transition: all 0.2s ease;
    }
    .scout-btn:hover {
        background: rgba(59, 130, 246, 0.25);
        border-color: rgba(59, 130, 246, 0.5);
    }
    .step-badge {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: rgba(59, 130, 246, 0.2);
        color: #60a5fa;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: bold;
        flex-shrink: 0;
    }
    .kbd {
        padding: 2px 8px;
        background: rgba(51, 65, 85, 0.5);
        border-radius: 4px;
        font-size: 12px;
        font-family: monospace;
    }
</style>

<div class="max-w-4xl mx-auto">
    <!-- 标题卡片 -->
    <div class="scout-panel p-6 mb-6">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center shadow-lg">
                <span class="text-2xl">🔭</span>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-white">斥候工具</h1>
                <p class="text-slate-400 text-sm">舰载扫描器结果分析 · 快速统计太空物体分布</p>
            </div>
        </div>
    </div>

    <!-- 输入表单 -->
    <form action="{{ route('scout.store') }}" method="POST" class="scout-panel p-6 mb-6">
        @csrf
        
        <!-- 输入区域 -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-slate-300 mb-2">
                📋 扫描数据
            </label>
            <textarea 
                name="clipboard_data" 
                id="clipboard_data"
                rows="12"
                class="scout-input w-full p-4 placeholder-slate-500 resize-none font-mono text-sm"
                placeholder="在此粘贴扫描结果...

打开舰载扫描器 → 选中一行 → CTRL+A 全选 → CTRL+C 复制 → 在此粘贴"
                required
            >{{ old('clipboard_data') }}</textarea>
            
            @error('clipboard_data')
            <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- 保存时间选择 -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-slate-300 mb-2">
                ⏱️ 保存时间
            </label>
            <select name="retention" class="scout-select w-full md:w-64">
                @foreach ($retentionOptions as $hours => $label)
                <option value="{{ $hours }}" {{ $hours == $defaultRetention ? 'selected' : '' }}>
                    {{ $label }}
                </option>
                @endforeach
            </select>
            <p class="mt-2 text-xs text-slate-500">到期后数据将自动删除，选择"永久"则不会自动清理</p>
        </div>

        <!-- 错误信息 -->
        @error('error')
        <div class="mb-6 p-4 bg-red-500/10 border border-red-500/30 rounded-xl">
            <p class="text-red-400 text-sm">{{ $message }}</p>
        </div>
        @enderror

        <!-- 提交按钮 -->
        <div class="flex items-center gap-4">
            <button type="submit" class="scout-btn">
                🔍 分析扫描结果
            </button>
            <button 
                type="button"
                onclick="clearInput()"
                class="px-6 py-3 rounded-xl text-slate-400 hover:text-white hover:bg-slate-700/50 transition-all"
            >
                清空
            </button>
        </div>
    </form>

    <!-- 使用说明 -->
    <div class="scout-panel p-6">
        <h2 class="text-lg font-semibold text-slate-200 mb-4 flex items-center gap-2">
            <span>📖</span>
            <span>使用说明</span>
        </h2>
        <div class="space-y-3 text-slate-400 text-sm">
            <div class="flex items-start gap-3">
                <span class="step-badge">1</span>
                <p>在游戏中打开舰载扫描器（默认快捷键 <kbd class="kbd">Alt+D</kbd>），点击扫描按钮</p>
            </div>
            <div class="flex items-start gap-3">
                <span class="step-badge">2</span>
                <p>在扫描结果列表中，点击任意一行选中</p>
            </div>
            <div class="flex items-start gap-3">
                <span class="step-badge">3</span>
                <p>按 <kbd class="kbd">CTRL+A</kbd> 全选所有内容</p>
            </div>
            <div class="flex items-start gap-3">
                <span class="step-badge">4</span>
                <p>按 <kbd class="kbd">CTRL+C</kbd> 复制到剪贴板</p>
            </div>
            <div class="flex items-start gap-3">
                <span class="step-badge">5</span>
                <p>在上方的输入框中按 <kbd class="kbd">CTRL+V</kbd> 粘贴，选择保存时间，点击"分析扫描结果"</p>
            </div>
        </div>

        <div class="mt-6 p-4 bg-blue-500/10 border border-blue-500/30 rounded-xl">
            <p class="text-blue-300 text-sm">
                💡 <strong>提示：</strong>生成的结果页面可以分享给队友，让他们快速了解当前星系的舰船分布情况
            </p>
        </div>
    </div>
</div>

@push('scripts')
<script>
// 自动聚焦并监听粘贴
document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('clipboard_data');
    
    // 监听粘贴事件
    document.addEventListener('paste', function(e) {
        // 如果焦点不在文本框内，自动聚焦并粘贴
        if (document.activeElement !== textarea) {
            textarea.focus();
            // 让默认的粘贴行为发生
        }
    });
});

function clearInput() {
    document.getElementById('clipboard_data').value = '';
    document.getElementById('clipboard_data').focus();
}
</script>
@endpush
@endsection