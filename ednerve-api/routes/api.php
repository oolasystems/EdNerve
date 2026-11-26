
<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InstitutionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AcademicUnitController;
use App\Http\Controllers\Api\FacultyController;
use App\Http\Controllers\Api\SchoolController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\ExaminationController;
use App\Http\Controllers\Api\ExamDutyController;
use App\Http\Controllers\Api\SecurityAlertController;
use App\Http\Controllers\Api\SecurityAuditController;
use App\Http\Controllers\Api\MfaController;
use App\Http\Controllers\Api\QuestionBankController;
use App\Http\Controllers\Api\QuestionPaperApprovalController;
use App\Http\Controllers\Api\QuestionPaperTemplateController;
use App\Http\Controllers\Api\CredentialController;
use App\Http\Controllers\Api\OrdinanceController;
use App\Http\Controllers\Api\AssessmentSchemeController;
use App\Http\Controllers\Api\ExamInfrastructureController;
use App\Http\Controllers\Api\ExamSchedulingController;
use App\Http\Controllers\Api\UserRoleAssignmentController;
use App\Http\Controllers\Api\PlatformUserController;
use App\Http\Controllers\Api\PlatformRoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\PlatformSettingController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\SystemHealthController;
use App\Http\Controllers\Api\PlatformAssignmentController;
use App\Http\Controllers\Api\AcademicSessionController;
use App\Http\Controllers\Api\AcademicCalendarController;
use App\Http\Controllers\Api\FacultyWorkloadController;
use App\Http\Controllers\Api\LessonPlanController;
use App\Http\Controllers\Api\AcademicSectionController;
use App\Http\Controllers\Api\CourseRegistrationController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\ProgrammeController;
use App\Http\Controllers\Api\ProgrammeCourseController;
use App\Http\Controllers\Api\SyllabusController;
use App\Http\Controllers\Api\PrerequisiteController;
use App\Http\Controllers\Api\SubjectComponentController;
use App\Http\Controllers\Api\ElectiveGroupController;
use App\Http\Controllers\Api\PublicAdmissionController;
use App\Http\Controllers\Api\ApplicantRegistrationController;
use App\Http\Controllers\Api\ApplicantAuthController;
use App\Http\Controllers\Api\ApplicantPortalController;
use App\Http\Controllers\Api\AdmissionController;
use App\Http\Controllers\Api\ExamDutySwapController;

/*
|--------------------------------------------------------------------------
| API Health Check
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'OolaEdNerve API is running.',
    ]);
});

/*
|--------------------------------------------------------------------------
| Public Authentication Routes
|--------------------------------------------------------------------------
*/

Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:auth-login');

// MFA: challenge endpoint is public (password step already happened); the rest
// require an authenticated session.
Route::post('/auth/mfa/challenge', [MfaController::class, 'challenge'])
    ->middleware('throttle:auth-login');

/*
|--------------------------------------------------------------------------
| Public Applicant Portal (no authentication)
|--------------------------------------------------------------------------
*/

Route::prefix('/public/applicants')->controller(ApplicantRegistrationController::class)->group(function () {
    Route::post('/register', 'register')->middleware('throttle:applicant-register');
    Route::post('/verify-email', 'verifyEmail')->middleware('throttle:applicant-verify');
    Route::post('/resend-verification', 'resendVerification')->middleware('throttle:applicant-resend');
    Route::post('/set-password', 'setPassword')->middleware('throttle:applicant-register');
});

Route::post('/public/applicants/login', [ApplicantAuthController::class, 'login'])
    ->middleware('throttle:applicant-login');

Route::middleware(['auth:sanctum', 'applicant'])
    ->prefix('/applicants/auth')
    ->controller(ApplicantAuthController::class)
    ->group(function () {
        Route::get('/me', 'me');
        Route::post('/logout', 'logout');
    });

