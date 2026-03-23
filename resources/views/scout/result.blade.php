@extends('layouts.app')

@section('title', '斥候工具 - 扫描结果')
@section('page-title', '🔭 扫描结果')

@section('content')
<style>
    /* 斥候工具专用样式 - 避免stat-card的白光线问题 */
    .scout-panel {
        background: rgba(30, 41, 59, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 16px;
    }
    .scout-panel:hover {
        border-color: rgba(255, 255, 255, 0.12);
    }
</style>
<div class="max-w-7xl mx-auto">
    <!-- 顶部信息栏 -->
    <div class="scout-panel rounded-2xl p-6 mb-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center shadow-lg">
                    <span class="text-2xl">🔭</span>
                </div>
                <div>
                    <h1 class="text-2xl font-bold bg-gradient-to-r from-white to-slate-300 bg-clip-text text-transparent">扫描结果</h1>
                    <p class="text-slate-400 text-sm">
                        扫描时间: {{ $result->getCreatedAtFormatted() }} · 
                        保存至: {{ $result->getExpiresAtFormatted() }}
                    </p>
                </div>
            </div>
            
            <!-- 分享链接 -->
            <div class="flex items-center gap-2">
                <div class="bg-slate-800/50 rounded-xl px-4 py-2 flex items-center gap-2">
                    <span class="text-slate-400 text-sm">分享链接:</span>
                    <code class="text-blue-400 text-sm">{{ $result->getShareUrl() }}</code>
                </div>
                <button
                    onclick="copyShareUrl(event)"
                    class="px-4 py-2 bg-blue-500/20 hover:bg-blue-500/30 border border-blue-500/30 rounded-xl text-blue-400 text-sm transition-all flex items-center gap-2"
                >
                    <span>📋</span>
                    <span>复制链接</span>
                </button>
            </div>
        </div>
    </div>

    <!-- 三列布局 -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- 左列：所有物件 -->
        <div class="scout-panel rounded-2xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-slate-200 flex items-center gap-2">
                    <span>📋</span>
                    <span>所有物件</span>
                </h2>
                <span class="text-sm text-slate-400">
                    共 {{ $statistics['total_objects'] ?? 0 }} 个物体
                </span>
            </div>

            <div class="overflow-x-auto max-h-[600px] overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-slate-800/90 backdrop-blur-sm">
                        <tr class="text-slate-400 border-b border-slate-700/50">
                            <th class="text-left py-3 px-4 font-medium">物体名称</th>
                            <th class="text-right py-3 px-4 font-medium">数量</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                        $sortedItems = collect($items)->sortByDesc('quantity')->values();
                        @endphp
                        @foreach ($sortedItems as $item)
                        <tr class="border-b border-slate-700/30 hover:bg-slate-700/20 transition-colors">
                            <td class="py-3 px-4">
                                <span class="text-white">{{ $item['chinese_name'] ?? $item['raw_name'] ?? '未知' }}</span>
                                @if(($item['resolved'] ?? true) === false)
                                <span class="ml-2 text-xs text-yellow-500">(未识别)</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">
                                <span class="text-blue-400 font-medium">{{ number_format($item['quantity'] ?? 0) }}</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 中列：分类统计 -->
        <div class="scout-panel rounded-2xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-slate-200 flex items-center gap-2">
                    <span>📊</span>
                    <span>分类统计</span>
                </h2>
            </div>

            <div class="space-y-4 max-h-[600px] overflow-y-auto pr-2">
                @php
                $categories = $statistics['categories'] ?? [];
                $categoryOrder = ['舰船', '太空舱', '建筑', '货柜', '无人机', '天体', '其他'];
                $categoryIcons = [
                    '舰船' => '🚀',
                    '太空舱' => '🥚',
                    '建筑' => '🏢',
                    '货柜' => '📦',
                    '无人机' => '🤖',
                    '天体' => '🌍',
                    '其他' => '❓',
                ];
                @endphp

                @foreach ($categoryOrder as $category)
                @if (isset($categories[$category]))
                @php $cat = $categories[$category]; @endphp
                <div class="bg-slate-800/30 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-medium text-white flex items-center gap-2">
                            <span>{{ $categoryIcons[$category] ?? '📌' }}</span>
                            <span>{{ $category }}</span>
                        </h3>
                        <span class="text-blue-400 font-medium">{{ $cat['count'] ?? 0 }}</span>
                    </div>

                    <!-- 舰船显示子分类 -->
                    @if ($category === '舰船' && !empty($cat['subcategories']))
                    <div class="space-y-2 pl-4">
                        @foreach ($cat['subcategories'] as $subName => $subData)
                        <div class="bg-slate-700/20 rounded-lg p-3">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-slate-300 text-sm">{{ $subName }}</span>
                                <span class="text-slate-400 text-sm">{{ $subData['count'] ?? 0 }}</span>
                            </div>
                            @if (!empty($subData['items']))
                            <div class="pl-3 border-l-2 border-slate-600 space-y-1">
                                @foreach ($subData['items'] as $ship)
                                <div class="flex justify-between text-xs">
                                    <span class="text-slate-400">{{ $ship['name'] ?? '未知' }}</span>
                                    <span class="text-slate-500">{{ $ship['quantity'] ?? 0 }}</span>
                                </div>
                                @endforeach
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    @elseif (!empty($cat['items']))
                    <!-- 其他分类直接显示物品列表 -->
                    <div class="space-y-1 pl-4">
                        @php
                        $sortedCatItems = collect($cat['items'])->sortByDesc('quantity')->values();
                        @endphp
                        @foreach ($sortedCatItems->take(10) as $catItem)
                        <div class="flex justify-between text-sm py-1">
                            <span class="text-slate-400">{{ $catItem['name'] ?? '未知' }}</span>
                            <span class="text-slate-500">{{ $catItem['quantity'] ?? 0 }}</span>
                        </div>
                        @endforeach
                        @if (count($sortedCatItems) > 10)
                        <div class="text-xs text-slate-500 pt-1">
                            还有 {{ count($sortedCatItems) - 10 }} 项...
                        </div>
                        @endif
                    </div>
                    @endif
                </div>
                @endif
                @endforeach

                <!-- 处理不在预定义顺序中的分类 -->
                @foreach ($categories as $catName => $cat)
                @if (!in_array($catName, $categoryOrder))
                <div class="bg-slate-800/30 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-medium text-white flex items-center gap-2">
                            <span>{{ $categoryIcons['其他'] }}</span>
                            <span>{{ $catName }}</span>
                        </h3>
                        <span class="text-blue-400 font-medium">{{ $cat['count'] ?? 0 }}</span>
                    </div>
                    @if (!empty($cat['items']))
                    <div class="space-y-1 pl-4">
                        @foreach ($cat['items'] as $catItem)
                        <div class="flex justify-between text-sm py-1">
                            <span class="text-slate-400">{{ $catItem['name'] ?? '未知' }}</span>
                            <span class="text-slate-500">{{ $catItem['quantity'] ?? 0 }}</span>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
                @endif
                @endforeach
            </div>
        </div>

        <!-- 右列：舰船快速复制 -->
        <div class="scout-panel rounded-2xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-slate-200 flex items-center gap-2">
                    <span>🚀</span>
                    <span>舰船列表</span>
                </h2>
                <button
                    onclick="copyShipList(event)"
                    class="px-3 py-1.5 bg-green-500/20 hover:bg-green-500/30 border border-green-500/30 rounded-lg text-green-400 text-sm transition-all flex items-center gap-1"
                >
                    <span>📋</span>
                    <span>复制</span>
                </button>
            </div>

            @php
            // 提取所有舰船数据
            $shipList = [];
            $shipCategories = $statistics['categories']['舰船'] ?? null;
            if ($shipCategories && !empty($shipCategories['subcategories'])) {
                foreach ($shipCategories['subcategories'] as $subName => $subData) {
                    if (!empty($subData['items'])) {
                        foreach ($subData['items'] as $ship) {
                            $shipList[] = [
                                'name' => $ship['name'] ?? '未知',
                                'quantity' => $ship['quantity'] ?? 0
                            ];
                        }
                    }
                }
            }
            // 按数量降序排序
            usort($shipList, function($a, $b) {
                return $b['quantity'] - $a['quantity'];
            });
            @endphp

            <div class="max-h-[600px] overflow-y-auto pr-2">
                @if (empty($shipList))
                <div class="text-center text-slate-500 py-8">
                    <span class="text-4xl">🚫</span>
                    <p class="mt-2">未检测到舰船</p>
                </div>
                @else
                <div id="ship-list-content" class="space-y-1 text-sm">
                    @foreach ($shipList as $ship)
                    <div class="flex justify-between py-1 px-2 rounded hover:bg-slate-700/30 transition-colors">
                        <span class="text-slate-300">{{ $ship['name'] }}</span>
                        <span class="text-cyan-400 font-medium">✖{{ $ship['quantity'] }}</span>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            <!-- 隐藏的复制内容 -->
            @if (!empty($shipList))
            <textarea id="ship-list-clipboard" class="hidden" readonly>{{ implode("\n", array_map(function($s) { return $s['name'] . '✖' . $s['quantity']; }, $shipList)) }}</textarea>
            @endif
        </div>
    </div>

    <!-- 底部操作栏 -->
    <div class="mt-6 flex items-center justify-center gap-4">
        <a 
            href="{{ route('scout.index') }}" 
            class="px-6 py-3 bg-slate-700/50 hover:bg-slate-700/70 border border-slate-600/50 rounded-xl text-slate-300 transition-all flex items-center gap-2"
        >
            <span>🏠</span>
            <span>返回首页</span>
        </a>
        <a 
            href="{{ route('scout.index') }}" 
            class="btn-primary px-6 py-3 rounded-xl text-white font-medium flex items-center gap-2"
        >
            <span>🔍</span>
            <span>新建扫描</span>
        </a>
    </div>
</div>

@push('scripts')
<script>
function copyShareUrl(e) {
    const url = '{{ $result->getShareUrl() }}';
    const btn = e.target.closest('button');
    const originalText = btn.innerHTML;
    
    navigator.clipboard.writeText(url).then(() => {
        btn.innerHTML = '<span>✅</span><span>已复制</span>';
        btn.classList.add('bg-green-500/20', 'border-green-500/30', 'text-green-400');
        btn.classList.remove('bg-blue-500/20', 'border-blue-500/30', 'text-blue-400');

        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.classList.remove('bg-green-500/20', 'border-green-500/30', 'text-green-400');
            btn.classList.add('bg-blue-500/20', 'border-blue-500/30', 'text-blue-400');
        }, 2000);
    }).catch(err => {
        alert('复制失败，请手动复制: ' + url);
    });
}

function copyShipList(e) {
    const textarea = document.getElementById('ship-list-clipboard');
    if (!textarea) {
        alert('没有舰船数据可复制');
        return;
    }

    const text = textarea.value;
    const btn = e.target.closest('button');
    const originalText = btn.innerHTML;

    navigator.clipboard.writeText(text).then(() => {
        btn.innerHTML = '<span>✅</span><span>已复制</span>';
        btn.classList.remove('bg-green-500/20', 'border-green-500/30', 'text-green-400');
        btn.classList.add('bg-emerald-500/20', 'border-emerald-500/30', 'text-emerald-400');

        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.classList.add('bg-green-500/20', 'border-green-500/30', 'text-green-400');
            btn.classList.remove('bg-emerald-500/20', 'border-emerald-500/30', 'text-emerald-400');
        }, 2000);
    }).catch(err => {
        // 备用方案：选中文本
        textarea.classList.remove('hidden');
        textarea.select();
        document.execCommand('copy');
        textarea.classList.add('hidden');
        btn.innerHTML = '<span>✅</span><span>已复制</span>';
        setTimeout(() => {
            btn.innerHTML = originalText;
        }, 2000);
    });
}
</script>
@endpush
@endsection