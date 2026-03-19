@extends('layouts.app')

@push('styles')
<style>
    .eve-glow { box-shadow: 0 0 20px rgba(59, 130, 246, 0.5); }
    @keyframes shimmer { 0% { background-position: -1000px 0; } 100% { background-position: 1000px 0; } }
    .skeleton { background: linear-gradient(90deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.08) 50%, rgba(255,255,255,0.03) 100%); background-size: 1000px 100%; animation: shimmer 2s infinite; border-radius: 4px; }
</style>
@endpush

@section('title', '联系人 - Tus Esi System')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow">
            <h2 class="text-2xl font-semibold mb-6">👥 联系人列表</h2>
            
            <div id="contacts-area">
                <div class="space-y-3">
                    <div class="skeleton h-16 w-full"></div>
                    <div class="skeleton h-16 w-full"></div>
                    <div class="skeleton h-16 w-full"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function formatStanding(value) {
        return (value >= 0 ? '+' : '') + value.toFixed(2);
    }

    function getStandingColor(value) {
        if (value >= 5) return 'text-blue-400';
        if (value > 0) return 'text-green-400';
        if (value < -5) return 'text-red-600';
        if (value < 0) return 'text-red-400';
        return 'text-gray-400';
    }

    function getContactTypeIcon(type) {
        switch(type) {
            case 'character': return '👤';
            case 'corporation': return '🏢';
            case 'alliance': return '⚔️';
            default: return '❓';
        }
    }

    function escapeHtml(s) {
        var div = document.createElement('div');
        div.textContent = s || '';
        return div.innerHTML;
    }

    fetch('/api/dashboard/contacts')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var area = document.getElementById('contacts-area');
            if (data.error) {
                area.innerHTML = '<div class="text-center py-8 text-red-400">' + escapeHtml(data.error) + '</div>';
                return;
            }
            if (!data || data.length === 0) {
                area.innerHTML = '<div class="text-center py-12"><div class="text-6xl mb-4">📭</div><p class="text-xl text-blue-200">暂无联系人</p></div>';
                return;
            }

            var html = '<div class="grid gap-3">';
            data.forEach(function(contact) {
                var blocked = contact.is_blocked ? '<span class="text-xs bg-red-600/50 px-2 py-0.5 rounded ml-2">已拉黑</span>' : '';
                var watched = contact.is_watched ? '<span class="text-xs bg-yellow-600/50 px-2 py-0.5 rounded ml-2">关注中</span>' : '';
                
                html += '<div class="bg-white/5 rounded-lg p-4 hover:bg-white/10 transition-colors flex justify-between items-center">';
                html += '<div class="flex items-center space-x-3">';
                html += '<span class="text-2xl">' + getContactTypeIcon(contact.contact_type) + '</span>';
                html += '<div>';
                html += '<div class="font-medium">' + escapeHtml(contact.name) + blocked + watched + '</div>';
                html += '<div class="text-sm text-blue-300/70">' + escapeHtml(contact.contact_type) + '</div>';
                html += '</div></div>';
                html += '<div class="text-right">';
                html += '<div class="' + getStandingColor(contact.standing) + ' font-bold text-lg">' + formatStanding(contact.standing) + '</div>';
                html += '</div></div>';
            });
            html += '</div>';
            area.innerHTML = html;
        })
        .catch(function() {
            document.getElementById('contacts-area').innerHTML = '<div class="text-center py-8 text-red-400">加载失败</div>';
        });
</script>
@endpush
