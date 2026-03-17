# EVE旗舰导航系统 - 技术实现记忆文件

> 本文档记录星系距离计算、旗舰跳跃能力、旗舰路径规划三个核心功能的技术实现细节，供AI搭建网站使用。

---

## 一、ESI API基础信息

### 1.1 API端点

| API | 方法 | 用途 | 关键字段 |
|-----|------|------|----------|
| `/universe/ids/` | POST | 名称转ID | 返回 `systems[{id, name}]` |
| `/universe/systems/{system_id}/` | GET | 获取星系信息 | `position{x,y,z}`, `security_status`, `stargates[]` |
| `/universe/stargates/{stargate_id}/` | GET | 获取星门连接 | `destination{system_id, stargate_id}` |
| `/universe/types/{type_id}/` | GET | 获取舰船属性 | `dogma_attributes[]` |
| `/dogma/attributes/{attribute_id}/` | GET | 获取属性定义 | 属性详情 |

### 1.2 国服ESI地址

```
https://ali-esi.evepc.163.com/latest/
```

### 1.3 请求示例

```javascript
// 名称转ID
const response = await fetch('https://ali-esi.evepc.163.com/latest/universe/ids/?datasource=serenity&language=zh', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(["艾瑟特", "维梅尼"])
});

// 获取星系信息
const system = await fetch('https://ali-esi.evepc.163.com/latest/universe/systems/30002084/?datasource=serenity&language=zh')
    .then(r => r.json());
```

---

## 二、功能一：星系距离计算

### 2.1 计算公式

EVE星系使用三维笛卡尔坐标系，单位为米。

```javascript
// 欧几里得距离公式
function calculateDistance(pos1, pos2) {
    const dx = pos2.x - pos1.x;
    const dy = pos2.y - pos1.y;
    const dz = pos2.z - pos1.z;
    const distanceMeters = Math.sqrt(dx*dx + dy*dy + dz*dz);
    
    // 转换为光年 (1光年 ≈ 9.461e15 米)
    const LIGHT_YEAR_IN_METERS = 9.461e15;
    return distanceMeters / LIGHT_YEAR_IN_METERS;
}
```

### 2.2 实现代码

```javascript
async function getSystemDistance(originName, destName) {
    // 1. 名称转ID
    const idsResp = await fetch(ESI_URL + '/universe/ids/?datasource=serenity&language=zh', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify([originName, destName])
    });
    const ids = await idsResp.json();
    
    const originId = ids.systems[0].id;
    const destId = ids.systems[1].id;
    
    // 2. 获取坐标
    const [origin, dest] = await Promise.all([
        fetch(ESI_URL + `/universe/systems/${originId}/?datasource=serenity&language=zh`).then(r => r.json()),
        fetch(ESI_URL + `/universe/systems/${destId}/?datasource=serenity&language=zh`).then(r => r.json())
    ]);
    
    // 3. 计算距离
    const distanceLY = calculateDistance(origin.position, dest.position);
    
    return {
        origin: { id: originId, name: origin.name, security: origin.security_status },
        destination: { id: destId, name: dest.name, security: dest.security_status },
        distanceLightYears: distanceLY,
        distanceMeters: distanceLY * 9.461e15
    };
}
```

---

## 三、功能二：旗舰跳跃能力计算

### 3.1 舰船基础属性

| 舰船类型 | Group ID | 基础跳跃范围 | 基础燃料消耗 | Dogma属性ID(跳跃范围) |
|----------|----------|-------------|-------------|----------------------|
| 战略货舰 | 902 | 5.0 光年 | 10000/光年 | 867 |
| 长须鲸级 | 883 | 5.0 光年 | 4000/光年 | 867 |
| 黑隐特勤舰 | 898 | 4.0 光年 | 700/光年 | 867 |
| 航空母舰 | 547 | 3.5 光年 | 3000/光年 | 867 |
| 无畏舰 | 485 | 3.5 光年 | 3000/光年 | 867 |
| 超级航母 | 659 | 3.0 光年 | 3000/光年 | 867 |
| 泰坦 | 30 | 3.0 光年 | 3000/光年 | 867 |

