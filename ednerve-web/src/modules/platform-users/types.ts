/** Platform-wide user administration types. */

export type AccountTypeFilter = "all" | "platform" | "institution";
export type UserStatus = "active" | "inactive";

export interface PlatformInstitutionSummary {
  id: number;
  code: string;
  name: string;
  slug: string;
}

export interface PlatformRoleSummary {
  id: number;
  code: string;
  name: string;
}

export interface PlatformUser {
  id: number;
  account_type: "platform" | "institution";
  institution_id: number | null;
  name: string;
  email: string;
  phone: string | null;
  status: UserStatus;
  created_at: string;
  updated_at: string;
  last_login_at: string | null;
  institution: PlatformInstitutionSummary | null;
  active_role_assignments: Array<{
    id: number;
    is_primary: boolean;
    role: PlatformRoleSummary;
  }>;
}

export interface CreatePlatformUserPayload {
  name: string;
  email: string;
  phone?: string | null;
  password: string;
}

export interface EffectiveAssignmentRole {
  code: string;
  name: string;
  is_system: boolean;
}

export interface EffectiveAssignment {
  id: number;
  role: EffectiveAssignmentRole;
  academic_unit: { id: number; code: string; name: string } | null;
  is_primary: boolean;
  starts_at: string | null;
  ends_at: string | null;
}

export interface EffectivePermissionVia {
  role_code: string;
  role_name: string;
  scope: string | null;
  is_primary: boolean;
  expires: string | null;
}

export interface EffectivePermission {
  id: number;
  code: string;
  name: string;
  module: string;
  description: string | null;
  via: EffectivePermissionVia[];
}

export interface EffectivePermissionsResponse {
  user: Pick<
    PlatformUser,
    "id" | "account_type" | "name" | "email" | "status" | "institution_id" | "created_at"
  > | null;
  institution: PlatformInstitutionSummary | null;
  assignments: EffectiveAssignment[];
  permissions: EffectivePermission[];
  assignment_count: number;
  permission_count: number;
}

export interface PlatformUserListParams {
  page?: number;
  per_page?: number;
  search?: string;
  account_type?: "platform" | "institution";
  institution_id?: number;
  status?: UserStatus;
  sort_by?: "name" | "email" | "created_at" | "status";
  sort_order?: "asc" | "desc";
}
