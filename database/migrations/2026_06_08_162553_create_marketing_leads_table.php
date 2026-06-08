<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('marketing_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index();
            $table->unsignedInteger('campaign_id')->comment('推广计划ID');
            $table->string('campaign_name',255)->comment('推广计划名称');
            $table->unsignedInteger('group_id')->comment('推广组ID');
            $table->string('group_name',255)->comment('推广组名称');
            $table->string('name',30)->comment('表单用户名');
            $table->string('gender',10)->nullable()->comment('性别');
            $table->string('phone',11)->nullable()->comment('手机号码');
            $table->timestamp('create_time')->comment('记录时间（原始字符串）');
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
