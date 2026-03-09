# EVE ESI 快速安装指南

> 📋 复制粘贴这些命令到你的阿里云服务器

---

## 🚀 一键安装（复制以下所有命令）

```bash
# 1. SSH 登录服务器（在你的电脑终端执行）
ssh root@47.116.211.186
# 输入密码：Zyc895930711.

# 2. 创建项目目录
mkdir -p /opt/eve-esi
cd /opt/eve-esi

# 3. 安装 Docker（如果已安装可跳过）
curl -fsSL https://get.docker.com -o get-docker.sh
sh get-docker.sh
rm get-docker.sh

# 4. 安装 Docker Compose
DOCKER_CONFIG=${DOCKER_CONFIG:-$HOME/.docker}
mkdir -p $DOCKER_CONFIG/cli-plugins
curl -SL https://github.com/docker/compose/releases/download/v2.24.0/docker-compose-linux-x86_64 -o $DOCKER_CONFIG/cli-plugins/docker-compose
chmod +x $DOCKER_CONFIG/cli-plugins/docker-compose

# 5. 验证安装
docker --version
docker compose version
```

---

## 📁 上传项目文件

### 方式 A：使用 Git（推荐）

```bash
# 在你的本地电脑上执行
cd /home/tus/.openclaw/workspace/projects/eve-esi
git init
git add .
git commit -m "Initial commit"

# 创建 GitHub 仓库后（替换 YOUR_USERNAME 为你的 GitHub 用户名）
git remote add origin https://github.com/YOUR_USERNAME/eve-esi.git
git push -u origin main

# 然后在服务器上执行
cd /opt/eve-esi
git clone https://github.com/YOUR_USERNAME/eve-esi.git .
```

### 方式 B：使用 SCP 上传

```bash
# 在你的本地电脑上执行
cd /home/tus/.openclaw/workspace/projects
scp -r eve-esi/* root@47.116.211.186:/opt/eve-esi/
```

### 方式 C：直接在服务器上创建文件

如果上面两种方式都不方便，可以在服务器上直接创建文件：

```bash
cd /opt/eve-esi

# 创建 composer.json
cat > composer.json << 'EOF'
{
    "name": "tus/eve-esi",
    "type": "project",
    "description": "EVE Online 国服 ESI 管理平台",
    "require": {
        "php": "^8.2",
        "guzzlehttp/guzzle": "^7.8",
        "laravel/framework": "^10.10",
        "laravel/sanctum": "^3.3"
    },
    "require-dev": {
        "fakerphp/faker": "^1.9.1",
        "laravel/pint": "^1.0",
        "mockery/mockery": "^1.4.4",
        "phpunit/phpunit": "^10.1"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\Factories\\": "database/factories/",
            "Database\\Seeders\\": "database/seeders/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    },
    "config": {
        "optimize-autoloader": true,
        "preferred-install": "dist",
        "sort-packages": true
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
EOF

# 创建 .env 文件
cat > .env << 'EOF'
APP_NAME="EVE ESI"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://47.116.211.186

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=sqlite
SESSION_DRIVER=database
SESSION_LIFETIME=120

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

ESI_BASE_URL=https://ali-esi.evepc.163.com/latest/
ESI_OAUTH_URL=https://login.evepc.163.com/v2/oauth/
ESI_CLIENT_ID=bc90aa496a404724a93f41b4f4e97761
ESI_REDIRECT_URI=http://47.116.211.186/callback
ESI_DATASOURCE=serenity
EOF

# 创建 .env.example
cp .env .env.example

# 创建 .gitignore
cat > .gitignore << 'EOF'
/node_modules
/public/build
/public/hot
/public/storage
/storage/*.key
/vendor
.env
.env.backup
.phpunit.result.cache
EOF

# 创建 docker-compose.yml
cat > docker-compose.yml << 'EOF'
version: '3.8'

services:
    app:
        build:
            context: ./docker/app
            dockerfile: Dockerfile
        container_name: eve-esi-app
        restart: unless-stopped
        working_dir: /var/www/html
        volumes:
            - ./:/var/www/html
            - ./storage:/var/www/html/storage
        networks:
            - eve-esi-network
        depends_on:
            - redis

    nginx:
        image: nginx:alpine
        container_name: eve-esi-nginx
        restart: unless-stopped
        ports:
            - "80:80"
        volumes:
            - ./:/var/www/html
            - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
        networks:
            - eve-esi-network
        depends_on:
            - app

    redis:
        image: redis:alpine
        container_name: eve-esi-redis
        restart: unless-stopped
        networks:
            - eve-esi-network

networks:
    eve-esi-network:
        driver: bridge
EOF

# 创建目录结构
mkdir -p docker/app docker/nginx app/Http/Controllers app/Models config database/migrations resources/views routes bootstrap public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs

# 创建 Dockerfile
cat > docker/app/Dockerfile << 'EOF'
FROM php:8.2-fpm
WORKDIR /var/www/html
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev libzip-dev zip unzip sqlite3 \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
EXPOSE 9000
CMD ["php-fpm"]
EOF

# 创建 Nginx 配置
cat > docker/nginx/default.conf << 'EOF'
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;
    index index.php index.html;
    
    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
EOF

# 创建 ESI 配置
cat > config/esi.php << 'EOF'
<?php
return [
    'base_url' => env('ESI_BASE_URL', 'https://ali-esi.evepc.163.com/latest/'),
    'oauth_url' => env('ESI_OAUTH_URL', 'https://login.evepc.163.com/v2/oauth/'),
    'client_id' => env('ESI_CLIENT_ID', 'bc90aa496a404724a93f41b4f4e97761'),
    'client_secret' => env('ESI_CLIENT_SECRET', ''),
    'redirect_uri' => env('ESI_REDIRECT_URI', 'http://localhost/callback'),
    'datasource' => env('ESI_DATASOURCE', 'serenity'),
    'scopes' => [
        'esi-skills.read_skills.v1',
        'esi-skills.read_skillqueue.v1',
        'esi-assets.read_assets.v1',
        'esi-wallet.read_character_wallet.v1',
        'esi-location.read_location.v1',
        'esi-markets.read_character_orders.v1',
    ],
];
EOF

echo "文件创建完成！"
```

