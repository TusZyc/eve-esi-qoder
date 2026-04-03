@extends('layouts.app')

@section('title', '创建舰队行动')
@section('page-title', '创建舰队行动')

@section('content')
<div class="p-6 max-w-4xl mx-auto">
    <!-- 页面标题 -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white">创建舰队行动</h1>
            <p class="text-gray-400 mt-1">创建一个新的舰队行动并开始记录出勤数据</p>
        </div>
        <a href="{{ route('fleet.index') }}" class="text-gray-400 hover:text-white transition-colors">
            返回列表
        </a>
    </div>

    <!-- 舰队状态检查 -->
    @if(!$fleetStatus)
    <div class="bg-yellow-500/20 border border-yellow-500/50 rounded-lg p-4 mb-6">
        <div class="flex items-center gap-3">
            <svg class="w-6 h-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <div>
                <h3 class="text-yellow-400 font-medium">未检测到舰队</h3>
                <p class="text-yellow-300/80 text-sm mt-1">请先在游戏中创建舰队并加入，然后刷新此页面</p>
            </div>
        </div>
    </div>
    @else
    <div class="bg-green-500/20 border border-green-500/50 rounded-lg p-4 mb-6">
        <div class="flex items-center gap-3">
            <svg class="w-6 h-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div>
                <h3 class="text-green-400 font-medium">已检测到舰队</h3>
                <p class="text-green-300/80 text-sm mt-1">舰队 ID: {{ $fleetStatus['fleet_id'] ?? 'N/A' }} | 角色: {{ $fleetStatus['role'] ?? '成员' }}</p>
            </div>
        </div>
    </div>
    @endif

    <!-- 创建表单 -->
    <form id="createForm" class="space-y-6">
        @csrf
        
        <!-- 基础信息 -->
        <div class="bg-gray-800/50 rounded-lg p-6 backdrop-blur-sm border border-gray-700/50">
            <h2 class="text-lg font-semibold text-white mb-4">基础信息</h2>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-300 mb-2">行动名称 <span class="text-red-400">*</span></label>
                    <input type="text" name="operation_name" required
                        class="w-full bg-gray-700/50 border border-gray-600 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-blue-500"
                        placeholder="例如: 3月21日 C5收复战">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-300 mb-2">集结类型 <span class="text-red-400">*</span></label>
                        <select name="assembly_type" id="assembly_type" required
                            class="w-full bg-gray-700/50 border border-gray-600 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                            <option value="">请选择...</option>
                            <option value="00收割">00收割</option>
                            <option value="00反收割">00反收割</option>
                            <option value="低安收割">低安收割</option>
                            <option value="低安反收割">低安反收割</option>
                            <option value="小队散打">小队散打</option>
                            <option value="特殊集结">特殊集结</option>
                            <option value="其他">其他</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-300 mb-2">舰队指挥</label>
                        <input type="text" name="fleet_commander" id="fleet_commander"
                            class="w-full bg-gray-700/50 border border-gray-600 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-blue-500"
                            placeholder="默认为当前授权角色"
                            value="{{ $user->name ?? '' }}">
                    </div>
                </div>
                
                <!-- 其他类型输入框 -->
                <div id="assembly_type_other_container" class="hidden">
                    <label class="block text-gray-300 mb-2">其他类型说明 <span class="text-red-400">*</span></label>
                    <input type="text" name="assembly_type_other"
                        class="w-full bg-gray-700/50 border border-gray-600 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-blue-500"
                        placeholder="请输入集结类型">
                </div>
                
                <div>
                    <label class="block text-gray-300 mb-2">建制</label>
                    <div class="flex gap-2 mb-2">
                        <select id="doctrine_select" class="flex-1 bg-gray-700/50 border border-gray-600 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                            <option value="">选择舰船或类别...</option>
                            <optgroup label="舰船类别">
                                <option value="category:frigate">护卫舰</option>
                                <option value="category:destroyer">驱逐舰</option>
                                <option value="category:cruiser">巡洋舰</option>
                                <option value="category:battlecruiser">战列巡洋舰</option>
                                <option value="category:battleship">战列舰</option>
                                <option value="category:strategic_cruiser">战略巡洋舰</option>
                                <option value="category:carrier">航母</option>
                                <option value="category:dreadnought">无畏舰</option>
                                <option value="category:force_auxiliary"> Force Auxiliary</option>
                                <option value="category:supercarrier">超级航母</option>
                                <option value="category:titan">泰坦</option>
                                <option value="category:logistics">后勤舰</option>
                                <option value="category:interdictor">拦截舰</option>
                                <option value="category:heavy_interdictor">重型拦截舰</option>
                                <option value="category:recon">侦察舰</option>
                            </optgroup>
                            <optgroup label="常用舰船">
                                <option value="ship:伊泽库尔">伊泽库尔</option>
                                <option value="ship:企业级">企业级</option>
                                <option value="ship:猛鲑">猛鲑</option>
                                <option value="ship:狂暴">狂暴</option>
                                <option value="ship:台风">台风</option>
                                <option value="ship:多米尼克斯">多米尼克斯</option>
                                <option value="ship:斯特修斯">斯特修斯</option>
                                <option value="ship:冥想级">冥想级</option>
                                <option value="ship:奇奇莫拉">奇奇莫拉</option>
                                <option value="ship:勒沙克">勒沙克</option>
                                <option value="ship:巴戈龙">巴戈龙</option>
                                <option value="ship:泛安级">泛安级</option>
                                <option value="ship:守护者">守护者</option>
                                <option value="ship:奥尼伯索斯">奥尼伯索斯</option>
                                <option value="ship:光明使者">光明使者</option>
                                <option value="ship:神示级">神示级</option>
                                <option value="ship:凤凰级">凤凰级</option>
                                <option value="ship:莫洛斯">莫洛斯</option>
                                <option value="ship:纳加多尔">纳加多尔</option>
                                <option value="ship:厄里斯">厄里斯</option>
                                <option value="ship:阔刀">阔刀</option>
                                <option value="ship:弗莱克">弗莱克</option>
                            </optgroup>
                        </select>
                        <button type="button" id="add_doctrine" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                            添加
                        </button>
                    </div>
                    <div id="doctrine_tags" class="flex flex-wrap gap-2">
                        <!-- 选中的建制标签会显示在这里 -->
                    </div>
                    <input type="hidden" name="doctrine" id="doctrine_input" value="">
                </div>
                
                <div>
                    <label class="block text-gray-300 mb-2">集结地点</label>
                    <input type="text" name="assembly_location"
                        class="w-full bg-gray-700/50 border border-gray-600 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-blue-500"
                        placeholder="输入星系名称，例如: Jita、Amarr、1DQ1-A">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-300 mb-2">抓取模式</label>
                        <select name="auto_snapshot" class="w-full bg-gray-700/50 border border-gray-600 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                            <option value="1">自动定时抓取</option>
                            <option value="0">仅手动抓取</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-300 mb-2">抓取间隔（秒）</label>
                        <input type="number" name="snapshot_interval" value="60" min="30" max="300"
                            class="w-full bg-gray-700/50 border border-gray-600 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                    </div>
                </div>
                
                <div>
                    <label class="block text-gray-300 mb-2">备注</label>
                    <textarea name="notes" rows="2"
                        class="w-full bg-gray-700/50 border border-gray-600 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-blue-500"
                        placeholder="可选的行动说明"></textarea>
                </div>
            </div>
        </div>

        <!-- 考核标准配置 -->
        <div class="bg-gray-800/50 rounded-lg p-6 backdrop-blur-sm border border-gray-700/50">
            <h2 class="text-lg font-semibold text-white mb-4">考核标准配置</h2>
            
            <div class="space-y-4">
                <!-- 在队时长 -->
                <div class="flex items-center gap-4 p-3 bg-gray-700/30 rounded-lg">
                    <input type="checkbox" name="config[duration_enabled]" checked class="w-5 h-5 rounded bg-gray-700 border-gray-600 text-blue-500 focus:ring-blue-500">
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <span class="text-white">在队时长考核</span>
                            <div class="flex items-center gap-2">
                                <input type="number" name="config[duration_weight]" value="20" step="0.01" min="0" max="100"
                                    class="w-16 bg-gray-700 border border-gray-600 rounded px-2 py-1 text-white text-sm">
                                <span class="text-gray-400 text-sm">%</span>
                            </div>
                        </div>
                        <p class="text-gray-400 text-sm mt-1">最低在线时长: <input type="number" name="config[duration_min_percent]" value="60" class="w-12 bg-gray-700 border border-gray-600 rounded px-1 text-white text-sm"> %</p>
                    </div>
                </div>

                <!-- 加入时间 -->
                <div class="flex items-center gap-4 p-3 bg-gray-700/30 rounded-lg">
                    <input type="checkbox" name="config[join_time_enabled]" checked class="w-5 h-5 rounded bg-gray-700 border-gray-600 text-blue-500 focus:ring-blue-500">
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <span class="text-white">加入时间考核</span>
                            <div class="flex items-center gap-2">
                                <input type="number" name="config[join_time_weight]" value="15" step="0.01" min="0" max="100"
                                    class="w-16 bg-gray-700 border border-gray-600 rounded px-2 py-1 text-white text-sm">
                                <span class="text-gray-400 text-sm">%</span>
                            </div>
                        </div>
                        <p class="text-gray-400 text-sm mt-1">迟到宽限: <input type="number" name="config[join_time_grace_minutes]" value="15" class="w-12 bg-gray-700 border border-gray-600 rounded px-1 text-white text-sm"> 分钟</p>
                    </div>
                </div>

                <!-- 舰船类型 -->
                <div class="flex items-center gap-4 p-3 bg-gray-700/30 rounded-lg">
                    <input type="checkbox" name="config[ship_type_enabled]" checked class="w-5 h-5 rounded bg-gray-700 border-gray-600 text-blue-500 focus:ring-blue-500">
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <span class="text-white">舰船类型考核</span>
                            <div class="flex items-center gap-2">
                                <input type="number" name="config[ship_type_weight]" value="20" step="0.01" min="0" max="100"
                                    class="w-16 bg-gray-700 border border-gray-600 rounded px-2 py-1 text-white text-sm">
                                <span class="text-gray-400 text-sm">%</span>
                            </div>
                        </div>
                        <p class="text-gray-400 text-sm mt-1">不匹配扣: <input type="number" name="config[ship_type_penalty_percent]" value="50" class="w-12 bg-gray-700 border border-gray-600 rounded px-1 text-white text-sm"> % （期望船型可在行动结束后配置）</p>
                    </div>
                </div>

                <!-- 到访星系 -->
                <div class="flex items-center gap-4 p-3 bg-gray-700/30 rounded-lg">
                    <input type="checkbox" name="config[systems_enabled]" checked class="w-5 h-5 rounded bg-gray-700 border-gray-600 text-blue-500 focus:ring-blue-500">
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <span class="text-white">到访星系考核</span>
                            <div class="flex items-center gap-2">
                                <input type="number" name="config[systems_weight]" value="20" step="0.01" min="0" max="100"
                                    class="w-16 bg-gray-700 border border-gray-600 rounded px-2 py-1 text-white text-sm">
                                <span class="text-gray-400 text-sm">%</span>
                            </div>
                        </div>
                        <p class="text-gray-400 text-sm mt-1">最低重叠: <input type="number" name="config[systems_overlap_min_percent]" value="70" class="w-12 bg-gray-700 border border-gray-600 rounded px-1 text-white text-sm"> %</p>
                    </div>
                </div>

                <!-- 离队距离 -->
                <div class="flex items-center gap-4 p-3 bg-gray-700/30 rounded-lg">
                    <input type="checkbox" name="config[distance_enabled]" checked class="w-5 h-5 rounded bg-gray-700 border-gray-600 text-blue-500 focus:ring-blue-500">
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <span class="text-white">离队距离考核</span>
                            <div class="flex items-center gap-2">
                                <input type="number" name="config[distance_weight]" value="15" step="0.01" min="0" max="100"
                                    class="w-16 bg-gray-700 border border-gray-600 rounded px-2 py-1 text-white text-sm">
                                <span class="text-gray-400 text-sm">%</span>
                            </div>
                        </div>
                        <p class="text-gray-400 text-sm mt-1">合格距离: <input type="number" name="config[distance_max_jumps]" value="2" class="w-12 bg-gray-700 border border-gray-600 rounded px-1 text-white text-sm"> 跳跃以内</p>
                    </div>
                </div>

                <!-- 在站次数 -->
                <div class="flex items-center gap-4 p-3 bg-gray-700/30 rounded-lg">
                    <input type="checkbox" name="config[in_station_enabled]" checked class="w-5 h-5 rounded bg-gray-700 border-gray-600 text-blue-500 focus:ring-blue-500">
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <span class="text-white">在站次数考核</span>
                            <div class="flex items-center gap-2">
                                <input type="number" name="config[in_station_weight]" value="10" step="0.01" min="0" max="100"
                                    class="w-16 bg-gray-700 border border-gray-600 rounded px-2 py-1 text-white text-sm">
                                <span class="text-gray-400 text-sm">%</span>
                            </div>
                        </div>
                        <p class="text-gray-400 text-sm mt-1">容忍上限: <input type="number" name="config[in_station_max_percent]" value="20" class="w-12 bg-gray-700 border border-gray-600 rounded px-1 text-white text-sm"> %</p>
                    </div>
                </div>
            </div>

            <!-- 权重总计 -->
            <div class="mt-4 pt-4 border-t border-gray-600">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-400">权重总计：</span>
                    <span id="totalWeight" class="text-white font-medium">100%</span>
                </div>
            </div>
        </div>

        <!-- 提交按钮 -->
        <div class="flex items-center justify-end gap-4">
            <a href="{{ route('fleet.index') }}" class="px-4 py-2 text-gray-400 hover:text-white transition-colors">
                取消
            </a>
            <button type="submit" {{ !$fleetStatus ? 'disabled' : '' }}
                class="px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white rounded-lg transition-colors">
                创建并开始行动
            </button>
        </div>
    </form>
