<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>使用指南 - Tus Esi System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* 基础背景 */
        .eve-bg {
            background: linear-gradient(135deg, #0a0f1a 0%, #0c1445 30%, #111827 60%, #0d1137 100%);
        }

        /* 玻璃态卡片 */
        .glass-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.05), rgba(255,255,255,0.02));
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.08);
            transition: all 0.3s ease;
        }
        .glass-card:hover {
            transform: translateY(-4px);
            border-color: rgba(59,130,246,0.3);
            box-shadow: 0 8px 32px rgba(59,130,246,0.15);
        }

        /* 入场动画 */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .anim-1 { animation: fadeInUp 0.6s ease-out 0.1s both; }
        .anim-2 { animation: fadeInUp 0.6s ease-out 0.2s both; }
        .anim-3 { animation: fadeInUp 0.6s ease-out 0.3s both; }
        .anim-4 { animation: fadeInUp 0.6s ease-out 0.4s both; }

        /* 标题辉光 */
        .title-glow {
            text-shadow: 0 0 40px rgba(59,130,246,0.6), 0 0 80px rgba(59,130,246,0.3);
        }

        /* 分隔线样式 */
        .section-divider {
            background: linear-gradient(90deg, transparent, rgba(59,130,246,0.3), transparent);
        }

        /* 按钮效果 */
        .btn-enter {
            transition: all 0.3s ease;
        }
        .btn-enter:hover {
            transform: translateX(4px);
            color: #60a5fa;
        }

        /* 主按钮 */
        .btn-primary {
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 30px rgba(59,130,246,0.4);
        }

        /* 步骤卡片 */
        .step-card {
            position: relative;
        }
        .step-card::before {
            content: '';
            position: absolute;
            left: 50%;
            bottom: -20px;
            width: 2px;
            height: 20px;
            background: rgba(59,130,246,0.3);
        }
        .step-card:last-child::before {
            display: none;
        }
    </style>
