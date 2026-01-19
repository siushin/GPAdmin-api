<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('gpa_menu', function (Blueprint $table) {
            // 添加 account_type 和 menu_key 的复合唯一索引，防止重复数据
            // upsert 需要依赖此唯一索引来判断是更新还是插入
            $table->unique(['account_type', 'menu_key'], 'gpa_menu_account_type_menu_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gpa_menu', function (Blueprint $table) {
            $table->dropUnique('gpa_menu_account_type_menu_key_unique');
        });
    }
};

