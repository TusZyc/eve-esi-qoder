@extends('layouts.admin')

@section('title', '用户管理')
@section('page-title', '用户管理')

@push('styles')
<style>
    .refresh-btn {
        transition: all 0.2s ease;
    }
    .refresh-btn:hover:not(:disabled) {
        transform: scale(1.05);
    }
    .refresh-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    .spinning {
        animation: spin 1s linear infinite;
    }
</style>
@endpush

@section('content')
<div class="stat-card rounded-xl p-6">
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-semibold flex items-center">
            <span class="mr-2">👥</span> 所有用户
        </h3>
        <p class="text-sm text-slate-400">共 {{ $users->total() }} 个用户</p>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="text-left text-slate-400 text-sm border-b border-slate-700">
                    <th class="pb-3">头像</th>
                    <th class="pb-3">角色名</th>
                    <th class="pb-3">角色 ID</th>
                    <th class="pb-3">军团 ID</th>
                    <th class="pb-3">授权状态</th>
                    <th class="pb-3">当前状态</th>
                    <th class="pb-3">最后活跃</th>
                    <th class="pb-3">操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $u)
                <tr class="border-b border-slate-700/50 hover:bg-slate-800/30" id="user-row-{{ $u->id }}">
                    <td class="py-3">
                        @if($u->eve_character_id)
                        <img src="https://image.evepc.163.com/Character/{{ $u->eve_character_id }}_64.jpg" 
                             alt="{{ $u->name }}" class="w-10 h-10 rounded-full">
                        @else
                        <div class="w-10 h-10 rounded-full bg-slate-700 flex items-center justify-center">
                            <span class="text-slate-400">?</span>
                        </div>
                        @endif
                    </td>
                    <td class="py-3 font-medium">{{ $u->name ?? '-' }}</td>
                    <td class="py-3 text-slate-400 font-mono text-sm">{{ $u->eve_character_id ?? '-' }}</td>
                    <td class="py-3 text-slate-400 font-mono text-sm">{{ $u->corporation_id ?? '-' }}</td>
                    <td class="py-3" id="auth-status-{{ $u->id }}">
                        @if($u->eve_character_id)
                            @if($u->auth_status === 'valid')
                                <span class="text-xs px-2 py-1 rounded bg-green-500/20 text-green-400">有效</span>
                            @elseif($u->auth_status === 'expired')
                                <span class="text-xs px-2 py-1 rounded bg-red-500/20 text-red-400">已过期</span>
                            @else
                                <span class="text-xs px-2 py-1 rounded bg-slate-500/20 text-slate-400">{{ $u->auth_status_text }}</span>
                            @endif
                        @else
                            <span class="text-xs px-2 py-1 rounded bg-slate-500/20 text-slate-400">未授权</span>
                        @endif
                    </td>
                    <td class="py-3" id="current-status-{{ $u->id }}">
                        @if($u->eve_character_id)
                            @if($u->current_status === 'valid')
                                <span class="text-xs px-2 py-1 rounded bg-green-500/20 text-green-400">有效</span>
                            @elseif($u->current_status === 'expiring')
                                <span class="text-xs px-2 py-1 rounded bg-yellow-500/20 text-yellow-400">即将过期</span>
                            @else
                                <span class="text-xs px-2 py-1 rounded bg-red-500/20 text-red-400">已过期</span>
                            @endif
                        @else
                            <span class="text-xs px-2 py-1 rounded bg-slate-500/20 text-slate-400">-</span>
                        @endif
                    </td>
                    <td class="py-3 text-slate-400 text-sm">
                        {{ $u->updated_at ? $u->updated_at->diffForHumans() : '-' }}
                    </td>
                    <td class="py-3">
                        @if($u->eve_character_id && $u->refresh_token)
                        <button onclick="refreshToken({{ $u->id }})" 
                                id="refresh-btn-{{ $u->id }}"
                                class="refresh-btn px-3 py-1 text-xs bg-blue-600 hover:bg-blue-700 rounded text-white">
                            🔄 刷新
                        </button>
                        @else
                        <span class="text-xs text-slate-500">-</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="py-8 text-center text-slate-400">暂无用户数据</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    @if($users->hasPages())
    <div class="mt-6">
        {{ $users->links() }}
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
async function refreshToken(userId) {
    const btn = document.getElementById(`refresh-btn-${userId}`);
    const originalText = btn.innerHTML;
    
    // 显示加载状态
    btn.disabled = true;
    btn.innerHTML = '<span class="spinning inline-block">⏳</span> 刷新中...';
    
    try {
        const response = await fetch(`/api/admin/users/${userId}/refresh-token`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            // 更新状态显示
            const authStatusEl = document.getElementById(`auth-status-${userId}`);
            const currentStatusEl = document.getElementById(`current-status-${userId}`);
            
            authStatusEl.innerHTML = '<span class="text-xs px-2 py-1 rounded bg-green-500/20 text-green-400">有效</span>';
            currentStatusEl.innerHTML = '<span class="text-xs px-2 py-1 rounded bg-green-500/20 text-green-400">有效</span>';
            
            btn.innerHTML = '✅ 成功';
            btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
            btn.classList.add('bg-green-600');
            
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.classList.remove('bg-green-600');
                btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                btn.disabled = false;
            }, 2000);
        } else {
            throw new Error(result.message || '刷新失败');
        }
    } catch (error) {
        btn.innerHTML = '❌ 失败';
        btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
        btn.classList.add('bg-red-600');
        
        alert('Token 刷新失败: ' + error.message);
        
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.classList.remove('bg-red-600');
            btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
            btn.disabled = false;
        }, 2000);
    }
}
</script>
@endpush
