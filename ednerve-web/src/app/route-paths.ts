export const ROUTE_PATHS = {
  root: "/",
  login: "/login",
  mfaChallenge: "/mfa-challenge",

  institution: {
    root: "/institution",
    dashboard: "/institution/dashboard",
    security: "/institution/security",
    profile: "/institution/profile",
    academicUnits: "/institution/academic-units",
    users: "/institution/users",
    students: "/institution/students",
    access: "/institution/access",
    roles: "/institution/access/roles",
    assignments: "/institution/access/assignments",
    finance: {
      root: "/institution/finance",
      structures: "/institution/finance/structures",
      ledger: "/institution/finance/ledger",
      receipts: "/institution/finance/receipts",
    },
    attendance: {
      root: "/institution/attendance",
      timetable: "/institution/attendance/timetable",
      mark: "/institution/attendance/mark",
      summary: "/institution/attendance/summary",
    },
    examinations: {
      root: "/institution/examinations",
      list: "/institution/examinations",
      detail: "/institution/examinations/:examId",
      marks: "/institution/examinations/marks",
      results: "/institution/examinations/results",
      hallTickets: "/institution/examinations/hall-tickets",
      revaluation: "/institution/examinations/revaluation",
      bank: "/institution/examinations/bank",
      papers: "/institution/examinations/papers",
      paperTemplates: "/institution/examinations/paper-templates",
      paperPrint: "/institution/examinations/papers/:paperId",
      credentials: "/institution/examinations/credentials",
      setup: "/institution/examinations/setup",
      infrastructure: "/institution/examinations/infrastructure",
      ordinances: "/institution/examinations/ordinances",
      schemes: "/institution/examinations/schemes",
    },
    admissions: {
      root: "/institution/admissions",
      applications: "/institution/admissions/applications",
      intakes: "/institution/admissions/intakes",
    },
    academic: {
      root: "/institution/academic",
      programmes: "/institution/academic/programmes",
      subjects: "/institution/academic/subjects",
      sessions: "/institution/academic/sessions",
      syllabi: "/institution/academic/syllabi",
      electiveGroups: "/institution/academic/elective-groups",
      programmeCourses: (programmeId: number | string) =>
        `/institution/academic/programmes/${programmeId}/courses`,
    },
  },

  platform: {
    root: "/platform",
    dashboard: "/platform/dashboard",
    security: "/platform/security",

    institutions: {
      directory: "/platform/institutions",
      create: "/platform/institutions/create",
      details: (institutionId: number | string) =>
        `/platform/institutions/${institutionId}`,
    },

    users: "/platform/users",
    access: "/platform/access",
    configuration: "/platform/configuration",
    administration: "/platform/administration",
  },
} as const;