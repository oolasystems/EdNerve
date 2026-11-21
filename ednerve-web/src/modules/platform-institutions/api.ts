import apiClient from "../../shared/lib/api-client";

import type {
  PaginatedResponse,
  Institution,
  InstitutionListParams,
  CreateInstitutionPayload,
  CreateInstitutionResponse,
} from "./types";

export const platformInstitutionsService = {
  async getInstitutions(
    params: InstitutionListParams = {}
  ): Promise<PaginatedResponse<Institution>> {
    const response = await apiClient.get<PaginatedResponse<Institution>>(
      "/platform/institutions",
      { params }
    );

    return response.data;
  },

  async getInstitution(id: number): Promise<{
    institution: Institution;
    institution_admin: {
      id: number;
      name: string;
      email: string;
      phone: string | null;
      status: string;
    } | null;
  }> {
    const response = await apiClient.get(`/platform/institutions/${id}`);

    return response.data;
  },

  async createInstitution(
    payload: CreateInstitutionPayload
  ): Promise<CreateInstitutionResponse> {
    const response = await apiClient.post<CreateInstitutionResponse>(
      "/platform/institutions",
      payload
    );

    return response.data;
  },
};