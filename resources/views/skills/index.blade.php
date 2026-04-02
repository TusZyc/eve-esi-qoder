@extends('layouts.app')

@push('styles')
<style>
    .eve-glow {
        box-shadow: 0 0 20px rgba(59, 130, 246, 0.5);
    }
    @keyframes shimmer {
        0% { background-position: -1000px 0; }
        100% { background-position: 1000px 0; }
    }
    .skeleton {
        background: linear-gradient(90deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.08) 50%, rgba(255,255,255,0.03) 100%);
        background-size: 1000px 100%;
        animation: shimmer 2s infinite;
        border-radius: 4px;
    }
</style>
@endpush

@section('title', '技能队列 - Tus Esi System')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <!-- 技能点统计 -->
        <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 mb-6 eve-glow">
            <h2 class="text-2xl font-semibold mb-6">📚 技能信息</h2>
            <div class="grid md:grid-cols-3 gap-6">
                <div class="text-center">
                    <div class="text-4xl font-bold text-yellow-400 mb-2" id="total-sp"><div class="skeleton h-10 w-40 mx-auto"></div></div>
                    <div class="text-blue-200">总技能点</div>
                </div>
                <div class="text-center">
                    <div class="text-4xl font-bold text-green-400 mb-2" id="unallocated-sp"><div class="skeleton h-10 w-40 mx-auto"></div></div>
                    <div class="text-blue-200">未分配技能点</div>
                </div>
                <div class="text-center">
                    <div class="text-4xl font-bold text-blue-400 mb-2" id="training-time"><div class="skeleton h-10 w-40 mx-auto"></div></div>
                    <div class="text-blue-200">训练剩余时间</div>
                </div>
            </div>
        </div>

        <!-- 技能队列 + 所有技能 并排 -->
        <div class="grid lg:grid-cols-2 gap-6 items-start">
            <!-- 技能队列（左） -->
            <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow">
                <h2 class="text-2xl font-semibold mb-6">⏳ 技能队列</h2>
                <div id="queue-area">
                    <div class="space-y-3">
                        <div class="bg-white/5 rounded-lg p-3"><div class="skeleton h-4 w-3/4 mb-2"></div><div class="skeleton h-2 w-full mb-1"></div><div class="skeleton h-3 w-1/2"></div></div>
                        <div class="bg-white/5 rounded-lg p-3"><div class="skeleton h-4 w-3/4 mb-2"></div><div class="skeleton h-2 w-full mb-1"></div><div class="skeleton h-3 w-1/2"></div></div>
                        <div class="bg-white/5 rounded-lg p-3"><div class="skeleton h-4 w-3/4 mb-2"></div><div class="skeleton h-2 w-full mb-1"></div><div class="skeleton h-3 w-1/2"></div></div>
                    </div>
                </div>
            </div>

            <!-- 所有技能（右） -->
            <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6 eve-glow lg:sticky lg:top-8">
                <h2 class="text-2xl font-semibold mb-6">📖 所有技能</h2>
                <div id="groups-area">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-6">
                        <div class="skeleton h-8 rounded-lg"></div><div class="skeleton h-8 rounded-lg"></div><div class="skeleton h-8 rounded-lg"></div>
                        <div class="skeleton h-8 rounded-lg"></div><div class="skeleton h-8 rounded-lg"></div><div class="skeleton h-8 rounded-lg"></div>
                    </div>
                    <div class="text-center py-8 text-blue-300/50">加载中...</div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function formatNumber(n) {
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    function formatDuration(seconds) {
        if (seconds <= 0) return '-';
        var d = Math.floor(seconds / 86400);
        var h = Math.floor((seconds % 86400) / 3600);
        var m = Math.floor((seconds % 3600) / 60);
        var parts = [];
        if (d > 0) parts.push(d + '天');
        if (h > 0) parts.push(h + '小时');
        if (m > 0 || parts.length === 0) parts.push(m + '分');
        return parts.join('');
    }
    function escapeHtml(s) {
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }
    function formatDateShort(ts) {
        var d = new Date(ts * 1000);
        return String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0') + ' ' + String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
    }

    var skillGroupsData = [];
    var queueExpanded = false;

    // 1. 加载概览
    fetch('/api/dashboard/skills/overview')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById('total-sp').textContent = formatNumber(data.total_sp || 0);
            document.getElementById('unallocated-sp').textContent = formatNumber(data.unallocated_sp || 0);
            document.getElementById('training-time').textContent = formatDuration(data.training_time_remaining || 0);
        })
        .catch(function() {
            document.getElementById('total-sp').textContent = '-';
            document.getElementById('unallocated-sp').textContent = '-';
            document.getElementById('training-time').textContent = '-';
        });

    // 2. 加载技能队列
    fetch('/api/dashboard/skills/queue')
        .then(function(r) { return r.json(); })
        .then(function(queue) {
            var area = document.getElementById('queue-area');
            if (!queue || queue.length === 0) {
                area.innerHTML = '<div class="text-center py-12"><div class="text-6xl mb-4">😴</div><p class="text-xl text-blue-200">技能队列为空</p><p class="text-sm text-blue-300 mt-2">您目前没有正在训练的技能</p></div>';
                return;
            }
            var now = Math.floor(Date.now() / 1000);
            var html = '<div class="space-y-3">';
            queue.forEach(function(item, idx) {
                var startDate = item.start_date ? Math.floor(new Date(item.start_date).getTime() / 1000) : now;
                var finishDate = item.finish_date ? Math.floor(new Date(item.finish_date).getTime() / 1000) : now;
                var totalTime = Math.max(1, finishDate - startDate);
                var status, elapsed, timeRemaining;
                if (now < startDate) { elapsed = 0; timeRemaining = finishDate - now; status = 'waiting'; }
                else if (now > finishDate) { elapsed = totalTime; timeRemaining = 0; status = 'completed'; }
                else { elapsed = now - startDate; timeRemaining = finishDate - now; status = 'training'; }
                var progress = Math.min(100, Math.max(0, (elapsed / totalTime) * 100));
                var hiddenCls = idx >= 5 ? ' hidden' : '';

                var statusHtml, timeHtml;
                if (status === 'training') {
                    statusHtml = '<span class="text-green-400 text-xs font-semibold">训练中</span>';
                    timeHtml = '<span>剩余 ' + formatDuration(timeRemaining) + '</span><span>' + progress.toFixed(1) + '% · 完成于 ' + formatDateShort(finishDate) + '</span>';
                } else if (status === 'waiting') {
                    statusHtml = '<span class="text-blue-400 text-xs font-semibold">等待中</span>';
                    timeHtml = '<span>开始 ' + formatDateShort(startDate) + '</span><span>完成于 ' + formatDateShort(finishDate) + '</span>';
                } else {
                    statusHtml = '<span class="text-yellow-400 text-xs font-semibold">已完成</span>';
                    timeHtml = '<span>已完成</span><span>' + formatDateShort(finishDate) + '</span>';
                }

                html += '<div class="skill-queue-item' + hiddenCls + ' bg-white/5 rounded-lg p-3 hover:bg-white/10 transition-colors">';
                html += '<div class="flex justify-between items-center mb-2">';
                html += '<div class="flex items-center space-x-2 min-w-0">';
                html += '<span class="bg-blue-600 text-xs px-1.5 py-0.5 rounded shrink-0">#' + (idx+1) + '</span>';
                html += '<span class="font-semibold truncate">' + escapeHtml(item.skill_name || '未知技能') + '</span>';
                html += '<span class="text-xs text-blue-300 shrink-0">' + ((item.finished_level||1)-1) + ' → ' + (item.finished_level||0) + '</span>';
                html += '</div><div class="shrink-0 ml-2">' + statusHtml + '</div></div>';
                html += '<div class="w-full bg-white/10 rounded-full h-2 overflow-hidden mb-1.5"><div class="bg-gradient-to-r from-blue-500 to-green-500 h-2 rounded-full" style="width:' + progress.toFixed(1) + '%"></div></div>';
                html += '<div class="flex justify-between items-center text-xs text-blue-300">' + timeHtml + '</div>';
                html += '</div>';
            });
            html += '</div>';
            if (queue.length > 5) {
                html += '<div class="text-center mt-4"><button onclick="toggleQueue()" id="queue-toggle-btn" class="text-sm text-blue-300 hover:text-white transition-colors px-4 py-2 bg-white/5 hover:bg-white/10 rounded-lg">展开全部 (共' + queue.length + '个) ▼</button></div>';
            }
            area.innerHTML = html;
        })
        .catch(function() {
            document.getElementById('queue-area').innerHTML = '<div class="text-center py-8 text-red-400">加载失败</div>';
        });

    // 3. 加载所有技能分组
    fetch('/api/dashboard/skills/groups')
        .then(function(r) { return r.json(); })
        .then(function(groups) {
            skillGroupsData = groups;
            var area = document.getElementById('groups-area');
            if (!groups || groups.length === 0) {
                area.innerHTML = '<div class="text-center py-8 text-blue-300">暂无技能数据</div>';
                return;
            }
            var totalLearned = 0, totalAll = 0;
            groups.forEach(function(g) { totalLearned += (g.learned_count || 0); totalAll += (g.total_count || 0); });
            var html = '<div class="flex items-center justify-between mb-4"><span class="text-sm text-blue-300">已学习 ' + totalLearned + ' / ' + totalAll + ' 个技能</span></div>';
            html += '<div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-6">';
            groups.forEach(function(g, idx) {
                var allLearned = g.learned_count === g.total_count;
                var tabBg = allLearned ? 'bg-green-900/30 hover:bg-green-900/40 border border-green-500/20' : 'bg-white/5 hover:bg-white/10';
                html += '<div onclick="showSkillGroup(' + idx + ')" id="group-tab-' + idx + '" class="skill-group-tab ' + tabBg + ' rounded-lg px-3 py-2 flex justify-between items-center transition-colors cursor-pointer">';
                html += '<span class="text-sm text-blue-200 truncate">' + escapeHtml(g.group_name) + '</span>';
                html += '<span class="text-xs ml-2 shrink-0 ' + (allLearned ? 'text-green-400 font-bold' : 'text-blue-300') + '">' + (g.learned_count || 0) + '/' + (g.total_count || 0) + '</span></div>';
            });
            html += '</div>';
            html += '<div id="skill-detail-area"><div id="skill-detail-placeholder" class="text-center py-8 text-blue-300">点击上方分类查看该分类下的技能</div></div>';
            area.innerHTML = html;
        })
        .catch(function() {
            document.getElementById('groups-area').innerHTML = '<div class="text-center py-8 text-red-400">加载失败</div>';
        });

    function toggleQueue() {
        queueExpanded = !queueExpanded;
        var items = document.querySelectorAll('.skill-queue-item');
        var btn = document.getElementById('queue-toggle-btn');
        items.forEach(function(el, i) {
            if (i >= 5) el.classList.toggle('hidden', !queueExpanded);
        });
        btn.textContent = queueExpanded ? '收起 ▲' : '展开全部 (共' + items.length + '个) ▼';
    }

    function showSkillGroup(idx) {
        var placeholder = document.getElementById('skill-detail-placeholder');
        if (placeholder) placeholder.style.display = 'none';
        var old = document.getElementById('active-group-detail');
        if (old) old.remove();
        document.querySelectorAll('.skill-group-tab').forEach(function(el) {
            el.classList.remove('bg-blue-600/30', 'ring-1', 'ring-blue-400');
        });
        var group = skillGroupsData[idx];
        if (!group) return;
        var tab = document.getElementById('group-tab-' + idx);
        if (tab) tab.classList.add('bg-blue-600/30', 'ring-1', 'ring-blue-400');

        var html = '<div id="active-group-detail">';
        html += '<div class="flex items-center space-x-3 mb-4"><span class="text-lg font-semibold">' + escapeHtml(group.group_name) + '</span>';
        html += '<span class="text-sm text-blue-300">(已学 ' + (group.learned_count || 0) + ' / 共 ' + (group.total_count || 0) + ' 个技能)</span></div>';
        html += '<div class="grid grid-cols-1 xl:grid-cols-2 gap-3">';
        group.skills.forEach(function(skill) {
            var learned = skill.learned;
            var level = skill.active_skill_level || skill.trained_skill_level || 0;
            if (learned) {
                var lvl = level == 5 ? '<span class="text-green-400 text-xs font-bold">Lv.5</span>' : '<span class="text-blue-300 text-xs">Lv.' + level + '</span>';
                html += '<div class="bg-white/5 rounded-lg p-3 hover:bg-white/10 transition-colors">';
                html += '<div class="flex justify-between items-center mb-2"><div class="font-semibold text-white text-sm">' + escapeHtml(skill.skill_name || '未知技能') + '</div>' + lvl + '</div>';
                html += '<div class="flex items-center space-x-1">';
                for (var i = 1; i <= 5; i++) html += '<div class="h-1.5 flex-1 rounded ' + (i <= level ? 'bg-blue-500' : 'bg-white/10') + '"></div>';
                html += '</div></div>';
            } else {
                html += '<div class="bg-white/[0.02] rounded-lg p-3 opacity-40">';
                html += '<div class="flex justify-between items-center mb-2"><div class="text-sm text-white/60">' + escapeHtml(skill.skill_name || '未知技能') + '</div>';
                html += '<span class="text-xs text-white/30">未学习</span></div>';
                html += '<div class="flex items-center space-x-1">';
                for (var i = 1; i <= 5; i++) html += '<div class="h-1.5 flex-1 rounded bg-white/5"></div>';
                html += '</div></div>';
            }
        });
        html += '</div></div>';
        document.getElementById('skill-detail-area').insertAdjacentHTML('beforeend', html);
    }

</script>
@endpush
