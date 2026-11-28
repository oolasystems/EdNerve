
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_duty_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('institution_id')
                ->constrained('institutions')
                ->restrictOnDelete();

            $table->unsignedBigInteger('roster_id')->nullable();
            $table->unsignedBigInteger('exam_subject_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('duty_role_id');

            // Links an original assignment to its replacement assignment.
            $table->unsignedBigInteger('replaced_by_assignment_id')->nullable();

            $table->string('status', 20)->default('assigned');
            // assigned | accepted | declined | replaced

            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('remarks', 500)->nullable();

            $table->timestamps();

            $table->foreign(
                ['institution_id', 'roster_id'],
                'edas_institution_roster_foreign'
            )
                ->references(['institution_id', 'id'])
                ->on('exam_duty_rosters')
                ->restrictOnDelete();

            // Institution-scoped foreign keys.
            $table->foreign(
                ['institution_id', 'exam_subject_id'],
                'edas_institution_exam_subject_foreign'
            )
                ->references(['institution_id', 'id'])
                ->on('exam_subjects')
                ->cascadeOnDelete();

            $table->foreign(
                ['institution_id', 'user_id'],
                'edas_institution_user_foreign'
            )
                ->references(['institution_id', 'id'])
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign(
                ['institution_id', 'duty_role_id'],
                'edas_institution_duty_role_foreign'
            )
                ->references(['institution_id', 'id'])
                ->on('exam_duty_roles')
                ->restrictOnDelete();

            $table->foreign(
                ['institution_id', 'assigned_by'],
                'edas_institution_assigned_by_foreign'
            )
                ->references(['institution_id', 'id'])
                ->on('users')
                ->restrictOnDelete();

            // Replacement must belong to the same institution.
            $table->foreign(
                'replaced_by_assignment_id',
                'edas_replacement_assignment_foreign'
            )
                ->references('id')
                ->on('exam_duty_assignments')
                ->restrictOnDelete();

            // Do not enforce uniqueness on subject + staff.
            // Multiple historical assignment attempts are permitted.
            $table->unique(
                ['institution_id', 'id'],
                'edas_institution_id_unique'
            );

            $table->index(
                ['institution_id', 'roster_id'],
                'edas_roster_index'
            );

            $table->index(
                ['institution_id', 'exam_subject_id'],
                'edas_exam_subject_index'
            );

            $table->index(
                ['institution_id', 'user_id'],
                'edas_user_index'
            );

            $table->index(
                ['institution_id', 'duty_role_id'],
                'edas_duty_role_index'
            );

            $table->index(
                ['institution_id', 'exam_subject_id', 'user_id'],
                'edas_subject_user_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_duty_assignments');
    }
};
