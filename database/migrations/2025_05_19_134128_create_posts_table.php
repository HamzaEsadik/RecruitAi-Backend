<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('token');
            $table->string('title');
            $table->text('description');
            $table->json('skills');
            $table->string('city');
            $table->integer('min_experience');
            $table->string('access_token');
            $table->string('share');
            $table->string('dashboard');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
