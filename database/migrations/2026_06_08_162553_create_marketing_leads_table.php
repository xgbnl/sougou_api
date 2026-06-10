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
        Schema::create('marketing_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->index();
            $table->foreignId('owner_id')->nullable()->index()->comment('线索所属用户ID');
            $table->string('clue_id', 64)->unique()->comment('线索ID');
            $table->string('username', 255)->default('')->comment('客户姓名');
            $table->string('phone', 32)->default('')->comment('客户手机号');
            $table->string('keyword', 255)->default('')->comment('关键词');
            $table->string('search_word', 255)->default('')->comment('搜索词');
            $table->timestamp('clue_time')->useCurrent()->comment('线索创建时间');
            $table->string('site_name', 255)->default('')->comment('落地页名称');
            $table->boolean('is_faker')->unsigned()->default(0)->comment('是否为伪造数据');
            $table->timestamps();
            $table->comment('广告线索表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_leads');
    }
};
