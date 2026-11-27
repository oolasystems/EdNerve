import "../examinations.css";
import { useEffect, useState } from "react";
import { Award, Plus, Printer, Ban } from "lucide-react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import QRCode from "qrcode";

import { examinationService } from "../api";
import { credentialsService } from "../credentials-api";
import type { Credential } from "../credentials-api";
import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin, isExamOfficer } from "../../../shared/lib/rbac";
import { getApiErrorMessage } from "../../../shared/lib/api-error";
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
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to issue credentials."));
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
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to revoke."));
    }
  };

  const printCredential = (credential: Credential) => {
    setQrCred(credential);
  };

  return (
    <>
      <div className="institution-directory-toolbar exam-credentials-toolbar">
        <label className="institution-mono exam-credentials-filter-label" htmlFor="cred-exam">Exam</label>
        <select
          id="cred-exam"
          className="filter-select exam-credentials-exam-select"
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
          <Award size={17} className="exam-heading-icon" /> Issued credentials
        </h3>
        {credentialsQuery.isLoading ? (
          <div className="dashboard-state"><Spinner size={22} /><p>Loading credentials...</p></div>
        ) : credentials.length === 0 ? (
          <p className="exam-credentials-empty">No credentials yet. Publish the exam and issue from here.</p>
        ) : (
          <table className="exam-credentials-table">
            <thead>
              <tr>
                <th className="exam-credentials-table-heading">Credential #</th>
                <th className="exam-credentials-table-heading">Student</th>
                <th className="exam-credentials-table-heading">Exam</th>
                <th className="exam-credentials-table-heading">Type</th>
                <th className="exam-credentials-table-heading">Issued</th>
                <th className="exam-credentials-table-heading">Status</th>
                <th className="exam-credentials-table-heading"></th>
              </tr>
            </thead>
            <tbody>
              {credentials.map((credential) => (
                <tr key={credential.id}>
                  <td className="exam-credentials-table-cell"><span className="institution-mono">{credential.credential_number}</span></td>
                  <td className="exam-credentials-table-cell">
                    <strong>{credential.student_profile?.user?.name ?? "—"}</strong>
                    <div className="institution-mono exam-credentials-application-number">{credential.student_profile?.application_number}</div>
                  </td>
                  <td className="exam-credentials-table-cell">{credential.exam?.title ?? "—"}</td>
                  <td className="exam-credentials-table-cell">{credential.type}</td>
                  <td className="exam-credentials-table-cell">{String(credential.issued_at).slice(0, 10)}</td>
                  <td className="exam-credentials-table-cell"><Badge tone={STATUS_TONE[credential.status] ?? "info"}>{credential.status}</Badge></td>
                  <td className="exam-credentials-table-cell">
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
            <div className="exam-credentials-qr-content">
              <p className="institution-mono exam-credentials-qr-number">{qrCred.credential_number}</p>
              <p><strong>{qrCred.student_profile?.user?.name}</strong> · {qrCred.exam?.title}</p>
              {qrDataUrl && <img src={qrDataUrl} alt="Credential QR" />}
              <p className="exam-credentials-qr-help">
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


export default CredentialsPage;
