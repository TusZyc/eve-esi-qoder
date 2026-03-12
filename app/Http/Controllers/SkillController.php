<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Helpers\EveHelper;

class SkillController extends Controller
{
    public function index(Request )
    {
         = ->user();

         = ->getSkillsData();
         = ->getSkillQueue();

        // 批量获取技能队列名称
        if (!empty()) {
             = array_column(, 'skill_id');
             = EveHelper::getNamesByIds(, 'skill');
            foreach ( as &) {
                 = (int) (['skill_id'] ?? 0);
                ['skill_name'] = [] ?? EveHelper::getNameById(, 'skill');
            }
        }

        // 批量获取已学技能名称
        if (!empty(['skills'])) {
             = array_column(['skills'], 'skill_id');
             = EveHelper::getNamesByIds(, 'skill');

            foreach (['skills'] as &) {
                 = (int) (['skill_id'] ?? 0);
                ['skill_name'] = [] ?? EveHelper::getNameById(, 'skill');
                ['group_name'] = '其他';
                ['group_id'] = 0;
            }

            ['skills_by_group'] = [
                'all' => ['group_id' => 0, 'group_name' => '所有技能', 'skills' => ['skills']]
            ];
        }

         = ['total_sp'] ?? 0;
         = ['unallocated_sp'] ?? 0;

         = 0;
         = time();
        foreach ( as ) {
             = isset(['finish_date']) ? strtotime(['finish_date']) : 0;
            if ( > ) {
                 += ( - );
            }
        }

        return view('skills.index', [
            'user' => ,
            'skillsData' => ,
            'skillQueue' => ,
            'totalSP' => ,
            'unallocatedSP' => ,
            'trainingTimeRemaining' => ,
        ]);
    }

    private function getSkillsData()
    {
         = "user_{->id}_skills_{->eve_character_id}";

        return Cache::remember(, 300, function () use () {
             = Http::timeout(10)
                ->withToken(->access_token)
                ->get(config('esi.base_url') . 'characters/' . ->eve_character_id . '/skills/');

            return ->ok() ? ->json() : null;
        });
    }

    private function getSkillQueue()
    {
         = "user_{->id}_skillqueue_{->eve_character_id}";

        return Cache::remember(, 60, function () use () {
             = Http::timeout(10)
                ->withToken(->access_token)
                ->get(config('esi.base_url') . 'characters/' . ->eve_character_id . '/skillqueue/');

            return ->ok() ? ->json() : [];
        });
    }
}
