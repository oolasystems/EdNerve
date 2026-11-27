/* eslint-disable react-refresh/only-export-components -- Route registry intentionally declares lazy page components alongside the router configuration. */
import { lazy, Suspense } from "react";
import {
  createBrowserRouter,
  Navigate,
} from "react-router-dom";

import PlatformAdminGuard from "../modules/auth/PlatformAdminGuard";
import InstitutionGuard from "../modules/auth/InstitutionGuard";
import PlatformAdminLayout from "../layouts/platform-admin/PlatformAdminLayout";
import InstitutionLayout from "../layouts/institution/InstitutionLayout";

import { LoginRoute, HomeRoute } from "./route-redirects";

import NotFoundPage from "../modules/shared/NotFoundPage";
import { LoadingState } from "../shared/ui";

import { ROUTE_PATHS } from "./route-paths";

// Route-level code splitting: each module loads on demand.
const PlatformDashboard = lazy(
  () => import("../modules/platform-dashboard/PlatformDashboard")
);
const InstitutionsPage = lazy(
  () => import("../modules/platform-institutions/pages/InstitutionsPage")
);
const AddInstitutionPage = lazy(
  () => import("../modules/platform-institutions/pages/AddInstitutionPage")
);
const InstitutionDetailsPage = lazy(
  () => import("../modules/platform-institutions/pages/InstitutionDetailsPage")
);
const PermissionsPage = lazy(
  () => import("../modules/platform-access/pages/PermissionsPage")
);
const AllUsersPage = lazy(
  () => import("../modules/platform-users/pages/AllUsersPage")
);
const PlatformUsersPage = lazy(
  () => import("../modules/platform-users/pages/PlatformUsersPage")
);
const UserDetailsPage = lazy(
  () => import("../modules/platform-users/pages/UserDetailsPage")
);
const RolesPage = lazy(
  () => import("../modules/platform-access/pages/RolesPage")
);
const AssignmentsPage = lazy(
  () => import("../modules/platform-access/pages/AssignmentsPage")
);
const AcademicConfigurationPage = lazy(
  () => import("../modules/platform-configuration/pages/AcademicConfigurationPage")
);
const SystemConfigurationPage = lazy(
  () => import("../modules/platform-configuration/pages/SystemConfigurationPage")
);
const AuditLogsPage = lazy(
  () => import("../modules/platform-administration/pages/AuditLogsPage")
);
const SystemHealthPage = lazy(
  () => import("../modules/platform-administration/pages/SystemHealthPage")
);

