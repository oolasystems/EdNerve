<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_duty_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('exam_subject_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('duty_role_id');
            $table->string('status', 20)->default('assigned'); // assigned|accepted|declined|replaced
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('remarks', 500)->nullable();
            $table->timestamps();

            $table->foreign(['institution_id', 'exam_subject_id'], 'edas_institution_exam_subject_foreign')
                ->references(['institution_id', 'id'])->on('exam_subjects')->cascadeOnDelete();
            $table->foreign(['institution_id', 'user_id'], 'edas_institution_user_foreign')
                ->references(['institution_id', 'id'])->on('users')->cascadeOnDelete();
            $table->foreign(['institution_id', 'duty_role_id'], 'edas_institution_duty_role_foreign')
                ->references(['institution_id', 'id'])->on('exam_duty_roles')->restrictOnDelete();
            $table->foreign(['institution_id', 'assigned_by'], 'edas_institution_assigned_by_foreign')
                ->references(['institution_id', 'id'])->on('users')->restrictOnDelete();

            $table->unique(['institution_id', 'exam_subject_id', 'user_id'], 'edas_subject_user_unique');
            $table->unique(['institution_id', 'id'], 'edas_institution_id_unique');
            $table->index(['institution_id', 'exam_subject_id'], 'edas_exam_subject_index');
            $table->index(['institution_id', 'user_id'], 'edas_user_index');
            $table->index(['institution_id', 'duty_role_id'], 'edas_duty_role_index');
        });

        // Additive backfill: migrate existing MVP invigilations into duty assignments.
        // Idempotent-ish: only when target is empty per institution+subject+user pair.
        if (Schema::hasTable('exam_invigilations')) {
            $now = now();
            $institutions = DB::table('exam_invigilations')->select('institution_id')->distinct()->pluck('institution_id');
            foreach ($institutions as $institutionId) {
                $roleIds = [];
                foreach (['INVIGILATOR' => 'Invigilator', 'CHIEF_SUPERINTENDENT' => 'Chief Superintendent'] as $code => $name) {
                    $role = DB::table('exam_duty_roles')->where('institution_id', $institutionId)->where('code', $code)->first();
                    if (!$role) {
                        $id = DB::table('exam_duty_roles')->insertGetId([
                            'institution_id' => $institutionId,
                            'code' => $code,
                            'name' => $name,
                            'description' => null,
                            'min_qualification' => null,
                            'duty_weight' => $code === 'CHIEF_SUPERINTENDENT' ? 2 : 1,
                            'active' => true,
                            'sort_order' => $code === 'CHIEF_SUPERINTENDENT' ? 2 : 1,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        $roleIds[$code] = $id;
                    } else {
                        $roleIds[$code] = $role->id;
                    }
                }

                $rows = DB::table('exam_invigilations')->where('institution_id', $institutionId)->get();
                foreach ($rows as $row) {
                    $code = ($row->duty ?? 'invigilator') === 'chief' ? 'CHIEF_SUPERINTENDENT' : 'INVIGILATOR';
                    $roleId = $roleIds[$code] ?? null;
                    if (!$roleId) {
                        continue;
                    }
                    $exists = DB::table('exam_duty_assignments')
                        ->where('institution_id', $institutionId)
                        ->where('exam_subject_id', $row->exam_subject_id)
                        ->where('user_id', $row->user_id)
                        ->exists();
                    if ($exists) {
                        continue;
                    }
                    DB::table('exam_duty_assignments')->insert([
                        'institution_id' => $institutionId,
                        'exam_subject_id' => $row->exam_subject_id,
                        'user_id' => $row->user_id,
                        'duty_role_id' => $roleId,
                        'status' => 'assigned',
                        'assigned_by' => null,
                        'assigned_at' => $row->created_at ?? $now,
                        'responded_at' => null,
                        'remarks' => 'Migrated from legacy invigilation duty.',
                        'created_at' => $row->created_at ?? $now,
                        'updated_at' => $row->updated_at ?? $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_duty_assignments');
    }
};
