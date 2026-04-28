<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('websocket_stats', function (Blueprint $table) {
            $table->id();
            $table->string('metric');
            $table->date('date');
            $table->unsignedBigInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['metric', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('websocket_stats');
    }
};
