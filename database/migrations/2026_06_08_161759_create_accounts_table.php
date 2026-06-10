<?php

use App\Enums\AccountChannel;
use App\Enums\Toggle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->enum('channel', AccountChannel::values())
                ->default(AccountChannel::QI_HU->value)
                ->comment('账户渠道');
            $table->string('username', 30)->default('')->comment('账号');
            $table->string('e_id', 10)->nullable()->default(null)->comment('点晴ID');
            $table->unsignedInteger('userid')->nullable()->default(null)->comment('UserId');
            $table->string('secret', 16)->nullable()->default(null)->comment('Secret');
            $table->rawColumn('status', 'TINYINT(1)')->unsigned()->default(Toggle::ENABLED->value)->comment('状态');
            $table->timestamps();
            $table->unique(['channel', 'username', 'e_id', 'userid', 'secret']);
            $table->comment('账号表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
