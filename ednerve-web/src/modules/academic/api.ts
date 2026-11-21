import apiClient from "../../shared/lib/api-client";

import type {
  AcademicSessionsResponse,
  AcademicSession,
  CreateAcademicSessionPayload,
  CreateProgrammeCoursePayload,
  CreateProgrammePayload,
  CreateSubjectPayload,
  ProgrammeCoursesResponse,
  ProgrammeCourse,
  Programme,
  ProgrammesResponse,
  SubjectsResponse,
  Subject,
  UpdateAcademicSessionPayload,
  UpdateProgrammeCoursePayload,
  UpdateProgrammePayload,
  UpdateSubjectPayload,
  AcademicCalendarEventsResponse,
  AcademicCalendarEventResponse,
  CreateAcademicCalendarEventPayload,
  UpdateAcademicCalendarEventPayload,
  FacultyWorkloadResponse,
  CreateFacultyWorkloadPayload,
  UpdateFacultyWorkloadPayload,
  LessonPlanResponse,
  CreateLessonPlanPayload,
  UpdateLessonPlanPayload,
  AcademicSectionsResponse,
  CreateAcademicSectionPayload,
  UpdateAcademicSectionPayload,
  CourseRegistrationsResponse,
  RegisterCoursesPayload,
} from "./types";

