<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('module_name');
            $table->string('action'); // 'enabled' or 'disabled'
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_logs');
    }
}; 