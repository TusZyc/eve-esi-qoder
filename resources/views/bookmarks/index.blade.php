@extends('layouts.app')

@push('styles')
<style>
    .eve-glow { box-shadow: 0 0 20px rgba(59, 130, 246, 0.5); }
    @keyframes shimmer { 0% { background-position: -1000px 0; } 100% { background-position: 1000px 0; } }
    .skeleton { background: linear-gradient(90deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.08) 50%, rgba(255,255,255,0.03) 100%); background-size: 1000px 100%; animation: shimmer 2s infinite; border-radius: 4px; }
</style>
@endpush

@section('title', '保存的地点 - Tus Esi System')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow">
            <h2 class="text-2xl font-semibold mb-6">📍 保存的地点</h2>
            
            <div id="bookmarks-area">
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

    fetch('/api/dashboard/bookmarks')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var area = document.getElementById('bookmarks-area');
            if (data.error) {
                area.innerHTML = '<div class="text-center py-8 text-red-400">' + escapeHtml(data.error) + '</div>';
                return;
            }
            
            var bookmarks = data.bookmarks || [];
            if (bookmarks.length === 0) {
                area.innerHTML = '<div class="text-center py-12"><div class="text-6xl mb-4">📭</div><p class="text-xl text-blue-200">暂无保存的地点</p></div>';
                return;
            }

            // 按文件夹分组
            var folders = {};
            bookmarks.forEach(function(bm) {
                var folderName = bm.folder_name || '个人地点';
                if (!folders[folderName]) folders[folderName] = [];
                folders[folderName].push(bm);
            });

            var html = '';
            Object.keys(folders).sort().forEach(function(folderName) {
                var items = folders[folderName];
                html += '<div class="mb-6">';
                html += '<h3 class="text-lg font-semibold mb-3 flex items-center"><span class="mr-2">📁</span>' + escapeHtml(folderName) + ' <span class="text-sm text-blue-300 ml-2">(' + items.length + ')</span></h3>';
                html += '<div class="space-y-2 pl-4">';
                items.forEach(function(bm) {
                    html += '<div class="bg-white/5 rounded-lg p-4 hover:bg-white/10 transition-colors">';
                    html += '<div class="flex justify-between items-start">';
                    html += '<div class="flex-1 min-w-0">';
                    html += '<div class="font-medium truncate">' + escapeHtml(bm.label || '未命名') + '</div>';
                    html += '<div class="text-sm text-blue-300/70">' + escapeHtml(bm.solar_system_name) + '</div>';
                    if (bm.notes) {
                        html += '<div class="text-sm text-blue-200/60 mt-1">' + escapeHtml(bm.notes) + '</div>';
                    }
                    html += '</div>';
                    html += '<div class="text-xs text-blue-300/50 ml-4">' + formatDate(bm.created) + '</div>';
                    html += '</div></div>';
                });
                html += '</div></div>';
            });
            area.innerHTML = html;
        })
        .catch(function() {
            document.getElementById('bookmarks-area').innerHTML = '<div class="text-center py-8 text-red-400">加载失败</div>';
        });
</script>
@endpush
