import apiClient from "../../shared/lib/api-client";

import type {
  ApplicationStatusResponse,
  PublicInstitutionResponse,
} from "./types";

export const publicPortalService = {
  async getInstitution(
    institutionCode: string
  ): Promise<PublicInstitutionResponse> {
    const response = await apiClient.get<PublicInstitutionResponse>(
      `/public/admissions/${institutionCode}`
    );
    return response.data;
  },

  async checkApplicationStatus(
    institutionCode: string,
    params: { application_number: string; dob: string }
  ): Promise<ApplicationStatusResponse> {
    const response = await apiClient.post<ApplicationStatusResponse>(
      `/public/admissions/${encodeURIComponent(institutionCode)}/application-status`,
      params
    );
    return response.data;
  },
};
