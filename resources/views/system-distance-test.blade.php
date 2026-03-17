<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>星系距离计算测试</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3b82f6',
                        dark: '#1e293b',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-900 text-white min-h-screen p-6">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-6 text-center text-primary">星系距离计算测试</h1>
        
        <div class="space-y-4">
            <!-- 路径距离计算 -->
            <div class="bg-dark p-6 rounded-lg shadow-lg">
                <h2 class="text-xl font-semibold mb-4">计算最短跳跃路径</h2>
                <div class="flex gap-4 mb-4">
                    <div class="flex-1">
                        <label class="block text-sm mb-1">起始星系 ID</label>
                        <input type="number" id="fromSystemId" value="30000142" class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white focus:outline-none focus:border-primary">
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm mb-1">目标星系 ID</label>
                        <input type="number" id="toSystemId" value="30002187" class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white focus:outline-none focus:border-primary">
                    </div>
                </div>
                <button onclick="calculatePath()" class="bg-primary hover:bg-blue-600 px-4 py-2 rounded transition w-full">
                    计算路径
                </button>
                <div id="pathResult" class="mt-4 hidden">
                    <div class="bg-green-900/30 border border-green-700 rounded p-4">
                        <h3 class="font-semibold text-green-400 mb-2">✓ 路径计算成功!</h3>
                        <div id="pathContent" class="space-y-2"></div>
                    </div>
                </div>
            </div>

            <!-- 欧几里得距离计算 -->
            <div class="bg-dark p-6 rounded-lg shadow-lg">
                <h2 class="text-xl font-semibold mb-4">计算欧几里得距离(直线距离)</h2>
                <div class="flex gap-4 mb-4">
                    <div class="flex-1">
                        <label class="block text-sm mb-1">星系 ID</label>
                        <input type="number" id="euclideanSystemId1" value="30000142" class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white focus:outline-none focus:border-primary">
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm mb-1">星系 ID</label>
                        <input type="number" id="euclideanSystemId2" value="30002187" class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white focus:outline-none focus:border-primary">
                    </div>
                </div>
                <button onclick="calculateEuclidean()" class="bg-primary hover:bg-blue-600 px-4 py-2 rounded transition w-full">
                    计算距离
                </button>
                <div id="euclideanResult" class="mt-4hidden">
                    <div class="bg-blue-900/30 border border-blue-700 rounded p-4">
                        <h3 class="font-semibold text-blue-400 mb-2">✓ 欧几里得距离</h3>
                        <div id="euclideanContent" class="space-y-2"></div>
                    </div>
                </div>
            </div>

            <!-- 星系信息查询 -->
            <div class="bg-dark p-6 rounded-lg shadow-lg">
                <h2 class="text-xl font-semibold mb-4">查询星系信息</h2>
                <div class="flex gap-4 mb-4">
                    <div class="flex-1">
                        <label class="block text-sm mb-1">星系 ID</label>
                        <input type="number" id="systemInfoId" value="30000142" class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white focus:outline-none focus:border-primary">
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm mb-1">语言</label>
                        <select id="systemInfoLanguage" class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white focus:outline-none focus:border-primary">
                            <option value="zh">中文</option>
                            <option value="en">English</option>
                        </select>
                    </div>
                </div>
                <button onclick="getSystemInfo()" class="bg-primary hover:bg-blue-600 px-4 py-2 rounded transition w-full">
                    查询信息
                </button>
                <div id="systemInfoResult" class="mt-4 hidden">
                    <div class="bg-purple-900/30 border border-purple-700 rounded p-4">
                        <h3 class="font-semibold text-purple-400 mb-2">✓ 星系信息</h3>
                        <div id="systemInfoContent" class="space-y-2"></div>
                    </div>
                </div>
            </div>

            <!-- 批量查询 -->
            <div class="bg-dark p-6 rounded-lg shadow-lg">
                <h2 class="text-xl font-semibold mb-4">批量查询星系</h2>
                <div class="mb-4">
                    <label class="block text-sm mb-1">星系 ID 列表(逗号分隔)</label>
                    <input type="text" id="batchIds" value="30000142,30002187,30000143" class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white focus:outline-none focus:border-primary">
                </div>
                <button onclick="batchQuery()" class="bg-primary hover:bg-blue-600 px-4 py-2 rounded transition w-full">
                    批量查询
                </button>
                <div id="batchResult" class="mt-4 hidden">
                    <div class="bg-teal-900/30 border border-teal-700 rounded p-4">
                        <h3 class="font-semibold text-teal-400 mb-2">✓ 批量查询结果</h3>
                        <div id="batchContent" class="space-y-2 max-h-60 overflow-y-auto"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = '/api/system-distance';

        // 加载默认星系名称
        window.onload = async function() {
            await getSystemNameOnLoad(30000142, 'fromSystemId');
            await getSystemNameOnLoad(30002187, 'toSystemId');
            await getSystemNameOnLoad(30000142, 'euclideanSystemId1');
            await getSystemNameOnLoad(30002187, 'euclideanSystemId2');
            await getSystemNameOnLoad(30000142, 'systemInfoId');
        };

        async function getSystemNameOnLoad(systemId, inputId) {
            try {
                const response = await fetch(`${API_BASE}/name?id=${systemId}&language=zh`);
                const data = await response.json();
                if (data.success) {
                    document.getElementById(inputId).placeholder = `${systemId} - ${data.name}`;
                }
            } catch (e) {
                console.error('Failed to load system name:', e);
            }
        }

        async function calculatePath() {
            const from = document.getElementById('fromSystemId').value;
            const to = document.getElementById('toSystemId').value;

            if (!from || !to) {
                alert('请输入星系 ID');
                return;
            }

            try {
                const response = await fetch(`${API_BASE}/path?from=${from}&to=${to}`);
                const data = await response.json();

                const resultDiv = document.getElementById('pathResult');
                const contentDiv = document.getElementById('pathContent');

                if (data.error) {
                    resultDiv.classList.remove('hidden');
                    contentDiv.innerHTML = `<p class="text-red-400">错误: ${data.message}</p>`;
                    return;
                }

                resultDiv.classList.remove('hidden');
                contentDiv.innerHTML = `
                    <p><strong>跳跃次数:</strong> ${data.jump_count}</p>
                    <p><strong>欧几里得距离:</strong> ${data.path_details.total_euclidean_distance.toFixed(4)} 光年</p>
                    <p class="text-sm text-gray-400 mt-2">
                        路径: ${data.path.map(id => `<span class="bg-gray-700 px-2 py-1 rounded">${id}</span>`).join(' → ')}
                    </p>
                    <details class="mt-2">
                        <summary class="cursor-pointer text-primary hover:text-blue-300">查看详情</summary>
                        <ul class="mt-2 space-y-1">
                            ${data.path_details.systems.map((sys, index) => 
                                `<li>${index}. <span class="text-green-400">${sys.name}</span> (ID: ${sys.system_id})</li>`
                            ).join('')}
                        </ul>
                    </details>
                `;
            } catch (e) {
                alert('请求失败: ' + e.message);
            }
        }

        async function calculateEuclidean() {
            const id1 = document.getElementById('euclideanSystemId1').value;
            const id2 = document.getElementById('euclideanSystemId2').value;

            if (!id1 || !id2) {
                alert('请输入星系 ID');
                return;
            }

            try {
                const response = await fetch(`${API_BASE}/euclidean?from=${id1}&to=${id2}`);
                const data = await response.json();

                const resultDiv = document.getElementById('euclideanResult');
                const contentDiv = document.getElementById('euclideanContent');

                if (data.error) {
                    resultDiv.classList.remove('hidden');
                    contentDiv.innerHTML = `<p class="text-red-400">错误: ${data.message}</p>`;
                    return;
                }

                resultDiv.classList.remove('hidden');
                contentDiv.innerHTML = `
                    <p><strong>ID ${id1} → ID ${id2}</strong></p>
                    <p>距离: ${data.distance.meters.toLocaleString()} 米</p>
                    <p>光年: ${data.distance.light_years.toLocaleString(undefined, {maximumFractionDigits: 6})} 光年</p>
                `;
            } catch (e) {
                alert('请求失败: ' + e.message);
            }
        }

        async function getSystemInfo() {
            const id = document.getElementById('systemInfoId').value;
            const language = document.getElementById('systemInfoLanguage').value;

            if (!id) {
                alert('请输入星系 ID');
                return;
            }

            try {
                const response = await fetch(`${API_BASE}/name?id=${id}&language=${language}`);
                const data = await response.json();

                const resultDiv = document.getElementById('systemInfoResult');
                const contentDiv = document.getElementById('systemInfoContent');

                if (data.error) {
                    resultDiv.classList.remove('hidden');
                    contentDiv.innerHTML = `<p class="text-red-400">错误: ${data.message}</p>`;
                    return;
                }

                resultDiv.classList.remove('hidden');
                contentDiv.innerHTML = `
                    <p><strong>ID:</strong> ${data.system_id}</p>
                    <p><strong>名称 (${language}):</strong> ${data.name}</p>
                `;
            } catch (e) {
                alert('请求失败: ' + e.message);
            }
        }

        async function batchQuery() {
            const idsStr = document.getElementById('batchIds').value;
            const ids = idsStr.split(',').map(id => id.trim()).filter(id => id);

            if (ids.length === 0) {
                alert('请输入至少一个星系 ID');
                return;
            }

            try {
                const response = await fetch(`${API_BASE}/batch?ids=${ids.join(',')}`);
                const data = await response.json();

                const resultDiv = document.getElementById('batchResult');
                const contentDiv = document.getElementById('batchContent');

                if (data.error) {
                    resultDiv.classList.remove('hidden');
                    contentDiv.innerHTML = `<p class="text-red-400">错误: ${data.message}</p>`;
                    return;
                }

                resultDiv.classList.remove('hidden');
                contentDiv.innerHTML = `
                    <p class="mb-2 text-gray-400">查询了 ${data.count} 个星系:</p>
                    <div class="grid grid-cols-1 gap-2">
                        ${data.systems.map(sys => `
                            <div class="bg-gray-800 rounded p-2">
                                <p><strong>ID:</strong> ${sys.system_id}</p>
                                <p><strong>名称:</strong> ${sys.name}</p>
                                ${sys.coordinates ? `
                                    <p class="text-xs text-gray-500">
                                        坐标: x=${Math.round(sys.coordinates.x)}, 
                                        y=${Math.round(sys.coordinates.y)}, 
                                        z=${Math.round(sys.coordinates.z)}
                                    </p>
                                ` : ''}
                            </div>
                        `).join('')}
                    </div>
                `;
            } catch (e) {
                alert('请求失败: ' + e.message);
            }
        }
    </script>
</body>
</html>
