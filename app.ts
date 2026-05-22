import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';

document.addEventListener('DOMContentLoaded', () => {
    const calendarEl = document.getElementById('calendar')!;
    const apiToken = localStorage.getItem('api_token');
    const currentUserId = "1"; // ログイン中のセッションユーザーID
    
    const LARAVEL_API = 'http://localhost:8000/api/v1';
    const GO_API      = 'http://localhost:8080/api/v1';
    const GO_WS       = 'ws://localhost:8080/ws/signals';

    // 1. WebSocket：他のユーザーからの編集シグナルの常時待受け
    const socket = new WebSocket(GO_WS);
    socket.onmessage = (event) => {
        const signal = JSON.parse(event.data);
        if (String(signal.user_id) === currentUserId) return; // 自分のシグナルは無視

        if (signal.action === 'editing') {
            applyEditingVisuals(signal.event_id, signal.user_name);
        } else if (signal.action === 'idle') {
            removeEditingVisuals(signal.event_id);
        }
    };

    // 2. FullCalendar コア初期化
    const calendar = new Calendar(calendarEl, {
        plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
        initialView: 'timeGridWeek',
        locale: 'ja',
        editable: true,
        selectable: true,

        // 【READルート】カレンダー描画用データはGoの高速キャッシュAPIから取得
        events: {
            url: `${GO_API}/events`,
            method: 'GET',
            extraParams: { user_id: currentUserId },
            failure: () => console.error('キャッシュデータの取得に失敗しました。')
        },

        // 【WRITEルート】新規予定の追加
        select: async (selectionInfo) => {
            const title = prompt('新しい予定のタイトル:');
            if (!title) return calendar.unselect();

            const response = await fetch(`${LARAVEL_API}/events`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${apiToken}`
                },
                body: JSON.stringify({
                    title: title,
                    start_at: selectionInfo.startStr,
                    end_at: selectionInfo.endStr,
                    is_all_day: selectionInfo.allDay
                })
            });

            if (response.ok) {
                calendar.refetchEvents(); // Go経由でカレンダーを再読み込み
            }
            calendar.unselect();
        },

        // 【SIGNALルート】予定をクリック（編集開始）した瞬間に、他者へ編集中シグナルを送信
        eventClick: (info) => {
            fetch(`${LARAVEL_API}/events/${info.event.id}/editing`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${apiToken}`
                },
                body: JSON.stringify({ action: 'editing' })
            });
            
            // 例：ここでモーダルを開く。モーダルを閉じる際には 'idle' を指定して再送信する。
            openEditModal(info.event);
        },

        // 【WRITEルート】ドラッグ＆ドロップによる予定移動（楽観的ロックのチェック対象）
        eventDrop: async (dropInfo) => {
            const eventExtendedProps = dropInfo.event.extendedProps;
            const currentVersion = eventExtendedProps.version || 1;

            const response = await fetch(`${LARAVEL_API}/events/${dropInfo.event.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${apiToken}`
                },
                body: JSON.stringify({
                    start_at: dropInfo.event.startStr,
                    end_at: dropInfo.event.endStr || dropInfo.event.startStr,
                    is_all_day: dropInfo.event.allDay,
                    version: currentVersion // バージョンを厳格に送信
                })
            });

            if (response.status === 409) {
                const errorData = await response.json();
                alert(errorData.message); // 衝突警告を表示
                dropInfo.revert();        // カレンダー上の要素を元の位置に強制引き戻し
                calendar.refetchEvents(); // 表示を最新の状態に強制同期
            } else if (!response.ok) {
                alert('通信エラーによりスケジュールを更新できませんでした。');
                dropInfo.revert();
            } else {
                calendar.refetchEvents(); // 成功時、最新キャッシュで再描画
            }
        }
    });

    calendar.render();
});

// UI制御：編集中エフェクトの付与
function applyEditingVisuals(eventId: number, userName: string) {
    const el = document.querySelector(`[data-event-id="${eventId}"]`);
    if (el && !el.querySelector('.edit-indicator')) {
        el.classList.add('animate-pulse', 'border-2', 'border-amber-500');
        const badge = document.createElement('span');
        badge.className = 'edit-indicator absolute top-0 right-0 bg-amber-500 text-white text-xxs px-1 rounded';
        badge.innerText = `${userName}が変更中`;
        el.appendChild(badge);
    }
}

// UI制御：編集中エフェクトの解除
function removeEditingVisuals(eventId: number) {
    const el = document.querySelector(`[data-event-id="${eventId}"]`);
    if (el) {
        el.classList.remove('animate-pulse', 'border-2', 'border-amber-500');
        el.querySelector('.edit-indicator')?.remove();
    }
}

function openEditModal(event: any) {
    // 編集モーダルの展開ロジック（任意）
    console.log("Editing event:", event.title);
}