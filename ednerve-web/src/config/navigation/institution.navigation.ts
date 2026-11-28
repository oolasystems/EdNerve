/**
 * Institution Administration navigation.
 *
 * Institution-admin workspace rail areas. Items carry accountTypes
 * ["institution"]; permission codes are evaluated against the
 * institution-admin grant set mirrored client-side in lib/rbac
 * (backend enforces authoritatively).
 */

import {
  LayoutDashboard,
  Building2,
  Network,
  Users,
  KeyRound,
  ClipboardList,
  BookOpen,
  CalendarDays,
  CalendarRange,
  GraduationCap,
  ClipboardCheck,
  IndianRupee,
  Briefcase,
  BookMarked,
  Layers,
  Wallet,
  Receipt,
  LayoutGrid,
  ChartBar,
  FileSearch,
  Printer,
  BookOpenCheck,
  Layers3,
  Award,
  FileText,
  ListChecks,
  CalendarClock,
  ScrollText,
} from "lucide-react";

import type { NavigationAreaConfig } from "./navigation.types";

export const institutionNavigation: NavigationAreaConfig[] = [
  // 1. OVERVIEW
  {
    id: "overview",
    label: "Overview",
    icon: LayoutDashboard,
    route: "/institution/dashboard",
    accountTypes: ["institution"],
    defaultItemId: "institution-overview",

    sections: [
      {
        id: "institution",
        title: "INSTITUTION",

        items: [
          {
            id: "institution-overview",
            label: "Overview",
            icon: LayoutDashboard,
            route: "/institution/dashboard",
            description:
              "Institution snapshot: people, units, profile and your access.",
            accountTypes: ["institution"],
          },
        ],
      },
    ],
  },

  // 2. INSTITUTION PROFILE
  {
    id: "institution",
    label: "Institution",
    icon: Building2,
    route: "/institution/profile",
    accountTypes: ["institution"],
    permissions: ["institution.settings.view"],
    defaultItemId: "institution-profile",

    sections: [
      {
        id: "profile",
        title: "PROFILE",

        items: [
          {
            id: "institution-profile",
            label: "Institution Profile",
            icon: Building2,
            route: "/institution/profile",
            description:
              "Institution contact details, address and profile information.",
            accountTypes: ["institution"],
            permissions: ["institution.settings.view"],
          },
        ],
      },
    ],
  },

  // 3. ACADEMIC
  {
    id: "academic",
    label: "Academic",
    icon: BookOpen,
    route: "/institution/academic/programmes",
    accountTypes: ["institution"],
    permissions: ["academic.programmes.view"],
    defaultItemId: "academic-programmes",

    sections: [
      {
        id: "academic",
        title: "ACADEMIC",

        items: [
          {
            id: "academic-units",
            label: "Academic Setup",
            icon: Network,
            route: "/institution/academic-units",
            description:
              "Faculties, schools and academic units of the institution.",
            accountTypes: ["institution"],
            permissions: ["academic_units.view"],
          },
          {
            id: "academic-sessions",
            label: "Academic Sessions",
            icon: CalendarDays,
            route: "/institution/academic/sessions",
            description:
              "Academic sessions; exactly one active per institution.",
            accountTypes: ["institution"],
            permissions: ["academic.sessions.view"],
          },
          {
            id: "academic-calendar",
            label: "Academic Calendar",
            icon: CalendarRange,
            route: "/institution/academic/calendar",
            description:
              "Holidays, exam windows, registration and fee deadlines for the academic year.",
            accountTypes: ["institution"],
            permissions: ["academic.sessions.view"],
          },
          {
            id: "academic-sections",
            label: "Sections / Batches",
            icon: LayoutGrid,
            route: "/institution/academic/sections",
            description:
              "First-class academic sections/batches (programme, session, semester, capacity).",
            accountTypes: ["institution"],
            permissions: ["academic.sessions.view"],
          },
          {
            id: "academic-subjects",
            label: "Subjects",
            icon: BookOpen,
            route: "/institution/academic/subjects",
            description:
              "Institution subject master with credits and theory/practical type (components + prerequisites).",
            accountTypes: ["institution"],
            permissions: ["academic.subjects.view"],
          },
          {
            id: "academic-programmes",
            label: "Programmes",
            icon: GraduationCap,
            route: "/institution/academic/programmes",
            description:
              "Programmes and their semester-wise course (subject) maps.",
            accountTypes: ["institution"],
            permissions: ["academic.programmes.view"],
          },
          {
            id: "academic-elective-groups",
            label: "Elective Groups",
            icon: ListChecks,
            route: "/institution/academic/elective-groups",
            description:
              "Programme elective baskets: capacity, required choices and offered subjects.",
            accountTypes: ["institution"],
            permissions: ["academic.elective_groups.view"],
          },
          {
            id: "academic-course-registrations",
            label: "Course Registration",
            icon: ClipboardList,
            route: "/institution/academic/course-registrations",
            description:
              "Per-session student course enrolment with core/elective selection and elective enforcement.",
            accountTypes: ["institution"],
            permissions: ["academic.programmes.view"],
          },
          {
            id: "academic-syllabi",
            label: "Syllabi",
            icon: FileText,
            route: "/institution/academic/syllabi",
            description:
              "Versioned syllabi with units, hours and learning outcomes; review and publish workflow.",
            accountTypes: ["institution"],
            permissions: ["academic.syllabi.view"],
          },
          {
            id: "academic-faculty-workload",
            label: "Faculty Workload",
            icon: Briefcase,
            route: "/institution/academic/faculty-workload",
            description:
              "Teaching/load allocations per faculty member (periods and hours).",
            accountTypes: ["institution"],
            permissions: ["academic.programmes.view"],
          },
          {
            id: "academic-lesson-plans",
            label: "Lesson Plans",
            icon: BookMarked,
            route: "/institution/academic/lesson-plans",
            description:
              "Unit-wise teaching plans and progress (planned vs taught).",
            accountTypes: ["institution"],
            permissions: ["academic.programmes.view"],
          },
        ],
      },
    ],
  },

  // 5. FINANCE
  {
    id: "finance",
    label: "Finance",
    icon: IndianRupee,
    route: "/institution/finance/structures",
    accountTypes: ["institution"],
    permissions: ["finance.view"],
    defaultItemId: "finance-structures",

    sections: [
      {
        id: "finance",
        title: "FINANCE",

        items: [
          {
            id: "finance-structures",
            label: "Fee Structures",
            icon: Layers,
            route: "/institution/finance/structures",
            description:
              "Configure fee heads and per-programme fee schedules.",
            accountTypes: ["institution"],
            permissions: ["finance.view"],
          },
          {
            id: "finance-ledger",
            label: "Student Ledgers",
            icon: Wallet,
            route: "/institution/finance/ledger",
            description:
              "View student ledgers, allocate payments, record receipts.",
            accountTypes: ["institution"],
            permissions: ["finance.view"],
          },
          {
            id: "finance-receipts",
            label: "Receipts",
            icon: Receipt,
            route: "/institution/finance/receipts",
            description:
              "List of received payments and their receipt numbers.",
            accountTypes: ["institution"],
            permissions: ["finance.view"],
          },
        ],
      },
    ],
  },

  // 6. PEOPLE
  {
    id: "users",
    label: "People",
    icon: Users,
    route: "/institution/users",
    accountTypes: ["institution"],
    permissions: ["institution_users.view"],
    defaultItemId: "institution-users",

    sections: [
      {
        id: "users",
        title: "PEOPLE",

        items: [
          {
            id: "institution-users",
            label: "Institution Users",
            icon: Users,
            route: "/institution/users",
            description:
              "User accounts, lifecycle and role coverage for this institution.",
            accountTypes: ["institution"],
            permissions: ["institution_users.view"],
          },
          {
            id: "students",
            label: "Students",
            icon: GraduationCap,
            route: "/institution/students",
            description:
              "Enrolled students, academic profile, enrollment state.",
            accountTypes: ["institution"],
            permissions: ["students.view"],
          },
        ],
      },
    ],
  },

  // 7. ACCESS
  {
    id: "access",
    label: "Access",
    icon: KeyRound,
    route: "/institution/access/roles",
    accountTypes: ["institution"],
    permissions: ["role_assignments.view"],
    defaultItemId: "access-roles",

    sections: [
      {
        id: "access",
        title: "ACCESS",

        items: [
          {
            id: "access-roles",
            label: "Role Catalogue",
            icon: KeyRound,
            route: "/institution/access/roles",
            description:
              "Roles available for assignment. Role definitions are managed by the platform.",
            accountTypes: ["institution"],
            permissions: ["roles.view"],
          },
          {
            id: "access-assignments",
            label: "Role Assignments",
            icon: ClipboardList,
            route: "/institution/access/assignments",
            description:
              "Assign and revoke institution roles for institution users.",
            accountTypes: ["institution"],
            permissions: ["role_assignments.view"],
          },
        ],
      },
    ],
  },
  // 8. ADMISSIONS
  {
    id: "admissions",
    label: "Admissions",
    icon: ClipboardCheck,
    route: "/institution/admissions/applications",
    accountTypes: ["institution"],
    permissions: ["admissions.applications.view"],
    defaultItemId: "admissions-applications",

    sections: [
      {
        id: "admissions",
        title: "ADMISSIONS",

        items: [
          {
            id: "admissions-intakes",
            label: "Intakes",
            icon: CalendarDays,
            route: "/institution/admissions/intakes",
            description:
              "Admission windows (programme × academic session), fees and open/closed status.",
            accountTypes: ["institution"],
            permissions: ["admissions.intakes.view"],
          },
          {
            id: "admissions-applications",
            label: "Applications",
            icon: ClipboardCheck,
            route: "/institution/admissions/applications",
            description:
              "Review, shortlist, admit and enroll applicants; decisions are audit-logged.",
            accountTypes: ["institution"],
            permissions: ["admissions.applications.view"],
          },
        ],
      },
    ],
  },

  // 9. ATTENDANCE
  {
    id: "attendance",
    label: "Attendance",
    icon: CalendarDays,
    route: "/institution/attendance/timetable",
    accountTypes: ["institution"],
    permissions: ["attendance.view"],
    defaultItemId: "attendance-mark",

    sections: [
      {
        id: "attendance",
        title: "ATTENDANCE",

        items: [
          {
            id: "attendance-timetable",
            label: "Timetable",
            icon: LayoutGrid,
            route: "/institution/attendance/timetable",
            description:
              "Weekly class grid per programme, semester and section.",
            accountTypes: ["institution"],
            permissions: ["timetable.view"],
          },
          {
            id: "attendance-mark",
            label: "Mark Attendance",
            icon: ClipboardCheck,
            route: "/institution/attendance/mark",
            description:
              "Open a class register and mark present / late / absent.",
            accountTypes: ["institution"],
            permissions: ["attendance.mark"],
          },
          {
            id: "attendance-summary",
            label: "Summaries",
            icon: ChartBar,
            route: "/institution/attendance/summary",
            description:
              "Per-student attendance percentage by subject.",
            accountTypes: ["institution"],
            permissions: ["attendance.view"],
          },
        ],
      },
    ],
  },

  // 10. EXAMINATION
  {
    id: "examinations",
    label: "Examination",
    icon: GraduationCap,
    route: "/institution/examinations",
    accountTypes: ["institution"],
    permissions: ["examinations.view"],
    defaultItemId: "examinations-list",

    sections: [
      {
        id: "examinations",
        title: "EXAMINATION",

        items: [
          {
            id: "examinations-schemes",
            label: "Assessment Schemes",
            icon: Layers3,
            route: "/institution/examinations/schemes",
            description:
              "Per-subject or per-programme internal/external splits (sessional weights).",
            accountTypes: ["institution"],
            permissions: ["examinations.view"],
          },
          {
            id: "examinations-ordinances",
            label: "Ordinances",
            icon: ScrollText,
            route: "/institution/examinations/ordinances",
            description:
              "Grading bands, pass criteria and attendance thresholds per programme.",
            accountTypes: ["institution"],
            permissions: ["examinations.view"],
          },
          {
            id: "examinations-infrastructure",
            label: "Exam Infrastructure",
            icon: Building2,
            route: "/institution/examinations/infrastructure",
            description:
              "Day slots (FN/AN/EV), exam centres, rooms and exam kinds.",
            accountTypes: ["institution"],
            permissions: ["examinations.view"],
          },
          {
            id: "examinations-list",
            label: "Exams",
            icon: ClipboardList,
            route: "/institution/examinations",
            description:
              "Schedule examinations, subjects and marking components.",
            accountTypes: ["institution"],
            permissions: ["examinations.view"],
          },
          {
            id: "examinations-setup",
            label: "Exam Setup",
            icon: CalendarClock,
            route: "/institution/examinations/setup",
            description:
              "Assign rooms, sessions and attendance-eligibility; check scheduling conflicts.",
            accountTypes: ["institution"],
            permissions: ["examinations.view"],
          },
          {
            id: "examinations-duty-roster",
            label: "Duty Roster",
            icon: Users,
            route: "/institution/examinations/duty-roster",
            description:
              "Assign exam duties by role, manage staff availability and workload, auto-generate and print rosters.",
            accountTypes: ["institution"],
            permissions: ["examinations.view"],
          },
          {
            id: "examinations-my-duties",
            label: "My Duties",
            icon: CalendarDays,
            route: "/institution/examinations/my-duties",
            description:
              "View your current published examination duty assignments and accept or decline pending duties.",
            accountTypes: ["institution"],
            permissions: ["examinations.view"],
          },
          {
            id: "examinations-question-bank",
            label: "Question Bank",
            icon: BookOpenCheck,
            route: "/institution/examinations/bank",
            description:
              "Maintain course-wise questions with model answers (encrypted at rest).",
            accountTypes: ["institution"],
            permissions: ["question_bank.view"],
          },
          {
            id: "examinations-paper-templates",
            label: "Paper Templates",
            icon: ListChecks,
            route: "/institution/examinations/paper-templates",
            description:
              "University-configurable paper blueprints: sections, items, marks and instructions.",
            accountTypes: ["institution"],
            permissions: ["question_bank.view"],
          },
          {
            id: "examinations-question-papers",
            label: "Question Papers",
            icon: Layers3,
            route: "/institution/examinations/papers",
            description:
              "Prepare, check, approve and ratify examination papers (separation of duties).",
            accountTypes: ["institution"],
            permissions: ["question_bank.view"],
          },
          {
            id: "examinations-hall-tickets",
            label: "Hall Tickets",
            icon: Printer,
            route: "/institution/examinations/hall-tickets",
            description:
              "Print hall tickets for scheduled examinations (blocked until attendance-eligibility is resolved).",
            accountTypes: ["institution"],
            permissions: ["examinations.view"],
          },
          {
            id: "examinations-marks",
            label: "Marks Entry",
            icon: ClipboardCheck,
            route: "/institution/examinations/marks",
            description:
              "Enter component-wise marks for enrolled students.",
            accountTypes: ["institution"],
            permissions: ["examinations.record_marks"],
          },
          {
            id: "examinations-results",
            label: "Results",
            icon: ChartBar,
            route: "/institution/examinations/results",
            description:
              "Preview per-subject results and publish the snapshot.",
            accountTypes: ["institution"],
            permissions: ["examinations.view"],
          },
          {
            id: "examinations-revaluation",
            label: "Revaluation",
            icon: FileSearch,
            route: "/institution/examinations/revaluation",
            description:
              "Log and action revaluation requests from students.",
            accountTypes: ["institution"],
            permissions: ["examinations.view"],
          },
          {
            id: "examinations-credentials",
            label: "Credentials",
            icon: Award,
            route: "/institution/examinations/credentials",
            description:
              "Issue and verify digitally signed academic credentials.",
            accountTypes: ["institution"],
            permissions: ["examinations.view"],
          },
        ],
      },
    ],
  },

];
