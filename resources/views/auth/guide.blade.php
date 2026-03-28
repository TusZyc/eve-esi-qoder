@extends('layouts.guest')

@section('title', '角色授权 - Tus Esi System')
@section('page-title', 'EVE 角色授权')

@section('content')
<div class="max-w-4xl mx-auto">
    <!-- 顶部引导头 -->
    <div class="mb-8">
        <div class="flex items-center space-x-3 mb-2">
            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center">
                <span class="text-xl">🚀</span>
            </div>
            <div>
                <h1 class="text-2xl font-bold bg-gradient-to-r from-white to-slate-300 bg-clip-text text-transparent">EVE 角色授权</h1>
                <p class="text-sm text-slate-400">授权后即可查看角色信息、技能、资产等数据</p>
            </div>
        </div>
    </div>

    <!-- 步骤 1：清除授权缓存 -->
    <div class="stat-card rounded-xl p-6 mb-6">
        <div class="flex items-center space-x-3 mb-4">
            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-yellow-500 to-orange-600 flex items-center justify-center text-sm font-bold shadow-lg shadow-yellow-500/20">1</div>
            <h2 class="text-lg font-semibold">清除授权缓存</h2>
            <span id="step1Status" class="hidden ml-auto text-green-400 text-sm">✓ 已完成</span>
        </div>
        
        <div class="space-y-4">
            <p class="text-slate-300 text-sm">首次授权前，需要先清除旧的授权缓存：</p>
            
            <!-- 提示框 -->
            <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-4">
                <p class="text-yellow-300 text-sm">
                    <strong class="flex items-center mb-1">
                        <span class="mr-2">💡</span>为什么要清除缓存？
                    </strong>
                    <span class="text-yellow-200/80">如果您之前授权过其他 EVE 工具，可能需要先清除旧授权，确保本次授权正常生效。</span>
                </p>
            </div>
            
            <!-- 清除缓存按钮 -->
            <button onclick="clearCache()" 
                    id="clearCacheBtn"
                    class="w-full bg-gradient-to-r from-yellow-500 to-orange-600 hover:from-yellow-400 hover:to-orange-500 text-white font-semibold px-6 py-3.5 rounded-lg transition-all shadow-lg shadow-yellow-500/20 hover:shadow-yellow-500/30 hover:-translate-y-0.5 flex items-center justify-center space-x-2">
                <span>🗑️</span>
                <span>点击清除授权缓存</span>
            </button>
            
            <!-- 等待提示 -->
            <div id="clearCacheTip" class="hidden">
                <div class="bg-blue-500/10 border border-blue-500/30 rounded-lg p-4">
                    <p class="text-blue-300 text-sm font-medium mb-2">📝 操作步骤：</p>
                    <ol class="list-decimal list-inside space-y-1.5 text-blue-200/80 text-sm">
                        <li>在新打开的窗口中，会自动清除授权缓存</li>
                        <li>等待页面显示 <strong class="text-red-400">"抱歉，处理你的请求时发生了错误"</strong></li>
                        <li>看到错误提示后，关闭新窗口</li>
                        <li>返回此页面，继续下一步</li>
                    </ol>
                </div>
            </div>
            
            <!-- 清除完成提示 -->
            <div id="clearCacheDone" class="hidden">
                <div class="bg-green-500/10 border border-green-500/30 rounded-lg p-4">
                    <p class="text-green-300 text-sm flex items-center">
                        <span class="mr-2">✅</span>
                        <strong>缓存已清除！</strong>
                        <span class="ml-1 text-green-200/80">请继续下一步授权操作</span>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- 步骤连接线 -->
        <div class="flex justify-center mt-6">
            <div class="w-0.5 h-8 bg-gradient-to-b from-slate-600 to-transparent"></div>
        </div>
    </div>

    <!-- 步骤 2：开始授权 -->
    <div class="stat-card rounded-xl p-6 mb-6">
        <div class="flex items-center space-x-3 mb-4">
            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-sm font-bold shadow-lg shadow-blue-500/20">2</div>
            <h2 class="text-lg font-semibold">开始授权</h2>
        </div>
        
        <div class="space-y-4">
            <p class="text-slate-300 text-sm">点击下方按钮，在新窗口完成授权：</p>
            
            <!-- 流程步骤条 -->
            <div class="flex items-center justify-center py-4">
                <div class="flex items-center space-x-3">
                    <!-- 步骤 1 -->
                    <div class="flex items-center">
                        <div class="w-3 h-3 rounded-full bg-blue-500 shadow-lg shadow-blue-500/50"></div>
                        <span class="ml-2 text-sm text-slate-300">点击按钮</span>
                    </div>
                    <!-- 连接线 -->
                    <div class="w-12 h-0.5 bg-gradient-to-r from-blue-500 to-blue-400"></div>
                    <!-- 步骤 2 -->
                    <div class="flex items-center">
                        <div class="w-3 h-3 rounded-full bg-blue-500 shadow-lg shadow-blue-500/50"></div>
                        <span class="ml-2 text-sm text-slate-300">网易登录</span>
                    </div>
                    <!-- 连接线 -->
                    <div class="w-12 h-0.5 bg-gradient-to-r from-blue-400 to-blue-500"></div>
                    <!-- 步骤 3 -->
                    <div class="flex items-center">
                        <div class="w-3 h-3 rounded-full bg-blue-500 shadow-lg shadow-blue-500/50"></div>
                        <span class="ml-2 text-sm text-slate-300">确认授权</span>
                    </div>
                </div>
            </div>
            
            <!-- 授权按钮 -->
            <button onclick="openAuth()" 
                    id="authBtn"
                    disabled
                    class="w-full bg-slate-700 text-slate-500 font-semibold px-6 py-3.5 rounded-lg transition-all cursor-not-allowed flex items-center justify-center space-x-2">
                <span>🔗</span>
                <span>请先完成第 1 步</span>
            </button>
            
            <!-- 等待提示 -->
            <div id="waitingTip" class="hidden">
                <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-4 text-center">
                    <p class="text-yellow-300 text-sm flex items-center justify-center">
                        <span class="mr-2 animate-spin">⏳</span>
                        等待授权完成...
                    </p>
                    <p class="text-blue-300/80 text-xs mt-2">授权完成后请关闭新窗口，返回此页面填写授权码</p>
                </div>
            </div>
        </div>
        
        <!-- 步骤连接线 -->
        <div class="flex justify-center mt-6">
            <div class="w-0.5 h-8 bg-gradient-to-b from-slate-600 to-transparent"></div>
        </div>
    </div>

    <!-- 步骤 3：填写授权码 -->
    <div class="stat-card rounded-xl p-6 mb-6">
        <div class="flex items-center space-x-3 mb-4">
            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center text-sm font-bold shadow-lg shadow-green-500/20">3</div>
            <h2 class="text-lg font-semibold">填写授权码</h2>
        </div>
        
        <div class="space-y-4">
            <p class="text-slate-300 text-sm">授权完成后，从新窗口的地址栏复制完整 URL 粘贴到这里：</p>
            
            <form action="{{ route('auth.callback') }}" method="GET" class="space-y-4">
                <div>
                    <label class="block text-sm text-slate-400 mb-2">授权后的完整 URL：</label>
                    <input type="text" name="callback_url" id="callbackUrlInput"
                           placeholder="https://ali-esi.evepc.163.com/ui/oauth2-redirect.html?code=XXX&state=XXX"
                           class="w-full bg-slate-800/50 border border-slate-600/50 rounded-lg px-4 py-3 text-white font-mono text-sm placeholder-slate-500 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-all" />
                    <p class="text-xs text-slate-500 mt-2">
                        💡 授权后 URL 格式：https://.../oauth2-redirect.html?<span class="text-yellow-400">code=ABC123...</span>&state=...
                    </p>
                </div>
                
                <button type="submit" 
                        class="w-full bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-400 hover:to-emerald-500 text-white font-semibold px-6 py-3.5 rounded-lg transition-all shadow-lg shadow-green-500/20 hover:shadow-green-500/30 hover:-translate-y-0.5 flex items-center justify-center space-x-2">
                    <span>✅</span>
                    <span>提交授权码</span>
                </button>
            </form>
        </div>
    </div>

    <!-- 权限说明 -->
    <div class="stat-card rounded-xl p-6">
        <div class="flex items-center space-x-3 mb-4">
            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center shadow-lg shadow-purple-500/20">
                <span class="text-sm">📊</span>
            </div>
            <h2 class="text-lg font-semibold">需要哪些权限？</h2>
        </div>
        
        <p class="text-slate-400 text-sm mb-4">为了提供完整的功能，我们需要访问以下游戏数据：</p>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
            <div class="flex items-center text-sm">
                <span class="text-green-400 mr-2">✓</span>
                <span class="text-slate-300">技能信息</span>
            </div>
            <div class="flex items-center text-sm">
                <span class="text-green-400 mr-2">✓</span>
                <span class="text-slate-300">技能队列</span>
            </div>
            <div class="flex items-center text-sm">
                <span class="text-green-400 mr-2">✓</span>
                <span class="text-slate-300">角色资产</span>
            </div>
            <div class="flex items-center text-sm">
                <span class="text-green-400 mr-2">✓</span>
                <span class="text-slate-300">钱包余额</span>
            </div>
            <div class="flex items-center text-sm">
                <span class="text-green-400 mr-2">✓</span>
                <span class="text-slate-300">市场订单</span>
            </div>
            <div class="flex items-center text-sm">
                <span class="text-green-400 mr-2">✓</span>
                <span class="text-slate-300">军团信息</span>
            </div>
            <div class="flex items-center text-sm">
                <span class="text-green-400 mr-2">✓</span>
                <span class="text-slate-300">当前位置</span>
            </div>
            <div class="flex items-center text-sm">
                <span class="text-green-400 mr-2">✓</span>
                <span class="text-slate-300">舰船信息</span>
            </div>
        </div>
        
        <div class="bg-blue-500/10 border border-blue-500/30 rounded-lg p-4">
            <p class="text-blue-300 text-sm">
                <strong class="flex items-center mb-2">
                    <span class="mr-2">🔒</span>安全提示
                </strong>
            </p>
            <ul class="text-blue-200/80 text-sm space-y-1">
                <li>• 授权通过网易官方页面进行，安全可靠</li>
                <li>• 授权后随时可以在官网撤销</li>
                <li>• 网站不会存储您的账号密码</li>
            </ul>
        </div>
    </div>
</div>
@endsection

@push('scripts')
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
                    document.getElementById('step1Status').classList.remove('hidden');
                    
                    // 启用授权按钮
                    const authBtn = document.getElementById('authBtn');
                    authBtn.disabled = false;
                    authBtn.classList.remove('bg-slate-700', 'text-slate-500', 'cursor-not-allowed');
                    authBtn.classList.add('bg-gradient-to-r', 'from-blue-500', 'to-blue-600', 'hover:from-blue-400', 'hover:to-blue-500', 'text-white', 'shadow-lg', 'shadow-blue-500/20', 'hover:shadow-blue-500/30', 'hover:-translate-y-0.5');
                    authBtn.innerHTML = '<span>🔗</span><span class="ml-2">点击前往授权页面</span>';
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
                        '<div class="bg-green-500/10 border border-green-500/30 rounded-lg p-4 text-center">' +
                        '<p class="text-green-300 text-sm">✅ 授权窗口已关闭，请从刚才的窗口地址栏复制授权码填写到下方</p></div>';
                    
                    // 聚焦到输入框
                    document.getElementById('callbackUrlInput').focus();
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
@endpush
