<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Programme;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Services\AuditService;

class SecurityIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'StrongPass@123';

    private Institution $institution;
    private int $adminRoleId;
    private int $officerRoleId;
    private int $studentRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institution = Institution::create([
            'code' => 'SECI',
            'name' => 'Security Test College',
            'slug' => 'seci',
            'institution_type' => 'university',
            'country' => 'India',
            'status' => 'active',
        ]);

        $this->adminRoleId = Role::create(['code' => 'institution_admin', 'name' => 'Institution Admin', 'status' => 'active'])->id;
        $this->officerRoleId = Role::create(['code' => 'examination_officer', 'name' => 'Examination Officer', 'status' => 'active'])->id;
        $this->studentRoleId = Role::create(['code' => 'student', 'name' => 'Student', 'status' => 'active'])->id;
    }

    private function makeUser(string $email, int $roleId): User
    {
        $user = User::create([
            'account_type' => 'institution',
            'institution_id' => $this->institution->id,
            'name' => $email,
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'status' => 'active',
        ]);

        \DB::table('user_role_assignments')->insert([
            'institution_id' => $this->institution->id,
            'user_id' => $user->id,
            'role_id' => $roleId,
            'academic_unit_id' => null,
            'is_primary' => true,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    /** Confirm MFA for the user so privileged write routes become reachable. */
    private function confirmMfa(User $user): void
    {
        \DB::table('user_mfa_secrets')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'secret' => 'KVKFQXREADAANFLM', // value irrelevant — gate checks confirmed_at only
                'confirmed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function seedExamWithMarks(User $officer): array
    {
        $this->confirmMfa($officer);
        Sanctum::actingAs($officer);

        $programme = Programme::create([
            'institution_id' => $this->institution->id,
            'code' => 'BTCSE',
            'name' => 'B.Tech CSE',
            'level' => 'UG',
            'duration_semesters' => 8,
            'status' => 'active',
        ]);

        $subject = Subject::create([
            'institution_id' => $this->institution->id,
            'code' => 'CS401',
            'name' => 'Compiler Design',
            'credits' => 4,
            'subject_type' => 'theory',
            'status' => 'active',
        ]);

        $studentUser = $this->makeUser('student.seci.test', $this->studentRoleId);
        StudentProfile::create([
            'institution_id' => $this->institution->id,
            'user_id' => $studentUser->id,
            'programme_id' => $programme->id,
            'enrollment_status' => 'current',
        ]);

        $examId = $this->postJson('/api/examinations', [
            'title' => 'End-Semester Exam',
            'kind' => 'end_sem',
            'programme_id' => $programme->id,
            'semester' => 3,
        ])->assertCreated()->json('exam.id');

        $this->postJson("/api/examinations/{$examId}/subjects", [
            'subject_id' => $subject->id,
            'exam_date' => '2026-12-15',
            'components' => [['name' => 'Theory', 'max_marks' => 100]],
        ])->assertCreated();

        $detail = $this->getJson("/api/examinations/{$examId}")->assertOk();
        $componentId = $detail->json('subjects.0.components.0.id');
        $studentProfile = StudentProfile::where('institution_id', $this->institution->id)->firstOrFail();

        $this->putJson("/api/examinations/{$examId}/marks", [
            'subject_id' => $detail->json('subjects.0.id'),
            'component_id' => $componentId,
            'records' => [
                ['student_profile_id' => $studentProfile->id, 'status' => 'present', 'marks_obtained' => 80],
            ],
        ])->assertOk();

        return [$programme, $subject, $examId];
    }

    public function test_audit_chain_verifies_and_detects_tampering(): void
    {
        $admin = $this->makeUser('admin.seci.test', $this->adminRoleId);
        Sanctum::actingAs($admin);

        $this->postJson('/api/examinations', [
            'title' => 'Chain Test Exam',
            'kind' => 'class_test',
            'programme_id' => Programme::create([
                'institution_id' => $this->institution->id,
                'code' => 'MCA',
                'name' => 'MCA',
                'level' => 'PG',
                'duration_semesters' => 4,
                'status' => 'active',
            ])->id,
            'semester' => 1,
        ])->assertCreated();

        [$valid, $checked] = AuditService::verifyChain();
        $this->assertTrue($valid);
        $this->assertGreaterThan(0, $checked);

        // Tamper with the most recent audit entry directly in the database.
        $last = DB::table('audit_logs')->orderByDesc('id')->first();
        DB::table('audit_logs')->where('id', $last->id)->update([
            'new_values' => json_encode(['probe' => 'tampered']),
        ]);

        [$validAfter, $checkedBefore, $broken] = AuditService::verifyChain();
        $this->assertFalse($validAfter);
        $this->assertSame($last->id, $broken['id']);
    }

    public function test_checkpoint_signature_validates_and_tamper_is_detected(): void
    {
        $admin = $this->makeUser('admin.seci.test', $this->adminRoleId);
        Sanctum::actingAs($admin);

        $this->postJson('/api/examinations', [
            'title' => 'Checkpoint Test Exam',
            'kind' => 'class_test',
            'programme_id' => Programme::create([
                'institution_id' => $this->institution->id,
                'code' => 'MCA',
                'name' => 'MCA',
                'level' => 'PG',
                'duration_semesters' => 4,
                'status' => 'active',
            ])->id,
            'semester' => 1,
        ])->assertCreated();

        $today = now()->toDateString();
        $this->artisan('audit:checkpoint', ['date' => $today])->assertSuccessful();

        $checkpoint = DB::table('audit_log_checkpoints')->where('for_date', $today)->first();
        $this->assertNotNull($checkpoint);

        $payload = "{$checkpoint->for_date}|{$checkpoint->record_count}|{$checkpoint->last_entry_hash}";
        $expected = hash_hmac('sha256', $payload, config('app.key'));
        $this->assertTrue(hash_equals($expected, $checkpoint->signature));

        // Tamper with the checkpoint's record count — signature must fail.
        DB::table('audit_log_checkpoints')->where('id', $checkpoint->id)->update(['record_count' => $checkpoint->record_count + 99]);

        $tampered = DB::table('audit_log_checkpoints')->where('id', $checkpoint->id)->first();
        $tamperedPayload = "{$tampered->for_date}|{$tampered->record_count}|{$tampered->last_entry_hash}";
        $expectedTampered = hash_hmac('sha256', $tamperedPayload, config('app.key'));
        $this->assertFalse(hash_equals($expectedTampered, $tampered->signature));
    }

    public function test_republish_bumps_result_version_and_archives_history(): void
    {
        $officer = $this->makeUser('officer.seci.test', $this->officerRoleId);
        $admin = $this->makeUser('admin.seci.test', $this->adminRoleId);
        [$programme, $subject, $examId] = $this->seedExamWithMarks($officer);

        $this->confirmMfa($admin);
        Sanctum::actingAs($admin);
        $this->postJson("/api/examinations/{$examId}/publish")->assertOk();

        $first = DB::table('results')->where('exam_id', $examId)->first();
        $this->assertSame(1, (int) $first->version);

        // Republish — old snapshot moves to history, live row becomes v2.
        $this->postJson("/api/examinations/{$examId}/publish")->assertOk();

        $second = DB::table('results')->where('exam_id', $examId)->first();
        $this->assertSame(2, (int) $second->version);

        $history = DB::table('results_history')->where('exam_id', $examId)->first();
        $this->assertNotNull($history);
        $this->assertSame(1, (int) $history->version);
        $this->assertNotNull($history->superseded_at);
    }

    public function test_privileged_user_without_mfa_is_blocked_on_sensitive_writes(): void
    {
        $officer = $this->makeUser('officer.seci.test', $this->officerRoleId);

        $programme = Programme::create([
            'institution_id' => $this->institution->id,
            'code' => 'FYUP',
            'name' => 'FYUP',
            'level' => 'UG',
            'duration_semesters' => 8,
            'status' => 'active',
        ]);

        Sanctum::actingAs($officer);

        // Creating the exam is not an MFA-protected write.
        $examId = $this->postJson('/api/examinations', [
            'title' => 'MFA Gate Test',
            'kind' => 'class_test',
            'programme_id' => $programme->id,
            'semester' => 1,
        ])->assertCreated()->json('exam.id');

        // Marks entry is MFA-protected for privileged roles.
        $this->putJson("/api/examinations/{$examId}/marks", [
            'subject_id' => 1,
            'component_id' => 1,
            'records' => [['student_profile_id' => 1, 'status' => 'present', 'marks_obtained' => 10]],
        ])->assertForbidden()->assertJsonPath('code', 'mfa_required');

        // Fee payment is MFA-protected for privileged roles (institution admin actor).
        $admin = $this->makeUser('admin.gated.test', $this->adminRoleId);
        Sanctum::actingAs($admin);
        $this->postJson('/api/students/1/payments', [
            'amount' => 1000,
            'payment_method' => 'cash',
        ])->assertForbidden()->assertJsonPath('code', 'mfa_required');

        // Publishing is MFA-protected for privileged roles.
        $this->postJson("/api/examinations/{$examId}/publish")->assertForbidden()->assertJsonPath('code', 'mfa_required');

        // After enrolling, the payments route passes the MFA gate (student #1 doesn't exist → 404).
        $this->confirmMfa($admin);
        $this->postJson('/api/students/1/payments', [
            'amount' => 1000,
            'payment_method' => 'cash',
        ])->assertNotFound();
        $this->getJson("/api/examinations/{$examId}")->assertOk();
    }

    public function test_audit_verify_endpoint_requires_admin(): void
    {
        $student = $this->makeUser('student2.seci.test', $this->studentRoleId);
        Sanctum::actingAs($student);
        $this->getJson('/api/security/audit/verify')->assertForbidden();

        $officer = $this->makeUser('officer2.seci.test', $this->officerRoleId);
        Sanctum::actingAs($officer);
        $this->getJson('/api/security/audit/verify')->assertForbidden();

        $admin = $this->makeUser('admin2.seci.test', $this->adminRoleId);
        Sanctum::actingAs($admin);
        $this->getJson('/api/security/audit/verify')->assertOk()->assertJsonStructure(['chain_valid', 'entries_verified']);
    }
}
