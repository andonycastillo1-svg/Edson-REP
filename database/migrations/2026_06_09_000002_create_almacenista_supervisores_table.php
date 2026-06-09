<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('almacenista_supervisores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('almacenista_id');
            $table->unsignedBigInteger('supervisor_id');
            $table->timestamps();

            $table->unique(['almacenista_id', 'supervisor_id'], 'alm_sup_unique');
            $table->foreign('almacenista_id', 'alm_sup_alm_fk')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('supervisor_id', 'alm_sup_sup_fk')
                ->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('almacenista_supervisores');
    }
};
