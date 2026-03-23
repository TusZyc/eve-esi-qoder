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
        Schema::create('scout_results', function (Blueprint $table) {
            $table->string('id', 6)->primary();
            $table->json('items')->nullable();
            $table->json('statistics')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->unsignedInteger('retention_hours')->default(2);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            
            $table->index('created_at');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scout_results');
    }
};