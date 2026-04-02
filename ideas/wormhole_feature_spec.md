# 虫洞查询功能开发规格说明

## 一、功能概述

开发虫洞查询功能页面，包含三个标签栏：
1. **虫洞搜索** - 输入虫洞星系编号查询详细信息
2. **虫洞检索** - 模仿 anoik.is/systems 的虫洞星系列表
3. **洞点快查** - 模仿 anoik.is/wormholes 的虫洞类型列表

---

## 二、数据存储方案

### 2.1 静态数据文件（新建）

由于虫洞数据变化频率极低，采用静态JSON文件存储，通过Nginx直接提供，实现O(1)读取速度。

```
data/
├── wormhole_systems.json      # 虫洞星系元数据（C1-C6分类、效果、静态连接）
├── wormhole_types.json        # 虫洞类型数据（B274、Y683等约90种）
├── wormhole_anomalies.json    # 虫洞异常空间列表（按类别）
├── wormhole_effects.json      # 虫洞效果数据（中文）
└── wormhole_connections.json  # 随机游走虫洞概率列表
```

### 2.2 静态文件直接访问（新建）

```
public/data/
├── wormhole_systems.json      # Nginx直接提供，无需PHP处理
├── wormhole_types.json        # 约50KB，即时加载
├── wormhole_anomalies.json    # 约30KB
├── wormhole_effects.json      # 约10KB
```

### 2.3 数据结构设计

#### wormhole_systems.json（虫洞星系元数据）
```json
{
  "J055520": {
    "system_id": 31000001,
    "name": "J055520",
    "class": 1,
    "effect": "pulsar",
    "effect_id": 15,
    "statics": ["Z647", "N069"],
    "region_id": 11000033,
    "constellation_id": 21000334,
    "wandering_possible": ["E004", "L005", "Z006", "M001", "C008", "G008", "Q003", "A009"]
  },
  "J100033": {
    "system_id": 31001178,
    "name": "J100033",
    "class": 2,
    "effect": "wolf_rayet",
    "effect_id": 14,
    "statics": ["B274", "Y683"],
    "region_id": 11000030,
    "constellation_id": 21000330,
    "wandering_possible": ["E004", "L005", "Z006", "M001", "C008", "G008", "Q003", "A009"]
  }
}
```

#### wormhole_types.json（虫洞类型数据）
```json
{
  "B274": {
    "type_id": "B274",
    "destination": "high_sec",
    "destination_zh": "高安全区域",
    "target_class": "HS",
    "max_mass": 2000000000,
    "jump_mass": 50000000,
    "lifetime": 24,
    "spawn_rate": 10
  },
  "Y683": {
    "type_id": "Y683",
    "destination": "c4",
    "destination_zh": "C4虫洞空间",
    "target_class": "D",
    "max_mass": 2000000000,
    "jump_mass": 300000000,
    "lifetime": 24,
    "spawn_rate": 4
  },
  "Z647": {
    "type_id": "Z647",
    "destination": "c1",
    "destination_zh": "C1虫洞空间",
    "target_class": "A",
    "max_mass": 500000000,
    "jump_mass": 20000000,
    "lifetime": 24,
    "spawn_rate": 10
  }
}
```

#### wormhole_effects.json（虫洞效果数据）
```json
{
  "wolf_rayet": {
    "effect_id": 14,
    "name_zh": "沃尔夫-拉叶星",
    "name_en": "Wolf-Rayet Star",
    "effects": {
      "armor_hp": "+30%",
      "shield_hp": "-15%",
      "resists": "不变",
      "signature_radius": "+50%"
    }
  },
  "magnetar": {
    "effect_id": 13,
    "name_zh": "磁星",
    "name_en": "Magnetar",
    "effects": {
      "damage": "+30%",
      "missile_explosion_radius": "+15%",
      "tracking": "-15%",
      "signature_radius": "+30%"
    }
  },
  "pulsar": {
    "effect_id": 15,
    "name_zh": "脉冲星",
    "name_en": "Pulsar",
    "effects": {
      "shield_hp": "+30%",
      "armor_hp": "-15%",
      "cap_recharge": "+30%",
      "signature_radius": "+50%"
    }
  },
  "cataclysmic_variable": {
    "effect_id": 16,
    "name_zh": "激变变星",
    "name_en": "Cataclysmic Variable",
    "effects": {
      "cap_hp": "+30%",
      "cap_recharge": "-15%",
      "remote_rep_amount": "+30%",
      "remote_rep_range": "+30%"
    }
  },
  "black_hole": {
    "effect_id": 17,
    "name_zh": "黑洞",
    "name_en": "Black Hole",
    "effects": {
      "missile_velocity": "+15%",
      "missile_explosion_velocity": "+15%",
      "ship_velocity": "+30%",
      "targeting_range": "+30%",
      "web_strength": "-15%"
    }
  },
  "red_giant": {
    "effect_id": 18,
    "name_zh": "红巨星",
    "name_en": "Red Giant",
    "effects": {
      "armor_hp": "+30%",
      "cap_recharge": "-15%",
      "smart_bomb_range": "+30%",
      "smart_bomb_damage": "+30%"
    }
  }
}
```

