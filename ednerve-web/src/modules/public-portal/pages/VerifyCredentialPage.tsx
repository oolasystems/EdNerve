import { useParams, Link } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import apiClient from "../../../shared/lib/api-client";

import { Award, AlertTriangle, X, CheckCircle2, ShieldCheck } from "lucide-react";
import "../verify.css";


interface CredentialVerificationSubjectResult {
  subjectCode: string;
  percent: number;
  pass: boolean;
}

interface CredentialVerificationResponse {
  status: string;
  institution?: { name?: string | null } | null;
  credential_number: string;
  student?: { name?: string | null; application_number?: string | null } | null;
  programme?: string | null;
  academic_session?: string | null;
  results?: {
    overallGrade?: string | null;
    overallStatus?: string | null;
    totalPercentage?: number | null;
    examTitle?: string | null;
    subjectResults?: CredentialVerificationSubjectResult[];
  } | null;
  issued_at: string;
  signature_valid: boolean;
  revocation_reason?: string | null;
  revoked_at?: string | null;
}

const VerifyCredentialPage = () => {
  const { credentialNumber } = useParams<{ credentialNumber: string }>();

  const query = useQuery({
    queryKey: ["verify", credentialNumber],
    queryFn: async () => {
      const response = await apiClient.get(`/verify/credentials/${encodeURIComponent(credentialNumber ?? "")}`);
      return response.data as CredentialVerificationResponse;
    },
    enabled: Boolean(credentialNumber),
    retry: false,
  });

  const data = query.data;

  if (query.isLoading) {
    return (
      <div className="verify-page">
        <div className="verify-card">
          <ShieldCheck size={48} className="verify-spin" />
          <p>Verifying credential…</p>
        </div>
      </div>
    );
  }

  if (query.isError || !data) {
    return (
      <div className="verify-page">
        <div className="verify-card">
          <AlertTriangle size={48} color="#ef4444" />
          <h1>Credential not found</h1>
          <p>No credential with this number exists.</p>
        </div>
      </div>
    );
  }

  const tone = data.status === "valid" ? "good" : data.status === "revoked" || data.status === "tampered" ? "bad" : "neutral";
  const statusLabels: Record<string, string> = {
    valid: "VERIFIED",
    revoked: "REVOKED",
    expired: "EXPIRED",
    replaced: "REPLACED",
    tampered: "SIGNATURE INVALID",
    invalid: "INVALID",
  };
  const toneLabel = statusLabels[data.status] ?? "NOT VERIFIED";

  return (
    <div className="verify-page">
      <div className={`verify-card verify-${tone}`}>
        <div className="verify-header">
          <div>
            <Award size={28} />
            <span className="verify-school">{data.institution?.name}</span>
          </div>
          <div className={`verify-badge verify-badge-${tone}`}>
            {tone === "good" ? <CheckCircle2 size={18} /> : <X size={18} />}
            {toneLabel}
          </div>
        </div>

        <h1>Academic Credential</h1>
        <p className="verify-number">{data.credential_number}</p>

        {tone === "good" && (
          <>
            <dl className="verify-dl">
              <dt>Name</dt>
              <dd>{data.student?.name}</dd>
              <dt>Application number</dt>
              <dd>{data.student?.application_number ?? "—"}</dd>
              <dt>Programme</dt>
              <dd>{data.programme}</dd>
              <dt>Academic session</dt>
              <dd>{data.academic_session}</dd>
              <dt>Overall result</dt>
              <dd>
                <strong>{data.results?.overallGrade}</strong> ({data.results?.overallStatus}) · {data.results?.totalPercentage}%
              </dd>
              {data.results?.examTitle && (
                <>
                  <dt>Exam</dt>
                  <dd>{data.results.examTitle}</dd>
                </>
              )}
              <dt>Issued</dt>
              <dd>{new Date(data.issued_at).toLocaleDateString("en-IN", { year: "numeric", month: "long", day: "numeric" })}</dd>
            </dl>

            {Array.isArray(data.results?.subjectResults) && data.results.subjectResults.length > 0 && (
              <div className="verify-results">
                <h2>Subject results</h2>
                <table className="verify-table">
                  <thead>
                    <tr><th>Subject</th><th>%</th><th>Result</th></tr>
                  </thead>
                  <tbody>
                    {data.results.subjectResults.map((subject, idx) => (
                      <tr key={idx}>
                        <td>{subject.subjectCode}</td>
                        <td>{subject.percent}</td>
                        <td>{subject.pass ? "Pass" : "Fail"}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            <p className="verify-sig">
              Signature <strong>{data.signature_valid ? "valid" : "invalid"}</strong>. Issued by {data.institution?.name}.
            </p>
          </>
        )}

        {data.status !== "valid" && (
          <div className="verify-revoked" role="status">
            {data.status === "revoked" ? (
              <>
                <p><strong>This credential has been revoked.</strong></p>
                <p>Reason recorded: {data.revocation_reason ?? "No reason provided."}</p>
                {data.revoked_at && <p>Revoked on: {new Date(data.revoked_at).toLocaleDateString("en-IN", { year: "numeric", month: "long", day: "numeric" })}</p>}
              </>
            ) : (
              <p><strong>This credential could not be verified as current and authentic.</strong> Do not rely on it as proof of academic status. Contact the issuing institution for assistance.</p>
            )}
          </div>
        )}
      </div>

      <p className="verify-footer">
        OolaEdNerve verification · <Link to="/">Institution portal</Link>
      </p>
    </div>
  );
};

export default VerifyCredentialPage;
