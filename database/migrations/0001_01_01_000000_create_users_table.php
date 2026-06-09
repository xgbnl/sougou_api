<?php

use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('description',45)->comment('账号描述');
            $table->string('username', 12)->comment('账号');
            $table->string('password', 255)->comment('密码');
            $table->enum('role', Role::values())->comment('用户角色');
            $table->timestamps();
            $table->comment('用户表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
