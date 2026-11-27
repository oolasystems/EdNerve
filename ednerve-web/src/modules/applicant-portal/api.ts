import axios from "axios";

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL;
const APPLICANT_TOKEN_KEY = "ednerve_applicant_auth_token";
const APPLICANT_INSTITUTION_KEY = "ednerve_applicant_institution_code";

export const applicantInstitutionCode = {
  get: () => localStorage.getItem(APPLICANT_INSTITUTION_KEY),
  set: (code: string) => localStorage.setItem(APPLICANT_INSTITUTION_KEY, code.toUpperCase()),
  clear: () => localStorage.removeItem(APPLICANT_INSTITUTION_KEY),
};

export const applicantToken = {
  get: () => localStorage.getItem(APPLICANT_TOKEN_KEY),
  set: (token: string) => localStorage.setItem(APPLICANT_TOKEN_KEY, token),
  clear: () => localStorage.removeItem(APPLICANT_TOKEN_KEY),
};

const publicClient = axios.create({
  baseURL: API_BASE_URL,
  headers: { Accept: "application/json", "Content-Type": "application/json" },
  timeout: 30000,
});

export const applicantClient = axios.create({
  baseURL: API_BASE_URL,
  headers: { Accept: "application/json", "Content-Type": "application/json" },
  timeout: 30000,
});