Route::middleware(['auth:sanctum', 'applicant'])
    ->prefix('/applicant')
    ->controller(ApplicantPortalController::class)
    ->group(function () {
        Route::get('/profile', 'profile');
        Route::put('/profile', 'updateProfile');
        Route::get('/admissions/intakes', 'intakes');
        Route::get('/applications', 'applications');
        Route::post('/applications', 'storeApplication');
        Route::get('/applications/{id}', 'showApplication');
        Route::put('/applications/{id}', 'updateApplication');
        Route::get('/applications/{id}/form', [\App\Http\Controllers\Api\ApplicantApplicationFormController::class, 'show']);
        Route::put('/applications/{id}/answers', [\App\Http\Controllers\Api\ApplicantApplicationFormController::class, 'saveAnswers']);
        Route::put('/applications/{id}/qualifications', [\App\Http\Controllers\Api\ApplicantApplicationFormController::class, 'saveQualifications']);
    });

Route::get('/public/admissions/{institutionCode}', [PublicAdmissionController::class, 'institution']);
Route::post('/public/applications', [PublicAdmissionController::class, 'submit'])
    ->middleware('throttle:public-apply');
Route::get('/public/application-status', [PublicAdmissionController::class, 'status'])
    ->middleware('throttle:public-apply');

// Digital Academic Credentials — public verification surface
Route::get('/.well-known/verification-keys.json', [CredentialController::class, 'verificationKeys']);
Route::get('/verify/credentials/{credentialNumber}', [CredentialController::class, 'verify'])
    ->where('credentialNumber', '[A-Za-z0-9\-]+');

