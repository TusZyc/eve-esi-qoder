# EVE Swagger Interface (ESI) API 完整文档

## API 基本信息
- **基础 URL**: `/latest`
- **API 版本**: 1.19
- **数据源**: serenity (默认), infinity
- **备用路由**: `/dev/`, `/legacy/`, `/v1/`, `/v2/`

---

## 一、Alliance（联盟）API

### 1.1 GET /alliances/
- **功能**: 列出所有活跃的玩家联盟 ID
- **缓存**: 3600 秒
- **认证**: 无需认证
- **参数**:
  - `datasource` (query): 期望数据来源 (serenity/infinity, 默认: serenity)
  - `If-None-Match` (header): ETag 缓存支持

### 1.2 GET /alliances/{alliance_id}/
- **功能**: 获取联盟信息
- **缓存**: 3600 秒
- **认证**: 无需认证
- **参数**:
  - `alliance_id` (path): 联盟 ID
  - `datasource` (query)
  - `If-None-Match` (header)

### 1.3 GET /alliances/{alliance_id}/contacts/
- **功能**: 获取联盟联系人列表
- **缓存**: 300 秒
- **认证**: 需要 OAuth
- **Scope**: `esi-alliances.read_contacts.v1`
- **参数**:
  - `alliance_id` (path)
  - `datasource` (query)
  - `token` (query, OAuth token)
  - `page` (query, 分页)

### 1.4 GET /alliances/{alliance_id}/contacts/labels/
- **功能**: 获取联盟联系人标签
- **认证**: 需要 OAuth
- **Scope**: `esi-alliances.read_contacts.v1`

### 1.5 GET /alliances/{alliance_id}/corporations/
- **功能**: 列出联盟成员公司
- **缓存**: 3600 秒
- **认证**: 无需认证

### 1.6 GET /alliances/{alliance_id}/icons/
- **功能**: 获取联盟图标 URL
- **缓存**: 每日
- **认证**: 无需认证

---

## 二、Character（角色）API

### 2.1 GET /characters/{character_id}/
- **功能**: 获取角色公开信息
- **缓存**: 604800 秒 (7天)
- **认证**: 无需认证
- **返回**: 名称、公司、种族、性别、生日、属性

### 2.2 POST /characters/affiliation/
- **功能**: 批量查询角色 affiliation (公司/联盟/阵营)
- **缓存**: 3600 秒
- **认证**: 无需认证
- **参数**: `character_ids` (数组)

### 2.3 GET /characters/{character_id}/agents_research/
- **功能**: 获取角色agent研究信息
- **缓存**: 3600 秒
- **认证**: 需要 OAuth
- **Scope**: `esi-characters.read_agents_research.v1`

### 2.4 GET /characters/{character_id}/assets/
- **功能**: 获取角色资产列表
- **缓存**: 3600 秒
- **分页**: 最多 1000 项
- **认证**: 需要 OAuth
- **Scope**: `esi-assets.read_assets.v1`

### 2.5 POST /characters/{character_id}/assets/locations/
- **功能**: 获取资产坐标
- **认证**: 需要 OAuth
- **Scope**: `esi-assets.read_assets.v1`

### 2.6 POST /characters/{character_id}/assets/names/
- **功能**: 获取资产名称(如飞船、容器)
- **认证**: 需要 OAuth
- **Scope**: `esi-assets.read_assets.v1`

### 2.7 GET /characters/{character_id}/attributes/
- **功能**: 获取角色核心属性(魅力、智力等)
- **缓存**: 120 秒
- **认证**: 需要 OAuth
- **Scope**: `esi-skills.read_skills.v1`

### 2.8 GET /characters/{character_id}/blueprints/
- **功能**: 获取角色拥有的蓝图
- **缓存**: 3600 秒
- **分页**: 最多 1000 项
- **认证**: 需要 OAuth
- **Scope**: `esi-characters.read_blueprints.v1`

### 2.9 GET /characters/{character_id}/bookmarks/
- **功能**: 获取角色书签列表
- **缓存**: 3600 秒
- **包含**: 位置和坐标
- **认证**: 需要 OAuth
- **Scope**: `esi-bookmarks.read_character_bookmarks.v1`

### 2.10 GET /characters/{character_id}/bookmarks/folders/
- **功能**: 获取书签文件夹列表
- **认证**: 需要 OAuth
- **Scope**: `esi-bookmarks.read_character_bookmarks.v1`

### 2.11 GET /characters/{character_id}/calendar/
- **功能**: 获取 50 条事件摘要
- **缓存**: 5 秒
- **参数**: `from_event` (从指定事件开始)
- **认证**: 需要 OAuth
- **Scope**: `esi-calendar.read_calendar_events.v1`

### 2.12 GET /characters/{character_id}/calendar/{event_id}/
- **功能**: 获取事件详细信息
- **PUT**: 回应事件
- **缓存**: 5 秒
- **认证**: 需要 OAuth
- **Scope**: `esi-calendar.read_calendar_events.v1`

