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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('username', 30)->comment('账号');
            $table->string('e_id', 10)->comment('点晴ID');
            $table->unsignedInteger('userid')->comment('UserId');
            $table->string('secret', 16)->comment('Secret');
            $table->rawColumn('status', 'TINYINT(1)')->unsigned()->comment('状态');
            $table->timestamps();
            $table->unique(['username', 'e_id', 'userid', 'secret']);
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
