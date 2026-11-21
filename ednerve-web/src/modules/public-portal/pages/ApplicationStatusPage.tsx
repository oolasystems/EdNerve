import { useState } from "react";
import { useParams, useSearchParams, Link } from "react-router-dom";
import {
  Search,
  CalendarDays,
  CheckCircle2,
  ShieldCheck,
} from "lucide-react";
import { useQuery } from "@tanstack/react-query";

import { publicPortalService } from "../api";
import "../public.css";

import logo from "../../../assets/brand/oolaednerve-logo-light.svg";

import { Alert } from "../../../shared/ui";

const ApplicationStatusPage = () => {
  const { institutionCode } = useParams<{ institutionCode: string }>();
  const [searchParams] = useSearchParams();

  const [applicationNumber, setApplicationNumber] = useState(
    searchParams.get("application_number") ?? ""
  );
  const [dob, setDob] = useState(searchParams.get("dob") ?? "");
  const [submitted, setSubmitted] = useState(false);

  const statusQuery = useQuery({
    queryKey: ["public", "application-status", applicationNumber, dob],
    queryFn: () =>
      publicPortalService.checkApplicationStatus({
        application_number: applicationNumber.trim(),
        dob,
      }),
    enabled: submitted && Boolean(applicationNumber.trim()) && Boolean(dob),
    retry: false,
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
    setSubmitted(true);
    void statusQuery.refetch();
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

          <form
            onSubmit={handleLookup}
            style={{ display: "flex", gap: 12, marginTop: 24, flexWrap: "wrap" }}
          >
            <div className="public-field" style={{ flex: "1 1 220px" }}>
              <label htmlFor="application_number">Application Number</label>
              <input
                id="application_number"
                value={applicationNumber}
                onChange={(event) => setApplicationNumber(event.target.value)}
                placeholder="ADMA-2026-000001"
                autoComplete="off"
                required
              />
            </div>

            <div className="public-field" style={{ flex: "1 1 180px" }}>
              <label htmlFor="dob">Date of Birth</label>
              <input
                id="dob"
                type="date"
                value={dob}
                onChange={(event) => setDob(event.target.value)}
                required
              />
            </div>

            <div style={{ alignSelf: "flex-end" }}>
              <button
                type="submit"
                className="public-apply-button"
                style={{ padding: "11px 22px" }}
                disabled={statusQuery.isFetching}
              >
                <Search size={17} />
                {statusQuery.isFetching ? "Checking..." : "Check Status"}
              </button>
            </div>
          </form>

          {notFound && (
            <div style={{ marginTop: 20 }}>
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
              <div style={{ display: "flex", alignItems: "center", gap: 14 }}>
                {result.status === "admitted" || result.status === "enrolled" ? (
                  <ShieldCheck size={32} color="var(--brand-teal)" />
                ) : (
                  <CalendarDays size={32} color="var(--brand-teal)" />
                )}
                <div>
                  <span className="public-portal-eyebrow">
                    Application Status
                  </span>
                  <h2
                    style={{
                      margin: "2px 0",
                      color: "#ffffff",
                      textTransform: "capitalize",
                    }}
                  >
                    {humanStatus(result.status)}
                  </h2>
                </div>
              </div>

              <dl
                style={{
                  display: "grid",
                  gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))",
                  gap: 16,
                  margin: "18px 0 0",
                }}
              >
                <div>
                  <dt style={{ color: "#9fb4c4", fontSize: 12 }}>
                    Applicant name
                  </dt>
                  <dd style={{ margin: 0, color: "#fff" }}>
                    {result.applicant_name}
                  </dd>
                </div>
                <div>
                  <dt style={{ color: "#9fb4c4", fontSize: 12 }}>
                    Application number
                  </dt>
                  <dd
                    style={{
                      margin: 0,
                      color: "#fff",
                      fontFamily: "var(--font-mono)",
                    }}
                  >
                    {result.application_number}
                  </dd>
                </div>
                {result.programme && (
                  <div>
                    <dt style={{ color: "#9fb4c4", fontSize: 12 }}>Programme</dt>
                    <dd style={{ margin: 0, color: "#fff" }}>
                      {result.programme.name} ({result.programme.code})
                    </dd>
                  </div>
                )}
                {result.intake && (
                  <div>
                    <dt style={{ color: "#9fb4c4", fontSize: 12 }}>Intake</dt>
                    <dd style={{ margin: 0, color: "#fff" }}>{result.intake}</dd>
                  </div>
                )}
              </dl>

              <p style={{ color: "#9fb4c4", fontSize: 13, marginBottom: 0 }}>
                Submitted: {result.submitted_at?.replace("T", " ").slice(0, 16) ?? "—"}
                {" · "}Last update:{" "}
                {result.last_update?.replace("T", " ").slice(0, 16) ?? "—"}
              </p>

              {(result.status === "admitted" || result.status === "enrolled") && (
                <p style={{ color: "#7de8e0", marginTop: 14 }}>
                  <CheckCircle2 size={16} style={{ verticalAlign: "-3px" }} />
                  {result.status === "enrolled"
                    ? " You are enrolled. Please complete your student onboarding to get portal access."
                    : " Congratulations! Your admission has been offered. Watch this page and your phone/email for enrollment instructions."}
                </p>
              )}
            </div>
          )}
        </div>

        <p style={{ marginTop: 18, textAlign: "center" }}>
          <Link
            to={`/apply/${institutionCode}`}
            style={{ color: "var(--brand-teal)" }}
          >
            ← Back to admissions
          </Link>
        </p>
      </div>
    </div>
  );
};

export default ApplicationStatusPage;
