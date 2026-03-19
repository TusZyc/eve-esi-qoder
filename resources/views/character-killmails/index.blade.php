@extends('layouts.app')

@push('styles')
<style>
    .eve-glow { box-shadow: 0 0 20px rgba(59, 130, 246, 0.5); }
    @keyframes shimmer { 0% { background-position: -1000px 0; } 100% { background-position: 1000px 0; } }
    .skeleton { background: linear-gradient(90deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.08) 50%, rgba(255,255,255,0.03) 100%); background-size: 1000px 100%; animation: shimmer 2s infinite; border-radius: 4px; }
    .km-kill { border-left: 3px solid #22c55e; }
    .km-loss { border-left: 3px solid #ef4444; }
</style>
@endpush

@section('title', '击毁报告 - Tus Esi System')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow">
            <h2 class="text-2xl font-semibold mb-6">⚔️ 我的击毁报告</h2>
            
            <div id="killmails-area">
                <div class="space-y-3">
                    <div class="skeleton h-24 w-full"></div>
                    <div class="skeleton h-24 w-full"></div>
                    <div class="skeleton h-24 w-full"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function formatNumber(n) { return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
    function formatDate(dateStr) {
        if (!dateStr) return '-';
        var d = new Date(dateStr);
        return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0') + ' ' + String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
    }
    function escapeHtml(s) { var div = document.createElement('div'); div.textContent = s || ''; return div.innerHTML; }

    fetch('/api/dashboard/killmails')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var area = document.getElementById('killmails-area');
            if (data.error) {
                area.innerHTML = '<div class="text-center py-8 text-red-400">' + escapeHtml(data.error) + '</div>';
                return;
            }
            if (!data || data.length === 0) {
                area.innerHTML = '<div class="text-center py-12"><div class="text-6xl mb-4">🕊️</div><p class="text-xl text-blue-200">暂无击毁报告</p><p class="text-sm text-blue-300/70 mt-2">需要重新授权获取击毁报告权限</p></div>';
                return;
            }

            var html = '<div class="space-y-3">';
            data.forEach(function(km) {
                var isLoss = km.is_victim;
                var borderClass = isLoss ? 'km-loss' : 'km-kill';
                var typeLabel = isLoss ? '<span class="text-red-400 font-bold">损失</span>' : '<span class="text-green-400 font-bold">击杀</span>';
                
                html += '<div class="bg-white/5 rounded-lg p-4 hover:bg-white/10 transition-colors ' + borderClass + '">';
                html += '<div class="flex justify-between items-start">';
                html += '<div class="flex-1">';
                html += '<div class="flex items-center space-x-2 mb-2">' + typeLabel + '<span class="text-sm text-blue-300/70">' + formatDate(km.killmail_time) + '</span></div>';
                
                if (km.victim) {
                    html += '<div class="mb-2">';
                    html += '<div class="font-medium">' + (isLoss ? '我的舰船' : '击毁目标') + ': <span class="text-yellow-400">' + escapeHtml(km.victim.ship_type_name || '未知') + '</span></div>';
                    if (!isLoss && km.victim.character_name) {
                        html += '<div class="text-sm text-blue-300/70">驾驶员: ' + escapeHtml(km.victim.character_name) + '</div>';
                    }
                    html += '</div>';
                }
                
                if (km.final_blow && !isLoss) {
                    html += '<div class="text-sm text-blue-300/70">最后一击: ' + escapeHtml(km.final_blow.character_name || 'NPC') + '</div>';
                } else if (km.final_blow && isLoss) {
                    html += '<div class="text-sm text-blue-300/70">击杀者: ' + escapeHtml(km.final_blow.character_name || 'NPC') + ' (' + escapeHtml(km.final_blow.ship_type_name || '') + ')</div>';
                }
                
                html += '<div class="text-sm text-blue-300/70">位置: ' + escapeHtml(km.solar_system_name || '未知') + '</div>';
                html += '<div class="text-sm text-blue-300/70">参与人数: ' + km.attacker_count + '</div>';
                
                html += '</div>';
                html += '<a href="' + km.zkb_url + '" target="_blank" class="text-blue-400 hover:text-blue-300 text-sm">zkillboard →</a>';
                html += '</div></div>';
            });
            html += '</div>';
            area.innerHTML = html;
        })
        .catch(function() {
            document.getElementById('killmails-area').innerHTML = '<div class="text-center py-8 text-red-400">加载失败</div>';
        });
</script>
@endpush
