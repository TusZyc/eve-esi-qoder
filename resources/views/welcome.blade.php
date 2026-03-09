<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} - EVE ESI 管理平台</title>
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
        <header class="text-center mb-12">
            <h1 class="text-5xl font-bold mb-4 eve-glow">🚀 EVE ESI</h1>
            <p class="text-xl text-blue-200">国服 ESI 管理平台</p>
        </header>

        <!-- 主内容 -->
        <main class="max-w-2xl mx-auto">
            <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-8 eve-glow">
                <h2 class="text-2xl font-semibold mb-6 text-center">欢迎指挥官</h2>
                
                <div class="space-y-4">
                    <p class="text-blue-100 text-center mb-8">
                        绑定你的 EVE 角色，管理技能、资产、市场订单
                    </p>

                    @if(session('error'))
                        <div class="bg-red-500/20 border border-red-500 text-red-200 px-4 py-3 rounded-lg mb-4">
                            ❌ {{ session('error') }}
                        </div>
                    @endif

                    @if(session('success'))
                        <div class="bg-green-500/20 border border-green-500 text-green-200 px-4 py-3 rounded-lg mb-4">
                            ✅ {{ session('success') }}
                        </div>
                    @endif

                    <!-- 登录按钮 -->
                    <div class="text-center">
                        <a href="{{ route('auth.login') }}" 
                           class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold px-8 py-4 rounded-lg transition-all eve-glow hover:scale-105">
                            🔐 使用 EVE 账号登录
                        </a>
                        <p class="text-xs text-blue-300 mt-4">
                            点击后将跳转到网易通行证授权页面
                        </p>
                    </div>

                    <div class="text-center mt-6">
                        <p class="text-sm text-blue-300">
                            通过网易 OAuth2 安全认证 · 仅读取公开数据
                        </p>
                    </div>
                </div>
            </div>

            <!-- 功能介绍 -->
            <div class="grid md:grid-cols-3 gap-6 mt-8">
                <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 text-center">
                    <div class="text-4xl mb-3">📊</div>
                    <h3 class="font-semibold mb-2">技能监控</h3>
                    <p class="text-sm text-blue-200">实时查看技能队列和训练进度</p>
                </div>

                <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 text-center">
                    <div class="text-4xl mb-3">💰</div>
                    <h3 class="font-semibold mb-2">资产管理</h3>
                    <p class="text-sm text-blue-200">统计你的舰船、装备和 ISK</p>
                </div>

                <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 text-center">
                    <div class="text-4xl mb-3">📈</div>
                    <h3 class="font-semibold mb-2">市场订单</h3>
                    <p class="text-sm text-blue-200">追踪买卖挂单和交易记录</p>
                </div>
            </div>
        </main>

        <!-- 页脚 -->
        <footer class="text-center mt-16 text-blue-300 text-sm">
            <p>EVE Online 国服 ESI 管理平台</p>
            <p class="mt-2">数据来源于 EVE ESI API · 非官方项目</p>
        </footer>
    </div>
</body>
</html>
