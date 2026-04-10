@extends('layouts.app')

@section('page-title', '装配模拟器')

@push('styles')
<style>
    .fitting-page { min-height: calc(100vh - 180px); padding: 12px; }
    .fitting-grid { display: grid; grid-template-columns: 320px minmax(360px, 1fr) 320px; gap: 12px; }
    .fitting-panel { display: flex; flex-direction: column; min-height: 720px; background: rgba(15, 23, 42, 0.75); border: 1px solid rgba(148, 163, 184, 0.18); border-radius: 12px; overflow: hidden; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.22); }
    .fitting-panel__header { padding: 12px 14px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(148, 163, 184, 0.14); background: linear-gradient(90deg, rgba(59, 130, 246, 0.18), rgba(15, 23, 42, 0)); }
    .fitting-panel__title { font-size: 14px; font-weight: 700; color: #e2e8f0; }
    .fitting-panel__body { flex: 1; overflow-y: auto; padding: 12px; }
    .fitting-tabs, .slot-filter { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-bottom: 12px; }
    .slot-filter { grid-template-columns: repeat(5, 1fr); }
    .fitting-btn { border: 1px solid rgba(148, 163, 184, 0.18); background: rgba(30, 41, 59, 0.75); color: #cbd5e1; border-radius: 10px; padding: 8px 10px; font-size: 13px; cursor: pointer; transition: 0.18s ease; }
    .fitting-btn:hover { border-color: rgba(96, 165, 250, 0.45); color: #f8fafc; }
    .fitting-btn.is-active { background: rgba(37, 99, 235, 0.28); border-color: rgba(96, 165, 250, 0.65); color: #eff6ff; }
    .fitting-btn.is-danger { background: rgba(127, 29, 29, 0.24); border-color: rgba(248, 113, 113, 0.38); color: #fee2e2; }
    .fitting-input { width: 100%; border: 1px solid rgba(148, 163, 184, 0.18); background: rgba(15, 23, 42, 0.88); color: #e2e8f0; border-radius: 10px; padding: 10px 12px; font-size: 13px; outline: none; margin-bottom: 12px; }
    .fitting-input:focus { border-color: rgba(96, 165, 250, 0.55); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.16); }
    .notice { margin-bottom: 12px; padding: 10px 12px; border-radius: 10px; font-size: 13px; line-height: 1.5; }
    .notice.info { background: rgba(30, 64, 175, 0.18); border: 1px solid rgba(96, 165, 250, 0.22); color: #dbeafe; }
    .notice.warn { background: rgba(120, 53, 15, 0.22); border: 1px solid rgba(251, 191, 36, 0.24); color: #fef3c7; }
    .tree-group, .card-list, .stats-block, .slot-block { display: flex; flex-direction: column; gap: 8px; }
    .tree-item, .list-item, .slot-item, .stats-card { border: 1px solid rgba(148, 163, 184, 0.14); background: rgba(15, 23, 42, 0.78); border-radius: 10px; }
    .tree-head, .list-item, .slot-item, .stats-card { padding: 10px 12px; }
    .tree-head { display: flex; align-items: center; justify-content: space-between; cursor: pointer; color: #e2e8f0; font-size: 13px; font-weight: 600; }
    .tree-children { padding: 0 8px 8px; display: flex; flex-direction: column; gap: 6px; }
    .faction-item { display: flex; align-items: center; justify-content: space-between; padding: 8px 10px; border-radius: 8px; cursor: pointer; color: #cbd5e1; background: rgba(30, 41, 59, 0.62); border: 1px solid transparent; font-size: 12px; }
    .faction-item.is-active, .list-item.is-active, .slot-item.is-selected { border-color: rgba(96, 165, 250, 0.56); background: rgba(30, 64, 175, 0.24); }
    .list-item { display: flex; gap: 10px; align-items: center; cursor: pointer; }
    .list-item img, .slot-item img, .ship-summary img { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; background: rgba(255, 255, 255, 0.06); }
    .list-item__text, .slot-item__text { flex: 1; min-width: 0; }
    .item-name { color: #f8fafc; font-size: 13px; font-weight: 600; }
    .item-meta { color: #94a3b8; font-size: 12px; margin-top: 3px; }
    .ship-summary { display: flex; gap: 12px; align-items: center; padding: 12px; margin-bottom: 12px; border-radius: 12px; background: rgba(30, 41, 59, 0.74); border: 1px solid rgba(148, 163, 184, 0.16); }
    .slot-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; color: #e2e8f0; font-size: 13px; font-weight: 700; }
    .slot-item { display: flex; gap: 10px; align-items: center; }
    .slot-index { width: 22px; text-align: center; color: #60a5fa; font-size: 12px; font-weight: 700; }
    .resource-row, .stats-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; color: #cbd5e1; font-size: 13px; }
    .resource-row strong, .stats-row strong { color: #f8fafc; }
    .resource-row.is-danger strong { color: #fca5a5; }
    .empty-state { padding: 18px 12px; text-align: center; color: #94a3b8; font-size: 13px; border: 1px dashed rgba(148, 163, 184, 0.18); border-radius: 10px; }
    .tiny-tip { margin-top: 10px; color: #94a3b8; font-size: 12px; line-height: 1.6; }
    @media (max-width: 1280px) { .fitting-grid { grid-template-columns: 300px 1fr; } .fitting-panel:last-child { grid-column: span 2; min-height: 420px; } }
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
                                <div class="tree-head"
                                     :style="'padding-left:' + (12 + node.depth * 18) + 'px'"
                                     @click="onShipNodeClick(node)">
                                    <span x-text="(node.hasChildren ? (isExpanded(shipExpandedNodes, node.key) ? '▾ ' : '▸ ') : '· ') + node.name"></span>
                                    <span x-text="node.count"></span>
                                </div>
                            </template>
                        </div>

                        <template x-if="shipLeafLoading">
                            <div class="empty-state" style="margin-top:12px;">正在加载这个分类下的舰船...</div>
                        </template>

                        <div class="card-list" style="margin-top:12px;" x-show="!shipLeafLoading && shipLeafResults.length">
                            <template x-for="ship in shipLeafResults" :key="ship.type_id">
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
                    </div>

                    <div x-show="activeTab === 'modules'">
                        <div class="slot-filter">
                            <button class="fitting-btn" :class="{ 'is-active': moduleSlotFilter === null }" @click="setModuleSlotFilter(null)">全部</button>
                            <button class="fitting-btn" :class="{ 'is-active': moduleSlotFilter === 'high' }" @click="setModuleSlotFilter('high')">高槽</button>
                            <button class="fitting-btn" :class="{ 'is-active': moduleSlotFilter === 'med' }" @click="setModuleSlotFilter('med')">中槽</button>
                            <button class="fitting-btn" :class="{ 'is-active': moduleSlotFilter === 'low' }" @click="setModuleSlotFilter('low')">低槽</button>
                            <button class="fitting-btn" :class="{ 'is-active': moduleSlotFilter === 'rig' }" @click="setModuleSlotFilter('rig')">改装</button>
                        </div>

                        <input class="fitting-input" type="text" placeholder="输入装备名称搜索" x-model="moduleSearchQuery" @keydown.enter.prevent="searchModules" @input.debounce.300ms="searchModules">

                        <div class="tiny-tip">先点中间的槽位，再点这里的装备。现在默认走分类树；只有你输入搜索词时，才走搜索列表。</div>

                        <div class="tree-group" style="margin-top:12px;" x-show="!moduleSearchQuery">
                            <template x-for="node in visibleModuleNodes()" :key="node.key">
                                <div class="tree-head"
                                     :style="'padding-left:' + (12 + node.depth * 18) + 'px'"
                                     @click="onModuleNodeClick(node)">
                                    <span x-text="(node.hasChildren ? (isExpanded(moduleExpandedNodes, node.key) ? '▾ ' : '▸ ') : '· ') + node.name"></span>
                                    <span x-text="node.count"></span>
                                </div>
                            </template>
                        </div>

                        <template x-if="moduleLeafLoading && !moduleSearchQuery">
                            <div class="empty-state" style="margin-top:12px;">正在加载这个分类下的装备...</div>
                        </template>

                        <div class="card-list" style="margin-top:12px;" x-show="!moduleSearchQuery && !moduleLeafLoading && moduleLeafResults.length">
                            <template x-for="module in moduleLeafResults" :key="module.type_id">
                                <div class="list-item" @click="addModule(module)">
                                    <img :src="module.image_url" :alt="moduleDisplayName(module)">
                                    <div class="list-item__text">
                                        <div class="item-name" x-text="moduleDisplayName(module)"></div>
                                        <div class="item-meta" x-text="moduleMeta(module)"></div>
                                    </div>
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
                                            <div class="list-item" @click="addModule(module)">
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
                            <template x-if="moduleSearchQuery && !moduleLoading && !moduleResults.length">
                                <div class="empty-state">先搜索装备，或先点一个槽位后再搜索。</div>
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
                                <img :src="selectedShip.image_url" :alt="shipDisplayName(selectedShip)">
                                <div class="list-item__text">
                                    <div class="item-name" x-text="shipDisplayName(selectedShip)"></div>
                                    <div class="item-meta">先点槽位，再点左侧装备列表安装。</div>
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
                                                <div style="width:40px;height:40px;border-radius:8px;background:rgba(30,41,59,0.65);"></div>
                                            </template>
                                            <div class="slot-item__text">
                                                <div class="item-name" x-text="slotModule(slotType, index - 1) ? moduleDisplayName(slotModule(slotType, index - 1)) : '空槽位'"></div>
                                                <div class="item-meta" x-text="selectedSlot?.type === slotType && selectedSlot?.index === (index - 1) ? '当前选中，左侧点装备即可安装' : '点击选择这个槽位'"></div>
                                            </div>
                                            <button class="fitting-btn" style="padding:6px 8px;" x-show="slotModule(slotType, index - 1)" @click.stop="removeModule(slotType, index - 1)">卸下</button>
                                        </div>
                                    </template>
                                </div>
                            </template>
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
                                    <strong x-text="formatUsage(resourceUsage.cpu.used, resourceUsage.cpu.total, ' tf')"></strong>
                                </div>
                                <div class="resource-row" :class="{ 'is-danger': resourceUsage.power.used > resourceUsage.power.total }" style="margin-top:8px;">
                                    <span>电网</span>
                                    <strong x-text="formatUsage(resourceUsage.power.used, resourceUsage.power.total, ' MW')"></strong>
                                </div>
                                <div class="resource-row" :class="{ 'is-danger': resourceUsage.calibration.used > resourceUsage.calibration.total }" style="margin-top:8px;">
                                    <span>校准值</span>
                                    <strong x-text="formatUsage(resourceUsage.calibration.used, resourceUsage.calibration.total, '')"></strong>
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
            shipLeafResults: [],
            shipLeafLoading: false,
            activeTab: 'ships',
            moduleSearchQuery: '',
            moduleSlotFilter: null,
            moduleTree: [],
            moduleExpandedNodes: {},
            moduleLeafResults: [],
            moduleLeafLoading: false,
            moduleResults: [],
            moduleLoading: false,
            selectedShip: null,
            shipStats: null,
            selectedSlot: null,
            fittedModules: { high: [], med: [], low: [], rig: [] },
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

            async onShipNodeClick(node) {
                if (node.hasChildren) {
                    this.toggleNode(this.shipExpandedNodes, node.key);
                    return;
                }

                this.shipLeafLoading = true;
                try {
                    const response = await fetch(`/api/public/fitting-simulator/ships-by-category-path?path=${encodeURIComponent(JSON.stringify(node.path))}`);
                    if (!response.ok) {
                        throw new Error('load ships by path failed');
                    }
                    const data = await response.json();
                    this.shipLeafResults = data.ships || [];
                } catch (error) {
                    this.shipLeafResults = [];
                    this.setNotice('这个舰船分类暂时没有成功加载出来。', 'warn');
                } finally {
                    this.shipLeafLoading = false;
                }
            },

            async loadModuleTree(slotType) {
                try {
                    const query = slotType ? `?slot=${slotType}` : '';
                    const response = await fetch(`/api/public/fitting-simulator/module-category-tree${query}`);
                    if (!response.ok) {
                        throw new Error('load module tree failed');
                    }
                    this.moduleTree = await response.json();
                    this.moduleExpandedNodes = {};
                    this.moduleLeafResults = [];
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

                this.moduleLeafLoading = true;
                try {
                    const query = new URLSearchParams({
                        path: JSON.stringify(node.path),
                    });
                    if (this.moduleSlotFilter) {
                        query.set('slot', this.moduleSlotFilter);
                    }

                    const response = await fetch(`/api/public/fitting-simulator/modules-by-category-path?${query.toString()}`);
                    if (!response.ok) {
                        throw new Error('load modules by path failed');
                    }
                    const data = await response.json();
                    this.moduleLeafResults = data.modules || [];
                } catch (error) {
                    this.moduleLeafResults = [];
                    this.setNotice('这个装备分类暂时没有成功加载出来。', 'warn');
                } finally {
                    this.moduleLeafLoading = false;
                }
            },

            async selectShip(ship) {
                try {
                    const response = await fetch(`/api/public/fitting-simulator/ships/${ship.type_id}`);
                    if (!response.ok) {
                        throw new Error('load ship detail failed');
                    }

                    this.shipStats = await response.json();
                    this.selectedShip = { ...ship, image_url: this.shipStats.image_url || ship.image_url };
                    this.selectedSlot = null;
                    this.fittedModules = {
                        high: Array(this.slotCapacity('high')).fill(null),
                        med: Array(this.slotCapacity('med')).fill(null),
                        low: Array(this.slotCapacity('low')).fill(null),
                        rig: Array(this.slotCapacity('rig')).fill(null),
                    };
                    this.resourceUsage.cpu.total = Number(this.shipStats.resources?.cpu_output || 0);
                    this.resourceUsage.power.total = Number(this.shipStats.resources?.power_output || 0);
                    this.resourceUsage.calibration.total = Number(this.shipStats.resources?.upgrade_capacity || 0);
                    this.updateResourceUsage();
                    this.setNotice('舰船已载入。下一步先点一个槽位，再去左侧点装备。', 'info');
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
                this.moduleLeafResults = [];
                if (this.moduleSearchQuery) {
                    this.searchModules();
                }
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
                if (!this.selectedSlot) {
                    this.setNotice('请先点中间的槽位，再点左侧装备。', 'warn');
                    return;
                }

                let moduleDetails = module;
                if (!moduleDetails.attributes || !moduleDetails.effects) {
                    try {
                        const response = await fetch(`/api/public/fitting-simulator/types/${module.type_id}`);
                        if (!response.ok) {
                            throw new Error('load type failed');
                        }
                        moduleDetails = await response.json();
                    } catch (error) {
                        this.setNotice('装备详情没有取到，所以这次没能安装。', 'warn');
                        return;
                    }
                }

                const slotType = this.selectedSlot.type;
                const realSlot = moduleDetails.slot || module.slot || null;
                if (!realSlot || realSlot !== slotType) {
                    this.setNotice(`这个装备不是${this.slotLabel(slotType)}用的，所以不能装在这里。`, 'warn');
                    return;
                }

                if (this.selectedSlot.index >= this.slotCapacity(slotType)) {
                    this.setNotice('这个槽位超出了舰船可用范围。', 'warn');
                    return;
                }

                if (slotType === 'high') {
                    if (this.isTurretModule(moduleDetails) && this.remainingHardpoints('turret') <= 0) {
                        this.setNotice('炮台硬点已经用完了。', 'warn');
                        return;
                    }
                    if (this.isLauncherModule(moduleDetails) && this.remainingHardpoints('launcher') <= 0) {
                        this.setNotice('发射器硬点已经用完了。', 'warn');
                        return;
                    }
                }

                this.fittedModules[slotType][this.selectedSlot.index] = {
                    ...moduleDetails,
                    slot: slotType,
                };
                this.updateResourceUsage();
                this.setNotice(`已安装到${this.slotLabel(slotType)}第 ${this.selectedSlot.index + 1} 个槽位。`, 'info');
            },

            removeModule(slotType, index) {
                if (!this.fittedModules[slotType]?.[index]) {
                    return;
                }
                this.fittedModules[slotType][index] = null;
                this.updateResourceUsage();
                this.setNotice(`已从${this.slotLabel(slotType)}第 ${index + 1} 个槽位卸下装备。`, 'info');
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
                this.selectedSlot = null;
                this.updateResourceUsage();
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
                        cpu += Number(module.attributes?.[50] || module.cpu || 0);
                        power += Number(module.attributes?.[30] || module.power || 0);
                        calibration += Number(module.attributes?.[1153] || module.upgrade_cost || 0);
                    });
                });

                this.resourceUsage.cpu.used = cpu;
                this.resourceUsage.power.used = power;
                this.resourceUsage.calibration.used = calibration;
            },

            remainingHardpoints(type) {
                const total = Number(this.shipStats?.hardpoints?.[type] || 0);
                let used = 0;
                (this.fittedModules.high || []).forEach((module) => {
                    if (!module) {
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

            isTurretModule(module) {
                return (module.effects || []).some((effect) => [10, 42, 44, 46, 48, 50].includes(Number(effect.effect_id)));
            },

            isLauncherModule(module) {
                return (module.effects || []).some((effect) => [11, 41, 43, 45, 47, 49, 51].includes(Number(effect.effect_id)));
            },

            slotLabel(slotType) {
                return { high: '高槽', med: '中槽', low: '低槽', rig: '改装槽' }[slotType] || slotType;
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
                    parts.push(`CPU ${Number(module.cpu).toFixed(1)}`);
                }
                if (module.power) {
                    parts.push(`电网 ${Number(module.power).toFixed(1)}`);
                }
                if (module.upgrade_cost) {
                    parts.push(`校准 ${Number(module.upgrade_cost).toFixed(0)}`);
                }
                return parts.join(' / ');
            },

            groupedModuleResults() {
                const groups = {};
                (this.moduleResults || []).forEach((module) => {
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
