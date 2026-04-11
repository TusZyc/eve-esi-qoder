@extends('layouts.app')

@section('page-title', '装配模拟器')

@push('styles')
<style>
    .fitting-page { min-height: calc(100vh - 180px); padding: 12px; }
    .fitting-grid { display: grid; grid-template-columns: 390px minmax(340px, 1fr) 360px; gap: 12px; }
    .fitting-panel { display: flex; flex-direction: column; min-height: 720px; background: rgba(15, 23, 42, 0.75); border: 1px solid rgba(148, 163, 184, 0.18); border-radius: 12px; overflow: hidden; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.22); }
    .fitting-panel__header { padding: 12px 14px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(148, 163, 184, 0.14); background: linear-gradient(90deg, rgba(59, 130, 246, 0.18), rgba(15, 23, 42, 0)); }
    .fitting-panel__title { font-size: 14px; font-weight: 700; color: #e2e8f0; }
    .fitting-panel__body { flex: 1; overflow-y: auto; padding: 12px; }
    .fitting-tabs, .slot-filter { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-bottom: 12px; }
    .slot-filter { display: flex; gap: 6px; }
    .slot-filter .fitting-btn { flex: 1 1 0; min-width: 0; padding: 8px 5px; font-size: 12px; }
    .fit-filter-row { display: flex; gap: 8px; flex-wrap: wrap; margin: -2px 0 10px; }
    .fit-filter-toggle { display: inline-flex; align-items: center; gap: 6px; padding: 6px 8px; border-radius: 8px; background: rgba(15, 23, 42, 0.74); border: 1px solid rgba(148, 163, 184, 0.18); color: #cbd5e1; font-size: 12px; cursor: pointer; }
    .fit-filter-toggle input { accent-color: #60a5fa; }
    .fitting-btn { border: 1px solid rgba(148, 163, 184, 0.18); background: rgba(30, 41, 59, 0.75); color: #cbd5e1; border-radius: 10px; padding: 8px 10px; font-size: 13px; cursor: pointer; transition: 0.18s ease; }
    .fitting-btn:hover { border-color: rgba(96, 165, 250, 0.45); color: #f8fafc; }
    .fitting-btn.is-active { background: rgba(37, 99, 235, 0.28); border-color: rgba(96, 165, 250, 0.65); color: #eff6ff; }
    .fitting-btn.is-danger { background: rgba(127, 29, 29, 0.24); border-color: rgba(248, 113, 113, 0.38); color: #fee2e2; }
    .fitting-input { width: 100%; border: 1px solid rgba(148, 163, 184, 0.18); background: rgba(15, 23, 42, 0.88); color: #e2e8f0; border-radius: 10px; padding: 10px 12px; font-size: 13px; outline: none; margin-bottom: 12px; }
    .fitting-input:focus { border-color: rgba(96, 165, 250, 0.55); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.16); }
    .notice { margin-bottom: 12px; padding: 10px 12px; border-radius: 10px; font-size: 13px; line-height: 1.5; }
    .notice.info { background: rgba(30, 64, 175, 0.18); border: 1px solid rgba(96, 165, 250, 0.22); color: #dbeafe; }
    .notice.warn { background: rgba(120, 53, 15, 0.22); border: 1px solid rgba(251, 191, 36, 0.24); color: #fef3c7; }
    .tree-group, .card-list, .stats-block, .slot-block { display: flex; flex-direction: column; gap: 6px; }
    .tree-item, .list-item, .slot-item, .stats-card { border: 1px solid rgba(148, 163, 184, 0.14); background: rgba(15, 23, 42, 0.78); border-radius: 10px; }
    .tree-head, .list-item, .slot-item, .stats-card { padding: 10px 12px; }
    .tree-head { display: flex; align-items: center; justify-content: space-between; cursor: pointer; color: #e2e8f0; font-size: 13px; font-weight: 600; min-height: 34px; padding-top: 6px; padding-bottom: 6px; }
    .tree-name { display: flex; align-items: center; gap: 7px; min-width: 0; }
    .tree-name__label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .tree-icon { width: 16px; height: 16px; display: inline-flex; align-items: center; justify-content: center; border-radius: 4px; background: rgba(96, 165, 250, 0.12); color: #bfdbfe; font-size: 11px; flex: 0 0 auto; }
    .tree-toggle { color: #93c5fd; flex: 0 0 auto; }
    .tree-children { padding: 0 6px 6px; display: flex; flex-direction: column; gap: 4px; }
    .faction-item { display: flex; align-items: center; justify-content: space-between; padding: 8px 10px; border-radius: 8px; cursor: pointer; color: #cbd5e1; background: rgba(30, 41, 59, 0.62); border: 1px solid transparent; font-size: 12px; }
    .faction-item.is-active, .list-item.is-active, .slot-item.is-selected { border-color: rgba(96, 165, 250, 0.56); background: rgba(30, 64, 175, 0.24); }
    .list-item { display: grid; grid-template-columns: 34px minmax(0, 1fr); gap: 8px; align-items: center; cursor: pointer; padding: 6px 9px; min-height: 44px; }
    .list-item > img { grid-column: 1; grid-row: 1; width: 34px; height: 34px; border-radius: 7px; object-fit: cover; background: rgba(255, 255, 255, 0.06); }
    .list-item > .list-item__text { grid-column: 2; grid-row: 1; display: flex; min-width: 0; flex-direction: column; justify-content: center; }
    .compact-item { display: flex !important; flex-direction: row !important; align-items: center !important; gap: 8px !important; padding: 6px 9px !important; min-height: 44px !important; }
    .compact-item > img { width: 34px !important; height: 34px !important; flex: 0 0 34px !important; border-radius: 7px !important; object-fit: cover !important; }
    .compact-item > .list-item__text { display: flex !important; min-width: 0 !important; flex: 1 1 auto !important; flex-direction: column !important; justify-content: center !important; }
    .slot-item img { width: 34px; height: 34px; border-radius: 7px; object-fit: cover; background: rgba(255, 255, 255, 0.06); }
    .ship-summary img { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; background: rgba(255, 255, 255, 0.06); }
    .list-item__text, .slot-item__text { flex: 1; min-width: 0; }
    .item-name { color: #f8fafc; font-size: 13px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .item-meta { color: #94a3b8; font-size: 11px; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ship-summary { display: flex; gap: 12px; align-items: center; padding: 12px; margin-bottom: 12px; border-radius: 12px; background: rgba(30, 41, 59, 0.74); border: 1px solid rgba(148, 163, 184, 0.16); }
    .slot-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; color: #e2e8f0; font-size: 13px; font-weight: 700; }
    .slot-item { display: grid; grid-template-columns: 22px 34px minmax(0, 1fr) auto; gap: 8px; align-items: center; padding: 7px 9px; min-height: 48px; }
    .slot-index { width: 22px; text-align: center; color: #60a5fa; font-size: 12px; font-weight: 700; }
    .drone-bay-item { display: grid; grid-template-columns: 34px minmax(0, 1fr) auto auto; gap: 8px; align-items: center; padding: 7px 9px; min-height: 48px; }
    .drone-bay-item img { width: 34px; height: 34px; border-radius: 7px; object-fit: cover; background: rgba(255, 255, 255, 0.06); }
    .drone-qty { min-width: 34px; text-align: center; color: #bfdbfe; font-size: 12px; font-weight: 700; }
    .resource-row, .stats-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; color: #cbd5e1; font-size: 13px; }
    .resource-row > span, .stats-row > span { flex: 0 0 auto; }
    .resource-row strong { display: inline-flex; align-items: center; justify-content: flex-end; gap: 6px; white-space: nowrap; min-width: 0; }
    .resource-row strong, .stats-row strong { color: #f8fafc; }
    .resource-row.is-danger strong { color: #fca5a5; }
    .limit-warning { color: #fca5a5; font-size: 12px; font-weight: 700; flex: 0 0 auto; }
    .empty-state { padding: 18px 12px; text-align: center; color: #94a3b8; font-size: 13px; border: 1px dashed rgba(148, 163, 184, 0.18); border-radius: 10px; }
    .tiny-tip { margin-top: 10px; color: #94a3b8; font-size: 12px; line-height: 1.6; }
    @media (max-width: 1280px) { .fitting-grid { grid-template-columns: 360px 1fr; } .fitting-panel:last-child { grid-column: span 2; min-height: 420px; } }
    @media (max-width: 900px) { .fitting-grid { grid-template-columns: 1fr; } .fitting-panel:last-child { grid-column: auto; } .fitting-panel { min-height: auto; } }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="fitting-page" x-data="fittingStageOne()" x-init="init()">
        <div class="fitting-grid">
            <section class="fitting-panel">
                <div class="fitting-panel__header">
                    <div class="fitting-panel__title">选择舰船与装备</div>
                    <div style="font-size:12px;color:#94a3b8;">阶段1基础版</div>
                </div>
                <div class="fitting-panel__body">
                    <template x-if="notice.message">
                        <div class="notice" :class="notice.type" x-text="notice.message"></div>
                    </template>

                    <div class="fitting-tabs">
                        <button class="fitting-btn" :class="{ 'is-active': activeTab === 'ships' }" @click="activeTab = 'ships'">舰船</button>
                        <button class="fitting-btn" :class="{ 'is-active': activeTab === 'modules' }" @click="activeTab = 'modules'">装备</button>
                    </div>

                    <div x-show="activeTab === 'ships'">
                        <div class="tiny-tip">现在舰船改成按本地分类树展开。点到最后一级后，下面才显示具体舰船。</div>

                        <div class="tree-group" style="margin-top:12px;">
                            <template x-for="node in visibleShipNodes()" :key="node.key">
                                <div>
                                    <div class="tree-head"
                                         :style="'padding-left:' + (12 + node.depth * 18) + 'px'"
                                         @click="onShipNodeClick(node)">
                                        <span class="tree-name">
                                            <span class="tree-toggle" x-text="node.hasChildren ? (isExpanded(shipExpandedNodes, node.key) ? '▾' : '▸') : (isExpanded(shipExpandedNodes, node.key) ? '▾' : '▸')"></span>
                                            <span class="tree-icon" x-text="categoryIcon(node.name)"></span>
                                            <span class="tree-name__label" x-text="node.name"></span>
                                        </span>
                                        <span x-text="node.count"></span>
                                    </div>

                                    <template x-if="!node.hasChildren && isExpanded(shipExpandedNodes, node.key)">
                                        <div class="card-list" :style="'margin: 6px 0 10px ' + (18 + node.depth * 18) + 'px;'">
                                            <template x-if="shipLeafLoadingKey === node.key">
                                                <div class="empty-state">正在加载这个分类下的舰船...</div>
                                            </template>

                                            <template x-if="shipLeafLoadingKey !== node.key && !(shipLeafResults[node.key] || []).length">
                                                <div class="empty-state">这个分类下暂时没有可用舰船。</div>
                                            </template>

                                            <template x-for="ship in (shipLeafResults[node.key] || [])" :key="ship.type_id">
                                                <div class="list-item"
                                                     :class="{ 'is-active': selectedShip?.type_id === ship.type_id }"
                                                     @click="selectShip(ship)">
                                                    <img :src="ship.image_url" :alt="shipDisplayName(ship)">
                                                    <div class="list-item__text">
                                                        <div class="item-name" x-text="shipDisplayName(ship)"></div>
                                                        <div class="item-meta" x-text="'高 ' + (ship.slots?.hi || 0) + ' / 中 ' + (ship.slots?.med || 0) + ' / 低 ' + (ship.slots?.low || 0) + ' / 改装 ' + (ship.slots?.rig || 0)"></div>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div x-show="activeTab === 'modules'">
                        <div class="slot-filter">
                            <button class="fitting-btn" :class="{ 'is-active': moduleSlotFilter === null }" @click="setModuleSlotFilter(null)">全部</button>
                            <button class="fitting-btn" :class="{ 'is-active': moduleSlotFilter === 'high' }" @click="setModuleSlotFilter('high')">高槽</button>
                            <button class="fitting-btn" :class="{ 'is-active': moduleSlotFilter === 'med' }" @click="setModuleSlotFilter('med')">中槽</button>
                            <button class="fitting-btn" :class="{ 'is-active': moduleSlotFilter === 'low' }" @click="setModuleSlotFilter('low')">低槽</button>
                            <button class="fitting-btn" :class="{ 'is-active': moduleSlotFilter === 'rig' }" @click="setModuleSlotFilter('rig')">改装</button>
                            <button class="fitting-btn" :class="{ 'is-active': moduleSlotFilter === 'drone' }" @click="setModuleSlotFilter('drone')">无人机</button>
                        </div>

                        <input class="fitting-input" type="text" placeholder="输入装备名称搜索" x-model="moduleSearchQuery" @keydown.enter.prevent="searchModules" @input.debounce.300ms="searchModules">

                        <div class="tiny-tip">可以直接点装备自动安装；如果想指定位置，也可以先点中间的空槽位。输入搜索词时会显示搜索列表。</div>

                        <div class="fit-filter-row">
                            <label class="fit-filter-toggle">
                                <input type="checkbox" x-model="filterShipRestrictions" @change="reloadModuleFilters">
                                <span>适合当前舰船</span>
                            </label>
                            <label class="fit-filter-toggle">
                                <input type="checkbox" x-model="filterResources" @change="reloadModuleFilters">
                                <span>资源足够</span>
                            </label>
                        </div>

                        <div class="tree-group" style="margin-top:12px;" x-show="!moduleSearchQuery">
                            <template x-for="node in visibleModuleNodes()" :key="node.key">
                                <div>
                                    <div class="tree-head"
                                         :style="'padding-left:' + (12 + node.depth * 18) + 'px'"
                                         @click="onModuleNodeClick(node)">
                                        <span class="tree-name">
                                            <span class="tree-toggle" x-text="node.hasChildren ? (isExpanded(moduleExpandedNodes, node.key) ? '▾' : '▸') : (isExpanded(moduleExpandedNodes, node.key) ? '▾' : '▸')"></span>
                                            <span class="tree-icon" x-text="categoryIcon(node.name)"></span>
                                            <span class="tree-name__label" x-text="node.name"></span>
                                        </span>
                                        <span x-text="node.count"></span>
                                    </div>

                                    <template x-if="!node.hasChildren && isExpanded(moduleExpandedNodes, node.key)">
                                        <div class="card-list" :style="'margin: 6px 0 10px ' + (18 + node.depth * 18) + 'px;'">
                                            <template x-if="moduleLeafLoadingKey === node.key">
                                                <div class="empty-state">正在加载这个分类下的装备...</div>
                                            </template>

                                            <template x-if="moduleLeafLoadingKey !== node.key && !groupedLeafModules(node.key).length">
                                                <div class="empty-state">这个分类下暂时没有可用装备。</div>
                                            </template>

                                            <template x-for="entry in groupedLeafModules(node.key)" :key="entry.key">
                                                <div>
                                                    <template x-if="entry.type === 'module'">
                                                        <div class="list-item compact-item" @click="addModule(entry.module)">
                                                            <img :src="entry.module.image_url" :alt="moduleDisplayName(entry.module)">
                                                            <div class="list-item__text">
                                                                <div class="item-name" x-text="moduleDisplayName(entry.module)"></div>
                                                                <div class="item-meta" x-text="moduleMeta(entry.module)"></div>
                                                            </div>
                                                        </div>
                                                    </template>

                                                    <template x-if="entry.type === 'group'">
                                                        <div class="tree-item">
                                                            <div class="tree-head" @click="toggleRarityGroup(entry.key)">
                                                                <span class="tree-name">
                                                                    <span class="tree-toggle" x-text="isRarityGroupExpanded(entry.key) ? '▾' : '▸'"></span>
                                                                    <span class="tree-icon" x-text="entry.label === '官员' ? '官' : '势'"></span>
                                                                    <span class="tree-name__label" x-text="entry.label"></span>
                                                                </span>
                                                                <span x-text="entry.modules.length"></span>
                                                            </div>
                                                            <div class="tree-children" x-show="isRarityGroupExpanded(entry.key)">
                                                                <template x-for="module in entry.modules" :key="module.type_id">
                                                                    <div class="list-item compact-item" @click="addModule(module)">
                                                                        <img :src="module.image_url" :alt="moduleDisplayName(module)">
                                                                        <div class="list-item__text">
                                                                            <div class="item-name" x-text="moduleDisplayName(module)"></div>
                                                                            <div class="item-meta" x-text="moduleMeta(module)"></div>
                                                                        </div>
                                                                    </div>
                                                                </template>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>

                        <div class="card-list" style="margin-top:12px;" x-show="moduleSearchQuery">
                            <template x-for="group in groupedModuleResults()" :key="group.label">
                                <div class="tree-item">
                                    <div class="tree-head" style="cursor:default;">
                                        <span x-text="group.label"></span>
                                        <span x-text="group.items.length + ' 件'"></span>
                                    </div>
                                    <div class="tree-children" style="padding-top:8px;">
                                        <template x-for="module in group.items" :key="module.type_id">
                                            <div class="list-item compact-item" @click="addModule(module)">
                                                <img :src="module.image_url" :alt="moduleDisplayName(module)">
                                                <div class="list-item__text">
                                                    <div class="item-name" x-text="moduleDisplayName(module)"></div>
                                                    <div class="item-meta" x-text="moduleMeta(module)"></div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            <template x-if="moduleLoading">
                                <div class="empty-state">正在查找装备...</div>
                            </template>
                            <template x-if="moduleSearchQuery && !moduleLoading && !groupedModuleResults().length">
                                <div class="empty-state">输入装备名称后会显示搜索结果。</div>
                            </template>
                        </div>
                    </div>
                </div>
            </section>

            <section class="fitting-panel">
                <div class="fitting-panel__header">
                    <div class="fitting-panel__title">装配区</div>
                    <button class="fitting-btn is-danger" @click="clearAllSlots" x-show="selectedShip">清空装配</button>
                </div>
                <div class="fitting-panel__body">
                    <template x-if="selectedShip">
                        <div>
                            <div class="ship-summary">
                                <img :src="selectedShip.render_url || selectedShip.image_url" :alt="shipDisplayName(selectedShip)">
                                <div class="list-item__text">
                                    <div class="item-name" x-text="shipDisplayName(selectedShip)"></div>
                                    <div class="item-meta">直接点左侧装备会自动安装；也可以先点空槽位来指定位置。</div>
                                </div>
                            </div>

                            <template x-for="slotType in ['high', 'med', 'low', 'rig']" :key="slotType">
                                <div class="slot-block" style="margin-bottom: 14px;">
                                    <div class="slot-header">
                                        <span x-text="slotLabel(slotType)"></span>
                                        <span x-text="filledCount(slotType) + ' / ' + slotCapacity(slotType)"></span>
                                    </div>

                                    <template x-for="index in slotCapacity(slotType)" :key="slotType + '-' + index">
                                        <div class="slot-item"
                                             :class="{ 'is-selected': selectedSlot?.type === slotType && selectedSlot?.index === (index - 1) }"
                                             @click="selectSlot(slotType, index - 1)">
                                            <div class="slot-index" x-text="index"></div>
                                            <template x-if="slotModule(slotType, index - 1)">
                                                <img :src="slotModule(slotType, index - 1).image_url" :alt="moduleDisplayName(slotModule(slotType, index - 1))">
                                            </template>
                                            <template x-if="!slotModule(slotType, index - 1)">
                                                <div style="width:34px;height:34px;border-radius:7px;background:rgba(30,41,59,0.65);"></div>
                                            </template>
                                            <div class="slot-item__text">
                                                <div class="item-name" x-text="slotModule(slotType, index - 1) ? moduleDisplayName(slotModule(slotType, index - 1)) : '空槽位'"></div>
                                                <div class="item-meta" x-text="selectedSlot?.type === slotType && selectedSlot?.index === (index - 1) ? '当前选中，左侧点装备会优先装到这里' : '点击可指定这个槽位'"></div>
                                            </div>
                                            <button class="fitting-btn" style="padding:6px 8px;" x-show="slotModule(slotType, index - 1)" @click.stop="removeModule(slotType, index - 1)">卸下</button>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <div class="slot-block" style="margin-bottom: 14px;" x-show="droneBayCapacity() > 0 || fittedDrones.length">
                                <div class="slot-header">
                                    <span>无人机舱</span>
                                    <span x-text="formatUsage(droneBayUsed(), droneBayCapacity(), ' m³')"></span>
                                </div>

                                <template x-if="!fittedDrones.length">
                                    <div class="empty-state">点击左侧无人机，会自动加入这里。</div>
                                </template>

                                <template x-for="drone in fittedDrones" :key="drone.type_id">
                                    <div class="tree-item drone-bay-item">
                                        <img :src="drone.image_url" :alt="moduleDisplayName(drone)">
                                        <div class="slot-item__text">
                                            <div class="item-name" x-text="moduleDisplayName(drone)"></div>
                                            <div class="item-meta" x-text="`${Number(drone.volume || 0).toFixed(1)} m³ / 架`"></div>
                                        </div>
                                        <div class="drone-qty" x-text="'x' + drone.quantity"></div>
                                        <button class="fitting-btn" style="padding:6px 8px;" @click.stop="removeDrone(drone.type_id)">卸下</button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <template x-if="!selectedShip">
                        <div class="empty-state">先从左侧选一艘舰船，再开始装配。</div>
                    </template>
                </div>
            </section>

            <section class="fitting-panel">
                <div class="fitting-panel__header">
                    <div class="fitting-panel__title">资源与基础数据</div>
                    <div style="font-size:12px;color:#94a3b8;">先显示基础值</div>
                </div>
                <div class="fitting-panel__body">
                    <template x-if="selectedShip && shipStats">
                        <div class="stats-block">
                            <div class="stats-card">
                                <div class="slot-header">
                                    <span>资源占用</span>
                                </div>
                                <div class="resource-row" :class="{ 'is-danger': resourceUsage.cpu.used > resourceUsage.cpu.total }">
                                    <span>CPU</span>
                                    <strong>
                                        <span x-text="formatUsage(resourceUsage.cpu.used, resourceUsage.cpu.total, ' tf')"></span>
                                        <span class="limit-warning" x-show="resourceUsage.cpu.used > resourceUsage.cpu.total">超上限</span>
                                    </strong>
                                </div>
                                <div class="resource-row" :class="{ 'is-danger': resourceUsage.power.used > resourceUsage.power.total }" style="margin-top:8px;">
                                    <span>能量栅格</span>
                                    <strong>
                                        <span x-text="formatUsage(resourceUsage.power.used, resourceUsage.power.total, ' MW')"></span>
                                        <span class="limit-warning" x-show="resourceUsage.power.used > resourceUsage.power.total">超上限</span>
                                    </strong>
                                </div>
                                <div class="resource-row" :class="{ 'is-danger': resourceUsage.calibration.used > resourceUsage.calibration.total }" style="margin-top:8px;">
                                    <span>校准值</span>
                                    <strong>
                                        <span x-text="formatUsage(resourceUsage.calibration.used, resourceUsage.calibration.total, '')"></span>
                                        <span class="limit-warning" x-show="resourceUsage.calibration.used > resourceUsage.calibration.total">超上限</span>
                                    </strong>
                                </div>
                                <div class="resource-row" :class="{ 'is-danger': droneBayUsed() > droneBayCapacity() }" style="margin-top:8px;">
                                    <span>无人机舱</span>
                                    <strong>
                                        <span x-text="formatUsage(droneBayUsed(), droneBayCapacity(), ' m³')"></span>
                                        <span class="limit-warning" x-show="droneBayUsed() > droneBayCapacity()">超上限</span>
                                    </strong>
                                </div>
                                <div class="resource-row" :class="{ 'is-danger': droneBandwidthUsed() > droneBandwidthCapacity() }" style="margin-top:8px;">
                                    <span>无人机带宽</span>
                                    <strong>
                                        <span x-text="formatUsage(droneBandwidthUsed(), droneBandwidthCapacity(), ' Mbit')"></span>
                                        <span class="limit-warning" x-show="droneBandwidthUsed() > droneBandwidthCapacity()">超上限</span>
                                    </strong>
                                </div>
                                <div class="resource-row" style="margin-top:8px;">
                                    <span>炮台硬点剩余</span>
                                    <strong x-text="remainingHardpoints('turret')"></strong>
                                </div>
                                <div class="resource-row" style="margin-top:8px;">
                                    <span>发射器硬点剩余</span>
                                    <strong x-text="remainingHardpoints('launcher')"></strong>
                                </div>
                            </div>

                            <div class="stats-card">
                                <div class="slot-header">
                                    <span>舰船基础属性</span>
                                </div>
                                <div class="stats-row"><span>护盾</span><strong x-text="numberAttr('shieldCapacity')"></strong></div>
                                <div class="stats-row" style="margin-top:8px;"><span>装甲</span><strong x-text="numberAttr('armorHP')"></strong></div>
                                <div class="stats-row" style="margin-top:8px;"><span>结构</span><strong x-text="numberAttr('hp')"></strong></div>
                                <div class="stats-row" style="margin-top:8px;"><span>最大速度</span><strong x-text="numberAttr('maxVelocity', ' m/s')"></strong></div>
                                <div class="stats-row" style="margin-top:8px;"><span>信号半径</span><strong x-text="numberAttr('signatureRadius', ' m')"></strong></div>
                                <div class="stats-row" style="margin-top:8px;"><span>锁定距离</span><strong x-text="distanceAttr('maxTargetRange')"></strong></div>
                                <div class="stats-row" style="margin-top:8px;"><span>扫描分辨率</span><strong x-text="numberAttr('scanResolution', ' mm')"></strong></div>
                                <div class="stats-row" style="margin-top:8px;"><span>无人机带宽</span><strong x-text="numberAttr('droneBandwidth', ' Mbit/s')"></strong></div>
                                <div class="stats-row" style="margin-top:8px;"><span>无人机容量</span><strong x-text="numberAttr('droneCapacity', ' m3')"></strong></div>
                            </div>

                            <div class="notice info">
                                这一步先保证“选船、装装备、资源检查”稳定可用。更复杂的属性实时变化，会放到下一阶段单独做。
                            </div>
                        </div>
                    </template>

                    <template x-if="!selectedShip">
                        <div class="empty-state">选中舰船后，这里会显示资源占用和基础属性。</div>
                    </template>
                </div>
            </section>
        </div>
    </div>
</div>

<script>
    function fittingStageOne() {
        return {
            shipTree: @json($shipTree ?? []),
            shipExpandedNodes: {},
            shipLeafResults: {},
            shipLeafLoadingKey: null,
            activeTab: 'ships',
            moduleSearchQuery: '',
            moduleSlotFilter: null,
            moduleTree: [],
            moduleExpandedNodes: {},
            moduleLeafResults: {},
            rarityExpandedGroups: {},
            moduleLeafLoadingKey: null,
            moduleResults: [],
            moduleLoading: false,
            filterShipRestrictions: false,
            filterResources: false,
            selectedShip: null,
            shipStats: null,
            selectedSlot: null,
            fittedModules: { high: [], med: [], low: [], rig: [] },
            fittedDrones: [],
            resourceUsage: {
                cpu: { used: 0, total: 0 },
                power: { used: 0, total: 0 },
                calibration: { used: 0, total: 0 },
            },
            notice: { message: '', type: 'info' },

            init() {
                this.loadModuleTree(null);
            },

            setNotice(message, type = 'info') {
                this.notice = { message, type };
            },

            flattenTree(nodes, expandedMap, depth = 0, list = []) {
                (nodes || []).forEach((node) => {
                    const path = node.path || [node.name];
                    const key = path.join(' > ');
                    const hasChildren = !!(node.children && node.children.length);

                    list.push({
                        key,
                        name: node.name,
                        path,
                        count: node.count || 0,
                        depth,
                        hasChildren,
                    });

                    if (hasChildren && expandedMap[key]) {
                        this.flattenTree(node.children, expandedMap, depth + 1, list);
                    }
                });

                return list;
            },

            visibleShipNodes() {
                return this.flattenTree(this.shipTree, this.shipExpandedNodes);
            },

            visibleModuleNodes() {
                return this.flattenTree(this.moduleTree, this.moduleExpandedNodes);
            },

            isExpanded(expandedMap, key) {
                return !!expandedMap[key];
            },

            toggleNode(expandedMap, key) {
                expandedMap[key] = !expandedMap[key];
            },

            toggleRarityGroup(key) {
                this.rarityExpandedGroups[key] = !this.rarityExpandedGroups[key];
            },

            isRarityGroupExpanded(key) {
                return !!this.rarityExpandedGroups[key];
            },

            groupedLeafModules(nodeKey) {
                const modules = (this.moduleLeafResults[nodeKey] || []).filter((module) => this.modulePassesActiveFilters(module));
                const entries = [];
                const specialGroups = {
                    '势力与故事线': [],
                    '官员': [],
                };

                modules.forEach((module) => {
                    if (module.rarity_group && specialGroups[module.rarity_group]) {
                        specialGroups[module.rarity_group].push(module);
                        return;
                    }

                    entries.push({
                        type: 'module',
                        key: `module-${module.type_id}`,
                        module,
                    });
                });

                Object.entries(specialGroups).forEach(([label, groupedModules]) => {
                    if (!groupedModules.length) {
                        return;
                    }

                    entries.push({
                        type: 'group',
                        key: `${nodeKey}::${label}`,
                        label,
                        modules: groupedModules,
                    });
                });

                return entries;
            },

            categoryIcon(name) {
                const iconMap = {
                    '舰船': '舰',
                    '护卫舰': '护',
                    '驱逐舰': '驱',
                    '巡洋舰': '巡',
                    '战列巡洋舰': '战',
                    '战列舰': '列',
                    '旗舰': '旗',
                    '工业舰': '工',
                    '采集设备': '采',
                    '船体和装甲': '甲',
                    '电子学和感应器升级': '感',
                    '电子战': '扰',
                    '工程装备': '能',
                    '护盾': '盾',
                    '舰队辅助装备': '辅',
                    '立体炸弹': '爆',
                    '炮台和发射器': '炮',
                    '扫描设备': '扫',
                    '推进器': '推',
                    '无人机升级模块': '无',
                    '压缩装置': '压',
                    '改装件': '改',
                    '子系统': '子',
                    '无人机': '机',
                };

                return iconMap[name] || (name ? name.slice(0, 1) : '·');
            },

            async onShipNodeClick(node) {
                if (node.hasChildren) {
                    this.toggleNode(this.shipExpandedNodes, node.key);
                    return;
                }

                if (this.isExpanded(this.shipExpandedNodes, node.key)) {
                    this.toggleNode(this.shipExpandedNodes, node.key);
                    return;
                }

                this.shipExpandedNodes[node.key] = true;
                if (this.shipLeafResults[node.key]) {
                    return;
                }

                this.shipLeafLoadingKey = node.key;
                try {
                    const response = await fetch(`/api/public/fitting-simulator/ships-by-category-path?path=${encodeURIComponent(JSON.stringify(node.path))}`);
                    if (!response.ok) {
                        throw new Error('load ships by path failed');
                    }
                    const data = await response.json();
                    this.shipLeafResults[node.key] = data.ships || [];
                } catch (error) {
                    this.shipLeafResults[node.key] = [];
                    this.setNotice('这个舰船分类暂时没有成功加载出来。', 'warn');
                } finally {
                    this.shipLeafLoadingKey = null;
                }
            },

            async loadModuleTree(slotType) {
                try {
                    const query = this.buildModuleFilterParams();
                    if (slotType) {
                        query.set('slot', slotType);
                    }
                    const suffix = query.toString() ? `?${query.toString()}` : '';
                    const response = await fetch(`/api/public/fitting-simulator/official/module-category-tree${suffix}`);
                    if (!response.ok) {
                        throw new Error('load module tree failed');
                    }
                    this.moduleTree = await response.json();
                    this.moduleExpandedNodes = {};
                    this.moduleLeafResults = {};
                    this.moduleLeafLoadingKey = null;
                } catch (error) {
                    this.moduleTree = [];
                    this.setNotice('装备分类树加载失败了。', 'warn');
                }
            },

            async onModuleNodeClick(node) {
                if (node.hasChildren) {
                    this.toggleNode(this.moduleExpandedNodes, node.key);
                    return;
                }

                if (this.isExpanded(this.moduleExpandedNodes, node.key)) {
                    this.toggleNode(this.moduleExpandedNodes, node.key);
                    return;
                }

                this.moduleExpandedNodes[node.key] = true;
                if (this.moduleLeafResults[node.key]) {
                    return;
                }

                this.moduleLeafLoadingKey = node.key;
                try {
                    const query = new URLSearchParams({
                        path: JSON.stringify(node.path),
                    });
                    if (this.moduleSlotFilter) {
                        query.set('slot', this.moduleSlotFilter);
                    }
                    this.appendModuleFilterParams(query);

                    const response = await fetch(`/api/public/fitting-simulator/official/modules-by-category-path?${query.toString()}`);
                    if (!response.ok) {
                        throw new Error('load modules by path failed');
                    }
                    const data = await response.json();
                    this.moduleLeafResults[node.key] = data.modules || [];
                } catch (error) {
                    this.moduleLeafResults[node.key] = [];
                    this.setNotice('这个装备分类暂时没有成功加载出来。', 'warn');
                } finally {
                    this.moduleLeafLoadingKey = null;
                }
            },

            async selectShip(ship) {
                try {
                    const response = await fetch(`/api/public/fitting-simulator/ships/${ship.type_id}`);
                    if (!response.ok) {
                        throw new Error('load ship detail failed');
                    }

                    this.shipStats = await response.json();
                    this.selectedShip = {
                        ...ship,
                        image_url: this.shipStats.image_url || ship.image_url,
                        render_url: this.shipStats.render_url || ship.render_url,
                    };
                    this.selectedSlot = null;
                    this.fittedModules = {
                        high: Array(this.slotCapacity('high')).fill(null),
                        med: Array(this.slotCapacity('med')).fill(null),
                        low: Array(this.slotCapacity('low')).fill(null),
                        rig: Array(this.slotCapacity('rig')).fill(null),
                    };
                    this.fittedDrones = [];
                    this.resourceUsage.cpu.total = Number(this.shipStats.resources?.cpu_output || 0);
                    this.resourceUsage.power.total = Number(this.shipStats.resources?.power_output || 0);
                    this.resourceUsage.calibration.total = Number(this.shipStats.resources?.upgrade_capacity || 0);
                    this.updateResourceUsage();
                    if (this.filterShipRestrictions || this.filterResources) {
                        this.reloadModuleFilters();
                    }
                    this.setNotice('舰船已载入。现在可以直接点左侧装备自动安装，也可以先点指定槽位。', 'info');
                } catch (error) {
                    this.setNotice('舰船详情加载失败了，这次没有成功拿到数据。', 'warn');
                }
            },

            slotCapacity(slotType) {
                if (!this.shipStats) {
                    return 0;
                }
                const mapping = { high: 'hi', med: 'med', low: 'low', rig: 'rig' };
                return Number(this.shipStats.slots?.[mapping[slotType]] || 0);
            },

            filledCount(slotType) {
                return (this.fittedModules[slotType] || []).filter(Boolean).length;
            },

            slotModule(slotType, index) {
                return this.fittedModules[slotType]?.[index] || null;
            },

            selectSlot(slotType, index) {
                this.selectedSlot = { type: slotType, index };
                this.activeTab = 'modules';
                this.setModuleSlotFilter(slotType);
                if (this.moduleSearchQuery) {
                    this.searchModules();
                }
                this.setNotice(`已选中${this.slotLabel(slotType)}第 ${index + 1} 个槽位。现在点左侧装备即可安装。`, 'info');
            },

            setModuleSlotFilter(slotType) {
                this.moduleSlotFilter = slotType;
                this.loadModuleTree(slotType);
                this.moduleLeafResults = {};
                this.moduleLeafLoadingKey = null;
                if (this.moduleSearchQuery) {
                    this.searchModules();
                }
            },

            reloadModuleFilters() {
                this.moduleLeafResults = {};
                this.moduleLeafLoadingKey = null;
                this.loadModuleTree(this.moduleSlotFilter);
                if (this.moduleSearchQuery) {
                    this.searchModules();
                }
            },

            buildModuleFilterParams() {
                const query = new URLSearchParams();
                this.appendModuleFilterParams(query);
                return query;
            },

            appendModuleFilterParams(query) {
                if (!this.selectedShip || !this.shipStats) {
                    return;
                }

                if (this.filterShipRestrictions) {
                    query.set('filter_ship', '1');
                }
                if (this.filterResources) {
                    query.set('filter_resources', '1');
                }
                if (!this.filterShipRestrictions && !this.filterResources) {
                    return;
                }

                query.set('ship_type_id', String(this.shipStats.type_id || this.selectedShip.type_id || 0));
                query.set('ship_group_id', String(this.shipStats.group_id || this.selectedShip.group_id || 0));
                query.set('ship_is_capital_size', this.shipIsCapitalSize() ? '1' : '0');
                query.set('slot_high', String(this.slotCapacity('high')));
                query.set('slot_med', String(this.slotCapacity('med')));
                query.set('slot_low', String(this.slotCapacity('low')));
                query.set('slot_rig', String(this.slotCapacity('rig')));
                query.set('ship_rig_size', String(this.shipRigSize()));
                query.set('drone_bay_capacity', String(this.droneBayCapacity()));
                query.set('remaining_cpu', String(this.remainingCpu()));
                query.set('remaining_power', String(this.remainingPower()));
                query.set('remaining_calibration', String(this.remainingCalibration()));
                query.set('remaining_drone_bay', String(this.remainingDroneBay()));
                query.set('remaining_drone_bandwidth', String(this.remainingDroneBandwidth()));
            },

            async searchModules() {
                if (!this.moduleSearchQuery) {
                    this.moduleResults = [];
                    return;
                }

                this.moduleLoading = true;
                try {
                    const params = new URLSearchParams();
                    params.set('q', this.moduleSearchQuery || '');
                    if (this.moduleSlotFilter) {
                        params.set('slot', this.moduleSlotFilter);
                    }
                    params.set('limit', '50');

                    const response = await fetch(`/api/public/fitting-simulator/search?${params.toString()}`);
                    if (!response.ok) {
                        throw new Error('search failed');
                    }

                    const data = await response.json();
                    this.moduleResults = data.results || [];
                } catch (error) {
                    this.moduleResults = [];
                    this.setNotice('装备搜索刚才没有成功，可以稍后再试一次。', 'warn');
                } finally {
                    this.moduleLoading = false;
                }
            },

            async addModule(module) {
                if (!this.selectedShip) {
                    this.setNotice('要先选舰船，才能开始装装备。', 'warn');
                    return;
                }

                let moduleDetails = module;
                if (!moduleDetails.attributes || !moduleDetails.effects) {
                    try {
                        const response = await fetch(`/api/public/fitting-simulator/types/${module.type_id}`);
                        if (!response.ok) {
                            throw new Error('load type failed');
                        }
                        const fetchedDetails = await response.json();
                        moduleDetails = {
                            ...module,
                            ...fetchedDetails,
                            slot: module.slot || fetchedDetails.slot,
                            group_id: module.group_id || fetchedDetails.group_id,
                            cpu: module.cpu ?? fetchedDetails.cpu ?? fetchedDetails.attributes?.[50] ?? 0,
                            power: module.power ?? fetchedDetails.power ?? fetchedDetails.attributes?.[30] ?? 0,
                            upgrade_cost: module.upgrade_cost ?? fetchedDetails.upgrade_cost ?? fetchedDetails.attributes?.[1153] ?? 0,
                            rig_size: module.rig_size ?? fetchedDetails.rig_size ?? fetchedDetails.attributes?.[1547] ?? 0,
                            max_group_fitted: module.max_group_fitted ?? fetchedDetails.max_group_fitted ?? fetchedDetails.attributes?.[1544] ?? 0,
                            max_type_fitted: module.max_type_fitted ?? fetchedDetails.max_type_fitted ?? fetchedDetails.attributes?.[2431] ?? 0,
                            drone_bandwidth_used: module.drone_bandwidth_used ?? fetchedDetails.drone_bandwidth_used ?? fetchedDetails.attributes?.[1272] ?? 0,
                            effect_ids: module.effect_ids || fetchedDetails.effect_ids,
                            volume: fetchedDetails.volume ?? module.volume ?? 0,
                            image_url: module.image_url || fetchedDetails.image_url,
                        };
                    } catch (error) {
                        this.setNotice('装备详情没有取到，所以这次没能安装。', 'warn');
                        return;
                    }
                }

                const realSlot = moduleDetails.slot || module.slot || null;
                if (!this.moduleCanInstallOnSelectedShip(moduleDetails)) {
                    this.setNotice('这个装备当前舰船不能安装，可能是舰船限制或改装件尺寸不匹配。', 'warn');
                    return;
                }

                if (realSlot === 'drone') {
                    this.addDrone(moduleDetails);
                    return;
                }

                const slotType = this.targetSlotType(realSlot);
                if (!slotType) {
                    this.setNotice('这个物品暂时还不能安装到当前装配里。', 'warn');
                    return;
                }

                let slotIndex = this.findTargetSlotIndex(slotType);
                if (slotIndex === null) {
                    this.setNotice(`${this.slotLabel(slotType)}已经满了。`, 'warn');
                    return;
                }

                if (slotType === 'high') {
                    const replacingModule = this.fittedModules[slotType]?.[slotIndex] || null;
                    if (!this.modulePassesFittedQuantityLimits(moduleDetails, replacingModule)) {
                        return;
                    }
                    if (this.isTurretModule(moduleDetails) && this.remainingHardpoints('turret', replacingModule) <= 0) {
                        this.setNotice('炮台硬点已经用完了。', 'warn');
                        return;
                    }
                    if (this.isLauncherModule(moduleDetails) && this.remainingHardpoints('launcher', replacingModule) <= 0) {
                        this.setNotice('发射器硬点已经用完了。', 'warn');
                        return;
                    }
                }

                if (slotType !== 'high') {
                    const replacingModule = this.fittedModules[slotType]?.[slotIndex] || null;
                    if (!this.modulePassesFittedQuantityLimits(moduleDetails, replacingModule)) {
                        return;
                    }
                }

                if (this.filterResources && !this.modulePassesResourceLimit(moduleDetails)) {
                    this.setNotice('CPU、能量栅格或校准值不够。关闭“资源足够”筛选后，可以先放上去看超了多少。', 'warn');
                    return;
                }

                this.fittedModules[slotType][slotIndex] = {
                    ...moduleDetails,
                    slot: slotType,
                };
                this.selectedSlot = { type: slotType, index: slotIndex };
                this.updateResourceUsage();
                if (this.filterResources) {
                    this.reloadModuleFilters();
                }
                this.setNotice(`已安装到${this.slotLabel(slotType)}第 ${slotIndex + 1} 个槽位。`, 'info');
            },

            targetSlotType(slotType) {
                if (slotType === 'subsystem') {
                    return 'rig';
                }
                return ['high', 'med', 'low', 'rig'].includes(slotType) ? slotType : null;
            },

            findTargetSlotIndex(slotType) {
                const capacity = this.slotCapacity(slotType);
                if (this.selectedSlot?.type === slotType && this.selectedSlot.index < capacity && !this.slotModule(slotType, this.selectedSlot.index)) {
                    return this.selectedSlot.index;
                }

                const slots = this.fittedModules[slotType] || [];
                for (let index = 0; index < capacity; index += 1) {
                    if (!slots[index]) {
                        return index;
                    }
                }

                return null;
            },

            addDrone(drone) {
                const capacity = this.droneBayCapacity();
                if (capacity <= 0) {
                    this.setNotice('这艘舰船没有无人机舱，不能加入无人机。', 'warn');
                    return;
                }

                const volume = this.moduleDroneVolume(drone);
                if (volume <= 0) {
                    this.setNotice('这个无人机缺少体积数据，暂时不能加入无人机舱。', 'warn');
                    return;
                }

                if (this.filterResources && this.droneBayUsed() + volume > capacity) {
                    this.setNotice('无人机舱容量不够了。', 'warn');
                    return;
                }

                const bandwidth = this.moduleDroneBandwidth(drone);
                if (this.filterResources && bandwidth > 0 && this.droneBandwidthUsed() + bandwidth > this.droneBandwidthCapacity()) {
                    this.setNotice('无人机带宽不够了。', 'warn');
                    return;
                }

                const existing = this.fittedDrones.find((item) => Number(item.type_id) === Number(drone.type_id));
                if (existing) {
                    existing.quantity += 1;
                } else {
                    this.fittedDrones.push({
                        ...drone,
                        quantity: 1,
                        volume,
                    });
                }

                if (this.filterResources) {
                    this.reloadModuleFilters();
                }
                this.setNotice(`已加入无人机舱：${this.moduleDisplayName(drone)}。`, 'info');
            },

            removeModule(slotType, index) {
                if (!this.fittedModules[slotType]?.[index]) {
                    return;
                }
                this.fittedModules[slotType][index] = null;
                this.updateResourceUsage();
                if (this.filterResources) {
                    this.reloadModuleFilters();
                }
                this.setNotice(`已从${this.slotLabel(slotType)}第 ${index + 1} 个槽位卸下装备。`, 'info');
            },

            removeDrone(typeId) {
                const existing = this.fittedDrones.find((item) => Number(item.type_id) === Number(typeId));
                if (!existing) {
                    return;
                }

                existing.quantity -= 1;
                if (existing.quantity <= 0) {
                    this.fittedDrones = this.fittedDrones.filter((item) => Number(item.type_id) !== Number(typeId));
                }

                if (this.filterResources) {
                    this.reloadModuleFilters();
                }
                this.setNotice('已从无人机舱卸下一架无人机。', 'info');
            },

            clearAllSlots() {
                if (!this.selectedShip) {
                    return;
                }
                this.fittedModules = {
                    high: Array(this.slotCapacity('high')).fill(null),
                    med: Array(this.slotCapacity('med')).fill(null),
                    low: Array(this.slotCapacity('low')).fill(null),
                    rig: Array(this.slotCapacity('rig')).fill(null),
                };
                this.fittedDrones = [];
                this.selectedSlot = null;
                this.updateResourceUsage();
                if (this.filterResources) {
                    this.reloadModuleFilters();
                }
                this.setNotice('当前舰船的装配已经清空。', 'info');
            },

            updateResourceUsage() {
                let cpu = 0;
                let power = 0;
                let calibration = 0;

                ['high', 'med', 'low', 'rig'].forEach((slotType) => {
                    (this.fittedModules[slotType] || []).forEach((module) => {
                        if (!module) {
                            return;
                        }
                        cpu += this.moduleCpuUsage(module);
                        power += this.modulePowerUsage(module);
                        calibration += this.moduleCalibrationUsage(module);
                    });
                });

                this.resourceUsage.cpu.used = cpu;
                this.resourceUsage.power.used = power;
                this.resourceUsage.calibration.used = calibration;
            },

            modulePassesActiveFilters(module) {
                return this.modulePassesShipFilter(module) && this.modulePassesResourceFilter(module);
            },

            modulePassesShipFilter(module) {
                if (!this.filterShipRestrictions || !this.shipStats) {
                    return true;
                }

                const slotType = module.slot === 'drone' ? 'drone' : this.targetSlotType(module.slot);
                if (!slotType) {
                    return false;
                }
                if (slotType !== 'drone' && this.slotCapacity(slotType) <= 0) {
                    return false;
                }
                if (slotType === 'drone' && this.droneBayCapacity() <= 0) {
                    return false;
                }

                if (module.slot === 'subsystem' && !this.subsystemMatchesSelectedShip(module)) {
                    return false;
                }

                if (module.slot === 'rig' && !this.rigMatchesSelectedShip(module)) {
                    return false;
                }

                if (this.moduleRequiresCapitalShip(module) && !this.shipIsCapitalSize()) {
                    return false;
                }

                const allowedGroups = module.can_fit_ship_groups || [];
                const allowedTypes = module.can_fit_ship_types || [];
                if (!allowedGroups.length && !allowedTypes.length) {
                    return true;
                }

                return this.canFitAllowedTargets(allowedGroups, allowedTypes);
            },

            moduleCanInstallOnSelectedShip(module) {
                if (!this.shipStats) {
                    return false;
                }

                if (module.slot === 'subsystem' && !this.subsystemMatchesSelectedShip(module)) {
                    return false;
                }

                if (module.slot === 'rig' && !this.rigMatchesSelectedShip(module)) {
                    return false;
                }

                if (this.moduleRequiresCapitalShip(module) && !this.shipIsCapitalSize()) {
                    return false;
                }

                const slotType = module.slot === 'drone' ? 'drone' : this.targetSlotType(module.slot);
                if (!slotType) {
                    return false;
                }

                if (slotType !== 'drone' && this.slotCapacity(slotType) <= 0) {
                    return false;
                }

                if (slotType === 'drone' && this.droneBayCapacity() <= 0) {
                    return false;
                }

                const allowedGroups = module.can_fit_ship_groups || [];
                const allowedTypes = module.can_fit_ship_types || [];
                if (!allowedGroups.length && !allowedTypes.length) {
                    return true;
                }

                return this.canFitAllowedTargets(allowedGroups, allowedTypes);
            },

            canFitAllowedTargets(allowedGroups, allowedTypes) {
                const shipTypeId = Number(this.shipStats?.type_id || this.selectedShip?.type_id || 0);
                const shipGroupId = Number(this.shipStats?.group_id || this.selectedShip?.group_id || 0);
                return allowedTypes.map(Number).includes(shipTypeId) || allowedGroups.map(Number).includes(shipGroupId);
            },

            moduleRequiresCapitalShip(module) {
                const slotType = module.slot === 'drone' ? 'drone' : this.targetSlotType(module.slot);
                if (!['high', 'med', 'low'].includes(slotType)) {
                    return false;
                }

                const category = Array.isArray(module.category) ? module.category.join(' ') : String(module.category || '');
                const text = [
                    module.name_cn || '',
                    module.name || '',
                    module.category_label || '',
                    category,
                ].join(' ');

                const keywords = ['\u65d7\u8230', '\u8d85\u5927\u578b', 'Capital', 'Doomsday'];
                if (keywords.some((keyword) => text.includes(keyword))) {
                    return true;
                }

                return Number(module.charge_size || module.attributes?.[128] || 0) >= 4;
            },

            shipIsCapitalSize() {
                return Boolean(this.shipStats?.resources?.is_capital_size)
                    || Number(this.shipStats?.attributes?.isCapitalSize?.value || this.shipStats?.attributes?.attr_1785?.value || 0) > 0;
            },

            subsystemMatchesSelectedShip(module) {
                const name = this.moduleDisplayName(module);
                const subsystemShipMap = {
                    '金鹏': 29984,
                    '圣卒': 29986,
                    '海神': 29988,
                    '洛基': 29990,
                };

                for (const [prefix, shipTypeId] of Object.entries(subsystemShipMap)) {
                    if (name.startsWith(prefix)) {
                        return Number(this.shipStats?.type_id || this.selectedShip?.type_id || 0) === shipTypeId;
                    }
                }

                return false;
            },

            rigMatchesSelectedShip(module) {
                const moduleRigSize = Number(module.rig_size || module.attributes?.[1547] || 0);
                const shipRigSize = this.shipRigSize();
                if (!moduleRigSize || !shipRigSize) {
                    return true;
                }

                return moduleRigSize === shipRigSize;
            },

            modulePassesResourceFilter(module) {
                if (!this.filterResources || !this.shipStats) {
                    return true;
                }

                return this.modulePassesResourceLimit(module);
            },

            modulePassesResourceLimit(module) {
                if (!this.shipStats) {
                    return false;
                }

                if (module.slot === 'drone') {
                    return this.moduleDroneVolume(module) <= this.remainingDroneBay()
                        && this.moduleDroneBandwidth(module) <= this.remainingDroneBandwidth();
                }

                const slotType = this.targetSlotType(module.slot);
                if (!slotType) {
                    return false;
                }

                const cpu = this.moduleCpuUsage(module);
                const power = this.modulePowerUsage(module);
                const calibration = this.moduleCalibrationUsage(module);

                if (cpu > this.remainingCpu()) {
                    return false;
                }
                if (power > this.remainingPower()) {
                    return false;
                }
                if (slotType === 'rig' && calibration > this.remainingCalibration()) {
                    return false;
                }

                return true;
            },

            remainingCpu() {
                return Math.max(Number(this.resourceUsage.cpu.total || 0) - Number(this.resourceUsage.cpu.used || 0), 0);
            },

            moduleCpuUsage(module) {
                return Number(module?.cpu ?? module?.attributes?.[50] ?? 0);
            },

            modulePowerUsage(module) {
                return Number(module?.power ?? module?.attributes?.[30] ?? 0);
            },

            moduleCalibrationUsage(module) {
                return Number(module?.upgrade_cost ?? module?.attributes?.[1153] ?? 0);
            },

            moduleDroneVolume(module) {
                return Number(module?.volume ?? 0);
            },

            moduleDroneBandwidth(module) {
                return Number(module?.drone_bandwidth_used ?? module?.attributes?.[1272] ?? 0);
            },

            remainingPower() {
                return Math.max(Number(this.resourceUsage.power.total || 0) - Number(this.resourceUsage.power.used || 0), 0);
            },

            remainingCalibration() {
                return Math.max(Number(this.resourceUsage.calibration.total || 0) - Number(this.resourceUsage.calibration.used || 0), 0);
            },

            droneBayCapacity() {
                return Number(this.shipStats?.resources?.drone_capacity || this.shipStats?.attributes?.droneCapacity?.value || 0);
            },

            shipRigSize() {
                return Number(this.shipStats?.resources?.rig_size || this.shipStats?.attributes?.rigSize?.value || this.shipStats?.attributes?.attr_1547?.value || 0);
            },

            droneBayUsed() {
                return this.fittedDrones.reduce((total, drone) => total + (this.moduleDroneVolume(drone) * Number(drone.quantity || 0)), 0);
            },

            remainingDroneBay() {
                return Math.max(this.droneBayCapacity() - this.droneBayUsed(), 0);
            },

            droneBandwidthCapacity() {
                return Number(this.shipStats?.resources?.drone_bandwidth || this.shipStats?.attributes?.droneBandwidth?.value || 0);
            },

            droneBandwidthUsed() {
                return this.fittedDrones.reduce((total, drone) => total + (this.moduleDroneBandwidth(drone) * Number(drone.quantity || 0)), 0);
            },

            remainingDroneBandwidth() {
                return Math.max(this.droneBandwidthCapacity() - this.droneBandwidthUsed(), 0);
            },

            remainingHardpoints(type, ignoredModule = null) {
                const total = Number(this.shipStats?.hardpoints?.[type] || 0);
                let used = 0;
                (this.fittedModules.high || []).forEach((module) => {
                    if (!module || module === ignoredModule) {
                        return;
                    }
                    if (type === 'turret' && this.isTurretModule(module)) {
                        used += 1;
                    }
                    if (type === 'launcher' && this.isLauncherModule(module)) {
                        used += 1;
                    }
                });
                return Math.max(total - used, 0);
            },

            modulePassesFittedQuantityLimits(module, ignoredModule = null) {
                const maxGroupFitted = Number(module.max_group_fitted ?? module.attributes?.[1544] ?? 0);
                if (maxGroupFitted > 0 && module.group_id) {
                    const fittedInGroup = this.fittedModuleCount((fitted) => Number(fitted.group_id) === Number(module.group_id), ignoredModule);
                    if (fittedInGroup >= maxGroupFitted) {
                        this.setNotice(`${this.moduleDisplayName(module)}同类装备最多只能安装 ${maxGroupFitted} 个。`, 'warn');
                        return false;
                    }
                }

                const maxTypeFitted = Number(module.max_type_fitted ?? module.attributes?.[2431] ?? 0);
                if (maxTypeFitted > 0) {
                    const fittedSameType = this.fittedModuleCount((fitted) => Number(fitted.type_id) === Number(module.type_id), ignoredModule);
                    if (fittedSameType >= maxTypeFitted) {
                        this.setNotice(`${this.moduleDisplayName(module)}最多只能安装 ${maxTypeFitted} 个。`, 'warn');
                        return false;
                    }
                }

                return true;
            },

            fittedModuleCount(predicate, ignoredModule = null) {
                let count = 0;
                ['high', 'med', 'low', 'rig'].forEach((slotType) => {
                    (this.fittedModules[slotType] || []).forEach((module) => {
                        if (!module || module === ignoredModule) {
                            return;
                        }
                        if (predicate(module)) {
                            count += 1;
                        }
                    });
                });
                return count;
            },

            isTurretModule(module) {
                const effectIds = module.effect_ids || (module.effects || []).map((effect) => Number(effect.effect_id));
                return effectIds.map(Number).includes(42);
            },

            isLauncherModule(module) {
                const effectIds = module.effect_ids || (module.effects || []).map((effect) => Number(effect.effect_id));
                return effectIds.map(Number).includes(40);
            },

            slotLabel(slotType) {
                return { high: '高槽', med: '中槽', low: '低槽', rig: '改装槽', drone: '无人机', subsystem: '子系统' }[slotType] || slotType;
            },

            shipDisplayName(ship) {
                return ship?.name_cn || ship?.name || '未命名舰船';
            },

            moduleDisplayName(module) {
                return module?.name_cn || module?.name || '未命名装备';
            },

            moduleMeta(module) {
                const parts = [];
                parts.push(this.slotLabel(module.slot || '未知'));
                if (module.category_label) {
                    parts.push(module.category_label);
                }
                if (module.cpu) {
                    parts.push(`CPU ${this.moduleCpuUsage(module).toFixed(1)}`);
                }
                if (module.power) {
                    parts.push(`能量栅格 ${this.modulePowerUsage(module).toFixed(1)}`);
                }
                if (module.upgrade_cost) {
                    parts.push(`校准 ${this.moduleCalibrationUsage(module).toFixed(0)}`);
                }
                return parts.join(' / ');
            },

            groupedModuleResults() {
                const groups = {};
                (this.moduleResults || []).filter((module) => this.modulePassesActiveFilters(module)).forEach((module) => {
                    const label = module.category_label || '其他';
                    if (!groups[label]) {
                        groups[label] = [];
                    }
                    groups[label].push(module);
                });

                return Object.entries(groups).map(([label, items]) => ({ label, items }));
            },

            formatUsage(used, total, suffix) {
                return `${Number(used).toFixed(1)} / ${Number(total).toFixed(1)}${suffix}`;
            },

            numberAttr(key, suffix = '') {
                const value = Number(this.shipStats?.attributes?.[key]?.value || 0);
                return `${value.toLocaleString(undefined, { maximumFractionDigits: 1 })}${suffix}`;
            },

            distanceAttr(key) {
                const value = Number(this.shipStats?.attributes?.[key]?.value || 0);
                if (value >= 1000) {
                    return `${(value / 1000).toFixed(1)} km`;
                }
                return `${value.toFixed(0)} m`;
            },
        };
    }
</script>
@endsection
