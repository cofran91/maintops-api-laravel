<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->index();
            $table->string('phone', 50)->nullable();
            $table->string('document_number', 100)->nullable()->unique();
            $table->string('address', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['document_number']);
            $table->dropIndex(['is_active']);
            $table->dropColumn([
                'is_active',
                'phone',
                'document_number',
                'address',
            ]);
        });
    }
};
