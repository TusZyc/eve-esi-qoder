# EVE ESI 国服工具站

基于 EVE Online 国服（Serenity）ESI API 的角色管理和市场工具。

**技术栈：** Laravel 10 + PHP 8.2 + Redis + Nginx + Docker

---

## 功能列表

### 角色功能（需登录）

| 功能 | 说明 |
|------|------|
| 🎯 仪表盘 | 角色概览、服务器状态、技能队列摘要 |
| 📊 技能队列 | 25 分组 673 技能全量显示，支持搜索筛选 |
| 📦 资产管理 | 懒加载架构、位置分类、搜索功能 |
| 👤 角色信息 | 属性/植入体/克隆体/雇佣历史 |
| 💰 钱包 | 余额、流水、交易记录、LP 点数、军团钱包 |
| 📍 书签 | 书签文件夹和坐标管理 |
| 👥 联系人 | 联系人列表和声望值 |
| 📜 合同 | 交换/拍卖/快递合同详情 |
| 🔧 装配 | 已保存装配清单 |
| ⚔️ 击杀记录 | 个人击杀/损失记录 |
| 🔔 通知 | 游戏内通知消息 |
| 📊 声望 | NPC/军团/联盟声望值 |

### 公开功能（无需登录）

| 功能 | 说明 |
|------|------|
| ⚔️ KM 查询 | 高级多维搜索、飞行员战绩统计 |
| 🏪 市场 | 分组浏览、订单表、价格历史图表 |
| 🏷️ LP 商店 | 势力商店比价、利润计算 |
| 🚀 旗舰导航 | 星系距离计算、一跳可达范围 |

### 管理功能

| 功能 | 说明 |
|------|------|
| 👑 管理后台 | 用户管理、日志查看、API 统计 |

---

## 技术架构

| 组件 | 技术 |
|------|------|
| 后端框架 | Laravel 10 (PHP 8.2) |
| 缓存 | Redis |
| Web 服务器 | Nginx |
| 容器化 | Docker Compose (app / nginx / redis) |
| 认证 | EVE SSO OAuth2 (国服 Serenity) |
| API | EVE ESI API (网易代理) |

---

## 项目结构

```
app/
├── Http/Controllers/       # 页面控制器
├── Http/Controllers/Api/   # API 控制器
├── Http/Controllers/Admin/ # 管理后台
├── Services/               # 业务服务层
├── Models/                 # 数据模型
└── Providers/              # 服务提供者

config/                     # 配置文件
routes/                     # 路由定义
resources/views/            # Blade 视图模板
data/                       # 静态数据文件 (星系/物品/势力)
docker/                     # Docker 配置
```

---

## 部署说明

### 1. 克隆项目

```bash
git clone <仓库地址>
cd eve-esi
```

### 2. 配置环境变量

```bash
cp .env.example .env
```

编辑 `.env` 文件，配置以下关键项：

```env
# EVE SSO 配置 (国服)
EVE_CLIENT_ID=<your_client_id>
EVE_CLIENT_SECRET=<your_client_secret>
EVE_CALLBACK_URL=https://your-domain/auth/callback

# Redis 配置
REDIS_HOST=redis
```

### 3. 启动服务

```bash
docker compose up -d
```

### 4. 初始化数据库

```bash
docker compose exec app php artisan migrate
```

---

## 环境要求

- PHP 8.2+
- Redis
- Docker & Docker Compose
- EVE SSO 客户端（需在网易 EVE 开发者平台申请）

---

## 开发信息

- **初始开发**：OpenClaw
- **接替开发**：Qoder (2026-03-12 起)
- **仓库**：eve-esi-qoder

---

## 许可证

MIT License
