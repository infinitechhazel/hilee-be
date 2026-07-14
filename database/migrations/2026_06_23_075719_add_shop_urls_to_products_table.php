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
    Schema::table('products', function (Blueprint $table) {
        $table->string('tiktok_url')->nullable()->after('image');
        $table->string('shopee_url')->nullable()->after('tiktok_url');
        $table->string('lazada_url')->nullable()->after('shopee_url');
    });
}

public function down(): void
{
    Schema::table('products', function (Blueprint $table) {
        $table->dropColumn(['tiktok_url', 'shopee_url', 'lazada_url']);
    });
}
};
