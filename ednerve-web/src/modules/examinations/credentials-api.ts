import apiClient from "../../shared/lib/api-client";

export type CredentialStatus = "issued" | "revoked" | "replaced";

export interface CredentialUser {
  id: number;
  name: string;
  email: string;
}

export interface CredentialStudentProfile {
  id: number;
  application_number: string | null;
  user?: CredentialUser | null;
}

export interface CredentialExam {
  id: number;
  title: string;
  status: string;
}

export interface Credential {
  id: number;
  credential_number: string;
  student_profile_id: number;
  type: string;
  exam_id: number | null;
  programme_id: number | null;
  academic_session_id: number | null;
  status: CredentialStatus;
  issued_at: string;
  expires_at: string | null;
  revoked_at: string | null;
  revoke_reason: string | null;
  student_profile?: CredentialStudentProfile | null;
  exam?: CredentialExam | null;
}

export interface CredentialListResponse {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  data: Credential[];
}

export const credentialsService = {
  async getCredentials(params?: { exam_id?: number; status?: string; page?: number }): Promise<CredentialListResponse> {
    const response = await apiClient.get<CredentialListResponse>("/credentials", {
      params: { page: 1, per_page: 20, ...params },
    });
    return response.data;
  },

  async issueCredentials(examId: number): Promise<{ message: string; count: number }> {
    const response = await apiClient.post(`/examinations/${examId}/credentials/issue`, {});
    return response.data;
  },

  async revokeCredential(id: number, reason: string): Promise<{ message: string }> {
    const response = await apiClient.patch(`/credentials/${id}/revoke`, { reason });
    return response.data;
  },
};
