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

export interface ApplicationStatusResponse {
  application_number: string;
  status: string;
  status_label: string;
  applicant_name: string;
  programme: { code: string; name: string } | null;
  intake: string | null;
  submitted_at: string | null;
  institution: string;
}
