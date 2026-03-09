# EVE ESI 管理平台

> 🚀 基于 Laravel 的 EVE Online 国服 ESI 管理工具  
> 📊 技能监控 · 资产管理 · 军团管理 · 数据统计

---

## 🎯 项目简介

**EVE ESI** 是一个专为 EVE Online 国服（晨曦服）玩家设计的管理平台，参考了欧服成熟的 SeAT 项目架构，针对国服 API 进行了适配和优化。

### 核心功能

- ✅ **OAuth2 认证** - 网易通行证登录，角色绑定
- ✅ **技能监控** - 技能队列、技能点统计
- ✅ **资产管理** - 角色资产、钱包余额
- ✅ **市场订单** - 买卖挂单追踪
- ✅ **军团管理** - 成员信息、权限控制
- ✅ **数据统计** - 可视化图表、报表导出

### 技术栈

| 层级 | 技术 |
|------|------|
| 后端 | Laravel 10 + PHP 8.2 |
| 前端 | Blade + TailwindCSS |
| 数据库 | SQLite / MySQL |
| 缓存 | Redis |
| 部署 | Docker + Docker Compose |

---

## 🚀 快速开始

### 环境要求

- Docker 20.10+
- Docker Compose 2.0+
- 至少 2GB 可用内存

### 一键部署

```bash
# 克隆项目
git clone https://github.com/YOUR_USERNAME/eve-esi.git
cd eve-esi

# 复制环境配置
cp .env.example .env

# 启动容器
docker compose up -d

# 安装依赖
docker compose exec app composer install

# 生成应用密钥
docker compose exec app php artisan key:generate

# 运行迁移
docker compose exec app php artisan migrate

# 访问 http://your-server-ip
```

---

## 📁 项目结构

```
eve-esi/
├── app/
│   ├── Http/
│   │   ├── Controllers/     # 控制器
│   │   └── Middleware/      # 中间件
│   ├── Models/              # 数据模型
│   ├── Services/            # ESI API 服务
│   └── Jobs/                # 定时任务
├── config/
│   └── esi.php              # ESI 配置
├── database/
│   ├── migrations/          # 数据库迁移
│   └── seeders/             # 数据填充
├── resources/
│   ├── views/               # Blade 模板
│   └── css/                 # TailwindCSS
├── routes/
│   └── web.php              # 路由定义
├── docker/
│   ├── app/
│   │   └── Dockerfile       # PHP 容器
│   └── nginx/
│       └── default.conf     # Nginx 配置
├── docker-compose.yml       # Docker 编排
└── .env.example             # 环境配置示例
```

---

## 🔐 ESI 认证配置

### 国服 OAuth2 端点

```env
ESI_BASE_URL=https://ali-esi.evepc.163.com/latest/
ESI_OAUTH_URL=https://login.evepc.163.com/v2/oauth/
ESI_CLIENT_ID=bc90aa496a404724a93f41b4f4e97761
ESI_REDIRECT_URI=http://your-server-ip/callback
```

### 获取 Client ID

当前使用国服官方 Client ID，如需自定义：
1. 联系网易 EVE 国服开发者支持
2. 申请创建应用
3. 获取 Client ID 和 Secret

---

## 📊 功能模块

### 阶段 1：基础框架（当前）
- [x] 项目初始化
- [x] Docker 环境配置
- [ ] OAuth2 认证
- [ ] 用户系统

### 阶段 2：核心功能
- [ ] 技能队列
- [ ] 技能点统计
- [ ] 角色信息

### 阶段 3：扩展功能
- [ ] 资产统计
- [ ] 钱包余额
- [ ] 市场订单

### 阶段 4：完善优化
- [ ] 军团管理
- [ ] 数据图表
- [ ] 多用户支持

---

## 🛠 开发指南

### 本地开发

```bash
# 启动开发环境
docker compose up -d

# 查看日志
docker compose logs -f app

# 进入容器
docker compose exec app bash

# 运行测试
docker compose exec app php artisan test
```

### 数据库迁移

```bash
# 创建新迁移
docker compose exec app php artisan make:migration create_xxx_table

# 运行迁移
docker compose exec app php artisan migrate

# 回滚
docker compose exec app php artisan migrate:rollback
```

---

## 📚 相关资源

- [EVE 国服 API 文档](https://ali-esi.evepc.163.com/ui)
- [SeAT 文档](https://eveseat.github.io/docs/)
- [Laravel 文档](https://laravel.com/docs)
- [EVE ESI 规范](https://esi.evetech.net/ui/)

---

## 🤝 贡献指南

欢迎提交 Issue 和 Pull Request！

1. Fork 本项目
2. 创建功能分支 (`git checkout -b feature/amazing-feature`)
3. 提交更改 (`git commit -m 'Add amazing feature'`)
4. 推送到分支 (`git push origin feature/amazing-feature`)
5. 提交 Pull Request

---

## 📄 许可证

MIT License - 详见 [LICENSE](LICENSE) 文件

---

## 📞 联系方式

- 项目 Issues: https://github.com/YOUR_USERNAME/eve-esi/issues
- EVE 国服讨论：百度贴吧 EVE 吧

---

*最后更新：2026-03-09*