#### wormhole_anomalies.json（异常空间列表）
```json
{
  "combat": {
    "1": {
      "name_zh": "边境",
      "sites": [
        "边境异常巡礼",
        "边境异常侦查",
        "边境异常潜伏",
        "边境异常前哨"
      ]
    },
    "2": {
      "name_zh": "边界",
      "sites": [
        "边界异常巡礼",
        "边界异常侦查",
        "边界异常潜伏",
        "边界异常前哨"
      ]
    },
    "3": {
      "name_zh": "中心",
      "sites": [
        "中心异常巡礼",
        "中心异常侦查",
        "中心异常潜伏",
        "中心异常前哨"
      ]
    },
    "4": {
      "name_zh": "核心",
      "sites": [
        "核心异常巡礼",
        "核心异常侦查",
        "核心异常潜伏",
        "核心异常前哨"
      ]
    },
    "5": {
      "name_zh": "极限",
      "sites": [
        "极限异常巡礼",
        "极限异常侦查",
        "极限异常潜伏",
        "极限异常前哨"
      ]
    },
    "6": {
      "name_zh": "深渊",
      "sites": [
        "深渊异常巡礼",
        "深渊异常侦查",
        "深渊异常潜伏",
        "深渊异常前哨"
      ]
    }
  },
  "relic": {
    "1": ["边境遗迹数据银行", "边境遗迹废墟", "边境遗迹档案馆"],
    "2": ["边界遗迹数据银行", "边界遗迹废墟", "边界遗迹档案馆"],
    "3": ["中心遗迹数据银行", "中心遗迹废墟", "中心遗迹档案馆"],
    "4": ["核心遗迹数据银行", "核心遗迹废墟", "核心遗迹档案馆"],
    "5": ["极限遗迹数据银行", "极限遗迹废墟", "极限遗迹档案馆"],
    "6": ["深渊遗迹数据银行", "深渊遗迹废墟", "深渊遗迹档案馆"]
  },
  "data": {
    "1": ["边境数据终端", "边境数据服务器", "边境数据仓库"],
    "2": ["边界数据终端", "边界数据服务器", "边界数据仓库"],
    "3": ["中心数据终端", "中心数据服务器", "中心数据仓库"],
    "4": ["核心数据终端", "核心数据服务器", "核心数据仓库"],
    "5": ["极限数据终端", "极限数据服务器", "极限数据仓库"],
    "6": ["深渊数据终端", "深渊数据服务器", "深渊数据仓库"]
  },
  "gas": {
    "1": ["边境气云矿点"],
    "2": ["边界气云矿点"],
    "3": ["中心气云矿点", "气云矿点"],
    "4": ["核心气云矿点"],
    "5": ["极限气云矿点"],
    "6": ["深渊气云矿点"]
  },
  "ore": {
    "1": ["边境矿石矿点", "普通矿石矿点"],
    "2": ["边界矿石矿点", "普通矿石矿点"],
    "3": ["中心矿石矿点", "普通矿石矿点"],
    "4": ["核心矿石矿点"],
    "5": ["极限矿石矿点"],
    "6": ["深渊矿石矿点"]
  }
}
```

---

## 三、路由设计