### 3.2 技能加成计算

```javascript
const SKILL_BONUSES = {
    // 跳跃引擎校对理论：每级+20%跳跃距离
    jdc: {
        0: 0, 1: 0.20, 2: 0.40, 3: 0.60, 4: 0.80, 5: 1.00
    },
    // 跳跃引擎能耗效率研究：每级-10%燃料消耗
    fuelEfficiency: {
        0: 0, 1: 0.10, 2: 0.20, 3: 0.30, 4: 0.40, 5: 0.50
    },
    // 战略货舰概论：每级-10%燃料消耗（仅战略货舰）
    jfSkill: {
        0: 0, 1: 0.10, 2: 0.20, 3: 0.30, 4: 0.40, 5: 0.50
    }
};

function calculateJumpCapability(shipType, jdcLevel, fuelEffLevel, jfLevel = 0) {
    // 基础属性
    const SHIP_DATA = {
        'Jump Freighter': { baseRange: 5.0, baseFuel: 10000 },
        'Dreadnought': { baseRange: 3.5, baseFuel: 3000 },
        'Carrier': { baseRange: 3.5, baseFuel: 3000 },
        'Supercarrier': { baseRange: 3.0, baseFuel: 3000 },
        'Titan': { baseRange: 3.0, baseFuel: 3000 },
        'Black Ops': { baseRange: 4.0, baseFuel: 700 },
        'Rorqual': { baseRange: 5.0, baseFuel: 4000 }
    };
    
    const ship = SHIP_DATA[shipType];
    
    // 计算跳跃范围
    const rangeMultiplier = 1 + SKILL_BONUSES.jdc[jdcLevel];
    const maxJumpRange = ship.baseRange * rangeMultiplier;
    
    // 计算燃料消耗率
    const fuelEffReduction = SKILL_BONUSES.fuelEfficiency[fuelEffLevel];
    const jfReduction = shipType === 'Jump Freighter' ? SKILL_BONUSES.jfSkill[jfLevel] : 0;
    const fuelRate = Math.ceil(ship.baseFuel * (1 - fuelEffReduction) * (1 - jfReduction));
    
    return {
        maxJumpRange: maxJumpRange,
        fuelRate: fuelRate,
        rangeBonus: `${(rangeMultiplier - 1) * 100}%`,
        fuelReduction: `${(fuelEffReduction + jfReduction) * 100}%`
    };
}
```

### 3.3 燃料消耗计算

```javascript
function calculateFuelConsumption(distanceLightYears, fuelRate) {
    return Math.ceil(distanceLightYears * fuelRate);
}

// 示例：战略货舰 JDC5 燃效5 JF5
const capability = calculateJumpCapability('Jump Freighter', 5, 5, 5);
// maxJumpRange: 10.0 光年
// fuelRate: 2500 单位/光年 (10000 * 0.5 * 0.5)

const fuel = calculateFuelConsumption(7.5, 2500); // 18750
```

---

## 四、功能三：旗舰路径规划

### 4.1 约束条件

```javascript
const NAVIGATION_CONSTRAINTS = {
    // 排除高安星系（安等 >= 0.5）
    isHighSec: (security) => Math.round(security * 10) / 10 >= 0.5,
    
    // 排除波赫文星域
    proviSystems: new Set([
        30000157, 30000192, 30001372, 30001445, 30002079, 30002737,
        30005005, 30010141, 30031392, 30000021, 30001413, 30002225,
        30002411, 30002770, 30003495, 30003504, 30040141, 30045328,
        30000206, 30001381, 30002652, 30002702, 30002797, 30003046,
        30005029, 30020141, 30045329
    ]),
    
    // 检查星系是否允许旗舰进入
    isAllowed: function(sys) {
        if (this.proviSystems.has(sys.system_id)) return false;
        return !this.isHighSec(sys.security_status);
    }
};
```

### 4.2 数据结构