</div>

<script>
// 集结类型选择 - 显示/隐藏"其他"输入框
document.getElementById('assembly_type').addEventListener('change', function() {
    const otherContainer = document.getElementById('assembly_type_other_container');
    const otherInput = otherContainer.querySelector('input');
    if (this.value === '其他') {
        otherContainer.classList.remove('hidden');
        otherInput.required = true;
    } else {
        otherContainer.classList.add('hidden');
        otherInput.required = false;
        otherInput.value = '';
    }
});

// 建制选择
let selectedDoctrine = [];

document.getElementById('add_doctrine').addEventListener('click', function() {
    const select = document.getElementById('doctrine_select');
    const value = select.value;
    if (!value) return;
    
    // 检查是否已添加
    if (selectedDoctrine.includes(value)) {
        alert('该建制已添加');
        return;
    }
    
    selectedDoctrine.push(value);
    updateDoctrineTags();
    select.value = '';
});

function updateDoctrineTags() {
    const container = document.getElementById('doctrine_tags');
    const input = document.getElementById('doctrine_input');
    
    container.innerHTML = selectedDoctrine.map((item, index) => {
        const [type, name] = item.split(':');
        const label = type === 'category' ? `[类别] ${name}` : name;
        return `<span class="inline-flex items-center gap-1 px-3 py-1 bg-blue-500/20 text-blue-300 rounded-full text-sm">
            ${label}
            <button type="button" onclick="removeDoctrine(${index})" class="hover:text-white">&times;</button>
        </span>`;
    }).join('');
    
    input.value = JSON.stringify(selectedDoctrine);
}

