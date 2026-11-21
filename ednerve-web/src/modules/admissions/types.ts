export type AdmissionIntakeStatus = "open" | "closed";

export interface ProgrammeSummary {
  id: number;
  code: string;
  name: string;
}

export interface SessionSummary {
  id: number;
  code: string;
  name: string;
}

export interface AdmissionIntake {
  id: number;
  name: string;
  programme: ProgrammeSummary | null;
  session: SessionSummary | null;
  open_date: string | null;
  close_date: string | null;
  seat_capacity: number;
  seats_remaining: number;
  enrolled_count: number;
  status: AdmissionIntakeStatus;
  application_fee: string | null;
  applications_count: number;
  admitted_count: number;
}

export interface CreateAdmissionIntakePayload {
  name: string;
  programme_id: number;
  academic_session_id: number;
  open_date: string;
  close_date: string;
  seat_capacity: number;
  status?: AdmissionIntakeStatus;
  application_fee?: number | null;
}

export interface UpdateAdmissionIntakePayload {
  name?: string;
  programme_id?: number;
  academic_session_id?: number;
  open_date?: string;
  close_date?: string;
  seat_capacity?: number;
  status?: AdmissionIntakeStatus;
  application_fee?: number | null;
}

export type ApplicationStatus =
  | "new"
  | "under_review"
  | "shortlisted"
  | "waitlisted"
  | "rejected"
  | "admitted"
  | "enrolled";

export interface ApplicationRow {
  id: number;
  institution_id: number;
  intake_id: number;
  first_name: string;
  middle_name?: string | null;
  last_name: string;
  father_name?: string | null;
  mother_name?: string | null;
  dob: string;
  gender?: string | null;
  category: string;
  nationality: string;
  email: string;
  phone: string;
  address_line?: string | null;
  city?: string | null;
  state?: string | null;
  pin_code?: string | null;
  last_exam_name?: string | null;
  last_exam_board_or_univ?: string | null;
  last_exam_year?: number | null;
  last_exam_percentage?: string | null;
  application_number: string;
  status: ApplicationStatus;
  review_notes?: string | null;
  reviewed_at?: string | null;
  enrolled_user_id?: number | null;
  intake?: {
    id: number;
    name: string;
    programme_id: number;
    programme?: { id: number; code: string; name: string } | null;
    academicSession?: { id: number; code: string; name: string } | null;
  } | null;
  reviewer?: { id: number; name: string; email: string } | null;
  enrolledUser?: { id: number; name: string; email: string; status: string } | null;
  created_at?: string;
  updated_at?: string;
}

export interface ApplicationListResponse {
  current_page: number;
  data: ApplicationRow[];
  first_page_url: string | null;
  from: number | null;
  last_page: number;
  last_page_url: string | null;
  links?: unknown[];
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_page_url: string | null;
  to: number | null;
  total: number;
}

export interface DecisionPayload {
  status: Exclude<ApplicationStatus, "enrolled" | "new">;
  notes?: string;
}
