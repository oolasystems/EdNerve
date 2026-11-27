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
  applicant?: { id: number; full_name: string; email: string; mobile_country_code?: string | null; mobile?: string | null } | null;
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


export interface ApplicationReviewField {
  key: string;
  label: string;
  type: string;
  answer: unknown;
  options?: Array<{ value: string; label: string }>;
}

export interface ApplicationReviewSection {
  id: number;
  key: string;
  title: string;
  description?: string | null;
  fields: ApplicationReviewField[];
}

export interface ApplicationReviewDocument {
  id: number;
  document_type: string;
  original_filename: string;
  mime_type?: string | null;
  file_size?: number | null;
  verification_status: string;
  review_notes?: string | null;
  reviewed_at?: string | null;
  uploaded_at?: string | null;
  download_url: string;
}

export interface ApplicationReviewEvent {
  id: number;
  event_type: string;
  from_status?: string | null;
  to_status?: string | null;
  remarks?: string | null;
  actor_type?: string | null;
  occurred_at: string;
  metadata?: Record<string, unknown> | null;
}

export interface ApplicationClarification {
  id: number;
  request_text: string;
  requested_fields: string[];
  status: "open" | "responded" | "resolved";
  due_at?: string | null;
  applicant_response?: string | null;
  responded_at?: string | null;
  created_at?: string;
}

export interface ApplicationReviewData {
  form_sections: ApplicationReviewSection[];
  qualifications: Array<Record<string, unknown> & { id: number; qualification_name: string; qualification_level: string }>;
  documents: ApplicationReviewDocument[];
  can_view_documents: boolean;
  events: ApplicationReviewEvent[];
  clarifications: ApplicationClarification[];
  can_scrutinize?: boolean;
  can_verify_documents?: boolean;
}

export interface ApplicationDetailResponse {
  application: ApplicationRow;
  review: ApplicationReviewData;
}
