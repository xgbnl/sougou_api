<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE accounts MODIFY channel ENUM('qihu', 'baidu', 'sougou') NOT NULL DEFAULT 'qihu' COMMENT '账户渠道'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE accounts MODIFY channel ENUM('qihu', 'baidu') NOT NULL DEFAULT 'qihu' COMMENT '账户渠道'");
    }
};
