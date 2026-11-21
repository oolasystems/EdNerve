import apiClient from "../../shared/lib/api-client";

import type {
  CreatePlatformUserPayload,
  EffectivePermissionsResponse,
  PlatformUser,
  PlatformUserListParams,
} from "./types";
import type { PaginatedResponse } from "../../shared/types/api";

export const platformUserService = {
  async getUsers(
    params: PlatformUserListParams = {}
  ): Promise<PaginatedResponse<PlatformUser>> {
    const response = await apiClient.get<PaginatedResponse<PlatformUser>>(
      "/platform/users",
      { params }
    );

    return response.data;
  },

  async getUser(id: number): Promise<{ user: PlatformUser }> {
    const response = await apiClient.get<{ user: PlatformUser }>(
      `/platform/users/${id}`
    );

    return response.data;
  },

  async getEffectivePermissions(
    id: number
  ): Promise<EffectivePermissionsResponse> {
    const response = await apiClient.get<EffectivePermissionsResponse>(
      `/platform/users/${id}/effective-permissions`
    );

    return response.data;
  },

  async createPlatformUser(
    payload: CreatePlatformUserPayload
  ): Promise<{ message: string; user: PlatformUser }> {
    const response = await apiClient.post<{ message: string; user: PlatformUser }>(
      "/platform/platform-users",
      payload
    );

    return response.data;
  },

  async updateUserStatus(
    id: number,
    status: "active" | "inactive"
  ): Promise<{ message: string; user: PlatformUser }> {
    const response = await apiClient.patch<{ message: string; user: PlatformUser }>(
      `/platform/users/${id}/status`,
      { status }
    );

    return response.data;
  },
};
