<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Employees are assigned to one or both departments explicitly (D-034).
        Schema::create('department_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('department', 16);
            $table->boolean('can_manage_areas')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'department']);
        });

        // One company-wide area directory shared by both departments (D-081).
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('governorate', 16);
            $table->boolean('is_active')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['name', 'governorate']);
            $table->index(['governorate', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
        Schema::dropIfExists('department_user');
    }
};
