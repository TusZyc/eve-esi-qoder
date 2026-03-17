# 星系距离计算服务指南

## 概述

`SystemDistanceService` 是一个基于星系连接关系的.jump 路径计算服务，它使用 BFS 算法计算两个星系之间的最短跳跃路径。

## 功能特点

### 1. 最短跳跃路径计算
- 使用 BFS 算法寻找最短路径
- 数据来源：Fuzzwork SDE 数据 (mapSolarSystemJumps)
- 自动处理星系连接关系

### 2. 欧几里得距离计算
- 基于 ESI API 的星系坐标
- 计算空间中的直线距离
- 支持米和光年两种单位

### 3. 星系信息查询
- 星系名称(支持中英文)
- 星系坐标(x, y, z)
- 批量查询支持

## 数据源

### SDE 数据
- **来源**: Fuzzwork `mapSolarSystemJumps.csv`
- **文件位置**: `data/solar_system_jumps.json`
- **数据量**: 5,215 个星系，13,776 个 stargate 连接
- **更新频率**: 可通过 `scripts/update_system_jumps.php` 更新

### ESI 数据
- **来源**: EVE Swagger Interface
- **端点**: `/universe/systems/{system_id}/`
- **数据**: 星系坐标、名称、状态等

## 安装

数据文件已自动下载并存储在:
```
d:\Qoder-work\eve-esi-qoder\data\solar_system_jumps.json
```

如果需要重新下载数据:

```bash
php scripts/update_system_jumps.php
```

## API 端点

### 1. 计算最短跳跃路径

**端点**: `/api/system-distance/path`

**参数**:
- `from`: 起始星系 ID (required)
- `to`: 目标星系 ID (required)

**示例**:
```
GET /api/system-distance/path?from=30000142&to=30002187
```

**响应**:
```json
{
  "success": true,
  "jump_count": 3,
  "path": [30000142, 30000143, 30000144, 30002187],
  "path_details": {
    "jump_count": 3,
    "total_euclidean_distance": 15.23,
    "systems": [
      {
        "system_id": 30000142,
        "name": "Jita",
        "coordinates": {...},
        "index": 0
      }
    ]
  }
}
```

### 2. 计算欧几里得距离

**端点**: `/api/system-distance/euclidean`

**参数**:
- `from`: 星系 ID 1 (required)
- `to`: 星系 ID 2 (required)

**示例**:
```
GET /api/system-distance/euclidean?from=30000142&to=30002187
```

**响应**:
```json
{
  "success": true,
  "from_system_id": 30000142,
  "to_system_id": 30002187,
  "distance": {
    "meters": 191848049358182016,
    "light_years": 20.276543
  }
}
```

### 3. 获取星系名称

**端点**: `/api/system-distance/name`

**参数**:
- `id`: 星系 ID (required)
- `language`: 语言代码, `zh` 或 `en` (optional, default: `zh`)

**示例**:
```
GET /api/system-distance/name?id=30000142&language=zh
```

**响应**:
```json
{
  "success": true,
  "system_id": 30000142,
  "name": "Jita",
  "language": "zh"
}
```

### 4. 批量查询

**端点**: `/api/system-distance/batch`

**参数**:
- `ids`: 逗号分隔的星系 ID 列表 (required)
- `language`: 语言代码 (optional)

**示例**:
```
GET /api/system-distance/batch?ids=30000142,30002187,30000143&language=zh
```

**响应**:
```json
{
  "success": true,
  "count": 3,
  "systems": [
    {
      "system_id": 30000142,
      "name": "Jita",
      "coordinates": {"x": -1.29064861735e+17, "y": 6.075530691e+16, "z": 1.1746922706e+17}
    }
  ]
}
```

## 使用示例

### PHP 使用示例