```javascript
// 星系信息缓存
const SystemCache = {
    systems: {},      // {system_id: {id, name, position, security, stargates}}
    positions: {},    // {system_id: {x, y, z}}
    stargateGraph: {}, // {system_id: [neighbor_system_ids]}
    jumpGraph: {}     // {system_id: [{sysId, distance, fuel}]}
};

// 路径节点
interface PathNode {
    id: number;
    name: string;
    security: number;
    position: {x, y, z};
}

// 路径步骤
interface PathStep {
    type: 'jump' | 'stargate';
    from: number;
    to: number;
    fromName: string;
    toName: string;
    distance?: number;
    fuel: number;
}

// 路径结果
interface PathResult {
    found: boolean;
    jumps: number;
    totalFuel: number;
    totalDistance: number;
    path: PathStep[];
}
```

### 4.3 纯跳跃路径算法（BFS）

```javascript
/**
 * 寻找纯跳跃路径（最少跳跃次数）
 * 时间复杂度: O(V + E) 其中V为星系数量，E为跳跃边数量
 */
async function findJumpOnlyPath(originId, destId, maxJumpRange, fuelRate) {
    const visited = new Set();
    const queue = [{
        id: originId,
        path: [],
        totalFuel: 0,
        totalDistance: 0
    }];
    
    while (queue.length > 0) {
        const current = queue.shift();
        
        // 到达目标
        if (current.id === destId) {
            return {
                found: true,
                jumps: current.path.length,
                totalFuel: current.totalFuel,
                totalDistance: current.totalDistance,
                path: current.path
            };
        }
        
        if (visited.has(current.id)) continue;
        visited.add(current.id);
        
        // 获取跳跃可达星系
        const currentSys = SystemCache.systems[current.id];
        const reachable = getJumpReachable(currentSys.position, maxJumpRange);
        
        for (const next of reachable) {
            if (!visited.has(next.sysId) && NAVIGATION_CONSTRAINTS.isAllowed(next)) {
                queue.push({
                    id: next.sysId,
                    path: [...current.path, {
                        type: 'jump',
                        from: current.id,
                        to: next.sysId,
                        distance: next.distance,
                        fuel: next.fuel
                    }],
                    totalFuel: current.totalFuel + next.fuel,
                    totalDistance: current.totalDistance + next.distance
                });
            }
        }
        
        // 按燃料排序（可改为按距离）
        queue.sort((a, b) => a.totalFuel - b.totalFuel);
    }
    
    return { found: false, reason: '无法到达目标' };
}
```

### 4.4 组合路径算法（星门+跳跃）

