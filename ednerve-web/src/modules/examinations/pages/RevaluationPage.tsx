import { useState } from "react";
import { FileSearch, Plus } from "lucide-react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { examinationService } from "../api";
import type { RevaluationRequest } from "../api";
import "../../institution/institution.css";

import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin, isExamOfficer } from "../../../shared/lib/rbac";

import {
  Badge,
  Button,
  FormField,
  Input,
  Modal,
  Spinner,
} from "../../../shared/ui";

const STATUS_TONE: Record<string, "info" | "warning" | "success" | "danger"> = {
  requested: "warning",
  in_review: "info",
  completed: "success",
  rejected: "danger",
};

const RevaluationPage = () => {
  const { user } = useAuth();
  const qc = useQueryClient();
  const canManage = isInstitutionAdmin(user) || isExamOfficer(user);

  const [statusFilter, setStatusFilter] = useState<string>("");

  const revaluationsQuery = useQuery({
    queryKey: ["examinations", "revaluation", statusFilter],
    queryFn: () => examinationService.getRevaluations(statusFilter ? { status: statusFilter } : undefined),
  });

  const examsQuery = useQuery({
    queryKey: ["examinations", "list"],
    queryFn: () => examinationService.getExams(),
  });

  const [modalOpen, setModalOpen] = useState(false);
  const [examId, setExamId] = useState<number | null>(null);
  const [examDetailId, setExamDetailId] = useState<number | null>(null);
  const [studentProfileId, setStudentProfileId] = useState<number | null>(null);
  const [subjectId, setSubjectId] = useState<number | null>(null);
  const [reason, setReason] = useState("");
  const [fee, setFee] = useState("");
  const [formError, setFormError] = useState("");
  const [saving, setSaving] = useState(false);

  const detailQuery = useQuery({
    queryKey: ["examinations", "detail", examDetailId],
    queryFn: () => examinationService.getExam(examDetailId!),
    enabled: examDetailId !== null,
  });

  // Reuse results computation for the candidate roster (student list of the exam's programme).
  const resultsQuery = useQuery({
    queryKey: ["examinations", "results", examDetailId],
    queryFn: () => examinationService.getResults(examDetailId!),
    enabled: examDetailId !== null,
  });

  const submitRequest = async () => {
    setFormError("");
    if (!examId || !studentProfileId || !subjectId) {
      setFormError("Pick an examination, a candidate and a subject.");
      return;
    }
    if (reason.trim().length < 3) {
      setFormError("Reason is too short.");
      return;
    }
    setSaving(true);
    try {
      await examinationService.createRevaluation({
        exam_id: examId,
        student_profile_id: studentProfileId,
        subject_id: subjectId,
        reason: reason.trim(),
        fee_amount: fee.trim() === "" ? null : Number(fee),
      });
      await qc.invalidateQueries({ queryKey: ["examinations", "revaluation"] });
      toast.success("Revaluation request recorded.");
      setModalOpen(false);
      setReason("");
      setFee("");
      setStudentProfileId(null);
      setSubjectId(null);
    } catch (e: any) {
      setFormError(e?.response?.data?.message ?? "Unable to record the request.");
    } finally {
      setSaving(false);
    }
  };

  const updateStatus = async (request: RevaluationRequest, status: "in_review" | "completed" | "rejected") => {
    try {
      await examinationService.updateRevaluationStatus(request.id, status);
      await qc.invalidateQueries({ queryKey: ["examinations", "revaluation"] });
      toast.success(`Request moved to ${status}.`);
    } catch {
      toast.error("Unable to update the status.");
    }
  };

  const revaluations = revaluationsQuery.data?.revaluations ?? [];
  const detail = detailQuery.data;

  return (
    <>
      <div className="institution-directory-toolbar" style={{ gap: 10, flexWrap: "wrap" }}>
        <select className="filter-select" value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} aria-label="Filter by status">
          <option value="">All statuses</option>
          {["requested", "in_review", "completed", "rejected"].map((s) => <option key={s} value={s}>{s}</option>)}
        </select>
        {canManage && (
          <Button onClick={() => { setFormError(""); setExamDetailId(examId ?? null); setModalOpen(true); }}>
            <Plus size={16} /> Log request
          </Button>
        )}
      </div>

      <div className="institution-admin-panel">
        <h3>
          <FileSearch size={17} style={{ verticalAlign: "-3px" }} /> Revaluation requests
        </h3>
        {revaluationsQuery.isLoading ? (
          <Spinner size={20} />
        ) : revaluations.length === 0 ? (
          <p style={{ color: "var(--text-muted)" }}>No revaluation requests yet.</p>
        ) : (
          <table style={tableStyle}>
            <thead>
              <tr>
                <th style={th}>Candidate</th>
                <th style={th}>Exam</th>
                <th style={th}>Paper</th>
                <th style={th}>Reason / Fee</th>
                <th style={th}>Status</th>
                {canManage && <th style={th}>Action</th>}
              </tr>
            </thead>
            <tbody>
              {revaluations.map((r) => (
                <tr key={r.id}>
                  <td style={td}>
                    <strong>{r.student_profile?.user?.name ?? `#${r.student_profile_id}`}</strong>
                    <div className="institution-mono" style={{ fontSize: 11 }}>{r.student_profile?.application_number}</div>
                  </td>
                  <td style={td}>{r.exam?.title ?? r.exam_id}</td>
                  <td style={td}>{r.subject?.code ?? "—"}</td>
                  <td style={td}>
                    <div style={{ fontSize: 12 }}>{r.reason}</div>
                    {r.fee_amount != null && (
                      <div className="institution-mono" style={{ fontSize: 11 }}>Fee ₹{Number(r.fee_amount).toLocaleString("en-IN")}</div>
                    )}
                  </td>
                  <td style={td}><Badge tone={STATUS_TONE[r.status]}>{r.status}</Badge></td>
                  {canManage && (
                    <td style={td}>
                      <select
                        className="filter-select"
                        value=""
                        onChange={(e) => {
                          const next = e.target.value as "in_review" | "completed" | "rejected" | "";
                          if (next) void updateStatus(r, next);
                        }}
                        aria-label="Move status"
                      >
                        <option value="">Move to...</option>
                        {r.status !== "in_review" && <option value="in_review">In review</option>}
                        {r.status !== "completed" && <option value="completed">Completed</option>}
                        {r.status !== "rejected" && <option value="rejected">Rejected</option>}
                      </select>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      <Modal
        open={modalOpen}
        title="Log revaluation request"
        onClose={() => setModalOpen(false)}
        footer={
          <>
            <Button variant="secondary" onClick={() => setModalOpen(false)}>Cancel</Button>
            <Button onClick={submitRequest} disabled={saving}>{saving ? "Saving..." : "Save request"}</Button>
          </>
        }
      >
        {formError && <div className="institution-form-alert" role="alert">{formError}</div>}

        <FormField label="Examination" htmlFor="rev-exam" required>
          <select id="rev-exam" className="filter-select" value={examId ?? ""}
            onChange={(e) => { const v = e.target.value ? Number(e.target.value) : null; setExamId(v); setExamDetailId(v); setStudentProfileId(null); setSubjectId(null); }}>
            <option value="">Select examination...</option>
            {(examsQuery.data?.exams ?? []).map((exam) => (
              <option key={exam.id} value={exam.id}>{exam.title}</option>
            ))}
          </select>
        </FormField>

        {examDetailId !== null && (
          <>
            <FormField label="Candidate" htmlFor="rev-student" required>
              <select id="rev-student" className="filter-select" value={studentProfileId ?? ""}
                onChange={(e) => setStudentProfileId(e.target.value ? Number(e.target.value) : null)}>
                <option value="">Select candidate...</option>
                {(resultsQuery.data?.results ?? []).map((row) => (
                  <option key={row.student.id} value={row.student.id}>
                    {row.student.name} — {row.student.application_number}
                  </option>
                ))}
              </select>
            </FormField>

            <FormField label="Paper" htmlFor="rev-subject" required>
              <select id="rev-subject" className="filter-select" value={subjectId ?? ""}
                onChange={(e) => setSubjectId(e.target.value ? Number(e.target.value) : null)}>
                <option value="">Select paper...</option>
                {(detail?.subjects ?? []).map((entry) => (
                  <option key={entry.id} value={entry.subject_id}>
                    {entry.subject?.code} — {entry.subject?.name}
                  </option>
                ))}
              </select>
            </FormField>
          </>
        )}

        <FormField label="Reason" htmlFor="rev-reason" required>
          <textarea id="rev-reason" className="filter-select" style={{ width: "100%", minHeight: 60 }}
            value={reason} onChange={(e) => setReason(e.target.value)}
            placeholder="Suspected totaling error / valuation doubt..." />
        </FormField>

        <FormField label="Fee collected (₹, optional)" htmlFor="rev-fee">
          <Input id="rev-fee" type="number" step="0.01" min={0} value={fee}
            onChange={(e) => setFee(e.target.value)} placeholder="100" />
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

export default RevaluationPage;