```php
use App\Services\SystemDistanceService;

$service = new SystemDistanceService();

// 1. 计算最短跳跃路径
list($distance, $path) = $service->getPathDistance(30000142, 30002187);
echo "跳跃次数: " . $distance . "\n";
echo "路径: " . implode(' -> ', $path) . "\n";

// 2. 获取详细路径信息
$details = $service->getPathDetails($path);
echo "总欧几里得距离: " . $details['total_euclidean_distance'] . " 光年\n";

// 3. 计算欧几里得距离
$distanceLy = $service->getEuclideanDistanceLightYears(30000142, 30002187);
echo "直线距离: " . round($distanceLy, 2) . " 光年\n";

// 4. 获取星系名称
$name = $service->getSystemName(30000142, 'zh');
echo "星系名称: " . $name . "\n";

// 5. 获取星系坐标
$coords = $service->getSystemCoordinates(30000142);
print_r($coords);
```

### JavaScript 使用示例

```javascript
// 计算最短跳跃路径
async function calculatePath(from, to) {
    const response = await fetch(`/api/system-distance/path?from=${from}&to=${to}`);
    const data = await response.json();
    
    if (data.success) {
        console.log(`跳跃次数: ${data.jump_count}`);
        console.log(`路径: ${data.path.join(' -> ')}`);
        
        // 显示路径详情
        data.path_details.systems.forEach((sys, index) => {
            console.log(`${index}. ${sys.name} (ID: ${sys.system_id})`);
        });
    }
}

calculatePath(30000142, 30002187);
```

### Web 测试页面

访问: `http://your-domain/system-distance`

提供完整的交互式界面来测试所有功能:

1. **计算最短跳跃路径**: 输入起始和目标星系 ID
2. **计算欧几里得距离**: 查看空间中的直线距离
3. **查询星系信息**: 获取星系名称和坐标
4. **批量查询**: 一次性查询多个星系

## 技术细节

### BFS 算法实现

`SystemDistanceService::getPathDistance()` 使用 BFS(Breadth-First Search)算法:

1. 从起始星系开始
2. 将所有邻居加入队列
3. 记录每个星系的前驱节点
4. 找到目标后重构路径

**时间复杂度**: O(V + E)，其中 V 是星系数量，E 是连接数量

**空间复杂度**: O(V)，存储访问状态和前驱节点

### 缓存机制

- **星系名称**: 24 小时缓存
- **星系坐标**: 24 小时缓存
- **邻接表**: 24 小时缓存

### 数据更新

SDE 数据可以通过以下方式更新:

```bash
# 下载最新的 SDE 数据
php -r "
\$csv = file_get_contents('https://www.fuzzwork.co.uk/dump/latest/mapSolarSystemJumps.csv');
\$lines = explode('\n', \$csv);
\$adjacency = [];

foreach (\$lines as \$line) {
    if (trim(\$line) && strpos(\$line, 'from') !== 0) {
        \$parts = explode(',', \$line);
        if (count(\$parts) >= 4) {
            \$from = (int)\$parts[2];
            \$to = (int)\$parts[3];
            \$adjacency[\$from][] = \$to;
            \$adjacency[\$to][] = \$from;  // 双向连接
        }
    }
}

// 去重并保存
foreach (\$adjacency as &\$conns) {
    \$conns = array_unique(\$conns);
}
\$adjacency = array_map('array_unique', \$adjacency);

file_put_contents('data/solar_system_jumps.json', json_encode(\$adjacency, JSON_PRETTY_PRINT));
"
```

## 性能优化

1. **使用邻接表**: O(1) 查找邻居
2. **BFS 算法**: 保证找到最短路径
3. **缓存机制**: 减少重复的 ESI API 调用
4. **延迟加载**: 仅在需要时获取星系名称和坐标

## 注意事项

1. **跳数 ≠ 距离**: 星系跳跃次数不等于地理距离
2. **跳跃范围**: 实际跳跃受舰船类型和装备限制
3. **数据更新**: SDE 数据定期更新，建议每月检查
4. **ESI 限流**: 注意 API 限流策略，避免过度请求

## 相关资源

- [EVE Online 官方文档](https://developers.eveonline.com/)
- [Fuzzwork SDE](https://www.fuzzwork.co.uk/dump/)
- [ EVE Swagger Interface](https://esi.evetech.net/)
- [EVE based 网站](https://eve.sgfans.org/)

## License

MIT License
