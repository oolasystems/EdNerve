<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_duty_swap_requests', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('roster_id');

            // The two assignments whose staff members want to exchange duties.
            $table->unsignedBigInteger('first_assignment_id');
            $table->unsignedBigInteger('second_assignment_id');

            // Staff A initiates and explicitly consents by submitting the request.
            $table->unsignedBigInteger('initiated_by');
            $table->unsignedBigInteger('target_user_id');

            $table->timestamp('initiator_accepted_at');
            $table->timestamp('target_accepted_at')->nullable();
            $table->timestamp('target_responded_at')->nullable();
            $table->string('target_response_reason', 1000)->nullable();

            // pending_target | pending_officer | approved | rejected | withdrawn
            $table->string('status', 30)->default('pending_target');

            // Examination Officer's decision.
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_reason', 1000)->nullable();

            // Either participating staff member may withdraw before approval.
            $table->unsignedBigInteger('withdrawn_by')->nullable();
            $table->timestamp('withdrawn_at')->nullable();

            $table->timestamps();

            // Institution-scoped relationship to the roster version.
            $table->foreign(
                ['institution_id', 'roster_id'],
                'edsr_institution_roster_foreign'
            )
                ->references(['institution_id', 'id'])
                ->on('exam_duty_rosters')
                ->cascadeOnDelete();

            // Both assignments must belong to the same institution.
            $table->foreign(
                ['institution_id', 'first_assignment_id'],
                'edsr_institution_first_assignment_foreign'
            )
                ->references(['institution_id', 'id'])
                ->on('exam_duty_assignments')
                ->restrictOnDelete();

            $table->foreign(
                ['institution_id', 'second_assignment_id'],
                'edsr_institution_second_assignment_foreign'
            )
                ->references(['institution_id', 'id'])
                ->on('exam_duty_assignments')
                ->restrictOnDelete();

            // Initiator, target, decision-maker and withdrawing user
            // must belong to the same institution.
            $table->foreign(
                ['institution_id', 'initiated_by'],
                'edsr_institution_initiator_foreign'
            )
                ->references(['institution_id', 'id'])
                ->on('users')
                ->restrictOnDelete();

            $table->foreign(
                ['institution_id', 'target_user_id'],
                'edsr_institution_target_foreign'
            )
                ->references(['institution_id', 'id'])
                ->on('users')
                ->restrictOnDelete();

            $table->foreign(
                ['institution_id', 'decided_by'],
                'edsr_institution_decided_by_foreign'
            )
                ->references(['institution_id', 'id'])
                ->on('users')
                ->restrictOnDelete();

            $table->foreign(
                ['institution_id', 'withdrawn_by'],
                'edsr_institution_withdrawn_by_foreign'
            )
                ->references(['institution_id', 'id'])
                ->on('users')
                ->restrictOnDelete();

            // Prevent the same assignment being used as both sides.
            $table->unique(
                ['institution_id', 'id'],
                'edsr_institution_id_unique'
            );

            // Request listing and history queries.
            $table->index(
                ['institution_id', 'roster_id', 'status'],
                'edsr_roster_status_index'
            );

            $table->index(
                ['institution_id', 'initiated_by', 'status'],
                'edsr_initiator_status_index'
            );

            $table->index(
                ['institution_id', 'target_user_id', 'status'],
                'edsr_target_status_index'
            );

            // Used when checking whether either assignment is already
            // involved in a pending swap request.
            $table->index(
                ['institution_id', 'first_assignment_id', 'status'],
                'edsr_first_assignment_status_index'
            );

            $table->index(
                ['institution_id', 'second_assignment_id', 'status'],
                'edsr_second_assignment_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_duty_swap_requests');
    }
};
