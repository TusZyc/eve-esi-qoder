@extends('layouts.app')

@section('title', '邮件 - Tus Esi System')
@section('page-title', '邮件')

@section('content')
<style>
    /* 邮件页面专用样式 */
    .mail-panel {
        background: rgba(30, 41, 59, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 12px;
    }
    .mail-panel:hover {
        border-color: rgba(255, 255, 255, 0.12);
    }
    .mail-btn {
        background: rgba(51, 65, 85, 0.4);
        border-radius: 8px;
        padding: 8px 12px;
        font-size: 14px;
        color: #cbd5e1;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        text-align: left;
    }
    .mail-btn:hover {
        background: rgba(51, 65, 85, 0.6);
    }
    .mail-btn.active {
        background: rgba(59, 130, 246, 0.15);
        color: #60a5fa;
        border: 1px solid rgba(59, 130, 246, 0.3);
    }
    .mail-item {
        padding: 16px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        cursor: pointer;
        transition: background 0.2s ease;
    }
    .mail-item:hover {
        background: rgba(51, 65, 85, 0.3);
    }
    .mail-item:last-child {
        border-bottom: none;
    }
    .mail-item.unread {
        background: rgba(59, 130, 246, 0.05);
    }
    .mail-item.unread:hover {
        background: rgba(59, 130, 246, 0.1);
    }
    .badge {
        font-size: 12px;
        padding: 2px 8px;
        border-radius: 4px;
    }
    .badge-blue {
        background: rgba(59, 130, 246, 0.2);
        color: #93c5fd;
    }
    .section-title {
        font-size: 13px;
        font-weight: 500;
        color: #94a3b8;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
</style>

<div class="flex gap-6">
    <!-- 左侧筛选栏 -->
    <div class="w-56 shrink-0 space-y-4">
        <!-- 标签分类 -->
        <div class="mail-panel p-4">
            <div class="section-title">
                <span>📁</span> 邮件分类
            </div>
            <div id="labels-list" class="space-y-1">
                <div class="skeleton h-8 w-full rounded"></div>
                <div class="skeleton h-8 w-full rounded"></div>
                <div class="skeleton h-8 w-full rounded"></div>
            </div>
        </div>

        <!-- 邮件群组 -->
        <div class="mail-panel p-4">
            <div class="section-title">
                <span>👥</span> 邮件群组
            </div>
            <div id="mailing-lists" class="space-y-1">
                <div class="skeleton h-8 w-full rounded"></div>
                <div class="skeleton h-8 w-full rounded"></div>
            </div>
        </div>


    </div>

    <!-- 右侧邮件列表 -->
    <div class="flex-1 min-w-0 space-y-4">
        <!-- 页面标题 -->
        <div class="mail-panel p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500/20 to-cyan-500/20 flex items-center justify-center">
                        <span class="text-xl">📧</span>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-white">邮件</h2>
                        <p id="current-filter" class="text-xs text-slate-400">全部邮件</p>
                    </div>
                </div>
                <div id="total-count" class="text-sm text-slate-400">
                    共 <span class="text-white font-medium">-</span> 封
                </div>
            </div>
        </div>

        <!-- 邮件列表 -->
        <div class="mail-panel overflow-hidden">
            <div id="mail-area">
                <div class="p-6 space-y-3">
                    <div class="skeleton h-20 w-full rounded-lg"></div>
                    <div class="skeleton h-20 w-full rounded-lg"></div>
                    <div class="skeleton h-20 w-full rounded-lg"></div>
                    <div class="skeleton h-20 w-full rounded-lg"></div>
                </div>
            </div>
        </div>

        <!-- 加载更多按钮 -->
        <div id="load-more-area" class="hidden text-center">
            <button onclick="loadMore()" class="px-6 py-2 rounded-lg bg-slate-700/50 text-slate-300 hover:bg-slate-700 transition-colors">
                加载更多
            </button>
        </div>
    </div>
</div>

<!-- 邮件详情模态框 -->
<div id="mail-modal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden items-center justify-center">
    <div class="bg-slate-800 rounded-xl max-w-2xl w-full mx-4 max-h-[80vh] overflow-hidden flex flex-col border border-slate-700">
        <div class="p-4 border-b border-slate-700 flex items-center justify-between">
            <h3 id="modal-subject" class="text-lg font-semibold text-white truncate">邮件主题</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-white p-1">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div class="p-4 border-b border-slate-700/50 text-sm text-slate-400">
            <div class="flex items-center gap-4">
                <span>发件人：<span id="modal-from" class="text-slate-200"></span></span>
                <span>时间：<span id="modal-time" class="text-slate-200"></span></span>
            </div>
            <div class="mt-1">
                <span>收件人：<span id="modal-recipients" class="text-slate-200"></span></span>
            </div>
        </div>
        <div id="modal-body" class="p-4 flex-1 overflow-y-auto text-slate-300 whitespace-pre-wrap">
            <!-- 邮件正文 -->
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    var allMails = [];
    var filteredMails = [];
    var labels = [];
    var mailingLists = [];
    var currentLabel = null;
    var currentListId = null;
    var lastMailId = null;
    var currentMailId = null;
    var autoLoadCount = 0; // 自动加载计数
    var maxAutoLoad = 20; // 最多自动加载 20 页 (1000封)

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        var d = new Date(dateStr);
        return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0') + ' ' + String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
    }

    function escapeHtml(s) {
        var div = document.createElement('div');
        div.textContent = s || '';
        return div.innerHTML;
    }

    function fetchLabels() {
        fetch('/api/dashboard/mail/labels')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                labels = data.labels || [];
                
                var html = '';
                
                html += '<button onclick="filterByLabel(null)" id="label-all" class="mail-btn active">';
                html += '<span>📁 全部</span>';
                html += '</button>';
                
                labels.forEach(function(label) {
                    var nameCn = label.name;
                    var icon = '🏷️';
                    if (nameCn.indexOf('收件箱') !== -1 || nameCn.indexOf('Inbox') !== -1) icon = '📥';
                    else if (nameCn.indexOf('已发送') !== -1 || nameCn.indexOf('Sent') !== -1) icon = '📤';
                    else if (nameCn.indexOf('回收站') !== -1 || nameCn.indexOf('Trash') !== -1) icon = '🗑️';
                    else if (nameCn.indexOf('军团') !== -1) icon = '🏛️';
                    else if (nameCn.indexOf('联盟') !== -1) icon = '🤝';
                    
                    html += '<button onclick="filterByLabel(' + label.label_id + ')" id="label-' + label.label_id + '" class="mail-btn">';
                    html += '<span>' + icon + ' ' + escapeHtml(nameCn) + '</span>';
                    html += '</button>';
                });
                
                document.getElementById('labels-list').innerHTML = html;
            })
            .catch(function(e) {
                console.error('获取标签失败:', e);
                document.getElementById('labels-list').innerHTML = '<div class="text-sm text-red-400 text-center py-2">加载失败</div>';
            });
    }

    function fetchMailingLists() {
        fetch('/api/dashboard/mail/lists')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.error) {
                    document.getElementById('mailing-lists').innerHTML = '<div class="text-sm text-slate-500 text-center py-2">暂无群组</div>';
                    return;
                }
                
                mailingLists = data || [];
                
                if (mailingLists.length === 0) {
                    document.getElementById('mailing-lists').innerHTML = '<div class="text-sm text-slate-500 text-center py-2">暂无订阅群组</div>';
                    return;
                }
                
                var html = '';
                mailingLists.forEach(function(list) {
                    html += '<button onclick="filterByList(' + list.mailing_list_id + ')" id="list-' + list.mailing_list_id + '" class="mail-btn">';
                    html += '<span class="truncate">👥 ' + escapeHtml(list.name) + '</span>';
                    html += '</button>';
                });
                
                document.getElementById('mailing-lists').innerHTML = html;
            })
            .catch(function(e) {
                console.error('获取群组失败:', e);
                document.getElementById('mailing-lists').innerHTML = '<div class="text-sm text-red-400 text-center py-2">加载失败</div>';
            });
    }

    function fetchMails(labelId, lastId) {
        var url = '/api/dashboard/mail';
        var params = [];
        if (labelId) params.push('labels=' + labelId);
        if (lastId) params.push('last_mail_id=' + lastId);
        if (params.length > 0) url += '?' + params.join('&');

        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.error) {
                    document.getElementById('mail-area').innerHTML = '<div class="p-8 text-center"><div class="text-5xl mb-4">⚠️</div><p class="text-red-400">' + escapeHtml(data.error) + '</p></div>';
                    return;
                }

                if (!lastId) {
                    allMails = data;
                } else {
                    allMails = allMails.concat(data);
                }

                if (data.length > 0) {
                    lastMailId = data[data.length - 1].mail_id;
                }

                // 群组筛选模式下，如果当前结果为空且还有更多邮件，自动加载
                var filtered = applyFilterAndCount();
                var hasMore = data.length >= 50;
                
                if (currentListId !== null && filtered === 0 && hasMore && autoLoadCount < maxAutoLoad) {
                    autoLoadCount++;
                    // 显示加载中提示
                    document.getElementById('mail-area').innerHTML = '<div class="p-8 text-center"><div class="text-slate-400">正在搜索群组邮件... (' + (autoLoadCount * 50) + '封已扫描)</div></div>';
                    // 自动加载下一页
                    fetchMails(labelId, lastMailId);
                    return;
                }

                // 重置计数
                autoLoadCount = 0;

                var loadMoreArea = document.getElementById('load-more-area');
                if (hasMore) {
                    loadMoreArea.classList.remove('hidden');
                } else {
                    loadMoreArea.classList.add('hidden');
                }

                applyFilterAndRender();
            })
            .catch(function(e) {
                console.error('获取邮件失败:', e);
                document.getElementById('mail-area').innerHTML = '<div class="p-8 text-center"><div class="text-5xl mb-4">❌</div><p class="text-red-400">加载失败，请稍后重试</p></div>';
            });
    }

    function applyFilterAndCount() {
        if (currentListId !== null) {
            return allMails.filter(function(mail) {
                var listIds = mail.mailing_list_ids || [];
                return listIds.indexOf(currentListId) !== -1;
            }).length;
        }
        return allMails.length;
    }

    function applyFilterAndRender() {
        if (currentListId !== null) {
            filteredMails = allMails.filter(function(mail) {
                var listIds = mail.mailing_list_ids || [];
                return listIds.indexOf(currentListId) !== -1;
            });
        } else {
            filteredMails = allMails;
        }
        
        document.querySelector('#total-count span').textContent = filteredMails.length;
        
        renderMails();
    }

    function renderMails() {
        var area = document.getElementById('mail-area');

        if (!filteredMails || filteredMails.length === 0) {
            area.innerHTML = '<div class="p-12 text-center"><div class="text-6xl mb-4">📭</div><p class="text-xl text-slate-400">暂无邮件</p><p class="text-sm text-slate-500 mt-2">当有新邮件时会在这里显示</p></div>';
            return;
        }

        var html = '';
        filteredMails.forEach(function(mail) {
            var isRead = mail.is_read;
            var itemClass = isRead ? 'mail-item' : 'mail-item unread';

            html += '<div class="' + itemClass + '" onclick="showMail(' + mail.mail_id + ')">';
            html += '<div class="flex items-start gap-4">';

            html += '<div class="pt-1">';
            if (!isRead) {
                html += '<div class="w-2.5 h-2.5 bg-blue-500 rounded-full shadow-lg shadow-blue-500/50"></div>';
            } else {
                html += '<div class="w-2.5 h-2.5 bg-slate-600 rounded-full"></div>';
            }
            html += '</div>';

            html += '<div class="flex-1 min-w-0">';
            html += '<div class="flex items-center justify-between mb-1">';
            html += '<span class="font-medium ' + (isRead ? 'text-slate-300' : 'text-white') + ' truncate">' + escapeHtml(mail.subject) + '</span>';
            html += '<span class="text-xs text-slate-500 ml-2 shrink-0">' + formatDate(mail.timestamp) + '</span>';
            html += '</div>';

            var fromIcon = getTypeIcon(mail.from_type);
            var fromName = mail.from_name || ('ID: ' + mail.from_id);
            html += '<div class="text-sm text-slate-400">';
            html += '发件人: <span class="text-slate-300">' + fromIcon + ' ' + escapeHtml(fromName) + '</span>';
            html += '</div>';

            if (mail.recipients && mail.recipients.length > 0) {
                var recipientNames = mail.recipients.map(function(r) {
                    var icon = getTypeIcon(r.type);
                    var name = r.name || ('ID: ' + r.id);
                    return icon + ' ' + name;
                }).join(', ');
                html += '<div class="text-sm text-slate-500 truncate">';
                html += '收件人: ' + escapeHtml(recipientNames);
                html += '</div>';
            }

            html += '</div>';
            html += '</div>';
            html += '</div>';
        });
        area.innerHTML = html;
    }

    function getTypeIcon(type) {
        switch(type) {
            case 'character': return '👤';
            case 'corporation': return '🏛️';
            case 'alliance': return '🤝';
            case 'mailing_list': return '👥';
            default: return '❓';
        }
    }

    function showMail(mailId) {
        currentMailId = mailId;
        
        fetch('/api/dashboard/mail/' + mailId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.error) {
                    alert(data.error);
                    return;
                }

                document.getElementById('modal-subject').textContent = data.subject || '(无主题)';
                
                var fromIcon = getTypeIcon(data.from_type);
                var fromName = data.from_name || ('ID: ' + data.from_id);
                document.getElementById('modal-from').textContent = fromIcon + ' ' + fromName;
                document.getElementById('modal-time').textContent = formatDate(data.timestamp);
                document.getElementById('modal-body').textContent = data.body || '(无内容)';

                var recipients = (data.recipients || []).map(function(r) {
                    var icon = getTypeIcon(r.type);
                    var name = r.name || ('ID: ' + r.id);
                    return icon + ' ' + name;
                }).join(', ');
                document.getElementById('modal-recipients').textContent = recipients || '-';

                document.getElementById('mail-modal').classList.remove('hidden');
                document.getElementById('mail-modal').classList.add('flex');
            })
            .catch(function(e) {
                console.error('获取邮件详情失败:', e);
                alert('获取邮件详情失败');
            });
    }

    function closeModal() {
        document.getElementById('mail-modal').classList.add('hidden');
        document.getElementById('mail-modal').classList.remove('flex');
        currentMailId = null;
    }

    function markAsRead() {
        if (!currentMailId) return;
        updateMail(currentMailId, { read: true });
    }

    function markAsUnread() {
        if (!currentMailId) return;
        updateMail(currentMailId, { read: false });
    }

    function updateMail(mailId, data) {
        fetch('/api/dashboard/mail/' + mailId, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(data)
        })
            .then(function(r) { return r.json(); })
            .then(function(result) {
                if (result.success) {
                    fetchMails(currentLabel);
                    fetchLabels();
                    closeModal();
                } else {
                    alert(result.error || '操作失败');
                }
            })
            .catch(function(e) {
                console.error('更新邮件失败:', e);
                alert('更新邮件失败');
            });
    }

    function deleteMail() {
        if (!currentMailId) return;
        if (!confirm('确定要删除这封邮件吗？')) return;

        fetch('/api/dashboard/mail/' + currentMailId, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
            .then(function(r) { return r.json(); })
            .then(function(result) {
                if (result.success) {
                    fetchMails(currentLabel);
                    fetchLabels();
                    closeModal();
                } else {
                    alert(result.error || '删除失败');
                }
            })
            .catch(function(e) {
                console.error('删除邮件失败:', e);
                alert('删除邮件失败');
            });
    }

    function updateFilterStyles() {
        document.querySelectorAll('#labels-list .mail-btn').forEach(function(btn) {
            btn.classList.remove('active');
        });
        document.querySelectorAll('#mailing-lists .mail-btn').forEach(function(btn) {
            btn.classList.remove('active');
        });

        if (currentLabel !== null) {
            var btn = document.getElementById('label-' + currentLabel);
            if (btn) btn.classList.add('active');
        } else if (currentListId !== null) {
            var btn = document.getElementById('list-' + currentListId);
            if (btn) btn.classList.add('active');
        } else {
            var btn = document.getElementById('label-all');
            if (btn) btn.classList.add('active');
        }
    }

    function filterByLabel(labelId) {
        currentLabel = labelId;
        currentListId = null;
        lastMailId = null;
        allMails = [];

        var filterText = '全部邮件';
        if (labelId !== null) {
            var label = labels.find(function(l) { return l.label_id === labelId; });
            filterText = label ? label.name : '邮件';
        }
        document.getElementById('current-filter').textContent = filterText;

        updateFilterStyles();
        fetchMails(labelId);
    }

    function filterByList(listId) {
        currentLabel = null;
        currentListId = listId;
        lastMailId = null;
        allMails = [];
        autoLoadCount = 0; // 重置自动加载计数

        var list = mailingLists.find(function(l) { return l.mailing_list_id === listId; });
        var filterText = list ? list.name : '邮件群组';
        document.getElementById('current-filter').textContent = '👥 ' + filterText;

        updateFilterStyles();
        fetchMails(null);
    }

    function loadMore() {
        fetchMails(currentLabel, lastMailId);
    }

    document.getElementById('mail-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    fetchLabels();
    fetchMailingLists();
    fetchMails();
</script>
@endpush