```javascript
/**
 * 寻找组合路径（最小燃料消耗）
 * 使用Dijkstra算法，星门成本为0，跳跃成本为燃料消耗
 */
async function findHybridPath(originId, destId, maxJumpRange, fuelRate) {
    const distances = {};  // {sysId: {cost, path}}
    distances[originId] = { cost: 0, path: [] };
    
    const pq = [{ id: originId, cost: 0 }];
    const visited = new Set();
    
    while (pq.length > 0) {
        // 取出成本最低的节点
        pq.sort((a, b) => a.cost - b.cost);
        const current = pq.shift();
        
        if (current.id === destId) {
            return { found: true, ...distances[destId] };
        }
        
        if (visited.has(current.id)) continue;
        visited.add(current.id);
        
        const currentSys = SystemCache.systems[current.id];
        
        // === 探索星门连接（成本=0）===
        const gateNeighbors = SystemCache.stargateGraph[current.id] || [];
        for (const neighborId of gateNeighbors) {
            const neighborSys = SystemCache.systems[neighborId];
            
            // 旗舰不能进高安
            if (!NAVIGATION_CONSTRAINTS.isAllowed(neighborSys)) continue;
            
            const newCost = distances[current.id].cost; // 星门无燃料成本
            
            if (!distances[neighborId] || newCost < distances[neighborId].cost) {
                distances[neighborId] = {
                    cost: newCost,
                    path: [...distances[current.id].path, {
                        type: 'stargate',
                        from: current.id,
                        to: neighborId,
                        fromName: currentSys.name,
                        toName: neighborSys.name,
                        fuel: 0
                    }]
                };
                pq.push({ id: neighborId, cost: newCost });
            }
        }
        
        // === 探索跳跃连接（成本=燃料）===
        const jumpTargets = getJumpReachable(currentSys.position, maxJumpRange);
        for (const target of jumpTargets) {
            const targetSys = SystemCache.systems[target.sysId];
            
            if (!NAVIGATION_CONSTRAINTS.isAllowed(targetSys)) continue;
            
            const newCost = distances[current.id].cost + target.fuel;
            
            if (!distances[target.sysId] || newCost < distances[target.sysId].cost) {
                distances[target.sysId] = {
                    cost: newCost,
                    path: [...distances[current.id].path, {
                        type: 'jump',
                        from: current.id,
                        to: target.sysId,
                        distance: target.distance,
                        fuel: target.fuel
                    }]
                };
                pq.push({ id: target.sysId, cost: newCost });
            }
        }
    }
    
    return { found: false, reason: '无法到达目标' };
}

// 获取跳跃可达星系
function getJumpReachable(position, maxJumpRange) {
    const reachable = [];
    const LIGHT_YEAR_IN_METERS = 9.461e15;
    
    for (const [sysId, sys] of Object.entries(SystemCache.systems)) {
        const distance = calculateDistance(position, sys.position);
        
        if (distance <= maxJumpRange) {
            reachable.push({
                sysId: parseInt(sysId),
                distance: distance,
                fuel: Math.ceil(distance * fuelRate)
            });
        }
    }
    
    return reachable;
}
```

### 4.5 预计算跳跃图（优化性能）

```javascript
/**
 * 启动时预计算所有星系的跳跃可达性
 * 将计算时间从 O(n²) 分摊到启动阶段
 */
async function precomputeJumpGraph(maxJumpRange) {
    const systems = Object.values(SystemCache.systems);
    const graph = {};
    
    for (const sys of systems) {
        graph[sys.id] = [];
        
        for (const other of systems) {
            if (sys.id === other.id) continue;
            
            const distance = calculateDistance(sys.position, other.position);
            
            if (distance <= maxJumpRange) {
                graph[sys.id].push({
                    sysId: other.id,
                    distance: distance
                });
            }
        }
        
        // 按距离排序
        graph[sys.id].sort((a, b) => a.distance - b.distance);
    }
    
    SystemCache.jumpGraph = graph;
    return graph;
}
```

---

## 五、数据库设计

### 5.1 星系表

```sql
CREATE TABLE universe_systems (
    system_id INT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    constellation_id INT,
    region_id INT,
    security_status DECIMAL(10, 8),
    security_class VARCHAR(10),
    position_x BIGINT,
    position_y BIGINT,
    position_z BIGINT,
    star_id INT,
    stargates JSON,
    stations JSON,
    updated_at TIMESTAMP
);

CREATE INDEX idx_systems_region ON universe_systems(region_id);
CREATE INDEX idx_systems_security ON universe_systems(security_status);
CREATE INDEX idx_systems_name ON universe_systems(name);
```

### 5.2 星门表

```sql
CREATE TABLE universe_stargates (
    stargate_id BIGINT PRIMARY KEY,
    system_id INT NOT NULL,
    destination_system_id INT NOT NULL,
    destination_stargate_id BIGINT,
    position_x BIGINT,
    position_y BIGINT,
    position_z BIGINT,
    FOREIGN KEY (system_id) REFERENCES universe_systems(system_id),
    FOREIGN KEY (destination_system_id) REFERENCES universe_systems(system_id)
);

CREATE INDEX idx_stargates_system ON universe_stargates(system_id);
CREATE INDEX idx_stargates_destination ON universe_stargates(destination_system_id);
```

### 5.3 舰船属性表

