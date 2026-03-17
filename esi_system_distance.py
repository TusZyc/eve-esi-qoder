#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
星系距离计算器 - 使用纯 ESI API
完全基于 ESI /universe/stargates/ 端点动态获取星系连接关系
"""

import urllib.request
import json
from collections import defaultdict
import math
import sys

# 使用中国服务器 ESI
base_url = 'https://ali-esi.evepc.163.com/latest/'

def get_system_name(system_id, language='en'):
    """获取星系名称"""
    try:
        url = f'{base_url}universe/systems/{system_id}/?datasource=serenity&language={language}'
        req = urllib.request.Request(url, headers={'User-Agent': 'Python/EVE'})
        with urllib.request.urlopen(req, timeout=10) as response:
            data = json.loads(response.read().decode('utf-8'))
            return data.get('name', 'Unknown')
    except Exception as e:
        return f'Error'

def get_system_coordinates(system_id):
    """获取星系坐标"""
    try:
        url = f'{base_url}universe/systems/{system_id}/?datasource=serenity'
        req = urllib.request.Request(url, headers={'User-Agent': 'Python/EVE'})
        with urllib.request.urlopen(req, timeout=10) as response:
            data = json.loads(response.read().decode('utf-8'))
            pos = data.get('position', {})
            return (pos.get('x', 0), pos.get('y', 0), pos.get('z', 0))
    except:
        return None

def get_system_stargates(system_id):
    """获取星系的星门列表"""
    try:
        url = f'{base_url}universe/systems/{system_id}/?datasource=serenity'
        req = urllib.request.Request(url, headers={'User-Agent': 'Python/EVE'})
        with urllib.request.urlopen(req, timeout=10) as response:
            data = json.loads(response.read().decode('utf-8'))
            return data.get('stargates', [])
    except:
        return []

def get_stargate_destination(gate_id):
    """获取星门的目标星系 ID"""
    try:
        url = f'{base_url}universe/stargates/{gate_id}/?datasource=serenity'
        req = urllib.request.Request(url, headers={'User-Agent': 'Python/EVE'})
        with urllib.request.urlopen(req, timeout=10) as response:
            data = json.loads(response.read().decode('utf-8'))
            return data.get('destination', {}).get('system_id')
    except:
        return None

def get_system_adjacency(system_id, max_gates=20):
    """获取星系的邻接表(邻居列表)"""
    stargates = get_system_stargates(system_id)
    neighbors = set()
    
    for gate in stargates[:max_gates]:
        dest = get_stargate_destination(gate)
        if dest and dest != system_id:
            neighbors.add(dest)
    
    return list(neighbors)

def bfs_find_path(adjacency, start, end, max_depth=15):
    """BFS 寻找最短跳跃路径"""
    queue = [(start, [start])]
    visited = {start}
    
    while queue:
        current, path = queue.pop(0)
        
        if len(path) > max_depth:
            continue
            
        if current == end:
            return path
            
        neighbors = adjacency.get(current, [])
        for neighbor in neighbors:
            if neighbor not in visited:
                visited.add(neighbor)
                queue.append((neighbor, path + [neighbor]))
    
    return None

def calculate_euclidean_distance(coord1, coord2):
    """计算欧几里得距离(光年)"""
    dx = coord2[0] - coord1[0]
    dy = coord2[1] - coord1[1]
    dz = coord2[2] - coord1[2]
    distance_meters = math.sqrt(dx*dx + dy*dy + dz*dz)
    return distance_meters / 9.461e+15

def expand_adjacency(adjacency, extra_systems, max_gates_per_system=20):
    """扩展邻接表,获取更多星系连接"""
    for sys_id in extra_systems:
        if sys_id not in adjacency:
            neighbors = get_system_adjacency(sys_id, max_gates_per_system)
            if neighbors:
                adjacency[sys_id] = neighbors
                for neighbor in neighbors:
                    if neighbor not in adjacency:
                        adjacency[neighbor] = []
                    if sys_id not in adjacency[neighbor]:
                        adjacency[neighbor].append(sys_id)
    
    return adjacency

def calculate_distance(system1_id, system2_id, max_depth=15, extra_expansion=20):
    """
    计算两个星系之间的距离
    
    Args:
        system1_id: 第一个星系 ID
        system2_id: 第二个星系 ID
        max_depth: BFS 搜索最大深度
        extra_expansion: 额外扩展的星系数
        
    Returns:
        dict: 路径信息
    """
    print(f"计算 {system1_id} -> {system2_id} 的距离...\n")
    
    # Step 1: 获取初始星系的邻接表
    print("Step 1: 获取初始星系连接...")
    adjacency = defaultdict(list)
    
    # 获取起始星系的邻居
    neighbors1 = get_system_adjacency(system1_id, max_gates=20)
    if neighbors1:
        adjacency[system1_id] = neighbors1
        for neighbor in neighbors1:
            adjacency[neighbor].append(system1_id)
    
    # 获取目标星系的邻居
    neighbors2 = get_system_adjacency(system2_id, max_gates=20)
    if neighbors2:
        adjacency[system2_id] = neighbors2
        for neighbor in neighbors2:
            if system2_id not in adjacency[neighbor]:
                adjacency[neighbor].append(system2_id)
    
    print(f"  初始邻接表: {len(adjacency)} 个星系\n")
    
    # Step 2: 尝试寻找路径
    print("Step 2: 寻找路径...")
    path = bfs_find_path(dict(adjacency), system1_id, system2_id, max_depth)
    
    if path:
        jump_count = len(path) - 1
        print(f"  找到路径 (跳跃次数: {jump_count}):")
        print(f"  {' -> '.join(map(str, path))}\n")
        return build_path_details(path, max_depth)
    
    # Step 3: 如果没找到,扩展更多星系
    print(f"  未找到路径,扩展更多星系...")
    
    # 获取当前邻接表中所有星系的额外邻居
    all_systems = list(adjacency.keys())
    for sys_id in all_systems[:extra_expansion]:
        neighbors = get_system_adjacency(sys_id, max_gates=20)
        if neighbors:
            adjacency[sys_id] = list(set(adjacency[sys_id] + neighbors))
            for neighbor in neighbors:
                if sys_id not in adjacency[neighbor]:
                    adjacency[neighbor].append(sys_id)
    
    print(f"  扩展后邻接表: {len(adjacency)} 个星系\n")
    
    # Step 4: 再次尝试寻找路径
    path = bfs_find_path(dict(adjacency), system1_id, system2_id, max_depth)
    
    if path:
        jump_count = len(path) - 1
        print(f"  找到路径 (跳跃次数: {jump_count}):")
        print(f"  {' -> '.join(map(str, path))}\n")
        return build_path_details(path, max_depth)
    else:
        print(f"  仍然未找到路径")
        print(f"  当前邻接表包含 {len(adjacency)} 个星系\n")
        return None

def build_path_details(path, max_depth):
    """构建路径详情"""
    details = {
        'path': path,
        'jump_count': len(path) - 1,
        'systems': []
    }
    
    total_distance = 0
    prev_coords = None
    
    for i, sys_id in enumerate(path):
        name_en = get_system_name(sys_id, 'en')
        name_zh = get_system_name(sys_id, 'zh')
        coords = get_system_coordinates(sys_id)
        
        system_info = {
            'index': i,
            'id': sys_id,
            'name_en': name_en,
            'name_zh': name_zh,
            'coordinates': coords
        }
        
        details['systems'].append(system_info)
        
        # 计算距离
        if coords and prev_coords:
            dist = calculate_euclidean_distance(prev_coords, coords)
            total_distance += dist
            system_info['distance_from_prev'] = dist
        
        if coords:
            prev_coords = coords
    
    details['total_euclidean_distance'] = total_distance
    
    # 打印路径详情
    print("Detailed Path:")
    for sys_info in details['systems']:
        print(f"  {sys_info['index']}. {sys_info['name_en']} ({sys_info['name_zh']}) - ID: {sys_info['id']}")
        if sys_info['coordinates']:
            coords = sys_info['coordinates']
            print(f"      Coordinates: ({coords[0]:.2e}, {coords[1]:.2e}, {coords[2]:.2e})")
            if 'distance_from_prev' in sys_info:
                dist = sys_info['distance_from_prev']
                print(f"      Distance from previous: {dist:.4f} light years")
    
    print(f"\nTotal jumps: {details['jump_count']}")
    print(f"Total Euclidean distance: {details['total_euclidean_distance']:.4f} light years\n")
    
    return details

def main():
    """主函数 - 示例使用"""
    print("="*60)
    print("星系距离计算器 - 纯 ESI 版本")
    print("="*60)
    print()
    
    # 测试用例
    test_cases = [
        (30000142, 30002187, "Jita to Amarr"),
    ]
    
    for system1, system2, description in test_cases:
        print(f"Test: {description}")
        print("-"*60)
        result = calculate_distance(system1, system2, max_depth=15, extra_expansion=30)
        
        if result:
            print(f"SUCCESS! Path found with {result['jump_count']} jumps")
        else:
            print(f"FAILED to find path")
        
        print()
        print("="*60)
        print()

if __name__ == "__main__":
    main()
