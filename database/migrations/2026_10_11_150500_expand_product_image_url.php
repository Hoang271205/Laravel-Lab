<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->string('display_image_url', 2048)->change());
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->string('display_image_url')->change());
    }
};
