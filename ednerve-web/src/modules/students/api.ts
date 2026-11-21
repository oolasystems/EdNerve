import apiClient from "../../shared/lib/api-client";

import type { PaginatedResponse } from "../../shared/types/api";

export type EnrollmentStatus =
  | "current"
  | "leave"
  | "withheld"
  | "graduated"
  | "terminated";

export interface StudentProgrammeRef {
  id: number;
  code: string;
  name: string;
  level?: string;
  duration_semesters?: number;
}

export interface StudentSessionRef {
  id: number;
  code: string;
  name: string;
}

export interface StudentUserRef {
  id: number;
  name: string;
  email: string;
  phone?: string | null;
  status: string;
  last_login_at?: string | null;
}

export interface StudentProfile {
  id: number;
  institution_id: number;
  application_number?: string | null;
  programme_id?: number | null;
  academic_session_id?: number | null;
  enrollment_status: EnrollmentStatus;
  admitted_on?: string | null;
  left_on?: string | null;
  father_name?: string | null;
  mother_name?: string | null;
  dob?: string | null;
  gender?: string | null;
  nationality?: string | null;
  category?: string | null;
  guardian_name?: string | null;
  guardian_relation?: string | null;
  guardian_phone?: string | null;
  address_line?: string | null;
  city?: string | null;
  state?: string | null;
  pin_code?: string | null;
  remarks?: string | null;
  user?: StudentUserRef | null;
  programme?: StudentProgrammeRef | null;
  academic_session?: StudentSessionRef | null;
  created_at?: string;
  updated_at?: string;
}

export interface CreateStudentProfilePayload {
  /* nothing — enrollment happens via Admissions > Enroll */
}

export interface UpdateStudentProfilePayload {
  programme_id?: number | null;
  academic_session_id?: number | null;
  enrollment_status?: EnrollmentStatus;
  admitted_on?: string | null;
  left_on?: string | null;
  father_name?: string | null;
  mother_name?: string | null;
  dob?: string | null;
  gender?: string | null;
  nationality?: string | null;
  category?: string | null;
  guardian_name?: string | null;
  guardian_relation?: string | null;
  guardian_phone?: string | null;
  address_line?: string | null;
  city?: string | null;
  state?: string | null;
  pin_code?: string | null;
  remarks?: string | null;
}

export interface UpdateStudentStatusPayload {
  enrollment_status: EnrollmentStatus;
  left_on?: string | null;
  remarks?: string | null;
}

export interface GetStudentsParams {
  page?: number;
  q?: string;
  programme_id?: number;
  academic_session_id?: number;
  status?: EnrollmentStatus;
  per_page?: number;
}

export type StudentListResponse = PaginatedResponse<StudentProfile>;

export const studentService = {
  async getStudents(
    params?: GetStudentsParams
  ): Promise<StudentListResponse> {
    const response = await apiClient.get<StudentListResponse>("/students", {
      params,
    });
    return response.data;
  },

  async getStudent(id: number): Promise<{ student: StudentProfile }> {
    const response = await apiClient.get<{ student: StudentProfile }>(
      `/students/${id}`
    );
    return response.data;
  },

  async updateStudent(
    id: number,
    payload: UpdateStudentProfilePayload
  ): Promise<{ message: string; student: StudentProfile }> {
    const response = await apiClient.put(`/students/${id}`, payload);
    return response.data;
  },

  async updateStudentStatus(
    id: number,
    payload: UpdateStudentStatusPayload
  ): Promise<{ message: string; student: StudentProfile }> {
    const response = await apiClient.patch(`/students/${id}/status`, payload);
    return response.data;
  },
};