Route::post('/auth/password/setup', [AuthController::class, 'setupPassword'])
    ->middleware('throttle:auth-login');

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'institution.account'])->group(function () {

    // Authentication
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/tokens', [MfaController::class, 'tokens']);
    Route::delete('/auth/tokens/{id}', [MfaController::class, 'revokeToken'])->whereNumber('id');
    Route::delete('/auth/tokens', [MfaController::class, 'revokeAllTokens']);
    Route::post('/auth/mfa/enrol', [MfaController::class, 'enrol']);
    Route::post('/auth/mfa/confirm', [MfaController::class, 'confirm']);
    Route::post('/auth/mfa/status', [MfaController::class, 'status']);
    Route::post('/auth/mfa/disable', [MfaController::class, 'disable']);

    /*
    |--------------------------------------------------------------------------
    | Platform Admin Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('platform')->prefix('platform')->group(function () {

        Route::get('dashboard', [InstitutionController::class, 'dashboard']);

        Route::apiResource('institutions', InstitutionController::class)
            ->only(['index', 'show', 'store', 'update']);

        Route::patch('institutions/{id}/status', [InstitutionController::class, 'updateStatus']);

        // Platform-wide user administration
        Route::get('users', [PlatformUserController::class, 'index']);
        Route::get('users/{id}', [PlatformUserController::class, 'show'])
            ->whereNumber('id');
        Route::patch('users/{id}/status', [PlatformUserController::class, 'updateStatus'])
            ->whereNumber('id');
        Route::post('platform-users', [PlatformUserController::class, 'store']);

        // Roles and permission mappings (platform authority)
        Route::get('roles', [PlatformRoleController::class, 'index']);
        Route::post('roles', [PlatformRoleController::class, 'store']);
        Route::get('roles/{role}', [PlatformRoleController::class, 'show']);
        Route::patch('roles/{role}', [PlatformRoleController::class, 'update']);
        Route::delete('roles/{role}', [PlatformRoleController::class, 'destroy']);
        Route::put('roles/{role}/permissions', [PlatformRoleController::class, 'syncPermissions']);
        Route::post('roles/{role}/copy-permissions/{sourceRole}', [PlatformRoleController::class, 'copyPermissions']);
        Route::get('permissions', [PermissionController::class, 'index']);
        Route::post('permissions', [PermissionController::class, 'store']);
        Route::patch('permissions/{permission}', [PermissionController::class, 'update']);

        // User detail: effective access (read-only lens)
        Route::get('users/{id}/effective-permissions', [PlatformUserController::class, 'effectivePermissions'])
            ->whereNumber('id');

        // Global platform configuration
        Route::get('settings', [PlatformSettingController::class, 'index']);
        Route::put('settings', [PlatformSettingController::class, 'update']);

        // Audit trail and system administration
        Route::get('audit-logs', [AuditLogController::class, 'index']);
        Route::get('system-health', [SystemHealthController::class, 'show']);

        // Platform-wide role assignments (read + activate/deactivate)
        Route::get('role-assignments', [PlatformAssignmentController::class, 'index']);
        Route::patch('role-assignments/{id}/status', [PlatformAssignmentController::class, 'updateStatus'])
            ->whereNumber('id');

        // Audit integrity verification (platform admin)
        Route::get('security/audit/verify', [SecurityAuditController::class, 'verify']);
    });

    /*
    |--------------------------------------------------------------------------
    | Institution User Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('institution')->group(function () {

        // Current institution
        Route::get('/institution', [InstitutionController::class, 'show']);
        Route::patch('/institution', [InstitutionController::class, 'updateProfile']);

        // Universal role catalogue
        Route::get('/roles', [RoleController::class, 'index']);
        Route::get('/roles/{role}', [RoleController::class, 'show']);

        // User Management
        Route::apiResource('users', UserController::class);

        // Academic Management
        Route::apiResource(
            'faculties',
            FacultyController::class
        )->only(['index', 'store', 'update', 'destroy']);

        Route::apiResource(
            'schools',
            SchoolController::class
        )->only(['index', 'store', 'update', 'destroy']);

        Route::apiResource(
            'academic-units',
            AcademicUnitController::class
        )->only(['index', 'store', 'update', 'destroy']);

        // User Role Assignments
        Route::apiResource(
            'role-assignments',
            UserRoleAssignmentController::class
        );

        // Academic Core
        Route::apiResource(
            'academic-sessions',
            AcademicSessionController::class
        )->only(['index', 'store', 'update', 'destroy']);

        Route::patch(
            'academic-sessions/{id}/activate',
            [AcademicSessionController::class, 'activate']
        );

        Route::apiResource(
            'academic-calendar',
            AcademicCalendarController::class
        )->only(['index', 'store', 'show', 'update', 'destroy']);

        Route::apiResource(
            'faculty-workload',
            FacultyWorkloadController::class
        )->only(['index', 'store', 'update', 'destroy']);

        Route::apiResource(
            'lesson-plans',
            LessonPlanController::class
        )->only(['index', 'store', 'update', 'destroy']);

        Route::apiResource(
            'academic-sections',
            AcademicSectionController::class
        )->only(['index', 'store', 'update', 'destroy']);

        Route::apiResource(
            'course-registrations',
            CourseRegistrationController::class
        )->only(['index', 'store', 'update', 'destroy']);

        Route::apiResource(
            'subjects',
            SubjectController::class
        )->only(['index', 'store', 'update', 'destroy']);

        Route::apiResource(
            'programmes',
            ProgrammeController::class
        )->only(['index', 'store', 'update', 'destroy']);

        Route::get(
            'programmes/{id}/courses',
            [ProgrammeController::class, 'courses']
        );

        Route::apiResource(
            'programme-courses',
            ProgrammeCourseController::class
        )->only(['store', 'update', 'destroy']);

        // Syllabus (Phase A — versioned, SoD-controlled publish lifecycle)
        Route::get('syllabi', [SyllabusController::class, 'index']);
        Route::post('syllabi', [SyllabusController::class, 'store']);
        Route::get('syllabi/{id}', [SyllabusController::class, 'show'])->whereNumber('id');
        Route::put('syllabi/{id}', [SyllabusController::class, 'update'])->whereNumber('id');
        Route::post('syllabi/{id}/submit', [SyllabusController::class, 'submit'])->whereNumber('id');
        Route::post('syllabi/{id}/approve', [SyllabusController::class, 'approve'])->whereNumber('id');
        Route::post('syllabi/{id}/publish', [SyllabusController::class, 'publish'])->whereNumber('id');
        Route::post('syllabi/{id}/request-changes', [SyllabusController::class, 'requestChanges'])->whereNumber('id');
        Route::post('syllabi/{id}/clone', [SyllabusController::class, 'clone'])->whereNumber('id');
        Route::delete('syllabi/{id}', [SyllabusController::class, 'destroy'])->whereNumber('id');

        // Course structure extensions (Phase A)
        Route::get('subjects/{id}/prerequisites', [PrerequisiteController::class, 'index'])->whereNumber('id');
        Route::post('subjects/{id}/prerequisites', [PrerequisiteController::class, 'store'])->whereNumber('id');
        Route::delete('subjects/{id}/prerequisites/{prerequisiteId}', [PrerequisiteController::class, 'destroy'])->whereNumber('id')->whereNumber('prerequisiteId');
        Route::get('subjects/{id}/components', [SubjectComponentController::class, 'index'])->whereNumber('id');
        Route::post('subjects/{id}/components', [SubjectComponentController::class, 'upsert'])->whereNumber('id');
        Route::delete('subjects/{id}/components/{kind}', [SubjectComponentController::class, 'destroy'])->whereNumber('id');
        Route::get('elective-groups', [ElectiveGroupController::class, 'index']);
        Route::post('elective-groups', [ElectiveGroupController::class, 'store']);
        Route::patch('elective-groups/{id}', [ElectiveGroupController::class, 'update'])->whereNumber('id');
        Route::delete('elective-groups/{id}', [ElectiveGroupController::class, 'destroy'])->whereNumber('id');
        Route::post('elective-groups/{id}/subjects', [ElectiveGroupController::class, 'attachSubject'])->whereNumber('id');
        Route::delete('elective-groups/{id}/subjects/{subjectId}', [ElectiveGroupController::class, 'detachSubject'])->whereNumber('id')->whereNumber('subjectId');

        // Admissions
        Route::get('/admissions/intakes', [AdmissionController::class, 'intakes']);
        Route::post('/admissions/intakes', [AdmissionController::class, 'storeIntake']);
        Route::patch('/admissions/intakes/{id}', [AdmissionController::class, 'updateIntake']);
        Route::delete('/admissions/intakes/{id}', [AdmissionController::class, 'destroyIntake']);
        Route::get('/admissions/applications', [AdmissionController::class, 'applications']);
        Route::get('/admissions/applications/{id}', [AdmissionController::class, 'show']);
        Route::patch('/admissions/applications/{id}/decision', [AdmissionController::class, 'decide']);
        Route::post('/admissions/applications/{id}/enroll', [AdmissionController::class, 'enroll']);

        // Students
        Route::apiResource('students', StudentController::class)->only(['index', 'show', 'update']);
        Route::patch('/students/{id}/status', [StudentController::class, 'updateStatus']);

        // Finance
        Route::get('/finance/fee-heads', [FinanceController::class, 'heads']);
        Route::get('/finance/structures', [FinanceController::class, 'structures']);
        Route::post('/finance/structures', [FinanceController::class, 'storeStructure']);
        Route::get('/students/{id}/ledger', [FinanceController::class, 'ledger']);
        Route::post('/students/{id}/payments', [FinanceController::class, 'recordPayment'])->middleware('mfa.privileged');
        Route::get('/receipts', [FinanceController::class, 'receipts']);

        // Attendance & Timetable
        Route::get('/timetable', [AttendanceController::class, 'index']);
        Route::get('/timetable/mine', [AttendanceController::class, 'mine']);
        Route::post('/timetable/entries', [AttendanceController::class, 'store']);
        Route::patch('/timetable/entries/{id}', [AttendanceController::class, 'update']);
        Route::delete('/timetable/entries/{id}', [AttendanceController::class, 'destroy']);
        Route::post('/attendance/sheets/open', [AttendanceController::class, 'openSheet']);
        Route::get('/attendance/sheets/{sheet}', [AttendanceController::class, 'showSheet']);
        Route::put('/attendance/sheets/{sheet}/records', [AttendanceController::class, 'saveSheet']);
        Route::get('/attendance/summary', [AttendanceController::class, 'summary']);

        // Examinations
        Route::get('/examinations', [ExaminationController::class, 'index']);
        Route::post('/examinations', [ExaminationController::class, 'store']);
        Route::get('/examinations/{id}', [ExaminationController::class, 'show'])->whereNumber('id');
        Route::patch('/examinations/{id}', [ExaminationController::class, 'update'])->whereNumber('id');
        Route::delete('/examinations/{id}', [ExaminationController::class, 'destroy'])->whereNumber('id');
        Route::post('/examinations/{id}/subjects', [ExaminationController::class, 'storeSubject'])->whereNumber('id');
        Route::patch('/examinations/{id}/subjects/{subjectEntryId}', [ExaminationController::class, 'updateSubject'])->whereNumber('id');
        Route::delete('/examinations/{id}/subjects/{subjectEntryId}', [ExaminationController::class, 'destroySubject'])->whereNumber('id');
        Route::get('/examinations/{id}/marks', [ExaminationController::class, 'marksGrid'])->whereNumber('id');
        Route::put('/examinations/{id}/marks', [ExaminationController::class, 'saveMarks'])->whereNumber('id')->middleware('mfa.privileged');
        Route::get('/examinations/{id}/results', [ExaminationController::class, 'results'])->whereNumber('id');
        Route::post('/examinations/{id}/publish', [ExaminationController::class, 'publish'])->whereNumber('id')->middleware('mfa.privileged');
        Route::post('/examinations/{id}/subjects/{subjectEntryId}/invigilations', [ExaminationController::class, 'assignInvigilator'])->whereNumber('id');
        Route::delete('/examinations/{id}/invigilations/{invigilationId}', [ExaminationController::class, 'removeInvigilator'])->whereNumber('id');
        Route::get('/examinations/invigilation/mine', [ExaminationController::class, 'myInvigilations']);
        // Revaluation management
        Route::get('/examinations/revaluations', [ExaminationController::class, 'listRevaluations']);
        Route::post('/examinations/revaluations', [ExaminationController::class, 'storeRevaluation']);
        Route::patch('/examinations/revaluations/{id}/status', [ExaminationController::class, 'updateRevaluationStatus'])
            ->whereNumber('id');

        // Hall ticket
        Route::get('/examinations/{examId}/hall-ticket', [ExaminationController::class, 'hallTicket'])
            ->whereNumber('examId');

        // Examination duty management (F5)
        Route::get('/examinations/duty-roles', [ExamDutyController::class, 'roles']);
        Route::post('/examinations/duty-roles', [ExamDutyController::class, 'storeRole']);
        Route::patch('/examinations/duty-roles/{id}', [ExamDutyController::class, 'updateRole'])->whereNumber('id');

        Route::get('/examinations/duties/mine', [ExamDutyController::class, 'myDuties']);
        Route::get('/examinations/{examId}/duty-roster', [ExamDutyController::class, 'roster'])->whereNumber('examId');
        Route::post('/examinations/{examId}/duty-roster/submit', [ExamDutyController::class, 'submitRoster'])->whereNumber('examId');
        Route::post('/examinations/{examId}/duty-roster/approve', [ExamDutyController::class, 'approveRoster'])->whereNumber('examId');
        Route::post('/examinations/{examId}/duty-roster/reject',  [ExamDutyController::class, 'rejectRoster'])->whereNumber('examId');
        Route::post('/examinations/{examId}/duty-roster/publish', [ExamDutyController::class, 'publishRoster'])->whereNumber('examId');
        Route::post('/examinations/{examId}/duty-roster/new-version', [ExamDutyController::class, 'createRosterVersion'])->whereNumber('examId');
        Route::get('/examinations/{examId}/duty-roster/history', [ExamDutyController::class, 'rosterHistory'])->whereNumber('examId');
        Route::get('/examinations/{examId}/duty-roster/print', [ExamDutyController::class, 'printPayload'])->whereNumber('examId');
        Route::post('/examinations/{examId}/duty-roster/auto-assign', [ExamDutyController::class, 'autoAssign'])->whereNumber('examId');

        Route::get('/examinations/{examId}/duty-availability', [ExamDutyController::class, 'listAvailability'])->whereNumber('examId');
        Route::post('/examinations/{examId}/duty-availability', [ExamDutyController::class, 'storeAvailability'])->whereNumber('examId');

        Route::post('/examinations/{examId}/duty-assignments', [ExamDutyController::class, 'storeAssignment'])->whereNumber('examId');
        Route::patch('/examinations/{examId}/duty-assignments/{id}', [ExamDutyController::class, 'updateAssignment'])->whereNumber('examId')->whereNumber('id');
        Route::post('/examinations/{examId}/duty-assignments/{id}/respond', [ExamDutyController::class, 'respondToAssignment'])->whereNumber('examId')->whereNumber('id');
        Route::delete('/examinations/{examId}/duty-assignments/{id}', [ExamDutyController::class, 'destroyAssignment'])->whereNumber('examId')->whereNumber('id');

        // F5.3-F: Examination duty swaps
        Route::post(
            '/examinations/{examId}/duty-swaps',
            [ExamDutySwapController::class, 'initiate']
        )->whereNumber('examId');

        Route::get(
            '/examinations/{examId}/duty-swaps',
            [ExamDutySwapController::class, 'index']
        )->whereNumber('examId');

        Route::get(
            '/examinations/{examId}/duty-swaps/{swapId}',
            [ExamDutySwapController::class, 'show']
        )->whereNumber('examId')->whereNumber('swapId');

        Route::post(
            '/examinations/{examId}/duty-swaps/{swapId}/respond',
            [ExamDutySwapController::class, 'respond']
        )->whereNumber('examId')->whereNumber('swapId');

        Route::post(
            '/examinations/{examId}/duty-swaps/{swapId}/approve',
            [ExamDutySwapController::class, 'approve']
        )->whereNumber('examId')->whereNumber('swapId')
            ->middleware('mfa.privileged');

        Route::post(
            '/examinations/{examId}/duty-swaps/{swapId}/reject',
            [ExamDutySwapController::class, 'reject']
        )->whereNumber('examId')->whereNumber('swapId')
            ->middleware('mfa.privileged');

        Route::post(
            '/examinations/{examId}/duty-swaps/{swapId}/withdraw',
            [ExamDutySwapController::class, 'withdraw']
        )->whereNumber('examId')->whereNumber('swapId');

        // Examination infrastructure & ordinances (F4)
        Route::get('/examinations/ordinances', [OrdinanceController::class, 'index']);
        Route::post('/examinations/ordinances', [OrdinanceController::class, 'store']);
        Route::patch('/examinations/ordinances/{id}', [OrdinanceController::class, 'update'])->whereNumber('id');
        Route::post('/examinations/ordinances/{id}/set-default', [OrdinanceController::class, 'setDefault'])->whereNumber('id');
        Route::delete('/examinations/ordinances/{id}', [OrdinanceController::class, 'archive'])->whereNumber('id');

        Route::get('/examinations/schemes', [AssessmentSchemeController::class, 'index']);
        Route::post('/examinations/schemes', [AssessmentSchemeController::class, 'store']);
        Route::patch('/examinations/schemes/{id}', [AssessmentSchemeController::class, 'update'])->whereNumber('id');
        Route::delete('/examinations/schemes/{id}', [AssessmentSchemeController::class, 'destroy'])->whereNumber('id');

        Route::get('/examinations/exam-sessions', [ExamInfrastructureController::class, 'listSessions']);
        Route::post('/examinations/exam-sessions', [ExamInfrastructureController::class, 'storeSession']);
        Route::patch('/examinations/exam-sessions/{id}', [ExamInfrastructureController::class, 'updateSession'])->whereNumber('id');
        Route::get('/examinations/centres', [ExamInfrastructureController::class, 'listCentres']);
        Route::post('/examinations/centres', [ExamInfrastructureController::class, 'storeCentre']);
        Route::patch('/examinations/centres/{id}', [ExamInfrastructureController::class, 'updateCentre'])->whereNumber('id');
        Route::get('/examinations/centres/{id}/rooms', [ExamInfrastructureController::class, 'listRooms'])->whereNumber('id');
        Route::post('/examinations/rooms', [ExamInfrastructureController::class, 'storeRoom']);
        Route::patch('/examinations/rooms/{id}', [ExamInfrastructureController::class, 'updateRoom'])->whereNumber('id');
        Route::get('/examinations/exam-kinds', [ExamInfrastructureController::class, 'listKinds']);
        Route::post('/examinations/exam-kinds', [ExamInfrastructureController::class, 'storeKind']);
        Route::patch('/examinations/exam-kinds/{id}', [ExamInfrastructureController::class, 'updateKind'])->whereNumber('id');

        Route::patch('/examinations/{id}/subjects/{entryId}/schedule', [ExamSchedulingController::class, 'assignSchedule'])
            ->whereNumber('id')->whereNumber('entryId');
        Route::post('/examinations/{id}/conflicts/check', [ExamSchedulingController::class, 'conflicts'])->whereNumber('id');
        Route::post('/examinations/{id}/eligibility/compute', [ExamSchedulingController::class, 'computeEligibility'])->whereNumber('id');
        Route::get('/examinations/{id}/eligibility', [ExamSchedulingController::class, 'eligibilityReport'])->whereNumber('id');
        Route::post('/examinations/eligibility/override', [ExamSchedulingController::class, 'overrideEligibility'])
            ->middleware('mfa.privileged');

        // Security & audit integrity
        Route::get('/security/audit/verify', [SecurityAuditController::class, 'verify']);
        Route::get('/security/alerts', [SecurityAlertController::class, 'index']);
        Route::patch('/security/alerts/{id}/ack', [SecurityAlertController::class, 'acknowledge'])->whereNumber('id');

        // Credentials (institution-authenticated)
        Route::get('/credentials', [CredentialController::class, 'index']);
        Route::post('/examinations/{id}/credentials/issue', [CredentialController::class, 'issueMarksheets'])->whereNumber('id');
        Route::patch('/credentials/{id}/revoke', [CredentialController::class, 'revoke'])->whereNumber('id');

        // Question bank & paper setting
        Route::get('/question-bank', [QuestionBankController::class, 'index']);
        Route::post('/question-bank', [QuestionBankController::class, 'store']);
        Route::get('/question-bank/{id}', [QuestionBankController::class, 'show'])->whereNumber('id');
        Route::patch('/question-bank/{id}', [QuestionBankController::class, 'update'])->whereNumber('id');
        Route::delete('/question-bank/{id}', [QuestionBankController::class, 'destroy'])->whereNumber('id');
        Route::get('/question-bank/papers', [QuestionBankController::class, 'papers']);
        Route::post('/question-bank/papers', [QuestionBankController::class, 'storePaper']);
        Route::post('/question-bank/papers/generate', [QuestionBankController::class, 'generatePaper']);
        Route::get('/question-bank/papers/{id}', [QuestionBankController::class, 'showPaper'])->whereNumber('id');
        Route::patch('/question-bank/papers/{id}', [QuestionBankController::class, 'updatePaper'])->whereNumber('id');
        Route::delete('/question-bank/papers/{id}', [QuestionBankController::class, 'destroyPaper'])->whereNumber('id');
        Route::post('/question-bank/papers/{id}/sections', [QuestionBankController::class, 'addPaperSection'])->whereNumber('id');
        Route::delete('/question-bank/papers/{id}/sections/{sectionId}', [QuestionBankController::class, 'removePaperSection'])->whereNumber('id');
        Route::post('/question-bank/papers/{id}/items', [QuestionBankController::class, 'addPaperItem'])->whereNumber('id');
        Route::delete('/question-bank/papers/{id}/items/{itemId}', [QuestionBankController::class, 'removePaperItem'])->whereNumber('id');
        Route::get('/question-bank/papers/{id}/print', [QuestionBankController::class, 'printPaper'])->whereNumber('id');

        // Question paper templates & approval workflow (Phase B)
        Route::get('/question-paper-templates', [QuestionPaperTemplateController::class, 'index']);
        Route::post('/question-paper-templates', [QuestionPaperTemplateController::class, 'store']);
        Route::patch('/question-paper-templates/{id}', [QuestionPaperTemplateController::class, 'update'])->whereNumber('id');
        Route::delete('/question-paper-templates/{id}', [QuestionPaperTemplateController::class, 'destroy'])->whereNumber('id');
        Route::post('/question-bank/papers/{id}/submit', [QuestionPaperApprovalController::class, 'submit'])->whereNumber('id');
        Route::post('/question-bank/papers/{id}/check', [QuestionPaperApprovalController::class, 'check'])->whereNumber('id');
        Route::post('/question-bank/papers/{id}/approve', [QuestionPaperApprovalController::class, 'approve'])->whereNumber('id');
        Route::post('/question-bank/papers/{id}/ratify', [QuestionPaperApprovalController::class, 'ratify'])->whereNumber('id')->middleware('mfa.privileged');
        Route::post('/question-bank/papers/{id}/reject', [QuestionPaperApprovalController::class, 'reject'])->whereNumber('id');
    });
});
