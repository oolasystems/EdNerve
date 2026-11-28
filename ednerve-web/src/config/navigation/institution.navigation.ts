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
              
              "Review a consolidated snapshot of institutional people, organisational units, profile information, and your access.",
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
              
              "Maintain institutional contact details, registered address, identity information, and official profile settings.",
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
        id: "academic-structure",
        collapsible: true,
        title: "ACADEMIC STRUCTURE",
        items: [
          {
            id: "academic-units",
            label: "Academic Setup",
            icon: Network,
            route: "/institution/academic-units",
            description:
              
              "Organise faculties, schools, and academic units that define the institution’s academic hierarchy and ownership.",
            accountTypes: ["institution"],
            permissions: ["academic_units.view"],
          },
          {
            id: "academic-sessions",
            label: "Academic Sessions",
            icon: CalendarDays,
            route: "/institution/academic/sessions",
            description:
              
              "Manage academic years, sessions, semesters, and their associated institutional timelines and configurations.",
            accountTypes: ["institution"],
            permissions: ["academic.sessions.view"],
          },
          {
            id: "academic-calendar",
            label: "Academic Calendar",
            icon: CalendarRange,
            route: "/institution/academic/calendar",
            description:
              
              "Schedule academic activities, holidays, examinations, events, and important institutional dates throughout each academic year.",
            accountTypes: ["institution"],
            permissions: ["academic.sessions.view"],
          },
        ],
      },
      {
        id: "programmes-curriculum",
        collapsible: true,
        title: "PROGRAMMES & CURRICULUM",
        items: [
          {
            id: "academic-programmes",
            label: "Programmes",
            icon: GraduationCap,
            route: "/institution/academic/programmes",
            description:
              
              "Create and manage degree programmes, their duration, structure, eligibility, and academic ownership.",
            accountTypes: ["institution"],
            permissions: ["academic.programmes.view"],
          },
          {
            id: "academic-subjects",
            label: "Subjects",
            icon: BookOpen,
            route: "/institution/academic/subjects",
            description:
              
              "Maintain subject codes, names, credits, classifications, and reusable academic subject information for programmes.",
            accountTypes: ["institution"],
            permissions: ["academic.subjects.view"],
          },
          {
            id: "academic-syllabi",
            label: "Curriculum & Syllabus",
            icon: FileText,
            route: "/institution/academic/syllabi",
            description:
              
              "Manage programme-wise curriculum structures, semester subject mappings, syllabus versions, units, hours, and learning outcomes.",
            accountTypes: ["institution"],
            permissions: ["academic.syllabi.view"],
          },
          {
            id: "academic-elective-groups",
            label: "Elective Groups",
            icon: ListChecks,
            route: "/institution/academic/elective-groups",
            description:
              
              "Configure elective subject groups, student choice requirements, and programme-specific elective selection rules.",
            accountTypes: ["institution"],
            permissions: ["academic.elective_groups.view"],
          },
        ],
      },
      {
        id: "academic-operations",
        collapsible: true,
        title: "ACADEMIC OPERATIONS",
        items: [
          {
            id: "academic-sections",
            label: "Sections & Batches",
            icon: LayoutGrid,
            route: "/institution/academic/sections",
            description:
              
              "Organise students into academic batches, sections, and groups for institutional teaching and administration.",
            accountTypes: ["institution"],
            permissions: ["academic.sessions.view"],
          },
          {
            id: "academic-subject-registrations",
            label: "Subject Registration",
            icon: ClipboardList,
            route: "/institution/academic/subject-registrations",
            description:
              
              "Manage individual student subject registrations, including compulsory selections, electives, additions, drops, and adjustments.",
            accountTypes: ["institution"],
            permissions: ["academic.programmes.view"],
          },
          {
            id: "academic-faculty-workload",
            label: "Teaching & Workload",
            icon: Briefcase,
            route: "/institution/academic/faculty-workload",
            description:
              
              "Assign faculty members to subjects, manage teaching responsibilities, and monitor academic workload allocations.",
            accountTypes: ["institution"],
            permissions: ["academic.programmes.view"],
          },
          {
            id: "academic-lesson-plans",
            label: "Lesson Plans",
            icon: BookMarked,
            route: "/institution/academic/lesson-plans",
            description:
              
              "Prepare teaching plans, define syllabus coverage, and track planned versus completed classroom learning activities.",
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
              
              "Configure institutional fee heads, programme fee schedules, applicable charges, and student payment requirements.",
            accountTypes: ["institution"],
            permissions: ["finance.view"],
          },
          {
            id: "finance-ledger",
            label: "Student Ledgers",
            icon: Wallet,
            route: "/institution/finance/ledger",
            description:
              
              "Review individual student financial ledgers, allocate received payments, and monitor outstanding balances and dues.",
            accountTypes: ["institution"],
            permissions: ["finance.view"],
          },
          {
            id: "finance-receipts",
            label: "Receipts",
            icon: Receipt,
            route: "/institution/finance/receipts",
            description:
              
              "Review recorded student payments, receipt references, transaction details, and issued payment acknowledgements.",
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
              
              "Manage institution user accounts, monitor account lifecycle, and review assigned roles and access coverage.",
            accountTypes: ["institution"],
            permissions: ["institution_users.view"],
          },
          {
            id: "students",
            label: "Students",
            icon: GraduationCap,
            route: "/institution/students",
            description:
              
              "Access enrolled student profiles, review academic information, and monitor current institutional enrolment status.",
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
              
              "Review institution roles available for assignment; platform administrators maintain the underlying role definitions.",
            accountTypes: ["institution"],
            permissions: ["roles.view"],
          },
          {
            id: "access-assignments",
            label: "Role Assignments",
            icon: ClipboardList,
            route: "/institution/access/assignments",
            description:
              
              "Assign, review, and revoke institution-level roles for authorised staff and other institutional users.",
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
              
              "Configure programme admission windows for academic sessions, including application fees and intake availability.",
            accountTypes: ["institution"],
            permissions: ["admissions.intakes.view"],
          },
          {
            id: "admissions-applications",
            label: "Applications",
            icon: ClipboardCheck,
            route: "/institution/admissions/applications",
            description:
              
              "Review applications, shortlist candidates, record admission decisions, and initiate successful applicant enrolment workflows.",
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
              
              "View weekly class schedules organised by programme, semester, section, subject, and assigned teaching periods.",
            accountTypes: ["institution"],
            permissions: ["timetable.view"],
          },
          {
            id: "attendance-mark",
            label: "Mark Attendance",
            icon: ClipboardCheck,
            route: "/institution/attendance/mark",
            description:
              
              "Open class attendance registers and record each enrolled student as present, late, or absent.",
            accountTypes: ["institution"],
            permissions: ["attendance.mark"],
          },
          {
            id: "attendance-summary",
            label: "Summaries",
            icon: ChartBar,
            route: "/institution/attendance/summary",
            description:
              
              "Review student attendance percentages by subject, identify attendance patterns, and monitor participation across classes.",
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
    // Keep Exams as the landing page and the first item in the first group.
    defaultItemId: "examinations-list",

    sections: [
      {
        id: "exam-planning-setup",
        collapsible: true,
        title: "EXAM PLANNING & SETUP",
        items: [
          {
            id: "examinations-list",
            label: "Exams",
            icon: ClipboardList,
            route: "/institution/examinations",
            description:
              
              "Create and manage examinations, schedule subjects, and configure the assessment components used for evaluation.",
            accountTypes: ["institution"],
            permissions: ["examinations.view"],
          },
          {
            id: "examinations-schemes",
            label: "Assessment Schemes",
            icon: Layers3,
            route: "/institution/examinations/schemes",
            description:
              
              "Define subject or programme assessment components, internal and external weightages, and examination evaluation patterns.",
            accountTypes: ["institution"],
            permissions: ["examinations.view"],
          },
          {
            id: "examinations-ordinances",
            label: "Ordinances",
            icon: ScrollText,
            route: "/institution/examinations/ordinances",
            description:
              
              "Maintain programme grading bands, passing criteria, attendance thresholds, and applicable examination regulations.",
            accountTypes: ["institution"],
            permissions: ["examinations.view"],
          },
          {
            id: "examinations-infrastructure",
            label: "Exam Infrastructure",
            icon: Building2,
            route: "/institution/examinations/infrastructure",
            description:
              
              "Configure examination sessions, time slots, centres, rooms, and infrastructure resources required for assessments.",
            accountTypes: ["institution"],
            permissions: ["examinations.view"],
          },
          {
            id: "examinations-setup",
            label: "Exam Setup",
            icon: CalendarClock,
            route: "/institution/examinations/setup",
            description:
              
              "Allocate examination rooms and sessions, verify student eligibility, and identify scheduling or infrastructure conflicts.",
            accountTypes: ["institution"],
            permissions: ["examinations.view"],
          },
        ],
      },
      {
        id: "question-paper-management",
        collapsible: true,
        title: "QUESTION PAPER MANAGEMENT",
        items: [
          {
            id: "examinations-question-bank",
            label: "Question Bank",
            icon: BookOpenCheck,
            route: "/institution/examinations/bank",
            description:
              
              "Maintain subject-wise examination questions, organise them by topic, and manage model answers securely.",
            accountTypes: ["institution"],
            permissions: ["question_bank.view"],
          },
          {
            id: "examinations-paper-templates",
            label: "Paper Templates",
            icon: ListChecks,
            route: "/institution/examinations/paper-templates",
            description:
              
              "Design reusable examination paper templates with configurable sections, question formats, marks, and instructions.",
            accountTypes: ["institution"],
            permissions: ["question_bank.view"],
          },
          {
            id: "examinations-question-papers",
            label: "Question Papers",
            icon: Layers3,
            route: "/institution/examinations/papers",
            description:
              
              "Prepare, review, approve, and ratify examination question papers through controlled approval workflows.",
            accountTypes: ["institution"],
            permissions: ["question_bank.view"],
          },
        ],
      },
      {
        id: "examination-operations",
        collapsible: true,
        title: "EXAMINATION OPERATIONS",
        items: [
          {
            id: "examinations-duty-roster",
            label: "Duty Roster",
            icon: Users,
            route: "/institution/examinations/duty-roster",
            description:
              
              "Assign examination duties, manage staff availability and workload, and generate institution-wide invigilation rosters.",
            accountTypes: ["institution"],
            permissions: ["examinations.view"],
          },
          {
            id: "examinations-my-duties",
            label: "My Duties",
            icon: CalendarDays,
            route: "/institution/examinations/my-duties",
            description:
              
              "View published examination duty assignments, review instructions, and respond to pending duty confirmations.",
            accountTypes: ["institution"],
            permissions: ["examinations.view"],
          },
          {
            id: "examinations-hall-tickets",
            label: "Hall Tickets",
            icon: Printer,
            route: "/institution/examinations/hall-tickets",
            description:
              
              "Generate student hall tickets for scheduled examinations after verifying applicable attendance and eligibility requirements.",
            accountTypes: ["institution"],
            permissions: ["examinations.view"],
          },
        ],
      },
      {
        id: "evaluation-results",
        collapsible: true,
        title: "EVALUATION & RESULTS",
        items: [
          {
            id: "examinations-marks",
            label: "Marks Entry",
            icon: ClipboardCheck,
            route: "/institution/examinations/marks",
            description:
              
              "Record and validate component-wise examination marks for students enrolled in the relevant assessments.",
            accountTypes: ["institution"],
            permissions: ["examinations.record_marks"],
          },
          {
            id: "examinations-results",
            label: "Results",
            icon: ChartBar,
            route: "/institution/examinations/results",
            description:
              
              "Review subject-wise examination outcomes, verify calculated results, and publish approved student result records.",
            accountTypes: ["institution"],
            permissions: ["examinations.view"],
          },
          {
            id: "examinations-revaluation",
            label: "Revaluation",
            icon: FileSearch,
            route: "/institution/examinations/revaluation",
            description:
              
              "Process student revaluation requests, coordinate reassessment, and record revised marks and final decisions.",
            accountTypes: ["institution"],
            permissions: ["examinations.view"],
          },
          {
            id: "examinations-credentials",
            label: "Credentials",
            icon: Award,
            route: "/institution/examinations/credentials",
            description:
              
              "Generate, issue, and verify digitally signed academic credentials and examination-related student certificates.",
            accountTypes: ["institution"],
            permissions: ["examinations.view"],
          },
        ],
      },
    ],
  },

];