```php
// 虫洞查询页面（公开访问）
Route::get('/wormhole', [WormholeController::class, 'index'])->name('wormhole.index');
Route::get('/wormhole/{system_name}', [WormholeController::class, 'show'])->name('wormhole.show');

// 虫洞公开 API
Route::middleware('throttle:30,1')->prefix('api/public/wormhole')->group(function () {
    Route::get('/search', [WormholeApiController::class, 'search'])->name('api.public.wormhole.search');
    Route::get('/system/{system_id}', [WormholeApiController::class, 'systemInfo'])->name('api.public.wormhole.system');
    Route::get('/system/{system_id}/kills', [WormholeApiController::class, 'systemKills'])->name('api.public.wormhole.kills');
    Route::get('/systems', [WormholeApiController::class, 'systemsList'])->name('api.public.wormhole.systems');
    Route::get('/types', [WormholeApiController::class, 'typesList'])->name('api.public.wormhole.types');
    Route::get('/autocomplete', [WormholeApiController::class, 'autocomplete'])->name('api.public.wormhole.autocomplete');
});
```

---

## 四、标签栏功能详细设计

### 4.1 标签栏一：虫洞搜索

#### 4.1.1 输入框设计
- 支持模糊搜索（如输入 "100033" 或 "J100033"）
- 首位 "J" 可填可不填
- 自动补全（输入≥3字符时触发）
- 搜索范围：全部虫洞星系（J编号 + 系统ID）

#### 4.1.2 搜索结果展示（五个信息栏）

**第一栏：基本信息**
| 字段 | 数据来源 | 说明 |
|------|----------|------|
| 星系编号 | 本地数据 | J+六位数字 |
| 星系ID | 本地数据 | 31000001+ |
| 虫洞等级 | wormhole_systems.json | C1-C6 |
| 星座名称 | eve_constellations.json | 中文名称 |
| 星域名称 | eve_regions.json | 中文名称 |
| 安全等级 | eve_systems.json | -0.99（固定） |
| 系统大小 | ESI计算 | AU半径 |
| 特殊效果 | wormhole_effects.json | 中文名称+效果说明 |

**第二栏：连接信息**
| 字段 | 数据来源 | 说明 |
|------|----------|------|
| 静态连接 | wormhole_systems.json | 类型代码+目的地 |
| 游走虫洞可能 | wormhole_systems.json | 类型代码列表 |

展示格式：
```
静态连接:
  B274 → 高安全区域 (HS)
  Y683 → C4虫洞空间 (D)

可能出现的游走虫洞:
  E004 → C1虫洞空间 (A)
  L005 → C2虫洞空间 (B)
  ...
```

**第三栏：天体信息**
| 字段 | 数据来源 | 说明 |
|------|----------|------|
| 行星列表 | ESI `/universe/systems/{system_id}` | 行星详情 |
| 卫星列表 | ESI `/universe/systems/{system_id}` | 每行星的卫星 |

ESI返回示例：
```json
{
  "planets": [
    {
      "planet_id": 40000001,
      "name": "J100033 I",
      "moons": [
        {"moon_id": 40000002, "name": "J100033 I - Moon 1"}
      ]
    }
  ]
}
```

展示格式：
```
行星 1: J100033 I
  └─ 卫星 1: J100033 I - Moon 1
  └─ 卫星 2: J100033 I - Moon 2
行星 2: J100033 II
  └─ 无卫星
```

**第四栏：可能的异常空间**
| 类别 | 数据来源 | 说明 |
|------|----------|------|
| 战斗地点 | wormhole_anomalies.json | 按等级的战斗异常列表 |
| 遗迹地点 | wormhole_anomalies.json | 遗迹扫描信号列表 |
| 数据地点 | wormhole_anomalies.json | 数据扫描信号列表 |
| 气云地点 | wormhole_anomalies.json | 气云矿点列表 |
| 矿石地点 | wormhole_anomalies.json | 矿石矿点列表 |

展示格式：
```
战斗地点:
  - 边境异常巡礼
  - 边境异常侦查
  - 边境异常潜伏

遗迹地点:
  - 边境遗迹数据银行
  - 边境遗迹废墟

数据地点:
  - 边境数据终端
  - 边境数据服务器

气云地点:
  - 边境气云矿点

矿石地点:
  - 边境矿石矿点
```

