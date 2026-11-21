// =====================================================
// Authentication Types
// =====================================================

export type AccountType = "platform" | "institution";

export type UserStatus = "active" | "inactive";

/** Minimal shape of a role as serialized on /auth/me */
export interface AuthRole {
  id: number;
  code: string;
  name: string;
}

/** Assignment as serialized on /auth/me (nested under active_role_assignments). */
export interface AuthRoleAssignment {
  id: number;
  role_id: number;
  academic_unit_id: number | null;
  is_primary?: boolean;
  status?: string;
  starts_at?: string | null;
  ends_at?: string | null;
  role?: AuthRole | null;
}

/** What an institution is, as serialized on institution users. */
export interface InstitutionSummary {
  id: number;
  code: string;
  name: string;
  institution_type?: string;
  city?: string | null;
  state?: string | null;
  country?: string | null;
  status?: UserStatus;
}

// =====================================================
// Authenticated User
// =====================================================

export interface AuthUser {
  id: number;
  account_type: AccountType;
  institution_id: number | null;
  name: string;
  email: string;
  phone?: string | null;
  status: UserStatus;
  last_login_at?: string | null;
  institution?: InstitutionSummary | null;
  active_role_assignments?: AuthRoleAssignment[];
}

// =====================================================
// Login
// =====================================================

export interface LoginPayload {
  email: string;
  password: string;
}

export interface LoginResponse {
  message?: string;
  token?: string;
  token_type?: string;
  user?: AuthUser;
  mfa_required?: boolean;
  mfa_token?: string;
}

export type LoginResult =
  | { kind: "authenticated"; user: AuthUser }
  | { kind: "mfa_required"; mfaToken: string };

// =====================================================
// Current Authenticated User
// =====================================================

export interface CurrentUserResponse {
  user: AuthUser;
}

// =====================================================
// Logout
// =====================================================

export interface LogoutResponse {
  message: string;
}