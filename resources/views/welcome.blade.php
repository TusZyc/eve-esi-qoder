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

        /* 星空层 - 小星星 */
        #stars-small, #stars-medium, #stars-large {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
        }
        #stars-small {
            background: transparent;
            animation: drift 60s linear infinite;
        }
        #stars-medium {
            background: transparent;
            animation: drift 120s linear infinite;
        }
        #stars-large {
            background: transparent;
            animation: drift 180s linear infinite;
        }
        @keyframes drift {
            from { transform: translateY(0); }
            to { transform: translateY(-2000px); }
        }

        /* 星云效果 */
        .nebula {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0;
            animation: nebulaFloat 20s ease-in-out infinite, fadeInSlow 3s ease-out forwards;
        }
        .nebula-1 {
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(59,130,246,0.08) 0%, transparent 70%);
            top: 10%; left: -5%;
            animation-delay: 0s;
        }
        .nebula-2 {
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(139,92,246,0.06) 0%, transparent 70%);
            top: 50%; right: -10%;
            animation-delay: -7s;
        }
        .nebula-3 {
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(6,182,212,0.05) 0%, transparent 70%);
            bottom: 5%; left: 30%;
            animation-delay: -13s;
        }
        @keyframes nebulaFloat {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -20px) scale(1.05); }
            66% { transform: translate(-20px, 15px) scale(0.95); }
        }
        @keyframes fadeInSlow {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* 流星 */
        .shooting-star {
            position: absolute;
            width: 100px;
            height: 1px;
            background: linear-gradient(90deg, rgba(255,255,255,0.8), transparent);
            transform: rotate(-35deg);
            animation: shoot 3s ease-in infinite;
            opacity: 0;
        }
        .shooting-star:nth-child(1) {
            top: 15%; left: 70%;
            animation-delay: 1s;
        }
        .shooting-star:nth-child(2) {
            top: 35%; left: 85%;
            animation-delay: 5s;
            width: 70px;
        }
        .shooting-star:nth-child(3) {
            top: 55%; left: 60%;
            animation-delay: 9s;
            width: 120px;
        }
        @keyframes shoot {
            0% { transform: rotate(-35deg) translateX(0); opacity: 0; }
            5% { opacity: 1; }
            20% { transform: rotate(-35deg) translateX(-300px); opacity: 0; }
            100% { opacity: 0; }
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
            text-shadow: 0 0 40px rgba(59,130,246,0.5), 0 0 80px rgba(59,130,246,0.2);
        }

        /* 状态灯 */
        .status-dot {
            width: 10px; height: 10px;
            border-radius: 50%;
            display: inline-block;
        }
        .status-online {
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
    </style>
</head>
<body class="eve-bg min-h-screen text-white overflow-x-hidden">

    <!-- 背景层 -->
    <div id="bg-container" class="fixed inset-0 z-0 overflow-hidden">
        <!-- 星空粒子层 (由 JS 生成 box-shadow) -->
        <div id="stars-small"></div>
        <div id="stars-medium"></div>
        <div id="stars-large"></div>
        <!-- 星云层 -->
        <div class="nebula nebula-1"></div>
        <div class="nebula nebula-2"></div>
        <div class="nebula nebula-3"></div>
        <!-- 流星 -->
        <div class="shooting-star"></div>
        <div class="shooting-star"></div>
        <div class="shooting-star"></div>
        <!-- [未来] <video src="bg.webm" autoplay muted loop playsinline class="absolute inset-0 w-full h-full object-cover"></video> -->
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
            <p class="text-lg text-blue-200/70">EVE Online 数据工具平台</p>
        </div>

        <!-- 服务器状态 -->
        <div class="w-full max-w-4xl mb-14 anim-2">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5" id="server-cards">
                <!-- 晨曦 -->
                <div class="bg-white/[0.06] backdrop-blur-md rounded-2xl p-5 border border-white/[0.08] hover:border-white/15 transition-colors" id="card-0">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <div class="text-white font-semibold">晨曦</div>
                            <div class="text-xs text-blue-300/60">Serenity</div>
                        </div>
                        <div class="flex items-center gap-2" id="status-0">
                            <div class="skeleton w-10 h-3 rounded"></div>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-blue-300/50">在线玩家</span>
                            <span class="text-lg font-bold text-white" id="players-0"><span class="skeleton inline-block h-5 w-16 rounded"></span></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-blue-300/50">启动时间</span>
                            <span class="text-xs text-blue-200/70" id="starttime-0"><span class="skeleton inline-block h-3 w-24 rounded"></span></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-blue-300/50">版本</span>
                            <span class="text-xs text-blue-200/50 font-mono" id="version-0"><span class="skeleton inline-block h-3 w-16 rounded"></span></span>
                        </div>
                    </div>
                </div>

                <!-- 曙光 -->
                <div class="bg-white/[0.06] backdrop-blur-md rounded-2xl p-5 border border-white/[0.08] hover:border-white/15 transition-colors" id="card-1">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <div class="text-white font-semibold">曙光</div>
                            <div class="text-xs text-blue-300/60">Infinity</div>
                        </div>
                        <div class="flex items-center gap-2" id="status-1">
                            <div class="skeleton w-10 h-3 rounded"></div>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-blue-300/50">在线玩家</span>
                            <span class="text-lg font-bold text-white" id="players-1"><span class="skeleton inline-block h-5 w-16 rounded"></span></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-blue-300/50">启动时间</span>
                            <span class="text-xs text-blue-200/70" id="starttime-1"><span class="skeleton inline-block h-3 w-24 rounded"></span></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-blue-300/50">版本</span>
                            <span class="text-xs text-blue-200/50 font-mono" id="version-1"><span class="skeleton inline-block h-3 w-16 rounded"></span></span>
                        </div>
                    </div>
                </div>

                <!-- 欧服 -->
                <div class="bg-white/[0.06] backdrop-blur-md rounded-2xl p-5 border border-white/[0.08] hover:border-white/15 transition-colors" id="card-2">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <div class="text-white font-semibold">欧服</div>
                            <div class="text-xs text-blue-300/60">Tranquility</div>
                        </div>
                        <div class="flex items-center gap-2" id="status-2">
                            <div class="skeleton w-10 h-3 rounded"></div>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-blue-300/50">在线玩家</span>
                            <span class="text-lg font-bold text-white" id="players-2"><span class="skeleton inline-block h-5 w-16 rounded"></span></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-blue-300/50">启动时间</span>
                            <span class="text-xs text-blue-200/70" id="starttime-2"><span class="skeleton inline-block h-3 w-24 rounded"></span></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-blue-300/50">版本</span>
                            <span class="text-xs text-blue-200/50 font-mono" id="version-2"><span class="skeleton inline-block h-3 w-16 rounded"></span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 入口按钮 -->
        <div class="flex flex-col sm:flex-row gap-6 sm:gap-8 anim-3">
            <div class="text-center">
                <button onclick="showComingSoon()" class="btn-secondary bg-white/[0.06] backdrop-blur-md border border-white/15 text-blue-100 font-semibold px-10 py-4 rounded-xl text-lg w-full">
                    无授权使用
                </button>
                <p class="text-xs text-blue-300/40 mt-3">使用无需登录的公开功能</p>
            </div>
            <div class="text-center">
                <a href="{{ route('auth.guide') }}" class="btn-primary bg-blue-600 hover:bg-blue-500 text-white font-semibold px-10 py-4 rounded-xl text-lg text-center eve-glow block">
                    授权使用
                </a>
                <p class="text-xs text-blue-300/40 mt-3">通过 EVE Online 官方授权安全登录</p>
            </div>
        </div>

        <!-- 页脚 -->
        <footer class="mt-auto pt-16 text-center text-blue-300/30 text-xs anim-4">
            <p>Tus Esi System &middot; 数据来源于 EVE ESI API &middot; 非官方项目</p>
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
        // === 星空生成 ===
        function generateStars(count, size) {
            var shadows = [];
            for (var i = 0; i < count; i++) {
                var x = Math.floor(Math.random() * 2000);
                var y = Math.floor(Math.random() * 4000);
                var opacity = (Math.random() * 0.7 + 0.3).toFixed(2);
                shadows.push(x + 'px ' + y + 'px 0 rgba(255,255,255,' + opacity + ')');
            }
            return shadows.join(',');
        }
        (function() {
            var s = document.getElementById('stars-small');
            var m = document.getElementById('stars-medium');
            var l = document.getElementById('stars-large');
            s.style.width = '1px';  s.style.height = '1px';  s.style.boxShadow = generateStars(600, 1);
            m.style.width = '2px';  m.style.height = '2px';  m.style.boxShadow = generateStars(200, 2);
            l.style.width = '3px';  l.style.height = '3px';  l.style.boxShadow = generateStars(80, 3);
        })();

        // === 服务器状态 ===
        function formatNumber(n) {
            return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        function formatUptime(seconds) {
            if (seconds <= 0) return '-';
            var d = Math.floor(seconds / 86400);
            var h = Math.floor((seconds % 86400) / 3600);
            var m = Math.floor((seconds % 3600) / 60);
            if (d > 0) return d + '天 ' + h + '小时';
            if (h > 0) return h + '小时 ' + m + '分';
            return m + '分钟';
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
                            statusEl.innerHTML = '<span class="status-dot status-online"></span><span class="text-xs text-green-400">在线</span>';
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
