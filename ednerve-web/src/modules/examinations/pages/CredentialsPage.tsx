import { useEffect, useState } from "react";
import { Award, Plus, Printer, Ban } from "lucide-react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import QRCode from "qrcode";

import { examinationService } from "../api";
import { credentialsService } from "../credentials-api";
import type { Credential } from "../credentials-api";
import "../../institution/institution.css";

import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin, isExamOfficer } from "../../../shared/lib/rbac";

import {
  Badge,
  Button,
  Modal,
  FormField,
  Input,
  Spinner,
} from "../../../shared/ui";

const STATUS_TONE: Record<string, "success" | "warning" | "danger"> = {
  issued: "success",
  revoked: "danger",
  replaced: "warning",
};

const CredentialsPage = () => {
  const { user } = useAuth();
  const qc = useQueryClient();
  const canManage = isInstitutionAdmin(user) || isExamOfficer(user);

  const examsQuery = useQuery({
    queryKey: ["examinations", "list"],
    queryFn: () => examinationService.getExams({ status: "published" }),
  });

  const [examId, setExamId] = useState<number | "">("");

  const credentialsQuery = useQuery({
    queryKey: ["credentials", examId],
    queryFn: () =>
      credentialsService.getCredentials({
        exam_id: examId === "" ? undefined : Number(examId),
      }),
    placeholderData: (prev) => prev,
  });

  const credentials = credentialsQuery.data?.data ?? [];
  const publishedExams = examsQuery.data?.exams ?? [];

  const [issuing, setIssuing] = useState(false);
  const [qrCred, setQrCred] = useState<Credential | null>(null);
  const [qrDataUrl, setQrDataUrl] = useState("");
  const [revokeTarget, setRevokeTarget] = useState<Credential | null>(null);
  const [revokeReason, setRevokeReason] = useState("");

  useEffect(() => {
    if (!qrCred) return;
    const base = window.location.origin;
    const url = `${base}/verify/${encodeURIComponent(qrCred.credential_number)}`;
    QRCode.toDataURL(url, { width: 260, margin: 1 }).then(setQrDataUrl);
  }, [qrCred]);

  const issue = async () => {
    if (examId === "") {
      toast.error("Pick an exam first.");
      return;
    }
    setIssuing(true);
    try {
      const result = await credentialsService.issueCredentials(Number(examId));
      await qc.invalidateQueries({ queryKey: ["credentials"] });
      toast.success(result.message);
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? "Unable to issue credentials.");
    } finally {
      setIssuing(false);
    }
  };

  const revoke = async () => {
    if (!revokeTarget) return;
    if (revokeReason.trim().length < 3) {
      toast.error("Reason is too short.");
      return;
    }
    try {
      await credentialsService.revokeCredential(revokeTarget.id, revokeReason.trim());
      setRevokeTarget(null);
      setRevokeReason("");
      await qc.invalidateQueries({ queryKey: ["credentials"] });
      toast.success("Credential revoked.");
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? "Unable to revoke.");
    }
  };

  const printCredential = (credential: Credential) => {
    setQrCred(credential);
  };

  return (
    <>
      <div className="institution-directory-toolbar" style={{ gap: 10, flexWrap: "wrap" }}>
        <label className="institution-mono" htmlFor="cred-exam" style={{ fontSize: 12, color: "var(--text-secondary)", textTransform: "uppercase" }}>Exam</label>
        <select
          id="cred-exam"
          className="filter-select"
          style={{ minWidth: 300 }}
          value={examId}
          onChange={(e) => setExamId(e.target.value === "" ? "" : Number(e.target.value))}
          aria-label="Filter by exam"
        >
          <option value="">All published exams</option>
          {publishedExams.map((exam) => (
            <option key={exam.id} value={exam.id}>{exam.title}</option>
          ))}
        </select>
        {canManage && (
          <Button onClick={issue} disabled={issuing || examId === ""}>
            <Plus size={16} /> {issuing ? "Issuing..." : "Issue credentials"}
          </Button>
        )}
      </div>

      <div className="institution-admin-panel">
        <h3>
          <Award size={17} style={{ verticalAlign: "-3px" }} /> Issued credentials
        </h3>
        {credentialsQuery.isLoading ? (
          <div className="dashboard-state"><Spinner size={22} /><p>Loading credentials...</p></div>
        ) : credentials.length === 0 ? (
          <p style={{ color: "var(--text-muted)" }}>No credentials yet. Publish the exam and issue from here.</p>
        ) : (
          <table style={tableStyle}>
            <thead>
              <tr>
                <th style={th}>Credential #</th>
                <th style={th}>Student</th>
                <th style={th}>Exam</th>
                <th style={th}>Type</th>
                <th style={th}>Issued</th>
                <th style={th}>Status</th>
                <th style={th}></th>
              </tr>
            </thead>
            <tbody>
              {credentials.map((credential) => (
                <tr key={credential.id}>
                  <td style={td}><span className="institution-mono">{credential.credential_number}</span></td>
                  <td style={td}>
                    <strong>{credential.student_profile?.user?.name ?? "—"}</strong>
                    <div className="institution-mono" style={{ fontSize: 11 }}>{credential.student_profile?.application_number}</div>
                  </td>
                  <td style={td}>{credential.exam?.title ?? "—"}</td>
                  <td style={td}>{credential.type}</td>
                  <td style={td}>{String(credential.issued_at).slice(0, 10)}</td>
                  <td style={td}><Badge tone={STATUS_TONE[credential.status] ?? "info"}>{credential.status}</Badge></td>
                  <td style={td}>
                    <button type="button" className="timetable-icon-btn" onClick={() => printCredential(credential)} aria-label="Show QR / print">
                      <Printer size={15} />
                    </button>
                    {credential.status === "issued" && canManage && (
                      <button type="button" className="timetable-icon-btn" onClick={() => setRevokeTarget(credential)} aria-label="Revoke">
                        <Ban size={15} />
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {/* Print/QR modal */}
      <Modal
        open={qrCred !== null}
        title="Credential QR"
        onClose={() => setQrCred(null)}
        footer={
          <>
            <Button variant="secondary" onClick={() => setQrCred(null)}>Close</Button>
            <Button onClick={() => window.print()}><Printer size={15} /> Print</Button>
          </>
        }
      >
        {qrCred && (
          <>
            <div style={{ textAlign: "center" }}>
              <p className="institution-mono" style={{ fontSize: 13 }}>{qrCred.credential_number}</p>
              <p><strong>{qrCred.student_profile?.user?.name}</strong> · {qrCred.exam?.title}</p>
              {qrDataUrl && <img src={qrDataUrl} alt="Credential QR" />}
              <p style={{ fontSize: 13, color: "var(--text-muted)" }}>
                Scan to verify at <code>/verify/{qrCred.credential_number}</code>
              </p>
            </div>
          </>
        )}
      </Modal>

      {/* Revoke modal */}
      <Modal
        open={revokeTarget !== null}
        title="Revoke credential"
        onClose={() => setRevokeTarget(null)}
        footer={
          <>
            <Button variant="secondary" onClick={() => setRevokeTarget(null)}>Cancel</Button>
            <Button onClick={revoke}><Ban size={15} /> Revoke</Button>
          </>
        }
      >
        <p>
          This marks <strong>{revokeTarget?.credential_number}</strong> as revoked and records a reason.
          Verification will return "revoked" from then on.
        </p>
        <FormField label="Reason" htmlFor="revoke-reason" required>
          <Input id="revoke-reason" autoFocus value={revokeReason}
            onChange={(e) => setRevokeReason(e.target.value)} placeholder="Clerical error — reissuing" />
        </FormField>
      </Modal>
    </>
  );
};

const tableStyle: React.CSSProperties = {
  width: "100%",
  borderCollapse: "collapse",
  fontSize: "var(--font-size-sm)",
};
const th: React.CSSProperties = {
  textAlign: "left",
  padding: "8px 10px",
  borderBottom: "1px solid var(--border)",
  color: "var(--text-muted)",
  fontSize: "var(--font-size-xs)",
  textTransform: "uppercase",
  letterSpacing: "0.05em",
};
const td: React.CSSProperties = {
  padding: "10px",
  borderBottom: "1px solid var(--border)",
  verticalAlign: "middle",
};

export default CredentialsPage;
