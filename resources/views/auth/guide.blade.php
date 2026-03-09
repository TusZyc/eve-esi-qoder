<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>角色授权 - EVE ESI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .eve-bg {
            background: linear-gradient(135deg, #0c1445 0%, #1a237e 50%, #283593 100%);
        }
        .eve-glow {
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.5);
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
</head>
<body class="eve-bg min-h-screen text-white">
    <div class="container mx-auto px-4 py-8">
        <!-- 头部 -->
        <header class="text-center mb-8">
            <h1 class="text-4xl font-bold mb-2 eve-glow">🚀 EVE 角色授权</h1>
            <p class="text-blue-200">授权后即可查看角色信息、技能、资产等数据</p>
        </header>

        <main class="max-w-3xl mx-auto">
            <!-- 步骤 1：清除缓存 -->
            <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-8 mb-6 eve-glow">
                <h2 class="text-2xl font-semibold mb-6 text-center">📋 第 1 步：清除授权缓存</h2>
                
                <div class="space-y-6">
                    <div class="text-center space-y-3">
                        <p class="text-blue-100">首次授权前，需要先清除旧的授权缓存：</p>
                        
                        <div class="bg-yellow-500/20 border border-yellow-500/50 rounded-lg p-4 text-left text-sm text-yellow-200">
                            <strong>💡 为什么要清除缓存？</strong><br>
                            如果您之前授权过其他 EVE 工具，可能需要先清除旧授权，确保本次授权正常生效。
                        </div>
                    </div>
                    
                    <!-- 清除缓存按钮 -->
                    <button onclick="clearCache()" 
                            id="clearCacheBtn"
                            class="w-full bg-yellow-600 hover:bg-yellow-700 text-white font-semibold px-8 py-4 rounded-lg transition-all eve-glow hover:scale-105 text-lg">
                        🗑️ 点击清除授权缓存
                    </button>
                    
                    <!-- 等待提示 -->
                    <div id="clearCacheTip" class="hidden">
                        <div class="bg-blue-500/20 border border-blue-500/50 rounded-lg p-4">
                            <p class="text-blue-200">
                                <strong>📝 操作步骤：</strong>
                            </p>
                            <ol class="list-decimal list-inside space-y-2 text-blue-100 mt-2">
                                <li>在新打开的窗口中，会自动清除授权缓存</li>
                                <li>等待页面显示 <strong class="text-red-400">"抱歉，处理你的请求时发生了错误"</strong></li>
                                <li>看到错误提示后，关闭新窗口</li>
                                <li>返回此页面，继续下一步</li>
                            </ol>
                        </div>
                    </div>
                    
                    <!-- 清除完成提示 -->
                    <div id="clearCacheDone" class="hidden">
                        <div class="bg-green-500/20 border border-green-500/50 rounded-lg p-4">
                            <p class="text-green-300">
                                ✅ <strong>缓存已清除！</strong>请继续下一步授权操作
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 步骤 2：开始授权 -->
            <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-8 mb-6 eve-glow">
                <h2 class="text-2xl font-semibold mb-6 text-center">🔐 第 2 步：开始授权</h2>
                
                <div class="space-y-6">
                    <div class="text-center space-y-3">
                        <p class="text-blue-100">点击下方按钮，在新窗口完成授权：</p>
                        
                        <div class="flex items-center justify-center space-x-4 text-sm text-blue-300">
                            <span class="flex items-center">
                                <span class="w-6 h-6 rounded-full bg-blue-600 flex items-center justify-center text-xs mr-2">1</span>
                                点击按钮
                            </span>
                            <span>→</span>
                            <span class="flex items-center">
                                <span class="w-6 h-6 rounded-full bg-blue-600 flex items-center justify-center text-xs mr-2">2</span>
                                网易登录
                            </span>
                            <span>→</span>
                            <span class="flex items-center">
                                <span class="w-6 h-6 rounded-full bg-blue-600 flex items-center justify-center text-xs mr-2">3</span>
                                确认授权
                            </span>
                        </div>
                    </div>
                    
                    <!-- 授权按钮 -->
                    <button onclick="openAuth()" 
                            id="authBtn"
                            disabled
                            class="w-full bg-gray-600 text-gray-400 font-semibold px-8 py-4 rounded-lg transition-all eve-glow text-lg cursor-not-allowed">
                        🔗 请先完成第 1 步
                    </button>
                    
                    <!-- 等待提示 -->
                    <div id="waitingTip" class="hidden text-center">
                        <p class="text-yellow-300">⏳ 等待授权完成...</p>
                        <p class="text-sm text-blue-300 mt-2">授权完成后请关闭新窗口，返回此页面填写授权码</p>
                    </div>
                </div>
            </div>

            <!-- 步骤 3：填写授权码 -->
            <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-6 mb-6 eve-glow">
                <h2 class="text-xl font-semibold mb-4">📋 第 3 步：填写授权码</h2>
                
                <p class="text-sm text-blue-100 mb-4">
                    授权完成后，从新窗口的地址栏复制授权码粘贴到这里：
                </p>
                
                <form action="{{ route('auth.callback') }}" method="GET" class="space-y-4">
                    <div>
                        <label class="block text-sm text-blue-300 mb-2">授权后的完整 URL：</label>
                        <input type="text" name="callback_url" id="callbackUrlInput"
                               placeholder="https://ali-esi.evepc.163.com/ui/oauth2-redirect.html?code=XXX&state=XXX"
                               class="w-full bg-black/30 border border-blue-500/50 rounded-lg p-3 text-white font-mono" />
                        <p class="text-xs text-blue-400 mt-2">
                            💡 授权后 URL 格式：https://.../oauth2-redirect.html?<strong class="text-yellow-400">code=ABC123...</strong>&state=...
                        </p>
                    </div>
                    
                    <button type="submit" 
                            class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-3 rounded-lg transition-all eve-glow">
                        ✅ 提交授权码
                    </button>
                </form>
            </div>

            <!-- 权限说明 -->
            <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-6 eve-glow">
                <h2 class="text-xl font-semibold mb-4">📊 需要哪些权限？</h2>
                <p class="text-sm text-blue-100 mb-4">
                    为了提供完整的功能，我们需要访问以下游戏数据：
                </p>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-xs text-blue-200">
                    <div class="flex items-center">
                        <span class="text-green-400 mr-2">✓</span>
                        <span>技能信息</span>
                    </div>
                    <div class="flex items-center">
                        <span class="text-green-400 mr-2">✓</span>
                        <span>技能队列</span>
                    </div>
                    <div class="flex items-center">
                        <span class="text-green-400 mr-2">✓</span>
                        <span>角色资产</span>
                    </div>
                    <div class="flex items-center">
                        <span class="text-green-400 mr-2">✓</span>
                        <span>钱包余额</span>
                    </div>
                    <div class="flex items-center">
                        <span class="text-green-400 mr-2">✓</span>
                        <span>市场订单</span>
                    </div>
                    <div class="flex items-center">
                        <span class="text-green-400 mr-2">✓</span>
                        <span>军团信息</span>
                    </div>
                    <div class="flex items-center">
                        <span class="text-green-400 mr-2">✓</span>
                        <span>当前位置</span>
                    </div>
                    <div class="flex items-center">
                        <span class="text-green-400 mr-2">✓</span>
                        <span>舰船信息</span>
                    </div>
                    <div class="flex items-center">
                        <span class="text-green-400 mr-2">✓</span>
                        <span>更多...</span>
                    </div>
                </div>
                
                <div class="bg-blue-500/20 border border-blue-500/50 rounded-lg p-4 mt-4">
                    <p class="text-sm text-blue-200">
                        <strong>🔒 安全提示：</strong><br>
                        • 授权通过网易官方页面进行，安全可靠<br>
                        • 授权后随时可以在官网撤销<br>
                        • 我们不会存储您的账号密码
                    </p>
                </div>
            </div>
        </main>

        <!-- 页脚 -->
        <footer class="text-center mt-8 text-blue-300 text-sm">
            <a href="{{ route('home') }}" class="hover:text-white">← 返回首页</a>
        </footer>
    </div>

    <script>
        let authWindow = null;
        let checkInterval = null;
        let cacheCleared = false;
        
        // 第 1 步：清除授权缓存
        function clearCache() {
            // 打开清除缓存的页面
            const clearCacheUrl = 'https://login.evepc.163.com/account/logoff';
            const cacheWindow = window.open(clearCacheUrl, '_blank', 'width=600,height=400');
            
            // 显示等待提示
            document.getElementById('clearCacheTip').classList.remove('hidden');
            document.getElementById('clearCacheBtn').disabled = true;
            document.getElementById('clearCacheBtn').classList.add('opacity-50', 'cursor-not-allowed');
            
            // 定期检查窗口是否关闭
            if (cacheWindow) {
                const checkCacheInterval = setInterval(function() {
                    if (cacheWindow && cacheWindow.closed) {
                        clearInterval(checkCacheInterval);
                        
                        // 清除完成
                        cacheCleared = true;
                        document.getElementById('clearCacheTip').classList.add('hidden');
                        document.getElementById('clearCacheDone').classList.remove('hidden');
                        
                        // 启用授权按钮
                        const authBtn = document.getElementById('authBtn');
                        authBtn.disabled = false;
                        authBtn.classList.remove('bg-gray-600', 'text-gray-400', 'cursor-not-allowed');
                        authBtn.classList.add('bg-blue-600', 'hover:bg-blue-700', 'text-white');
                        authBtn.innerHTML = '🔗 点击前往授权页面';
                    }
                }, 1000);
            } else {
                alert('浏览器阻止了弹出窗口，请允许弹出窗口后重试');
            }
        }
        
        // 第 2 步：开始授权
        function openAuth() {
            if (!cacheCleared) {
                alert('请先完成第 1 步：清除授权缓存');
                return;
            }
            
            // 生成随机 state（3 个字节 = 6 个字符，和 3V 一样）
            const state = Array.from(crypto.getRandomValues(new Uint8Array(3)), 
                b => b.toString(16).padStart(2, '0')).join('');
            
            // 保存到 sessionStorage
            sessionStorage.setItem('esi_state', state);
            
            // 使用 3V 完全一样的 URL 格式
            const clientId = 'bc90aa496a404724a93f41b4f4e97761';
            const redirectUri = 'https://ali-esi.evepc.163.com/ui/oauth2-redirect.html';
            const deviceId = 'tus';
            const scopes = getScopes();
            
            // 按照 3V 的参数顺序构建 URL（scope 不编码空格！）
            const authUrl = 'https://login.evepc.163.com/v2/oauth/authorize' +
                '?response_type=code' +
                '&client_id=' + clientId +
                '&redirect_uri=' + encodeURIComponent(redirectUri) +
                '&device_id=' + deviceId +
                '&state=' + state +
                '&scope=' + scopes;  // 不要 encodeURIComponent！
            
            // 在新窗口打开授权页面
            authWindow = window.open(authUrl, '_blank', 'width=900,height=700,scrollbars=yes,resizable=yes');
            
            // 检查窗口是否关闭
            if (authWindow) {
                // 显示等待提示
                document.getElementById('waitingTip').classList.remove('hidden');
                
                // 定期检查窗口是否关闭
                checkInterval = setInterval(function() {
                    if (authWindow && authWindow.closed) {
                        clearInterval(checkInterval);
                        document.getElementById('waitingTip').innerHTML = 
                            '<p class="text-green-300">✅ 授权窗口已关闭，请从刚才的窗口地址栏复制授权码填写到下方</p>';
                        
                        // 聚焦到输入框
                        document.getElementById('codeInput').focus();
                    }
                }, 1000);
            } else {
                alert('浏览器阻止了弹出窗口，请允许弹出窗口后重试');
            }
        }
        
        function getScopes() {
            return [
                'esi-calendar.respond_calendar_events.v1',
                'esi-calendar.read_calendar_events.v1',
                'esi-location.read_location.v1',
                'esi-location.read_ship_type.v1',
                'esi-location.read_online.v1',
                'esi-mail.organize_mail.v1',
                'esi-mail.read_mail.v1',
                'esi-mail.send_mail.v1',
                'esi-skills.read_skills.v1',
                'esi-skills.read_skillqueue.v1',
                'esi-wallet.read_character_wallet.v1',
                'esi-wallet.read_corporation_wallets.v1',
                'esi-search.search_structures.v1',
                'esi-clones.read_clones.v1',
                'esi-clones.read_implants.v1',
                'esi-characters.read_contacts.v1',
                'esi-characters.write_contacts.v1',
                'esi-corporations.read_contacts.v1',
                'esi-alliances.read_contacts.v1',
                'esi-universe.read_structures.v1',
                'esi-killmails.read_killmails.v1',
                'esi-killmails.read_corporation_killmails.v1',
                'esi-corporations.read_corporation_membership.v1',
                'esi-corporations.read_structures.v1',
                'esi-corporations.track_members.v1',
                'esi-corporations.read_divisions.v1',
                'esi-corporations.read_titles.v1',
                'esi-corporations.read_blueprints.v1',
                'esi-corporations.read_standings.v1',
                'esi-corporations.read_starbases.v1',
                'esi-corporations.read_facilities.v1',
                'esi-corporations.read_medals.v1',
                'esi-corporations.read_container_logs.v1',
                'esi-corporations.read_fw_stats.v1',
                'esi-assets.read_assets.v1',
                'esi-assets.read_corporation_assets.v1',
                'esi-planets.manage_planets.v1',
                'esi-planets.read_customs_offices.v1',
                'esi-fleets.read_fleet.v1',
                'esi-fleets.write_fleet.v1',
                'esi-ui.open_window.v1',
                'esi-ui.write_waypoint.v1',
                'esi-fittings.read_fittings.v1',
                'esi-fittings.write_fittings.v1',
                'esi-markets.structure_markets.v1',
                'esi-markets.read_character_orders.v1',
                'esi-markets.read_corporation_orders.v1',
                'esi-characters.read_loyalty.v1',
                'esi-characters.read_opportunities.v1',
                'esi-characters.read_medals.v1',
                'esi-characters.read_standings.v1',
                'esi-characters.read_agents_research.v1',
                'esi-industry.read_character_jobs.v1',
                'esi-industry.read_corporation_jobs.v1',
                'esi-characters.read_blueprints.v1',
                'esi-characters.read_corporation_roles.v1',
                'esi-characters.read_notifications.v1',
                'esi-characters.read_fw_stats.v1',
                'esi-characterstats.read.v1',
                'esi-contracts.read_character_contracts.v1',
                'esi-contracts.read_corporation_contracts.v1',
                'esi-characters.read_fatigue.v1',
                'esi-industry.read_character_mining.v1',
                'esi-industry.read_corporation_mining.v1',
                'esi-characters.read_titles.v1',
            ].join(' ');
        }
    </script>
</body>
</html>
