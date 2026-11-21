
export type InstitutionStatus = "active" | "inactive";

export type InstitutionType =
  | "university"
  | "college"
  | "college_group"
  | "autonomous_college"
  | "other";

export interface Institution {
  id: number;
  code: string;
  name: string;
  slug: string;
  institution_type: string;
  email: string | null;
  phone: string | null;
  website: string | null;
  address: string | null;
  city: string | null;
  state: string | null;
  country: string;
  logo_path: string | null;
  status: InstitutionStatus;
  created_at: string;
  updated_at: string;
  users_count: number;
  active_users_count: number;
  inactive_users_count?: number;
  academic_units_count: number;
}

export type { PaginatedResponse, PaginationLink } from "../../shared/types/api";

export interface InstitutionListParams {
  search?: string;
  status?: InstitutionStatus | "all";
  institution_type?: string;
  sort?: string;
  per_page?: number;
  page?: number;
}

export interface CreateInstitutionPayload {
  institution: {
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
    country: string;
  };

  initial_admin: {
    name: string;
    email: string;
    phone?: string | null;
    password: string;
  };
}

export interface CreateInstitutionResponse {
  message: string;
  institution: Institution;
  institution_admin: {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    account_type: string;
    institution_id: number;
    role: unknown;
  };
}