<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'phone']);
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained();
            $table->string('title');
            $table->string('site_address');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->bigInteger('quote_cents');
            $table->string('status')->default('draft'); // draft | sent | active | completed | cancelled
            $table->date('start_date')->nullable();
            $table->date('target_date')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('share_token', 64)->unique();
            $table->timestamps();
            $table->index(['company_id', 'status']);
        });

        Schema::create('variation_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->string('number'); // VO-001 per job
            $table->text('description');
            $table->text('reason')->nullable();
            $table->bigInteger('amount_cents'); // negative allowed
            // Target milestone to adjust when signed; null = signing adds a new milestone.
            $table->unsignedBigInteger('milestone_id')->nullable();
            $table->string('status')->default('draft'); // draft | sent | signed | rejected
            $table->timestamp('signed_at')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('signer_name')->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestamps();
            $table->unique(['job_id', 'number']);
        });

        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variation_order_id')->nullable()->constrained();
            $table->unsignedInteger('sequence');
            $table->string('name');
            $table->text('description')->nullable();
            $table->bigInteger('amount_cents');
            $table->date('due_date')->nullable();
            $table->string('status')->default('planned'); // planned | in_progress | requested | approved | rejected | paid
            $table->foreignId('assigned_user_id')->nullable()->constrained('users'); // phase 2
            $table->timestamps();
        });

        Schema::create('proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('milestone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('type'); // photo | file | note
            $table->string('path')->nullable();
            $table->string('thumb_path')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->text('caption')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('milestone_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount_cents');
            $table->text('note')->nullable();
            $table->string('status')->default('open'); // open | approved | rejected | withdrawn
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by_client_id')->nullable()->constrained('clients');
            $table->text('reject_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('vo_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variation_order_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->char('sha256', 64);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('milestone_id')->nullable()->constrained();
            $table->bigInteger('amount_cents');
            $table->date('paid_on');
            $table->string('method'); // transfer | cash | cheque | other
            $table->string('reference')->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('client_status')->default('pending'); // pending | confirmed | disputed
            $table->text('dispute_note')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->morphs('commentable');
            $table->morphs('author');
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->nullable()->constrained()->cascadeOnDelete();
            $table->nullableMorphs('actor');
            $table->string('action');
            $table->nullableMorphs('subject');
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('client_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->string('phone');
            $table->string('otp_hash')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->unsignedTinyInteger('otp_attempts')->default(0);
            $table->string('device_token', 64)->nullable()->unique();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform'); // android | ios
            $table->string('token')->unique();
            $table->timestamps();
        });

        // Phase 2 tables: created empty so workers and subcontractors slot in later.
        Schema::create('daily_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->date('log_date');
            $table->string('weather')->nullable();
            $table->unsignedInteger('workers_on_site')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('subcontracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subcontractor_user_id')->constrained('users');
            $table->text('scope');
            $table->bigInteger('amount_cents');
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('job_members', function (Blueprint $table) {
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->primary(['job_id', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach (['job_members', 'subcontracts', 'daily_logs', 'device_tokens', 'client_sessions', 'activity_log',
            'comments', 'payments', 'vo_attachments', 'payment_requests', 'proofs', 'milestones',
            'variation_orders', 'jobs', 'clients'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
