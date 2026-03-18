<nav class="bg-white/10 backdrop-blur-lg border-b border-white/20">
    <div class="container mx-auto px-4 py-2">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
                @php $isLogged = $isLoggedIn ?? false; $activePage = $activePage ?? ''; @endphp

                <!-- 仪表盘按钮 -->
                @if($isLogged)
                    <a href="{{ route('dashboard') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all{{ $activePage === 'dashboard' ? ' bg-white/10' : '' }}" title="仪表盘"><div class="text-2xl">🏠</div></a>
                @else
                    <a href="{{ route('guest.dashboard') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all{{ $activePage === 'dashboard' ? ' bg-white/10' : '' }}" title="仪表盘"><div class="text-2xl">🏠</div></a>
                @endif

                <!-- 市场按钮（所有用户） -->
                <a href="{{ route('market.index') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all{{ $activePage === 'market' ? ' bg-white/10' : '' }}" title="市场中心"><div class="text-2xl">📊</div></a>

                <!-- KM 查询（所有用户） -->
                <a href="{{ route('killmails.index') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all{{ $activePage === 'killmails' ? ' bg-white/10' : '' }}" title="KM 查询"><div class="text-2xl">⚔️</div></a>

                <!-- 旗舰导航（所有用户） -->
                <a href="{{ route('capital-nav.index') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all{{ $activePage === 'capital-nav' ? ' bg-white/10' : '' }}" title="旗舰导航"><div class="text-2xl">📍</div></a>

                <!-- LP 商店（所有用户） -->
                <a href="{{ route('lp-store.index') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all{{ $activePage === 'lp-store' ? ' bg-white/10' : '' }}" title="LP 商店"><div class="text-2xl">💎</div></a>

                <!-- 技能队列（仅认证用户） -->
                @if($isLogged)
                    <a href="{{ route('skills.index') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all{{ $activePage === 'skills' ? ' bg-white/10' : '' }}" title="技能队列"><div class="text-2xl">📚</div></a>
                @endif

                <!-- 资产和角色（仅认证用户） -->
                @if($isLogged)
                    <a href="{{ route('assets.index') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all{{ $activePage === 'assets' ? ' bg-white/10' : '' }}" title="我的资产"><div class="text-2xl">📦</div></a>
                    <a href="{{ route('characters.index') }}" class="p-3 hover:bg-white/10 rounded-lg transition-all{{ $activePage === 'characters' ? ' bg-white/10' : '' }}" title="角色管理"><div class="text-2xl">👥</div></a>
                @endif
            </div>

            <div class="flex items-center space-x-3">
                <!-- 品牌名称和用户信息 -->
                @if($isLogged)
                    <a href="{{ route('dashboard') }}" class="text-xl font-bold">Tus Esi System</a>
                    @php
                        $isSiteAdmin = \App\Http\Middleware\EnsureSiteAdmin::isSiteAdmin($user ?? null);
                    @endphp
                    @if($isSiteAdmin)
                        <a href="{{ route('admin.dashboard') }}" class="text-sm text-blue-200 ml-3 hover:text-white transition-colors" title="点击进入管理后台">
                            欢迎，{{ $user->name ?? '' }} <span class="ml-1">⚙️</span>
                        </a>
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
