# 紧急修复部署说明

**修复内容：**
1. ✅ Dashboard 页面添加快捷操作按钮（技能队列/资产/角色管理/钱包）
2. ✅ 主页根据登录状态自动跳转（已登录→Dashboard，未登录→登录页）

**文件修改：**
- `resources/views/dashboard.blade.php` - 添加快捷操作按钮
- `resources/views/welcome.blade.php` - 添加登录判断逻辑

---

## 🚀 手动部署步骤

由于 SSH 会话卡住，请手动执行以下命令：

### 方式 1：一条命令更新

```bash
ssh -i /home/tus/.openclaw/workspace/projects/openclaw.pem root@47.116.211.186 "cd /opt/eve-esi && git pull origin main && docker compose exec -T app php artisan view:clear && docker compose restart"
```

### 方式 2：分步执行

```bash
# 1. SSH 登录
ssh -i /home/tus/.openclaw/workspace/projects/openclaw.pem root@47.116.211.186

# 2. 进入项目目录
cd /opt/eve-esi

# 3. 拉取最新代码
git pull origin main

# 4. 清理视图缓存
docker compose exec -T app php artisan view:clear

# 5. 重启容器
docker compose restart

# 6. 验证部署
docker compose ps
```

---

## ✅ 验证方法

### 1. 检查 Dashboard 快捷按钮

访问 http://47.116.211.186/dashboard

**应该看到：**
- 📚 技能队列 按钮
- 📦 我的资产 按钮
- 👥 角色管理 按钮
- 💰 钱包查询（开发中）按钮

### 2. 检查主页登录跳转

访问 http://47.116.211.186/

**预期行为：**
- **已登录** → 自动跳转到 /dashboard
- **未登录** → 显示登录按钮

---

## 📝 Git 提交信息

```
commit 0e2de40
fix: 修复 Dashboard 快捷按钮 + 主页登录判断

1. 修复 Dashboard 页面缺少快捷操作按钮
   - 添加技能队列/资产/角色管理/钱包入口
   - 保持异步加载逻辑不变

2. 优化主页登录逻辑
   - 已登录用户自动跳转到 Dashboard
   - 未登录用户显示登录按钮
```

---

*创建时间：2026-03-10 18:05*  
*紧急修复，请手动部署*
