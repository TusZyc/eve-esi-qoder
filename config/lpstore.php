<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LP 商店配置
    |--------------------------------------------------------------------------
    */

    // 默认市场区域（伏尔戈）
    'default_region' => 10000002,

    // 缓存过期时间（秒）
    'cache_ttl' => [
        'factions' => 86400,    // 24小时
        'offers' => 3600,       // 1小时
        'prices' => 3600,       // 1小时
        'history' => 21600,     // 6小时
        'orders' => 300,        // 5分钟
    ],

    // ESI 基础 URL
    'esi_base_url' => 'https://ali-esi.evepc.163.com/latest',

    // 每页显示数量
    'per_page' => 25,

    // 主要市场区域
    'market_regions' => [
        10000002 => '伏尔戈 (The Forge)',
        10000043 => '多美 (Domain)',
        10000030 => '赫曼 (Heimatar)',
        10000032 => '金纳腾 (Sinq Laison)',
        10000042 => '美特伯里斯 (Metropolis)',
        10000064 => '精华之域 (Essence)',
        10000065 => '科斯 (Kor-Azor)',
        10000020 => '塔什蒙贡 (Tash-Murkon)',
        10000033 => '伏尔戈边境 (The Citadel)',
        10000016 => '洛内特雷克 (Lonetrek)',
    ],

    // 历史数据并发请求数
    'history_concurrent_requests' => 15,
];
