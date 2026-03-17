#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
快速查询两个星系的距离
"""

import urllib.request
import json
from collections import defaultdict
import math

base_url = 'https://ali-esi.evepc.163.com/latest/'

def get_system_name(system_id, language='zh'):
    try:
        url = f'{base_url}universe/systems/{system_id}/?datasource=serenity&language={language}'
        req = urllib.request.Request(url, headers={'User-Agent': 'Python/EVE'})
        with urllib.request.urlopen(req, timeout=10) as response:
            data = json.loads(response.read().decode('utf-8'))
            return data.get('name', 'Unknown')
    except:
        return f'Error'

def get_system_stargates(system_id):
    try:
        url = f'{base_url}universe/systems/{system_id}/?datasource=serenity'
        req = urllib.request.Request(url, headers={'User-Agent': 'Python/EVE'})
        with urllib.request.urlopen(req, timeout=10) as response:
            data = json.loads(response.read().decode('utf-8'))
            return data.get('stargates', [])
    except:
        return []

def get_stargate_destination(gate_id):
    try:
        url = f'{base_url}universe/stargates/{gate_id}/?datasource=serenity'
        req = urllib.request.Request(url, headers={'User-Agent': 'Python/EVE'})
        with urllib.request.urlopen(req, timeout=10) as response:
            data = json.loads(response.read().decode('utf-8'))
            return data.get('destination', {}).get('system_id')
    except:
        return None

def get_system_adjacency(system_id, max_gates=20):
    stargates = get_system_stargates(system_id)
    neighbors = set()
    for gate in stargates[:max_gates]:
        dest = get_stargate_destination(gate)
        if dest and dest != system_id:
            neighbors.add(dest)
    return list(neighbors)

def bfs_find_path(adjacency, start, end, max_depth=15):
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
    dx = coord2[0] - coord1[0]
    dy = coord2[1] - coord1[1]
    dz = coord2[2] - coord1[2]
    distance_meters = math.sqrt(dx*dx + dy*dy + dz*dz)
    return distance_meters / 9.461e+15

def get_system_coordinates(system_id):
    try:
        url = f'{base_url}universe/systems/{system_id}/?datasource=serenity'
        req = urllib.request.Request(url, headers={'User-Agent': 'Python/EVE'})
        with urllib.request.urlopen(req, timeout=10) as response:
            data = json.loads(response.read().decode('utf-8'))
            pos = data.get('position', {})
            return (pos.get('x', 0), pos.get('y', 0), pos.get('z', 0))
    except:
        return None

def calculate_distance(system1_id, system2_id, verbose=True):
    """计算两个星系之间的距离"""
    
    # 获取初始邻接表
    adjacency = defaultdict(list)
    
    # 起始星系
    neighbors1 = get_system_adjacency(system1_id, max_gates=20)
    if neighbors1:
        adjacency[system1_id] = neighbors1
        for neighbor in neighbors1:
            adjacency[neighbor].append(system1_id)
    
    # 目标星系
    neighbors2 = get_system_adjacency(system2_id, max_gates=20)
    if neighbors2:
        adjacency[system2_id] = neighbors2
        for neighbor in neighbors2:
            if system2_id not in adjacency[neighbor]:
                adjacency[neighbor].append(system2_id)
    
    # 尝试寻找路径
    path = bfs_find_path(dict(adjacency), system1_id, system2_id, 15)
    
    if not path:
        # 扩展
        all_systems = list(adjacency.keys())
        for sys_id in all_systems[:30]:
            neighbors = get_system_adjacency(sys_id, max_gates=20)
            if neighbors:
                adjacency[sys_id] = list(set(adjacency[sys_id] + neighbors))
                for neighbor in neighbors:
                    if sys_id not in adjacency[neighbor]:
                        adjacency[neighbor].append(sys_id)
        
        path = bfs_find_path(dict(adjacency), system1_id, system2_id, 15)
    
    if path:
        jump_count = len(path) - 1
        
        # 计算距离
        total_distance = 0
        prev_coords = None
        
        for sys_id in path:
            coords = get_system_coordinates(sys_id)
            if coords and prev_coords:
                dist = calculate_euclidean_distance(prev_coords, coords)
                total_distance += dist
            if coords:
                prev_coords = coords
        
        return {
            'success': True,
            'path': path,
            'jump_count': jump_count,
            'total_distance': total_distance,
            'systems': []
        }
    else:
        return {'success': False}

def main():
    # 埃玛马克和艾瑟特 - 需要确认正确的名称或 ID
    # 请确认这两个星系的确切名称或 ID
    
    # 示例测试
    print("星系距离计算器")
    print("="*50)
    
    # 尝试用户提供的星系
    # 请替换为实际的星系 ID
    
    print("\n使用示例:")
    print("1. Jita (30000142) -> Amarr (30002187)")
    
    try:
        result = calculate_distance(30000142, 30002187)
        
        if result['success']:
            path = result['path']
            print(f"路径: {' -> '.join(map(str, path))}")
            print(f"跳跃次数: {result['jump_count']}")
            print(f"总距离: {result['total_distance']:.2f} 光年")
            
            print("\n路径详情:")
            for i, sys_id in enumerate(path):
                name_zh = get_system_name(sys_id, 'zh')
                name_en = get_system_name(sys_id, 'en')
                print(f"  {i}. {name_en} ({name_zh}) - ID: {sys_id}")
        else:
            print("未找到路径")
    except Exception as e:
        print(f"错误: {e}")
    
    print("\n" + "="*50)
    print("请提供 '埃玛马克' 和 '艾瑟特' 的正确星系 ID 或名称")
    print("你可以告诉我:")
    print("  1. 两个星系的中文名称(确保拼写正确)")
    print("  2. 或者星系 ID")
    print("  3. 或者英文名称")

if __name__ == "__main__":
    main()
