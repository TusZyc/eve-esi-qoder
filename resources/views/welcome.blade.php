<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tus Esi System (Beta)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* 基础背景 */
        .eve-bg {
            background: linear-gradient(135deg, #0a0e27 0%, #0c1445 30%, #1a237e 60%, #0d1137 100%);
        }
        .eve-glow {
            box-shadow: 0 0 30px rgba(59, 130, 246, 0.4);
        }

        /* 入场动画 */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .anim-1 { animation: fadeInUp 0.8s ease-out 0.2s both; }
        .anim-2 { animation: fadeInUp 0.8s ease-out 0.5s both; }
        .anim-3 { animation: fadeInUp 0.8s ease-out 0.8s both; }
        .anim-4 { animation: fadeInUp 0.8s ease-out 1.1s both; }

        /* 标题辉光 */
        .title-glow {
            text-shadow: 0 0 40px rgba(59,130,246,0.6), 0 0 80px rgba(59,130,246,0.3), 0 2px 10px rgba(0,0,0,0.8);
        }

        .status-dot {
            width: 10px; height: 10px;
            border-radius: 50%;
            display: inline-block;
        }
        .status-online, .status-maintenance {
            background: #22c55e;
            box-shadow: 0 0 8px #22c55e;
            animation: pulse-green 2s ease-in-out infinite;
        }
        .status-offline {
            background: #ef4444;
            box-shadow: 0 0 8px #ef4444;
        }
        @keyframes pulse-green {
            0%, 100% { box-shadow: 0 0 8px #22c55e; }
            50% { box-shadow: 0 0 16px #22c55e, 0 0 24px rgba(34,197,94,0.3); }
        }

        /* 骨架屏 */
        @keyframes shimmer {
            0% { background-position: -400px 0; }
            100% { background-position: 400px 0; }
        }
        .skeleton {
            background: linear-gradient(90deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.08) 50%, rgba(255,255,255,0.03) 100%);
            background-size: 400px 100%;
            animation: shimmer 1.5s infinite;
            border-radius: 4px;
        }

        /* 按钮悬停效果 */
        .btn-primary {
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 40px rgba(59,130,246,0.5), 0 8px 25px rgba(59,130,246,0.3);
        }
        .btn-secondary {
            transition: all 0.3s ease;
        }
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 20px rgba(255,255,255,0.1);
        }

        /* === 星空背景 === */
        #starfield {
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, #0a0e27 0%, #0c1445 30%, #1a237e 60%, #0d1137 100%);
            transition: opacity 1s ease-out;
            z-index: 1;
        }
        #starfield.fade-out {
            opacity: 0;
        }

        /* 星星层 - 通过 box-shadow 批量绘制 */
        .stars-layer {
            position: absolute;
            inset: 0;
            overflow: hidden;
        }
        .stars-sm, .stars-md, .stars-lg {
            position: absolute;
            top: 0;
            left: 0;
            width: 1px;
            height: 1px;
            border-radius: 50%;
            background: transparent;
        }
        .stars-sm {
            box-shadow:
                25vw 10vh 0 0 rgba(255,255,255,0.5),
                50vw 20vh 0 0 rgba(255,255,255,0.4),
                75vw 5vh 0 0 rgba(255,255,255,0.6),
                10vw 35vh 0 0 rgba(255,255,255,0.3),
                90vw 45vh 0 0 rgba(255,255,255,0.5),
                30vw 55vh 0 0 rgba(255,255,255,0.4),
                60vw 65vh 0 0 rgba(255,255,255,0.6),
                85vw 75vh 0 0 rgba(255,255,255,0.3),
                15vw 85vh 0 0 rgba(255,255,255,0.5),
                45vw 90vh 0 0 rgba(255,255,255,0.4),
                5vw 50vh 0 0 rgba(255,255,255,0.3),
                95vw 25vh 0 0 rgba(255,255,255,0.5),
                40vw 40vh 0 0 rgba(255,255,255,0.4),
                70vw 30vh 0 0 rgba(255,255,255,0.6),
                20vw 70vh 0 0 rgba(255,255,255,0.3),
                55vw 80vh 0 0 rgba(255,255,255,0.5),
                80vw 15vh 0 0 rgba(255,255,255,0.4),
                35vw 95vh 0 0 rgba(255,255,255,0.3),
                65vw 50vh 0 0 rgba(255,255,255,0.6),
                12vw 60vh 0 0 rgba(255,255,255,0.4),
                88vw 88vh 0 0 rgba(255,255,255,0.3),
                42vw 12vh 0 0 rgba(255,255,255,0.5),
                72vw 92vh 0 0 rgba(255,255,255,0.4),
                8vw 22vh 0 0 rgba(255,255,255,0.6),
                52vw 42vh 0 0 rgba(255,255,255,0.3),
                33vw 8vh 0 0 rgba(255,255,255,0.5),
                78vw 58vh 0 0 rgba(255,255,255,0.4),
                18vw 48vh 0 0 rgba(255,255,255,0.6),
                62vw 78vh 0 0 rgba(255,255,255,0.3),
                92vw 62vh 0 0 rgba(255,255,255,0.5);
        }
        .stars-md {
            box-shadow:
                15vw 15vh 0 0.5px rgba(255,255,255,0.7),
                48vw 32vh 0 0.5px rgba(255,255,255,0.6),
                82vw 52vh 0 0.5px rgba(255,255,255,0.8),
                28vw 72vh 0 0.5px rgba(255,255,255,0.6),
                68vw 18vh 0 0.5px rgba(255,255,255,0.7),
                38vw 88vh 0 0.5px rgba(255,255,255,0.6),
                92vw 38vh 0 0.5px rgba(255,255,255,0.8),
                5vw 78vh 0 0.5px rgba(255,255,255,0.6),
                58vw 58vh 0 0.5px rgba(255,255,255,0.7),
                75vw 85vh 0 0.5px rgba(255,255,255,0.6),
                22vw 28vh 0 0.5px rgba(255,255,255,0.7),
                88vw 8vh 0 0.5px rgba(255,255,255,0.6),
                42vw 62vh 0 0.5px rgba(255,255,255,0.8),
                3vw 92vh 0 0.5px rgba(255,255,255,0.6),
                65vw 42vh 0 0.5px rgba(255,255,255,0.7);
            animation: twinkle-md 4s ease-in-out infinite alternate;
        }
        .stars-lg {
            box-shadow:
                20vw 25vh 0 1px rgba(200,220,255,0.9),
                55vw 45vh 0 1px rgba(200,220,255,0.8),
                80vw 70vh 0 1px rgba(200,220,255,0.9),
                35vw 15vh 0 1px rgba(200,220,255,0.7),
                70vw 90vh 0 1px rgba(200,220,255,0.8),
                10vw 55vh 0 1px rgba(200,220,255,0.9),
                90vw 30vh 0 1px rgba(200,220,255,0.7),
                45vw 75vh 0 1px rgba(200,220,255,0.8);
            animation: twinkle-lg 3s ease-in-out infinite alternate;
        }

        @keyframes twinkle-md {
            0%   { opacity: 1; }
            50%  { opacity: 0.4; }
            100% { opacity: 1; }
        }
        @keyframes twinkle-lg {
            0%   { opacity: 0.6; }
            50%  { opacity: 1; }
            100% { opacity: 0.6; }
        }

        /* 流星 */
        .shooting-star {
            position: absolute;
            width: 80px;
            height: 1px;
            background: linear-gradient(90deg, rgba(255,255,255,0.9), transparent);
            border-radius: 1px;
            opacity: 0;
            filter: drop-shadow(0 0 3px rgba(150,180,255,0.6));
        }
        .shooting-star::before {
            content: '';
            position: absolute;
            top: -1px;
            left: 0;
            width: 4px;
            height: 3px;
            border-radius: 50%;
            background: rgba(255,255,255,0.9);
            box-shadow: 0 0 6px 2px rgba(150,180,255,0.5);
        }

        .shooting-star-1 {
            top: 12%;
            left: -80px;
            transform: rotate(25deg);
            animation: shoot1 6s ease-in 1s infinite;
        }
        .shooting-star-2 {
            top: 35%;
            left: -80px;
            transform: rotate(35deg);
            animation: shoot2 8s ease-in 4s infinite;
        }
        .shooting-star-3 {
            top: 8%;
            right: -80px;
            left: auto;
            transform: rotate(-20deg) scaleX(-1);
            animation: shoot3 10s ease-in 7s infinite;
        }

        @keyframes shoot1 {
            0%   { opacity: 0; transform: rotate(25deg) translateX(0); }
            2%   { opacity: 1; }
            15%  { opacity: 0; transform: rotate(25deg) translateX(calc(100vw + 160px)); }
            100% { opacity: 0; transform: rotate(25deg) translateX(calc(100vw + 160px)); }
        }
        @keyframes shoot2 {
            0%   { opacity: 0; transform: rotate(35deg) translateX(0); }
            2%   { opacity: 1; }
            12%  { opacity: 0; transform: rotate(35deg) translateX(calc(100vw + 160px)); }
            100% { opacity: 0; transform: rotate(35deg) translateX(calc(100vw + 160px)); }
        }
        @keyframes shoot3 {
            0%   { opacity: 0; transform: rotate(-20deg) scaleX(-1) translateX(0); }
            2%   { opacity: 1; }
            10%  { opacity: 0; transform: rotate(-20deg) scaleX(-1) translateX(calc(100vw + 160px)); }
            100% { opacity: 0; transform: rotate(-20deg) scaleX(-1) translateX(calc(100vw + 160px)); }
        }

        /* 视频初始隐藏 */
        #bg-video {
            opacity: 0;
            transition: opacity 1s ease-in;
        }
        #bg-video.video-ready {
            opacity: 1;
        }
    </style>
