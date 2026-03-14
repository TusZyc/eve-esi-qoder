<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\KillmailService;
use Illuminate\Http\Request;

class KillmailController extends Controller
{
    protected $killmailService;

    public function __construct(KillmailService $killmailService)
    {
        $this->killmailService = $killmailService;
    }

    /**
     * 渲染 KM 查询页面
     */
    public function index(Request $request)
    {
        return view('killmails.index', [
            'user' => $request->user(),
        ]);
    }

    /**
     * 自动补全搜索
     */
    public function autocomplete(Request $request)
    {
        $query = $request->input('q');
        $type = $request->input('type', 'character');

        if (empty($query) || mb_strlen($query) < 2) {
            return response()->json([
                'success' => false,
                'error' => 'query_too_short',
                'message' => '搜索关键词至少 2 个字符',
            ], 400);
        }

        $validTypes = ['character', 'corporation', 'alliance', 'ship', 'system'];
        if (!in_array($type, $validTypes)) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_type',
                'message' => '无效的搜索类型',
            ], 400);
        }

        try {
            $results = $this->killmailService->searchAutocomplete($query, $type);

            return response()->json([
                'success' => true,
                'data' => $results,
                'count' => count($results),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'search_failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 高级搜索 - 返回富化的 KM 列表
     */
    public function advancedSearch(Request $request)
    {
        $entityType = $request->input('entity_type');
        $entityId = $request->input('entity_id');

        if (empty($entityType) || empty($entityId)) {
            return response()->json([
                'success' => false,
                'error' => 'missing_params',
                'message' => '缺少搜索条件',
            ], 400);
        }

        $validTypes = ['pilot', 'corporation', 'alliance', 'ship', 'system'];
        if (!in_array($entityType, $validTypes)) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_entity_type',
                'message' => '无效的实体类型',
            ], 400);
        }

        try {
            $params = [
                'entity_type' => $entityType,
                'entity_id' => (int) $entityId,
                'involvement' => $request->input('involvement'),
                'ship_id' => $request->input('ship_id'),
                'system_id' => $request->input('system_id'),
                'time_start' => $request->input('time_start'),
                'time_end' => $request->input('time_end'),
            ];

            $results = $this->killmailService->advancedSearch($params);

            return response()->json([
                'success' => true,
                'data' => $results,
                'count' => count($results),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'search_failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 搜索角色 (保留旧接口)
     */
    public function search(Request $request)
    {
        $query = $request->input('q');

        if (empty($query) || strlen($query) < 2) {
            return response()->json([
                'success' => false,
                'error' => 'query_too_short',
                'message' => '搜索关键词至少 2 个字符',
            ], 400);
        }

        try {
            $results = $this->killmailService->searchCharacter($query);

            return response()->json([
                'success' => true,
                'data' => $results,
                'count' => count($results),
                'source' => 'kb-autocomplete+esi',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'search_failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 获取角色 KM 列表
     */
    public function pilotKills($pilotId, Request $request)
    {
        $mode = $request->input('mode', 'kills');

        if (!is_numeric($pilotId) || $pilotId <= 0) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_pilot_id',
                'message' => '无效的角色 ID',
            ], 400);
        }

        try {
            $kills = $this->killmailService->getPilotKills((int) $pilotId, $mode);

            return response()->json([
                'success' => true,
                'data' => $kills,
                'pilot_id' => $pilotId,
                'mode' => $mode,
                'count' => count($kills),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'fetch_failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 获取 KM 详情
     */
    public function killDetail($killId, Request $request)
    {
        if (!is_numeric($killId) || $killId <= 0) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_kill_id',
                'message' => '无效的 KM ID',
            ], 400);
        }

        $hash = $request->input('hash');

        try {
            if (!empty($hash) && preg_match('/^[a-f0-9]{20,}$/i', $hash)) {
                $detail = $this->killmailService->getKillDetailsByHash((int) $killId, $hash);
            } else {
                $detail = $this->killmailService->getKillDetails((int) $killId);
            }

            return response()->json([
                'success' => true,
                'data' => $detail,
                'kill_id' => $killId,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'fetch_failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
