<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Fleet\FleetOperation;
use App\Models\Fleet\FleetOperationConfig;
use App\Services\Fleet\FleetService;
use App\Services\Fleet\FleetSnapshotService;

class FleetController extends Controller
{
    private FleetService $fleetService;
    private FleetSnapshotService $snapshotService;

    public function __construct(FleetService $fleetService, FleetSnapshotService $snapshotService)
    {
        $this->fleetService = $fleetService;
        $this->snapshotService = $snapshotService;
    }

    /**
     * 显示行动列表
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $isLoggedIn = $user !== null;

        $query = FleetOperation::with(['config'])
            ->orderBy('started_at', 'desc');

        // 筛选状态
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $operations = $query->paginate(20);

        return view('fleet.index', [
            'user' => $user,
            'isLoggedIn' => $isLoggedIn,
            'activePage' => 'fleet',
            'operations' => $operations,
            'statusFilter' => $request->status ?? 'all',
        ]);
    }

    /**
     * 显示创建行动页面
     */
    public function create(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return redirect()->route('login');
        }

        // 检查用户是否在舰队中
        $fleetStatus = $this->fleetService->checkUserFleetStatus($user);

        // 获取默认配置
        $defaultConfig = FleetOperationConfig::getDefault();

        return view('fleet.create', [
            'user' => $user,
            'isLoggedIn' => true,
            'activePage' => 'fleet',
            'fleetStatus' => $fleetStatus,
            'defaultConfig' => $defaultConfig,
        ]);
    }

    /**
     * 创建行动
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => '请先登录',
            ], 401);
        }

        $request->validate([
            'operation_name' => 'required|string|max:255',
            'assembly_type' => 'required|string|max:50',
            'assembly_type_other' => 'nullable|string|max:255',
            'doctrine' => 'nullable|string',
            'assembly_location' => 'nullable|string|max:255',
            'fleet_commander' => 'nullable|string|max:255',
            'snapshot_interval' => 'nullable|integer|min:30|max:300',
            'auto_snapshot' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        $operation = $this->fleetService->createOperation($user, $request->all());

        if (!$operation) {
            return response()->json([
                'success' => false,
                'error' => '无法创建行动，请确保您在舰队中并拥有舰队指挥权限',
            ], 400);
        }

        // 行动创建成功后立即抓取一次快照
        if ($user->access_token) {
            $this->snapshotService->capture($operation, $user->access_token, true);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'operation_id' => $operation->id,
                'redirect' => route('fleet.show', $operation->id),
            ],
        ]);
    }

    /**
     * 显示行动详情/实时监控
     */
    public function show(Request $request, $id)
    {
        $user = Auth::user();
        $isLoggedIn = $user !== null;

        $operation = FleetOperation::with(['config', 'snapshots.members'])
            ->findOrFail($id);

        // 获取最新快照
        $latestSnapshot = $operation->latestSnapshot();

        // 获取快照历史（最近10条）
        $snapshotHistory = $operation->snapshots()
            ->orderBy('snapshot_time', 'desc')
            ->limit(10)
            ->get();

        return view('fleet.show', [
            'user' => $user,
            'isLoggedIn' => $isLoggedIn,
            'activePage' => 'fleet',
            'operation' => $operation,
            'latestSnapshot' => $latestSnapshot,
            'snapshotHistory' => $snapshotHistory,
        ]);
    }

    /**
     * 手动抓取快照
     */
    public function snapshot(Request $request, $id)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => '请先登录',
            ], 401);
        }

        $operation = FleetOperation::findOrFail($id);

        if (!$operation->isActive()) {
            return response()->json([
                'success' => false,
                'error' => '行动已结束，无法抓取快照',
            ], 400);
        }

        $snapshot = $this->fleetService->captureSnapshot($operation, $user);

        if (!$snapshot) {
            return response()->json([
                'success' => false,
                'error' => '无法抓取快照，请确保您在舰队中并拥有舰队指挥权限',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'snapshot_id' => $snapshot->id,
                'member_count' => $snapshot->member_count,
                'snapshot_time' => $snapshot->snapshot_time->format('H:i:s'),
            ],
        ]);
    }

    /**
     * 结束行动
     */
    public function end(Request $request, $id)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => '请先登录',
            ], 401);
        }

        $operation = FleetOperation::findOrFail($id);

        // 验证权限：只有指挥官可以结束行动
        if ($operation->commander_character_id !== $user->eve_character_id) {
            return response()->json([
                'success' => false,
                'error' => '只有行动指挥官可以结束行动',
            ], 403);
        }

        $success = $this->fleetService->endOperation($operation);

        if (!$success) {
            return response()->json([
                'success' => false,
                'error' => '结束行动失败',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'redirect' => route('fleet.report', $operation->id),
        ]);
    }

    /**
     * 显示出勤报告
     */
    public function report(Request $request, $id)
    {
        $user = Auth::user();
        $isLoggedIn = $user !== null;

        $operation = FleetOperation::with(['config', 'attendanceSummaries'])
            ->findOrFail($id);

        // 获取评分统计
        $statistics = $this->fleetService->getScoreStatistics($operation);

        // 获取出勤汇总（按分数排序）
        $summaries = $operation->attendanceSummaries()
            ->orderByDesc('total_score')
            ->get();

        return view('fleet.report', [
            'user' => $user,
            'isLoggedIn' => $isLoggedIn,
            'activePage' => 'fleet',
            'operation' => $operation,
            'statistics' => $statistics,
            'summaries' => $summaries,
        ]);
    }

    /**
     * 导出出勤报告（CSV）
     */
    public function export(Request $request, $id)
    {
        $operation = FleetOperation::with(['attendanceSummaries'])
            ->findOrFail($id);

        $summaries = $operation->attendanceSummaries()
            ->orderByDesc('total_score')
            ->get();

        $filename = "fleet_attendance_{$operation->id}_" . date('Ymd_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($operation, $summaries) {
            $file = fopen('php://output', 'w');
            
            // BOM for Excel UTF-8
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            
            // 标题行
            fputcsv($file, [
                '行动名称',
                $operation->operation_name,
            ]);
            
            fputcsv($file, [
                '行动时间',
                $operation->started_at->format('Y-m-d H:i') . ' - ' . ($operation->ended_at?->format('Y-m-d H:i') ?? '进行中'),
            ]);
            
            fputcsv($file, []);
            
            // 表头
            fputcsv($file, [
                '角色名称',
                '公司名称',
                '总分',
                '等级',
                '出勤时长(分)',
                '加入延迟(分)',
                '主要舰船',
                '星系重叠%',
                '平均跳跃',
                '在站比例%',
            ]);
            
            // 数据行
            foreach ($summaries as $summary) {
                fputcsv($file, [
                    $summary->character_name,
                    $summary->corporation_name ?? '-',
                    $summary->total_score,
                    $summary->grade,
                    $summary->attendance_duration_minutes,
                    $summary->join_delay_minutes,
                    $summary->primary_ship_name ?? '-',
                    $summary->system_overlap_percent,
                    $summary->avg_jumps_from_commander,
                    $summary->in_station_percent,
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * 检查用户舰队状态（API）
     */
    public function checkStatus(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => '请先登录',
            ], 401);
        }

        $fleetStatus = $this->fleetService->checkUserFleetStatus($user);

        return response()->json([
            'success' => true,
            'data' => $fleetStatus,
        ]);
    }

    /**
     * 获取实时成员列表（API）
     */
    public function members(Request $request, $id)
    {
        $operation = FleetOperation::findOrFail($id);
        $latestSnapshot = $operation->latestSnapshot();

        if (!$latestSnapshot) {
            return response()->json([
                'success' => true,
                'data' => [
                    'members' => [],
                    'snapshot_time' => null,
                ],
            ]);
        }

        $members = $latestSnapshot->members->map(function ($member) {
            return [
                'character_id' => $member->character_id,
                'character_name' => $member->character_name,
                'corporation_name' => $member->corporation_name,
                'ship_type_name' => $member->ship_type_name,
                'solar_system_name' => $member->solar_system_name,
                'jumps_from_commander' => $member->jumps_from_commander,
                'in_station' => $member->in_station,
                'role' => $member->getRoleDisplayName(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'members' => $members,
                'snapshot_time' => $latestSnapshot->snapshot_time->format('H:i:s'),
                'member_count' => $latestSnapshot->member_count,
            ],
        ]);
    }
}