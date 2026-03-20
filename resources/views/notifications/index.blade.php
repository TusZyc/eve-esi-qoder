@extends('layouts.app')

@section('title', '通知提醒 - Tus Esi System')
@section('page-title', '通知提醒')

@section('content')
    <div class="space-y-6">
        <!-- 页面标题卡片 -->
        <div class="stat-card rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500/20 to-orange-500/20 flex items-center justify-center">
                        <span class="text-2xl">🔔</span>
                    </div>
                    <div>
                        <h2 class="text-xl font-semibold">通知提醒</h2>
                        <p class="text-sm text-slate-400">查看 EVE Online 游戏内的所有通知消息</p>
                    </div>
                </div>
                <div id="stats-area" class="flex items-center gap-4">
                    <div class="text-right">
                        <div id="total-count" class="text-2xl font-bold text-white">-</div>
                        <div class="text-xs text-slate-500">总通知数</div>
                    </div>
                    <div class="text-right">
                        <div id="unread-count" class="text-2xl font-bold text-blue-400">-</div>
                        <div class="text-xs text-slate-500">未读消息</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 通知列表 -->
        <div class="stat-card rounded-xl overflow-hidden">
            <div id="notifications-area">
                <div class="p-6 space-y-3">
                    <div class="skeleton h-20 w-full rounded-lg"></div>
                    <div class="skeleton h-20 w-full rounded-lg"></div>
                    <div class="skeleton h-20 w-full rounded-lg"></div>
                    <div class="skeleton h-20 w-full rounded-lg"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
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

    // 通知字段中文翻译映射
    var fieldTranslations = {
        'amount': '金额',
        'charID': '角色',
        'corpID': '军团',
        'allianceID': '联盟',
        'solarSystemID': '星系',
        'typeID': '物品类型',
        'killMailID': '击毁报告',
        'killMailHash': '报告哈希',
        'applicationText': '申请说明',
        'bounty': '赏金',
        'payout': '赔付',
        'price': '价格',
        'tax': '税金',
        'medalID': '勋章',
        'medalName': '勋章名称',
        'locationID': '位置',
        'stationID': '空间站',
        'structureID': '建筑',
        'structureName': '建筑名称',
        'structureTypeID': '建筑类型',
        'fuelRemaining': '燃料剩余',
        'duration': '时长',
        'endDate': '结束日期',
        'startDate': '开始日期',
        'level': '等级',
        'isHouseWarmingGift': '新手礼包',
        'systemID': '星系',
        'shipsDestroyed': '摧毁舰船',
        'iskValue': 'ISK价值',
        'declarationDate': '宣战日期',
        'delayHours': '延迟小时',
        'hostileState': '敌对状态',
        'warHQ': '战争总部',
        'defenderAllianceID': '防守联盟',
        'attackerAllianceID': '进攻联盟',
        'defenderCorpID': '防守军团',
        'attackerCorpID': '进攻军团',
        'againstID': '对方',
        'declaredByID': '宣战方',
        'cloneStationID': '克隆空间站',
        'corpStationID': '军团空间站',
        'lastCloned': '上次克隆时间',
        'podKillerID': '击毁胶囊者',
        'shipTypeID': '舰船类型',
        'victimID': '受害者',
        'finalBlowAttacker': '最后一击',
        'isHouseWar': '领土战争',
        'warID': '战争',
        'moonID': '卫星',
        'moonName': '卫星名称',
        'planetID': '行星',
        'armorPercentage': '装甲百分比',
        'shieldPercentage': '护盾百分比',
        'hullPercentage': '结构百分比',
        'aggressorID': '攻击者',
        'aggressorCorpID': '攻击军团',
        'aggressorAllianceID': '攻击联盟'
    };

    // 格式化 ISK 金额
    function formatIsk(value) {
        if (typeof value !== 'number') {
            value = parseFloat(value);
        }
        if (isNaN(value)) return value;
        if (value >= 1000000000) {
            return (value / 1000000000).toFixed(2) + ' B ISK';
        } else if (value >= 1000000) {
            return (value / 1000000).toFixed(2) + ' M ISK';
        } else if (value >= 1000) {
            return (value / 1000).toFixed(2) + ' K ISK';
        }
        return value.toLocaleString() + ' ISK';
    }

    // 解析通知 text 字段（YAML 格式，支持 anchor 引用）
    function parseNotificationText(text) {
        if (!text) return {};
        var result = {};
        var anchors = {}; // 存储 YAML anchor 引用
        var lines = text.split('\n');
        
        lines.forEach(function(line) {
            line = line.trim();
            if (!line || line.startsWith('#')) return;
            
            var colonIndex = line.indexOf(':');
            if (colonIndex > 0) {
                var key = line.substring(0, colonIndex).trim();
                var value = line.substring(colonIndex + 1).trim();
                
                // 处理 YAML anchor 定义：&id001 60005401
                var anchorMatch = value.match(/^&(\w+)\s+(.+)$/);
                if (anchorMatch) {
                    var anchorName = anchorMatch[1];
                    value = anchorMatch[2].trim();
                    anchors[anchorName] = value;
                }
                
                // 处理 YAML anchor 引用：*id001
                var refMatch = value.match(/^\*(\w+)$/);
                if (refMatch) {
                    var refName = refMatch[1];
                    value = anchors[refName] || value;
                }
                
                // 移除引号
                if ((value.startsWith('"') && value.endsWith('"')) || 
                    (value.startsWith("'") && value.endsWith("'"))) {
                    value = value.substring(1, value.length - 1);
                }
                
                result[key] = value;
            }
        });
        return result;
    }

    // 将 Windows FILETIME 转换为可读日期
    function filetimeToDate(filetime) {
        if (!filetime || filetime === '0') return null;
        // Windows FILETIME: 100纳秒间隔，从1601-01-01开始
        // JS时间戳从1970-01-01开始
        var ftNum = parseInt(filetime);
        if (isNaN(ftNum) || ftNum <= 0) return null;
        var msFromFiletime = ftNum / 10000; // 转换为毫秒
        var epochDiff = 11644473600000; // 1601到1970的毫秒差
        var jsTimestamp = msFromFiletime - epochDiff;
        var d = new Date(jsTimestamp);
        if (isNaN(d.getTime())) return null;
        return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0') + ' ' + String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
    }

    // 格式化通知内容为可读格式
    function formatNotificationText(notif) {
        var text = notif.text;
        var type = notif.type || '';
        
        if (!text) return '<span class="text-slate-500">无详细内容</span>';
        
        var parsed = parseNotificationText(text);
        var keys = Object.keys(parsed);
        
        // 如果解析失败或不是YAML格式，显示原文
        if (keys.length === 0) {
            return '<div class="text-slate-400">' + escapeHtml(text.substring(0, 500)) + '</div>';
        }
        
        // 根据通知类型提供特定格式
        var typeLower = type.toLowerCase();
        
        // 克隆体激活通知
        if (type === 'CloneActivationMsg1' || type === 'CloneActivationMsg2') {
            var stationId = parsed.cloneStationID || parsed.corpStationID || '';
            return '<div class="flex items-center gap-2"><span class="text-cyan-400">🧬 你的克隆体已激活</span>' +
                   (stationId ? '<span class="text-slate-400">（空间站ID: ' + escapeHtml(stationId) + '）</span>' : '') + '</div>';
        }
        
        // 跳跃克隆删除
        if (type === 'JumpCloneDeletedMsg1' || type === 'JumpCloneDeletedMsg2') {
            return '<div class="flex items-center gap-2"><span class="text-yellow-400">⚠️ 跳跃克隆已被删除</span></div>';
        }
        
        // 击毁报告
        if (type === 'KillReportVictim') {
            var kmId = parsed.killMailID || '';
            return '<div class="flex items-center gap-2"><span class="text-red-400">💀 你被击毁了</span>' +
                   (kmId ? '<span class="text-slate-300">击毁报告 #' + escapeHtml(kmId) + '</span>' : '') + '</div>';
        }
        
        if (type === 'KillReportFinalBlow') {
            var kmId = parsed.killMailID || '';
            return '<div class="flex items-center gap-2"><span class="text-green-400">⚔️ 你获得了最后一击</span>' +
                   (kmId ? '<span class="text-slate-300">击毁报告 #' + escapeHtml(kmId) + '</span>' : '') + '</div>';
        }
        
        // 其他击毁相关
        if (typeLower.indexOf('kill') >= 0 && parsed.killMailID) {
            return '<div class="flex items-center gap-2"><span class="text-red-400">💀 击毁报告</span><span class="text-slate-300">#' + escapeHtml(parsed.killMailID) + '</span></div>';
        }
        
        // 赏金相关
        if (typeLower.indexOf('bounty') >= 0) {
            var bountyAmount = parsed.amount || parsed.bounty || parsed.payout;
            if (bountyAmount) {
                return '<div class="flex items-center gap-2"><span class="text-green-400">💰 赏金领取</span><span class="text-emerald-300">' + formatIsk(bountyAmount) + '</span></div>';
            }
        }
        
        // 保险赔付
        if (type === 'InsurancePayoutMsg') {
            var payoutAmount = parsed.amount || parsed.payout;
            if (payoutAmount) {
                return '<div class="flex items-center gap-2"><span class="text-blue-400">🛡️ 保险赔付</span><span class="text-blue-300">' + formatIsk(payoutAmount) + '</span></div>';
            }
        }
        
        // 其他保险相关
        if (typeLower.indexOf('insurance') >= 0) {
            var payoutAmount = parsed.amount || parsed.payout;
            if (payoutAmount) {
                return '<div class="flex items-center gap-2"><span class="text-blue-400">🛡️ 保险通知</span><span class="text-blue-300">' + formatIsk(payoutAmount) + '</span></div>';
            }
            return '<div class="flex items-center gap-2"><span class="text-blue-400">🛡️ 保险通知</span></div>' + formatFieldsAsTable(parsed);
        }
        
        // 军团申请
        if (type === 'CorpAppNewMsg') {
            var appText = parsed.applicationText || '';
            return '<div><span class="text-purple-400">📝 新的军团申请</span>' + 
                   (appText ? '<div class="text-slate-400 mt-1">' + escapeHtml(appText.substring(0, 200)) + '</div>' : '') + '</div>';
        }
        
        if (typeLower.indexOf('corpapp') >= 0 || typeLower.indexOf('application') >= 0) {
            var appText = parsed.applicationText || '';
            return '<div><span class="text-purple-400">📝 军团申请</span>' + 
                   (appText ? '<div class="text-slate-400 mt-1">' + escapeHtml(appText.substring(0, 200)) + '</div>' : '') + '</div>';
        }
        
        // 战争宣布
        if (type === 'WarDeclared' || typeLower.indexOf('wardeclared') >= 0) {
            return '<div class="flex items-center gap-2"><span class="text-orange-400">⚔️ 战争已宣布</span></div>' + 
                   formatFieldsAsTable(parsed);
        }
        
        // 战争无效/失效
        if (type === 'WarInvalid' || typeLower.indexOf('warinvalidated') >= 0) {
            return '<div class="flex items-center gap-2"><span class="text-green-400">🕊️ 战争已失效</span></div>' + 
                   formatFieldsAsTable(parsed);
        }
        
        // 其他战争相关
        if (typeLower.indexOf('war') >= 0) {
            return '<div class="flex items-center gap-2"><span class="text-orange-400">⚔️ 战争通知</span></div>' + 
                   formatFieldsAsTable(parsed);
        }
        
        // 建筑受攻击
        if (type === 'StructureUnderAttack') {
            var structName = parsed.structureName || '';
            return '<div><span class="text-red-400">🚨 建筑正在被攻击！</span>' + 
                   (structName ? '<span class="text-slate-300 ml-2">' + escapeHtml(structName) + '</span>' : '') + '</div>' +
                   formatFieldsAsTable(parsed);
        }
        
        // 建筑/燃料相关
        if (typeLower.indexOf('structure') >= 0 || typeLower.indexOf('fuel') >= 0 || typeLower.indexOf('tower') >= 0) {
            var structName = parsed.structureName || parsed.moonName || '';
            return '<div><span class="text-cyan-400">🏗️ 建筑通知</span>' + 
                   (structName ? '<span class="text-slate-300 ml-2">' + escapeHtml(structName) + '</span>' : '') + '</div>' +
                   formatFieldsAsTable(parsed);
        }
        
        // 月矿相关
        if (typeLower.indexOf('moon') >= 0) {
            var moonName = parsed.moonName || '';
            return '<div><span class="text-purple-400">🌙 月矿通知</span>' + 
                   (moonName ? '<span class="text-slate-300 ml-2">' + escapeHtml(moonName) + '</span>' : '') + '</div>' +
                   formatFieldsAsTable(parsed);
        }
        
        // 主权相关
        if (typeLower.indexOf('sov') >= 0) {
            return '<div class="flex items-center gap-2"><span class="text-yellow-400">🏛️ 主权通知</span></div>' + 
                   formatFieldsAsTable(parsed);
        }
        
        // 其他类型：通用表格格式
        return formatFieldsAsTable(parsed);
    }
    
    // 将字段格式化为表格
    function formatFieldsAsTable(parsed) {
        var keys = Object.keys(parsed);
        if (keys.length === 0) return '';
        
        // 过滤掉一些不需要显示的字段
        var hiddenFields = ['killMailHash', 'podKillerID'];
        
        var html = '<div class="mt-2 space-y-1">';
        keys.forEach(function(key) {
            // 跳过隐藏字段
            if (hiddenFields.indexOf(key) >= 0) return;
            
            var value = parsed[key];
            var label = fieldTranslations[key] || key;
            
            // 跳过空值和0值的一些字段
            if (value === '' || value === null || value === undefined) return;
            if (key === 'podKillerID' && (value === '0' || value === 0)) return;
            
            // 特殊格式化 - ISK 金额
            if (key.toLowerCase().indexOf('amount') >= 0 || 
                key.toLowerCase().indexOf('bounty') >= 0 || 
                key.toLowerCase().indexOf('payout') >= 0 ||
                key.toLowerCase().indexOf('price') >= 0 ||
                key.toLowerCase().indexOf('tax') >= 0 ||
                key.toLowerCase().indexOf('iskvalue') >= 0) {
                value = formatIsk(value);
            }
            
            // 特殊格式化 - Windows FILETIME（很大的数字，如 134175450000000000）
            if (key.toLowerCase().indexOf('cloned') >= 0 || 
                key.toLowerCase().indexOf('time') >= 0) {
                var numValue = parseInt(value);
                // FILETIME 通常是很大的数字（超过 10^16）
                if (!isNaN(numValue) && numValue > 100000000000000) {
                    var dateStr = filetimeToDate(value);
                    if (dateStr) value = dateStr;
                }
            }
            
            // 布尔值转中文
            if (value === 'true' || value === true) value = '是';
            if (value === 'false' || value === false) value = '否';
            
            html += '<div class="flex text-sm">';
            html += '<span class="text-slate-500 w-28 shrink-0">' + escapeHtml(label) + '</span>';
            html += '<span class="text-slate-300">' + escapeHtml(String(value)) + '</span>';
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    fetch('/api/dashboard/notifications')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var area = document.getElementById('notifications-area');
            
            if (data.error) {
                area.innerHTML = '<div class="p-8 text-center"><div class="text-5xl mb-4">⚠️</div><p class="text-red-400">' + escapeHtml(data.error) + '</p></div>';
                return;
            }
            
            // 更新统计数据
            var total = data ? data.length : 0;
            var unread = data ? data.filter(function(n) { return !n.is_read; }).length : 0;
            document.getElementById('total-count').textContent = total;
            document.getElementById('unread-count').textContent = unread;
            
            if (!data || data.length === 0) {
                area.innerHTML = '<div class="p-12 text-center"><div class="text-6xl mb-4">📭</div><p class="text-xl text-slate-400">暂无通知消息</p><p class="text-sm text-slate-500 mt-2">当有新的游戏内通知时会在这里显示</p></div>';
                return;
            }

            var html = '<div class="divide-y divide-slate-700/50">';
            data.forEach(function(notif) {
                var isRead = notif.is_read;
                var bgClass = isRead ? '' : 'bg-blue-500/5';
                
                html += '<div class="p-4 hover:bg-slate-700/30 transition-colors ' + bgClass + '">';
                html += '<div class="flex items-start gap-4">';
                
                // 未读指示器
                html += '<div class="pt-1">';
                if (!isRead) {
                    html += '<div class="w-2.5 h-2.5 bg-blue-500 rounded-full shadow-lg shadow-blue-500/50"></div>';
                } else {
                    html += '<div class="w-2.5 h-2.5 bg-slate-600 rounded-full"></div>';
                }
                html += '</div>';
                
                // 内容区
                html += '<div class="flex-1 min-w-0">';
                html += '<div class="flex items-center justify-between mb-1">';
                html += '<span class="font-medium ' + (isRead ? 'text-slate-300' : 'text-white') + '">' + escapeHtml(notif.type_name) + '</span>';
                html += '<span class="text-xs text-slate-500">' + formatDate(notif.timestamp) + '</span>';
                html += '</div>';
                
                // 格式化后的通知内容
                html += '<div class="text-sm bg-slate-800/50 rounded-lg p-3 mt-2">' + formatNotificationText(notif) + '</div>';
                
                html += '</div>';
                html += '</div>';
                html += '</div>';
            });
            html += '</div>';
            area.innerHTML = html;
        })
        .catch(function() {
            document.getElementById('notifications-area').innerHTML = '<div class="p-8 text-center"><div class="text-5xl mb-4">❌</div><p class="text-red-400">加载失败，请稍后重试</p></div>';
        });
</script>
@endpush