</head>
<body class="eve-bg min-h-screen text-white">

    <!-- 顶部导航 -->
    <nav class="fixed top-0 left-0 right-0 z-50 bg-black/40 backdrop-blur-lg border-b border-white/[0.08]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-14">
                <a href="{{ route('home') }}" class="flex items-center gap-2 text-blue-300 hover:text-blue-200 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    <span>返回首页</span>
                </a>
                <a href="{{ route('auth.guide') }}" class="bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                    授权登录
                </a>
            </div>
        </div>
    </nav>

    <!-- 主内容 -->
    <main class="pt-20 pb-16 px-4 sm:px-6 lg:px-8">
        <div class="max-w-6xl mx-auto">

            <!-- 大标题区 -->
            <div class="text-center mb-16 anim-1">
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold tracking-tight title-glow mb-4">
                    Tus Esi System 使用指南
                </h1>
                <p class="text-lg md:text-xl text-blue-200/80 max-w-2xl mx-auto">
                    EVE Online 数据工具平台 - 让你的新伊甸之旅更加高效
                </p>
            </div>

            <!-- 无需授权功能区 -->
            <section class="mb-16 anim-2">
                <div class="flex items-center gap-4 mb-8">
                    <div class="h-px flex-1 section-divider"></div>
                    <h2 class="text-xl font-semibold text-blue-300 flex items-center gap-2 whitespace-nowrap">
                        <span>🔓</span> 无需授权即可使用
                    </h2>
                    <div class="h-px flex-1 section-divider"></div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- 市场中心 -->
                    <div class="glass-card rounded-2xl p-6">
                        <div class="flex items-start gap-4 mb-4">
                            <span class="text-3xl">📊</span>
                            <div>
                                <h3 class="text-xl font-semibold text-white mb-1">市场中心</h3>
                                <p class="text-blue-200/70 text-sm">实时市场数据查询</p>
                            </div>
                        </div>
                        <ul class="text-blue-100/80 text-sm space-y-2 mb-5">
                            <li class="flex items-start gap-2">
                                <span class="text-blue-400 mt-0.5">•</span>
                                <span>浏览全服物品市场订单，买单卖单一目了然</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-blue-400 mt-0.5">•</span>
                                <span>查看30天价格历史走势图，把握市场动态</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-blue-400 mt-0.5">•</span>
                                <span>支持按星域筛选和物品搜索，快速定位目标</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-green-400 mt-0.5">★</span>
                                <span>授权后还能查看自己的挂单状态</span>
                            </li>
                        </ul>
                        <a href="{{ route('market.index') }}" class="btn-enter inline-flex items-center gap-1 text-blue-400 text-sm font-medium">
                            进入市场中心
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>

                    <!-- KM 查询 -->
                    <div class="glass-card rounded-2xl p-6">
                        <div class="flex items-start gap-4 mb-4">
                            <span class="text-3xl">⚔️</span>
                            <div>
                                <h3 class="text-xl font-semibold text-white mb-1">KM 查询</h3>
                                <p class="text-blue-200/70 text-sm">击毁报告搜索与分析</p>
                            </div>
                        </div>
                        <ul class="text-blue-100/80 text-sm space-y-2 mb-5">
                            <li class="flex items-start gap-2">
                                <span class="text-blue-400 mt-0.5">•</span>
                                <span>高级搜索：按玩家/军团/联盟/舰船/星系/时间查询</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-blue-400 mt-0.5">•</span>
                                <span>直接查询：输入KM ID或ESI链接快速查看</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-blue-400 mt-0.5">•</span>
                                <span>详情展示：攻击者列表、装配详情、掉落物品</span>
                            </li>
                        </ul>
                        <a href="{{ route('killmails.index') }}" class="btn-enter inline-flex items-center gap-1 text-blue-400 text-sm font-medium">
                            进入KM查询
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>

                    <!-- 旗舰导航 -->
                    <div class="glass-card rounded-2xl p-6">
                        <div class="flex items-start gap-4 mb-4">
                            <span class="text-3xl">📍</span>
                            <div>
                                <h3 class="text-xl font-semibold text-white mb-1">旗舰导航</h3>
                                <p class="text-blue-200/70 text-sm">跳跃距离计算与路线规划</p>
                            </div>
                        </div>
                        <ul class="text-blue-100/80 text-sm space-y-2 mb-5">
                            <li class="flex items-start gap-2">
                                <span class="text-blue-400 mt-0.5">•</span>
                                <span>计算任意两个星系间的光年距离</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-blue-400 mt-0.5">•</span>
                                <span>查看指定舰船一跳可达的所有星系</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-blue-400 mt-0.5">•</span>
                                <span>路线规划：自动计算多跳最优路线</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-blue-400 mt-0.5">•</span>
                                <span>支持：战略货舰、长须鲸、黑隐特勤舰、航母、无畏舰、超级航母</span>
                            </li>
                        </ul>
                        <a href="{{ route('capital-nav.index') }}" class="btn-enter inline-flex items-center gap-1 text-blue-400 text-sm font-medium">
                            进入旗舰导航
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>

                    <!-- LP 商店 -->
                    <div class="glass-card rounded-2xl p-6">
                        <div class="flex items-start gap-4 mb-4">
                            <span class="text-3xl">💎</span>
                            <div>
                                <h3 class="text-xl font-semibold text-white mb-1">LP 商店</h3>
                                <p class="text-blue-200/70 text-sm">忠诚点数兑换利润计算</p>
                            </div>
                        </div>
                        <ul class="text-blue-100/80 text-sm space-y-2 mb-5">
                            <li class="flex items-start gap-2">
                                <span class="text-blue-400 mt-0.5">•</span>
                                <span>浏览各势力NPC军团的LP商店物品</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-blue-400 mt-0.5">•</span>
                                <span>自动计算LP兑换利润，一眼看出赚不赚</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-blue-400 mt-0.5">•</span>
                                <span>按利润排序，快速找到最赚钱的兑换项</span>
                            </li>
                        </ul>
                        <a href="{{ route('lp-store.index') }}" class="btn-enter inline-flex items-center gap-1 text-blue-400 text-sm font-medium">
                            进入LP商店
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </section>

            <!-- 授权功能区 -->
            <section class="mb-16 anim-3">
                <div class="flex items-center gap-4 mb-6">
                    <div class="h-px flex-1 section-divider"></div>
                    <h2 class="text-xl font-semibold text-green-400 flex items-center gap-2 whitespace-nowrap">
                        <span>🔐</span> 授权后解锁更多功能
                    </h2>
                    <div class="h-px flex-1 section-divider"></div>
                </div>

                <p class="text-center text-blue-200/70 text-sm mb-8 max-w-2xl mx-auto">
                    通过 EVE 官方 OAuth 授权，安全地获取你的角色数据。我们不会获取你的账号密码，授权随时可以撤销。
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <!-- 仪表盘 -->
                    <div class="glass-card rounded-2xl p-5">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="text-2xl">🏠</span>
                            <h3 class="text-lg font-semibold text-white">仪表盘</h3>
                        </div>
                        <p class="text-blue-100/70 text-sm">
                            一览角色基本信息和在线状态，实时查看服务器运行状态，快速概览技能训练进度和资产总览。
                        </p>
                    </div>

                    <!-- 技能队列 -->
                    <div class="glass-card rounded-2xl p-5">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="text-2xl">📚</span>
                            <h3 class="text-lg font-semibold text-white">技能队列</h3>
                        </div>
                        <p class="text-blue-100/70 text-sm">
                            查看当前技能训练队列和进度，按分类浏览所有已学技能，技能点数统计和分布一目了然。
                        </p>
                    </div>

                    <!-- 我的资产 -->
                    <div class="glass-card rounded-2xl p-5">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="text-2xl">📦</span>
                            <h3 class="text-lg font-semibold text-white">我的资产</h3>
                        </div>
                        <p class="text-blue-100/70 text-sm">
                            按位置分类展示所有资产，树形结构清晰展示物品层级，快速定位你的物品在哪个空间站。
                        </p>
                    </div>

                    <!-- 钱包 -->
                    <div class="glass-card rounded-2xl p-5">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="text-2xl">💰</span>
                            <h3 class="text-lg font-semibold text-white">钱包</h3>
                        </div>
                        <p class="text-blue-100/70 text-sm">
                            查看 ISK 余额和 LP 点数，浏览详细的交易记录和收支流水。
                        </p>
                    </div>

                    <!-- 联系人 -->
                    <div class="glass-card rounded-2xl p-5">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="text-2xl">👥</span>
                            <h3 class="text-lg font-semibold text-white">联系人</h3>
                        </div>
                        <p class="text-blue-100/70 text-sm">
                            查看你的联系人列表，按好友/红名/中立等分类管理。
                        </p>
                    </div>

                    <!-- 声望 -->
                    <div class="glass-card rounded-2xl p-5">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="text-2xl">⭐</span>
                            <h3 class="text-lg font-semibold text-white">声望</h3>
                        </div>
                        <p class="text-blue-100/70 text-sm">
                            查看与各势力、军团的声望关系，了解你在新伊甸的社交地位。
                        </p>
                    </div>

                    <!-- 邮件 -->
                    <div class="glass-card rounded-2xl p-5">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="text-2xl">📧</span>
                            <h3 class="text-lg font-semibold text-white">邮件</h3>
                        </div>
                        <p class="text-blue-100/70 text-sm">
                            查看角色邮件，按标签和群组分类筛选，支持查看发件人、收件人详情。
                        </p>
                    </div>

                    <!-- 合同 -->
                    <div class="glass-card rounded-2xl p-5">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="text-2xl">📝</span>
                            <h3 class="text-lg font-semibold text-white">合同</h3>
                        </div>
                        <p class="text-blue-100/70 text-sm">
                            浏览你的合同列表，查看合同状态和详细内容。
                        </p>
                    </div>

                    <!-- 装配 -->
                    <div class="glass-card rounded-2xl p-5">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="text-2xl">🔧</span>
                            <h3 class="text-lg font-semibold text-white">装配</h3>
                        </div>
                        <p class="text-blue-100/70 text-sm">
                            查看你保存的舰船装配方案，方便随时参考。
                        </p>
                    </div>

                    <!-- 击毁报告 -->
                    <div class="glass-card rounded-2xl p-5">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="text-2xl">💥</span>
                            <h3 class="text-lg font-semibold text-white">我的击毁报告</h3>
                        </div>
                        <p class="text-blue-100/70 text-sm">
                            查看你参与的所有击毁和损失记录，回顾战斗历程。
                        </p>
                    </div>

                    <!-- 角色管理 -->
                    <div class="glass-card rounded-2xl p-5">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="text-2xl">👤</span>
                            <h3 class="text-lg font-semibold text-white">角色管理</h3>
                        </div>
                        <p class="text-blue-100/70 text-sm">
                            管理多个已授权角色，随时切换查看不同角色的数据。
                        </p>
                    </div>

                    <!-- 提醒 -->
                    <div class="glass-card rounded-2xl p-5">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="text-2xl">🔔</span>
                            <h3 class="text-lg font-semibold text-white">提醒</h3>
                        </div>
                        <p class="text-blue-100/70 text-sm">
                            查看游戏内的各类通知和提醒消息。
                        </p>
                    </div>
                </div>
            </section>

            <!-- 如何开始 -->
            <section class="mb-16 anim-4">
                <div class="flex items-center gap-4 mb-8">
                    <div class="h-px flex-1 section-divider"></div>
                    <h2 class="text-xl font-semibold text-blue-300 whitespace-nowrap">如何开始？</h2>
                    <div class="h-px flex-1 section-divider"></div>
                </div>

                <div class="max-w-2xl mx-auto">
                    <div class="flex flex-col items-center gap-6 mb-10">
                        <!-- 步骤1 -->
                        <div class="step-card glass-card rounded-xl p-5 w-full text-center">
                            <div class="inline-flex items-center justify-center w-10 h-10 bg-blue-500/20 rounded-full mb-3">
                                <span class="text-blue-400 font-bold">1</span>
                            </div>
                            <h3 class="text-white font-medium mb-2">点击首页"授权使用"按钮</h3>
                            <p class="text-blue-200/60 text-sm">或点击本页顶部的"授权登录"</p>
                        </div>

                        <!-- 步骤2 -->
                        <div class="step-card glass-card rounded-xl p-5 w-full text-center">
                            <div class="inline-flex items-center justify-center w-10 h-10 bg-blue-500/20 rounded-full mb-3">
                                <span class="text-blue-400 font-bold">2</span>
                            </div>
                            <h3 class="text-white font-medium mb-2">跳转到 EVE 官方授权页面</h3>
                            <p class="text-blue-200/60 text-sm">使用你的 EVE 账号登录并确认授权</p>
                        </div>

                        <!-- 步骤3 -->
                        <div class="step-card glass-card rounded-xl p-5 w-full text-center">
                            <div class="inline-flex items-center justify-center w-10 h-10 bg-green-500/20 rounded-full mb-3">
                                <span class="text-green-400 font-bold">3</span>
                            </div>
                            <h3 class="text-white font-medium mb-2">授权完成，开始使用</h3>
                            <p class="text-blue-200/60 text-sm">自动返回本站，即可使用所有功能</p>
                        </div>
                    </div>

                    <!-- 安全说明 -->
                    <div class="glass-card rounded-xl p-6">
                        <h3 class="text-white font-semibold mb-4 flex items-center gap-2">
                            <span>🛡️</span> 安全说明
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="flex items-center gap-3 text-sm">
                                <span class="text-green-400">✓</span>
                                <span class="text-blue-100/80">使用 EVE 官方 OAuth2.0 协议</span>
                            </div>
                            <div class="flex items-center gap-3 text-sm">
                                <span class="text-green-400">✓</span>
                                <span class="text-blue-100/80">不获取账号密码</span>
                            </div>
                            <div class="flex items-center gap-3 text-sm">
                                <span class="text-green-400">✓</span>
                                <span class="text-blue-100/80">仅读取授权的数据</span>
                            </div>
                            <div class="flex items-center gap-3 text-sm">
                                <span class="text-green-400">✓</span>
                                <span class="text-blue-100/80">随时可在 EVE 官网撤销授权</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 底部 CTA -->
            <div class="text-center anim-4">
                <a href="{{ route('home') }}" class="btn-primary inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-500 text-white font-semibold px-8 py-3 rounded-xl">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    返回首页
                </a>
            </div>

        </div>
    </main>

    <!-- 页脚 -->
    <footer class="border-t border-white/[0.06] py-6 text-center text-blue-300/50 text-xs">
        <p>Tus Esi System · 数据来源于 EVE ESI API · 非官方项目</p>
    </footer>

</body>
</html>
