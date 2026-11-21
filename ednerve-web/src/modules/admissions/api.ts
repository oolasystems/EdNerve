import apiClient from "../../shared/lib/api-client";

import type {
  ApplicationRow,
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

  async getApplication(id: number): Promise<{ application: ApplicationRow }> {
    const response = await apiClient.get<{ application: ApplicationRow }>(
      `/admissions/applications/${id}`
    );
    return response.data;
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
    credentials: {
      name: string;
      email: string;
      temporary_password: string;
    } | null;
  }> {
    const response = await apiClient.post(`/admissions/applications/${id}/enroll`);
    return response.data;
  },
};
