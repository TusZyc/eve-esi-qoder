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
                    <span class="text-sm text-blue-200 ml-3">欢迎，{{ $user->name ?? '' }}</span>
                @else
                    <a href="{{ route('guest.dashboard') }}" class="text-xl font-bold">Tus Esi System</a>
                @endif
            </div>
        </div>
    </div>
</nav>
