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
        Schema::create('greetings', function (Blueprint $table) {
            $table->unsignedBigInteger('id', true);
            $table->string('category');
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('message_id');
            $table->softDeletes();
            $table->timestamp('created_at')->useCurrent()->nullable();
            $table->timestamp('updated_at')->useCurrent()->nullable();
            $table->foreign('store_id')->references('id')->on('stores');
            $table->foreign('message_id')->references('id')->on('messages');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('greetings');
    }
};
