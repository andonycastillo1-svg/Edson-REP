<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('bodega_id')->nullable()->after('role_id');
            $table->foreign('bodega_id')->references('id')->on('bodegas')->nullOnDelete();
            $table->index('bodega_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['bodega_id']);
            $table->dropIndex(['bodega_id']);
            $table->dropColumn('bodega_id');
        });
    }
};