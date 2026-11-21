import type { PaginatedResponse } from "../../shared/types/api";

// =====================================================
// Academic hierarchy (Faculty → School → Academic Unit)
// =====================================================

export type AcademicUnitStatus = "active" | "inactive";
export type AcademicUnitType =
  | "department"
  | "centre"
  | "office"
  | "division"
  | "other";

export const ACADEMIC_UNIT_TYPES: Array<{
  value: AcademicUnitType;
  label: string;
}> = [
  { value: "department", label: "Department" },
  { value: "centre", label: "Centre" },
  { value: "office", label: "Office" },
  { value: "division", label: "Division" },
  { value: "other", label: "Other" },
];

export interface Faculty {
  id: number;
  institution_id: number;
  code: string;
  name: string;
  status: AcademicUnitStatus;
  created_at?: string;
  updated_at?: string;
}

export interface School {
  id: number;
  institution_id: number;
  faculty_id: number | null;
  code: string;
  name: string;
  status: AcademicUnitStatus;
  faculty?: Pick<Faculty, "id" | "code" | "name"> | null;
  created_at?: string;
  updated_at?: string;
}

export interface AcademicUnit {
  id: number;
  institution_id: number;
  school_id: number | null;
  faculty_id: number | null;
  unit_type: AcademicUnitType;
  code: string;
  name: string;
  description?: string | null;
  status: AcademicUnitStatus;
  school?: (School & { faculty?: Pick<Faculty, "id" | "code" | "name"> | null }) | null;
  faculty?: Pick<Faculty, "id" | "code" | "name"> | null;
  created_at?: string;
  updated_at?: string;
}

export interface FacultyResponse {
  message?: string;
  faculty: Faculty;
}

export interface SchoolResponse {
  message?: string;
  school: School;
}

export interface AcademicUnitResponse {
  message?: string;
  academic_unit: AcademicUnit;
}

export interface CreateFacultyPayload {
  code: string;
  name: string;
  status?: AcademicUnitStatus;
}

export interface UpdateFacultyPayload {
  code?: string;
  name?: string;
  status?: AcademicUnitStatus;
}

export interface CreateSchoolPayload {
  faculty_id?: number | null;
  code: string;
  name: string;
  status?: AcademicUnitStatus;
}

export interface UpdateSchoolPayload {
  faculty_id?: number | null;
  code?: string;
  name?: string;
  status?: AcademicUnitStatus;
}

export interface CreateAcademicUnitPayload {
  school_id?: number | null;
  faculty_id?: number | null;
  unit_type: AcademicUnitType;
  code: string;
  name: string;
  description?: string | null;
  status?: AcademicUnitStatus;
}

export interface UpdateAcademicUnitPayload {
  school_id?: number | null;
  faculty_id?: number | null;
  unit_type?: AcademicUnitType;
  code?: string;
  name?: string;
  description?: string | null;
  status?: AcademicUnitStatus;
}

export interface FacultyListResponse {
  faculties: Faculty[];
}

export interface SchoolListResponse {
  schools: School[];
}

export interface AcademicUnitListResponse {
  academic_units: AcademicUnit[];
}

// =====================================================
// Institution profile
// =====================================================

export interface InstitutionProfile {
  id: number;
  code: string;
  name: string;
  slug: string;
  institution_type: string;
  email?: string | null;
  phone?: string | null;
  website?: string | null;
  address?: string | null;
  city?: string | null;
  state?: string | null;
  country?: string | null;
  status: "active" | "inactive";
  created_at?: string;
  updated_at?: string;
}

export interface InstitutionProfileResponse {
  institution: InstitutionProfile;
}

export interface UpdateInstitutionProfilePayload {
  name?: string;
  email?: string | null;
  phone?: string | null;
  website?: string | null;
  address?: string | null;
  city?: string | null;
  state?: string | null;
  country?: string | null;
}

export interface UpdateInstitutionProfileResponse {
  message: string;
  institution: InstitutionProfile;
}

// =====================================================
// Institution users
// =====================================================

export interface InstitutionUserRoleAssignment {
  id: number;
  role_id: number;
  academic_unit_id: number | null;
  is_primary?: boolean;
  status?: string;
  starts_at?: string | null;
  ends_at?: string | null;
  role?: { id: number; code: string; name: string; status?: string } | null;
  academic_unit?: { id: number; code?: string; name: string } | null;
}

export interface InstitutionUser {
  id: number;
  account_type: "platform" | "institution";
  institution_id: number | null;
  name: string;
  email: string;
  phone?: string | null;
  status: "active" | "inactive";
  last_login_at?: string | null;
  institution?: InstitutionProfile | null;
  active_role_assignments?: InstitutionUserRoleAssignment[];
  created_at?: string;
  updated_at?: string;
}

export interface CreateInstitutionUserPayload {
  name: string;
  email: string;
  phone?: string | null;
  password: string;
  status?: "active" | "inactive";
}

export interface UpdateInstitutionUserPayload {
  name?: string;
  email?: string;
  phone?: string | null;
  password?: string;
  status?: "active" | "inactive";
}

export interface InstitutionUserResponse {
  message?: string;
  user: InstitutionUser;
}

export type InstitutionUserListResponse = PaginatedResponse<InstitutionUser>;

// =====================================================
// Roles (read-only catalogue — platform managed)
// =====================================================

export interface RoleDefinition {
  id: number;
  code: string;
  name: string;
  description?: string | null;
  status: string;
  created_at?: string;
}

export interface RoleCatalogueResponse {
  roles: RoleDefinition[];
}

export interface RoleDefinitionResponse {
  role: RoleDefinition;
}

// =====================================================
// Role assignments
// =====================================================

export interface RoleAssignment {
  id: number;
  institution_id: number;
  user_id: number;
  role_id: number;
  academic_unit_id: number | null;
  is_primary: boolean;
  status: string;
  starts_at?: string | null;
  ends_at?: string | null;
  created_at?: string;
  user?: Pick<InstitutionUser, "id" | "name" | "email" | "status"> | null;
  role?: Pick<RoleDefinition, "id" | "code" | "name" | "description"> | null;
  academic_unit?: { id: number; code?: string; name: string } | null;
}

export interface CreateRoleAssignmentPayload {
  user_id: number;
  role_id: number;
  academic_unit_id?: number | null;
  is_primary?: boolean;
  status?: "active" | "inactive";
  starts_at?: string | null;
  ends_at?: string | null;
}

export interface UpdateRoleAssignmentPayload {
  role_id?: number;
  academic_unit_id?: number | null;
  is_primary?: boolean;
  status?: "active" | "inactive";
  starts_at?: string | null;
  ends_at?: string | null;
}

export interface RoleAssignmentResponse {
  message?: string;
  assignment: RoleAssignment;
}

export type RoleAssignmentListResponse = PaginatedResponse<RoleAssignment>;
