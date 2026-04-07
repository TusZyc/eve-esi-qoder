<?php

return [
    /*
    |--------------------------------------------------------------------------
    | EVE ESI 配置
    |--------------------------------------------------------------------------
    */

    // API 基础 URL
    'base_url' => env('ESI_BASE_URL', 'https://ali-esi.evepc.163.com/latest/'),
    
    // OAuth2 端点
    'oauth_url' => env('ESI_OAUTH_URL', 'https://login.evepc.163.com/v2/oauth/'),
    
    // Client ID
    'client_id' => env('ESI_CLIENT_ID', 'bc90aa496a404724a93f41b4f4e97761'),
    
    // Client Secret（如果有）
    'client_secret' => env('ESI_CLIENT_SECRET', ''),
    
    // 回调 URL
    'redirect_uri' => env('ESI_REDIRECT_URI', 'http://localhost/callback'),
    
    // 数据源
    'datasource' => env('ESI_DATASOURCE', 'serenity'),
    
    /*
    |--------------------------------------------------------------------------
    | OAuth2 权限范围
    |--------------------------------------------------------------------------
    |
    | 这里列出需要申请的权限
    | 参考：https://esi.evetech.net/ui/
    |
    */
    'scopes' => [
        // 技能相关
        'esi-skills.read_skills.v1',
        'esi-skills.read_skillqueue.v1',
        
        // 角色信息
        'esi-characters.read_contacts.v1',
        'esi-characters.read_fatigue.v1',
        'esi-characters.read_medals.v1',
        'esi-characters.read_notifications.v1',
        'esi-characters.read_standings.v1',
        
        // 资产和钱包
        'esi-assets.read_assets.v1',
        'esi-assets.read_corporation_assets.v1',
        'esi-wallet.read_character_wallet.v1',
        
        // 位置
        'esi-location.read_location.v1',
        'esi-location.read_online.v1',
        
        // 市场
        'esi-markets.read_character_orders.v1',
        
        // 军团和联盟
        'esi-corporations.read_corporation_membership.v1',
        'esi-alliances.read_alliances.v1',
        
        // 其他
        'esi-ui.open_window.v1',

        // 克隆体和植入体
        'esi-clones.read_clones.v1',
        'esi-clones.read_implants.v1',

        // 书签
        'esi-bookmarks.read_character_bookmarks.v1',

        // 合同
        'esi-contracts.read_character_contracts.v1',

        // 装配
        'esi-fittings.read_fittings.v1',

        // 击毁报告
        'esi-killmails.read_killmails.v1',

        // LP点数
        'esi-characters.read_loyalty.v1',

        // 邮件相关
        'esi-mail.read_mail.v1',
        'esi-mail.send_mail.v1',
        'esi-mail.organize_mail.v1',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | 缓存设置
    |--------------------------------------------------------------------------
    */
    'cache' => [
        // 角色公开信息缓存时间（秒）
        'character_info' => 3600,
        
        // 技能队列缓存时间（秒）
        'skill_queue' => 300,
        
        // 资产缓存时间（秒）
        'assets' => 900,
        
        // 钱包缓存时间（秒）
        'wallet' => 300,
        
        // 市场订单缓存时间（秒）
        'orders' => 300,
        
        // 服务器状态缓存时间（秒）
        'status' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP 超时设置
    |--------------------------------------------------------------------------
    |
    | 统一管理 HTTP 请求超时时间，便于针对不同环境调整策略
    |
    */
    'timeouts' => [
        'quick' => 5,      // 快速查询（自动补全等）
        'normal' => 10,    // 标准查询
        'long' => 15,      // 耗时操作（图片生成、批量请求）
        'market' => 30,    // 市场价格查询
    ],
];
