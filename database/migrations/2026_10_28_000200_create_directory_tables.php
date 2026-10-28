<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer, broker, lead source and close reason tables exist once per
 * department. There is no shared customer record (D-020).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['rental', 'resale'] as $department) {
            Schema::create("{$department}_customers", function (Blueprint $table) {
                $table->id();
                $table->string('name');
                // Stored as E.164 (D-029). Unique inside the department, and
                // archived customers keep their number reserved (D-028, D-067).
                $table->string('phone', 32)->unique();
                $table->string('nationality')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users');
                $table->timestamp('archived_at')->nullable();
                $table->foreignId('archived_by')->nullable()->constrained('users');
                $table->timestamps();

                $table->index('name');
                $table->index('archived_at');
            });

            Schema::create("{$department}_brokers", function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('phone', 32)->unique();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users');
                $table->timestamps();
                $table->softDeletes();

                $table->index('name');
            });

            Schema::create("{$department}_lead_sources", function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->boolean('requires_description')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique('name');
            });

            Schema::create("{$department}_close_reasons", function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique('name');
            });
        }
    }

    public function down(): void
    {
        foreach (['rental', 'resale'] as $department) {
            Schema::dropIfExists("{$department}_close_reasons");
            Schema::dropIfExists("{$department}_lead_sources");
            Schema::dropIfExists("{$department}_brokers");
            Schema::dropIfExists("{$department}_customers");
        }
    }
};