```sql
CREATE TABLE ship_jump_attributes (
    type_id INT PRIMARY KEY,
    type_name VARCHAR(100),
    group_id INT,
    group_name VARCHAR(50),
    base_jump_range DECIMAL(4, 2),
    base_fuel_consumption INT,
    is_capital BOOLEAN DEFAULT TRUE
);

INSERT INTO ship_jump_attributes VALUES
(28844, 'Ark', 902, 'Jump Freighter', 5.0, 10000, TRUE),
(28846, 'Rhea', 902, 'Jump Freighter', 5.0, 10000, TRUE),
(23757, 'Archon', 547, 'Carrier', 3.5, 3000, TRUE),
(19720, 'Moros', 485, 'Dreadnought', 3.5, 3000, TRUE);
```

---

## 六、API接口设计

### 6.1 计算星系距离

```
POST /api/distance
Request:
{
    "origin": "艾瑟特",
    "destination": "维梅尼"
}

Response:
{
    "success": true,
    "data": {
        "origin": { "id": 30002084, "name": "艾瑟特", "security": 0.4 },
        "destination": { "id": 30002088, "name": "维梅尼", "security": 0.4 },
        "distance_light_years": 1.35,
        "distance_meters": 12750000000000000
    }
}
```

### 6.2 计算跳跃能力

```
POST /api/jump-capability
Request:
{
    "ship_type": "Jump Freighter",
    "jdc_level": 5,
    "fuel_efficiency_level": 5,
    "jf_skill_level": 5
}

Response:
{
    "success": true,
    "data": {
        "ship_type": "Jump Freighter",
        "base_jump_range": 5.0,
        "max_jump_range": 10.0,
        "base_fuel_rate": 10000,
        "actual_fuel_rate": 2500,
        "range_bonus_percent": 100,
        "fuel_reduction_percent": 75
    }
}
```

### 6.3 规划跳跃路径

```
POST /api/jump-route
Request:
{
    "origin": "Z-UZZN",
    "destination": "艾瑟特",
    "ship_type": "Jump Freighter",
    "jdc_level": 5,
    "fuel_efficiency_level": 5,
    "jf_skill_level": 5,
    "use_stargates": false,
    "avoid_provi": true
}

Response:
{
    "success": true,
    "data": {
        "found": true,
        "jumps": 5,
        "total_fuel": 286775,
        "total_distance": 42.48,
        "path": [
            {
                "step": 1,
                "from": { "id": 30003991, "name": "Z-UZZN", "security": -0.5 },
                "to": { "id": 30003943, "name": "T8H-66", "security": -0.3 },
                "type": "jump",
                "distance": 9.68,
                "fuel": 65371
            },
            // ... 更多步骤
        ]
    }
}
```

---

## 七、性能优化策略

### 7.1 缓存策略

| 数据类型 | 缓存位置 | 过期时间 | 更新策略 |
|----------|----------|----------|----------|
| 星系信息 | Redis/内存 | 24小时 | 后台定时刷新 |
| 星门连接 | Redis/内存 | 24小时 | 后台定时刷新 |
| 跳跃图 | Redis/内存 | 24小时 | 预计算 |
| 路径结果 | Redis | 1小时 | LRU淘汰 |

### 7.2 数据同步

```javascript
// 后台任务：每日同步ESI数据
async function syncUniverseData() {
    // 1. 获取所有星系列表
    const systemIds = await fetchAllSystemIds();
    
    // 2. 批量获取星系信息
    const batchSize = 100;
    for (let i = 0; i < systemIds.length; i += batchSize) {
        const batch = systemIds.slice(i, i + batchSize);
        const systems = await Promise.all(
            batch.map(id => fetchSystemInfo(id))
        );
        
        // 3. 存入数据库
        await System.upsertMany(systems);
    }
    
    // 4. 重建跳跃图
    await precomputeJumpGraph();
    
    // 5. 清除旧缓存
    await Cache.flush('jump_routes');
}
```

### 7.3 响应时间目标

| 功能 | 无缓存 | 有缓存 | 目标 |
|------|--------|--------|------|
| 星系距离 | 0.5-1秒 | <10ms | <100ms |
| 跳跃能力 | <10ms | <1ms | <10ms |
| 路径规划 | 15-30秒 | 50-200ms | <500ms |