---

## 🏗️ 初始化项目

```bash
# 在服务器上执行（无论用哪种方式上传文件后）
cd /opt/eve-esi

# 启动 Docker 容器
docker compose up -d

# 等待 10 秒让容器启动
sleep 10

# 安装 Composer 依赖
docker compose exec -T app composer install --no-interaction --prefer-dist --optimize-autoloader

# 生成应用密钥
docker compose exec -T app php artisan key:generate

# 创建 SQLite 数据库
docker compose exec -T app touch database/database.sqlite

# 运行数据库迁移
docker compose exec -T app php artisan migrate --force

# 设置权限
docker compose exec -T app chmod -R 775 storage bootstrap/cache

echo "======================================="
echo "🎉 安装完成！"
echo "======================================="
echo ""
echo "访问地址：http://47.116.211.186"
echo ""
echo "如果看到白屏或错误，请检查："
echo "1. docker compose logs -f"
echo "2. 确认 .env 中的 ESI_REDIRECT_URI 正确"
```

---

## ✅ 验证安装

```bash
# 查看容器状态
docker compose ps

# 应该看到 3 个容器都在运行：
# eve-esi-app     Up
# eve-esi-nginx   Up
# eve-esi-redis   Up

# 查看日志
docker compose logs -f
```

---

## 🔧 常见问题

### 问题 1：Docker 命令找不到

```bash
# 添加 Docker 到 PATH
export PATH=$PATH:/usr/bin
# 或重新登录 SSH
```

### 问题 2：端口 80 被占用

```bash
# 检查占用端口的服务
netstat -tlnp | grep :80

# 如果是 Nginx/Apache，先停止
systemctl stop nginx
# 或
systemctl stop apache2
```

### 问题 3：容器启动失败

```bash
# 查看详细日志
docker compose logs app

# 重新构建
docker compose up -d --build
```

### 问题 4：OAuth2 回调失败

确保 `.env` 文件中的 `ESI_REDIRECT_URI` 与你访问的 URL 完全一致：

```env
ESI_REDIRECT_URI=http://47.116.211.186/callback
```

---

## 📞 需要帮助？

1. 查看日志：`docker compose logs -f`
2. 进入容器调试：`docker compose exec app bash`
3. 重启服务：`docker compose restart`

---

*祝你安装顺利！有问题随时反馈。*