**第五栏：最近击杀**
| 字段 | 数据来源 | 说明 |
|------|----------|------|
| KM列表 | Beta KB API | 最近5条 |
| KM详情 | Beta KB API | 点击展开 |
| KB链接 | kb.ceve-market.org | 跳转链接 |

Beta KB API调用：
```php
// 使用 fetchBetaSearchKillsAdvanced 方法
$params = [
    'systems' => [$systemId],  // 虫洞系统ID（31000001+）
];
$kills = $betaKbApiClient->fetchBetaSearchKillsAdvanced($params);
// 取前5条，按时间倒序
```

展示格式：
```
最近击杀（共 X 条）:
[点击展开详情]
┌─────────────────────────────────────────────────────┐
│ 2024-01-15 14:30  J100033                          │
│ 受害者: 图斯 - 舰船: 巨鸟级                         │
│ 价值: 15,000,000 ISK                               │
│ [查看详情]                                          │
└─────────────────────────────────────────────────────┘
...
[查看更多击杀 → kb.ceve-market.org/system/31001178]
```

---

### 4.2 标签栏二：虫洞检索（模仿 anoik.is/systems）

#### 4.2.1 功能设计
- 虫洞星系列表展示
- 按等级筛选（C1-C6）
- 按效果筛选（沃尔夫-拉叶星、磁星、脉冲星等）
- 搜索框快速查找
- 分页显示

#### 4.2.2 列表字段
| 列名 | 数据来源 | 说明 |
|------|----------|------|
| 星系编号 | wormhole_systems.json | J+六位数字 |
| 等级 | wormhole_systems.json | C1-C6 |
| 效果 | wormhole_effects.json | 中文名称 |
| 静态1 | wormhole_types.json | 类型→目的地 |
| 靟态2 | wormhole_types.json | 类型→目的地 |
| 操作 | - | 查看详情链接 |

#### 4.2.3 展示格式（表格）
```
筛选: [C1] [C2] [C3] [C4] [C5] [C6] | 效果: [全部▼] | 搜索: [____]

| 星系编号 | 等级 | 效果       | 静态1      | 静态2      | 操作   |
|----------|------|------------|------------|------------|--------|
| J055520  | C1   | 脉冲星     | Z647→C1    | N069→高安  | [详情] |
| J100033  | C2   | 沃尔夫-拉叶星 | B274→高安 | Y683→C4    | [详情] |
| J100102  | C2   | 磁星       | B274→高安  | Z647→C1    | [详情] |
...

分页: < 1 2 3 ... 100 >
```

---

### 4.3 标签栏三：洞点快查（模仿 anoik.is/wormholes）

#### 4.3.1 功能设计
- 虫洞类型列表展示（约90种）
- 搜索框快速查找
- 按目的地筛选（高安/低安/00/C1-C6）

#### 4.3.2 列表字段
| 列名 | 数据来源 | 说明 |
|------|----------|------|
| 类型代码 | wormhole_types.json | 如 B274 |
| 目的地 | wormhole_types.json | 中文名称 |
| 最大质量 | wormhole_types.json | 总通行质量 |
| 单次跳跃 | wormhole_types.json | 单次跳跃质量 |
| 寿命 | wormhole_types.json | 小时数 |

#### 4.3.3 展示格式（表格）
```
筛选: 目的地: [全部▼] | 搜索: [____]

| 类型代码 | 目的地       | 最大质量 | 单次跳跃质量 | 寿命 |
|----------|--------------|----------|--------------|------|
| B274     | 高安全区域   | 2B kg    | 50M kg       | 24h  |
| Y683     | C4虫洞空间   | 2B kg    | 300M kg      | 24h  |
| Z647     | C1虫洞空间   | 500M kg  | 20M kg       | 24h  |
| U210     | 低安全区域   | 1B kg    | 300M kg      | 24h  |
| N069     | 高安全区域   | 500M kg  | 62M kg       | 24h  |
...
```

---

## 五、API端点设计

### 5.1 虫洞搜索API
```
GET /api/public/wormhole/search?q=J100033
返回: { results: [{ system_id, name, class, effect, statics }] }
```

