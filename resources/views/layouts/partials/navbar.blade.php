<nav class="bg-white/10 backdrop-blur-lg border-b border-white/20 relative z-50">
    <div class="container mx-auto px-4 py-2">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-1">
                @php $isLogged = $isLoggedIn ?? false; $activePage = $activePage ?? ''; @endphp

                <!-- 仪表盘按钮 -->
                @if($isLogged)
                    <a href="{{ route('dashboard') }}" class="flex items-center space-x-2 px-3 py-2 hover:bg-white/10 rounded-lg transition-all{{ $activePage === 'dashboard' ? ' bg-white/10' : '' }}">
                        <span class="text-xl">🏠</span>
                        <span class="text-sm font-medium">仪表盘</span>
                    </a>
                @else
                    <a href="{{ route('guest.dashboard') }}" class="flex items-center space-x-2 px-3 py-2 hover:bg-white/10 rounded-lg transition-all{{ $activePage === 'dashboard' ? ' bg-white/10' : '' }}">
                        <span class="text-xl">🏠</span>
                        <span class="text-sm font-medium">仪表盘</span>
                    </a>
                @endif

                <!-- 角色信息分类（仅认证用户） -->
                @if($isLogged)
                    <div class="relative group z-50">
                        <button class="flex items-center space-x-2 px-3 py-2 hover:bg-white/10 rounded-lg transition-all cursor-pointer">
                            <span class="text-xl">👤</span>
                            <span class="text-sm font-medium">角色信息</span>
                            <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div class="absolute left-0 top-full mt-1 w-40 bg-gray-900/95 backdrop-blur-lg border border-white/20 rounded-lg shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 max-h-96 overflow-y-auto">
                            <a href="{{ route('skills.index') }}" class="flex items-center space-x-2 px-4 py-3 hover:bg-white/10 rounded-t-lg transition-all{{ $activePage === 'skills' ? ' bg-white/10' : '' }}">
                                <span class="text-lg">📚</span>
                                <span class="text-sm">技能队列</span>
                            </a>
                            <a href="{{ route('assets.index') }}" class="flex items-center space-x-2 px-4 py-3 hover:bg-white/10 transition-all{{ $activePage === 'assets' ? ' bg-white/10' : '' }}">
                                <span class="text-lg">📦</span>
                                <span class="text-sm">我的资产</span>
                            </a>
                            <a href="{{ route('wallet.index') }}" class="flex items-center space-x-2 px-4 py-3 hover:bg-white/10 transition-all{{ $activePage === 'wallet' ? ' bg-white/10' : '' }}">
                                <span class="text-lg">💰</span>
                                <span class="text-sm">钱包</span>
                            </a>
                            <a href="{{ route('contacts.index') }}" class="flex items-center space-x-2 px-4 py-3 hover:bg-white/10 transition-all{{ $activePage === 'contacts' ? ' bg-white/10' : '' }}">
                                <span class="text-lg">📇</span>
                                <span class="text-sm">联系人</span>
                            </a>
                            <a href="{{ route('contracts.index') }}" class="flex items-center space-x-2 px-4 py-3 hover:bg-white/10 transition-all{{ $activePage === 'contracts' ? ' bg-white/10' : '' }}">
                                <span class="text-lg">📜</span>
                                <span class="text-sm">合同</span>
                            </a>
                            <a href="{{ route('fittings.index') }}" class="flex items-center space-x-2 px-4 py-3 hover:bg-white/10 transition-all{{ $activePage === 'fittings' ? ' bg-white/10' : '' }}">
                                <span class="text-lg">🔧</span>
                                <span class="text-sm">装配</span>
                            </a>
                            <a href="{{ route('character-killmails.index') }}" class="flex items-center space-x-2 px-4 py-3 hover:bg-white/10 transition-all{{ $activePage === 'character-killmails' ? ' bg-white/10' : '' }}">
                                <span class="text-lg">💀</span>
                                <span class="text-sm">击毁报告</span>
                            </a>
                            <a href="{{ route('notifications.index') }}" class="flex items-center space-x-2 px-4 py-3 hover:bg-white/10 transition-all{{ $activePage === 'notifications' ? ' bg-white/10' : '' }}">
                                <span class="text-lg">🔔</span>
                                <span class="text-sm">提醒</span>
                            </a>
                            <a href="{{ route('standings.index') }}" class="flex items-center space-x-2 px-4 py-3 hover:bg-white/10 transition-all{{ $activePage === 'standings' ? ' bg-white/10' : '' }}">
                                <span class="text-lg">⭐</span>
                                <span class="text-sm">声望</span>
                            </a>
                            <a href="{{ route('characters.index') }}" class="flex items-center space-x-2 px-4 py-3 hover:bg-white/10 rounded-b-lg transition-all{{ $activePage === 'characters' ? ' bg-white/10' : '' }}">
                                <span class="text-lg">👥</span>
                                <span class="text-sm">角色管理</span>
                            </a>
                        </div>
                    </div>
                @endif

                <!-- 工具栏分类 -->
                <div class="relative group z-50">
                    <button class="flex items-center space-x-2 px-3 py-2 hover:bg-white/10 rounded-lg transition-all cursor-pointer">
                        <span class="text-xl">🛠️</span>
                        <span class="text-sm font-medium">工具栏</span>
                        <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div class="absolute left-0 top-full mt-1 w-40 bg-gray-900/95 backdrop-blur-lg border border-white/20 rounded-lg shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                        <a href="{{ route('market.index') }}" class="flex items-center space-x-2 px-4 py-3 hover:bg-white/10 rounded-t-lg transition-all{{ $activePage === 'market' ? ' bg-white/10' : '' }}">
                            <span class="text-lg">📊</span>
                            <span class="text-sm">市场中心</span>
                        </a>
                        <a href="{{ route('killmails.index') }}" class="flex items-center space-x-2 px-4 py-3 hover:bg-white/10 transition-all{{ $activePage === 'killmails' ? ' bg-white/10' : '' }}">
                            <span class="text-lg">⚔️</span>
                            <span class="text-sm">KM查询</span>
                        </a>
                        <a href="{{ route('capital-nav.index') }}" class="flex items-center space-x-2 px-4 py-3 hover:bg-white/10 transition-all{{ $activePage === 'capital-nav' ? ' bg-white/10' : '' }}">
                            <span class="text-lg">📍</span>
                            <span class="text-sm">旗舰导航</span>
                        </a>
                        <a href="{{ route('lp-store.index') }}" class="flex items-center space-x-2 px-4 py-3 hover:bg-white/10 rounded-b-lg transition-all{{ $activePage === 'lp-store' ? ' bg-white/10' : '' }}">
                            <span class="text-lg">💎</span>
                            <span class="text-sm">LP商店</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="flex items-center space-x-3">
                <!-- 品牌名称和用户信息 -->
                @if($isLogged)
                    <a href="{{ route('dashboard') }}" class="text-xl font-bold">Tus Esi System</a>
                    @if(\App\Http\Middleware\EnsureSiteAdmin::isSiteAdmin($user))
                        <a href="{{ route('admin.dashboard') }}" class="text-sm text-blue-200 ml-3 hover:text-white transition-colors">欢迎，{{ $user->name ?? '' }} 👑</a>
                    @else
                        <span class="text-sm text-blue-200 ml-3">欢迎，{{ $user->name ?? '' }}</span>
                    @endif
                @else
                    <a href="{{ route('guest.dashboard') }}" class="text-xl font-bold">Tus Esi System</a>
                @endif
            </div>
        </div>
    </div>
</nav>