export const academicService = {
  // ---------- Academic sessions ----------
  async getSessions(): Promise<AcademicSessionsResponse> {
    const response = await apiClient.get<AcademicSessionsResponse>(
      "/academic-sessions"
    );
    return response.data;
  },

  async createSession(
    payload: CreateAcademicSessionPayload
  ): Promise<{ message: string; session: AcademicSession }> {
    const response = await apiClient.post("/academic-sessions", payload);
    return response.data;
  },

  async updateSession(
    id: number,
    payload: UpdateAcademicSessionPayload
  ): Promise<{ message: string; session: AcademicSession }> {
    const response = await apiClient.put(`/academic-sessions/${id}`, payload);
    return response.data;
  },

  async deactivateSession(id: number): Promise<{ message: string }> {
    const response = await apiClient.delete(`/academic-sessions/${id}`);
    return response.data;
  },

  async activateSession(
    id: number
  ): Promise<{ message: string; session: AcademicSession }> {
    const response = await apiClient.patch(
      `/academic-sessions/${id}/activate`
    );
    return response.data;
  },

  // ---------- Subjects ----------
  async getSubjects(): Promise<SubjectsResponse> {
    const response = await apiClient.get<SubjectsResponse>("/subjects");
    return response.data;
  },

  async createSubject(
    payload: CreateSubjectPayload
  ): Promise<{ message: string; subject: Subject }> {
    const response = await apiClient.post("/subjects", payload);
    return response.data;
  },

  async updateSubject(
    id: number,
    payload: UpdateSubjectPayload
  ): Promise<{ message: string; subject: Subject }> {
    const response = await apiClient.put(`/subjects/${id}`, payload);
    return response.data;
  },

  async deactivateSubject(id: number): Promise<{ message: string }> {
    const response = await apiClient.delete(`/subjects/${id}`);
    return response.data;
  },

  // ---------- Programmes ----------
  async getProgrammes(): Promise<ProgrammesResponse> {
    const response = await apiClient.get<ProgrammesResponse>("/programmes");
    return response.data;
  },

  async createProgramme(
    payload: CreateProgrammePayload
  ): Promise<{ message: string; programme: Programme }> {
    const response = await apiClient.post("/programmes", payload);
    return response.data;
  },

  async updateProgramme(
    id: number,
    payload: UpdateProgrammePayload
  ): Promise<{ message: string; programme: Programme }> {
    const response = await apiClient.put(`/programmes/${id}`, payload);
    return response.data;
  },

  async deactivateProgramme(id: number): Promise<{ message: string }> {
    const response = await apiClient.delete(`/programmes/${id}`);
    return response.data;
  },

  async getProgrammeCourses(
    programmeId: number
  ): Promise<ProgrammeCoursesResponse> {
    const response = await apiClient.get<ProgrammeCoursesResponse>(
      `/programmes/${programmeId}/courses`
    );
    return response.data;
  },

  async mapProgrammeCourse(
    payload: CreateProgrammeCoursePayload
  ): Promise<{ message: string; assignment: ProgrammeCourse }> {
    const response = await apiClient.post("/programme-courses", payload);
    return response.data;
  },

  async updateProgrammeCourse(
    id: number,
    payload: UpdateProgrammeCoursePayload
  ): Promise<{ message: string; assignment: ProgrammeCourse }> {
    const response = await apiClient.put(`/programme-courses/${id}`, payload);
    return response.data;
  },

  async removeProgrammeCourse(id: number): Promise<{ message: string }> {
    const response = await apiClient.delete(`/programme-courses/${id}`);
    return response.data;
  },

  // ---------- Academic calendar ----------
  async getCalendarEvents(params: {
    academic_session_id?: number;
    event_type?: string;
    status?: string;
    from?: string;
    to?: string;
  } = {}): Promise<AcademicCalendarEventsResponse> {
    const search = new URLSearchParams();
    if (params.academic_session_id) search.set("academic_session_id", String(params.academic_session_id));
    if (params.event_type) search.set("event_type", params.event_type);
    if (params.status) search.set("status", params.status);
    if (params.from) search.set("from", params.from);
    if (params.to) search.set("to", params.to);
    const q = search.toString();
    const response = await apiClient.get<AcademicCalendarEventsResponse>(`/academic-calendar${q ? `?${q}` : ""}`);
    return response.data;
  },

  async createCalendarEvent(payload: CreateAcademicCalendarEventPayload): Promise<AcademicCalendarEventResponse> {
    const response = await apiClient.post<AcademicCalendarEventResponse>("/academic-calendar", payload);
    return response.data;
  },

  async updateCalendarEvent(id: number, payload: UpdateAcademicCalendarEventPayload): Promise<AcademicCalendarEventResponse> {
    const response = await apiClient.patch<AcademicCalendarEventResponse>(`/academic-calendar/${id}`, payload);
    return response.data;
  },

  async deleteCalendarEvent(id: number): Promise<{ message: string }> {
    const response = await apiClient.delete<{ message: string }>(`/academic-calendar/${id}`);
    return response.data;
  },

  // ---------- Faculty workload (lite) ----------
  async getFacultyWorkload(params: { user_id?: number; academic_session_id?: number; subject_id?: number; status?: string } = {}): Promise<FacultyWorkloadResponse> {
    const search = new URLSearchParams();
    if (params.user_id) search.set("user_id", String(params.user_id));
    if (params.academic_session_id) search.set("academic_session_id", String(params.academic_session_id));
    if (params.subject_id) search.set("subject_id", String(params.subject_id));
    if (params.status) search.set("status", params.status);
    const q = search.toString();
    const response = await apiClient.get<FacultyWorkloadResponse>(`/faculty-workload${q ? `?${q}` : ""}`);
    return response.data;
  },
  async createFacultyWorkload(payload: CreateFacultyWorkloadPayload): Promise<FacultyWorkloadResponse> {
    const response = await apiClient.post<FacultyWorkloadResponse>("/faculty-workload", payload);
    return response.data;
  },
  async updateFacultyWorkload(id: number, payload: UpdateFacultyWorkloadPayload): Promise<FacultyWorkloadResponse> {
    const response = await apiClient.patch<FacultyWorkloadResponse>(`/faculty-workload/${id}`, payload);
    return response.data;
  },
  async deleteFacultyWorkload(id: number): Promise<{ message: string }> {
    const response = await apiClient.delete<{ message: string }>(`/faculty-workload/${id}`);
    return response.data;
  },

  // ---------- Lesson plans (lite) ----------
  async getLessonPlans(params: { subject_id?: number; academic_session_id?: number; faculty_user_id?: number; status?: string } = {}): Promise<LessonPlanResponse> {
    const search = new URLSearchParams();
    if (params.subject_id) search.set("subject_id", String(params.subject_id));
    if (params.academic_session_id) search.set("academic_session_id", String(params.academic_session_id));
    if (params.faculty_user_id) search.set("faculty_user_id", String(params.faculty_user_id));
    if (params.status) search.set("status", params.status);
    const q = search.toString();
    const response = await apiClient.get<LessonPlanResponse>(`/lesson-plans${q ? `?${q}` : ""}`);
    return response.data;
  },
  async createLessonPlan(payload: CreateLessonPlanPayload): Promise<LessonPlanResponse> {
    const response = await apiClient.post<LessonPlanResponse>("/lesson-plans", payload);
    return response.data;
  },
  async updateLessonPlan(id: number, payload: UpdateLessonPlanPayload): Promise<LessonPlanResponse> {
    const response = await apiClient.patch<LessonPlanResponse>(`/lesson-plans/${id}`, payload);
    return response.data;
  },
  async deleteLessonPlan(id: number): Promise<{ message: string }> {
    const response = await apiClient.delete<{ message: string }>(`/lesson-plans/${id}`);
    return response.data;
  },

  // ---------- Academic sections / batches ----------
  async getAcademicSections(params: { academic_session_id?: number; programme_id?: number; semester?: number; status?: string } = {}): Promise<AcademicSectionsResponse> {
    const search = new URLSearchParams();
    if (params.academic_session_id) search.set("academic_session_id", String(params.academic_session_id));
    if (params.programme_id) search.set("programme_id", String(params.programme_id));
    if (params.semester) search.set("semester", String(params.semester));
    if (params.status) search.set("status", params.status);
    const q = search.toString();
    const response = await apiClient.get<AcademicSectionsResponse>(`/academic-sections${q ? `?${q}` : ""}`);
    return response.data;
  },
  async createAcademicSection(payload: CreateAcademicSectionPayload): Promise<AcademicSectionsResponse> {
    const response = await apiClient.post<AcademicSectionsResponse>("/academic-sections", payload);
    return response.data;
  },
  async updateAcademicSection(id: number, payload: UpdateAcademicSectionPayload): Promise<AcademicSectionsResponse> {
    const response = await apiClient.patch<AcademicSectionsResponse>(`/academic-sections/${id}`, payload);
    return response.data;
  },
  async deleteAcademicSection(id: number): Promise<{ message: string }> {
    const response = await apiClient.delete<{ message: string }>(`/academic-sections/${id}`);
    return response.data;
  },

  // ---------- Course registration ----------
  async getCourseRegistrations(params: { student_profile_id?: number; academic_session_id?: number; semester?: number; status?: string } = {}): Promise<CourseRegistrationsResponse> {
    const search = new URLSearchParams();
    if (params.student_profile_id) search.set("student_profile_id", String(params.student_profile_id));
    if (params.academic_session_id) search.set("academic_session_id", String(params.academic_session_id));
    if (params.semester) search.set("semester", String(params.semester));
    if (params.status) search.set("status", params.status);
    const q = search.toString();
    const response = await apiClient.get<CourseRegistrationsResponse>(`/course-registrations${q ? `?${q}` : ""}`);
    return response.data;
  },
  async registerCourses(payload: RegisterCoursesPayload): Promise<CourseRegistrationsResponse> {
    const response = await apiClient.post<CourseRegistrationsResponse>("/course-registrations", payload);
    return response.data;
  },
  async updateCourseRegistration(id: number, status: "registered" | "dropped" | "completed"): Promise<CourseRegistrationsResponse> {
    const response = await apiClient.patch<CourseRegistrationsResponse>(`/course-registrations/${id}`, { status });
    return response.data;
  },
  async deleteCourseRegistration(id: number): Promise<{ message: string }> {
    const response = await apiClient.delete<{ message: string }>(`/course-registrations/${id}`);
    return response.data;
  },
};