### 5.2 虫洞系统详情API
```
GET /api/public/wormhole/system/{system_id}
返回: {
  system_id, name, class, effect, effect_detail,
  constellation_name, region_name, security,
  statics: [{ type, destination_zh }],
  wandering_possible: [{ type, destination_zh }],
  planets: [{ planet_id, name, moons }],
  anomalies: { combat, relic, data, gas, ore }
}
```

### 5.3 虫洞系统击杀API
```
GET /api/public/wormhole/system/{system_id}/kills
返回: { kills: [前5条KM], total: 总数, kb_url: KB链接 }
```

### 5.4 虫洞星系列表API
```
GET /api/public/wormhole/systems?class=2&effect=pulsar&page=1
返回: { systems: [...], pagination: { total, page, per_page } }
```

### 5.5 虫洞类型列表API
```
GET /api/public/wormhole/types?destination=high_sec
返回: { types: [...] }
```

### 5.6 自动补全API
```
GET /api/public/wormhole/autocomplete?q=100
返回: [{ system_id, name, class }]
```

---

## 六、数据获取方案

### 6.1 可从ESI获取的数据
| 数据 | ESI端点 | 备注 |
|------|---------|------|
| 星系基础信息 | `/universe/systems/{id}` | 包含行星、卫星 |
| 行星详情 | `/universe/planets/{id}` | 行星类型、位置 |
| 星座名称 | `/universe/constellations/{id}?language=zh` | 中文名称 |
| 星域名称 | `/universe/regions/{id}?language=zh` | 中文名称 |

### 6.2 需本地存储的数据（无法从ESI获取）
| 数据 | 来源 | 备注 |
|------|------|------|
| 虫洞等级(C1-C6) | Anoik.is爬取/社区数据 | ESI无此数据 |
| 静态连接类型 | Anoik.is爬取/社区数据 | ESI无此数据 |
| 虫洞效果类型 | SDE secondarySunGroupId | 需映射转换 |
| 虫洞类型参数 | Anoik.is爬取/社区数据 | 质量、寿命等 |
| 异常空间列表 | EVE社区Wiki | 按类别固定列表 |

### 6.3 击杀数据获取
| 数据 | 来源 | 备注 |
|------|------|------|
| KM列表 | Beta KB API | `fetchBetaSearchKillsAdvanced` |
| KM详情 | Beta KB API | `parseBetaKillDetailHtml` |
| KB链接 | kb.ceve-market.org | 系统页面链接 |

---

## 七、性能优化策略

### 7.1 静态文件直接提供
- 将 wormhole_*.json 放入 `public/data/`
- Nginx 直接提供，无需 PHP 处理
- 添加 HTTP 缓存头：`Cache-Control: public, max-age=86400`

### 7.2 前端加载策略
```javascript
// 优先加载静态文件
async function loadWormholeData() {
  try {
    const systemsResp = await fetch('/data/wormhole_systems.json');
    const typesResp = await fetch('/data/wormhole_types.json');
    // 失败则回退到 API
    if (!systemsResp.ok) {
      systemsResp = await fetch('/api/public/wormhole/systems');
    }
  } catch (e) {
    // 回退到 API
  }
}
```

### 7.3 ESI数据缓存
- 星系行星信息：Redis缓存 24小时
- 击杀数据：Redis缓存 5分钟

### 7.4 预生成静态数据命令
```bash
php artisan wormhole:cache-systems    # 生成 wormhole_systems.json
php artisan wormhole:cache-types      # 生成 wormhole_types.json
```

---

## 八、英文词汇中文化规则

### 8.1 必须使用ESI或本地数据的中文名称
| 英文 | 中文来源 | 备注 |
|------|----------|------|
| High Security | ESI `language=zh` | "高安全区域" |
| Low Security | ESI `language=zh` | "低安全区域" |
| Null Security | ESI `language=zh` | "零安全区域" |
| 星座名称 | eve_constellations.json | 中文数据 |
| 星域名称 | eve_regions.json | 中文数据 |
| 行星名称 | ESI `language=zh` | 如 "J100033 I" |

