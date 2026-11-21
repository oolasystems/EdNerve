import apiClient from "../../shared/lib/api-client";

/* ========================
 | Types
 ======================== */

export interface ExamSession {
  id: number;
  code: string;
  label: string;
  start_time: string;
  end_time: string;
  sort_order: number;
  active: boolean;
}

export interface ExamRoom {
  id: number;
  centre_id: number;
  code: string;
  name: string;
  room_type: "theory" | "lab" | "viva" | "server_room";
  capacity: number;
  active: boolean;
}

export interface ExamCentre {
  id: number;
  code: string;
  name: string;
  address: string | null;
  capacity: number;
  active: boolean;
  rooms?: ExamRoom[];
}

export interface ExamKind {
  id: number;
  code: string;
  label: string;
  max_attempts: number | null;
  active: boolean;
}

export interface GradingBand {
  min: number;
  grade: string;
  points: number;
}

export interface PassCriteria {
  overall_min_pct: number;
  external_min_pct: number;
  condonation_pct: number;
  max_backlogs: number | null;
}

export interface Ordinance {
  id: number;
  code: string;
  name: string;
  description: string | null;
  grading_bands: GradingBand[];
  pass_criteria: PassCriteria;
  attendance_threshold: number | string;
  is_default: boolean;
  status: string;
}

export interface SchemeComponent {
  id?: number;
  name: string;
  kind: "continuous" | "external" | "practical" | "viva" | "project";
  max_marks: number | string;
  weightage: number | string | null;
  is_external: boolean;
  min_percent: number | string | null;
  position?: number;
}

export interface AssessmentScheme {
  id: number;
  code: string;
  name: string;
  scope: "subject" | "programme";
  subject_id: number | null;
  programme_id: number | null;
  description: string | null;
  status: string;
  components: SchemeComponent[];
}

export interface Conflict {
  type: string;
  message: string;
}

export interface EligibilityRow {
  id: number;
  exam_subject_id: number;
  student_profile_id: number;
  application_number: string | null;
  student_name: string | null;
  status: "eligible" | "short" | "overridden";
  attendance_pct: string | null;
  reason: string | null;
}

/* ========================
 | API calls
 ======================== */

export const examSetupService = {
  // Sessions
  listSessions: () =>
    apiClient.get<{ data: ExamSession[] }>("/examinations/exam-sessions").then((r) => r.data.data),
  createSession: (body: Omit<ExamSession, "id" | "active">) =>
    apiClient.post("/examinations/exam-sessions", body).then((r) => r.data),
  updateSession: (id: number, body: Partial<ExamSession>) =>
    apiClient.patch(`/examinations/exam-sessions/${id}`, body).then((r) => r.data),

  // Centres & rooms
  listCentres: () =>
    apiClient.get<{ data: ExamCentre[] }>("/examinations/centres").then((r) => r.data.data),
  createCentre: (body: Partial<ExamCentre>) =>
    apiClient.post("/examinations/centres", body).then((r) => r.data),
  updateCentre: (id: number, body: Partial<ExamCentre>) =>
    apiClient.patch(`/examinations/centres/${id}`, body).then((r) => r.data),
  listRooms: (centreId: number) =>
    apiClient.get<{ data: ExamRoom[] }>(`/examinations/centres/${centreId}/rooms`).then((r) => r.data.data),
  createRoom: (body: Partial<ExamRoom>) =>
    apiClient.post("/examinations/rooms", body).then((r) => r.data),
  updateRoom: (id: number, body: Partial<ExamRoom>) =>
    apiClient.patch(`/examinations/rooms/${id}`, body).then((r) => r.data),

  // Exam kinds
  listKinds: () =>
    apiClient.get<{ data: ExamKind[] }>("/examinations/exam-kinds").then((r) => r.data.data),
  createKind: (body: Partial<ExamKind>) =>
    apiClient.post("/examinations/exam-kinds", body).then((r) => r.data),
  updateKind: (id: number, body: Partial<ExamKind>) =>
    apiClient.patch(`/examinations/exam-kinds/${id}`, body).then((r) => r.data),

  // Ordinances
  listOrdinances: () =>
    apiClient.get<{ data: Ordinance[] }>("/examinations/ordinances").then((r) => r.data.data),
  createOrdinance: (body: Partial<Ordinance>) =>
    apiClient.post("/examinations/ordinances", body).then((r) => r.data),
  updateOrdinance: (id: number, body: Partial<Ordinance>) =>
    apiClient.patch(`/examinations/ordinances/${id}`, body).then((r) => r.data),
  setDefaultOrdinance: (id: number) =>
    apiClient.post(`/examinations/ordinances/${id}/set-default`, {}).then((r) => r.data),
  archiveOrdinance: (id: number) =>
    apiClient.delete(`/examinations/ordinances/${id}`).then((r) => r.data),

  // Assessment schemes
  listSchemes: () =>
    apiClient.get<{ data: AssessmentScheme[] }>("/examinations/schemes").then((r) => r.data.data),
  createScheme: (body: Partial<AssessmentScheme>) =>
    apiClient.post("/examinations/schemes", body).then((r) => r.data),
  updateScheme: (id: number, body: Partial<AssessmentScheme>) =>
    apiClient.patch(`/examinations/schemes/${id}`, body).then((r) => r.data),
  deleteScheme: (id: number) =>
    apiClient.delete(`/examinations/schemes/${id}`).then((r) => r.data),

  // Schedule / conflicts / eligibility
  assignSchedule: (examId: number, entryId: number, body: {
    exam_session_id?: number | null;
    exam_room_id?: number | null;
    eligibility_threshold?: number | null;
    instructions?: string | null;
  }) =>
    apiClient.patch(`/examinations/${examId}/subjects/${entryId}/schedule`, body).then((r) => r.data),
  checkConflicts: (examId: number) =>
    apiClient.post<{ exam_id: number; conflicts: Conflict[]; count: number; checked: number }>(
      `/examinations/${examId}/conflicts/check`,
      {}
    ).then((r) => r.data),
  computeEligibility: (examId: number) =>
    apiClient.post<{ message: string; summary: Record<string, number> }>(
      `/examinations/${examId}/eligibility/compute`,
      {}
    ).then((r) => r.data),
  eligibilityReport: (examId: number, status?: string) =>
    apiClient.get<{ subjects: { id: number; code: string; name: string; threshold: string | null }[]; data: EligibilityRow[]; summary: Record<string, number> }>(
      `/examinations/${examId}/eligibility`,
      { params: status ? { status } : undefined }
    ).then((r) => r.data),
  overrideEligibility: (body: { exam_subject_id: number; student_profile_id: number; reason: string }) =>
    apiClient.post("/examinations/eligibility/override", body).then((r) => r.data),
};
