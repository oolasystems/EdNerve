import apiClient from "../../shared/lib/api-client";

import type {
  CreatePermissionPayload,
  CreateRolePayload,
  Permission,
  PermissionListParams,
  PlatformAssignment,
  PlatformAssignmentListParams,
  PlatformRole,
  UpdatePermissionPayload,
  UpdateRolePayload,
} from "./types";
import type { PaginatedResponse } from "../../shared/types/api";

export const accessService = {
  // Roles
  async getRoles(): Promise<{ roles: PlatformRole[] }> {
    const response = await apiClient.get<{ roles: PlatformRole[] }>(
      "/platform/roles"
    );
    return response.data;
  },

  async getRole(id: number): Promise<{ role: PlatformRole }> {
    const response = await apiClient.get<{ role: PlatformRole }>(
      `/platform/roles/${id}`
    );
    return response.data;
  },

  async createRole(payload: CreateRolePayload): Promise<{ message: string; role: PlatformRole }> {
    const response = await apiClient.post<{ message: string; role: PlatformRole }>(
      "/platform/roles",
      payload
    );
    return response.data;
  },

  async updateRole(
    id: number,
    payload: UpdateRolePayload
  ): Promise<{ message: string; role: PlatformRole }> {
    const response = await apiClient.patch<{ message: string; role: PlatformRole }>(
      `/platform/roles/${id}`,
      payload
    );
    return response.data;
  },

  async deleteRole(id: number): Promise<{ message: string }> {
    const response = await apiClient.delete<{ message: string }>(
      `/platform/roles/${id}`
    );
    return response.data;
  },

  async syncRolePermissions(
    id: number,
    permissionIds: number[]
  ): Promise<{ message: string; role: PlatformRole }> {
    const response = await apiClient.put<{ message: string; role: PlatformRole }>(
      `/platform/roles/${id}/permissions`,
      { permission_ids: permissionIds }
    );
    return response.data;
  },

  async copyPermissionsFromRole(
    roleId: number,
    sourceRoleId: number
  ): Promise<{ message: string; role: PlatformRole }> {
    const response = await apiClient.post<{ message: string; role: PlatformRole }>(
      `/platform/roles/${roleId}/copy-permissions/${sourceRoleId}`,
      {}
    );

    return response.data;
  },

  // Permissions catalogue
  async getPermissions(
    params: PermissionListParams = {}
  ): Promise<{ permissions: Permission[] }> {
    const response = await apiClient.get<{ permissions: Permission[] }>(
      "/platform/permissions",
      { params }
    );
    return response.data;
  },

  async createPermission(
    payload: CreatePermissionPayload
  ): Promise<{ message: string; permission: Permission }> {
    const response = await apiClient.post<{ message: string; permission: Permission }>(
      "/platform/permissions",
      payload
    );
    return response.data;
  },

  async updatePermission(
    id: number,
    payload: UpdatePermissionPayload
  ): Promise<{ message: string; permission: Permission }> {
    const response = await apiClient.patch<{ message: string; permission: Permission }>(
      `/platform/permissions/${id}`,
      payload
    );
    return response.data;
  },

  // Cross-institution role assignments (read + status toggle)
  async getAssignments(
    params: PlatformAssignmentListParams = {}
  ): Promise<PaginatedResponse<PlatformAssignment>> {
    const response = await apiClient.get<PaginatedResponse<PlatformAssignment>>(
      "/platform/role-assignments",
      { params }
    );
    return response.data;
  },

  async updateAssignmentStatus(
    id: number,
    status: "active" | "inactive"
  ): Promise<{ message: string; assignment: PlatformAssignment }> {
    const response = await apiClient.patch<{ message: string; assignment: PlatformAssignment }>(
      `/platform/role-assignments/${id}/status`,
      { status }
    );
    return response.data;
  },
};
