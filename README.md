# Tus Esi System (Beta)

基于 Laravel 的 EVE Online 国服 ESI 数据工具平台

## 功能概览

### 首页
- eve.webm 视频背景沉浸式首页
- 实时服务器状态面板：晨曦(Serenity) / 曙光(Infinity) / 欧服(Tranquility)
- 显示在线人数、启动时间、版本号，60 秒自动刷新

### OAuth2 认证
- 国服 OAuth2 授权流程（网易通行证）
- 3V 完整 73 个 ESI 权限
- Refresh Token 自动刷新（AutoRefreshEveToken 中间件）

### 仪表盘
- 异步数据加载 + 骨架屏
- 服务器状态、角色信息、技能概览、在线状态

### 技能系统
- 全量 673 个技能按分组展示，区分已学/未学
- 技能队列实时监控（训练中/等待中/已完成）
- 异步加载架构：SkillDataController 提供 3 个 API 端点

### 资产管理
- 两步加载：先加载位置列表，再按需加载物品详情
- 搜索功能：按物品名称搜索，结果按位置分组
- 舰船机库/物品机库自动分类
- 树形展示：容器内嵌套物品支持展开/折叠

### 本地数据服务
- 离线物品/星系/空间站中文名称数据库（43,305 条）
- 数据来源 ceve-market.org，Python 脚本自动更新

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

### 需认证（/api/dashboard/）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /server-status | 服务器状态 |
| GET | /character-info | 角色信息 |
| GET | /character-location | 角色位置 |
| GET | /character-online | 在线状态 |
| GET | /skills | 技能概览（仪表盘用） |
| GET | /skill-queue | 技能队列（仪表盘用） |
| GET | /skills/overview | 技能总览 |
| GET | /skills/queue | 技能队列详情 |
| GET | /skills/groups | 全量技能分组 |
| GET | /assets/locations | 资产位置列表 |
| GET | /assets/location/{id} | 位置物品详情 |
| GET | /assets/search?q= | 资产搜索 |

## 项目结构

```
eve-esi/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/              # 异步数据 API
│   │   │   │   ├── AssetDataController.php
│   │   │   │   ├── DashboardDataController.php
│   │   │   │   ├── ServerStatusController.php
│   │   │   │   └── SkillDataController.php
│   │   │   ├── AuthController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── AssetController.php
│   │   │   └── SkillController.php
│   │   └── Middleware/
│   │       └── AutoRefreshEveToken.php
│   ├── Services/
│   │   ├── EveDataService.php    # 本地数据服务
│   │   └── TokenRefreshService.php
│   └── Helpers/
│       └── EveHelper.php         # 静态门面
├── config/
│   └── esi.php                   # ESI 配置
├── data/
│   ├── eve_names.json            # 物品/星系中文名
│   └── eve_station_systems.json  # 空间站→星系映射
├── resources/views/
│   ├── welcome.blade.php         # 首页（视频背景）
│   ├── dashboard.blade.php       # 仪表盘
│   ├── skills/index.blade.php    # 技能页
│   └── assets/index.blade.php    # 资产页
├── routes/web.php
├── scripts/update_evedata.py     # 数据更新脚本
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

*最后更新：2026-03-13*
