@extends('layouts.app')

@push('styles')
<style>
    @keyframes shimmer { 0% { background-position: -1000px 0; } 100% { background-position: 1000px 0; } }
    .skeleton { background: linear-gradient(90deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.08) 50%, rgba(255,255,255,0.03) 100%); background-size: 1000px 100%; animation: shimmer 2s infinite; border-radius: 4px; }
    .category-btn {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        padding: 0.5rem 0.75rem;
        border-radius: 0.5rem;
        transition: all 0.2s;
        color: rgba(147, 197, 253, 0.8);
    }
    .category-btn:hover {
        background: rgba(255, 255, 255, 0.1);
        color: white;
    }
    .category-btn.active {
        background: rgba(59, 130, 246, 0.3);
        color: white;
        border-left: 3px solid #3b82f6;
    }
    .category-count {
        font-size: 0.75rem;
        background: rgba(255, 255, 255, 0.1);
        padding: 0.125rem 0.5rem;
        border-radius: 9999px;
    }
    .contact-row {
        display: flex;
        align-items: center;
        padding: 0.75rem 1rem;
        border-radius: 0.5rem;
        transition: background 0.2s;
    }
    .contact-row:hover {
        background: rgba(255, 255, 255, 0.08);
    }
    .standing-excellent { color: #60a5fa; }
    .standing-good { color: #4ade80; }
    .standing-neutral { color: #9ca3af; }
    .standing-bad { color: #fb923c; }
    .standing-terrible { color: #ef4444; }
</style>
@endpush

@section('title', '联系人 - Tus Esi System')

@section('content')
    <div class="max-w-6xl mx-auto px-4 py-8">
        <h2 class="text-2xl font-semibold mb-6">👥 联系人列表</h2>
        
        <div class="flex gap-6">
            <!-- 左侧分类面板 -->
            <div class="w-56 shrink-0">
                <div class="stat-card rounded-xl p-4 space-y-1">
                    <button class="category-btn active" data-filter="all" onclick="filterContacts('all', this)">
                        <span>📋 所有联系人</span>
                        <span class="category-count" id="count-all">0</span>
                    </button>
                    <button class="category-btn" data-filter="excellent" onclick="filterContacts('excellent', this)">
                        <span>💙 优异声望</span>
                        <span class="category-count" id="count-excellent">0</span>
                    </button>
                    <button class="category-btn" data-filter="good" onclick="filterContacts('good', this)">
                        <span>💚 良好声望</span>
                        <span class="category-count" id="count-good">0</span>
                    </button>
                    <button class="category-btn" data-filter="neutral" onclick="filterContacts('neutral', this)">
                        <span>⚪ 中立声望</span>
                        <span class="category-count" id="count-neutral">0</span>
                    </button>
                    <button class="category-btn" data-filter="bad" onclick="filterContacts('bad', this)">
                        <span>💛 不良声望</span>
                        <span class="category-count" id="count-bad">0</span>
                    </button>
                    <button class="category-btn" data-filter="terrible" onclick="filterContacts('terrible', this)">
                        <span>❤️ 糟糕声望</span>
                        <span class="category-count" id="count-terrible">0</span>
                    </button>
                    <div class="border-t border-white/10 my-2"></div>
                    <button class="category-btn" data-filter="watched" onclick="filterContacts('watched', this)">
                        <span>👥 好友列表</span>
                        <span class="category-count" id="count-watched">0</span>
                    </button>
                    <button class="category-btn" data-filter="blocked" onclick="filterContacts('blocked', this)">
                        <span>🚫 被阻止</span>
                        <span class="category-count" id="count-blocked">0</span>
                    </button>
                </div>
            </div>
            
            <!-- 右侧列表 -->
            <div class="flex-1">
                <div class="stat-card rounded-xl p-6">
                    <div id="contacts-area">
                        <div class="space-y-3">
                            <div class="skeleton h-14 w-full"></div>
                            <div class="skeleton h-14 w-full"></div>
                            <div class="skeleton h-14 w-full"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    var allContacts = [];
    var currentFilter = 'all';

    function escapeHtml(s) {
        var div = document.createElement('div');
        div.textContent = s || '';
        return div.innerHTML;
    }

    function formatStanding(value) {
        return (value >= 0 ? '+' : '') + value.toFixed(2);
    }

    function getStandingClass(value) {
        if (value >= 5) return 'standing-excellent';
        if (value > 0) return 'standing-good';
        if (value === 0) return 'standing-neutral';
        if (value > -5) return 'standing-bad';
        return 'standing-terrible';
    }

    function getContactTypeIcon(type) {
        switch(type) {
            case 'character': return '👤';
            case 'corporation': return '🏢';
            case 'alliance': return '⚔️';
            default: return '❓';
        }
    }

    function getContactTypeName(type) {
        switch(type) {
            case 'character': return '角色';
            case 'corporation': return '军团';
            case 'alliance': return '联盟';
            default: return type;
        }
    }

    function matchFilter(contact, filter) {
        var s = contact.standing;
        switch(filter) {
            case 'all': return true;
            case 'excellent': return s >= 5;
            case 'good': return s > 0 && s < 5;
            case 'neutral': return s === 0;
            case 'bad': return s < 0 && s > -5;
            case 'terrible': return s <= -5;
            case 'watched': return contact.is_watched === true;
            case 'blocked': return contact.is_blocked === true;
            default: return true;
        }
    }

    function updateCounts() {
        var counts = {
            all: allContacts.length,
            excellent: 0, good: 0, neutral: 0, bad: 0, terrible: 0,
            watched: 0, blocked: 0
        };
        allContacts.forEach(function(c) {
            if (c.standing >= 5) counts.excellent++;
            else if (c.standing > 0) counts.good++;
            else if (c.standing === 0) counts.neutral++;
            else if (c.standing > -5) counts.bad++;
            else counts.terrible++;
            if (c.is_watched) counts.watched++;
            if (c.is_blocked) counts.blocked++;
        });
        Object.keys(counts).forEach(function(key) {
            var el = document.getElementById('count-' + key);
            if (el) el.textContent = counts[key];
        });
    }

    function filterContacts(filter, btnEl) {
        currentFilter = filter;
        // 更新按钮状态
        document.querySelectorAll('.category-btn').forEach(function(btn) {
            btn.classList.remove('active');
        });
        if (btnEl) btnEl.classList.add('active');
        renderContacts();
    }

    function renderContacts() {
        var area = document.getElementById('contacts-area');
        var filtered = allContacts.filter(function(c) {
            return matchFilter(c, currentFilter);
        });

        if (filtered.length === 0) {
            area.innerHTML = '<div class="text-center py-12"><div class="text-5xl mb-4">📭</div><p class="text-lg text-blue-200/70">该分类下暂无联系人</p></div>';
            return;
        }

        var html = '<div class="space-y-1">';
        filtered.forEach(function(contact) {
            var statusTags = '';
            if (contact.is_blocked) statusTags += '<span class="text-xs bg-red-600/50 px-2 py-0.5 rounded">已拉黑</span> ';
            if (contact.is_watched) statusTags += '<span class="text-xs bg-yellow-600/50 px-2 py-0.5 rounded">关注</span>';

            html += '<div class="contact-row">';
            html += '<span class="text-2xl mr-3">' + getContactTypeIcon(contact.contact_type) + '</span>';
            html += '<div class="flex-1 min-w-0">';
            html += '<div class="font-medium truncate">' + escapeHtml(contact.name) + '</div>';
            html += '<div class="text-sm text-blue-300/60">' + getContactTypeName(contact.contact_type) + '</div>';
            html += '</div>';
            html += '<div class="flex items-center gap-3 ml-4">';
            if (statusTags) html += '<div class="flex gap-1">' + statusTags + '</div>';
            html += '<div class="' + getStandingClass(contact.standing) + ' font-bold text-lg w-16 text-right">' + formatStanding(contact.standing) + '</div>';
            html += '</div>';
            html += '</div>';
        });
        html += '</div>';
        area.innerHTML = html;
    }

    fetch('/api/dashboard/contacts')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) {
                document.getElementById('contacts-area').innerHTML = '<div class="text-center py-8 text-red-400">' + escapeHtml(data.error) + '</div>';
                return;
            }
            allContacts = data || [];
            updateCounts();
            renderContacts();
        })
        .catch(function() {
            document.getElementById('contacts-area').innerHTML = '<div class="text-center py-8 text-red-400">加载失败</div>';
        });
</script>
@endpush
