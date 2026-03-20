@extends('layouts.app')

@push('styles')
<style>
    @keyframes shimmer { 0% { background-position: -1000px 0; } 100% { background-position: 1000px 0; } }
    .skeleton { background: linear-gradient(90deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.08) 50%, rgba(255,255,255,0.03) 100%); background-size: 1000px 100%; animation: shimmer 2s infinite; border-radius: 4px; }
    
    .slot-section { border-left: 3px solid; padding-left: 12px; margin-bottom: 8px; }
    .slot-high { border-color: #ef4444; }
    .slot-med { border-color: #3b82f6; }
    .slot-low { border-color: #22c55e; }
    .slot-rig { border-color: #a855f7; }
    .slot-drone { border-color: #f59e0b; }
    
    .ship-group-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 1rem;
        background: rgba(255, 255, 255, 0.08);
        border-radius: 0.5rem;
        cursor: pointer;
        transition: background 0.2s;
        margin-bottom: 2px;
    }
    .ship-group-header:hover {
        background: rgba(255, 255, 255, 0.12);
    }
    .ship-group-header.expanded {
        background: rgba(59, 130, 246, 0.2);
        border-radius: 0.5rem 0.5rem 0 0;
    }
    .ship-group-count {
        font-size: 0.75rem;
        background: rgba(255, 255, 255, 0.15);
        padding: 0.125rem 0.5rem;
        border-radius: 9999px;
        color: rgba(147, 197, 253, 0.9);
    }
    .fitting-tree-item {
        display: flex;
        align-items: center;
        padding: 0.625rem 1rem 0.625rem 2.5rem;
        cursor: pointer;
        transition: background 0.2s;
        border-left: 1px solid rgba(255, 255, 255, 0.1);
        margin-left: 1rem;
    }
    .fitting-tree-item:hover {
        background: rgba(255, 255, 255, 0.06);
    }
    .fitting-tree-item:last-child {
        border-radius: 0 0 0 0.5rem;
    }
    .fitting-tree-item.expanded {
        background: rgba(255, 255, 255, 0.05);
    }
    .fitting-details {
        margin-left: 1rem;
        padding: 1rem 1rem 1rem 2.5rem;
        background: rgba(255, 255, 255, 0.03);
        border-left: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 0 0 0 0.5rem;
    }
    .tree-arrow {
        display: inline-block;
        width: 1rem;
        text-align: center;
        margin-right: 0.5rem;
        color: rgba(147, 197, 253, 0.7);
        font-size: 0.75rem;
    }
    .tree-connector {
        color: rgba(147, 197, 253, 0.4);
        margin-right: 0.5rem;
    }
</style>
@endpush

@section('title', '装配 - Tus Esi System')

@section('content')
    <div class="max-w-5xl mx-auto px-4 py-8">
        <h2 class="text-2xl font-semibold mb-6">🔧 装配方案</h2>
        
        <div class="stat-card rounded-xl p-6">
            <div id="fittings-area">
                <div class="space-y-3">
                    <div class="skeleton h-12 w-full"></div>
                    <div class="skeleton h-12 w-full"></div>
                    <div class="skeleton h-12 w-full"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    var fittingsData = [];
    var shipGroups = {};
    var expandedShips = {};
    var expandedFitting = null;

    function escapeHtml(s) { 
        var div = document.createElement('div'); 
        div.textContent = s || ''; 
        return div.innerHTML; 
    }

    function toggleShipGroup(shipName) {
        expandedShips[shipName] = !expandedShips[shipName];
        // 折叠舰船组时，也折叠其下的配装详情
        if (!expandedShips[shipName]) {
            shipGroups[shipName].forEach(function(f) {
                if (expandedFitting === f.fitting_id) expandedFitting = null;
            });
        }
        renderFittings();
    }

    function toggleFitting(fittingId) {
        if (expandedFitting === fittingId) {
            expandedFitting = null;
        } else {
            expandedFitting = fittingId;
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

    function buildShipGroups() {
        shipGroups = {};
        fittingsData.forEach(function(fitting) {
            var shipName = fitting.ship_type_name || '未知舰船';
            if (!shipGroups[shipName]) shipGroups[shipName] = [];
            shipGroups[shipName].push(fitting);
        });
    }

    function renderFittings() {
        var area = document.getElementById('fittings-area');
        
        if (!fittingsData || fittingsData.length === 0) {
            area.innerHTML = '<div class="text-center py-12"><div class="text-5xl mb-4">📭</div><p class="text-lg text-blue-200/70">暂无保存的装配</p></div>';
            return;
        }

        // 按舰船名称排序
        var sortedShipNames = Object.keys(shipGroups).sort();
        
        var html = '<div class="space-y-2">';
        
        sortedShipNames.forEach(function(shipName) {
            var fittings = shipGroups[shipName];
            var isShipExpanded = expandedShips[shipName];
            
            // 舰船类型行
            html += '<div class="ship-group">';
            html += '<div class="ship-group-header ' + (isShipExpanded ? 'expanded' : '') + '" onclick="toggleShipGroup(\'' + escapeHtml(shipName).replace(/'/g, "\\'") + '\')">';
            html += '<div class="flex items-center">';
            html += '<span class="tree-arrow">' + (isShipExpanded ? '▼' : '▶') + '</span>';
            html += '<span class="text-xl mr-2">🚀</span>';
            html += '<span class="font-medium">' + escapeHtml(shipName) + '</span>';
            html += '</div>';
            html += '<span class="ship-group-count">' + fittings.length + ' 个配装</span>';
            html += '</div>';
            
            // 配装子项
            if (isShipExpanded) {
                html += '<div class="ship-fittings">';
                fittings.forEach(function(fitting, idx) {
                    var isFittingExpanded = expandedFitting === fitting.fitting_id;
                    var isLast = idx === fittings.length - 1 && !isFittingExpanded;
                    
                    html += '<div class="fitting-tree-item ' + (isFittingExpanded ? 'expanded' : '') + '" onclick="toggleFitting(' + fitting.fitting_id + ')">';
                    html += '<span class="tree-connector">' + (idx === fittings.length - 1 ? '└' : '├') + '─</span>';
                    html += '<span class="tree-arrow">' + (isFittingExpanded ? '▼' : '▶') + '</span>';
                    html += '<span class="flex-1">' + escapeHtml(fitting.name) + '</span>';
                    html += '</div>';
                    
                    // 配装详情
                    if (isFittingExpanded) {
                        var slots = fitting.slots || {};
                        html += '<div class="fitting-details">';
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
                });
                html += '</div>';
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
            fittingsData = data || [];
            buildShipGroups();
            renderFittings();
        })
        .catch(function() {
            document.getElementById('fittings-area').innerHTML = '<div class="text-center py-8 text-red-400">加载失败</div>';
        });
</script>
@endpush
