export interface PublicIntake {
  id: number;
  name: string;
  programme_code: string | null;
  programme_name: string | null;
  programme_level: string | null;
  session_code: string | null;
  open_date: string | null;
  close_date: string | null;
  seat_capacity: number;
  enrolled_count: number;
  seats_remaining: number;
  application_fee: string | null;
}

export interface PublicInstitution {
  code: string;
  name: string;
  institution_type: string;
  city: string | null;
  state: string | null;
  website: string | null;
}

export interface PublicInstitutionResponse {
  institution: PublicInstitution;
  intakes: PublicIntake[];
}

export interface ApplicantPayload {
  intake_id: number;
  first_name: string;
  middle_name?: string | null;
  last_name: string;
  father_name?: string | null;
  mother_name?: string | null;
  dob: string;
  gender?: string | null;
  category?: string | null;
  nationality?: string | null;
  email: string;
  phone: string;
  address_line?: string | null;
  city?: string | null;
  state?: string | null;
  pin_code?: string | null;
  last_exam_name?: string | null;
  last_exam_board_or_univ?: string | null;
  last_exam_year?: number | null;
  last_exam_percentage?: number | null;
}

export interface SubmitApplicationResponse {
  message: string;
  application_number: string;
  status: string;
  intake: string;
}

export interface ApplicationStatusResponse {
  application_number: string;
  status: string;
  status_label: string;
  applicant_name: string;
  programme: { code: string; name: string } | null;
  intake: string | null;
  submitted_at: string | null;
  last_update: string | null;
}
