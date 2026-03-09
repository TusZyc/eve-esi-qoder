<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 运行迁移
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            
            // EVE 角色信息
            $table->unsignedBigInteger('eve_character_id')->nullable();
            $table->unsignedBigInteger('corporation_id')->nullable();
            $table->unsignedBigInteger('alliance_id')->nullable();
            
            // OAuth2 Token
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            
            $table->rememberToken();
            $table->timestamps();
            
            $table->index('eve_character_id');
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
