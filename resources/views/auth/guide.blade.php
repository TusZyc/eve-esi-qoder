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
            <!-- 授权按钮 -->
            <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-8 mb-6 eve-glow">
                <h2 class="text-2xl font-semibold mb-6 text-center">🔐 开始授权</h2>
                
                <div class="space-y-6">
                    <!-- 步骤说明 -->
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
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold px-8 py-4 rounded-lg transition-all eve-glow hover:scale-105 text-lg">
                        🔗 点击前往授权页面
                    </button>
                    
                    <!-- 等待提示 -->
                    <div id="waitingTip" class="hidden text-center">
                        <p class="text-yellow-300">⏳ 等待授权完成...</p>
                        <p class="text-sm text-blue-300 mt-2">授权完成后请关闭新窗口，返回此页面填写授权码</p>
                    </div>
                </div>
            </div>

            <!-- 手动输入 Code -->
            <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-6 mb-6 eve-glow">
                <h2 class="text-xl font-semibold mb-4">📋 填写授权码</h2>
                
                <p class="text-sm text-blue-100 mb-4">
                    授权完成后，从新窗口的地址栏复制授权码粘贴到这里：
                </p>
                
                <form action="{{ route('auth.callback') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm text-blue-300 mb-2">授权码（code）：</label>
                        <input type="text" name="code" id="codeInput"
                               placeholder="粘贴授权码（不包含 code=）"
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
        
        function openAuth() {
            // 生成随机 state（和 3V 一样的格式）
            const state = Array.from(crypto.getRandomValues(new Uint8Array(16)), 
                b => b.toString(16).padStart(2, '0')).join('');
            
            // 保存到 sessionStorage
            sessionStorage.setItem('esi_state', state);
            
            // 使用 3V 完全一样的 URL 格式（字符串拼接，固定参数顺序）
            const clientId = 'bc90aa496a404724a93f41b4f4e97761';
            const redirectUri = 'https://ali-esi.evepc.163.com/ui/oauth2-redirect.html';
            const deviceId = 'tus';
            const scopes = getScopes();
            
            // 按照 3V 的参数顺序构建 URL
            const authUrl = 'https://login.evepc.163.com/v2/oauth/authorize' +
                '?response_type=code' +
                '&client_id=' + clientId +
                '&redirect_uri=' + encodeURIComponent(redirectUri) +
                '&device_id=' + deviceId +
                '&state=' + state +
                '&scope=' + encodeURIComponent(scopes);
            
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