### 8.2 自建中文映射表
| 英文 | 中文 | 来源 |
|------|------|------|
| Wolf-Rayet Star | 沃尔夫-拉叶星 | EVE Wiki中文 |
| Magnetar | 磁星 | EVE Wiki中文 |
| Pulsar | 脉冲星 | EVE Wiki中文 |
| Cataclysmic Variable | 激变变星 | EVE Wiki中文 |
| Black Hole | 黑洞 | EVE Wiki中文 |
| Red Giant | 红巨星 | EVE Wiki中文 |
| C1-C6 Wormhole Space | C1-C6虫洞空间 | 社区通用翻译 |

---

## 九、文件结构

### 9.1 新建文件
```
app/
├── Http/Controllers/
│   ├── WormholeController.php          # 虫洞页面控制器
│   └── Api/
│       └── WormholeApiController.php   # 虫洞API控制器
├── Services/
│   └── WormholeService.php             # 虫洞数据服务
│   └── WormholeKbService.php           # 虫洞击杀数据服务

data/
├── wormhole_systems.json               # 虫洞星系元数据
├── wormhole_types.json                 # 虫洞类型数据
├── wormhole_anomalies.json             # 异常空间列表
├── wormhole_effects.json               # 效果数据（中文）
└── wormhole_connections.json           # 连接概率数据

public/data/
├── wormhole_systems.json               # Nginx直接提供
├── wormhole_types.json
├── wormhole_anomalies.json
├── wormhole_effects.json

resources/views/
├── wormhole/
│   ├── index.blade.php                 # 虫洞查询主页面（三标签栏）
│   ├── search.blade.php                # 搜索标签栏内容
│   ├── systems.blade.php               # 检索标签栏内容
│   └── types.blade.php                 # 洞点快查标签栏内容
│   └── components/
│       ├── system-info.blade.php       # 系统信息组件
│       ├── connection-info.blade.php   # 连接信息组件
│       ├── celestial-info.blade.php    # 天体信息组件
│       ├── anomaly-info.blade.php      # 异常空间组件
│       ├── kill-info.blade.php         # 击杀信息组件

app/Console/Commands/
├── CacheWormholeData.php               # 缓存虫洞数据命令
```

---

## 十、开发优先级

### Phase 1（核心功能）
1. 创建 wormhole_systems.json 数据文件（爬取 Anoik.is）
2. 创建 wormhole_types.json 数据文件
3. 创建 WormholeService 服务
4. 实现虫洞搜索标签栏
5. 实现五个信息栏展示

### Phase 2（扩展功能）
1. 实现虫洞检索标签栏
2. 实现洞点快查标签栏
3. 实现击杀数据获取
4. 添加筛选和分页功能

### Phase 3（优化）
1. 预生成静态JSON文件
2. 前端性能优化
3. HTTP缓存头配置
4. 添加 wormhole_anomalies.json 和 wormhole_effects.json

---

## 十一、数据采集说明

### 11.1 Anoik.is 数据爬取
- 虫洞星系列表：`https://anoik.is/systems`
- 虫洞类型列表：`https://anoik.is/wormholes`
- 单个星系详情：`https://anoik.is/systems/J100033`
- 单个虫洞详情：`https://anoik.is/wormholes/B274`

### 11.2 数据验证
- 与 Ellatha 数据交叉验证
- 与 Venus 数据对比验证（静态连接）
- 本地数据一致性检查

### 11.3 数据更新频率
- 虫洞静态数据：极少变化（每年0-1次）
- 虫洞类型参数：极少变化
- 建议每季度检查一次是否有更新

---

## 十二、注意事项

### 12.1 数据一致性
- 虫洞系统ID范围：31000001-31002605
- 虫洞区域ID范围：11000001-11000033
- 星系编号格式：J + 六位数字

### 12.2 禁止事项
- 不使用第三方非官方API（遵循项目约束）
- 不实时查询玩家当前位置的虫洞状态（无API支持）
- 不显示虫洞剩余质量百分比（无法获取实时数据）

### 12.3 击杀数据限制
- 仅显示最近5条击杀记录
- 提供跳转到KB网站的链接查看更多
- 使用项目已有的 BetaKbApiClient 获取数据

---

**文档版本**: 1.0
**创建日期**: 2024-01-XX
**作者**: 图斯 (Tus)