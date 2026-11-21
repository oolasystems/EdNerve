<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Programme;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuestionBankTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'StrongPass@123';

    private Institution $institution;
    private int $adminRoleId;
    private int $officerRoleId;
    private int $facultyRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institution = Institution::create([
            'code' => 'QBKC',
            'name' => 'Question Bank Test College',
            'slug' => 'qbkc',
            'institution_type' => 'university',
            'country' => 'India',
            'status' => 'active',
        ]);

        $this->adminRoleId = Role::create(['code' => 'institution_admin', 'name' => 'Institution Admin', 'status' => 'active'])->id;
        $this->officerRoleId = Role::create(['code' => 'examination_officer', 'name' => 'Examination Officer', 'status' => 'active'])->id;
        $this->facultyRoleId = Role::create(['code' => 'faculty', 'name' => 'Faculty', 'status' => 'active'])->id;
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

    private function seedSubject(): Subject
    {
        Programme::create([
            'institution_id' => $this->institution->id,
            'code' => 'BTCSE',
            'name' => 'B.Tech CSE',
            'level' => 'UG',
            'duration_semesters' => 8,
            'status' => 'active',
        ]);

        return Subject::create([
            'institution_id' => $this->institution->id,
            'code' => 'CS501',
            'name' => 'Algorithms',
            'credits' => 4,
            'subject_type' => 'theory',
            'status' => 'active',
        ]);
    }

    private function storeQuestion(User $actor, Subject $subject, array $overrides = []): array
    {
        Sanctum::actingAs($actor);

        return $this->postJson('/api/question-bank', array_merge([
            'subject_id' => $subject->id,
            'unit' => 'Graphs',
            'difficulty' => 'medium',
            'type' => 'long',
            'marks' => 10,
            'question_text' => 'Prove that Dijkstra\'s algorithm finds shortest paths with non-negative weights.',
            'model_answer' => 'Greedy choice: relaxing the minimum-distance unsettled node and marking it settled is optimal because no shorter path exists through remaining nodes with non-negative edges. Induction on settled set size completes the proof.',
            'tags' => 'graphs, shortest path',
            'status' => 'active',
        ], $overrides))->assertCreated()->json('question');
    }

    public function test_faculty_cannot_view_question_bank(): void
    {
        $subject = $this->seedSubject();
        $faculty = $this->makeUser('faculty.qbkc.test', $this->facultyRoleId);

        Sanctum::actingAs($faculty);
        $this->getJson('/api/question-bank')->assertForbidden();
        $this->postJson('/api/question-bank', [
            'subject_id' => $subject->id,
            'difficulty' => 'easy',
            'type' => 'mcq',
            'marks' => 2,
            'question_text' => 'x',
            'model_answer' => 'y',
        ])->assertForbidden();
    }

    public function test_officer_can_crud_and_roundtrip_encrypted_question(): void
    {
        $subject = $this->seedSubject();
        $officer = $this->makeUser('officer.qbkc.test', $this->officerRoleId);

        $question = $this->storeQuestion($officer, $subject);
        $this->assertSame('Prove that Dijkstra\'s algorithm finds shortest paths with non-negative weights.', $question['question_text']);

        // Storage stays encrypted at rest.
        $raw = \DB::table('question_bank_questions')->where('id', $question['id'])->first();
        $this->assertStringNotContainsString('Dijkstra', (string) $raw->question_text);

        // Editing round-trips through the encrypted columns.
        $this->patchJson('/api/question-bank/' . $question['id'], [
            'model_answer' => 'Induction over settled nodes; relaxation yields monotone tentative distances.',
        ])->assertOk();

        $updated = $this->getJson('/api/question-bank/' . $question['id'])->assertOk()->json('question');
        $this->assertStringContainsString('Induction over settled nodes', $updated['model_answer']);
    }

    public function test_blueprint_generation_and_final_lock(): void
    {
        $subject = $this->seedSubject();
        $officer = $this->makeUser('officer.qbkc.test', $this->officerRoleId);

        // Two active questions for the blueprint to draw.
        $this->storeQuestion($officer, $subject, ['unit' => 'Graphs', 'difficulty' => 'medium', 'marks' => 10]);
        $this->storeQuestion($officer, $subject, ['unit' => 'Sorting', 'difficulty' => 'medium', 'marks' => 10]);

        $generated = $this->postJson('/api/question-bank/papers/generate', [
            'subject_id' => $subject->id,
            'sections' => [
                ['title' => 'Section A', 'count' => 2, 'marks' => 10],
            ],
        ])->assertCreated()->json('paper');

        $this->assertEquals(20.0, (float) $generated['total_marks']);
        $this->assertSame('blueprint', $generated['source']);

        // Blueprint over-subscription fails cleanly.
        $this->postJson('/api/question-bank/papers/generate', [
            'subject_id' => $subject->id,
            'sections' => [
                ['title' => 'Too many', 'count' => 99, 'marks' => 1],
            ],
        ])->assertStatus(422);

        // Locking to final prevents item changes.
        $this->patchJson('/api/question-bank/papers/' . $generated['id'], ['status' => 'final'])->assertOk();

        $section = $generated['sections'][0];
        $this->postJson('/api/question-bank/papers/' . $generated['id'] . '/items', [
            'section_id' => $section['id'],
            'question_id' => $section['items'][0]['question_id'],
        ])->assertStatus(409);
    }

    public function test_admin_can_access_question_bank(): void
    {
        $subject = $this->seedSubject();
        $admin = $this->makeUser('admin.qbkc.test', $this->adminRoleId);

        Sanctum::actingAs($admin);
        $this->getJson('/api/question-bank')->assertOk();
        $this->postJson('/api/question-bank', [
            'subject_id' => $subject->id,
            'difficulty' => 'easy',
            'type' => 'short',
            'marks' => 5,
            'question_text' => 'Explain O notation.',
            'model_answer' => 'Asymptotic upper bound on growth rate.',
        ])->assertCreated();
    }
}
