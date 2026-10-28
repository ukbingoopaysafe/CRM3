<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Request tables per department (D-048, D-049) plus their activity history,
 * stage/status history and reassignment requests (D-027, D-070).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['rental', 'resale'] as $department) {
            Schema::create("{$department}_requests", function (Blueprint $table) use ($department) {
                $table->id();

                $table->foreignId('customer_id')->nullable()
                    ->constrained("{$department}_customers");
                // A hard broker deletion detaches the link but the snapshot
                // below keeps the request readable (D-026, D-072).
                $table->foreignId('broker_id')->nullable()
                    ->constrained("{$department}_brokers")
                    ->nullOnDelete();
                $table->string('broker_name_snapshot')->nullable();
                $table->string('broker_phone_snapshot', 32)->nullable();
                $table->string('contact_party', 16);

                $table->foreignId('owner_user_id')->constrained('users');
                $table->foreignId('lead_source_id')->nullable()
                    ->constrained("{$department}_lead_sources");
                $table->string('lead_source_other')->nullable();

                $table->string('governorate', 16);
                $table->foreignId('area_id')->constrained('areas');

                $table->string('stage', 32)->default('received');
                $table->string('status', 16)->default('active');
                $table->string('outcome', 16)->nullable();
                $table->text('outcome_reason_text')->nullable();
                $table->foreignId('outcome_reason_id')->nullable()
                    ->constrained("{$department}_close_reasons");
                $table->text('outcome_notes')->nullable();

                $table->timestamp('next_follow_up_at')->nullable();
                $table->timestamp('paused_at')->nullable();
                $table->timestamp('closed_at')->nullable();

                $table->string('currency', 8)->nullable();
                $table->decimal('budget_declared', 14, 2)->nullable();
                $table->decimal('budget_target', 14, 2)->nullable();

                // Need specifications differ per department and stay inside it.
                $table->string('unit_type', 32)->nullable();
                $table->text('other_requirements')->nullable();
                $table->text('notes')->nullable();

                if ($department === 'rental') {
                    $table->unsignedSmallInteger('rooms')->nullable();
                    $table->unsignedSmallInteger('occupants')->nullable();
                    $table->string('is_family', 8)->nullable();
                    $table->string('finishing', 32)->nullable();
                    $table->string('floor_preference', 32)->nullable();
                    $table->string('elevator', 16)->nullable();
                    $table->string('rental_term', 16)->nullable();
                    $table->unsignedSmallInteger('rental_term_count')->nullable();
                } else {
                    $table->decimal('area_min', 10, 2)->nullable();
                    $table->decimal('area_max', 10, 2)->nullable();
                }

                $table->foreignId('created_by')->nullable()->constrained('users');
                $table->timestamps();

                $table->index(['status', 'next_follow_up_at']);
                $table->index(['owner_user_id', 'status']);
                $table->index(['stage']);
            });

            Schema::create("{$department}_activities", function (Blueprint $table) use ($department) {
                $table->id();
                $table->foreignId('request_id')->constrained("{$department}_requests");
                $table->foreignId('actor_user_id')->constrained('users');
                $table->string('contact_party', 16);
                $table->string('contact_name_snapshot');
                $table->string('channel', 16);
                $table->timestamp('occurred_at');
                $table->text('result');
                $table->text('notes')->nullable();
                $table->text('next_action')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['request_id', 'occurred_at']);
            });

            Schema::create("{$department}_stage_histories", function (Blueprint $table) use ($department) {
                $table->id();
                $table->foreignId('request_id')->constrained("{$department}_requests");
                $table->string('from_stage', 32)->nullable();
                $table->string('to_stage', 32);
                $table->string('from_status', 16)->nullable();
                $table->string('to_status', 16);
                $table->foreignId('changed_by')->constrained('users');
                $table->text('note')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['request_id', 'created_at']);
            });

            Schema::create("{$department}_reassignments", function (Blueprint $table) use ($department) {
                $table->id();
                $table->foreignId('request_id')->constrained("{$department}_requests");
                $table->foreignId('requested_by')->constrained('users');
                $table->foreignId('current_owner_id')->constrained('users');
                $table->foreignId('target_user_id')->constrained('users');
                $table->text('reason');
                $table->string('status', 16)->default('pending');
                $table->foreignId('decided_by')->nullable()->constrained('users');
                $table->timestamp('decided_at')->nullable();
                $table->text('decision_note')->nullable();
                $table->timestamps();

                $table->index(['request_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        foreach (['rental', 'resale'] as $department) {
            Schema::dropIfExists("{$department}_reassignments");
            Schema::dropIfExists("{$department}_stage_histories");
            Schema::dropIfExists("{$department}_activities");
            Schema::dropIfExists("{$department}_requests");
        }
    }
};