---

## 八、前端组件示例

### 8.1 Vue组件

```vue
<template>
  <div class="jump-planner">
    <form @submit.prevent="calculateRoute">
      <!-- 起点终点 -->
      <div class="form-group">
        <label>起点星系</label>
        <input v-model="form.origin" placeholder="输入星系名称" />
      </div>
      
      <div class="form-group">
        <label>终点星系</label>
        <input v-model="form.destination" placeholder="输入星系名称" />
      </div>
      
      <!-- 舰船选择 -->
      <div class="form-group">
        <label>舰船类型</label>
        <select v-model="form.shipType">
          <option value="Jump Freighter">战略货舰</option>
          <option value="Dreadnought">无畏舰</option>
          <option value="Carrier">航空母舰</option>
          <option value="Black Ops">黑隐特勤舰</option>
        </select>
      </div>
      
      <!-- 技能等级 -->
      <div class="skills">
        <div class="skill">
          <label>跳跃引擎校对理论</label>
          <select v-model="form.jdcLevel">
            <option v-for="n in 6" :key="n-1" :value="n-1">{{ n-1 }}级</option>
          </select>
        </div>
        <!-- 更多技能... -->
      </div>
      
      <button type="submit" :disabled="loading">
        {{ loading ? '计算中...' : '计算路径' }}
      </button>
    </form>
    
    <!-- 结果展示 -->
    <div v-if="result" class="result">
      <div class="summary">
        <span>跳跃次数: {{ result.jumps }}</span>
        <span>总燃料: {{ result.total_fuel }}</span>
      </div>
      
      <div class="path">
        <div v-for="(step, i) in result.path" :key="i" class="step">
          {{ step.from.name }} → {{ step.to.name }}
          ({{ step.distance }}光年, {{ step.fuel }}燃料)
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      form: {
        origin: '',
        destination: '',
        shipType: 'Jump Freighter',
        jdcLevel: 5,
        fuelEfficiencyLevel: 5,
        jfSkillLevel: 5
      },
      loading: false,
      result: null
    };
  },
  
  methods: {
    async calculateRoute() {
      this.loading = true;
      try {
        const response = await axios.post('/api/jump-route', this.form);
        this.result = response.data.data;
      } catch (error) {
        alert('计算失败: ' + error.message);
      } finally {
        this.loading = false;
      }
    }
  }
};
</script>
```

---

## 九、错误处理

### 9.1 常见错误

| 错误类型 | 原因 | 处理方式 |
|----------|------|----------|
| 星系不存在 | 名称输入错误 | 提示用户检查输入 |
| 无法到达 | 目标在高安/波赫文 | 返回详细原因 |
| ESI超时 | API响应慢 | 重试+缓存 |
| 无可用路径 | 距离超出能力 | 建议中转点 |

### 9.2 错误响应格式

```json
{
    "success": false,
    "error": {
        "code": "DESTINATION_NOT_REACHABLE",
        "message": "目标星系位于高安区域，旗舰无法进入",
        "details": {
            "destination": "吉斯特",
            "security": 0.9,
            "required_security": "< 0.5"
        }
    }
}
```

---

## 十、附录

### 10.1 光年换算

```
1 光年 = 9.461 × 10^15 米
1 光年 = 9,461,000,000,000,000 米
```

### 10.2 安等计算

```javascript
// EVE安等显示为四舍五入到小数点后一位
function displaySecurity(security) {
    return Math.round(security * 10) / 10;
}

// 高安: >= 0.5
// 低安: < 0.5 且 > 0
// 00: <= 0
```

### 10.3 相关资源

- EVE国服ESI文档: https://ali-esi.evepc.163.com/ui
- EVE数据库: https://www.zkillboard.com/
- EVE地图工具: https://evemaps.dotlan.net/

---

**文档版本**: 1.0  
**最后更新**: 2026-03-16  
**适用项目**: EVE旗舰导航网站开发