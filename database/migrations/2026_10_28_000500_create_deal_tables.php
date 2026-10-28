<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deals, commission terms and aftercare records are created explicitly and
 * stay inside one department (D-058, D-059).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['rental', 'resale'] as $department) {
            Schema::create("{$department}_deals", function (Blueprint $table) use ($department) {
                $table->id();
                // One deal at most per request (D-036).
                $table->foreignId('request_id')->unique()
                    ->constrained("{$department}_requests");
                $table->foreignId('customer_id')->nullable()
                    ->constrained("{$department}_customers");
                $table->foreignId('broker_id')->nullable()
                    ->constrained("{$department}_brokers")
                    ->nullOnDelete();

                $table->date('deal_date');
                $table->decimal('agreed_total_value', 14, 2);
                $table->string('currency', 8);

                $table->string('unit_code', 32)->nullable();
                $table->string('unit_type', 32)->nullable();
                $table->decimal('unit_area', 10, 2)->nullable();
                $table->text('unit_location')->nullable();

                if ($department === 'rental') {
                    $table->string('rental_term', 16)->nullable();
                    $table->unsignedSmallInteger('rental_term_count')->nullable();
                    $table->string('payment_schedule', 16)->nullable();
                } else {
                    $table->string('seller_name')->nullable();
                    $table->string('seller_phone', 32)->nullable();
                }

                $table->string('contact_representative')->nullable();
                $table->string('field_agent_name')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users');
                $table->timestamps();
            });

            Schema::create("{$department}_deal_commissions", function (Blueprint $table) use ($department) {
                $table->id();
                $table->foreignId('deal_id')->constrained("{$department}_deals")->cascadeOnDelete();
                $table->string('payer');
                $table->string('beneficiary');
                $table->string('method', 16);
                $table->decimal('value', 14, 2);
                $table->text('conditions')->nullable();
                $table->timestamps();
            });

            Schema::create("{$department}_aftercares", function (Blueprint $table) use ($department) {
                $table->id();
                // One aftercare record per customer inside the department (D-059).
                $table->foreignId('customer_id')->unique()
                    ->constrained("{$department}_customers");
                $table->foreignId('owner_user_id')->constrained('users');
                $table->timestamp('next_follow_up_at')->nullable();
                $table->timestamps();
            });

            Schema::create("{$department}_aftercare_activities", function (Blueprint $table) use ($department) {
                $table->id();
                $table->foreignId('aftercare_id')->constrained("{$department}_aftercares");
                $table->foreignId('deal_id')->nullable()->constrained("{$department}_deals");
                $table->string('channel', 16);
                $table->timestamp('occurred_at');
                $table->text('result');
                $table->text('notes')->nullable();
                $table->text('referral_details')->nullable();
                $table->foreignId('recorded_by')->constrained('users');
                $table->timestamp('created_at')->nullable();

                $table->index(['aftercare_id', 'occurred_at']);
            });
        }

        // Immutable audit trail (D-061, D-071). Never updated or deleted.
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->string('department', 16)->nullable();
            $table->string('event_type', 64);
            $table->string('auditable_type', 64);
            $table->unsignedBigInteger('auditable_id');
            $table->foreignId('actor_user_id')->nullable()->constrained('users');
            $table->json('payload')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['department', 'created_at']);
            $table->index(['actor_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');

        foreach (['rental', 'resale'] as $department) {
            Schema::dropIfExists("{$department}_aftercare_activities");
            Schema::dropIfExists("{$department}_aftercares");
            Schema::dropIfExists("{$department}_deal_commissions");
            Schema::dropIfExists("{$department}_deals");
        }
    }
};
