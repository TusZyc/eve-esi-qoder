# EVE ESI Tools (eve-esi-qoder)

EVE Online 国服 (Serenity) ESI 数据工具网站，基于 Laravel 构建的全功能 EVE 角色管理平台。

> **线上地址**：部署于阿里云 ECS  
> **GitHub**: https://github.com/TusZyc/eve-esi-qoder

## 功能列表

| 功能 | 说明 |
|------|------|
| **EVE SSO 登录** | 国服 OAuth2 授权，支持 73 个 ESI 权限 |
| **仪表盘** | 角色概览、服务器状态、ISK 余额、在线玩家数 |
| **技能队列** | 训练队列监控、全量技能分组展示（673 个技能） |
| **资产管理** | 两步加载、搜索、位置翻译、舰船/物品机库分类 |
| **击杀报告 (KM)** | 多维度搜索、自动补全、KM 详情查看 |
| **市场工具** | 跨星域价格查询、分组浏览、价格历史图表 |
| **角色信息** | 属性、植入体、克隆体、雇佣历史四标签页 |
| **旗舰导航** | 跳跃距离计算、一跳可达、路线规划（BFS/Dijkstra） |
| **星系距离** | 两星系间跳数/光年距离计算 |
| **游客仪表盘** | 未登录用户可查看服务器状态和功能预览 |
| **共享布局** | 统一导航栏、认证/游客双布局系统 |
| **数据自动更新** | Cron 定时任务每周更新 EVE 数据 |

## 技术栈

- **后端**: Laravel 10 + PHP 8.2
- **前端**: Blade + Tailwind CSS (CDN) + Alpine.js
- **缓存**: Redis（多级缓存策略）
- **数据库**: SQLite
- **部署**: Docker Compose（php-fpm + nginx + redis）
- **数据**: Python 3 + openpyxl（数据更新脚本）

## 项目结构

```
eve-esi-qoder/
├── app/
│   ├── Console/Commands/       # Artisan 命令
│   ├── Exceptions/
│   │   └── EveApiException.php # ESI API 统一异常
│   ├── Http/
│   │   ├── Controllers/        # 控制器（含 Api/ 子目录）
│   │   └── Middleware/         # Token 自动刷新等中间件
│   ├── Services/               # 业务服务层
│   │   ├── Killmail/           # 击杀报告子服务（拆分后）
│   │   │   ├── ProtobufCodec.php      # Protobuf 编解码
│   │   │   ├── BetaKbApiClient.php    # Beta KB API 客户端
│   │   │   └── KillmailSearchService.php # KM 搜索逻辑
│   │   ├── EveEsiService.php   # ESI API 基础服务
│   │   ├── KillmailService.php # 击杀报告门面服务
│   │   ├── MarketService.php   # 市场服务
│   │   ├── AssetDataService.php      # 资产数据服务
│   │   ├── CharacterDataService.php  # 角色数据服务
│   │   ├── CacheKeyService.php       # 统一缓存键管理
│   │   ├── ApiErrorHandler.php       # 统一 API 错误处理
│   │   └── ...
│   └── Helpers/EveHelper.php   # EVE 通用辅助函数
├── config/
│   ├── esi.php                 # ESI API 配置
│   └── market.php              # 市场配置
├── data/                       # 本地数据文件
│   ├── items.json              # 物品名称数据
│   └── solar_system_jumps.json # 星系跳跃数据
├── database/migrations/        # 数据库迁移（含索引优化）
├── docker/
│   ├── app/Dockerfile          # PHP-FPM 容器
│   └── nginx/default.conf      # Nginx 配置
├── resources/views/
│   ├── layouts/                # 共享布局
│   └── ...                     # 各功能视图
├── routes/
│   ├── web.php                 # Web 路由
│   └── api.php                 # API 路由
├── scripts/update_evedata.py   # 数据更新脚本
├── docker-compose.yml
└── .env.example
```

## 环境要求

- Docker 20.10+
- Docker Compose 2.0+
- PHP 8.2+（容器内）
- Python 3.8+（数据更新脚本）

## 安装部署

### 1. 克隆项目

