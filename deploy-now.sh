#!/bin/bash
cd /opt/eve-esi
git fetch origin
git reset --hard origin/main
docker compose exec -T app php artisan view:clear
docker compose restart
echo "部署完成！"
git log --oneline -1
