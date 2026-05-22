<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('color', 7)->default('#3788d8');
            $table->boolean('is_all_day')->default(false);
            $table->unsignedInteger('version')->default(1); // 楽観的ロック用
            $table->timestamps();

            // 高速なインデックス検索のための複合インデックス
            $table->index(['user_id', 'start_at', 'end_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('events');
    }
};