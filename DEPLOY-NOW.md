# 🚀 紧急部署指南

**Git 版本：** 5abfbd1  
**提交信息：** feat: Dashboard 布局优化  
**状态：** ⏳ 待部署

---

## 📋 部署命令（复制粘贴执行）

### 方式 1：一条命令完成

```bash
ssh root@47.116.211.186 "cd /opt/eve-esi && git fetch && git reset --hard origin/main && docker compose exec -T app php artisan view:clear && docker compose restart && echo '✅ 部署完成！' && git log --oneline -1"
```

---

### 方式 2：分步执行

```bash
# 1. SSH 登录
ssh root@47.116.211.186

# 2. 进入项目目录
cd /opt/eve-esi

# 3. 强制更新到最新版本
git fetch origin
git reset --hard origin/main

# 4. 验证版本
git log --oneline -1
# 应该显示：5abfbd1 feat: Dashboard 布局优化

# 5. 清理缓存
docker compose exec -T app php artisan view:clear

# 6. 重启容器
docker compose restart

# 7. 验证容器状态
docker compose ps
```

---

## ✅ 验证方法

### 1. 检查 Git 版本

```bash
git log --oneline -1
```

**预期输出：**
```
5abfbd1 feat: Dashboard 布局优化
```

---

### 2. 访问 Dashboard

**URL:** http://47.116.211.186/dashboard

**预期效果：**
- ✅ 顶部有 4 个栏目导航按钮
- ✅ 仪表盘按钮高亮（蓝色边框）
- ✅ 没有技能队列板块
- ✅ 服务器状态、角色信息、技能信息正常显示

---

### 3. 清理浏览器缓存

**Windows/Linux:** `Ctrl + Shift + R`  
**Mac:** `Cmd + Shift + R`

---

## 🐛 故障排查

### 问题 1: Git 版本不更新

**解决：**
```bash
cd /opt/eve-esi
git fetch origin
git reset --hard origin/main
git status
# 应该显示：Your branch is up to date with 'origin/main'
```

---

### 问题 2: 页面还是旧版

**解决：**
1. 清理浏览器缓存（Ctrl + Shift + R）
2. 使用无痕模式测试
3. 检查容器是否重启成功

---

### 问题 3: 500 错误

**解决：**
```bash
cd /opt/eve-esi
docker compose logs app | tail -50
# 查看错误日志
docker compose restart
```

---

## 📊 当前状态

**本地代码：** ✅ 已提交（5abfbd1）  
**GitHub:** ✅ 已推送  
**服务器：** ⏳ 待部署

---

*创建时间：2026-03-10 21:50*  
*开发者：小图 🍞*
