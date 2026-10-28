<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unit recommendations, field inspections and viewings stay contextual to a
 * request; no property catalogue or photo storage is created (D-060).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['rental', 'resale'] as $department) {
            Schema::create("{$department}_recommendations", function (Blueprint $table) use ($department) {
                $table->id();
                $table->foreignId('request_id')->constrained("{$department}_requests");
                $table->foreignId('created_by')->constrained('users');

                $table->string('internal_code')->nullable();
                $table->string('listing_code', 32)->nullable();
                $table->string('unit_type', 32)->nullable();
                $table->decimal('area', 10, 2)->nullable();
                $table->text('location_description')->nullable();
                $table->decimal('price', 14, 2)->nullable();
                $table->string('currency', 8)->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 16)->default('suggested');
                $table->timestamps();

                $table->index(['request_id', 'status']);
            });

            Schema::create("{$department}_field_inspections", function (Blueprint $table) use ($department) {
                $table->id();
                $table->foreignId('recommendation_id')
                    ->constrained("{$department}_recommendations");
                $table->string('agent_name');
                $table->timestamp('inspected_at');
                $table->string('photos_match', 16);
                $table->text('defects')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('recorded_by')->constrained('users');
                $table->timestamps();
            });

            Schema::create("{$department}_viewings", function (Blueprint $table) use ($department) {
                $table->id();
                $table->foreignId('request_id')->constrained("{$department}_requests");
                $table->foreignId('recommendation_id')->nullable()
                    ->constrained("{$department}_recommendations");
                $table->timestamp('scheduled_at');
                $table->string('agent_name')->nullable();
                $table->string('result', 16)->default('pending');
                $table->text('customer_opinion')->nullable();
                $table->text('not_interested_reason')->nullable();
                $table->foreignId('recorded_by')->constrained('users');
                $table->timestamps();

                $table->index(['request_id', 'result']);
            });
        }
    }

    public function down(): void
    {
        foreach (['rental', 'resale'] as $department) {
            Schema::dropIfExists("{$department}_viewings");
            Schema::dropIfExists("{$department}_field_inspections");
            Schema::dropIfExists("{$department}_recommendations");
        }
    }
};
