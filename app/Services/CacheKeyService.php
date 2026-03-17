<?php

namespace App\Services;

/**
 * 统一缓存键管理服务
 * 
 * 提供标准化的缓存键和 TTL 常量，避免硬编码和键名冲突
 */
class CacheKeyService
{
    // TTL 常量（秒）
    const TTL_SHORT = 300;       // 5分钟
    const TTL_MEDIUM = 3600;     // 1小时
    const TTL_LONG = 86400;      // 24小时
    const TTL_WEEK = 604800;     // 7天
    
    /**
     * 服务器状态缓存键
     */
    public static function serverStatus(): string
    {
        return 'eve:server_status';
    }
    
    /**
     * 角色完整数据缓存键
     */
    public static function characterData(int $charId): string
    {
        return "char:full_data:{$charId}";
    }
    
    /**
     * 角色资产缓存键
     */
    public static function assets(int $charId): string
    {
        return "char:assets:{$charId}";
    }
    
    /**
     * 位置信息缓存键
     */
    public static function locationInfo(int $locId): string
    {
        return "eve:locinfo:{$locId}";
    }
    
    /**
     * 空间站中文名缓存键
     */
    public static function stationName(string $name, int $sysId, int $ownerId): string
    {
        return "eve:station_zh:" . md5("{$name}_{$sysId}_{$ownerId}");
    }
    
    /**
     * 技能类别分组缓存键
     */
    public static function skillCategoryGroups(): string
    {
        return 'eve:skill_category_groups';
    }
    
    /**
     * 技能分组完整信息缓存键
     */
    public static function skillGroupFull(int $groupId): string
    {
        return "eve:skillgroup_full:{$groupId}";
    }
    
    /**
     * KM 自动补全缓存键
     */
    public static function kmAutocomplete(string $query, string $type): string
    {
        return "kb:ac:" . md5("{$type}:{$query}");
    }
    
    /**
     * 星系名称缓存键
     */
    public static function solarSystemName(int $systemId): string
    {
        return "eve:system_name:{$systemId}";
    }
    
    /**
     * 角色资产位置物品缓存键
     */
    public static function assetLocationItems(int $charId, int $locationId): string
    {
        return "char:asset_loc:{$charId}:{$locationId}";
    }
    
    /**
     * 角色技能数据缓存键
     */
    public static function characterSkills(int $charId): string
    {
        return "char:skills:{$charId}";
    }
    
    /**
     * 角色技能队列缓存键
     */
    public static function characterSkillQueue(int $charId): string
    {
        return "char:skillqueue:{$charId}";
    }
    
    /**
     * 类型名称缓存键
     */
    public static function typeName(int $typeId): string
    {
        return "eve:typename:{$typeId}";
    }
    
    /**
     * 类型详情缓存键
     */
    public static function typeDetail(int $typeId): string
    {
        return "eve:type:{$typeId}";
    }
    
    /**
     * 分组信息缓存键
     */
    public static function groupInfo(int $groupId): string
    {
        return "eve:groupinfo_zh:{$groupId}";
    }
    
    /**
     * 实体名称缓存键
     */
    public static function entityName(int $entityId): string
    {
        return "eve:name:{$entityId}";
    }
}
