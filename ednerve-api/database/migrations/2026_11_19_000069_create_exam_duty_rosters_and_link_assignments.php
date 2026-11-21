<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_duty_rosters', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('exam_id');

            $table->unsignedInteger('version');

            // draft | submitted | approved | rejected | published
            $table->string('status', 20)->default('draft');

            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->unsignedBigInteger('published_by')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            // Institution-scoped examination relationship.
            $table->foreign(
                ['institution_id', 'exam_id'],
                'edr_institution_exam_foreign'
            )
                ->references(['institution_id', 'id'])
                ->on('exams')
                ->cascadeOnDelete();

            // Institution-scoped lifecycle actor relationships.
            $table->foreign(
                ['institution_id', 'submitted_by'],
                'edr_institution_submitted_by_foreign'
            )
                ->references(['institution_id', 'id'])
                ->on('users')
                ->restrictOnDelete();

            $table->foreign(
                ['institution_id', 'approved_by'],
                'edr_institution_approved_by_foreign'
            )
                ->references(['institution_id', 'id'])
                ->on('users')
                ->restrictOnDelete();

            $table->foreign(
                ['institution_id', 'rejected_by'],
                'edr_institution_rejected_by_foreign'
            )
                ->references(['institution_id', 'id'])
                ->on('users')
                ->restrictOnDelete();

            $table->foreign(
                ['institution_id', 'published_by'],
                'edr_institution_published_by_foreign'
            )
                ->references(['institution_id', 'id'])
                ->on('users')
                ->restrictOnDelete();

            $table->unique(
                ['institution_id', 'id'],
                'edr_institution_id_id_unique'
            );

            $table->unique(
                ['institution_id', 'exam_id', 'version'],
                'edr_exam_version_unique'
            );

            $table->index(
                ['institution_id', 'exam_id', 'status'],
                'edr_exam_status_index'
            );
        });

        // Add the roster link as nullable initially so existing assignments
        // can be linked before the foreign key is enforced.
        Schema::table('exam_duty_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('roster_id')
                ->nullable()
                ->after('institution_id');

            $table->index(
                ['institution_id', 'roster_id'],
                'edas_roster_index'
            );
        });

        // Create an initial draft roster (version 1) for every examination
        // and attach its existing duty assignments to that roster.
        DB::table('exams')
            ->select(['id', 'institution_id'])
            ->orderBy('id')
            ->chunk(500, function ($exams) {
                foreach ($exams as $exam) {
                    $now = now();

                    $rosterId = DB::table('exam_duty_rosters')->insertGetId([
                        'institution_id' => $exam->institution_id,
                        'exam_id' => $exam->id,
                        'version' => 1,
                        'status' => 'draft',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $subjectIds = DB::table('exam_subjects')
                        ->where('institution_id', $exam->institution_id)
                        ->where('exam_id', $exam->id)
                        ->select('id');

                    DB::table('exam_duty_assignments')
                        ->where('institution_id', $exam->institution_id)
                        ->whereIn('exam_subject_id', $subjectIds)
                        ->update([
                            'roster_id' => $rosterId,
                            'updated_at' => $now,
                        ]);
                }
            });

        // Enforce that an assignment and its roster belong to the same institution.
        Schema::table('exam_duty_assignments', function (Blueprint $table) {
            $table->foreign(
                ['institution_id', 'roster_id'],
                'edas_institution_roster_foreign'
            )
                ->references(['institution_id', 'id'])
                ->on('exam_duty_rosters')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exam_duty_assignments', function (Blueprint $table) {
            $table->dropForeign('edas_institution_roster_foreign');
            $table->dropIndex('edas_roster_index');
            $table->dropColumn('roster_id');
        });

        Schema::dropIfExists('exam_duty_rosters');
    }
};
