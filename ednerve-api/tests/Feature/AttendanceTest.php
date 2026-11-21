<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Programme;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'StrongPass@123';

    private Institution $institution;
    private int $adminRoleId;
    private int $facultyRoleId;
    private int $studentRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institution = Institution::create([
            'code' => 'ATTC',
            'name' => 'Attendance Test College',
            'slug' => 'attc',
            'institution_type' => 'university',
            'country' => 'India',
            'status' => 'active',
        ]);

        $this->adminRoleId = Role::create(['code' => 'institution_admin', 'name' => 'Institution Admin', 'status' => 'active'])->id;
        $this->facultyRoleId = Role::create(['code' => 'faculty', 'name' => 'Faculty', 'status' => 'active'])->id;
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

    private function seedAcademic(): array
    {
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
            'code' => 'CS301',
            'name' => 'Data Structures',
            'credits' => 4,
            'subject_type' => 'theory',
            'status' => 'active',
        ]);

        $studentUser = $this->makeUser('student.attc.test', $this->studentRoleId);
        StudentProfile::create([
            'institution_id' => $this->institution->id,
            'user_id' => $studentUser->id,
            'programme_id' => $programme->id,
            'enrollment_status' => 'current',
        ]);

        return [$programme, $subject];
    }

    private function seedSlot(int $programmeId, int $subjectId, ?int $facultyUserId): TimetableEntry
    {
        return TimetableEntry::create([
            'institution_id' => $this->institution->id,
            'programme_id' => $programmeId,
            'semester' => 3,
            'section' => 'A',
            'day_of_week' => 1,
            'period_no' => 1,
            'subject_id' => $subjectId,
            'faculty_user_id' => $facultyUserId,
            'room' => 'FET-101',
            'status' => 'active',
        ]);
    }

    public function test_faculty_can_view_timetable_but_student_cannot(): void
    {
        [$programme, $subject] = $this->seedAcademic();
        $faculty = $this->makeUser('faculty.attc.test', $this->facultyRoleId);
        $student = $this->makeUser('student2.attc.test', $this->studentRoleId);
        $this->seedSlot($programme->id, $subject->id, $faculty->id);

        Sanctum::actingAs($faculty);
        $this->getJson('/api/timetable?programme_id=' . $programme->id)
            ->assertOk()
            ->assertJsonPath('timetable.0.subject.code', 'CS301');

        Sanctum::actingAs($student);
        $this->getJson('/api/timetable?programme_id=' . $programme->id)->assertForbidden();
    }

    public function test_admin_can_create_slot_faculty_cannot(): void
    {
        [$programme, $subject] = $this->seedAcademic();
        $admin = $this->makeUser('admin.attc.test', $this->adminRoleId);
        $faculty = $this->makeUser('faculty.attc.test', $this->facultyRoleId);

        Sanctum::actingAs($faculty);
        $this->postJson('/api/timetable/entries', [
            'programme_id' => $programme->id,
            'semester' => 3,
            'day_of_week' => 3,
            'period_no' => 2,
            'subject_id' => $subject->id,
        ])->assertForbidden();

        Sanctum::actingAs($admin);
        $this->postJson('/api/timetable/entries', [
            'programme_id' => $programme->id,
            'semester' => 3,
            'day_of_week' => 3,
            'period_no' => 2,
            'subject_id' => $subject->id,
        ])->assertCreated();

        // Duplicate slot rejected
        $this->postJson('/api/timetable/entries', [
            'programme_id' => $programme->id,
            'semester' => 3,
            'day_of_week' => 3,
            'period_no' => 2,
            'subject_id' => $subject->id,
        ])->assertStatus(422);
    }

    public function test_attendance_open_save_and_summary_flow(): void
    {
        [$programme, $subject] = $this->seedAcademic();
        $faculty = $this->makeUser('faculty.attc.test', $this->facultyRoleId);
        $slot = $this->seedSlot($programme->id, $subject->id, $faculty->id);

        $studentProfile = StudentProfile::where('institution_id', $this->institution->id)->firstOrFail();

        Sanctum::actingAs($faculty);
        $today = now()->toDateString();

        $open = $this->postJson('/api/attendance/sheets/open', [
            'timetable_entry_id' => $slot->id,
            'date' => $today,
        ])->assertOk();

        $sheetId = $open->json('sheet.id');
        $this->assertCount(1, $open->json('roster'));

        $this->putJson("/api/attendance/sheets/{$sheetId}/records", [
            'records' => [
                ['student_profile_id' => $studentProfile->id, 'status' => 'present'],
            ],
        ])->assertOk()->assertJsonPath('saved', 1);

        $summary = $this->getJson('/api/attendance/summary?programme_id=' . $programme->id . '&semester=3')
            ->assertOk();

        $this->assertEquals(100.0, $summary->json('rows.0.total.percent'));
    }

    public function test_faculty_cannot_mark_another_facultys_slot(): void
    {
        [$programme, $subject] = $this->seedAcademic();
        $facultyA = $this->makeUser('faculty.a.test', $this->facultyRoleId);
        $facultyB = $this->makeUser('faculty.b.test', $this->facultyRoleId);
        $slot = $this->seedSlot($programme->id, $subject->id, $facultyB->id);

        Sanctum::actingAs($facultyA);
        $this->postJson('/api/attendance/sheets/open', [
            'timetable_entry_id' => $slot->id,
            'date' => now()->toDateString(),
        ])->assertForbidden();
    }

    public function test_slot_with_attendance_cannot_be_deleted(): void
    {
        [$programme, $subject] = $this->seedAcademic();
        $admin = $this->makeUser('admin.attc.test', $this->adminRoleId);
        $slot = $this->seedSlot($programme->id, $subject->id, $admin->id);

        Sanctum::actingAs($admin);
        $today = now()->toDateString();
        $open = $this->postJson('/api/attendance/sheets/open', [
            'timetable_entry_id' => $slot->id,
            'date' => $today,
        ]);
        $sheetId = $open->json('sheet.id');
        $studentProfile = StudentProfile::where('institution_id', $this->institution->id)->firstOrFail();
        $this->putJson("/api/attendance/sheets/{$sheetId}/records", [
            'records' => [
                ['student_profile_id' => $studentProfile->id, 'status' => 'present'],
            ],
        ]);

        $this->deleteJson('/api/timetable/entries/' . $slot->id)->assertStatus(409);
    }
}