### 2.13 PUT /characters/{character_id}/calendar/{event_id}/
- **功能**: 回应日历事件
- **认证**: 需要 OAuth
- **Scope**: `esi-calendar.respond_calendar_events.v1`

### 2.14 GET /characters/{character_id}/calendar/{event_id}/attendees/
- **功能**: 获取事件参与者列表
- **缓存**: 600 秒
- **认证**: 需要 OAuth
- **Scope**: `esi-calendar.read_calendar_events.v1`

### 2.15 GET /characters/{character_id}/clones/
- **功能**: 获取角色克隆信息(包括跳跃克隆)
- **缓存**: 120 秒
- **认证**: 需要 OAuth
- **Scope**: `esi-clones.read_clones.v1`

### 2.16 GET /characters/{character_id}/contacts/
- **功能**: 获取角色联系人列表
- **缓存**: 300 秒
- **认证**: 需要 OAuth
- **Scope**: `esi-characters.read_contacts.v1`

### 2.17 POST /characters/{character_id}/contacts/
- **功能**: 批量添加联系人
- **认证**: 需要 OAuth
- **Scope**: `esi-characters.write_contacts.v1`

### 2.18 PUT /characters/{character_id}/contacts/
- **功能**: 批量更新联系人
- **认证**: 需要 OAuth
- **Scope**: `esi-characters.write_contacts.v1`

### 2.19 DELETE /characters/{character_id}/contacts/
- **功能**: 批量删除联系人
- **认证**: 需要 OAuth
- **Scope**: `esi-characters.write_contacts.v1`

### 2.20 GET /characters/{character_id}/corporationhistory/
- **功能**: 获取角色公司历史
- **缓存**: 3600 秒

### 2.21 GET /characters/{character_id}/notifications/
- **功能**: 获取角色通知
- **缓存**: 30 秒
- **认证**: 需要 OAuth
- **Scope**: `esi-characters.read_notifications.v1`

### 2.22 GET /characters/{character_id}/notifications/contacts/
- **功能**: 获取新的联系人通知
- **缓存**: 30 秒
- **认证**: 需要 OAuth
- **Scope**: `esi-characters.read_notifications.v1`

### 2.23 GET /characters/{character_id}/planets/
- **功能**: 获取殖民地和星球信息
- **缓存**: 600 秒
- **认证**: 需要 OAuth
- **Scope**: `esi-planets.read_planets.v1`

### 2.24 GET /characters/{character_id}/planets/{planet_id}/
- **功能**: 获取星球详细信息和链接
- **认证**: 需要 OAuth
- **Scope**: `esi-planets.read_planets.v1`

### 2.25 GET /characters/{character_id}/roles/
- **功能**: 获取公司角色
- **缓存**: 300 秒
- **认证**: 需要 OAuth
- **Scope**: `esi-characters.read_corporation_roles.v1`

### 2.26 GET /characters/{character_id}/roles/history/
- **功能**: 获取角色变更历史
- **缓存**: 300 秒
- **认证**: 需要 OAuth
- **Scope**: `esi-characters.read_corporation_roles.v1`

### 2.27 GET /characters/{character_id}/skills/
- **功能**: 获取已学习技能和技能点
- **缓存**: 300 秒
- **认证**: 需要 OAuth
- **Scope**: `esi-skills.read_skills.v1`

### 2.28 GET /characters/{character_id}/skills/{skill_id}/
- **功能**: 获取特定技能详细信息
- **缓存**: 300 秒
- **认证**: 需要 OAuth
- **Scope**: `esi-skills.read_skills.v1`

### 2.29 GET /characters/{character_id}/standings/
- **功能**: 获取角色声望
- **缓存**: 300 秒
- **认证**: 需要 OAuth
- **Scope**: `esi-characters.read_standings.v1`

### 2.30 GET /characters/{character_id}/wallet/
- **功能**: 获取钱包余额
- **缓存**: 300 秒
- **认证**: 需要 OAuth
- **Scope**: `esi-wallet.read_character_wallet.v1`

### 2.31 GET /characters/{character_id}/wallet/journal/
- **功能**: 获取钱包记账条目
- **缓存**: 30 秒
- **分页**: 支持
- **认证**: 需要 OAuth
- **Scope**: `esi-wallet.read_character_wallet.v1`

### 2.32 GET /characters/{character_id}/wallet/transactions/
- **功能**: 获取钱包交易记录
- **缓存**: 30 秒
- **认证**: 需要 OAuth
- **Scope**: `esi-wallet.read_character_wallet.v1`

### 2.33 GET /characters/{character_id}/titles/
- **功能**: 获取公司头衔
- **缓存**: 300 秒
- **认证**: 需要 OAuth
- **Scope**: `esi-characters.read_titles.v1`

---

## 三、Market（市场）API

### 3.1 GET /markets/groups/{market_group_id}/
- **功能**: 获取市场组信息
- **参数**: `market_group_id` (path)

### 3.2 GET /markets/prices/
- **功能**: 获取市场价格
- **返回**: 所有物品的当前市场价格

### 3.3 GET /markets/{region_id}/history/
- **功能**: 获取市场历史数据
- **参数**: `region_id` (path)

