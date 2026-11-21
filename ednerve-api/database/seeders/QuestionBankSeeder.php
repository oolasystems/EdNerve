<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\QuestionBankQuestion;
use App\Models\QuestionPaper;
use App\Models\QuestionPaperItem;
use App\Models\QuestionPaperSection;

/**
 * Seed a few demo questions per BTCSE Sem-3 subject (with model answers,
 * encrypted at rest) and one demo question paper.
 * Idempotent.
 */
class QuestionBankSeeder extends Seeder
{
    public function run(): void
    {
        $institutions = DB::table('institutions')->get();

        $demoQuestions = [
            ['code' => 'CS301', 'unit' => 'Searching', 'difficulty' => 'easy', 'type' => 'mcq', 'marks' => 5,
             'question' => 'What is the time complexity of binary search on a sorted array of n elements?',
             'answer' => 'O(log n). Each comparison eliminates half of the remaining search space.', 'tags' => 'complexity, searching'],
            ['code' => 'CS301', 'unit' => 'Data Structures', 'difficulty' => 'medium', 'type' => 'short', 'marks' => 10,
             'question' => 'Linked list vs array — when would you prefer a linked list?',
             'answer' => 'When you need frequent insertions/deletions at arbitrary positions without shifting elements (O(1) pointer updates instead of O(n) shifts), and when the size is unpredictable. Trade-off: no O(1) random access and extra pointer overhead.', 'tags' => 'linked list, array'],
            ['code' => 'CS301', 'unit' => 'Graphs', 'difficulty' => 'hard', 'type' => 'long', 'marks' => 15,
             'question' => 'Explain Dijkstra\'s shortest-path algorithm and its complexity with a binary heap.',
             'answer' => 'Greedy algorithm maintaining a tentative distance map, relaxing edges of the extracted minimum node, skipping already-finalised nodes. With a binary heap, each extract-min is O(log V) and each edge relaxation may decrease-key in O(log V); total O((V + E) log V). Requires non-negative edge weights.', 'tags' => 'graphs, shortest path'],

            ['code' => 'CS302', 'unit' => 'Normalization', 'difficulty' => 'easy', 'type' => 'mcq', 'marks' => 5,
             'question' => 'Which normal form removes transitive dependency?',
             'answer' => 'Third Normal Form (3NF). It requires that every non-key attribute depend directly on the key, not transitively through another non-key attribute.', 'tags' => 'normalization, 3nf'],
            ['code' => 'CS302', 'unit' => 'SQL', 'difficulty' => 'medium', 'type' => 'short', 'marks' => 10,
             'question' => 'Difference between TRUNCATE and DELETE in SQL.',
             'answer' => 'DELETE is DML: row-by-row removal, can target rows with WHERE, fully logged, can fire triggers. TRUNCATE is DDL: deallocates whole pages, cannot filter rows, minimally logged, faster, cannot fire row-level triggers, resets identity counters.', 'tags' => 'sql, dml, ddl'],

            ['code' => 'CS303', 'unit' => 'Processes', 'difficulty' => 'easy', 'type' => 'mcq', 'marks' => 5,
             'question' => 'Which of the following is NOT a process state?',
             'answer' => '"Running-and-waiting" is not a state. Valid states: New, Ready, Running, Waiting (Blocked), Terminated.', 'tags' => 'process, states'],
            ['code' => 'CS303', 'unit' => 'Synchronization', 'difficulty' => 'medium', 'type' => 'short', 'marks' => 10,
             'question' => 'Explain the difference between a mutex and a semaphore.',
             'answer' => 'A mutex is a binary lock with ownership — the thread that locks it must unlock it (protects a single resource). A semaphore is a counter without ownership — any thread can signal it; counting semaphores allow N concurrent accesses; binary semaphore <= 1 but without ownership semantics.', 'tags' => 'synchronization, mutex, semaphore'],
        ];

        foreach ($institutions as $institution) {
            $questionIds = [];

            foreach ($demoQuestions as $demo) {
                $subject = DB::table('subjects')
                    ->where('institution_id', $institution->id)
                    ->where('code', $demo['code'])
                    ->first(['id']);
                if (!$subject) {
                    continue;
                }

                // With encryption we cannot dedupe by text; dedupe by (subject, unit, difficulty, type, marks).
                $existing = QuestionBankQuestion::where('institution_id', $institution->id)
                    ->where('subject_id', $subject->id)
                    ->where('unit', $demo['unit'])
                    ->where('difficulty', $demo['difficulty'])
                    ->where('type', $demo['type'])
                    ->where('marks', $demo['marks'])
                    ->first();

                if ($existing) {
                    $questionIds[$demo['code'] . '-' . $demo['marks']] = $existing->id;
                    continue;
                }

                $question = QuestionBankQuestion::create([
                    'institution_id' => $institution->id,
                    'subject_id' => $subject->id,
                    'unit' => $demo['unit'],
                    'difficulty' => $demo['difficulty'],
                    'type' => $demo['type'],
                    'marks' => $demo['marks'],
                    'question_text' => $demo['question'],
                    'model_answer' => $demo['answer'],
                    'tags' => $demo['tags'],
                    'status' => 'active',
                ]);

                $questionIds[$demo['code'] . '-' . $demo['marks']] = $question->id;
            }

            // Demo paper: CS301 — BTCSE-CS301-2026-001 (manual, 2 sections, 20 marks)
            $subject = DB::table('subjects')
                ->where('institution_id', $institution->id)
                ->where('code', 'CS301')
                ->first(['id']);
            if (!$subject || !isset($questionIds['CS301-5'], $questionIds['CS301-10'], $questionIds['CS301-15'])) {
                continue;
            }

            $code = 'BTCSE-CS301-2026-001';
            $paperId = DB::table('question_papers')
                ->where('institution_id', $institution->id)
                ->where('code', $code)
                ->value('id');

            if (!$paperId) {
                $paperId = DB::table('question_papers')->insertGetId([
                    'institution_id' => $institution->id,
                    'subject_id' => $subject->id,
                    'code' => $code,
                    'instructions' => 'Answer all questions. Write your application number on the answer booklet. Carry your hall ticket.',
                    'status' => 'draft',
                    'source' => 'manual',
                    'total_marks' => 0,
                    'created_by' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);

                $sectionA = DB::table('question_paper_sections')->insertGetId([
                    'institution_id' => $institution->id,
                    'paper_id' => $paperId,
                    'title' => 'Section A',
                    'instruction' => 'Answer all questions. Each carries 5 marks.',
                    'position' => 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
                $sectionB = DB::table('question_paper_sections')->insertGetId([
                    'institution_id' => $institution->id,
                    'paper_id' => $paperId,
                    'title' => 'Section B',
                    'instruction' => 'Answer the following. Each carries 15 marks.',
                    'position' => 2,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);

                $paper = QuestionPaper::find($paperId);
                foreach ([
                    ['section' => $sectionA, 'question' => $questionIds['CS301-5'], 'position' => 1, 'marks' => 5],
                    ['section' => $sectionB, 'question' => $questionIds['CS301-15'], 'position' => 1, 'marks' => 15],
                ] as $item) {
                    DB::table('question_paper_items')->insert([
                        'institution_id' => $institution->id,
                        'paper_id' => $paperId,
                        'section_id' => $item['section'],
                        'question_id' => $item['question'],
                        'position' => $item['position'],
                        'marks_override' => $item['marks'],
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]);
                }

                DB::table('question_papers')->where('id', $paperId)->update([
                    'total_marks' => 20,
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
