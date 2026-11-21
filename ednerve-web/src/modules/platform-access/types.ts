/** Role management & permission mapping types. */

export type RoleStatus = "active" | "inactive";

export interface Permission {
  id: number;
  code: string;
  name: string;
  module: string;
  description: string | null;
  status: RoleStatus;
  is_system: boolean;
}

export interface CreatePermissionPayload {
  code: string;
  name: string;
  module: string;
  description?: string | null;
}

export interface UpdatePermissionPayload {
  name?: string;
  description?: string | null;
  status?: RoleStatus;
}

export interface PermissionListParams {
  module?: string;
  status?: RoleStatus | "all";
  system?: "system" | "custom" | "all";
  search?: string;
}

export interface PlatformRole {
  id: number;
  code: string;
  name: string;
  description: string | null;
  status: RoleStatus;
  is_system: boolean;
  permissions_count?: number;
  permissions?: Permission[];
  created_at: string;
  updated_at: string;
}

export interface CreateRolePayload {
  code: string;
  name: string;
  description?: string | null;
  permission_ids?: number[];
}

export interface UpdateRolePayload {
  name?: string;
  description?: string | null;
  status?: RoleStatus;
}

export interface PlatformAssignmentInstitution {
  id: number;
  code: string;
  name: string;
}

export interface PlatformAssignmentUser {
  id: number;
  name: string;
  email: string;
  institution_id: number | null;
  institution: PlatformAssignmentInstitution | null;
}

export interface PlatformAssignment {
  id: number;
  user_id: number;
  role_id: number;
  institution_id: number | null;
  academic_unit_id: number | null;
  is_primary: boolean;
  status: RoleStatus;
  starts_at: string | null;
  ends_at: string | null;
  created_at: string;
  updated_at: string;
  user: PlatformAssignmentUser | null;
  role: { id: number; code: string; name: string } | null;
  academic_unit: { id: number; code: string; name: string } | null;
}

export interface PlatformAssignmentListParams {
  page?: number;
  per_page?: number;
  search?: string;
  institution_id?: number;
  role_id?: number;
  status?: RoleStatus;
  expiry?: "all" | "expiring" | "expired" | "permanent";
}