function removeDoctrine(index) {
    selectedDoctrine.splice(index, 1);
    updateDoctrineTags();
}

// 计算权重总和
function updateTotalWeight() {
    const weights = document.querySelectorAll('input[name^="config"][name$="weight"]');
    let total = 0;
    weights.forEach(input => {
        const checkbox = input.closest('.flex').querySelector('input[type="checkbox"]');
        if (checkbox && checkbox.checked) {
            total += parseFloat(input.value) || 0;
        }
    });
    document.getElementById('totalWeight').textContent = total.toFixed(2) + '%';
    document.getElementById('totalWeight').classList.toggle('text-red-400', Math.abs(total - 100) > 0.01);
}

// 监听变化
document.querySelectorAll('input[name^="config"][name$="weight"], input[type="checkbox"]').forEach(el => {
    el.addEventListener('change', updateTotalWeight);
    el.addEventListener('input', updateTotalWeight);
});

// 表单提交
document.getElementById('createForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    // 处理复选框
    document.querySelectorAll('input[type="checkbox"]').forEach(cb => {
        data[cb.name] = cb.checked ? 1 : 0;
    });
    
    // 验证集结类型
    if (!data.assembly_type) {
        alert('请选择集结类型');
        return;
    }
    
    if (data.assembly_type === '其他' && !data.assembly_type_other) {
        alert('请填写其他类型说明');
        return;
    }
    
    try {
        const response = await fetch('{{ route("fleet.store") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            window.location.href = result.data.redirect;
        } else {
            alert('创建失败: ' + result.error);
        }
    } catch (error) {
        alert('创建失败: ' + error.message);
    }
});
</script>
@endsection