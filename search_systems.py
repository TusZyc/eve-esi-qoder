#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""查找埃玛马克和艾瑟特星系"""

import urllib.request
import json

base_url = 'https://ali-esi.evepc.163.com/latest/'

print('Searching for systems...\n')

# Test different system IDs
test_systems = [
    (30005180, 'Eamark-1'),
    (30004680, 'Eamark-2'),
    (30004278, 'Aisette-1'),
    (30003278, 'Aisette-2'),
]

for sys_id, name in test_systems:
    try:
        url = f'{base_url}universe/systems/{sys_id}/?datasource=serenity&language=zh'
        req = urllib.request.Request(url, headers={'User-Agent': 'Python/EVE'})
        with urllib.request.urlopen(req, timeout=8) as response:
            data = json.loads(response.read().decode('utf-8'))
            print(f'{name} (ID: {sys_id}): {data["name"]}')
    except Exception as e:
        print(f'{name} (ID: {sys_id}): Not found')

print('\nIf none found, please provide:')
print('1. Correct Chinese names (check spelling)')
print('2. Or English names')
print('3. Or approximate location')
