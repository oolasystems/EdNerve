<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Programme;
use App\Models\QuestionPaperTemplate;
use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuestionPaperApprovalTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'StrongPass@123';

    private Institution $institution;
    private Subject $subject;

    private int $adminRoleId;
    private int $officerRoleId;
    private int $facultyRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institution = Institution::create([
            'code' => 'QPAA',
            'name' => 'Paper Institution A',
            'slug' => 'qpaa',
            'institution_type' => 'university',
            'country' => 'India',
            'status' => 'active',
        ]);

        $this->adminRoleId = Role::create(['code' => 'institution_admin', 'name' => 'Institution Admin', 'status' => 'active'])->id;
        $this->officerRoleId = Role::create(['code' => 'examination_officer', 'name' => 'Examination Officer', 'status' => 'active'])->id;
        $this->facultyRoleId = Role::create(['code' => 'faculty', 'name' => 'Faculty', 'status' => 'active'])->id;

        $this->subject = Subject::create([
            'institution_id' => $this->institution->id,
            'code' => 'CS301',
            'name' => 'Data Structures',
            'credits' => 4,
            'subject_type' => 'theory',
            'status' => 'active',
        ]);
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

    private function makeDraftPaperWithItem(User $actor): int
    {
        Sanctum::actingAs($actor);

        $question = $this->postJson('/api/question-bank', [
            'subject_id' => $this->subject->id,
            'difficulty' => 'medium',
            'type' => 'short',
            'marks' => 4,
            'question_text' => 'Explain quicksort partitioning.',
            'model_answer' => 'Partition around pivot...',
            'status' => 'active',
        ])->json('question.id');

        $paperResponse = $this->postJson('/api/question-bank/papers', [
            'subject_id' => $this->subject->id,
            'code' => 'CS301-2026-001',
            'instructions' => 'Attempt all questions.',
            'sections' => [
                ['title' => 'Section A', 'instruction' => 'Attempt all'],
            ],
        ]);
        $paperResponse->assertCreated();
        $paperId = $paperResponse->json('paper.id');
        $sectionId = $paperResponse->json('paper.sections.0.id');

        $this->postJson("/api/question-bank/papers/{$paperId}/items", [
            'section_id' => $sectionId,
            'question_id' => (int) $question,
        ])->assertCreated();

        return $paperId;
    }

    // ----------------------------------------------------------
    // Template catalogue
    // ----------------------------------------------------------

    public function test_template_crud_and_validation(): void
    {
        $admin = $this->makeUser('admin.a.test', $this->adminRoleId);
        Sanctum::actingAs($admin);

        $create = $this->postJson('/api/question-paper-templates', [
            'name' => 'End Semester 100',
            'assessment_kind' => 'exam',
            'total_marks' => 100,
            'pattern' => [
                ['title' => 'Section A', 'position' => 1, 'items' => 10, 'marks' => 2, 'instruction' => 'Attempt all'],
                ['title' => 'Section B', 'position' => 2, 'items' => 5, 'marks' => 16, 'instruction' => 'Attempt any 5'],
            ],
            'instructions_md' => "## Instructions\n- Bring your hall ticket",
        ]);
        $create->assertCreated();
        $templateId = $create->json('template.id');

        $this->getJson('/api/question-paper-templates')
            ->assertOk()
            ->assertJsonFragment(['name' => 'End Semester 100', 'assessment_kind' => 'exam']);

        // duplicate name rejected
        $this->postJson('/api/question-paper-templates', [
            'name' => 'End Semester 100',
            'total_marks' => 100,
        ])->assertStatus(422);

        // bad pattern shape rejected
        $this->postJson('/api/question-paper-templates', [
            'name' => 'Broken',
            'total_marks' => 50,
            'pattern' => [['title' => 'Section A']],
        ])->assertStatus(422);

        $this->patchJson("/api/question-paper-templates/{$templateId}", ['total_marks' => 110])
            ->assertOk()
            ->assertJsonPath('template.total_marks', '110.00');

        $this->deleteJson("/api/question-paper-templates/{$templateId}")->assertOk();
        $this->assertDatabaseHas('question_paper_templates', ['id' => $templateId, 'status' => 'inactive']);
    }

    // ----------------------------------------------------------
    // Full workflow with SoD
    // ----------------------------------------------------------

    public function test_full_workflow_with_separation_of_duties(): void
    {
        $author = $this->makeUser('author.a.test', $this->adminRoleId);
        $checker = $this->makeUser('checker.a.test', $this->officerRoleId);
        $approver = $this->makeUser('approver.a.test', $this->adminRoleId);
        $ratifier = $this->makeUser('ratifier.a.test', $this->officerRoleId);

        $paperId = $this->makeDraftPaperWithItem($author);

        Sanctum::actingAs($author);
        $this->postJson("/api/question-bank/papers/{$paperId}/submit")->assertOk();

        // author cannot check own paper
        $this->postJson("/api/question-bank/papers/{$paperId}/check")->assertForbidden();

        Sanctum::actingAs($checker);
        $this->postJson("/api/question-bank/papers/{$paperId}/check")->assertOk();

        // checker cannot approve own paper either (SoD)
        $this->postJson("/api/question-bank/papers/{$paperId}/approve")->assertForbidden();

        Sanctum::actingAs($approver);
        $this->postJson("/api/question-bank/papers/{$paperId}/approve")->assertOk();

        // approver cannot also ratify (SoD), and ratify without MFA is blocked
        $this->postJson("/api/question-bank/papers/{$paperId}/ratify")->assertForbidden();
        $this->confirmMfa($approver);
        $this->postJson("/api/question-bank/papers/{$paperId}/ratify")->assertForbidden(); // distinct actor required

        Sanctum::actingAs($ratifier);
        // ratify without MFA -> 403 mfa_required
        $this->postJson("/api/question-bank/papers/{$paperId}/ratify")
            ->assertForbidden()
            ->assertJsonPath('code', 'mfa_required');

        $this->confirmMfa($ratifier);
        $this->postJson("/api/question-bank/papers/{$paperId}/ratify")->assertOk();

        $this->assertDatabaseHas('question_papers', [
            'id' => $paperId,
            'status' => 'ratified',
            'total_marks' => 4,
        ]);

        $this->assertDatabaseCount('question_paper_approvals', 4);
    }

    public function test_marks_mismatch_blocks_ratification(): void
    {
        $author = $this->makeUser('author2.a.test', $this->adminRoleId);
        $checker = $this->makeUser('checker2.a.test', $this->officerRoleId);
        $approver = $this->makeUser('approver2.a.test', $this->adminRoleId);
        $ratifier = $this->makeUser('ratifier2.a.test', $this->officerRoleId);
        $this->confirmMfa($ratifier);

        $paperId = $this->makeDraftPaperWithItem($author);

        // simulate a legacy paper declaring 100 while questions sum to 4
        \DB::table('question_papers')->where('id', $paperId)->update(['total_marks' => 100]);

        Sanctum::actingAs($author);
        $this->postJson("/api/question-bank/papers/{$paperId}/submit")->assertOk();
        Sanctum::actingAs($checker);
        $this->postJson("/api/question-bank/papers/{$paperId}/check")->assertOk();
        Sanctum::actingAs($approver);
        $this->postJson("/api/question-bank/papers/{$paperId}/approve")->assertOk();

        Sanctum::actingAs($ratifier);
        $response = $this->postJson("/api/question-bank/papers/{$paperId}/ratify");
        $response->assertStatus(422);
        $this->assertStringContainsString('Marks mismatch', $response->json('message'));
    }

    public function test_reject_returns_paper_to_draft_and_clears_stage_stamps(): void
    {
        $author = $this->makeUser('author3.a.test', $this->adminRoleId);
        $checker = $this->makeUser('checker3.a.test', $this->officerRoleId);

        $paperId = $this->makeDraftPaperWithItem($author);

        Sanctum::actingAs($author);
        $this->postJson("/api/question-bank/papers/{$paperId}/submit")->assertOk();

        Sanctum::actingAs($checker);
        $this->postJson("/api/question-bank/papers/{$paperId}/reject", ['remarks' => 'Question 2 is ambiguous'])->assertOk();

        $this->assertDatabaseHas('question_papers', [
            'id' => $paperId,
            'status' => 'draft',
            'prepared_by' => null,
            'prepared_at' => null,
        ]);

        // can submit again after fixes
        Sanctum::actingAs($author);
        $this->postJson("/api/question-bank/papers/{$paperId}/submit")->assertOk();
    }

    public function test_faculty_cannot_use_approval_workflow(): void
    {
        $faculty = $this->makeUser('fac.a.test', $this->facultyRoleId);
        $author = $this->makeUser('author4.a.test', $this->adminRoleId);

        $paperId = $this->makeDraftPaperWithItem($author);

        Sanctum::actingAs($faculty);
        $this->postJson("/api/question-bank/papers/{$paperId}/submit")->assertForbidden();
        $this->getJson('/api/question-paper-templates')->assertForbidden();
    }
}
