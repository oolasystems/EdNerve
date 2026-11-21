import apiClient from "../../shared/lib/api-client";

import type {
  CreateAcademicUnitPayload,
  CreateFacultyPayload,
  CreateRoleAssignmentPayload,
  CreateInstitutionUserPayload,
  CreateSchoolPayload,
  InstitutionProfileResponse,
  InstitutionUserListResponse,
  InstitutionUserResponse,
  AcademicUnitListResponse,
  AcademicUnitResponse,
  FacultyListResponse,
  FacultyResponse,
  SchoolListResponse,
  SchoolResponse,
  RoleCatalogueResponse,
  RoleAssignmentListResponse,
  RoleAssignmentResponse,
  UpdateInstitutionProfilePayload,
  UpdateInstitutionProfileResponse,
  UpdateAcademicUnitPayload,
  UpdateFacultyPayload,
  UpdateInstitutionUserPayload,
  UpdateRoleAssignmentPayload,
  UpdateSchoolPayload,
} from "./types";

/**
 * Institution-scoped service. Endpoints are the institution middleware
 * group (reads for members, writes institution_admin-gated server-side).
 */
export const institutionService = {
  // ---------- Profile ----------
  async getProfile(): Promise<InstitutionProfileResponse> {
    const response = await apiClient.get<InstitutionProfileResponse>(
      "/institution"
    );
    return response.data;
  },

  async updateProfile(
    payload: UpdateInstitutionProfilePayload
  ): Promise<UpdateInstitutionProfileResponse> {
    const response = await apiClient.patch<UpdateInstitutionProfileResponse>(
      "/institution",
      payload
    );
    return response.data;
  },

  // ---------- Faculties ----------
  async getFaculties(): Promise<FacultyListResponse> {
    const response = await apiClient.get<FacultyListResponse>(
      "/faculties"
    );
    return response.data;
  },

  async createFaculty(
    payload: CreateFacultyPayload
  ): Promise<FacultyResponse> {
    const response = await apiClient.post<FacultyResponse>(
      "/faculties",
      payload
    );
    return response.data;
  },

  async updateFaculty(
    id: number,
    payload: UpdateFacultyPayload
  ): Promise<FacultyResponse> {
    const response = await apiClient.put<FacultyResponse>(
      `/faculties/${id}`,
      payload
    );
    return response.data;
  },

  async deactivateFaculty(id: number): Promise<{ message: string }> {
    const response = await apiClient.delete<{ message: string }>(
      `/faculties/${id}`
    );
    return response.data;
  },

  // ---------- Schools ----------
  async getSchools(): Promise<SchoolListResponse> {
    const response = await apiClient.get<SchoolListResponse>(
      "/schools"
    );
    return response.data;
  },

  async createSchool(
    payload: CreateSchoolPayload
  ): Promise<SchoolResponse> {
    const response = await apiClient.post<SchoolResponse>(
      "/schools",
      payload
    );
    return response.data;
  },

  async updateSchool(
    id: number,
    payload: UpdateSchoolPayload
  ): Promise<SchoolResponse> {
    const response = await apiClient.put<SchoolResponse>(
      `/schools/${id}`,
      payload
    );
    return response.data;
  },

  async deactivateSchool(id: number): Promise<{ message: string }> {
    const response = await apiClient.delete<{ message: string }>(
      `/schools/${id}`
    );
    return response.data;
  },

  // ---------- Academic units ----------
  async getAcademicUnits(): Promise<AcademicUnitListResponse> {
    const response = await apiClient.get<AcademicUnitListResponse>(
      "/academic-units"
    );
    return response.data;
  },

  async getAllAcademicUnits(): Promise<AcademicUnitListResponse["academic_units"]> {
    const first = await institutionService.getAcademicUnits();
    return first.academic_units;
  },

  async createAcademicUnit(
    payload: CreateAcademicUnitPayload
  ): Promise<AcademicUnitResponse> {
    const response = await apiClient.post<AcademicUnitResponse>(
      "/academic-units",
      payload
    );
    return response.data;
  },

  async updateAcademicUnit(
    id: number,
    payload: UpdateAcademicUnitPayload
  ): Promise<AcademicUnitResponse> {
    const response = await apiClient.put<AcademicUnitResponse>(
      `/academic-units/${id}`,
      payload
    );
    return response.data;
  },

  async deactivateAcademicUnit(
    id: number
  ): Promise<{ message: string }> {
    const response = await apiClient.delete<{ message: string }>(
      `/academic-units/${id}`
    );
    return response.data;
  },

  // ---------- Users ----------
  async getUsers(page = 1, perPage = 20): Promise<InstitutionUserListResponse> {
    const response = await apiClient.get<InstitutionUserListResponse>(
      "/users",
      { params: { page, per_page: perPage } }
    );
    return response.data;
  },

  async getAllUsers(): Promise<InstitutionUserListResponse["data"]> {
    const first = await institutionService.getUsers(1, 20);
    if (first.last_page > 1) {
      const rest = await Promise.all(
        Array.from({ length: first.last_page - 1 }, (_, index) =>
          institutionService.getUsers(index + 2, 20)
        )
      );
      return [
        ...first.data,
        ...rest.flatMap((page) => page.data),
      ];
    }
    return first.data;
  },

  async createUser(
    payload: CreateInstitutionUserPayload
  ): Promise<InstitutionUserResponse> {
    const response = await apiClient.post<InstitutionUserResponse>(
      "/users",
      payload
    );
    return response.data;
  },

  async updateUser(
    id: number,
    payload: UpdateInstitutionUserPayload
  ): Promise<InstitutionUserResponse> {
    const response = await apiClient.put<InstitutionUserResponse>(
      `/users/${id}`,
      payload
    );
    return response.data;
  },

  async deactivateUser(id: number): Promise<{ message: string }> {
    const response = await apiClient.delete<{ message: string }>(
      `/users/${id}`
    );
    return response.data;
  },

  // ---------- Roles catalogue (read-only, platform-managed) ----------
  async getRoles(): Promise<RoleCatalogueResponse> {
    const response = await apiClient.get<RoleCatalogueResponse>("/roles");
    return response.data;
  },

  // ---------- Role assignments ----------
  async getRoleAssignments(page = 1, perPage = 25): Promise<RoleAssignmentListResponse> {
    const response = await apiClient.get<RoleAssignmentListResponse>(
      "/role-assignments",
      { params: { page, per_page: perPage } }
    );
    return response.data;
  },

  async createRoleAssignment(
    payload: CreateRoleAssignmentPayload
  ): Promise<RoleAssignmentResponse> {
    const response = await apiClient.post<RoleAssignmentResponse>(
      "/role-assignments",
      payload
    );
    return response.data;
  },

  async updateRoleAssignment(
    id: number,
    payload: UpdateRoleAssignmentPayload
  ): Promise<RoleAssignmentResponse> {
    const response = await apiClient.put<RoleAssignmentResponse>(
      `/role-assignments/${id}`,
      payload
    );
    return response.data;
  },

  async revokeRoleAssignment(id: number): Promise<{ message: string }> {
    const response = await apiClient.delete<{ message: string }>(
      `/role-assignments/${id}`
    );
    return response.data;
  },
};