const StudentsPage = lazy(
  () => import("../modules/students/pages/StudentsPage")
);
const ExaminationsListPage = lazy(
  () => import("../modules/examinations/pages/ExamsPage")
);
const ExaminationsDetailPage = lazy(
  () => import("../modules/examinations/pages/ExamDetailPage")
);
const ExaminationsMarksPage = lazy(
  () => import("../modules/examinations/pages/MarksEntryPage")
);
const ExaminationsResultsPage = lazy(
  () => import("../modules/examinations/pages/ResultsPage")
);
const ExaminationsHallTicketsPage = lazy(
  () => import("../modules/examinations/pages/HallTicketsPage")
);
const ExaminationsRevaluationPage = lazy(
  () => import("../modules/examinations/pages/RevaluationPage")
);
const ExaminationsCredentialsPage = lazy(
  () => import("../modules/examinations/pages/CredentialsPage")
);
const ExamSetupPage = lazy(
  () => import("../modules/examinations/pages/ExamSetupPage")
);
const ExamInfrastructurePage = lazy(
  () => import("../modules/examinations/pages/ExamInfrastructurePage")
);
const ExamOrdinancesPage = lazy(
  () => import("../modules/examinations/pages/ExamOrdinancesPage")
);
const ExamSchemesPage = lazy(
  () => import("../modules/examinations/pages/ExamSchemesPage")
);
const ExaminationsDutyRosterPage = lazy(
  () => import("../modules/examinations/pages/DutyRosterPage")
);
const ExaminationsMyDutiesPage = lazy(
  () => import("../modules/examinations/pages/MyDutiesPage")
);
const ExaminationsBankPage = lazy(
  () => import("../modules/examinations/pages/QuestionBankPage")
);
const ExaminationsPapersPage = lazy(
  () => import("../modules/examinations/pages/QuestionPapersPage")
);
const PaperTemplatesPage = lazy(
  () => import("../modules/examinations/pages/PaperTemplatesPage")
);
const ExaminationsPaperPrintPage = lazy(
  () => import("../modules/examinations/pages/QuestionPaperPrintPage")
);
const AttendanceTimetablePage = lazy(
  () => import("../modules/attendance/pages/TimetablePage")
);
const AttendanceMarkPage = lazy(
  () => import("../modules/attendance/pages/AttendanceMarkPage")
);
const AttendanceSummaryPage = lazy(
  () => import("../modules/attendance/pages/AttendanceSummaryPage")
);
const FinanceStudentLedgerPage = lazy(
  () => import("../modules/finance/pages/FinanceStudentLedgerPage")
);
const FinanceStructuresPage = lazy(
  () => import("../modules/finance/pages/FinanceStructuresPage")
);
const FinanceReceiptsPage = lazy(
  () => import("../modules/finance/pages/FinanceReceiptsPage")
);
const ApplicantEntryPage = lazy(
  () => import("../modules/applicant-portal/pages/ApplicantEntryPage")
);
const VerifyCredentialPage = lazy(
  () => import("../modules/public-portal/pages/VerifyCredentialPage")
);
const ApplicantLoginPage = lazy(() => import("../modules/applicant-portal/pages/ApplicantLoginPage"));
const ApplicantRegisterPage = lazy(() => import("../modules/applicant-portal/pages/ApplicantRegisterPage"));
const ApplicantVerifyEmailPage = lazy(() => import("../modules/applicant-portal/pages/VerifyEmailPage"));
const ApplicantSetPasswordPage = lazy(() => import("../modules/applicant-portal/pages/SetPasswordPage"));
const ApplicantResendVerificationPage = lazy(() => import("../modules/applicant-portal/pages/ResendVerificationPage"));
const ApplicantGuard = lazy(() => import("../modules/applicant-portal/ApplicantGuard"));
const ApplicantPortalLayout = lazy(() => import("../modules/applicant-portal/ApplicantPortalLayout"));
const ApplicantDashboardPage = lazy(() => import("../modules/applicant-portal/pages/ApplicantDashboardPage"));
const ApplicantProfilePage = lazy(() => import("../modules/applicant-portal/pages/ApplicantProfilePage"));
const ApplicantApplicationsPage = lazy(() => import("../modules/applicant-portal/pages/ApplicantApplicationsPage"));
const NewApplicantApplicationPage = lazy(() => import("../modules/applicant-portal/pages/NewApplicantApplicationPage"));
const ApplicantApplicationEditorPage = lazy(() => import("../modules/applicant-portal/pages/ApplicantApplicationEditorPage"));
const AdmissionIntakesPage = lazy(
  () => import("../modules/admissions/pages/AdmissionIntakesPage")
);
const AdmissionApplicationsPage = lazy(
  () => import("../modules/admissions/pages/AdmissionApplicationsPage")
);
const AcademicSessionsPage = lazy(
  () => import("../modules/academic/pages/AcademicSessionsPage")
);
const AcademicCalendarPage = lazy(
  () => import("../modules/academic/pages/AcademicCalendarPage")
);
const AcademicProgrammesPage = lazy(
  () => import("../modules/academic/pages/ProgrammesPage")
);
const AcademicSubjectsPage = lazy(
  () => import("../modules/academic/pages/AcademicSubjectsPage")
);
const SyllabiPage = lazy(
  () => import("../modules/academic/pages/SyllabiPage")
);
const ElectiveGroupsPage = lazy(
  () => import("../modules/academic/pages/ElectiveGroupsPage")
);
const ProgrammeCoursesPage = lazy(
  () => import("../modules/academic/pages/ProgrammeCoursesPage")
);
const FacultyWorkloadPage = lazy(
  () => import("../modules/academic/pages/FacultyWorkloadPage")
);
const LessonPlansPage = lazy(
  () => import("../modules/academic/pages/LessonPlansPage")
);
const AcademicSectionsPage = lazy(
  () => import("../modules/academic/pages/AcademicSectionsPage")
);
const CourseRegistrationPage = lazy(
  () => import("../modules/academic/pages/CourseRegistrationPage")
);
const InstitutionDashboard = lazy(
  () => import("../modules/institution/pages/InstitutionDashboardPage")
);
const InstitutionProfilePage = lazy(
  () => import("../modules/institution/pages/InstitutionProfilePage")
);
const SecurityPage = lazy(
  () => import("../modules/security/SecurityPage")
);
const MfaChallengePage = lazy(
  () => import("../modules/auth/pages/MfaChallengePage")
);
const AcademicUnitsPage = lazy(
  () => import("../modules/institution/pages/AcademicUnitsPage")
);
const InstitutionUsersPage = lazy(
  () => import("../modules/institution/pages/InstitutionUsersPage")
);
const InstitutionRolesPage = lazy(
  () => import("../modules/institution/pages/InstitutionRolesPage")
);
const InstitutionAssignmentsPage = lazy(
  () => import("../modules/institution/pages/InstitutionAssignmentsPage")
);

