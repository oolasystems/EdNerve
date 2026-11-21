<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AcademicUnitSeeder extends Seeder
{
    /**
     * Seed institution academic hierarchy matching real public structures:
     *   AMU, SPPU, JSPM (via FTS→SoT), JMI — Faculty → units
     *   JNU             — University → School → units  (no faculty tier)
     *   JSPM            — Faculty → School → units   (full 3-tier)
     */
    public function run(): void
    {
        $institutions = DB::table('institutions')->get();

        foreach ($institutions as $institution) {
            $code = $institution->code;
            $id = $institution->id;

            // Idempotent refresh of this institution's hierarchy
            DB::table('academic_units')->where('institution_id', $id)->delete();
            DB::table('schools')->where('institution_id', $id)->delete();
            DB::table('faculties')->where('institution_id', $id)->delete();

            switch ($code) {
                case 'AMU':
                    // Aligarh Muslim University: University → Faculty → Department
                    $this->faculty($id, 'FET', 'Faculty of Engineering & Technology', [
                        ['code' => 'CSE', 'name' => 'Department of Computer Science Engineering', 'type' => 'department'],
                        ['code' => 'ECE', 'name' => 'Department of Electronics Engineering', 'type' => 'department'],
                    ]);
                    $this->faculty($id, 'FST', 'Faculty of Science & Technology', [
                        ['code' => 'PHY', 'name' => 'Department of Physics', 'type' => 'department'],
                    ]);
                    break;

                case 'JMI':
                    // Jamia Millia Islamia: University → Faculty → Department
                    $this->faculty($id, 'FEAT', 'Faculty of Engineering & Technology', [
                        ['code' => 'CSE', 'name' => 'Department of Computer Science Engineering', 'type' => 'department'],
                    ]);
                    break;

                case 'JSPM':
                    // JSPM-style: University → Faculty → School → Department
                    $facultyId = $this->faculty($id, 'FTS', 'Faculty of Technical Sciences & Management', []);
                    $schoolId = $this->school($id, $facultyId, 'SOT', 'School of Technology');
                    $this->unit($id, $facultyId, $schoolId, 'CSE',
                        'Department of Computer Science & Engineering', 'department');
                    break;

                case 'SPPU':
                    // SPPU: University → Faculty → Department
                    $this->faculty($id, 'FE', 'Faculty of Engineering', [
                        ['code' => 'CSE', 'name' => 'Department of Computer Engineering', 'type' => 'department'],
                    ]);
                    break;

                case 'JNU':
                    // JNU: University → School → Academic Unit
                    $schoolId = $this->school($id, null, 'SOE', 'School of Engineering');
                    $this->unit($id, null, $schoolId, 'CSE',
                        'Centre for Computer Science & Engineering', 'department');
                    break;

                default:
                    $this->faculty($id, 'GEN', 'Faculty of Arts & Sciences', [
                        ['code' => 'CSE', 'name' => 'Department of Computer Science', 'type' => 'department'],
                    ]);
            }
        }
    }

    private function faculty(
        int $institutionId,
        string $code,
        string $name,
        array $units
    ): int {
        DB::table('faculties')->updateOrInsert(
            ['institution_id' => $institutionId, 'code' => $code],
            ['name' => $name, 'status' => 'active', 'updated_at' => now(), 'created_at' => now()]
        );

        $facultyId = DB::table('faculties')
            ->where('institution_id', $institutionId)
            ->where('code', $code)
            ->value('id');

        foreach ($units as $unit) {
            $this->unit($institutionId, $facultyId, null, $unit['code'], $unit['name'], $unit['type']);
        }

        return $facultyId;
    }

    private function school(
        int $institutionId,
        ?int $facultyId,
        string $code,
        string $name
    ): int {
        DB::table('schools')->updateOrInsert(
            ['institution_id' => $institutionId, 'code' => $code],
            [
                'faculty_id' => $facultyId,
                'name' => $name,
                'status' => 'active',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return DB::table('schools')
            ->where('institution_id', $institutionId)
            ->where('code', $code)
            ->value('id');
    }

    private function unit(
        int $institutionId,
        ?int $facultyId,
        ?int $schoolId,
        string $code,
        string $name,
        string $type
    ): void {
        DB::table('academic_units')->updateOrInsert(
            ['institution_id' => $institutionId, 'code' => $code],
            [
                'faculty_id' => $facultyId,
                'school_id' => $schoolId,
                'unit_type' => $type,
                'name' => $name,
                'status' => 'active',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