applicantClient.interceptors.request.use((config) => {
  const token = applicantToken.get();
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

export interface ApplicantProfile {
  id: number;
  institution_id: number;
  full_name: string;
  email: string;
  mobile_country_code?: string | null;
  mobile?: string | null;
  status: string;
  email_verified_at?: string | null;
  institution?: { id: number; code: string; name: string; slug?: string | null } | null;
}

export const applicantApi = {
  async register(payload: {
    institution_code: string;
    full_name: string;
    email: string;
    mobile_country_code?: string;
    mobile: string;
  }) {
    const { data } = await publicClient.post(
      "/public/applicants/register",
      payload
    );
    return data as { message: string };
  },

  async verifyEmail(payload: { institution_code: string; email: string; token: string }) {
    const { data } = await publicClient.post(
      "/public/applicants/verify-email",
      payload
    );
    return data as { message: string; next_step?: string; setup_token?: string };
  },

  async resendVerification(payload: {
    institution_code: string;
    email: string;
  }) {
    const { data } = await publicClient.post(
      "/public/applicants/resend-verification",
      payload
    );
    return data as { message: string };
  },

  async setPassword(payload: {
    institution_code: string;
    email: string;
    setup_token: string;
    password: string;
    password_confirmation: string;
  }) {
    const { data } = await publicClient.post(
      "/public/applicants/set-password",
      payload
    );
    return data as { message: string };
  },

  async login(payload: {
    institution_code: string;
    email: string;
    password: string;
  }) {
    const { data } = await publicClient.post(
      "/public/applicants/login",
      { ...payload, device_name: "ednerve-applicant-web" }
    );
    return data as { access_token: string; applicant: ApplicantProfile };
  },

  async me() {
    const { data } = await applicantClient.get("/applicants/auth/me");
    return (data.applicant ?? data.data ?? data) as ApplicantProfile;
  },

  async profile() {
    const { data } = await applicantClient.get("/applicant/profile");
    return data.applicant as ApplicantProfile & { mobile_verified_at?: string | null };
  },
  async updateProfile(payload: { full_name: string; mobile_country_code?: string | null; mobile?: string | null }) {
    const { data } = await applicantClient.put("/applicant/profile", payload);
    return data.applicant as ApplicantProfile & { mobile_verified_at?: string | null };
  },
  async intakes() {
    const { data } = await applicantClient.get("/applicant/admissions/intakes");
    return data.data as AdmissionIntakeOption[];
  },
  async applications() {
    const { data } = await applicantClient.get("/applicant/applications");
    return data.data as ApplicantApplication[];
  },
  async createApplication(intake_id: number) {
    const { data } = await applicantClient.post("/applicant/applications", { intake_id });
    return data.application as ApplicantApplication;
  },
  async application(id: number) {
    const { data } = await applicantClient.get(`/applicant/applications/${id}`);
    return data.application as ApplicantApplication & Record<string, unknown>;
  },
  async updateApplication(id: number, payload: Record<string, unknown>) {
    const { data } = await applicantClient.put(`/applicant/applications/${id}`, payload);
    return data.application as ApplicantApplication;
  },
  async applicationForm(id: number) {
    const { data } = await applicantClient.get(`/applicant/applications/${id}/form`);
    return data as ApplicantApplicationForm;
  },
  async saveApplicationAnswers(id: number, answers: Record<string, unknown>) {
    const { data } = await applicantClient.put(`/applicant/applications/${id}/answers`, { answers });
    return data as { message: string };
  },
  async saveQualifications(id: number, qualifications: ApplicantQualification[]) {
    const { data } = await applicantClient.put(`/applicant/applications/${id}/qualifications`, { qualifications });
    return data as { message: string };
  },
  async uploadApplicationDocument(id: number, documentType: string, file: File) {
    const payload = new FormData();
    payload.append("document_type", documentType);
    payload.append("file", file);
    const { data } = await applicantClient.post(`/applicant/applications/${id}/documents`, payload, {
      headers: { "Content-Type": "multipart/form-data" },
    });
    return data.document as ApplicantApplicationDocument;
  },
  async submitApplication(id: number) {
    const { data } = await applicantClient.post(`/applicant/applications/${id}/submit`, {
      declaration_accepted: true,
    });
    return data as {
      message: string;
      application: { id: number; application_number: string; status: string; submitted_at: string };
    };
  },
  async clarifications(applicationId: number) {
    const { data } = await applicantClient.get(`/applicant/applications/${applicationId}/clarifications`);
    return data.data as ApplicantClarification[];
  },
  async respondToClarification(applicationId: number, clarificationId: number, applicant_response: string) {
    const { data } = await applicantClient.post(`/applicant/applications/${applicationId}/clarifications/${clarificationId}/respond`, { applicant_response });
    return data as { message: string; clarification: ApplicantClarification };
  },
  async logout() {
    try {
      await applicantClient.post("/applicants/auth/logout");
    } finally {
      applicantToken.clear();
    }
  },
};

export interface AdmissionIntakeOption { id: number; name: string; open_date: string; close_date: string; application_fee: string | null; programme: { id: number; code: string; name: string; level: string } | null; academic_session: string | null; }
export interface ApplicantApplication { id: number; application_number: string; status: string; intake_id: number; programme?: string | null; programme_code?: string | null; intake?: string | null; academic_session?: string | null; full_name?: string | null; updated_at?: string; }

export interface ApplicantFormField { id: number; field_key: string; label: string; field_type: string; help_text?: string | null; is_required: boolean; validation_rules?: Record<string, unknown>; options: { value: string; label: string }[]; }
export interface ApplicantFormSection { id: number; key: string; title: string; description?: string | null; required: boolean; fields: ApplicantFormField[]; }
export interface ApplicantQualification { id?: number; qualification_level: string; qualification_name: string; board_or_university?: string; institution_name?: string; country?: string; state?: string; roll_number?: string; passing_year?: number | string | null; result_status?: string; maximum_marks?: number | string | null; marks_obtained?: number | string | null; cgpa?: number | string | null; percentage?: number | string | null; stream_or_subjects?: string; }
export interface ApplicantApplicationDocument { id: number; document_type: string; original_filename: string; mime_type?: string | null; file_size?: number | null; verification_status: string; review_notes?: string | null; reviewed_at?: string | null; created_at: string; }
export interface ApplicantApplicationForm { documents: ApplicantApplicationDocument[]; form_version: { id: number; version_number: number } | null; sections: ApplicantFormSection[]; answers: Record<string, unknown>; qualifications: ApplicantQualification[]; application: { id: number; status: string; application_number: string }; }

export interface ApplicantClarification { id: number; request_text: string; requested_fields: string[]; status: "open" | "responded" | "resolved"; due_at?: string | null; applicant_response?: string | null; responded_at?: string | null; created_at?: string; }
