import apiClient from "../../shared/lib/api-client";

import type {
  ApplicationRow,
  ApplicationDetailResponse,
  ApplicationReviewDocument,
  ApplicationClarification,
  ApplicationListResponse,
  AdmissionIntake,
  CreateAdmissionIntakePayload,
  DecisionPayload,
  UpdateAdmissionIntakePayload,
} from "./types";

export const admissionService = {
  // ---------- Intakes ----------
  async getIntakes(): Promise<{ intakes: AdmissionIntake[] }> {
    const response = await apiClient.get<{ intakes: AdmissionIntake[] }>(
      "/admissions/intakes"
    );
    return response.data;
  },

  async createIntake(
    payload: CreateAdmissionIntakePayload
  ): Promise<{ message: string; intake: AdmissionIntake }> {
    const response = await apiClient.post("/admissions/intakes", payload);
    return response.data;
  },

  async updateIntake(
    id: number,
    payload: UpdateAdmissionIntakePayload
  ): Promise<{ message: string; intake: AdmissionIntake }> {
    const response = await apiClient.patch(`/admissions/intakes/${id}`, payload);
    return response.data;
  },

  async deleteIntake(id: number): Promise<{ message: string }> {
    const response = await apiClient.delete(`/admissions/intakes/${id}`);
    return response.data;
  },

  // ---------- Applications ----------
  async getApplications(params: {
    page?: number;
    intake_id?: number;
    status?: string;
    q?: string;
    per_page?: number;
  }): Promise<ApplicationListResponse> {
    const response = await apiClient.get<ApplicationListResponse>(
      "/admissions/applications",
      { params }
    );
    return response.data;
  },

  async getApplication(id: number): Promise<ApplicationDetailResponse> {
    const response = await apiClient.get<ApplicationDetailResponse>(
      `/admissions/applications/${id}`
    );
    return response.data;
  },

  async downloadApplicationDocument(
    applicationId: number,
    document: ApplicationReviewDocument
  ): Promise<Blob> {
    const response = await apiClient.get(
      `/admissions/applications/${applicationId}/documents/${document.id}`,
      { responseType: 'blob' }
    );
    return response.data as Blob;
  },

  async createClarification(id: number, payload: { request_text: string; requested_fields?: string[]; due_at?: string | null }) {
    const response = await apiClient.post(`/admissions/applications/${id}/clarifications`, payload);
    return response.data as { message: string; clarification: ApplicationClarification };
  },

  async resolveClarification(id: number, clarificationId: number, resolution_notes?: string) {
    const response = await apiClient.patch(`/admissions/applications/${id}/clarifications/${clarificationId}/resolve`, { resolution_notes });
    return response.data as { message: string; clarification: ApplicationClarification };
  },

  async verifyDocument(id: number, documentId: number, payload: { verification_status: "verified" | "returned"; review_notes?: string }) {
    const response = await apiClient.patch(`/admissions/applications/${id}/documents/${documentId}/verification`, payload);
    return response.data as { message: string; document: { id: number; verification_status: string; review_notes?: string | null } };
  },

  async decideApplication(
    id: number,
    payload: DecisionPayload
  ): Promise<{ message: string; application: ApplicationRow }> {
    const response = await apiClient.patch(
      `/admissions/applications/${id}/decision`,
      payload
    );
    return response.data;
  },

  async enrollApplicant(
    id: number
  ): Promise<{
    message: string;
    application: ApplicationRow;
    activation_email_sent: boolean | null;
  }> {
    const response = await apiClient.post(`/admissions/applications/${id}/enroll`);
    return response.data;
  },
};
