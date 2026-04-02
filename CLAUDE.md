# EVE ESI Tools - 开发指南

## 项目简介
EVE Online（国服/Serenity）数据工具平台，提供角色管理、市场查询、击杀分析等功能。

## 技术栈
- **后端：** Laravel 10 / PHP 8.2
- **前端：** Blade + Tailwind CSS
- **数据库：** SQLite
- **缓存/队列：** Redis
- **部署：** Docker Compose (Nginx + PHP-FPM + Redis)
- **EVE API：** ESI Serenity (ali-esi.evepc.163.com)

## 开发规范
- 所有用户界面文本使用中文
- ESI 调用优先使用本地 `data/` 目录下的静态 JSON 文件，减少外部请求
- 缓存键名应通过 `CacheKeyService` 统一管理
- API 控制器放在 `app/Http/Controllers/Api/` 目录
- 业务逻辑放在 `app/Services/` 目录，控制器保持精简
- 错误返回格式统一为 `{'success': false, 'error': 'code', 'message': '描述'}`

## 部署流程
```bash
git pull origin main
docker compose exec -T app php artisan cache:clear
docker compose exec -T app php artisan config:clear
docker compose exec -T app php artisan view:clear
docker compose exec -T app php artisan route:clear
docker restart eve-esi-app  # 仅在修改 PHP 静态变量时需要
```

## AI 协作开发记忆

> **⚠️ 致所有 AI 开发工具：本项目的开发记忆和任务管理存储在独立仓库。开始工作前，请先克隆/拉取该仓库并阅读 HANDOFF.md 和 README.md 中的协作规范。结束工作前，必须更新记忆仓库并标注你的工具名。**

记忆仓库地址：https://github.com/TusZyc/qoder-memory
