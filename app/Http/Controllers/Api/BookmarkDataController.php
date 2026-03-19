<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Helpers\EveHelper;

class BookmarkDataController extends Controller
{
    /**
     * 获取书签数据
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $characterId = $user->eve_character_id;
        $token = $user->access_token;
        $baseUrl = config('esi.base_url');

        try {
            // 获取书签文件夹
            $folders = $this->getFolders($characterId, $token, $baseUrl);
            
            // 获取书签列表（支持分页）
            $bookmarks = Cache::remember("bookmarks_{$characterId}", 300, function () use ($baseUrl, $characterId, $token) {
                $allBookmarks = [];
                $page = 1;
                
                do {
                    $response = Http::withToken($token)
                        ->timeout(15)
                        ->get("{$baseUrl}characters/{$characterId}/bookmarks/", [
                            'datasource' => 'serenity',
                            'page' => $page
                        ]);
                    
                    if (!$response->ok()) {
                        break;
                    }
                    
                    $data = $response->json();
                    if (empty($data)) {
                        break;
                    }
                    
                    $allBookmarks = array_merge($allBookmarks, $data);
                    $page++;
                    
                    $totalPages = (int) $response->header('X-Pages', 1);
                } while ($page <= $totalPages);
                
                return $allBookmarks;
            });

            if (empty($bookmarks)) {
                return response()->json([
                    'folders' => $folders,
                    'bookmarks' => []
                ]);
            }

            // 获取星系名称
            $systemIds = array_filter(array_unique(array_map(function($b) {
                return $b['location']['solar_system_id'] ?? ($b['target']['solar_system_id'] ?? null);
            }, $bookmarks)));
            
            $systemNames = $this->getSystemNames(array_values($systemIds));

            // 按文件夹分组
            $folderMap = [];
            foreach ($folders as $folder) {
                $folderMap[$folder['folder_id']] = $folder['name'];
            }

            // 构建返回数据
            $result = [];
            foreach ($bookmarks as $bookmark) {
                $folderId = $bookmark['folder_id'] ?? 0;
                $systemId = $bookmark['location']['solar_system_id'] ?? ($bookmark['target']['solar_system_id'] ?? 0);
                
                $result[] = [
                    'bookmark_id' => $bookmark['bookmark_id'] ?? 0,
                    'folder_id' => $folderId,
                    'folder_name' => $folderMap[$folderId] ?? '个人地点',
                    'label' => $bookmark['label'] ?? '',
                    'notes' => $bookmark['notes'] ?? '',
                    'created' => $bookmark['created'] ?? '',
                    'creator_id' => $bookmark['creator_id'] ?? 0,
                    'solar_system_id' => $systemId,
                    'solar_system_name' => $systemNames[$systemId] ?? "星系 #{$systemId}",
                    'coordinates' => $bookmark['coordinates'] ?? null,
                    'item' => $bookmark['item'] ?? null,
                ];
            }

            // 按文件夹分组排序
            usort($result, function($a, $b) {
                if ($a['folder_id'] !== $b['folder_id']) {
                    return $a['folder_id'] - $b['folder_id'];
                }
                return strcmp($a['label'], $b['label']);
            });

            return response()->json([
                'folders' => $folders,
                'bookmarks' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => '获取书签数据失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 获取书签文件夹
     */
    public function folders(Request $request)
    {
        $user = $request->user();
        $characterId = $user->eve_character_id;
        $token = $user->access_token;
        $baseUrl = config('esi.base_url');

        try {
            $folders = $this->getFolders($characterId, $token, $baseUrl);
            return response()->json($folders);
        } catch (\Exception $e) {
            return response()->json(['error' => '获取文件夹失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 获取文件夹数据
     */
    private function getFolders($characterId, $token, $baseUrl): array
    {
        return Cache::remember("bookmark_folders_{$characterId}", 300, function () use ($baseUrl, $characterId, $token) {
            $response = Http::withToken($token)
                ->timeout(15)
                ->get("{$baseUrl}characters/{$characterId}/bookmarks/folders/", [
                    'datasource' => 'serenity'
                ]);
            
            if ($response->ok()) {
                return $response->json();
            }
            return [];
        });
    }

    /**
     * 批量获取星系名称
     */
    private function getSystemNames(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $names = [];
        foreach ($ids as $id) {
            $name = EveHelper::getNameById($id, 'system');
            if ($name) {
                $names[$id] = $name;
            }
        }
        return $names;
    }
}
