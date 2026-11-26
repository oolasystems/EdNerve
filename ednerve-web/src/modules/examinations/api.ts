import apiClient from "../../shared/lib/api-client";

export type ExamStatus = "scheduled" | "completed" | "published";
export type ExamKind = "class_test" | "mid_sem" | "end_sem" | "supplementary";

export interface Exam {
  id: number;
  title: string;
  kind: ExamKind;
  academic_session_id: number | null;
  programme_id: number;
  semester: number;
  section: string;
  instructions?: string | null;
  status: ExamStatus;
  published_at?: string | null;
  programme?: { id: number; code: string; name: string } | null;
  academic_session?: { id: number; code: string; name: string } | null;
  subjects_count?: number;
  published_by?: number | null;
}

export interface ExamComponent {
  id: number;
  name: string;
  max_marks: number;
  position: number;
}

export interface ExamSubjectEntry {
  id: number;
  subject_id: number;
  subject?: {
    id: number;
    code: string;
    name: string;
    credits?: number;
    subject_type?: string;
  } | null;
  exam_date: string;
  exam_session_id?: number | null;
  exam_room_id?: number | null;
  start_time: string | null;
  end_time: string | null;
  venue: string | null;
  eligibility_threshold?: string | number | null;
  instructions?: string | null;
  pass_percent: number;
  total_max: number;
  components: ExamComponent[];
}

export interface ExamInvigilation {
  id: number;
  exam_subject_id: number;
  subject_code: string | null;
  duty: "invigilator" | "chief";
  user: { id: number; name: string; email: string } | null;
}

export interface ExamDetailResponse {
  exam: Exam;
  subjects: ExamSubjectEntry[];
  invigilations: ExamInvigilation[];
  results_published: boolean;
  marks_state: { components: number; students_with_marks: number; total_students: number };
}

export interface ExamMarkComponentState {
  [componentId: string]: { status: "present" | "absent"; marks_obtained: string | null } | null;
}

export interface ExamRosterRow {
  student_profile_id: number;
  application_number: string | null;
  name: string;
  email: string;
  marks: ExamMarkComponentState;
}

export interface MarksGridResponse {
  subject_entry: ExamSubjectEntry;
  students: ExamRosterRow[];
}

export interface ResultSubject {
  subject_id: number;
  code: string;
  name: string;
  obtained: number;
  max: number;
  percent: number;
  grade: string;
  pass: boolean;
  pass_percent: number;
}

export interface ResultRow {
  student: { id: number; application_number: string | null; name: string; email: string };
  subjects: ResultSubject[];
  total: { obtained: number; max: number; percent: number; grade: string; status: "pass" | "fail" };
}

export interface ResultsResponse {
  exam: Exam;
  published: boolean;
  results: ResultRow[];
}

export interface RevaluationRequest {
  id: number;
  exam_id: number;
  student_profile_id: number;
  subject_id: number;
  reason: string;
  fee_amount: string | null;
  status: "requested" | "in_review" | "completed" | "rejected";
  exam?: { id: number; title: string; kind: ExamKind; status: string } | null;
  subject?: { id: number; code: string; name: string } | null;
  student_profile?: { id: number; application_number: string | null; user_id: number; user?: { name: string; email: string } | null } | null;
  requestedBy?: { id: number; name: string } | null;
  handledBy?: { id: number; name: string } | null;
  created_at: string;
}

export interface HallTicketResponse {
  institution: { name: string; code: string };
  exam: Exam;
  student: {
    id: number;
    application_number: string | null;
    user?: { id: number; name: string; email: string } | null;
    programme?: { id: number; code: string; name: string } | null;
    academic_session?: { id: number; code: string; name: string } | null;
  };
  subjects: Array<{
    code: string;
    name: string;
    date: string;
    start_time: string | null;
    end_time: string | null;
    venue: string | null;
  }>;
  hall_ticket_number: string;
}

export interface InvigilationDuty {
  id: number;
  exam_subject_id: number;
  duty: "invigilator" | "chief";
  exam_subject?: {
    exam?: { id: number; title: string; kind: ExamKind; section: string } | null;
    subject?: { id: number; code: string; name: string } | null;
  } | null;
}