### 3.4 GET /markets/{region_id}/orders/
- **功能**: 获取市场订单
- **参数**: `region_id` (path)

### 3.5 GET /markets/structures/{structure_id}/
- **功能**: 获取结构体市场组信息

---

## 四、Universe（宇宙）API

### 4.1 POST /characters/affiliation/
- **功能**: 批量查询角色 affiliation
- **参数**: `character_ids` (数组)
- **缓存**: 3600 秒

### 4.2 POST /universe/names/
- **功能**: 批量获取实体名称
- **参数**: `ids` (数组)

### 4.3 GET /universe/regions/
- **功能**: 获取地区列表

### 4.4 GET /universe/regions/{region_id}/
- **功能**: 获取地区详细信息
- **参数**: `region_id` (path)

### 4.5 GET /universe/systems/
- **功能**: 获取星系列表

### 4.6 GET /universe/systems/{system_id}/
- **功能**: 获取星系详细信息
- **参数**: `system_id` (path)

### 4.7 GET /universe/systems/{system_id}/planets/
- **功能**: 获取星系中的星球列表
- **参数**: `system_id` (path)

### 4.8 GET /universe/stations/
- **功能**: 获取空间站列表

### 4.9 GET /universe/stations/{station_id}/
- **功能**: 获取空间站详细信息
- **参数**: `station_id` (path)

### 4.10 POST /universe/structures/
- **功能**: 获取结构体信息
- **参数**: `structure_ids` (数组)

### 4.11 GET /universe/structures/{structure_id}/
- **功能**: 获取结构体详细信息
- **参数**: `structure_id` (path)

---

## 五、认证系统

### 认证类型
- **认证类型**: EVE SSO (OAuth 2.0)
- **认证参数**: `token` (query parameter)
- **授予类型**: Authorization Code

### 常用 Scope
- `esi-alliances.read_contacts.v1` - 读取联盟联系人
- `esi-assets.read_assets.v1` - 读取资产
- `esi-bookmarks.read_character_bookmarks.v1` - 读取书签
- `esi-calendar.read_calendar_events.v1` - 读取日历事件
- `esi-calendar.respond_calendar_events.v1` - 回应日历事件
- `esi-characters.read_agents_research.v1` - 读取agent研究
- `esi-characters.read_blueprints.v1` - 读取蓝图
- `esi-characters.read_contacts.v1` - 读取联系人
- `esi-characters.read_corporation_roles.v1` - 读取公司角色
- `esi-characters.read_notifications.v1` - 读取通知
- `esi-characters.read_standings.v1` - 读取声望
- `esi-characters.read_titles.v1` - 读取头衔
- `esi-characters.write_contacts.v1` - 写入联系人
- `esi-clones.read_clones.v1` - 读取克隆
- `esi-planets.read_planets.v1` - 读取行星
- `esi-skills.read_skills.v1` - 读取技能
- `esi-universe.read_structures.v1` - 读取结构体
- `esi-wallet.read_character_wallet.v1` - 读取钱包

---

## 六、错误代码

| 代码 | 描述 |
|------|------|
| 200 | 成功 |
| 201 | 已创建 |
| 204 | 无内容 |
| 304 | 未修改 (ETag 缓存) |
| 400 | 错误请求 |
| 401 | 未授权 |
| 403 | 禁止访问 |
| 404 | 未找到 |
| 420 | 错误限制 |
| 500 | 内部服务器错误 |
| 503 | 服务不可用 |
| 504 | 网关超时 |

---

## 七、通用参数

### Query Parameters
- `datasource` - 数据源 (serenity/infinity)
- `language` - 语言 (en/en-us/zh)
- `page` - 分页
- `token` - OAuth token
- `from_event` - 日历事件起始点

### Header Parameters
- `If-None-Match` - ETag 缓存支持

### Path Parameters
- `{character_id}` - 角色 ID
- `{alliance_id}` - 联盟 ID
- `{corporation_id}` - 公司 ID
- `{region_id}` - 地区 ID
- `{system_id}` - 星系 ID
- `{station_id}` - 空间站 ID
- `{structure_id}` - 结构体 ID
- `{planet_id}` - 星球 ID
- `{market_group_id}` - 市场组 ID
- `{event_id}` - 日历事件 ID
- `{skill_id}` - 技能 ID

---

## 八、缓存信息

| 端点类型 | 缓存时间 |
|----------|----------|
| 公共信息 | 3600 秒 - 604800 秒 |
| 联系人 | 300 秒 |
| 日历事件 | 5 - 600 秒 |
| 资产 | 3600 秒 |
| 钱包 | 30 - 300 秒 |
| 市场历史 | 变动较大 |
| 图标 | 每日 |

---

## 九、备用路由

大部分端点支持以下备用路由:
- `/latest/` - 最新版本
- `/dev/` - 开发版本
- `/legacy/` - 旧版本
- `/v1/` - 版本 1
- `/v2/` - 版本 2

---

**文档生成日期**: 2026-03-16  
**数据来源**: https://ali-esi.evepc.163.com/ui/
