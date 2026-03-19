@extends('layouts.app')

@push('styles')
<style>
    .eve-glow { box-shadow: 0 0 20px rgba(59, 130, 246, 0.5); }
    @keyframes shimmer { 0% { background-position: -1000px 0; } 100% { background-position: 1000px 0; } }
    .skeleton { background: linear-gradient(90deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.08) 50%, rgba(255,255,255,0.03) 100%); background-size: 1000px 100%; animation: shimmer 2s infinite; border-radius: 4px; }
    .fitting-card { transition: all 0.3s; }
    .fitting-card.expanded { background: rgba(255,255,255,0.1); }
    .slot-section { border-left: 3px solid; padding-left: 12px; margin-bottom: 8px; }
    .slot-high { border-color: #ef4444; }
    .slot-med { border-color: #3b82f6; }
    .slot-low { border-color: #22c55e; }
    .slot-rig { border-color: #a855f7; }
    .slot-drone { border-color: #f59e0b; }
</style>
@endpush

@section('title', '装配 - Tus Esi System')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow">
            <h2 class="text-2xl font-semibold mb-6">🔧 装配方案</h2>
            
            <div id="fittings-area">
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
    var fittingsData = [];
    var expandedFitting = null;

    function escapeHtml(s) { var div = document.createElement('div'); div.textContent = s || ''; return div.innerHTML; }

    function toggleFitting(idx) {
        if (expandedFitting === idx) {
            expandedFitting = null;
        } else {
            expandedFitting = idx;
        }
        renderFittings();
    }

    function renderSlotSection(title, items, slotClass) {
        if (!items || items.length === 0) return '';
        var html = '<div class="slot-section ' + slotClass + '">';
        html += '<div class="text-sm font-medium text-blue-300 mb-1">' + title + '</div>';
        items.forEach(function(item) {
            html += '<div class="text-sm text-blue-200/80 pl-2">' + escapeHtml(item.type_name);
            if (item.quantity > 1) html += ' <span class="text-blue-400">x' + item.quantity + '</span>';
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    function renderFittings() {
        var area = document.getElementById('fittings-area');
        if (!fittingsData || fittingsData.length === 0) {
            area.innerHTML = '<div class="text-center py-12"><div class="text-6xl mb-4">📭</div><p class="text-xl text-blue-200">暂无保存的装配</p></div>';
            return;
        }

        var html = '<div class="space-y-3">';
        fittingsData.forEach(function(fitting, idx) {
            var isExpanded = expandedFitting === idx;
            var expandedClass = isExpanded ? 'expanded' : '';
            
            html += '<div class="fitting-card bg-white/5 rounded-lg overflow-hidden ' + expandedClass + '">';
            html += '<div class="p-4 cursor-pointer hover:bg-white/5" onclick="toggleFitting(' + idx + ')">';
            html += '<div class="flex justify-between items-center">';
            html += '<div class="flex items-center space-x-3">';
            html += '<span class="text-2xl">🚀</span>';
            html += '<div>';
            html += '<div class="font-medium">' + escapeHtml(fitting.name) + '</div>';
            html += '<div class="text-sm text-blue-300/70">' + escapeHtml(fitting.ship_type_name) + '</div>';
            html += '</div></div>';
            html += '<span class="text-blue-300">' + (isExpanded ? '▲' : '▼') + '</span>';
            html += '</div></div>';
            
            if (isExpanded) {
                var slots = fitting.slots || {};
                html += '<div class="px-4 pb-4 border-t border-white/10 pt-4">';
                if (fitting.description) {
                    html += '<div class="text-sm text-blue-200/60 mb-4 italic">' + escapeHtml(fitting.description) + '</div>';
                }
                html += '<div class="grid md:grid-cols-2 gap-4">';
                html += '<div>';
                html += renderSlotSection('高槽', slots.high, 'slot-high');
                html += renderSlotSection('中槽', slots.med, 'slot-med');
                html += renderSlotSection('低槽', slots.low, 'slot-low');
                html += '</div>';
                html += '<div>';
                html += renderSlotSection('改装件', slots.rig, 'slot-rig');
                html += renderSlotSection('无人机', slots.drone, 'slot-drone');
                html += renderSlotSection('货柜', slots.cargo, 'slot-drone');
                if (slots.subsystem && slots.subsystem.length > 0) html += renderSlotSection('子系统', slots.subsystem, 'slot-rig');
                if (slots.fighter && slots.fighter.length > 0) html += renderSlotSection('舰载机', slots.fighter, 'slot-drone');
                html += '</div>';
                html += '</div></div>';
            }
            html += '</div>';
        });
        html += '</div>';
        area.innerHTML = html;
    }

    fetch('/api/dashboard/fittings')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) {
                document.getElementById('fittings-area').innerHTML = '<div class="text-center py-8 text-red-400">' + escapeHtml(data.error) + '</div>';
                return;
            }
            fittingsData = data;
            renderFittings();
        })
        .catch(function() {
            document.getElementById('fittings-area').innerHTML = '<div class="text-center py-8 text-red-400">加载失败</div>';
        });
</script>
@endpush
