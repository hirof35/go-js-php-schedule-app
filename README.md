# Distributed Schedule System (CQRS Hybrid Architecture)

Go言語（High-Performance Read Engine）と PHP（Flexible Write/Command Command）を独立したプロセスとして稼働させ、カレンダーのデータストアを共有・同期する、高耐久かつ低遅延な分散型スケジュール管理システムです。
<img width="1919" height="991" alt="スクリーンショット 2026-05-22 100249" src="https://github.com/user-attachments/assets/a48fc28e-542a-47ca-874f-39a475dff286" />

## 🪐 アーキテクチャ概要

本システムは、CQRS（Command Query Responsibility Segregation：コマンドクエリ責任分離）の思想に基づき、データの読み込みと書き込みの最適化パスを分離しています。

                 +-----------------------------------+
                 |       Client (FullCalendar)       |
                 +-----------------------------------+
                   /                               \
   [READ / GET]   /                                 \   [WRITE / POST]
                 v                                   v
+---------------------------------+       +---------------------------------+
|   Go Engine (Port: 8080)        |       |    XAMPP / PHP (Port: 80)       |
+---------------------------------+       +---------------------------------+
| - 高速ストリーミング             |       | - ビジネスロジック処理          |
| - キャッシュ制御（実装予定）     |       | - データの永続化・破棄          |
+---------------------------------+       +---------------------------------+
                 \                                   /
                  \                                 /
                   v                               v
                 +-----------------------------------+
                 |       Shared Data Store           |
                 |         (events.json)             |
                 +-----------------------------------+

- **Read (Query) パス:** `Go (Gin Framework)` が担当。カレンダー描画に必要なイベントデータを、共有データストアから直接吸い上げてクライアントへ超高速返却します。
- **Write (Command) パス:** `Apache / PHP` が担当。予定の追加（Create）および削除（Delete）の要求を受け付け、データ構造のバリデーション、永続化ファイルの更新、およびキャッシュのクレンジングを制御します。

---

## 🛠️ 技術スタック

### フロントエンド
- HTML5 / JavaScript (ES6+ Async/Await)
- FullCalendar v6.1.11 (タイムドグリッド・スケジュールUI)
- Tailwind CSS (ユーティリティファーストなUIスタイリング)

### バックエンド
- **Go Engine:** Go 1.2x+ / Gin Web Framework (ポート: `8080`)
- **PHP Command Server:** PHP 8.x+ / XAMPP Apache環境 (ポート: `80`)

---

## 💾 共有データストア構造 (`events.json`)

データは FullCalendar が直接解釈可能なスキーマに準拠したフラットな JSON 構造で永続化されます。

```json
[
  {
    "id": "1716336000",
    "title": "システム作戦会議",
    "start": "2026-05-22T10:00:00+09:00",
    "end": "2026-05-22T11:30:00+09:00",
    "backgroundColor": "#3b82f6",
    "borderColor": "#2563eb",
    "allDay": false
  }
]
🚀 構築・展開手順
1. 前提条件の導入
Windows環境に XAMPP がインストールされており、Apache が起動していること。

Go言語 環境がセットアップされていること。

2. リポジトリの配置
XAMPP のドキュメントルート配下にプロジェクトを展開します。

Bash
C:\xampp\htdocs\php_code\go-js-php-schedule-app\
3. 書き込み側（PHP）の配置確認
以下のコマンド等で、Apache 経由のアクセスルートが開通していることを確認します。

save_event.php: 予定の追加用エンドポイント

delete_event.php: 予定の削除用エンドポイント

4. 読み込み側（Go Engine）のテイクオフ
PowerShell またはターミナルを開き、プロジェクトのルートディレクトリに移動して環境パスを通した上で起動します。

PowerShell
# プロジェクトフォルダへ移動
cd C:\xampp\htdocs\php_code\go-js-php-schedule-app

# XAMPPのPHPパスを環境変数へ一時追加（必要に応じて）
$env:Path += ";C:\xampp\php"

# Go エンジンの起動
go run main.go
Go サーバーがポート :8080 でスタンバイ状態に入れば成功です。

5. クライアントの起動
ブラウザを開き、以下のURLへアクセスします。

http://localhost/php_code/go-js-php-schedule-app/index.html
🛡️ 基本機能の操作仕様
READ (一覧表示): 画面ロード時、およびカレンダーの表示期間変更時、Go Engine からデータを動的に取得し描画します。

CREATE (予定追加): カレンダーの任意の時間帯をマウスでドラッグ選択するとダイアログが表示され、タイトルを入力して確定すると PHP を経由してデータが永続化されます。

DELETE (予定削除): 既存の予定カードをマウスクリックすると削除確認ダイアログが表示され、確定すると PHP 側で対象のオブジェクトが安全に破棄されます。

🎯 今後の拡張ロードマップ (Backlog)
データ永続化層の強化: 共有 JSON ファイルから、SQLite または MySQL (RDB) への換装

キャッシュ・アサイド戦略の実装: PHP 側での書き込みイベント発生時、Go 側が参照する Redis キャッシュをクリアするロジックの完全連動

リアルタイム同期: Go 側の WebSocket サーバ機能とフロントの接続による、マルチクライアント間でのリロードレス同期
