import requests
import math

def get_system_id(system_name):
    """
    通过ESI API的/universe/ids/接口查询星系ID
    """
    url = "https://ali-esi.evepc.163.com/latest/universe/ids/"
    headers = {
        "Content-Type": "application/json",
        "Accept": "application/json"
    }
    payload = [system_name]
    
    response = requests.post(url, json=payload, headers=headers)
    if response.status_code == 200:
        data = response.json()
        if data and system_name.lower() in data:
            systems_info = data[system_name.lower()]
            # 寻找type为'solar_system'的条目
            for item in systems_info:
                if item.get('category') == 'solar_system':
                    return item['id']
    return None

def get_system_position(system_id):
    """
    通过ESI API的/universe/systems/{system_id}/接口获取星系坐标
    """
    url = f"https://ali-esi.evepc.163.com/latest/universe/systems/{system_id}/"
    headers = {
        "Accept": "application/json"
    }
    
    response = requests.get(url, headers=headers)
    if response.status_code == 200:
        data = response.json()
        if 'position' in data:
            return data['position']  # 返回包含x, y, z坐标的字典
    return None

def calculate_distance(pos1, pos2):
    """
    计算两个三维坐标点之间的直线距离
    """
    dx = pos2['x'] - pos1['x']
    dy = pos2['y'] - pos1['y']
    dz = pos2['z'] - pos1['z']
    distance = math.sqrt(dx**2 + dy**2 + dz**2)
    return distance

# 主程序
if __name__ == "__main__":
    # 要查询的星系名称
    system_names = ["埃玛马克", "艾瑟特"]
    
    positions = {}
    
    for name in system_names:
        print(f"正在查询 {name} 的信息...")
        
        # 获取星系ID
        system_id = get_system_id(name)
        if system_id:
            print(f"{name} 的ID是: {system_id}")
            
            # 获取星系坐标
            position = get_system_position(system_id)
            if position:
                positions[name] = position
                print(f"{name} 的坐标: x={position['x']:.2f}, y={position['y']:.2f}, z={position['z']:.2f}")
            else:
                print(f"无法获取 {name} 的坐标信息")
        else:
            print(f"无法获取 {name} 的ID")
    
    # 如果两个星系的信息都获取成功，则计算距离
    if len(positions) == 2:
        names = list(positions.keys())
        distance = calculate_distance(positions[names[0]], positions[names[1]])
        print(f"\n{names[0]} 和 {names[1]} 之间的直线距离为: {distance:.2f} 米")
    else:
        print("无法计算距离，因为未能获取到所有星系的坐标信息")