// =====================================================
// Course structure extensions + Syllabus (Phase A)
// =====================================================

export const academicStructureService = {
  // ---------- Subject components ----------
  async getSubjectComponents(subjectId: number) {
    const response = await apiClient.get<{
      components: import("./types").SubjectComponent[];
    }>(`/subjects/${subjectId}/components`);
    return response.data;
  },

  async upsertSubjectComponent(
    subjectId: number,
    payload: import("./types").UpsertSubjectComponentPayload
  ) {
    const response = await apiClient.post(
      `/subjects/${subjectId}/components`,
      payload
    );
    return response.data;
  },

  async removeSubjectComponent(subjectId: number, kind: string) {
    const response = await apiClient.delete(
      `/subjects/${subjectId}/components/${kind}`
    );
    return response.data;
  },

  // ---------- Prerequisites ----------
  async getSubjectPrerequisites(subjectId: number) {
    const response = await apiClient.get<{
      prerequisites: import("./types").SubjectPrerequisite[];
    }>(`/subjects/${subjectId}/prerequisites`);
    return response.data;
  },

  async addPrerequisite(subjectId: number, prerequisiteSubjectId: number) {
    const response = await apiClient.post(
      `/subjects/${subjectId}/prerequisites`,
      { prerequisite_subject_id: prerequisiteSubjectId }
    );
    return response.data;
  },

  async removePrerequisite(subjectId: number, prerequisiteId: number) {
    const response = await apiClient.delete(
      `/subjects/${subjectId}/prerequisites/${prerequisiteId}`
    );
    return response.data;
  },

  // ---------- Elective groups ----------
  async getElectiveGroups(params: { programme_id?: number; semester?: number } = {}) {
    const search = new URLSearchParams();
    if (params.programme_id) search.set("programme_id", String(params.programme_id));
    if (params.semester) search.set("semester", String(params.semester));
    const query = search.toString();
    const response = await apiClient.get<import("./types").ElectiveGroupsResponse>(
      `/elective-groups${query ? `?${query}` : ""}`
    );
    return response.data;
  },

  async createElectiveGroup(payload: import("./types").CreateElectiveGroupPayload) {
    const response = await apiClient.post("/elective-groups", payload);
    return response.data;
  },

  async updateElectiveGroup(
    id: number,
    payload: import("./types").UpdateElectiveGroupPayload
  ) {
    const response = await apiClient.patch(`/elective-groups/${id}`, payload);
    return response.data;
  },

  async deactivateElectiveGroup(id: number) {
    const response = await apiClient.delete(`/elective-groups/${id}`);
    return response.data;
  },

  async attachElectiveGroupSubject(groupId: number, subjectId: number) {
    const response = await apiClient.post(`/elective-groups/${groupId}/subjects`, {
      subject_id: subjectId,
    });
    return response.data;
  },

  async detachElectiveGroupSubject(groupId: number, subjectId: number) {
    const response = await apiClient.delete(
      `/elective-groups/${groupId}/subjects/${subjectId}`
    );
    return response.data;
  },

  // ---------- Syllabi ----------
  async getSyllabi(params: { subject_id?: number; status?: string } = {}) {
    const search = new URLSearchParams();
    if (params.subject_id) search.set("subject_id", String(params.subject_id));
    if (params.status) search.set("status", params.status);
    const query = search.toString();
    const response = await apiClient.get<import("./types").SyllabiResponse>(
      `/syllabi${query ? `?${query}` : ""}`
    );
    return response.data;
  },

  async getSyllabus(id: number) {
    const response = await apiClient.get<import("./types").SyllabusResponse>(
      `/syllabi/${id}`
    );
    return response.data;
  },

  async createSyllabus(payload: import("./types").CreateSyllabusPayload) {
    const response = await apiClient.post("/syllabi", payload);
    return response.data;
  },

  async updateSyllabus(
    id: number,
    payload: import("./types").UpdateSyllabusPayload
  ) {
    const response = await apiClient.put(`/syllabi/${id}`, payload);
    return response.data;
  },

  async submitSyllabus(id: number) {
    const response = await apiClient.post(`/syllabi/${id}/submit`);
    return response.data;
  },

  async approveSyllabus(id: number) {
    const response = await apiClient.post(`/syllabi/${id}/approve`);
    return response.data;
  },

  async requestSyllabusChanges(id: number) {
    const response = await apiClient.post(`/syllabi/${id}/request-changes`);
    return response.data;
  },

  async publishSyllabus(id: number) {
    const response = await apiClient.post(`/syllabi/${id}/publish`);
    return response.data;
  },

  async cloneSyllabus(id: number, version?: string) {
    const response = await apiClient.post(`/syllabi/${id}/clone`, version ? { version } : {});
    return response.data;
  },

  async archiveSyllabus(id: number) {
    const response = await apiClient.delete(`/syllabi/${id}`);
    return response.data;
  },
};
