# EVE ESI 部署指南

> 📋 从零开始部署到阿里云服务器

---

## 📁 项目文件清单

在开始部署前，请确保以下文件已创建：

```
eve-esi/
├── README.md
├── LICENSE
├── composer.json
├── .env.example
├── .gitignore
├── deploy.sh
├── docker-compose.yml
├── DEPLOY.md (本文件)
├── docker/
│   ├── app/
│   │   └── Dockerfile
│   └── nginx/
│       └── default.conf
├── app/
│   ├── Http/Controllers/
│   │   ├── Controller.php
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   └── CharacterController.php
│   └── Models/
│       └── User.php
├── config/
│   └── esi.php
├── database/migrations/
│   ├── 2026_03_09_000001_create_users_table.php
│   ├── 2026_03_09_000002_create_sessions_table.php
│   └── 2026_03_09_000003_create_cache_table.php
├── resources/views/
│   ├── welcome.blade.php
│   └── dashboard.blade.php
├── routes/
│   └── web.php
└── bootstrap/
    └── app.php
└── public/
    └── index.php
```

---

## 🚀 部署步骤

### 步骤 1：上传文件到服务器

**方式 A：使用 Git（推荐）**

```bash
# 1. 在本地初始化 Git 仓库
cd /path/to/eve-esi
git init
git add .
git commit -m "Initial commit"

# 2. 创建 GitHub 仓库并推送
git remote add origin https://github.com/YOUR_USERNAME/eve-esi.git
git push -u origin main

# 3. 在服务器上克隆
ssh root@47.116.211.186
git clone https://github.com/YOUR_USERNAME/eve-esi.git /opt/eve-esi
cd /opt/eve-esi
```

**方式 B：直接上传**

```bash
# 使用 scp 上传
scp -r eve-esi/* root@47.116.211.186:/opt/eve-esi/

# 或使用 SFTP 工具（FileZilla、WinSCP 等）
```

---

### 步骤 2：执行部署脚本

```bash
# SSH 登录服务器
ssh root@47.116.211.186

# 进入项目目录
cd /opt/eve-esi

# 给脚本执行权限
chmod +x deploy.sh

# 执行部署脚本
./deploy.sh
```

部署脚本会自动完成：
- ✅ 系统更新
- ✅ Docker 安装
- ✅ Docker Compose 安装
- ✅ 环境变量配置
- ✅ 容器启动
- ✅ Laravel 初始化

---

### 步骤 3：配置环境变量

部署完成后，编辑 `.env` 文件：

```bash
nano .env
```

**必须修改的配置：**

```env
APP_DEBUG=false
APP_URL=http://47.116.211.186

ESI_REDIRECT_URI=http://47.116.211.186/callback
```

保存后重启容器：

```bash
docker compose restart
```

---

### 步骤 4：访问网站

打开浏览器访问：

```
http://47.116.211.186
```

点击「使用 EVE 账号登录」，完成 OAuth2 授权流程。

---

## 🔧 故障排查

### 容器无法启动

```bash
# 查看日志
docker compose logs

# 检查容器状态
docker compose ps
```

### 数据库迁移失败

```bash
# 进入容器
docker compose exec app bash

# 重新运行迁移
php artisan migrate:fresh --force
```

### OAuth2 回调失败

检查 `.env` 中的 `ESI_REDIRECT_URI` 是否与实际访问的 URL 一致。

---

## 📊 常用命令

```bash
# 查看日志
docker compose logs -f

# 查看应用日志
docker compose exec app tail -f storage/logs/laravel.log

# 进入容器
docker compose exec app bash

# 重启服务
docker compose restart

# 停止服务
docker compose down

# 重新构建
docker compose up -d --build

# 清理无用资源
docker system prune -a
```

---

## 🔐 安全建议

### 1. 配置防火墙

```bash
# 只开放必要端口
ufw allow 22/tcp    # SSH
ufw allow 80/tcp    # HTTP
ufw enable
```

### 2. 启用 HTTPS（推荐）

使用 Let's Encrypt 免费证书：

```bash
# 安装 Certbot
apt-get install certbot python3-certbot-nginx -y

# 获取证书（需要有域名）
certbot --nginx -d your-domain.com
```

### 3. 禁用 root SSH 登录

```bash
# 编辑 SSH 配置
nano /etc/ssh/sshd_config

# 修改以下配置
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes

# 重启 SSH 服务
systemctl restart sshd
```

---

## 📈 性能优化

### 1. 启用 OPcache

在 `docker/app/Dockerfile` 中添加：

```dockerfile
RUN docker-php-ext-install opcache
```

### 2. 配置 Redis 持久化

编辑 `docker-compose.yml`：

```yaml
redis:
  volumes:
    - redis-data:/data
volumes:
  redis-data:
```

### 3. 队列优化

对于大量数据同步，使用队列异步处理：

```bash
# 启动队列 worker
docker compose exec app php artisan queue:work --daemon
```

---

## 🔄 更新流程

```bash
# 1. 拉取最新代码
git pull origin main

# 2. 重新构建容器
docker compose up -d --build

# 3. 运行迁移
docker compose exec app php artisan migrate --force

# 4. 清理缓存
docker compose exec app php artisan optimize:clear
```

---

## 📞 获取帮助

遇到问题？

1. 查看日志：`docker compose logs -f`
2. 检查 Issue：https://github.com/YOUR_USERNAME/eve-esi/issues
3. EVE 贴吧讨论

---

*最后更新：2026-03-09*