```bash
git clone https://github.com/TusZyc/eve-esi-qoder.git
cd eve-esi-qoder
```

### 2. 配置环境变量

```bash
cp .env.example .env
# 编辑 .env 文件，配置 ESI 相关参数
```

### 3. 启动 Docker 容器

```bash
docker compose up -d
```

### 4. 初始化应用

```bash
# 安装依赖
docker compose exec app composer install

# 生成应用密钥
docker compose exec app php artisan key:generate

# 执行数据库迁移
docker compose exec app php artisan migrate

# 缓存市场分组（可选，加速首次加载）
docker compose exec app php artisan cache:market-groups
```

### 5. 访问应用

打开浏览器访问 `http://localhost` 或服务器 IP

## 配置说明

### .env 关键配置项

```env
# 应用配置
APP_ENV=production
APP_DEBUG=false
APP_URL=http://your-domain.com

# EVE ESI 配置（国服）
ESI_BASE_URL=https://ali-esi.evepc.163.com/latest/
ESI_OAUTH_URL=https://login.evepc.163.com/v2/oauth/
ESI_CLIENT_ID=your_client_id

# 缓存配置
CACHE_DRIVER=redis
REDIS_HOST=eve-esi-redis
REDIS_PORT=6379

# 会话配置
SESSION_DRIVER=redis
```

## 开发说明

### 本地开发

1. 按照上述安装步骤启动项目
2. 修改代码后，Blade 视图会自动刷新
3. PHP 代码修改后需清理缓存：
   ```bash
   docker compose exec app php artisan cache:clear
   docker compose exec app php artisan view:clear
   ```

### 部署到服务器

项目使用 SCP 直传方式部署（避免 tarball 导致的 PHP 变量符号问题）：

```bash
# 1. 上传文件到服务器 /tmp
scp -i your-key.pem file.php root@server:/tmp/

# 2. 复制到容器内
ssh -i your-key.pem root@server "docker cp /tmp/file.php eve-esi-app:/var/www/html/path/"

# 3. 清理缓存
ssh -i your-key.pem root@server "cd /opt/eve-esi && docker compose exec -T app php artisan cache:clear && docker compose exec -T app php artisan view:clear"
```

## 定时任务

服务器 crontab 配置（host 级别）：

```cron
# 每周一凌晨 2:00 更新 EVE 数据
0 2 * * 1 cd /opt/eve-esi && docker compose exec -T app php artisan eve:update-data >> /var/log/eve-update.log 2>&1
```

## 已知问题与待优化

### 性能问题

- ✅ 服务器状态缓存已添加（减少重复 API 调用）
- ✅ 角色信息页已改为并行 API 调用（10-20s → 3-5s）
- ✅ 数据库索引已添加（users 表查询优化）
- 资产页面首次加载较慢（15-30s），多阶段 API 调用 + 位置翻译
- 技能分组加载需 26 次 ESI 调用

### 架构问题

- ✅ KillmailService 已拆分为门面 + 3 子服务（Killmail/ 目录）
- ✅ AssetDataController 已重构（955 行 → 353 行）
- ✅ 统一错误处理已实现（EveApiException + ApiErrorHandler）
- ✅ Token 刷新逻辑已统一（EveEsiService）
- CacheKeyService 采用率待提升（目前 53%）
- 缓存键命名风格待统一
- 缺少数据转换层 (DTO)

### 安全问题

- Redis 无密码保护（待正式上线后处理）
- OAuth2 缺少 state 参数验证（待正式上线后处理）
- 日志级别为 DEBUG（生产环境应改为 ERROR）
- Token 加密存储（待正式上线后处理）
- HTTPS 配置（待正式上线后处理）

### 待开发功能

- 钱包查询页面
- 资产估值功能
- 军团管理页面

## 相关资源

- [EVE 国服 ESI API 文档](https://ali-esi.evepc.163.com/ui)
- [EVE ESI 规范](https://esi.evetech.net/ui/)
- [Laravel 文档](https://laravel.com/docs)

## License

MIT License

---

*开发者：TusZyc (图斯) | 最后更新：2026-03-17*
