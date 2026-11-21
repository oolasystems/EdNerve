import apiClient from "../../shared/lib/api-client";

import type {
  ApplicantPayload,
  ApplicationStatusResponse,
  PublicInstitutionResponse,
  SubmitApplicationResponse,
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

  async submitApplication(
    payload: ApplicantPayload
  ): Promise<SubmitApplicationResponse> {
    const response = await apiClient.post<SubmitApplicationResponse>(
      "/public/applications",
      payload
    );
    return response.data;
  },

  async checkApplicationStatus(params: {
    application_number: string;
    dob: string;
  }): Promise<ApplicationStatusResponse> {
    const response = await apiClient.get<ApplicationStatusResponse>(
      "/public/application-status",
      { params }
    );
    return response.data;
  },
};