export const examinationService = {
  async getExams(params?: { programme_id?: number; semester?: number; status?: string }): Promise<{ exams: Exam[] }> {
    const response = await apiClient.get<{ exams: Exam[] }>("/examinations", { params });
    return response.data;
  },

  async getExam(id: number): Promise<ExamDetailResponse> {
    const response = await apiClient.get<ExamDetailResponse>(`/examinations/${id}`);
    return response.data;
  },

  async createExam(payload: {
    title: string;
    kind: ExamKind;
    academic_session_id?: number | null;
    programme_id: number;
    semester: number;
    section?: string;
    instructions?: string;
  }) {
    const response = await apiClient.post("/examinations", payload);
    return response.data;
  },

  async updateExam(id: number, payload: Partial<{
    title: string;
    kind: ExamKind;
    academic_session_id: number | null;
    programme_id: number;
    semester: number;
    section: string;
    instructions: string;
    status: "scheduled" | "completed";
  }>) {
    const response = await apiClient.patch(`/examinations/${id}`, payload);
    return response.data;
  },

  async deleteExam(id: number) {
    const response = await apiClient.delete(`/examinations/${id}`);
    return response.data;
  },

  async addSubject(
    examId: number,
    payload: {
      subject_id: number;
      exam_date: string;
      start_time?: string;
      end_time?: string;
      venue?: string;
      pass_percent?: number;
      components: Array<{ name: string; max_marks: number; position?: number }>;
    }
  ) {
    const response = await apiClient.post(`/examinations/${examId}/subjects`, payload);
    return response.data;
  },

  async deleteSubject(examId: number, subjectEntryId: number) {
    const response = await apiClient.delete(`/examinations/${examId}/subjects/${subjectEntryId}`);
    return response.data;
  },

  async getMarksGrid(examId: number, subjectEntryId: number): Promise<MarksGridResponse> {
    const response = await apiClient.get<MarksGridResponse>(`/examinations/${examId}/marks`, {
      params: { subject_id: subjectEntryId },
    });
    return response.data;
  },

  async saveMarks(
    examId: number,
    subjectEntryId: number,
    componentId: number,
    records: Array<{ student_profile_id: number; status: "present" | "absent"; marks_obtained: number | null }>
  ) {
    const response = await apiClient.put(`/examinations/${examId}/marks`, {
      subject_id: subjectEntryId,
      component_id: componentId,
      records,
    });
    return response.data;
  },

  async getResults(examId: number): Promise<ResultsResponse> {
    const response = await apiClient.get<ResultsResponse>(`/examinations/${examId}/results`);
    return response.data;
  },

  async publish(examId: number) {
    const response = await apiClient.post(`/examinations/${examId}/publish`);
    return response.data;
  },

  async assignInvigilator(
    examId: number,
    subjectEntryId: number,
    payload: { user_id: number; duty?: "invigilator" | "chief" }
  ) {
    const response = await apiClient.post(
      `/examinations/${examId}/subjects/${subjectEntryId}/invigilations`,
      payload
    );
    return response.data;
  },

  async removeInvigilator(examId: number, invigilationId: number) {
    const response = await apiClient.delete(`/examinations/${examId}/invigilations/${invigilationId}`);
    return response.data;
  },

  async getMyDuties(): Promise<{ duties: InvigilationDuty[] }> {
    const response = await apiClient.get<{ duties: InvigilationDuty[] }>("/examinations/invigilation/mine");
    return response.data;
  },

  async getRevaluations(params?: { exam_id?: number; status?: string }): Promise<{ revaluations: RevaluationRequest[] }> {
    const response = await apiClient.get<{ revaluations: RevaluationRequest[] }>("/examinations/revaluations", {
      params,
    });
    return response.data;
  },

  async createRevaluation(payload: {
    exam_id: number;
    student_profile_id: number;
    subject_id: number;
    reason: string;
    fee_amount?: number | null;
  }) {
    const response = await apiClient.post("/examinations/revaluations", payload);
    return response.data;
  },

  async updateRevaluationStatus(id: number, status: "in_review" | "completed" | "rejected") {
    const response = await apiClient.patch(`/examinations/revaluations/${id}/status`, { status });
    return response.data;
  },

  async getHallTicket(examId: number, studentProfileId: number): Promise<HallTicketResponse> {
    const response = await apiClient.get<HallTicketResponse>(`/examinations/${examId}/hall-ticket`, {
      params: { student_profile_id: studentProfileId },
    });
    return response.data;
  },
};

