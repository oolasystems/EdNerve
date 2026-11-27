import { useState } from "react";
import { useParams, Link } from "react-router-dom";
import {
  Search,
  CalendarDays,
  CheckCircle2,
  ShieldCheck,
} from "lucide-react";
import { useMutation, useQuery } from "@tanstack/react-query";

import { publicPortalService } from "../api";
import "../public.css";

import logo from "../../../assets/brand/oolaednerve-logo-light.svg";

import { Alert } from "../../../shared/ui";

const ApplicationStatusPage = () => {
  const { institutionCode } = useParams<{ institutionCode: string }>();
  const [applicationNumber, setApplicationNumber] = useState("");
  const [dob, setDob] = useState("");

  const statusQuery = useMutation({
    mutationFn: () =>
      publicPortalService.checkApplicationStatus(String(institutionCode), {
        application_number: applicationNumber.trim(),
        dob,
      }),
  });

  const institutionQuery = useQuery({
    queryKey: ["public", "portal", institutionCode],
    queryFn: () => publicPortalService.getInstitution(String(institutionCode)),
    enabled: Boolean(institutionCode),
  });

  const institution = institutionQuery.data?.institution;

  const result = statusQuery.data;
  const notFound = statusQuery.isError;

  const handleLookup = (event: React.FormEvent) => {
    event.preventDefault();
    statusQuery.mutate();
  };

  const humanStatus = (status: string) => status.replace(/_/g, " ");

  return (
    <div className="public-portal">
      <div className="public-portal-container">
        <div className="public-portal-brand">
          <img src={logo} alt="OolaEdNerve" />
        </div>

        <div className="public-portal-card">
          <span className="public-portal-eyebrow">Admission Status</span>
          <h1 className="public-portal-title">
            {institution?.name ?? "Check your admission status"}
          </h1>
          <p className="public-portal-subtitle">
            Enter your application number and your date of birth as filled in
            the application form.
          </p>

          <form onSubmit={handleLookup} className="public-status-lookup-form">
            <div className="public-field public-status-application-field">
              <label htmlFor="application_number">Application Number</label>
              <input
                id="application_number"
                value={applicationNumber}
                onChange={(event) => { setApplicationNumber(event.target.value); statusQuery.reset(); }}
                placeholder="ADMA-2026-000001"
                autoComplete="off"
                required
              />
            </div>

            <div className="public-field public-status-dob-field">
              <label htmlFor="dob">Date of Birth</label>
              <input
                id="dob"
                type="date"
                value={dob}
                onChange={(event) => { setDob(event.target.value); statusQuery.reset(); }}
                required
              />
            </div>

            <div className="public-status-submit-wrap">
              <button
                type="submit"
                className="public-apply-button public-status-submit-button"
                disabled={statusQuery.isPending}
              >
                <Search size={17} />
                {statusQuery.isPending ? "Checking..." : "Check Status"}
              </button>
            </div>
          </form>

          {notFound && (
            <div className="public-status-error">
              <Alert tone="error">
                We could not find an application with these details. Check your
                application number and date of birth, or contact the admissions
                office.
              </Alert>
            </div>
          )}

          {result && (
            <div
              className={`public-status-hero status-${result.status}`}
              role="status"
            >
              <div className="public-status-heading">
                {result.status === "admitted" || result.status === "enrolled" ? (
                  <ShieldCheck size={32} color="var(--brand-teal)" />
                ) : (
                  <CalendarDays size={32} color="var(--brand-teal)" />
                )}
                <div>
                  <span className="public-portal-eyebrow">
                    Application Status
                  </span>
                  <h2 className="public-status-title">
                    {humanStatus(result.status)}
                  </h2>
                </div>
              </div>

              <dl className="public-status-details">
                <div>
                  <dt className="public-status-detail-label">
                    Applicant name
                  </dt>
                  <dd className="public-status-detail-value">
                    {result.applicant_name}
                  </dd>
                </div>
                <div>
                  <dt className="public-status-detail-label">
                    Application number
                  </dt>
                  <dd className="public-status-detail-value public-status-application-number">
                    {result.application_number}
                  </dd>
                </div>
                {result.programme && (
                  <div>
                    <dt className="public-status-detail-label">Programme</dt>
                    <dd className="public-status-detail-value">
                      {result.programme.name} ({result.programme.code})
                    </dd>
                  </div>
                )}
                {result.intake && (
                  <div>
                    <dt className="public-status-detail-label">Intake</dt>
                    <dd className="public-status-detail-value">{result.intake}</dd>
                  </div>
                )}
              </dl>

              <p className="public-status-timestamps">
                Submitted: {result.submitted_at ? new Date(result.submitted_at).toLocaleString("en-IN") : "—"}
                {" · "}Institution: {result.institution}
              </p>

              {(result.status === "admitted" || result.status === "enrolled") && (
                <p className="public-status-congratulations">
                  <CheckCircle2 size={16} className="public-status-success-icon" />
                  {result.status === "enrolled"
                    ? " You are enrolled. Please complete your student onboarding to get portal access."
                    : " Congratulations! Your admission has been offered. Watch this page and your phone/email for enrollment instructions."}
                </p>
              )}
            </div>
          )}
        </div>

        <p className="public-status-back-link">
          <Link
            to={`/apply/${institutionCode}`}
            className="public-status-back-link-anchor"
          >
            ← Back to admissions
          </Link>
        </p>
      </div>
    </div>
  );
};

export default ApplicationStatusPage;
