@extends('layouts.app')

@section('page-title', '装配模拟器')

@push('styles')
<style>
    /* 适配app布局的内容区域 */
    .fitting-page-wrapper {
        position: relative;
        height: calc(100vh - 180px);
        min-height: 600px;
        padding: 12px;
        background: transparent;
        overflow: hidden;
    }
    
    /* 三列等宽布局 */
    .fitting-layout {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 12px;
        height: 100%;
        box-sizing: border-box;
    }
    
    @media (max-width: 1200px) {
        .fitting-layout {
            grid-template-columns: 1fr 1fr;
        }
    }
    
    @media (max-width: 992px) {
        .fitting-layout {
            grid-template-columns: 1fr 1fr;
            height: auto;
        }
    }
    
    @media (max-width: 768px) {
        .fitting-layout {
            grid-template-columns: 1fr;
        }
    }
    
    /* 面板样式 */
    .panel {
        background: rgba(15, 23, 42, 0.7);
        border: 1px solid rgba(59, 130, 246, 0.2);
        border-radius: 8px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    
    .panel-header {
        padding: 10px 12px;
        background: linear-gradient(90deg, rgba(59, 130, 246, 0.15) 0%, transparent 100%);
        border-bottom: 1px solid rgba(59, 130, 246, 0.1);
        font-weight: 600;
        font-size: 13px;
        color: #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .panel-body {
        flex: 1;
        overflow-y: auto;
        padding: 8px;
    }
    
    /* 标签切换 */
    .tab-switcher {
        display: flex;
        gap: 4px;
        padding: 4px;
        background: rgba(30, 41, 59, 0.5);
        border-radius: 6px;
        margin-bottom: 8px;
    }
    
    .tab-btn {
        flex: 1;
        padding: 8px 12px;
        font-size: 12px;
        font-weight: 500;
        text-align: center;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s;
        color: #94a3b8;
    }
    
    .tab-btn:hover {
        background: rgba(59, 130, 246, 0.1);
    }
    
    .tab-btn.active {
        background: rgba(59, 130, 246, 0.2);
        color: #3b82f6;
    }
    
    /* 搜索框 */
    .search-input {
        width: 100%;
        background: rgba(30, 41, 59, 0.5);
        border: 1px solid rgba(71, 85, 105, 0.5);
        border-radius: 4px;
        padding: 6px 10px;
        font-size: 12px;
        color: #e2e8f0;
        margin-bottom: 8px;
    }
    
    .search-input:focus {
        outline: none;
        border-color: rgba(59, 130, 246, 0.5);
    }
    
    .search-input::placeholder {
        color: #64748b;
    }
    
    /* 分类 */
    .category-block {
        margin-bottom: 4px;
    }
    
    .category-header {
        padding: 8px 10px;
        font-size: 12px;
        font-weight: 600;
        color: #94a3b8;
        background: rgba(30, 41, 59, 0.5);
        border-radius: 4px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .category-header:hover {
        background: rgba(59, 130, 246, 0.15);
    }
    
    .category-header .toggle {
        font-size: 10px;
        transition: transform 0.2s;
    }
    
    .category-header.expanded .toggle {
        transform: rotate(90deg);
    }
    
    /* 物品行 */
    .item-row {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 8px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.15s;
    }
    
    .item-row:hover {
        background: rgba(59, 130, 246, 0.15);
    }
    
    .item-row.active {
        background: rgba(59, 130, 246, 0.25);
        border-left: 2px solid #3b82f6;
    }
    
    .item-row img {
        width: 22px;
        height: 22px;
        border-radius: 3px;
    }
    
    .item-row .name {
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .item-row .meta {
        font-size: 10px;
        color: #64748b;
    }
    
    /* 四级分类样式 */
    .cat-level-1 { font-weight: 600; color: #94a3b8; }
    .cat-level-2 { padding-left: 8px; font-weight: 500; color: #cbd5e1; }
    .cat-level-3 { padding-left: 16px; font-weight: 400; color: #e2e8f0; }
    .cat-level-4 { padding-left: 24px; font-size: 11px; color: #94a3b8; }
    
    .category-item {
        padding: 6px 8px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.15s;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .category-item:hover {
        background: rgba(59, 130, 246, 0.15);
    }
    
    .category-item.active {
        background: rgba(59, 130, 246, 0.25);
        border-left: 2px solid #3b82f6;
    }
    
    .category-item .toggle-icon {
        font-size: 10px;
        color: #64748b;
        transition: transform 0.2s;
    }
    
    .category-item.expanded .toggle-icon {
        transform: rotate(90deg);
    }
    
    .category-children {
        border-left: 1px solid rgba(71, 85, 105, 0.3);
        margin-left: 8px;
    }
    
    /* 过滤标签 */
    .filter-tabs {
        display: flex;
        gap: 4px;
        margin-bottom: 8px;
        flex-wrap: wrap;
    }
    
    .filter-tab {
        padding: 4px 8px;
        font-size: 11px;
        background: rgba(30, 41, 59, 0.5);
        border: 1px solid rgba(71, 85, 105, 0.3);
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.15s;
    }
    
    .filter-tab:hover {
        background: rgba(59, 130, 246, 0.15);
    }
    
    .filter-tab.active {
        background: rgba(59, 130, 246, 0.25);
        border-color: rgba(59, 130, 246, 0.5);
        color: #3b82f6;
    }
    
    /* 装备结果 */
    .module-results {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    
    .module-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 8px;
        background: rgba(30, 41, 59, 0.3);
        border: 1px solid rgba(71, 85, 105, 0.2);
        border-radius: 4px;
        cursor: pointer;
        font-size: 11px;
        transition: all 0.15s;
    }
    
    .module-item:hover {
        background: rgba(59, 130, 246, 0.15);
        border-color: rgba(59, 130, 246, 0.4);
    }
    
    .module-item img {
        width: 22px;
        height: 22px;
        border-radius: 3px;
    }
    
    .module-item .name {
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .module-item .slot-tag {
        font-size: 10px;
        padding: 2px 6px;
        background: rgba(59, 130, 246, 0.2);
        border-radius: 3px;
        color: #94a3b8;
    }
    
    /* 槽位列表 */
    .slot-section {
        margin-bottom: 12px;
    }
    
    .slot-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 6px 8px;
        background: rgba(30, 41, 59, 0.5);
        border-radius: 4px;
        margin-bottom: 4px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .slot-header .count {
        color: #64748b;
        font-size: 11px;
    }
    
    .slot-list {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    
    .slot-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px;
        background: rgba(30, 41, 59, 0.3);
        border: 1px solid rgba(71, 85, 105, 0.3);
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.15s;
    }
    
    .slot-item:hover {
        border-color: rgba(59, 130, 246, 0.4);
        background: rgba(30, 41, 59, 0.5);
    }
    
    .slot-item.empty {
        opacity: 0.5;
    }
    
    .slot-item.filled {
        border-color: rgba(59, 130, 246, 0.5);
        background: rgba(59, 130, 246, 0.1);
    }
    
    .slot-item .slot-num {
        width: 20px;
        height: 20px;
        background: rgba(59, 130, 246, 0.2);
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        color: #94a3b8;
    }
    
    .slot-item img {
        width: 22px;
        height: 22px;
        border-radius: 3px;
    }
    
    .slot-item .module-name {
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .slot-item .remove-btn {
        width: 18px;
        height: 18px;
        background: rgba(239, 68, 68, 0.2);
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        color: #ef4444;
        opacity: 0;
        transition: opacity 0.15s;
    }
    
    .slot-item:hover .remove-btn {
        opacity: 1;
    }
    
    /* 属性面板 */
    .stats-section {
        padding: 10px;
        border-bottom: 1px solid rgba(59, 130, 246, 0.1);
    }
    
    .stats-section:last-child {
        border-bottom: none;
    }
    
    .stats-section-title {
        font-size: 11px;
        font-weight: 600;
        color: #94a3b8;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .stat-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 3px 0;
        font-size: 12px;
    }
    
    .stat-label {
        color: #94a3b8;
    }
    
    .stat-value {
        color: #3b82f6;
        font-weight: 500;
    }
    
    .stat-value.danger {
        color: #ef4444;
    }
    
    /* 资源条 */
    .resource-bar {
        height: 6px;
        background: rgba(30, 41, 59, 0.8);
        border-radius: 3px;
        overflow: hidden;
        margin-top: 3px;
    }
    
    .resource-bar-fill {
        height: 100%;
        border-radius: 3px;
        transition: width 0.3s;
    }
    
    .resource-bar-fill.cpu { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
    .resource-bar-fill.power { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
    .resource-bar-fill.calibration { background: linear-gradient(90deg, #8b5cf6, #a78bfa); }
    
    /* 抗性条 */
    .resistance-row {
        display: flex;
        gap: 4px;
        margin-top: 4px;
    }
    
    .resistance-item {
        flex: 1;
        text-align: center;
        font-size: 10px;
    }
    
    .resistance-bar {
        height: 4px;
        border-radius: 2px;
        margin-bottom: 2px;
    }
    
    .resistance-bar.em { background: #3b82f6; }
    .resistance-bar.explosive { background: #f59e0b; }
    .resistance-bar.kinetic { background: #8b5cf6; }
    .resistance-bar.thermal { background: #ef4444; }
    
    /* 空状态 */
    .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #64748b;
        text-align: center;
        padding: 20px;
    }
    
    .empty-state .icon {
        font-size: 48px;
        margin-bottom: 12px;
    }
    
    /* 舰船信息头 */
    .ship-info {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .ship-info img {
        width: 24px;
        height: 24px;
        border-radius: 4px;
    }
</style>
@endpush

@section('title', '装配模拟器 - Tus Esi System')
@section('page-title', '装配模拟器')

@section('content')
<div class="fitting-page-wrapper">
<div x-data="fittingSimulator()" x-init="init()" class="fitting-layout">
    
    <!-- 第一列：舰船/装备选择器 -->
    <div class="panel">
        <div class="panel-header">
            <span x-text="activeTab === 'ships' ? '🚀 舰船' : '🔧 装备'"></span>
            <span class="text-xs text-slate-400" x-show="activeTab === 'ships'" x-text="Object.keys(categories).length + ' 类'"></span>
            <span class="text-xs text-slate-400" x-show="activeTab === 'modules'" x-text="moduleResults.length + ' 项'"></span>
        </div>
        <div class="panel-body">
            <!-- 标签切换 -->
            <div class="tab-switcher">
                <div class="tab-btn" :class="{ 'active': activeTab === 'ships' }" @click="activeTab = 'ships'">🚀 舰船</div>
                <div class="tab-btn" :class="{ 'active': activeTab === 'modules' }" @click="activeTab = 'modules'">🔧 装备</div>
            </div>
            
            <!-- 舰船列表 -->
            <div x-show="activeTab === 'ships'">
                <input type="text" x-model="shipSearchQuery" @input="filterShips()" placeholder="搜索舰船..." class="search-input">
                
                <template x-for="(category, key) in filteredCategories" :key="key">
                    <div class="category-block">
                        <div class="category-header" 
                             :class="{ 'expanded': expandedCategories.includes(key) }"
                             @click="toggleCategory(key)">
                            <span x-text="category.name"></span>
                            <span class="toggle">▶</span>
                        </div>
                        <div x-show="expandedCategories.includes(key)" x-collapse>
                            <template x-for="group in category.groups" :key="group.group_id">
                                <div>
                                    <div class="item-row" @click="toggleGroup(group.group_id)" :class="{ 'active': selectedGroup === group.group_id }">
                                        <span class="name" x-text="group.name_cn || group.name"></span>
                                        <span class="meta" x-text="group.count"></span>
                                    </div>
                                    <div x-show="expandedGroups.includes(group.group_id)" x-collapse>
                                        <template x-for="ship in getShipsByGroup(group.group_id)" :key="ship.type_id">
                                            <div class="item-row" style="padding-left: 16px;" @click="selectShip(ship)" :class="{ 'active': selectedShip?.type_id === ship.type_id }">
                                                <img :src="ship.image_url">
                                                <span class="name" x-text="ship.name_cn || ship.name"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
            
            <!-- 装备列表 -->
            <div x-show="activeTab === 'modules'">
                <input type="text" 
                       x-model="moduleSearchQuery" 
                       @input.debounce.300ms="searchModules()"
                       placeholder="搜索装备..."
                       class="search-input">
                
                <div class="filter-tabs">
                    <span class="filter-tab" :class="{ 'active': moduleSlotFilter === '' }" @click="setModuleFilter('')">全部</span>
                    <span class="filter-tab" :class="{ 'active': moduleSlotFilter === 'high' }" @click="setModuleFilter('high')">高槽</span>
                    <span class="filter-tab" :class="{ 'active': moduleSlotFilter === 'med' }" @click="setModuleFilter('med')">中槽</span>
                    <span class="filter-tab" :class="{ 'active': moduleSlotFilter === 'low' }" @click="setModuleFilter('low')">低槽</span>
                    <span class="filter-tab" :class="{ 'active': moduleSlotFilter === 'rig' }" @click="setModuleFilter('rig')">改装</span>
                    <span class="filter-tab" :class="{ 'active': moduleSlotFilter === 'drone' }" @click="setModuleFilter('drone')">无人机</span>
                </div>
                
                <!-- 搜索结果模式 -->
                <div x-show="moduleSearchQuery" class="module-results">
                    <template x-for="module in moduleResults" :key="module.type_id">
                        <div class="module-item" @click="addModule(module)">
                            <img :src="module.image_url">
                            <span class="name" x-text="module.name_cn || module.name"></span>
                            <span class="slot-tag" x-text="getSlotLabel(module.slot)"></span>
                        </div>
                    </template>
                    
                    <div x-show="moduleResults.length === 0" class="empty-state" style="height: 100px;">
                        <span>没有找到匹配的装备</span>
                    </div>
                </div>
                
                <!-- 分类浏览模式 -->
                <div x-show="!moduleSearchQuery" class="module-categories">
                    <!-- 使用新的四级分类树 -->
                    <template x-for="(l2Data, l2Name) in getFilteredCategoryTree()" :key="l2Name">
                        <div class="category-block">
                            <!-- 二级分类（如：护盾、工程装备等） -->
                            <div class="category-item cat-level-1"
                                 :class="{ 'expanded': expandedL2Categories.includes(l2Name) }"
                                 @click="toggleL2Category(l2Name)">
                                <span x-text="l2Name"></span>
                                <span class="toggle-icon">▶</span>
                            </div>
                            
                            <!-- 三级分类 -->
                            <div x-show="expandedL2Categories.includes(l2Name)" x-collapse class="category-children">
                                <template x-for="(l3Data, l3Name) in l2Data.children" :key="l3Name">
                                    <div>
                                        <div class="category-item cat-level-2"
                                             :class="{ 'expanded': expandedL3Categories.includes(l2Name + '|' + l3Name) }"
                                             @click="toggleL3Category(l2Name, l3Name)">
                                            <span x-text="l3Name"></span>
                                            <span class="toggle-icon">▶</span>
                                        </div>
                                        
                                        <!-- 四级分类或物品 -->
                                        <div x-show="expandedL3Categories.includes(l2Name + '|' + l3Name)" x-collapse class="category-children">
                                            <!-- 如果有四级分类 -->
                                            <template x-if="Object.keys(l3Data.children).length > 0">
                                                <template x-for="(l4Data, l4Name) in l3Data.children" :key="l4Name">
                                                    <div>
                                                        <div class="category-item cat-level-3"
                                                             :class="{ 'expanded': expandedL4Categories.includes(l2Name + '|' + l3Name + '|' + l4Name) }"
                                                             @click="toggleL4Category(l2Name, l3Name, l4Name)">
                                                            <span x-text="l4Name"></span>
                                                            <span class="toggle-icon">▶</span>
                                                        </div>
                                                        
                                                        <!-- 四级分类下的物品 -->
                                                        <div x-show="expandedL4Categories.includes(l2Name + '|' + l3Name + '|' + l4Name)" x-collapse>
                                                            <template x-for="module in getCategoryModules(['舰船装备', l2Name, l3Name, l4Name])" :key="module.type_id">
                                                                <div class="module-item cat-level-4" @click="addModule(module)">
                                                                    <img :src="module.image_url">
                                                                    <span class="name" x-text="module.name_cn || module.name"></span>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </template>
                                            </template>
                                            
                                            <!-- 如果没有四级分类，直接显示物品 -->
                                            <template x-if="Object.keys(l3Data.children).length === 0 && l3Data.items.length > 0">
                                                <div>
                                                    <template x-for="module in getCategoryModules(['舰船装备', l2Name, l3Name])" :key="module.type_id">
                                                        <div class="module-item cat-level-3" @click="addModule(module)">
                                                            <img :src="module.image_url">
                                                            <span class="name" x-text="module.name_cn || module.name"></span>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                    
                    <div x-show="Object.keys(getFilteredCategoryTree()).length === 0" class="empty-state" style="height: 100px;">
                        <span>请选择槽位类型</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 第二列：槽位列表 -->
    <div class="panel">
        <div class="panel-header">
            <template x-if="selectedShip">
                <div class="ship-info">
                    <img :src="selectedShip?.image_url">
                    <span x-text="selectedShip?.name_cn || selectedShip?.name"></span>
                </div>
            </template>
            <template x-if="!selectedShip">
                <span>装配槽位</span>
            </template>
            <button x-show="selectedShip" @click="clearAllSlots()" class="text-xs px-2 py-1 rounded bg-red-500/20 text-red-400 hover:bg-red-500/30">
                清空
            </button>
        </div>
        <div class="panel-body">
            <template x-if="selectedShip && shipStats">
                <div>
                    <!-- 高槽 -->
                    <div class="slot-section">
                        <div class="slot-header">
                            <span>🔫 高槽</span>
                            <span class="count" x-text="getFilledSlots('high') + '/' + (shipStats?.slots?.hi || 0)"></span>
                        </div>
                        <div class="slot-list">
                            <template x-for="i in shipStats?.slots?.hi || 0" :key="'hi-' + i">
                                <div class="slot-item" :class="{ 'empty': !getSlotModule('high', i-1), 'filled': getSlotModule('high', i-1) }" @click="selectSlotForModule('high', i-1)">
                                    <span class="slot-num" x-text="i"></span>
                                    <template x-if="getSlotModule('high', i-1)">
                                        <img :src="getSlotModule('high', i-1)?.image_url">
                                    </template>
                                    <span class="module-name" x-text="getSlotModule('high', i-1)?.name_cn || getSlotModule('high', i-1)?.name || '空'"></span>
                                    <template x-if="getSlotModule('high', i-1)">
                                        <span class="remove-btn" @click.stop="removeModule('high', i-1)">✕</span>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                    
                    <!-- 中槽 -->
                    <div class="slot-section">
                        <div class="slot-header">
                            <span>🛡️ 中槽</span>
                            <span class="count" x-text="getFilledSlots('med') + '/' + (shipStats?.slots?.med || 0)"></span>
                        </div>
                        <div class="slot-list">
                            <template x-for="i in shipStats?.slots?.med || 0" :key="'med-' + i">
                                <div class="slot-item" :class="{ 'empty': !getSlotModule('med', i-1), 'filled': getSlotModule('med', i-1) }" @click="selectSlotForModule('med', i-1)">
                                    <span class="slot-num" x-text="i"></span>
                                    <template x-if="getSlotModule('med', i-1)">
                                        <img :src="getSlotModule('med', i-1)?.image_url">
                                    </template>
                                    <span class="module-name" x-text="getSlotModule('med', i-1)?.name_cn || getSlotModule('med', i-1)?.name || '空'"></span>
                                    <template x-if="getSlotModule('med', i-1)">
                                        <span class="remove-btn" @click.stop="removeModule('med', i-1)">✕</span>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                    
                    <!-- 低槽 -->
                    <div class="slot-section">
                        <div class="slot-header">
                            <span>⚙️ 低槽</span>
                            <span class="count" x-text="getFilledSlots('low') + '/' + (shipStats?.slots?.low || 0)"></span>
                        </div>
                        <div class="slot-list">
                            <template x-for="i in shipStats?.slots?.low || 0" :key="'low-' + i">
                                <div class="slot-item" :class="{ 'empty': !getSlotModule('low', i-1), 'filled': getSlotModule('low', i-1) }" @click="selectSlotForModule('low', i-1)">
                                    <span class="slot-num" x-text="i"></span>
                                    <template x-if="getSlotModule('low', i-1)">
                                        <img :src="getSlotModule('low', i-1)?.image_url">
                                    </template>
                                    <span class="module-name" x-text="getSlotModule('low', i-1)?.name_cn || getSlotModule('low', i-1)?.name || '空'"></span>
                                    <template x-if="getSlotModule('low', i-1)">
                                        <span class="remove-btn" @click.stop="removeModule('low', i-1)">✕</span>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                    
                    <!-- 改装件 -->
                    <div class="slot-section">
                        <div class="slot-header">
                            <span>🔧 改装件</span>
                            <span class="count" x-text="getFilledSlots('rig') + '/' + (shipStats?.slots?.rig || 0)"></span>
                        </div>
                        <div class="slot-list">
                            <template x-for="i in shipStats?.slots?.rig || 0" :key="'rig-' + i">
                                <div class="slot-item" :class="{ 'empty': !getSlotModule('rig', i-1), 'filled': getSlotModule('rig', i-1) }" @click="selectSlotForModule('rig', i-1)">
                                    <span class="slot-num" x-text="i"></span>
                                    <template x-if="getSlotModule('rig', i-1)">
                                        <img :src="getSlotModule('rig', i-1)?.image_url">
                                    </template>
                                    <span class="module-name" x-text="getSlotModule('rig', i-1)?.name_cn || getSlotModule('rig', i-1)?.name || '空'"></span>
                                    <template x-if="getSlotModule('rig', i-1)">
                                        <span class="remove-btn" @click.stop="removeModule('rig', i-1)">✕</span>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
            
            <div x-show="!selectedShip" class="empty-state">
                <span class="icon">🚀</span>
                <span>请从左侧选择一艘舰船</span>
            </div>
        </div>
    </div>
    
    <!-- 第三列：舰船数据 -->
    <div class="panel">
        <div class="panel-header">
            <span>📊 舰船数据</span>
        </div>
        <div class="panel-body" style="padding: 0;">
            <template x-if="selectedShip && shipStats">
                <div>
                    <!-- 资源使用 -->
                    <div class="stats-section">
                        <div class="stats-section-title">📊 资源使用</div>
                        <div style="margin-bottom: 8px;">
                            <div class="stat-row">
                                <span class="stat-label">CPU</span>
                                <span class="stat-value" :class="{ 'danger': resourceUsage.cpu.percent > 100 }" x-text="resourceUsage.cpu.used.toFixed(1) + ' / ' + resourceUsage.cpu.total.toFixed(1) + ' tf'"></span>
                            </div>
                            <div class="resource-bar">
                                <div class="resource-bar-fill cpu" :style="'width: ' + Math.min(resourceUsage.cpu.percent, 100) + '%'"></div>
                            </div>
                        </div>
                        <div style="margin-bottom: 8px;">
                            <div class="stat-row">
                                <span class="stat-label">电网</span>
                                <span class="stat-value" :class="{ 'danger': resourceUsage.power.percent > 100 }" x-text="resourceUsage.power.used.toFixed(1) + ' / ' + resourceUsage.power.total.toFixed(1) + ' MW'"></span>
                            </div>
                            <div class="resource-bar">
                                <div class="resource-bar-fill power" :style="'width: ' + Math.min(resourceUsage.power.percent, 100) + '%'"></div>
                            </div>
                        </div>
                        <div>
                            <div class="stat-row">
                                <span class="stat-label">校准值</span>
                                <span class="stat-value" :class="{ 'danger': resourceUsage.calibration.percent > 100 }" x-text="resourceUsage.calibration.used + ' / ' + resourceUsage.calibration.total"></span>
                            </div>
                            <div class="resource-bar">
                                <div class="resource-bar-fill calibration" :style="'width: ' + Math.min(resourceUsage.calibration.percent, 100) + '%'"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 护盾 -->
                    <div class="stats-section">
                        <div class="stats-section-title">🛡️ 护盾</div>
                        <div class="stat-row">
                            <span class="stat-label">HP</span>
                            <span class="stat-value" x-text="formatNumber(shipStats.attributes?.shieldCapacity?.value)"></span>
                        </div>
                        <div class="resistance-row">
                            <div class="resistance-item">
                                <div class="resistance-bar em" :style="'opacity: ' + (1 - (shipStats.attributes?.shieldEmDamageResonance?.value || 1))"></div>
                                <span x-text="formatResistance(shipStats.attributes?.shieldEmDamageResonance?.value)"></span>
                            </div>
                            <div class="resistance-item">
                                <div class="resistance-bar explosive" :style="'opacity: ' + (1 - (shipStats.attributes?.shieldExplosiveDamageResonance?.value || 1))"></div>
                                <span x-text="formatResistance(shipStats.attributes?.shieldExplosiveDamageResonance?.value)"></span>
                            </div>
                            <div class="resistance-item">
                                <div class="resistance-bar kinetic" :style="'opacity: ' + (1 - (shipStats.attributes?.shieldKineticDamageResonance?.value || 1))"></div>
                                <span x-text="formatResistance(shipStats.attributes?.shieldKineticDamageResonance?.value)"></span>
                            </div>
                            <div class="resistance-item">
                                <div class="resistance-bar thermal" :style="'opacity: ' + (1 - (shipStats.attributes?.shieldThermalDamageResonance?.value || 1))"></div>
                                <span x-text="formatResistance(shipStats.attributes?.shieldThermalDamageResonance?.value)"></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 装甲 -->
                    <div class="stats-section">
                        <div class="stats-section-title">🔧 装甲</div>
                        <div class="stat-row">
                            <span class="stat-label">HP</span>
                            <span class="stat-value" x-text="formatNumber(shipStats.attributes?.armorHP?.value)"></span>
                        </div>
                        <div class="resistance-row">
                            <div class="resistance-item">
                                <div class="resistance-bar em" :style="'opacity: ' + (1 - (shipStats.attributes?.armorEmDamageResonance?.value || 1))"></div>
                                <span x-text="formatResistance(shipStats.attributes?.armorEmDamageResonance?.value)"></span>
                            </div>
                            <div class="resistance-item">
                                <div class="resistance-bar explosive" :style="'opacity: ' + (1 - (shipStats.attributes?.armorExplosiveDamageResonance?.value || 1))"></div>
                                <span x-text="formatResistance(shipStats.attributes?.armorExplosiveDamageResonance?.value)"></span>
                            </div>
                            <div class="resistance-item">
                                <div class="resistance-bar kinetic" :style="'opacity: ' + (1 - (shipStats.attributes?.armorKineticDamageResonance?.value || 1))"></div>
                                <span x-text="formatResistance(shipStats.attributes?.armorKineticDamageResonance?.value)"></span>
                            </div>
                            <div class="resistance-item">
                                <div class="resistance-bar thermal" :style="'opacity: ' + (1 - (shipStats.attributes?.armorThermalDamageResonance?.value || 1))"></div>
                                <span x-text="formatResistance(shipStats.attributes?.armorThermalDamageResonance?.value)"></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 机动性 -->
                    <div class="stats-section">
                        <div class="stats-section-title">🚀 机动性</div>
                        <div class="stat-row">
                            <span class="stat-label">最大速度</span>
                            <span class="stat-value" x-text="(shipStats.attributes?.maxVelocity?.value || 0).toFixed(1) + ' m/s'"></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">惯性系数</span>
                            <span class="stat-value" x-text="(shipStats.attributes?.agility?.value || 0).toFixed(3)"></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">信号半径</span>
                            <span class="stat-value" x-text="(shipStats.attributes?.signatureRadius?.value || 0).toFixed(0) + ' m'"></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">曲速</span>
                            <span class="stat-value" x-text="(shipStats.attributes?.warpSpeedMultiplier?.value || 1).toFixed(1) + ' AU/s'"></span>
                        </div>
                    </div>
                    
                    <!-- 锁定 -->
                    <div class="stats-section">
                        <div class="stats-section-title">🎯 锁定</div>
                        <div class="stat-row">
                            <span class="stat-label">锁定距离</span>
                            <span class="stat-value" x-text="formatDistance(shipStats.attributes?.maxTargetRange?.value)"></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">扫描分辨率</span>
                            <span class="stat-value" x-text="(shipStats.attributes?.scanResolution?.value || 0).toFixed(0) + ' mm'"></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">最大目标数</span>
                            <span class="stat-value" x-text="shipStats.attributes?.maxLockedTargets?.value || 0"></span>
                        </div>
                    </div>
                    
                    <!-- 无人机 -->
                    <div x-show="shipStats.attributes?.droneBandwidth?.value > 0" class="stats-section">
                        <div class="stats-section-title">🤖 无人机</div>
                        <div class="stat-row">
                            <span class="stat-label">带宽</span>
                            <span class="stat-value" x-text="(shipStats.attributes?.droneBandwidth?.value || 0) + ' Mbit/s'"></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">舱容</span>
                            <span class="stat-value" x-text="(shipStats.attributes?.droneCapacity?.value || 0) + ' m³'"></span>
                        </div>
                    </div>
                    
                    <!-- 电容 -->
                    <div class="stats-section">
                        <div class="stats-section-title">⚡ 电容</div>
                        <div class="stat-row">
                            <span class="stat-label">容量</span>
                            <span class="stat-value" x-text="(shipStats.attributes?.capacitorCapacity?.value || 0).toFixed(0) + ' GJ'"></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">充能时间</span>
                            <span class="stat-value" x-text="formatTime(shipStats.attributes?.rechargeRate?.value)"></span>
                        </div>
                    </div>
                </div>
            </template>
            
            <div x-show="!selectedShip" class="empty-state">
                <span class="icon">📊</span>
                <span>选择舰船后显示数据</span>
            </div>
        </div>
    </div>
</div>

<script>
function fittingSimulator() {
    return {
        activeTab: 'ships',
        categories: {},
        filteredCategories: {},
        allShips: {},
        shipSearchQuery: '',
        selectedShip: null,
        selectedGroup: null,
        expandedCategories: [],
        expandedGroups: [],
        shipStats: null,
        fittedModules: { high: [], med: [], low: [], rig: [] },
        moduleSearchQuery: '',
        moduleSlotFilter: '',
        moduleResults: [],
        selectedSlot: null,
        resourceUsage: {
            cpu: { used: 0, total: 0, percent: 0 },
            power: { used: 0, total: 0, percent: 0 },
            calibration: { used: 0, total: 0, percent: 0 }
        },
        // 装备分类相关
        moduleCategories: {},
        allModules: {},
        expandedModuleCategories: [],
        expandedModuleGroups: [],
        selectedModuleGroup: null,
        // 新的四级分类相关
        categoryTree: {},
        expandedL2Categories: [],
        expandedL3Categories: [],
        expandedL4Categories: [],
        categoryModules: {},
        
        async init() { 
            await this.loadCategories(); 
            await this.loadModuleCategories();
            await this.loadCategoryTree();
        },
        
        async loadCategories() {
            try {
                const response = await fetch('/api/public/fitting-simulator/categories');
                this.categories = await response.json();
                this.filteredCategories = this.categories;
            } catch (e) { console.error('Failed to load categories:', e); }
        },
        
        async loadModuleCategories() {
            try {
                const response = await fetch('/api/public/fitting-simulator/module-categories');
                this.moduleCategories = await response.json();
            } catch (e) { console.error('Failed to load module categories:', e); }
        },
        
        async loadCategoryTree() {
            try {
                const response = await fetch('/api/public/fitting-simulator/module-category-tree');
                this.categoryTree = await response.json();
            } catch (e) { console.error('Failed to load category tree:', e); }
        },
        
        // 获取过滤后的四级分类树
        getFilteredCategoryTree() {
            if (!this.moduleSlotFilter) {
                return this.categoryTree;
            }
            // 按slot属性过滤
            const filtered = {};
            for (const [name, cat] of Object.entries(this.categoryTree)) {
                if (cat.slot === this.moduleSlotFilter) {
                    filtered[name] = cat;
                }
            }
            return filtered;
        },
        
        // 切换二级分类展开状态
        toggleL2Category(l2Name) {
            const idx = this.expandedL2Categories.indexOf(l2Name);
            if (idx > -1) this.expandedL2Categories.splice(idx, 1);
            else this.expandedL2Categories.push(l2Name);
        },
        
        // 切换三级分类展开状态
        toggleL3Category(l2Name, l3Name) {
            const key = l2Name + '|' + l3Name;
            const idx = this.expandedL3Categories.indexOf(key);
            if (idx > -1) {
                this.expandedL3Categories.splice(idx, 1);
            } else {
                this.expandedL3Categories.push(key);
                // 检查是否有四级分类，如果没有则直接加载装备
                const l3Data = this.categoryTree[l2Name]?.children?.[l3Name];
                if (l3Data && Object.keys(l3Data.children || {}).length === 0) {
                    this.loadCategoryModules(['舰船装备', l2Name, l3Name]);
                }
            }
        },
        
        // 切换四级分类展开状态
        toggleL4Category(l2Name, l3Name, l4Name) {
            const key = l2Name + '|' + l3Name + '|' + l4Name;
            const idx = this.expandedL4Categories.indexOf(key);
            if (idx > -1) this.expandedL4Categories.splice(idx, 1);
            else {
                this.expandedL4Categories.push(key);
                // 加载该分类下的装备
                this.loadCategoryModules(['舰船装备', l2Name, l3Name, l4Name]);
            }
        },
        
        // 加载指定分类路径下的装备
        async loadCategoryModules(path) {
            const key = path.join('|');
            if (this.categoryModules[key]) return;
            
            try {
                const response = await fetch('/api/public/fitting-simulator/modules-by-category-path?path=' + encodeURIComponent(JSON.stringify(path)));
                const data = await response.json();
                this.categoryModules[key] = data.modules || [];
            } catch (e) {
                this.categoryModules[key] = [];
            }
        },
        
        // 获取指定分类路径下的装备
        getCategoryModules(path) {
            const key = path.join('|');
            // 如果还没有加载，触发加载
            if (!this.categoryModules[key]) {
                this.loadCategoryModules(path);
                return [];
            }
            return this.categoryModules[key] || [];
        },
        
        // 设置装备过滤
        setModuleFilter(filter) {
            this.moduleSlotFilter = filter;
            this.expandedModuleCategories = [];
            this.expandedModuleGroups = [];
            // 清空新的四级分类展开状态
            this.expandedL2Categories = [];
            this.expandedL3Categories = [];
            this.expandedL4Categories = [];
            if (this.moduleSearchQuery) {
                this.searchModules();
            }
        },
        
        // 获取过滤后的装备分类
        getFilteredModuleCategories() {
            if (!this.moduleSlotFilter) {
                return this.moduleCategories;
            }
            // 按slot属性过滤
            const filtered = {};
            for (const [key, cat] of Object.entries(this.moduleCategories)) {
                if (cat.slot === this.moduleSlotFilter) {
                    filtered[key] = cat;
                }
            }
            return filtered;
        },
        
        toggleModuleCategory(key) {
            const idx = this.expandedModuleCategories.indexOf(key);
            if (idx > -1) this.expandedModuleCategories.splice(idx, 1);
            else this.expandedModuleCategories.push(key);
        },
        
        toggleModuleGroup(groupId, slot) {
            const idx = this.expandedModuleGroups.indexOf(groupId);
            if (idx > -1) this.expandedModuleGroups.splice(idx, 1);
            else {
                this.expandedModuleGroups.push(groupId);
                if (!this.allModules[groupId]) this.loadModulesByGroup(groupId, slot);
            }
            this.selectedModuleGroup = groupId;
        },
        
        async loadModulesByGroup(groupId, slot) {
            try {
                let url = `/api/public/fitting-simulator/groups/${groupId}/modules`;
                if (slot && slot !== 'drone') url += `?slot=${slot}`;
                const response = await fetch(url);
                this.allModules[groupId] = await response.json();
            } catch (e) { this.allModules[groupId] = []; }
        },
        
        getModulesByGroup(groupId) { return this.allModules[groupId] || []; },
        
        toggleCategory(key) {
            const idx = this.expandedCategories.indexOf(key);
            if (idx > -1) this.expandedCategories.splice(idx, 1);
            else this.expandedCategories.push(key);
        },
        
        toggleGroup(groupId) {
            const idx = this.expandedGroups.indexOf(groupId);
            if (idx > -1) {
                this.expandedGroups.splice(idx, 1);
            } else {
                this.expandedGroups.push(groupId);
                // 立即加载数据
                this.loadShipsByGroup(groupId);
            }
            this.selectedGroup = groupId;
        },
        
        async loadShipsByGroup(groupId) {
            // 先设置为空数组，避免重复请求
            if (this.allShips[groupId] && this.allShips[groupId].length > 0) return;
            
            this.allShips[groupId] = []; // 先设为空，表示正在加载
            
            try {
                const response = await fetch('/api/public/fitting-simulator/groups/' + groupId + '/ships');
                const data = await response.json();
                // 直接赋值触发响应
                this.allShips[groupId] = data;
                // 强制更新
                this.allShips = JSON.parse(JSON.stringify(this.allShips));
            } catch (e) {
                console.error('加载舰船失败:', e);
                this.allShips[groupId] = [];
            }
        },
        
        getShipsByGroup(groupId) { 
            return this.allShips[groupId] || []; 
        },
        
        async selectShip(ship) {
            this.selectedShip = ship;
            this.clearAllSlots();
            try {
                const response = await fetch(`/api/public/fitting-simulator/ships/${ship.type_id}`);
                this.shipStats = await response.json();
                this.resourceUsage.cpu.total = this.shipStats.resources?.cpu_output || 0;
                this.resourceUsage.power.total = this.shipStats.resources?.power_output || 0;
                this.resourceUsage.calibration.total = this.shipStats.resources?.upgrade_capacity || 350;
                this.updateResourceUsage();
            } catch (e) { console.error('Failed to load ship details:', e); }
        },
        
        filterShips() {
            if (!this.shipSearchQuery) { this.filteredCategories = this.categories; return; }
            const query = this.shipSearchQuery.toLowerCase();
            const filtered = {};
            for (const [key, cat] of Object.entries(this.categories)) {
                const matchingGroups = cat.groups.filter(g => (g.name_cn || g.name).toLowerCase().includes(query));
                if (matchingGroups.length > 0) filtered[key] = { ...cat, groups: matchingGroups };
            }
            this.filteredCategories = filtered;
        },
        
        async searchModules() {
            if (!this.moduleSearchQuery) { this.moduleResults = []; return; }
            try {
                let url = `/api/public/fitting-simulator/search?q=${encodeURIComponent(this.moduleSearchQuery || '')}`;
                if (this.moduleSlotFilter && this.moduleSlotFilter !== 'drone') url += `&slot=${this.moduleSlotFilter}`;
                const response = await fetch(url);
                const data = await response.json();
                this.moduleResults = data.results || [];
            } catch (e) { this.moduleResults = []; }
        },
        
        selectSlotForModule(slotType, index) {
            this.selectedSlot = { type: slotType, index: index };
            this.moduleSlotFilter = slotType === 'rig' ? 'rig' : slotType;
            this.activeTab = 'modules';
            if (this.moduleSearchQuery) this.searchModules();
        },
        
        async addModule(module) {
            const slot = module.slot || this.selectedSlot?.type || this.moduleSlotFilter;
            if (!slot || !this.selectedShip) return;
            
            const maxSlots = this.shipStats?.slots?.[slot] || 0;
            let slotIndex = this.selectedSlot?.index ?? -1;
            
            if (slotIndex === -1 || this.fittedModules[slot][slotIndex]) {
                for (let i = 0; i < maxSlots; i++) {
                    if (!this.fittedModules[slot][i]) { slotIndex = i; break; }
                }
            }
            
            if (slotIndex === -1) { alert('该槽位已满！'); return; }
            
            let moduleDetails = module;
            if (!module.attributes) {
                try {
                    const response = await fetch(`/api/public/fitting-simulator/types/${module.type_id}`);
                    moduleDetails = await response.json();
                } catch (e) {}
            }
            
            if (slot === 'high') {
                if (this.isTurretModule(moduleDetails) && this.getRemainingHardpoints('turret') <= 0) { alert('炮台硬点已用完！'); return; }
                if (this.isLauncherModule(moduleDetails) && this.getRemainingHardpoints('launcher') <= 0) { alert('发射器硬点已用完！'); return; }
            }
            
            this.fittedModules[slot][slotIndex] = { ...moduleDetails, slot: slot, slotIndex: slotIndex };
            this.selectedSlot = null;
            this.updateResourceUsage();
        },
        
        removeModule(slotType, index) {
            this.fittedModules[slotType][index] = null;
            this.updateResourceUsage();
        },
        
        clearAllSlots() {
            this.fittedModules = { high: [], med: [], low: [], rig: [] };
            this.updateResourceUsage();
        },
        
        getSlotModule(slotType, index) { return this.fittedModules[slotType]?.[index]; },
        getFilledSlots(slotType) { return this.fittedModules[slotType]?.filter(m => m).length || 0; },
        
        updateResourceUsage() {
            let cpuUsed = 0, powerUsed = 0, calibrationUsed = 0;
            ['high', 'med', 'low', 'rig'].forEach(slot => {
                this.fittedModules[slot]?.forEach(m => {
                    if (m) {
                        cpuUsed += m.attributes?.[50] || m.cpu || 0;
                        powerUsed += m.attributes?.[30] || m.power || 0;
                        calibrationUsed += m.attributes?.[1153] || m.upgrade_cost || 0;
                    }
                });
            });
            this.resourceUsage.cpu.used = cpuUsed;
            this.resourceUsage.cpu.percent = this.resourceUsage.cpu.total > 0 ? (cpuUsed / this.resourceUsage.cpu.total) * 100 : 0;
            this.resourceUsage.power.used = powerUsed;
            this.resourceUsage.power.percent = this.resourceUsage.power.total > 0 ? (powerUsed / this.resourceUsage.power.total) * 100 : 0;
            this.resourceUsage.calibration.used = calibrationUsed;
            this.resourceUsage.calibration.percent = this.resourceUsage.calibration.total > 0 ? (calibrationUsed / this.resourceUsage.calibration.total) * 100 : 0;
        },
        
        getRemainingHardpoints(type) {
            const total = this.shipStats?.hardpoints?.[type] || 0;
            let used = 0;
            this.fittedModules.high?.forEach(m => { if (m) { if (type === 'turret' && this.isTurretModule(m)) used++; if (type === 'launcher' && this.isLauncherModule(m)) used++; } });
            return total - used;
        },
        
        isTurretModule(m) { return m.effects?.some(e => [10, 42, 44, 46, 48, 50].includes(e.effect_id)); },
        isLauncherModule(m) { return m.effects?.some(e => [11, 41, 43, 45, 47, 49, 51].includes(e.effect_id)); },
        getSlotLabel(s) { return { high: '高槽', med: '中槽', low: '低槽', rig: '改装', drone: '无人机' }[s] || s; },
        formatNumber(v) { return v ? Number(v).toLocaleString() : '0'; },
        formatResistance(v) { return v ? Math.round((1 - v) * 100) + '%' : '0%'; },
        formatDistance(v) { if (!v) return '0 m'; return v >= 1000 ? (v / 1000).toFixed(1) + ' km' : v.toFixed(0) + ' m'; },
        formatTime(ms) {
            if (!ms) return '0s';
            const seconds = ms / 1000;
            if (seconds >= 60) { const minutes = Math.floor(seconds / 60); const secs = Math.floor(seconds % 60); return `${minutes}m ${secs}s`; }
            return seconds.toFixed(1) + 's';
        }
    };
}
</script>
</div>
</div>
@endsection