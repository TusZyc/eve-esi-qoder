@extends('layouts.app')

@push('styles')
<style>
    .eve-glow { box-shadow: 0 0 20px rgba(59, 130, 246, 0.5); }
    @keyframes shimmer { 0% { background-position: -1000px 0; } 100% { background-position: 1000px 0; } }
    .skeleton { background: linear-gradient(90deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.08) 50%, rgba(255,255,255,0.03) 100%); background-size: 1000px 100%; animation: shimmer 2s infinite; border-radius: 4px; }
    .standing-bar { height: 6px; border-radius: 3px; transition: width 0.3s; }
    .standing-positive { background: linear-gradient(90deg, #10b981, #34d399); }
    .standing-negative { background: linear-gradient(90deg, #ef4444, #f87171); }
    .standing-neutral { background: #6b7280; }
</style>
@endpush

@section('title', '声望 - Tus Esi System')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow">
            <h2 class="text-2xl font-semibold mb-6">⭐ 角色声望</h2>
            
            <!-- 标签切换 -->
            <div class="flex space-x-4 mb-6 border-b border-white/20 pb-4">
                <button onclick="showTab('factions')" id="tab-factions" class="tab-btn px-4 py-2 rounded-lg bg-blue-600/50 text-white">势力</button>
                <button onclick="showTab('npc_corps')" id="tab-npc_corps" class="tab-btn px-4 py-2 rounded-lg bg-white/5 hover:bg-white/10 text-blue-200">NPC军团</button>
                <button onclick="showTab('agents')" id="tab-agents" class="tab-btn px-4 py-2 rounded-lg bg-white/5 hover:bg-white/10 text-blue-200">代理人</button>
            </div>
            
            <div id="standings-area">
                <div class="space-y-3">
                    <div class="skeleton h-16 w-full"></div>
                    <div class="skeleton h-16 w-full"></div>
                    <div class="skeleton h-16 w-full"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    var standingsData = { factions: [], npc_corps: [], agents: [] };
    var currentTab = 'factions';

    function formatStanding(value) {
        return (value >= 0 ? '+' : '') + value.toFixed(2);
    }

    function getStandingColor(value) {
        if (value > 0) return 'text-green-400';
        if (value < 0) return 'text-red-400';
        return 'text-gray-400';
    }

    function getStandingBarClass(value) {
        if (value > 0) return 'standing-positive';
        if (value < 0) return 'standing-negative';
        return 'standing-neutral';
    }

    function renderStandings(data) {
        var area = document.getElementById('standings-area');
        if (!data || data.length === 0) {
            area.innerHTML = '<div class="text-center py-12"><div class="text-6xl mb-4">📭</div><p class="text-xl text-blue-200">暂无声望数据</p></div>';
            return;
        }

        var html = '<div class="space-y-3">';
        data.forEach(function(item) {
            var pct = Math.abs(item.standing) * 10;
            html += '<div class="bg-white/5 rounded-lg p-4 hover:bg-white/10 transition-colors">';
            html += '<div class="flex justify-between items-center mb-2">';
            html += '<span class="font-medium">' + escapeHtml(item.name) + '</span>';
            html += '<span class="' + getStandingColor(item.standing) + ' font-bold">' + formatStanding(item.standing) + '</span>';
            html += '</div>';
            html += '<div class="w-full bg-white/10 rounded-full h-1.5 overflow-hidden">';
            html += '<div class="standing-bar ' + getStandingBarClass(item.standing) + '" style="width:' + pct + '%"></div>';
            html += '</div></div>';
        });
        html += '</div>';
        area.innerHTML = html;
    }

    function showTab(tab) {
        currentTab = tab;
        document.querySelectorAll('.tab-btn').forEach(function(btn) {
            btn.classList.remove('bg-blue-600/50', 'text-white');
            btn.classList.add('bg-white/5', 'text-blue-200');
        });
        document.getElementById('tab-' + tab).classList.remove('bg-white/5', 'text-blue-200');
        document.getElementById('tab-' + tab).classList.add('bg-blue-600/50', 'text-white');
        renderStandings(standingsData[tab]);
    }

    function escapeHtml(s) {
        var div = document.createElement('div');
        div.textContent = s || '';
        return div.innerHTML;
    }

    fetch('/api/dashboard/standings')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) {
                document.getElementById('standings-area').innerHTML = '<div class="text-center py-8 text-red-400">' + escapeHtml(data.error) + '</div>';
                return;
            }
            standingsData = data;
            renderStandings(standingsData.factions);
        })
        .catch(function() {
            document.getElementById('standings-area').innerHTML = '<div class="text-center py-8 text-red-400">加载失败</div>';
        });
</script>
@endpush
