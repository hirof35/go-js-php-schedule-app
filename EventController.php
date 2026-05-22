<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Illuminate\Http\JsonResponse;

class EventController extends Controller
{
    // C: 予定の新規作成
    public function store(Request $request): JsonResponse {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_at'    => 'required|date',
            'end_at'      => 'required|date|after:start_at',
            'color'       => 'nullable|string|max:7',
            'is_all_day'  => 'boolean'
        ]);

        $event = Event::create(array_merge($validated, [
            'user_id' => Auth::id(),
            'version' => 1
        ]));

        return response()->json($event, 201);
    }

    // U: 予定の更新（楽観的ロック判定を包含）
    public function update(Request $request, Event $event): JsonResponse {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'title'       => 'sometimes|required|string|max:255',
            'start_at'    => 'sometimes|required|date',
            'end_at'      => 'sometimes|required|date|after_or_equal:start_at',
            'is_all_day'  => 'boolean',
            'version'     => 'required|integer' // フロントが保持していたバージョン
        ]);

        // 楽観的ロックによる衝突検知
        if ($event->version !== (int)$validated['version']) {
            return response()->json([
                'error' => 'Conflict',
                'message' => '他のユーザーが既にこのスケジュールを更新しています。最新の情報を取得してください。'
            ], 409);
        }

        // データを更新し、バージョンをインクリメント
        $validated['version'] = $event->version + 1;
        $event->update($validated);

        return response()->json($event);
    }

    // D: 予定の削除
    public function destroy(Event $event): JsonResponse {
        $this->authorize('delete', $event);
        $event->delete();
        return response()->json(null, 204);
    }

    // Signal: 「編集中...」の状態をRDBを介さずRedisに直接PublishしてGoに流す
    public function notifyEditing(Request $request, $id): JsonResponse {
        $user = Auth::user();

        Redis::publish('calendar_signals', json_encode([
            'event_id'  => (int)$id,
            'user_id'   => $user->id,
            'user_name' => $user->name,
            'action'    => $request->input('action', 'editing') // 'editing' or 'idle'
        ]));

        return response()->json(['status' => 'Signal broadcasted']);
    }
}