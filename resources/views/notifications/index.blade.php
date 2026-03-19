@extends('layouts.app')

@push('styles')
<style>
    .eve-glow { box-shadow: 0 0 20px rgba(59, 130, 246, 0.5); }
    @keyframes shimmer { 0% { background-position: -1000px 0; } 100% { background-position: 1000px 0; } }
    .skeleton { background: linear-gradient(90deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.08) 50%, rgba(255,255,255,0.03) 100%); background-size: 1000px 100%; animation: shimmer 2s infinite; border-radius: 4px; }
</style>
@endpush

@section('title', '提醒 - Tus Esi System')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow">
            <h2 class="text-2xl font-semibold mb-6">🔔 游戏内提醒</h2>
            
            <div id="notifications-area">
                <div class="space-y-3">
                    <div class="skeleton h-20 w-full"></div>
                    <div class="skeleton h-20 w-full"></div>
                    <div class="skeleton h-20 w-full"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function formatDate(dateStr) {
        if (!dateStr) return '-';
        var d = new Date(dateStr);
        return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0') + ' ' + String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
    }

    function escapeHtml(s) {
        var div = document.createElement('div');
        div.textContent = s || '';
        return div.innerHTML;
    }

    fetch('/api/dashboard/notifications')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var area = document.getElementById('notifications-area');
            if (data.error) {
                area.innerHTML = '<div class="text-center py-8 text-red-400">' + escapeHtml(data.error) + '</div>';
                return;
            }
            if (!data || data.length === 0) {
                area.innerHTML = '<div class="text-center py-12"><div class="text-6xl mb-4">📭</div><p class="text-xl text-blue-200">暂无提醒</p></div>';
                return;
            }

            var html = '<div class="space-y-3">';
            data.forEach(function(notif) {
                var readClass = notif.is_read ? 'opacity-60' : '';
                var unreadDot = notif.is_read ? '' : '<span class="w-2 h-2 bg-blue-500 rounded-full mr-2"></span>';
                
                html += '<div class="bg-white/5 rounded-lg p-4 hover:bg-white/10 transition-colors ' + readClass + '">';
                html += '<div class="flex items-start justify-between mb-2">';
                html += '<div class="flex items-center">' + unreadDot + '<span class="font-medium">' + escapeHtml(notif.type_name) + '</span></div>';
                html += '<span class="text-sm text-blue-300/70">' + formatDate(notif.timestamp) + '</span>';
                html += '</div>';
                if (notif.text) {
                    html += '<div class="text-sm text-blue-200/80 bg-white/5 rounded p-2 mt-2 whitespace-pre-wrap">' + escapeHtml(notif.text.substring(0, 500)) + '</div>';
                }
                html += '</div>';
            });
            html += '</div>';
            area.innerHTML = html;
        })
        .catch(function() {
            document.getElementById('notifications-area').innerHTML = '<div class="text-center py-8 text-red-400">加载失败</div>';
        });
</script>
@endpush
