#!/bin/bash

# EVE ESI 管理平台 - 服务器部署脚本
# 适用于 Ubuntu 24.04

set -e

echo "🚀 开始部署 EVE ESI 管理平台..."

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 检查是否以 root 运行
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}请使用 root 用户运行此脚本${NC}"
    exit 1
fi

# 1. 系统更新
echo -e "${YELLOW}[1/7] 更新系统包...${NC}"
apt-get update
apt-get upgrade -y

# 2. 安装 Docker
echo -e "${YELLOW}[2/7] 安装 Docker...${NC}"
curl -fsSL https://get.docker.com -o get-docker.sh
sh get-docker.sh
rm get-docker.sh

# 3. 安装 Docker Compose
echo -e "${YELLOW}[3/7] 安装 Docker Compose...${NC}"
DOCKER_CONFIG=${DOCKER_CONFIG:-$HOME/.docker}
mkdir -p $DOCKER_CONFIG/cli-plugins
curl -SL https://github.com/docker/compose/releases/download/v2.24.0/docker-compose-linux-x86_64 -o $DOCKER_CONFIG/cli-plugins/docker-compose
chmod +x $DOCKER_CONFIG/cli-plugins/docker-compose

# 4. 创建项目目录
echo -e "${YELLOW}[4/7] 创建项目目录...${NC}"
PROJECT_DIR="/opt/eve-esi"
mkdir -p $PROJECT_DIR
cd $PROJECT_DIR

# 5. 克隆项目（如果是 Git 仓库）
# echo -e "${YELLOW}[5/7] 克隆项目代码...${NC}"
# git clone https://github.com/YOUR_USERNAME/eve-esi.git .
# 或者手动上传文件后跳过此步骤

# 6. 配置环境变量
echo -e "${YELLOW}[5/7] 配置环境变量...${NC}"
cat > .env << EOF
APP_NAME="EVE ESI"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://$(curl -s ifconfig.me)

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
ESI_REDIRECT_URI=http://$(curl -s ifconfig.me)/callback
ESI_DATASOURCE=serenity
EOF

echo -e "${GREEN}✓ 环境变量已配置${NC}"
echo -e "${YELLOW}请编辑 .env 文件修改 ESI_REDIRECT_URI 为你的服务器 IP${NC}"

# 7. 启动容器
echo -e "${YELLOW}[6/7] 启动 Docker 容器...${NC}"
docker compose up -d

# 8. 初始化 Laravel
echo -e "${YELLOW}[7/7] 初始化 Laravel 应用...${NC}"
sleep 10  # 等待容器启动

docker compose exec -T app composer install --no-interaction --prefer-dist --optimize-autoloader
docker compose exec -T app cp .env.example .env
docker compose exec -T app php artisan key:generate --force
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan storage:link

# 创建 SQLite 数据库文件
docker compose exec -T app touch database/database.sqlite
docker compose exec -T app php artisan migrate --force

echo ""
echo -e "${GREEN}======================================${NC}"
echo -e "${GREEN}🎉 部署完成！${NC}"
echo -e "${GREEN}======================================${NC}"
echo ""
echo "访问地址：http://$(curl -s ifconfig.me)"
echo ""
echo -e "${YELLOW}下一步:${NC}"
echo "1. 编辑 .env 文件，确认 ESI_REDIRECT_URI 正确"
echo "2. 重启容器：docker compose restart"
echo "3. 访问网站开始使用"
echo ""
echo -e "${YELLOW}常用命令:${NC}"
echo "  查看日志：docker compose logs -f"
echo "  进入容器：docker compose exec app bash"
echo "  重启服务：docker compose restart"
echo "  停止服务：docker compose down"
echo ""