</head>
<body class="eve-bg min-h-screen text-white overflow-x-hidden">

    <!-- 背景层 -->
    <div id="bg-container" class="fixed inset-0 z-0 overflow-hidden">
        <!-- 星空效果（视频加载前显示） -->
        <div id="starfield">
            <div class="stars-layer">
                <div class="stars-sm"></div>
                <div class="stars-md"></div>
                <div class="stars-lg"></div>
            </div>
            <div class="shooting-star shooting-star-1"></div>
            <div class="shooting-star shooting-star-2"></div>
            <div class="shooting-star shooting-star-3"></div>
        </div>

        <video autoplay muted loop playsinline class="absolute inset-0 w-full h-full object-cover" id="bg-video">
            <source src="/eve-esi-bg.webm" type="video/webm">
        </video>
        <!-- 视频上方叠加半透明暗色层，确保文字可读 -->
        <div class="absolute inset-0 bg-black/55"></div>
    </div>

    <!-- 前景内容层 -->
    <div class="relative z-10 min-h-screen flex flex-col items-center justify-center px-4 py-12">

        <!-- 品牌标题 -->
        <div class="text-center mb-12 anim-1">
            <h1 class="text-5xl md:text-7xl font-bold tracking-tight title-glow mb-3">
                Tus Esi System
            </h1>
            <div class="flex items-center justify-center gap-3 mb-4">
                <span class="bg-blue-500/20 text-blue-300 px-4 py-1 rounded-full text-sm font-medium border border-blue-500/30">Beta</span>
            </div>
            <p class="text-lg text-blue-200/90">EVE Online 数据工具平台</p>
        </div>

        <!-- 服务器状态 -->
        <div class="w-full max-w-4xl mb-14 anim-2">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5" id="server-cards">
                <!-- 晨曦 -->
                <div class="bg-black/40 backdrop-blur-lg rounded-2xl p-5 border border-white/[0.12] hover:border-white/20 transition-colors" id="card-0">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <div class="text-white font-semibold">晨曦</div>
                            <div class="text-xs text-blue-300/70">Serenity</div>
                        </div>
                        <div class="flex items-center gap-2" id="status-0">
                            <div class="skeleton w-10 h-3 rounded"></div>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-blue-300/70">在线玩家</span>
                            <span class="text-lg font-bold text-white" id="players-0"><span class="skeleton inline-block h-5 w-16 rounded"></span></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-blue-300/70">启动时间</span>
                            <span class="text-xs text-blue-200/90" id="starttime-0"><span class="skeleton inline-block h-3 w-24 rounded"></span></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-blue-300/70">版本</span>
                            <span class="text-xs text-blue-200/70 font-mono" id="version-0"><span class="skeleton inline-block h-3 w-16 rounded"></span></span>
                        </div>
                    </div>
                </div>

                <!-- 曙光 -->
                <div class="bg-black/40 backdrop-blur-lg rounded-2xl p-5 border border-white/[0.12] hover:border-white/20 transition-colors" id="card-1">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <div class="text-white font-semibold">曙光</div>
                            <div class="text-xs text-blue-300/70">Infinity</div>
                        </div>
                        <div class="flex items-center gap-2" id="status-1">
                            <div class="skeleton w-10 h-3 rounded"></div>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-blue-300/70">在线玩家</span>
                            <span class="text-lg font-bold text-white" id="players-1"><span class="skeleton inline-block h-5 w-16 rounded"></span></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-blue-300/70">启动时间</span>
                            <span class="text-xs text-blue-200/90" id="starttime-1"><span class="skeleton inline-block h-3 w-24 rounded"></span></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-blue-300/70">版本</span>
                            <span class="text-xs text-blue-200/70 font-mono" id="version-1"><span class="skeleton inline-block h-3 w-16 rounded"></span></span>
                        </div>
                    </div>
                </div>

                <!-- 欧服 -->
                <div class="bg-black/40 backdrop-blur-lg rounded-2xl p-5 border border-white/[0.12] hover:border-white/20 transition-colors" id="card-2">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <div class="text-white font-semibold">欧服</div>
                            <div class="text-xs text-blue-300/70">Tranquility</div>
                        </div>
                        <div class="flex items-center gap-2" id="status-2">
                            <div class="skeleton w-10 h-3 rounded"></div>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-blue-300/70">在线玩家</span>
                            <span class="text-lg font-bold text-white" id="players-2"><span class="skeleton inline-block h-5 w-16 rounded"></span></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-blue-300/70">启动时间</span>
                            <span class="text-xs text-blue-200/90" id="starttime-2"><span class="skeleton inline-block h-3 w-24 rounded"></span></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-blue-300/70">版本</span>
                            <span class="text-xs text-blue-200/70 font-mono" id="version-2"><span class="skeleton inline-block h-3 w-16 rounded"></span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 入口按钮 -->
        <div class="flex flex-col sm:flex-row gap-4 sm:gap-6 items-center justify-center anim-3">
            <div class="text-center min-w-[200px]">
                <a href="{{ route('guest.dashboard') }}" class="btn-secondary bg-black/40 backdrop-blur-lg border border-white/20 text-blue-100 font-semibold px-8 py-3 rounded-xl text-base w-full block">
                    无授权使用
                </a>
                <p class="text-xs text-blue-300/60 mt-2">使用无需登录的公开功能</p>
            </div>
            <div class="text-center min-w-[200px]">
                <a href="{{ route('auth.guide') }}" class="btn-primary bg-blue-600 hover:bg-blue-500 text-white font-semibold px-8 py-3 rounded-xl text-base w-full block eve-glow">
                    授权使用
                </a>
                <p class="text-xs text-blue-300/60 mt-2">通过 EVE Online 官方授权安全登录</p>
            </div>
        </div>

        <!-- 使用指南入口 -->
        <div class="mt-6 text-center anim-3">
            <a href="{{ route('guide') }}" class="text-blue-300/70 hover:text-blue-200 text-sm transition-colors inline-flex items-center gap-1">
                📖 查看使用指南
            </a>
        </div>

        <!-- 页脚 -->
        <footer class="mt-auto pt-16 text-center text-blue-300/50 text-xs anim-4">
            <p>Tus Esi System &middot; 数据来源于 EVE ESI API &middot; 非官方项目</p>
            <p class="mt-2">
                <a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer" class="hover:text-blue-300/80 transition-colors">苏ICP备17024259号-9</a>
                <span class="mx-2">|</span>
                <a href="http://www.beian.gov.cn/portal/registerSystemInfo?recordcode=32098202000512" target="_blank" rel="noopener noreferrer" class="hover:text-blue-300/80 transition-colors">苏公网安备32098202000512号</a>
            </p>
        </footer>
    </div>

    <!-- 功能开发中模态框 -->
    <div id="coming-soon-modal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="hideComingSoon()"></div>
        <div class="relative z-10 flex items-center justify-center min-h-screen px-4">
            <div class="bg-slate-900/90 backdrop-blur-xl border border-white/10 rounded-2xl p-8 max-w-sm w-full text-center">
                <div class="text-5xl mb-4">🚧</div>
                <h3 class="text-xl font-semibold mb-2">功能开发中</h3>
                <p class="text-blue-200/60 mb-6">该功能正在积极开发中，敬请期待!</p>
                <button onclick="hideComingSoon()" class="bg-white/10 hover:bg-white/20 px-6 py-2 rounded-lg transition-colors text-sm">
                    知道了
                </button>
            </div>
        </div>
    </div>

    <script>
        // === 服务器状态 ===
        function formatNumber(n) {
            return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        function formatStartTime(isoStr) {
            if (!isoStr) return '-';
            var d = new Date(isoStr);
            return d.getFullYear() + '-' +
                String(d.getMonth()+1).padStart(2,'0') + '-' +
                String(d.getDate()).padStart(2,'0') + ' ' +
                String(d.getHours()).padStart(2,'0') + ':' +
                String(d.getMinutes()).padStart(2,'0');
        }

        function loadServerStatus() {
            fetch('/api/public/server-status')
                .then(function(r) { return r.json(); })
                .then(function(servers) {
                    servers.forEach(function(srv, i) {
                        var statusEl = document.getElementById('status-' + i);
                        var playersEl = document.getElementById('players-' + i);
                        var starttimeEl = document.getElementById('starttime-' + i);
                        var versionEl = document.getElementById('version-' + i);

                        if (srv.is_online) {
                            // 根据 is_maintenance 显示不同状态
                            var statusText = '在线';
                            if (srv.is_maintenance && srv.players === 0) {
                                statusText = '调试中';
                                statusEl.innerHTML = '<span class="status-dot status-maintenance"></span><span class="text-xs text-green-400">' + statusText + '</span>';
                            } else {
                                statusEl.innerHTML = '<span class="status-dot status-online"></span><span class="text-xs text-green-400">' + statusText + '</span>';
                            }
                            playersEl.textContent = formatNumber(srv.players);
                            starttimeEl.textContent = formatStartTime(srv.start_time);
                            versionEl.textContent = srv.server_version || '-';
                        } else {
                            statusEl.innerHTML = '<span class="status-dot status-offline"></span><span class="text-xs text-red-400">离线</span>';
                            playersEl.innerHTML = '<span class="text-white/30">-</span>';
                            starttimeEl.innerHTML = '<span class="text-white/30">-</span>';
                            versionEl.innerHTML = '<span class="text-white/30">-</span>';
                        }
                    });
                })
                .catch(function() {
                    for (var i = 0; i < 3; i++) {
                        document.getElementById('status-' + i).innerHTML = '<span class="status-dot status-offline"></span><span class="text-xs text-red-400/60">无法连接</span>';
                        document.getElementById('players-' + i).innerHTML = '<span class="text-white/30">-</span>';
                        document.getElementById('starttime-' + i).innerHTML = '<span class="text-white/30">-</span>';
                        document.getElementById('version-' + i).innerHTML = '<span class="text-white/30">-</span>';
                    }
                });
        }

        loadServerStatus();
        setInterval(loadServerStatus, 60000);

        // === 星空 → 视频过渡 ===
        var bgVideo = document.getElementById('bg-video');
        var starfield = document.getElementById('starfield');
        if (bgVideo) {
            bgVideo.addEventListener('canplaythrough', function() {
                bgVideo.classList.add('video-ready');
                if (starfield) starfield.classList.add('fade-out');
            });
            // 如果视频已经加载完毕（缓存命中）
            if (bgVideo.readyState >= 4) {
                bgVideo.classList.add('video-ready');
                if (starfield) starfield.classList.add('fade-out');
            }
        }

        // === 模态框 ===
        function showComingSoon() {
            document.getElementById('coming-soon-modal').classList.remove('hidden');
        }
        function hideComingSoon() {
            document.getElementById('coming-soon-modal').classList.add('hidden');
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') hideComingSoon();
        });
    </script>
</body>
</html>
