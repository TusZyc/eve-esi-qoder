# Tus Esi System (Beta)

基于 Laravel 的 EVE Online 国服 ESI 数据工具平台

## 功能概览

### 首页
- eve.webm 视频背景沉浸式首页
- 实时服务器状态面板：晨曦(Serenity) / 曙光(Infinity) / 欧服(Tranquility)
- 显示在线人数、启动时间、版本号，60 秒自动刷新

### OAuth2 认证
- 国服 OAuth2 授权流程（网易通行证）
- 3V 完整 73 个 ESI 权限（含克隆体/植入体读取权限）
- Refresh Token 自动刷新（AutoRefreshEveToken 中间件）

### 仪表盘
- 异步数据加载 + 骨架屏
- 服务器状态、角色信息、技能概览、在线状态

### 技能系统
- 全量 673 个技能按分组展示，区分已学/未学（已过滤虚构技能分类）
- 技能队列实时监控（训练中/等待中/已完成）
- 异步加载架构：SkillDataController 提供 3 个 API 端点

### 资产管理
- 两步加载：先加载位置列表，再按需加载物品详情
- 搜索功能：按物品名称搜索，结果按位置分组
- 舰船机库/物品机库自动分类
- 树形展示：容器内嵌套物品支持展开/折叠

### 角色信息
- 四标签切换界面：角色描述 / 属性&植入体 / 克隆体 / 雇佣历史
- 角色描述：完整 EVE 富文本解码（Python unicode 转义、HTML 实体、hex 序列、ARGB 颜色转 CSS RGB）
- 属性&植入体：五大属性值 + 当前植入体列表，异步加载
- 克隆体：跳跃克隆体位置与植入体，空间站名称逐段中文翻译（星系名 + 军团名 + 设施类型映射）
- 雇佣历史：军团历史含起止时间与天数统计

### KM（击杀记录）查询
- 数据源：beta.ceve-market.org API，自定义 Protobuf 解码器
- 高级搜索：角色/军团/联盟/舰船/星系多维度
- 位置搜索：中文星系名模糊匹配自动补全
- 舰船搜索：支持具体舰船和舰船类别，自动补全
- 时间范围：精确到秒（datetime-local），支持仅时间条件搜索
- KM 详情弹窗：参与者列表、损失装配、伤害统计

### 市场查询
- 公开访问，无需登录
- 市场分组树 + 物品搜索
- MarketService 三级缓存策略

### 本地数据服务
- 离线物品/星系/空间站中文名称数据库（约 36,800 条，含 8,534 个星系中文名）
- 数据来源 ceve-market.org + ESI 批量抓取，Python 脚本自动更新

## 技术栈

| 层级 | 技术 |
|------|------|
| 后端 | Laravel 10 + PHP 8.2 |
| 前端 | Blade + TailwindCSS (CDN) |
| 数据库 | SQLite |
| 缓存 | Redis（三级缓存策略） |
| 部署 | Docker + Docker Compose |
| 数据 | Python 3 + openpyxl |

## 部署

### 环境要求

- Docker 20.10+
- Docker Compose 2.0+

### 启动

```bash
git clone https://github.com/TusZyc/eve-esi-qoder.git
cd eve-esi-qoder

cp .env.example .env

docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

### ESI 配置

```env
ESI_BASE_URL=https://ali-esi.evepc.163.com/latest/
ESI_OAUTH_URL=https://login.evepc.163.com/v2/oauth/
ESI_CLIENT_ID=your_client_id
```

## API 端点

### 公开（无需认证）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/public/server-status | 三服务器状态 |
| GET | /api/public/market/* | 市场数据 |

### 需认证（/api/dashboard/）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /server-status | 服务器状态 |
| GET | /character-info | 角色信息 |
| GET | /character-location | 角色位置 |
| GET | /character-online | 在线状态 |
| GET | /character/attributes | 角色属性 |
| GET | /character/implants | 当前植入体 |
| GET | /character/clones | 克隆体信息 |
| GET | /character/corphistory | 雇佣历史 |
| GET | /skills | 技能概览（仪表盘用） |
| GET | /skill-queue | 技能队列（仪表盘用） |
| GET | /skills/overview | 技能总览 |
| GET | /skills/queue | 技能队列详情 |
| GET | /skills/groups | 全量技能分组 |
| GET | /assets/locations | 资产位置列表 |
| GET | /assets/location/{id} | 位置物品详情 |
| GET | /assets/search?q= | 资产搜索 |

### KM 查询（/api/killmails/）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /autocomplete | 自动补全（角色/军团/联盟/舰船/星系） |
| GET | /advanced-search | 高级搜索 |

## 项目结构

```
eve-esi/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/              # 异步数据 API
│   │   │   │   ├── AssetDataController.php
│   │   │   │   ├── DashboardDataController.php
│   │   │   │   ├── KillmailController.php
│   │   │   │   ├── MarketDataController.php
│   │   │   │   ├── ServerStatusController.php
│   │   │   │   └── SkillDataController.php
│   │   │   ├── AuthController.php
│   │   │   ├── CharacterController.php  # 角色信息（属性/植入体/克隆体/雇佣历史）
│   │   │   ├── DashboardController.php
│   │   │   ├── AssetController.php
│   │   │   ├── MarketController.php
│   │   │   └── SkillController.php
│   │   └── Middleware/
│   │       └── AutoRefreshEveToken.php
│   ├── Services/
│   │   ├── EveDataService.php       # 本地数据服务
│   │   ├── KillmailService.php      # KM 查询 + Protobuf 解码
│   │   ├── MarketService.php        # 市场数据服务
│   │   └── TokenRefreshService.php
│   └── Helpers/
│       └── EveHelper.php            # 静态门面
├── config/
│   └── esi.php                      # ESI 配置（含 OAuth 权限列表）
├── data/
│   ├── items.json                   # 物品/星系中文名（~36,800 条）
│   └── eve_station_systems.json     # 空间站→星系映射
├── resources/views/
│   ├── welcome.blade.php            # 首页（视频背景）
│   ├── dashboard.blade.php          # 仪表盘
│   ├── skills/index.blade.php       # 技能页
│   ├── assets/index.blade.php       # 资产页
│   ├── characters/index.blade.php   # 角色信息页（四标签）
│   └── killmails/index.blade.php    # KM 查询页
├── routes/web.php
├── scripts/update_evedata.py        # 数据更新脚本
├── docker/
│   ├── app/Dockerfile
│   └── nginx/default.conf
└── docker-compose.yml
```

## 相关资源

- [EVE 国服 API 文档](https://ali-esi.evepc.163.com/ui)
- [EVE ESI 规范](https://esi.evetech.net/ui/)
- [Laravel 文档](https://laravel.com/docs)

## 许可证

MIT License

---

*最后更新：2026-03-15*
