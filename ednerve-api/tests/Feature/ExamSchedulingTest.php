<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExamSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'StrongPass@123';

    private Institution $institution;
    private int $roleAdminId;
    private int $roleOfficerId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institution = Institution::create([
            'code' => 'F4TT',
            'name' => 'F4 Test Institution',
            'slug' => 'f4tt',
            'institution_type' => 'university',
            'country' => 'India',
            'status' => 'active',
        ]);

        $this->roleAdminId = Role::create(['code' => 'institution_admin', 'name' => 'Institution Admin', 'status' => 'active'])->id;
        $this->roleOfficerId = Role::create(['code' => 'examination_officer', 'name' => 'Examination Officer', 'status' => 'active'])->id;
        Role::create(['code' => 'student', 'name' => 'Student', 'status' => 'active']);
    }

    private function confirmMfa(User $user): void
    {
        \DB::table('user_mfa_secrets')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'secret' => 'KVKFQXREADAANFLM',
                'confirmed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
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

    private function makeStudent(string $email, int $programmeId, ?string $appNumber = null): int
    {
        $studentRoleId = Role::where('code', 'student')->first()->id;
        $user = $this->makeUser($email, $studentRoleId);

        return \App\Models\StudentProfile::create([
            'institution_id' => $this->institution->id,
            'user_id' => $user->id,
            'programme_id' => $programmeId,
            'enrollment_status' => 'current',
            'application_number' => $appNumber ?? strtoupper(preg_replace('/[^A-Za-z0-9]+/', '-', substr($email, 0, strpos($email, '@')))),
        ])->id;
    }

    private function seedProgrammeAndSubject(): array
    {
        $session = AcademicSession::create([
            'institution_id' => $this->institution->id,
            'code' => '2026-27',
            'name' => '2026-27 Session',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
            'status' => 'active',
        ]);

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
            'name' => 'Project Work',
            'credits' => 4,
            'subject_type' => 'theory',
            'status' => 'active',
        ]);

        return [$session, $programme, $subject];
    }

    private function seedExamWithComponents(User $admin, Programme $programme, Subject $subject, AcademicSession $session): array
    {
        Sanctum::actingAs($admin);

        $examId = $this->postJson('/api/examinations', [
            'title' => 'End Semester Examination - December 2026',
            'kind' => 'end_sem',
            'programme_id' => $programme->id,
            'academic_session_id' => $session->id,
            'semester' => 3,
        ])->assertCreated()->json('exam.id');

        $this->postJson("/api/examinations/{$examId}/subjects", [
            'subject_id' => $subject->id,
            'exam_date' => '2026-12-15',
            'start_time' => '09:30',
            'end_time' => '12:30',
            'components' => [
                ['name' => 'Sessional', 'max_marks' => 20],
                ['name' => 'Theory', 'max_marks' => 80],
            ],
        ])->assertCreated();

        $detail = $this->getJson("/api/examinations/{$examId}")->assertOk();
        $entry = collect($detail->json('subjects'))->firstWhere('subject_id', $subject->id);
        $components = collect($entry['components'])->keyBy('name');

        return [$examId, $entry['id'], $components];
    }

    private function seedAttendance(int $programmeId, int $subjectId, array $studentsPresentCounts): void
    {
        $timetableEntryId = \DB::table('timetable_entries')->insertGetId([
            'institution_id' => $this->institution->id,
            'programme_id' => $programmeId,
            'semester' => 3,
            'section' => 'A',
            'day_of_week' => 1,
            'period_no' => 1,
            'subject_id' => $subjectId,
            'faculty_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (range(1, 10) as $day) {
            $sheetId = \DB::table('attendance_sheets')->insertGetId([
                'institution_id' => $this->institution->id,
                'timetable_entry_id' => $timetableEntryId,
                'date' => '2026-09-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT),
                'marked_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($studentsPresentCounts as $studentId => $presentCount) {
                \DB::table('attendance_records')->insert([
                    'institution_id' => $this->institution->id,
                    'attendance_sheet_id' => $sheetId,
                    'student_profile_id' => $studentId,
                    'status' => $day <= $presentCount ? 'present' : 'absent',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function test_ordinance_external_rule_decides_pass_or_fail(): void
    {
        $admin = $this->makeUser('admin.f4.test', $this->roleAdminId);
        $officer = $this->makeUser('officer.f4.test', $this->roleOfficerId);
        $this->confirmMfa($admin);
        $this->confirmMfa($officer);

        [$session, $programme, $subject] = $this->seedProgrammeAndSubject();
        $failStudent = $this->makeStudent('fail.f4.test', $programme->id);
        $passStudent = $this->makeStudent('pass.f4.test', $programme->id);

        // Mark 'Theory' as external via a programme scheme (AMU-like 20/80 split).
        Sanctum::actingAs($admin);
        $this->postJson('/api/examinations/schemes', [
            'code' => 'BTCSE-INT',
            'name' => 'BTCSE internal assessment',
            'scope' => 'programme',
            'programme_id' => $programme->id,
            'components' => [
                ['name' => 'Sessional', 'kind' => 'continuous', 'max_marks' => 20, 'weightage' => 20],
                ['name' => 'Theory', 'kind' => 'external', 'max_marks' => 80, 'weightage' => 80, 'is_external' => true],
            ],
        ])->assertCreated();

        // Ordinance: overall ≥ 40, external ≥ 40 (default for institution).
        $this->postJson('/api/examinations/ordinances', [
            'code' => 'AMU-BTCSE',
            'name' => 'AMU BTCSE',
            'grading_bands' => [
                ['min' => 90, 'grade' => 'O', 'points' => 10],
                ['min' => 80, 'grade' => 'A+', 'points' => 9],
                ['min' => 70, 'grade' => 'A', 'points' => 8],
                ['min' => 60, 'grade' => 'B+', 'points' => 7],
                ['min' => 50, 'grade' => 'B', 'points' => 6],
                ['min' => 40, 'grade' => 'C', 'points' => 5],
                ['min' => 0, 'grade' => 'F', 'points' => 0],
            ],
            'pass_criteria' => ['overall_min_pct' => 40, 'external_min_pct' => 40],
            'attendance_threshold' => 75,
            'is_default' => true,
        ])->assertCreated();

        [$examId, $entryId, $components] = $this->seedExamWithComponents($admin, $programme, $subject, $session);
        $sessionalId = $components['Sessional']['id'];
        $theoryId = $components['Theory']['id'];

        // Fail student: 20/20 internal, 30/80 external → overall 50 (≥40) but external 37.5% → FAIL.
        $this->putJson("/api/examinations/{$examId}/marks", [
            'subject_id' => $entryId,
            'component_id' => $sessionalId,
            'records' => [
                ['student_profile_id' => $failStudent, 'status' => 'present', 'marks_obtained' => 20],
                ['student_profile_id' => $passStudent, 'status' => 'present', 'marks_obtained' => 10],
            ],
        ])->assertOk();

        $this->putJson("/api/examinations/{$examId}/marks", [
            'subject_id' => $entryId,
            'component_id' => $theoryId,
            'records' => [
                ['student_profile_id' => $failStudent, 'status' => 'present', 'marks_obtained' => 30],
                ['student_profile_id' => $passStudent, 'status' => 'present', 'marks_obtained' => 55],
            ],
        ])->assertOk();

        Sanctum::actingAs($officer);
        $this->postJson("/api/examinations/{$examId}/publish", [])->assertOk();

        $results = \DB::table('results')->where('exam_id', $examId)->get()->keyBy('student_profile_id');
        $this->assertSame('fail', $results[$failStudent]->result_status);
        $this->assertSame('pass', $results[$passStudent]->result_status);
        $this->assertNotNull($results[$passStudent]->ordinance_id);
        $this->assertEquals(68.75, (float) $results[$passStudent]->external_percent);
    }

    public function test_eligibility_gate_blocks_hall_ticket_and_override_releases(): void
    {
        $admin = $this->makeUser('admin2.f4.test', $this->roleAdminId);
        $this->confirmMfa($admin);

        [$session, $programme, $subject] = $this->seedProgrammeAndSubject();
        $eligible = $this->makeStudent('eligible.f4.test', $programme->id, 'ELIGIBLE');
        $short = $this->makeStudent('short.f4.test', $programme->id, 'SHORT');

        $this->seedAttendance($programme->id, $subject->id, [
            $eligible => 8, // 80%
            $short => 7,    // 70%
        ]);

        [$examId, $entryId] = $this->seedExamWithComponents($admin, $programme, $subject, $session);

        Sanctum::actingAs($admin);
        $compute = $this->postJson("/api/examinations/{$examId}/eligibility/compute", [])->assertOk();
        $this->assertSame(2, $compute->json('summary.eligible') + $compute->json('summary.short'));

        $report = $this->getJson("/api/examinations/{$examId}/eligibility")->assertOk();
        $statuses = collect($report->json('data'))->mapWithKeys(fn ($row) => [$row['application_number'] => $row['status']]);
        $this->assertSame('eligible', $statuses['ELIGIBLE']);
        $this->assertSame('short', $statuses['SHORT']);

        // Hall tickets
        $this->getJson("/api/examinations/{$examId}/hall-ticket?student_profile_id={$eligible}")->assertOk();
        $this->getJson("/api/examinations/{$examId}/hall-ticket?student_profile_id={$short}")->assertForbidden();

        // Override releases the hall ticket
        $this->postJson('/api/examinations/eligibility/override', [
            'exam_subject_id' => $entryId,
            'student_profile_id' => $short,
            'reason' => 'Bunked 2 days with medical certificate; condoned by Principal.',
        ])->assertOk();

        $this->getJson("/api/examinations/{$examId}/hall-ticket?student_profile_id={$short}")->assertOk();
    }

    public function test_conflict_check_detects_room_double_booking_and_capacity(): void
    {
        $admin = $this->makeUser('admin3.f4.test', $this->roleAdminId);

        [$session, $programme, $subject] = $this->seedProgrammeAndSubject();
        $this->makeStudent('s1.f4.test', $programme->id);
        $this->makeStudent('s2.f4.test', $programme->id);

        $subject2 = Subject::create([
            'institution_id' => $this->institution->id,
            'code' => 'CS402',
            'name' => 'Compilers',
            'credits' => 4,
            'subject_type' => 'theory',
            'status' => 'active',
        ]);

        // Set up one centre with a tiny room (capacity 1) and a larger room.
        Sanctum::actingAs($admin);
        $centreId = $this->postJson('/api/examinations/centres', [
            'code' => 'MAIN', 'name' => 'Main Campus', 'capacity' => 500,
        ])->assertCreated()->json('centre.id');

        $smallRoomId = $this->postJson('/api/examinations/rooms', [
            'centre_id' => $centreId, 'code' => 'A1', 'name' => 'Room A1', 'capacity' => 1,
        ])->assertCreated()->json('room.id');

        $examId = $this->postJson('/api/examinations', [
            'title' => 'Conflict Test Exam',
            'kind' => 'end_sem',
            'programme_id' => $programme->id,
            'academic_session_id' => $session->id,
            'semester' => 3,
        ])->assertCreated()->json('exam.id');

        foreach ([$subject, $subject2] as $s) {
            $this->postJson("/api/examinations/{$examId}/subjects", [
                'subject_id' => $s->id,
                'exam_date' => '2026-12-20',
                'start_time' => '09:30',
                'end_time' => '12:30',
                'components' => [['name' => 'Theory', 'max_marks' => 100]],
            ])->assertCreated();
        }

        $entries = $this->getJson("/api/examinations/{$examId}")->assertOk()->json('subjects');
        foreach ($entries as $entry) {
            $this->patchJson("/api/examinations/{$examId}/subjects/{$entry['id']}/schedule", [
                'exam_room_id' => $smallRoomId,
            ])->assertOk();
        }

        $conflicts = $this->postJson("/api/examinations/{$examId}/conflicts/check", [])->assertOk()->json('conflicts');
        $types = collect($conflicts)->pluck('type');

        $this->assertContains('room_double_booked', $types);
        $this->assertContains('capacity_exceeded', $types);
    }
}
