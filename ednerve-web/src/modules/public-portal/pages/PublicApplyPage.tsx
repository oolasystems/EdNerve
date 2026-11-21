import { useState } from "react";
import { useParams, Link } from "react-router-dom";
import {
  CheckCircle2,
  CalendarDays,
  GraduationCap,
  MapPin,
  IndianRupee,
  Loader2,
  Users,
} from "lucide-react";
import { useQuery } from "@tanstack/react-query";

import { publicPortalService } from "../api";
import "../public.css";
import type { ApplicantPayload } from "../types";

import logo from "../../../assets/brand/oolaednerve-logo-light.svg";

import { Alert } from "../../../shared/ui";
import { getApiErrorMessage } from "../../../shared/lib/api-error";

const initialForm: ApplicantPayload = {
  intake_id: 0,
  first_name: "",
  middle_name: "",
  last_name: "",
  father_name: "",
  mother_name: "",
  dob: "",
  gender: "Male",
  category: "General",
  nationality: "Indian",
  email: "",
  phone: "",
  address_line: "",
  city: "",
  state: "",
  pin_code: "",
  last_exam_name: "",
  last_exam_board_or_univ: "",
  last_exam_year: undefined,
  last_exam_percentage: undefined,
};

const PublicApplyPage = () => {
  const { institutionCode } = useParams<{ institutionCode: string }>();

  const portalQuery = useQuery({
    queryKey: ["public", "portal", institutionCode],
    queryFn: () => publicPortalService.getInstitution(String(institutionCode)),
    enabled: Boolean(institutionCode),
  });

  const [selectedIntake, setSelectedIntake] = useState<number | null>(null);
  const [form, setForm] = useState<ApplicantPayload>(initialForm);
  const [formError, setFormError] = useState("");
  const [submitted, setSubmitted] = useState<{
    application_number: string;
    intake: string;
  } | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const institution = portalQuery.data?.institution;
  const intakes = portalQuery.data?.intakes ?? [];

  if (portalQuery.isLoading) {
    return (
      <div className="public-portal">
        <div className="public-portal-container public-portal-card">
          <p>Loading admission portal...</p>
        </div>
      </div>
    );
  }

  if (portalQuery.isError || !institution) {
    return (
      <div className="public-portal">
        <div className="public-portal-container public-portal-card">
          <Alert tone="error">
            Unable to open the admission portal for &quot;{institutionCode}&quot;.
            Please check the link or contact the institution.
          </Alert>
        </div>
      </div>
    );
  }

  if (submitted) {
    return (
      <div className="public-portal">
        <div className="public-portal-container">
          <div className="public-portal-brand">
            <img src={logo} alt="OolaEdNerve" />
          </div>

          <div className="public-success-card">
            <CheckCircle2 size={44} color="var(--brand-teal)" />
            <h1 style={{ color: "#ffffff", marginBottom: 6 }}>
              Application received
            </h1>
            <p style={{ color: "#b9cbd9" }}>
              Your application for <strong>{submitted.intake}</strong> has been
              received. Keep this application number safe — you will need it,
              along with your date of birth, to track your admission
              decision.
            </p>

            <div className="public-application-number">
              {submitted.application_number}
            </div>

            <p>
              <Link
                to={`/apply/${institutionCode}/status`}
                style={{ color: "var(--brand-teal)" }}
              >
                Track it on the status page →
              </Link>
            </p>
          </div>
        </div>
      </div>
    );
  }

  const field = (key: keyof ApplicantPayload) =>
    (event: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) =>
      setForm((current) => ({
        ...current,
        [key]: event.target.value,
      }));

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setFormError("");

    if (!selectedIntake) {
      setFormError("Please select an intake to apply for.");
      return;
    }
    if (!form.first_name.trim() || !form.last_name.trim()) {
      setFormError("First name and last name are required.");
      return;
    }
    if (!form.dob) {
      setFormError("Date of birth is required.");
      return;
    }
    if (!form.email.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
      setFormError("Enter a valid email address.");
      return;
    }
    if (!form.phone.trim()) {
      setFormError("Phone number is required.");
      return;
    }

    setSubmitting(true);
    try {
      const response = await publicPortalService.submitApplication({
        ...form,
        intake_id: selectedIntake,
        first_name: form.first_name.trim(),
        last_name: form.last_name.trim(),
        middle_name: form.middle_name?.trim() || null,
        father_name: form.father_name?.trim() || null,
        mother_name: form.mother_name?.trim() || null,
        email: form.email.trim().toLowerCase(),
        phone: form.phone.trim(),
        address_line: form.address_line?.trim() || null,
        city: form.city?.trim() || null,
        state: form.state?.trim() || null,
        pin_code: form.pin_code?.trim() || null,
        last_exam_name: form.last_exam_name?.trim() || null,
        last_exam_board_or_univ: form.last_exam_board_or_univ?.trim() || null,
        last_exam_year: form.last_exam_year ? Number(form.last_exam_year) : null,
        last_exam_percentage: form.last_exam_percentage
          ? Number(form.last_exam_percentage)
          : null,
      });

      setSubmitted({
        application_number: response.application_number,
        intake: response.intake,
      });
    } catch (error) {
      setFormError(
        getApiErrorMessage(error, "Unable to submit your application.")
      );
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="public-portal">
      <div className="public-portal-container">
        <div className="public-portal-brand">
          <img src={logo} alt="OolaEdNerve" />
        </div>

        <div className="public-portal-card">
          <span className="public-portal-eyebrow">
            Admissions 2026 · {institution.institution_type}
          </span>
          <h1 className="public-portal-title">{institution.name}</h1>
          <p className="public-portal-subtitle">
            Apply online for the programmes below. Applications are reviewed
            by the institution and decisions are communicated on this portal.
          </p>

          {/* Open intakes */}
          {intakes.length === 0 ? (
            <p style={{ marginTop: 28, color: "#ffb4a8" }}>
              No admission intakes are currently open for this institution.
              Please check back later or contact the admissions office.
            </p>
          ) : (
            <>
              <div className="public-intake-list">
                {intakes.map((intake) => (
                  <article
                    key={intake.id}
                    className="public-intake-card"
                    aria-label={intake.name}
                  >
                    <h3>{intake.programme_name ?? intake.name}</h3>
                    <div className="public-intake-meta">
                      {intake.programme_code && (
                        <span>
                          <GraduationCap size={14} /> {intake.programme_code} ·{" "}
                          {intake.programme_level}
                        </span>
                      )}
                      {intake.session_code && (
                        <span>
                          <CalendarDays size={14} /> Session {intake.session_code}
                        </span>
                      )}
                      <span>
                        <Users size={14} /> {intake.seats_remaining} of{" "}
                        {intake.seat_capacity} seats left
                      </span>
                      {intake.application_fee && (
                        <span>
                          <IndianRupee size={14} /> Fee ₹
                          {Number(intake.application_fee).toLocaleString("en-IN")}
                        </span>
                      )}
                      <span>
                        <MapPin size={14} /> Apply by {intake.close_date}
                      </span>
                    </div>

                    <button
                      type="button"
                      className="public-apply-button"
                      onClick={() => setSelectedIntake(intake.id)}
                    >
                      {selectedIntake === intake.id
                        ? "Selected ✓"
                        : "Apply for this programme"}
                    </button>
                  </article>
                ))}
              </div>

              {/* Application form */}
              {selectedIntake && (
                <form onSubmit={handleSubmit} noValidate>
                  <div className="public-form-section">
                    <h2>Applicant Details</h2>
                    <div className="public-form-grid">
                      <div className="public-field">
                        <label htmlFor="first_name">First Name *</label>
                        <input id="first_name" required value={form.first_name} onChange={field("first_name")} />
                      </div>
                      <div className="public-field">
                        <label htmlFor="middle_name">Middle Name</label>
                        <input id="middle_name" value={form.middle_name ?? ""} onChange={field("middle_name")} />
                      </div>
                      <div className="public-field">
                        <label htmlFor="last_name">Last Name *</label>
                        <input id="last_name" required value={form.last_name} onChange={field("last_name")} />
                      </div>
                      <div className="public-field">
                        <label htmlFor="dob">Date of Birth *</label>
                        <input id="dob" required type="date" value={form.dob} onChange={field("dob")} />
                      </div>
                      <div className="public-field">
                        <label htmlFor="father_name">Father / Guardian Name</label>
                        <input id="father_name" value={form.father_name ?? ""} onChange={field("father_name")} />
                      </div>
                      <div className="public-field">
                        <label htmlFor="mother_name">Mother / Guardian Name</label>
                        <input id="mother_name" value={form.mother_name ?? ""} onChange={field("mother_name")} />
                      </div>
                      <div className="public-field">
                        <label htmlFor="gender">Gender</label>
                        <select id="gender" value={form.gender ?? "Male"} onChange={field("gender")}>
                          <option value="Male">Male</option>
                          <option value="Female">Female</option>
                          <option value="Other">Other</option>
                        </select>
                      </div>
                      <div className="public-field">
                        <label htmlFor="category">Category</label>
                        <select id="category" value={form.category ?? "General"} onChange={field("category")}>
                          <option value="General">General</option>
                          <option value="OBC">OBC</option>
                          <option value="SC">SC</option>
                          <option value="ST">ST</option>
                          <option value="EWS">EWS</option>
                          <option value="Other">Other</option>
                        </select>
                      </div>
                    </div>
                  </div>

                  <div className="public-form-section">
                    <h2>Contact Details</h2>
                    <div className="public-form-grid">
                      <div className="public-field">
                        <label htmlFor="email">Email Address *</label>
                        <input id="email" required type="email" value={form.email} onChange={field("email")} placeholder="you@example.com" />
                      </div>
                      <div className="public-field">
                        <label htmlFor="phone">Mobile Number *</label>
                        <input id="phone" required type="tel" value={form.phone} onChange={field("phone")} placeholder="9876543210" />
                      </div>
                      <div className="public-field" style={{ gridColumn: "1 / -1" }}>
                        <label htmlFor="address_line">Address</label>
                        <textarea id="address_line" rows={2} value={form.address_line ?? ""} onChange={field("address_line")} />
                      </div>
                      <div className="public-field">
                        <label htmlFor="city">City</label>
                        <input id="city" value={form.city ?? ""} onChange={field("city")} />
                      </div>
                      <div className="public-field">
                        <label htmlFor="state">State</label>
                        <input id="state" value={form.state ?? ""} onChange={field("state")} />
                      </div>
                      <div className="public-field">
                        <label htmlFor="pin_code">PIN Code</label>
                        <input id="pin_code" value={form.pin_code ?? ""} onChange={field("pin_code")} />
                      </div>
                    </div>
                  </div>

                  <div className="public-form-section">
                    <h2>Academic Background</h2>
                    <div className="public-form-grid">
                      <div className="public-field">
                        <label htmlFor="last_exam_name">Last Examination</label>
                        <input id="last_exam_name" value={form.last_exam_name ?? ""} onChange={field("last_exam_name")} placeholder="Class 12" />
                      </div>
                      <div className="public-field">
                        <label htmlFor="last_exam_board_or_univ">Board / University</label>
                        <input id="last_exam_board_or_univ" value={form.last_exam_board_or_univ ?? ""} onChange={field("last_exam_board_or_univ")} placeholder="CBSE" />
                      </div>
                      <div className="public-field">
                        <label htmlFor="last_exam_year">Year of Passing</label>
                        <input id="last_exam_year" type="number" min={1950} max={2100} value={form.last_exam_year ?? ""} onChange={field("last_exam_year")} placeholder="2025" />
                      </div>
                      <div className="public-field">
                        <label htmlFor="last_exam_percentage">Percentage / CGPA</label>
                        <input id="last_exam_percentage" type="number" min={0} max={100} step="0.01" value={form.last_exam_percentage ?? ""} onChange={field("last_exam_percentage")} placeholder="92.5" />
                      </div>
                    </div>
                  </div>

                  {formError && (
                    <div style={{ marginTop: 16 }}>
                      <Alert tone="error">{formError}</Alert>
                    </div>
                  )}

                  <button
                    type="submit"
                    className="public-apply-button"
                    style={{ marginTop: 20, fontSize: 16, padding: "14px 24px" }}
                    disabled={submitting}
                  >
                    {submitting ? (
                      <>
                        <Loader2 size={18} className="spinner" />
                        Submitting...
                      </>
                    ) : (
                      "Submit Application"
                    )}
                  </button>
                </form>
              )}
            </>
          )}
        </div>

        <footer className="public-footer">
          Powered by OolaEdNerve · {institution.name} · {institution.city ?? "India"}
        </footer>
      </div>
    </div>
  );
};

export default PublicApplyPage;
