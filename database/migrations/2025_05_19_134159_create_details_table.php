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
        Schema::create('details', function (Blueprint $table) {
            $table->id();
            $table->json('skills')->nullable();
            $table->integer('experience')->nullable();
            $table->float('skills_match')->nullable();
            $table->float('ai_score')->nullable();
            $table->json('interview')->nullable();
            $table->unsignedBigInteger('apply_id');
            $table->foreign('apply_id')->references('id')->on('applies')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('details');
    }
};
