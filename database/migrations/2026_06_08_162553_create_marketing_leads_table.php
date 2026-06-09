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
            $table->unsignedInteger('lead_id')->unique()->comment('线索ID');
            $table->string('customer_name', 255)->comment('客户姓名');
            $table->string('customer_tel', 11)->comment('客户手机号');
            $table->rawColumn('status', 'TINYINT(1)')
                ->unsigned()
                ->comment('线索状态: 0待处理,1跟进中,2已转化,3已作废,4有效');
            $table->unsignedTinyInteger('data_type')
                ->comment('0表单,1抽奖,2公众号扫码,3掌中云-注册,4掌中云-支付,5公众号-新关注,6公众号-已关注扫码,7-0中间页,8活码,9悦读-注册,10悦读订单,11一句话咨询,12微信小程序,14三句话咨询');
            $table->unsignedInteger('data_sub_type')->comment('线索子类型');
            $table->timestamp('create_time')->comment('线索创建时间');
            $table->string('site_name', 255)->comment('落地页名称');
            $table->string('remark')->comment('备注');
            $table->string('ad_trace_id', 255)->comment('广告“点击”ID');
            $table->rawColumn('ad_source_type', 'TINYINT(1)')
                ->unsigned()
                ->comment('产品线(广告类型)');
            $table->string('ad_search_word', 255)->comment('搜索词');
            $table->string('ad_keyword', 255)->comment('关键词');
            $table->unsignedInteger('ad_bannerid')->comment('创意ID');
            $table->string('ip_address', 16)->comment('索线提交时的IP地址');
            $table->json('more_info')->comment('扩展信息');
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