const suspense = (element: React.ReactNode) => (
  <Suspense fallback={<LoadingState message="Loading..." />}>
    {element}
  </Suspense>
);

export const router = createBrowserRouter([
  {
    path: "/",
    element: <HomeRoute />,
  },
  {
    path: "/apply/:institutionCode",
    element: suspense(<ApplicantEntryPage />),
  },
  {
    path: "/apply/:institutionCode/status",
    element: suspense(<ApplicantEntryPage />),
  },
  { path: "/apply/:institutionCode/register", element: suspense(<ApplicantRegisterPage />) },
  { path: "/apply/:institutionCode/login", element: suspense(<ApplicantLoginPage />) },
  { path: "/apply/:institutionCode/verify-email", element: suspense(<ApplicantVerifyEmailPage />) },
  { path: "/apply/:institutionCode/set-password", element: suspense(<ApplicantSetPasswordPage />) },
  { path: "/apply/:institutionCode/resend-verification", element: suspense(<ApplicantResendVerificationPage />) },
  { path: "/applicant", element: suspense(<ApplicantGuard />), children: [{ path: "", element: suspense(<ApplicantPortalLayout />), children: [
    { path: "dashboard", element: suspense(<ApplicantDashboardPage />) },
    { path: "profile", element: suspense(<ApplicantProfilePage />) },
    { path: "applications", element: suspense(<ApplicantApplicationsPage />) },
    { path: "applications/new", element: suspense(<NewApplicantApplicationPage />) },
    { path: "applications/:applicationId", element: suspense(<ApplicantApplicationEditorPage />) },
  ]}] },
  {
    path: "/verify/:credentialNumber",
    element: suspense(<VerifyCredentialPage />),
  },
  {
    path: ROUTE_PATHS.login,
    element: <LoginRoute />,
  },
  {
    path: ROUTE_PATHS.mfaChallenge,
    element: suspense(<MfaChallengePage />),
  },
  {
    path: ROUTE_PATHS.platform.root,
    element: <PlatformAdminGuard />,
    children: [
      {
        path: "",
        element: <PlatformAdminLayout />,
        children: [
          {
            index: true,
            element: (
              <Navigate to={ROUTE_PATHS.platform.dashboard} replace />
            ),
          },
          {
            path: "dashboard",
            element: suspense(<PlatformDashboard />),
          },
          {
            path: "institutions",
            element: suspense(<InstitutionsPage />),
          },
          {
            path: "institutions/create",
            element: suspense(<AddInstitutionPage />),
          },
          {
            path: "institutions/:institutionId",
            element: suspense(<InstitutionDetailsPage />),
          },

          // Phase 3: Platform user management
          {
            path: "users",
            element: suspense(<AllUsersPage />),
          },
          {
            path: "users/platform",
            element: suspense(<PlatformUsersPage />),
          },
          {
            path: "users/:userId",
            element: suspense(<UserDetailsPage />),
          },

          // Security (MFA + sessions + audit integrity)
          {
            path: "security",
            element: suspense(<SecurityPage />),
          },
          // Phase 4: Roles and permissions
          {
            path: "access/permissions",
            element: suspense(<PermissionsPage />),
          },
          {
            path: "access",
            element: <Navigate to="/platform/access/roles" replace />,
          },
          {
            path: "configuration",
            element: <Navigate to="/platform/configuration/academic" replace />,
          },
          {
            path: "administration",
            element: <Navigate to="/platform/administration/audit-logs" replace />,
          },
          {
            path: "access/roles",
            element: suspense(<RolesPage />),
          },
          {
            path: "access/assignments",
            element: suspense(<AssignmentsPage />),
          },

          // Phase 5: Platform configuration
          {
            path: "configuration/academic",
            element: suspense(<AcademicConfigurationPage />),
          },
          {
            path: "configuration/system",
            element: suspense(<SystemConfigurationPage />),
          },

          // Phase 6: Audit logs and system administration
          {
            path: "administration/audit-logs",
            element: suspense(<AuditLogsPage />),
          },
          {
            path: "administration/system-health",
            element: suspense(<SystemHealthPage />),
          },
          {
            path: "*",
            element: <NotFoundPage />,
          },
        ],
      },
    ],
  },
  {
    path: ROUTE_PATHS.institution.root,
    element: <InstitutionGuard />,
    children: [
      {
        path: "",
        element: <InstitutionLayout />,
        children: [
          {
            index: true,
            element: (
              <Navigate to={ROUTE_PATHS.institution.dashboard} replace />
            ),
          },
          {
            path: "dashboard",
            element: suspense(<InstitutionDashboard />),
          },
          // Security (MFA + sessions + audit integrity)
          {
            path: "security",
            element: suspense(<SecurityPage />),
          },
          {
            path: "profile",
            element: suspense(<InstitutionProfilePage />),
          },
          {
            path: "academic-units",
            element: suspense(<AcademicUnitsPage />),
          },
          {
            path: "users",
            element: suspense(<InstitutionUsersPage />),
          },
          {
            path: "access",
            element: (
              <Navigate
                to={ROUTE_PATHS.institution.assignments}
                replace
              />
            ),
          },
          {
            path: "examinations",
            element: suspense(<ExaminationsListPage />),
          },
          {
            path: "examinations/marks",
            element: suspense(<ExaminationsMarksPage />),
          },
          {
            path: "examinations/results",
            element: suspense(<ExaminationsResultsPage />),
          },
          {
            path: "examinations/hall-tickets",
            element: suspense(<ExaminationsHallTicketsPage />),
          },
          {
            path: "examinations/revaluation",
            element: suspense(<ExaminationsRevaluationPage />),
          },
          {
            path: "examinations/bank",
            element: suspense(<ExaminationsBankPage />),
          },
          {
            path: "examinations/papers",
            element: suspense(<ExaminationsPapersPage />),
          },
          {
            path: "examinations/paper-templates",
            element: suspense(<PaperTemplatesPage />),
          },
          {
            path: "examinations/credentials",
            element: suspense(<ExaminationsCredentialsPage />),
          },
          {
            path: "examinations/setup",
            element: suspense(<ExamSetupPage />),
          },
          {
            path: "examinations/duty-roster",
            element: suspense(<ExaminationsDutyRosterPage />),
          },
          {
            path: "examinations/my-duties",
            element: suspense(<ExaminationsMyDutiesPage />),
          },
          {
            path: "examinations/infrastructure",
            element: suspense(<ExamInfrastructurePage />),
          },
          {
            path: "examinations/ordinances",
            element: suspense(<ExamOrdinancesPage />),
          },
          {
            path: "examinations/schemes",
            element: suspense(<ExamSchemesPage />),
          },
          {
            path: "examinations/papers/:paperId",
            element: suspense(<ExaminationsPaperPrintPage />),
          },
          {
            path: "examinations/:examId",
            element: suspense(<ExaminationsDetailPage />),
          },
          {
            path: "attendance",
            element: (
              <Navigate
                to={ROUTE_PATHS.institution.attendance.mark}
                replace
              />
            ),
          },
          {
            path: "attendance/timetable",
            element: suspense(<AttendanceTimetablePage />),
          },
          {
            path: "attendance/mark",
            element: suspense(<AttendanceMarkPage />),
          },
          {
            path: "attendance/summary",
            element: suspense(<AttendanceSummaryPage />),
          },
          {
            path: "finance",
            element: (
              <Navigate
                to={ROUTE_PATHS.institution.finance.structures}
                replace
              />
            ),
          },
          {
            path: "finance/structures",
            element: suspense(<FinanceStructuresPage />),
          },
          {
            path: "finance/ledger",
            element: suspense(<FinanceStudentLedgerPage />),
          },
          {
            path: "finance/receipts",
            element: suspense(<FinanceReceiptsPage />),
          },
          {
            path: "students",
            element: suspense(<StudentsPage />),
          },
          {
            path: "admissions",
            element: (
              <Navigate
                to={ROUTE_PATHS.institution.admissions.applications}
                replace
              />
            ),
          },
          {
            path: "admissions/intakes",
            element: suspense(<AdmissionIntakesPage />),
          },
          {
            path: "admissions/applications",
            element: suspense(<AdmissionApplicationsPage />),
          },
          {
            path: "academic",
            element: (
              <Navigate
                to={ROUTE_PATHS.institution.academic.programmes}
                replace
              />
            ),
          },
          {
            path: "academic/programmes",
            element: suspense(<AcademicProgrammesPage />),
          },
          {
            path: "academic/programmes/:programmeId/courses",
            element: suspense(<ProgrammeCoursesPage />),
          },
          {
            path: "academic/subjects",
            element: suspense(<AcademicSubjectsPage />),
          },
          {
            path: "academic/sessions",
            element: suspense(<AcademicSessionsPage />),
          },
          {
            path: "academic/calendar",
            element: suspense(<AcademicCalendarPage />),
          },
          {
            path: "academic/syllabi",
            element: suspense(<SyllabiPage />),
          },
          {
            path: "academic/elective-groups",
            element: suspense(<ElectiveGroupsPage />),
          },
          {
            path: "academic/faculty-workload",
            element: suspense(<FacultyWorkloadPage />),
          },
          {
            path: "academic/lesson-plans",
            element: suspense(<LessonPlansPage />),
          },
          {
            path: "academic/sections",
            element: suspense(<AcademicSectionsPage />),
          },
          {
            path: "academic/course-registrations",
            element: suspense(<CourseRegistrationPage />),
          },
          {
            path: "access/roles",
            element: suspense(<InstitutionRolesPage />),
          },
          {
            path: "access/assignments",
            element: suspense(<InstitutionAssignmentsPage />),
          },
          {
            path: "*",
            element: <NotFoundPage />,
          },
        ],
      },
    ],
  },
  {
    path: "*",
    element: <NotFoundPage />,
  },
]);

export default router;