// ---- Duty Roster (F5) ----
export interface ExamDutyRole {
  id: number;
  code: string;
  name: string;
  description?: string | null;
  min_qualification?: string | null;
  duty_weight: number;
  active: boolean;
  sort_order: number;
}

export interface DutyRosterAssignment {
  id: number;
  user: { id: number; name: string; email: string } | null;
  duty_role: { id: number; code: string; name: string; duty_weight?: number } | null;
  status: "assigned" | "accepted" | "declined" | "replaced";
  assigned_at?: string | null;
  responded_at?: string | null;
  remarks?: string | null;
  assigned_by?: { id: number; name: string } | null;
}

export interface DutyRosterSlot {
  exam_subject_id: number;
  exam_date: string | null;
  start_time: string | null;
  end_time: string | null;
  venue: string | null;
  subject: { id: number; code: string; name: string } | null;
  session: { id: number; code: string; label: string } | null;
  room: { id: number; code: string; name: string; capacity: number } | null;
  assignments: DutyRosterAssignment[];
}

export interface DutyRosterMetadata {
  id: number;
  version: number;
  status: "draft" | "submitted" | "approved" | "rejected" | "published";
  submitted_at?: string | null;
  approved_at?: string | null;
  published_at?: string | null;
}

export interface DutyRosterHistoryVersion {
  id: number;
  version: number;
  status: "draft" | "submitted" | "approved" | "rejected" | "published";
  submitted_by: { id: number; name: string } | null;
  submitted_at: string | null;
  approved_by: { id: number; name: string } | null;
  approved_at: string | null;
  rejected_by: { id: number; name: string } | null;
  rejected_at: string | null;
  rejection_reason: string | null;
  published_by: { id: number; name: string } | null;
  published_at: string | null;
  assignment_count: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface DutyRosterHistoryResponse {
  exam_id: number;
  versions: DutyRosterHistoryVersion[];
}

export interface DutyRosterResponse {
  exam: { id: number; title: string; kind: string; status: string; semester: number; section: string; programme?: { id: number; code: string; name: string } | null; academic_session?: { id: number; code: string; name: string } | null };
  roster: DutyRosterMetadata;
  roles: ExamDutyRole[];
  slots: DutyRosterSlot[];
  availability: Array<{ user: { id: number; name: string; email?: string }; entries: Array<{ id: number; available: boolean; duty_date: string | null; exam_session_id: number | null; session: { id: number; code: string; label: string } | null; reason: string | null }> }>;
  workload: Array<{ user: { id: number; name: string; email: string }; duty_count: number; weighted_load: number; accepted: number; assigned: number }>;
}

export interface MyDutyAssignment {
  id: number;
  exam_id: number;
  exam_title: string;
  roster_id: number;
  roster_version: number;
  exam_subject_id: number;
  exam_date: string | null;
  start_time: string | null;
  end_time: string | null;
  venue: string | null;
  subject: { id: number; code: string; name: string } | null;
  session: { id: number; code: string; label: string } | null;
  room: { id: number; code: string; name: string; capacity?: number } | null;
  duty_role: { id: number; code: string; name: string; duty_weight?: number } | null;
  status: "assigned" | "accepted" | "declined" | "replaced";
  assigned_at: string | null;
  responded_at: string | null;
  remarks: string | null;
  can_respond: boolean;
  can_request_swap: boolean;
}

export interface MyDutiesResponse {
  duties: MyDutyAssignment[];
}

export const myDutiesService = {
  async getMine(): Promise<MyDutiesResponse> {
    const response = await apiClient.get<MyDutiesResponse>("/examinations/duties/mine");
    return response.data;
  },
  async respond(
    examId: number,
    assignmentId: number,
    payload: { response: "accepted" | "declined"; remarks?: string }
  ) {
    const response = await apiClient.post(
      `/examinations/${examId}/duty-assignments/${assignmentId}/respond`,
      payload
    );
    return response.data;
  },
};

export const examDutyService = {
  async getRoles(): Promise<{ roles: ExamDutyRole[] }> {
    const response = await apiClient.get("/examinations/duty-roles");
    return response.data;
  },
  async createRole(payload: Partial<ExamDutyRole>) {
    const response = await apiClient.post("/examinations/duty-roles", payload);
    return response.data;
  },
  async updateRole(id: number, payload: Partial<ExamDutyRole>) {
    const response = await apiClient.patch(`/examinations/duty-roles/${id}`, payload);
    return response.data;
  },
  async getRoster(examId: number): Promise<DutyRosterResponse> {
    const response = await apiClient.get(`/examinations/${examId}/duty-roster`);
    return response.data;
  },
  async getRosterHistory(examId: number): Promise<DutyRosterHistoryResponse> {
    const response = await apiClient.get(`/examinations/${examId}/duty-roster/history`);
    return response.data;
  },
  async createRosterVersion(examId: number): Promise<{
    message: string;
    roster: DutyRosterMetadata;
    copied_assignments: number;
  }> {
    const response = await apiClient.post(`/examinations/${examId}/duty-roster/new-version`);
    return response.data;
  },
  async submitRoster(examId: number) {
    const response = await apiClient.post(`/examinations/${examId}/duty-roster/submit`);
    return response.data;
  },
  async approveRoster(examId: number) {
    const response = await apiClient.post(`/examinations/${examId}/duty-roster/approve`);
    return response.data;
  },
  async rejectRoster(examId: number, rejection_reason: string) {
    const response = await apiClient.post(
      `/examinations/${examId}/duty-roster/reject`,
      { rejection_reason }
    );
    return response.data;
  },
  async publishRoster(examId: number) {
    const response = await apiClient.post(`/examinations/${examId}/duty-roster/publish`);
    return response.data;
  },
  async getPrintRoster(examId: number): Promise<DutyRosterResponse & { generated_at: string }> {
    const response = await apiClient.get(`/examinations/${examId}/duty-roster/print`);
    return response.data;
  },
  async autoAssign(examId: number, payload: { duty_role_id: number; required_per_slot?: number; max_per_staff?: number; staff_user_ids?: number[]; replace_declined?: boolean }) {
    const response = await apiClient.post(`/examinations/${examId}/duty-roster/auto-assign`, payload);
    return response.data;
  },
  async storeAvailability(examId: number, payload: { user_id: number; exam_session_id?: number | null; duty_date?: string | null; available: boolean; reason?: string | null }) {
    const response = await apiClient.post(`/examinations/${examId}/duty-availability`, payload);
    return response.data;
  },
  async listAvailability(examId: number) {
    const response = await apiClient.get(`/examinations/${examId}/duty-availability`);
    return response.data;
  },
  async assign(examId: number, payload: { exam_subject_id: number; user_id: number; duty_role_id: number; status?: string; remarks?: string }) {
    const response = await apiClient.post(`/examinations/${examId}/duty-assignments`, payload);
    return response.data;
  },
  async updateAssignment(examId: number, id: number, payload: { duty_role_id?: number; remarks?: string }) {
    const response = await apiClient.patch(`/examinations/${examId}/duty-assignments/${id}`, payload);
    return response.data;
  },
  async respondToAssignment(
    examId: number,
    id: number,
    payload: { response: "accepted" | "declined"; remarks?: string }
  ) {
    const result = await apiClient.post(
      `/examinations/${examId}/duty-assignments/${id}/respond`,
      payload
    );
    return result.data;
  },
  async removeAssignment(examId: number, id: number) {
    const response = await apiClient.delete(`/examinations/${examId}/duty-assignments/${id}`);
    return response.data;
  },
};
