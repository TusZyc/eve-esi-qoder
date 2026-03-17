#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""Test system distance calculation"""

import urllib.request
import json
from collections import defaultdict
import math

base_url = 'https://ali-esi.evepc.163.com/latest/'

def get_system_name(system_id, language='en'):
    """Get system name"""
    try:
        url = f'{base_url}universe/systems/{system_id}/?datasource=serenity&language={language}'
        req = urllib.request.Request(url, headers={'User-Agent': 'Python/EVE'})
        with urllib.request.urlopen(req, timeout=10) as response:
            data = json.loads(response.read().decode('utf-8'))
            return data.get('name', 'Unknown')
    except Exception as e:
        return f'Error: {e}'

def get_system_stargates(system_id):
    """Get system stargates"""
    try:
        url = f'{base_url}universe/systems/{system_id}/?datasource=serenity'
        req = urllib.request.Request(url, headers={'User-Agent': 'Python/EVE'})
        with urllib.request.urlopen(req, timeout=10) as response:
            data = json.loads(response.read().decode('utf-8'))
            return data.get('stargates', [])
    except Exception as e:
        print(f'Error getting stargates for {system_id}: {e}')
        import traceback
        traceback.print_exc()
        return []

def get_stargate_destination(gate_id):
    """Get stargate destination system ID"""
    try:
        url = f'{base_url}universe/stargates/{gate_id}/?datasource=serenity'
        req = urllib.request.Request(url, headers={'User-Agent': 'Python/EVE'})
        with urllib.request.urlopen(req, timeout=10) as response:
            data = json.loads(response.read().decode('utf-8'))
            return data.get('destination', {}).get('system_id')
    except Exception as e:
        return None

def bfs_find_path(adjacency, start, end, max_depth=15):
    """BFS find shortest path"""
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
    """Calculate Euclidean distance in light years"""
    dx = coord2[0] - coord1[0]
    dy = coord2[1] - coord1[1]
    dz = coord2[2] - coord1[2]
    distance_meters = math.sqrt(dx*dx + dy*dy + dz*dz)
    return distance_meters / 9.461e+15

def get_system_coordinates(system_id):
    """Get system coordinates"""
    try:
        url = f'{base_url}universe/systems/{system_id}/?datasource=serenity'
        req = urllib.request.Request(url, headers={'User-Agent': 'Python/EVE'})
        with urllib.request.urlopen(req, timeout=10) as response:
            data = json.loads(response.read().decode('utf-8'))
            pos = data.get('position', {})
            return (pos.get('x', 0), pos.get('y', 0), pos.get('z', 0))
    except:
        return None

# Main test
print('=== System Distance Test ===\n')

# Test systems
test_systems = [
    (30000142, 'Jita'),
    (30002187, 'Amarr'),
]

# Get system info
for sys_id, name in test_systems:
    print(f'{name} (ID: {sys_id}):')
    print(f'  EN name: {get_system_name(sys_id, "en")}')
    print(f'  ZH name: {get_system_name(sys_id, "zh")}')
    
    coords = get_system_coordinates(sys_id)
    if coords:
        print(f'  Coordinates: ({coords[0]:.2e}, {coords[1]:.2e}, {coords[2]:.2e})')
    print()

# Get connections
print('Getting connections...\n')

adjacency = defaultdict(set)

for sys_id, name in test_systems:
    stargates = get_system_stargates(sys_id)
    print(f'{name} has {len(stargates)} stargates')
    
    for gate in stargates[:7]:
        dest = get_stargate_destination(gate)
        if dest and dest != sys_id:
            adjacency[sys_id].add(dest)
            adjacency[dest].add(sys_id)
            print(f'  {name} -> gate {gate} -> {get_system_name(dest, "en")} (ID: {dest})')

print(f'\nAdjacency contains {len(adjacency)} systems')

# Test path
print('\n=== Path Test ===\n')

path = bfs_find_path(dict(adjacency), 30000142, 30002187)

if path:
    print(f'Path found: {path}')
    print(f'Jump count: {len(path) - 1}')
    
    print('\nPath details:')
    for i, sys_id in enumerate(path):
        name = get_system_name(sys_id, 'en')
        coords = get_system_coordinates(sys_id)
        print(f'{i}. {name} (ID: {sys_id})')
        if coords:
            print(f'    Coordinates: ({coords[0]:.2e}, {coords[1]:.2e}, {coords[2]:.2e})')
            if i > 0:
                prev_coords = get_system_coordinates(path[i-1])
                if prev_coords:
                    dist = calculate_euclidean_distance(prev_coords, coords)
                    print(f'    Distance: {dist:.4f} light years')
else:
    print('Path not found')
