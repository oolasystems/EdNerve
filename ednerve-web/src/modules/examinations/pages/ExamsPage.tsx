import { useState } from "react";
import { GraduationCap, Plus, ArrowRight, Trash2 } from "lucide-react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { Link } from "react-router-dom";

import { examinationService } from "../api";
import type { Exam, ExamKind } from "../api";
import "../../institution/institution.css";

import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin, isExamOfficer } from "../../../shared/lib/rbac";
import { academicService } from "../../academic/api";
import { getApiErrorMessage } from "../../../shared/lib/api-error";
import {
  Badge,
  Button,
  FormField,
  Input,
  Modal,
  Spinner,
} from "../../../shared/ui";

const KIND_LABEL: Record<ExamKind, string> = {
  class_test: "Class test",
  mid_sem: "Mid-semester",
  end_sem: "End-semester",
  supplementary: "Supplementary",
};

const STATUS_TONE: Record<string, "info" | "warning" | "success"> = {
  scheduled: "info",
  completed: "warning",
  published: "success",
};

const ExamsPage = () => {
  const { user } = useAuth();
  const qc = useQueryClient();
  const canManage = isInstitutionAdmin(user) || isExamOfficer(user);

  const examsQuery = useQuery({
    queryKey: ["examinations", "list"],
    queryFn: () => examinationService.getExams(),
  });

  const programmesQuery = useQuery({
    queryKey: ["academic", "programmes"],
    queryFn: () => academicService.getProgrammes(),
  });

  const sessionsQuery = useQuery({
    queryKey: ["academic", "sessions"],
    queryFn: () => academicService.getSessions(),
  });

  const [modalOpen, setModalOpen] = useState(false);
  const [form, setForm] = useState({
    title: "",
    kind: "end_sem" as ExamKind,
    academic_session_id: "" as string | number,
    programme_id: "" as string | number,
    semester: 3,
    section: "A",
    instructions: "",
  });
  const [error, setError] = useState("");
  const [saving, setSaving] = useState(false);

  const createExam = async () => {
    setError("");
    if (!form.title.trim()) { setError("Title is required."); return; }
    if (form.programme_id === "") { setError("Select a programme."); return; }
    setSaving(true);
    try {
      await examinationService.createExam({
        title: form.title.trim(),
        kind: form.kind,
        academic_session_id: form.academic_session_id === "" ? null : Number(form.academic_session_id),
        programme_id: Number(form.programme_id),
        semester: Number(form.semester),
        section: form.section.trim() || "A",
        instructions: form.instructions.trim() || undefined,
      });
      await qc.invalidateQueries({ queryKey: ["examinations"] });
      toast.success("Examination created.");
      setModalOpen(false);
      setForm({ title: "", kind: "end_sem", academic_session_id: "", programme_id: "", semester: 3, section: "A", instructions: "" });
    } catch (e) {
      setError(getApiErrorMessage(e, "Unable to create the examination."));
    } finally {
      setSaving(false);
    }
  };

  const deleteExam = async (exam: Exam) => {
    if (!window.confirm(`Delete "${exam.title}"? This works only for scheduled exams with no marks.`)) {
      return;
    }
    try {
      await examinationService.deleteExam(exam.id);
      await qc.invalidateQueries({ queryKey: ["examinations"] });
      toast.success("Examination deleted.");
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to delete the examination."));
    }
  };

  if (examsQuery.isLoading) {
    return (
      <div className="dashboard-state"><Spinner size={22} /><p>Loading examinations...</p></div>
    );
  }

  const exams = examsQuery.data?.exams ?? [];

  return (
    <>
      <div className="institution-directory-toolbar">
        {canManage && (
          <Button onClick={() => { setError(""); setModalOpen(true); }}>
            <Plus size={16} /> New Examination
          </Button>
        )}
      </div>

      <div className="institution-admin-panel">
        <h3>
          <GraduationCap size={17} style={{ verticalAlign: "-3px" }} /> Examinations
        </h3>
        {exams.length === 0 ? (
          <p style={{ color: "var(--text-muted)" }}>
            No examinations configured yet. Create one, add subjects, enter marks and publish results.
          </p>
        ) : (
          <table style={tableStyle}>
            <thead>
              <tr>
                <th style={th}>Examination</th>
                <th style={th}>Semester</th>
                <th style={th}>Programme</th>
                <th style={th}>Subjects</th>
                <th style={th}>Status</th>
                {canManage && <th style={th}></th>}
                <th style={th}></th>
              </tr>
            </thead>
            <tbody>
              {exams.map((exam) => (
                <tr key={exam.id}>
                  <td style={td}>
                    <strong>{exam.title}</strong>
                    <div className="institution-mono" style={{ fontSize: 11 }}>
                      {KIND_LABEL[exam.kind]}{exam.section ? ` · Section ${exam.section}` : ""}
                    </div>
                  </td>
                  <td style={td}>{exam.semester}</td>
                  <td style={td}>{exam.programme?.code ?? "—"}</td>
                  <td style={td}>{exam.subjects_count ?? 0}</td>
                  <td style={td}><Badge tone={STATUS_TONE[exam.status]}>{exam.status}</Badge></td>
                  {canManage && (
                    <td style={td}>
                      <button type="button" className="timetable-icon-btn" onClick={() => void deleteExam(exam)} aria-label="Delete exam">
                        <Trash2 size={13} />
                      </button>
                    </td>
                  )}
                  <td style={td}>
                    <Link to={`/institution/examinations/${exam.id}`} className="btn btn-ghost" style={{ whiteSpace: "nowrap" }}>
                      Open <ArrowRight size={14} />
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      <Modal
        open={modalOpen}
        title="New Examination"
        onClose={() => setModalOpen(false)}
        footer={
          <>
            <Button variant="secondary" onClick={() => setModalOpen(false)}>Cancel</Button>
            <Button onClick={createExam} disabled={saving}>{saving ? "Creating..." : "Create"}</Button>
          </>
        }
      >
        {error && <div className="institution-form-alert" role="alert">{error}</div>}

        <FormField label="Title" htmlFor="exam-title" required>
          <Input id="exam-title" autoFocus value={form.title}
            onChange={(e) => setForm((c) => ({ ...c, title: e.target.value }))}
            placeholder="BTCSE End-Semester Examination — December 2026" />
        </FormField>

        <div className="institution-form-grid">
          <FormField label="Kind" htmlFor="exam-kind">
            <select id="exam-kind" className="filter-select" value={form.kind}
              onChange={(e) => setForm((c) => ({ ...c, kind: e.target.value as ExamKind }))}>
              {Object.entries(KIND_LABEL).map(([value, label]) => (
                <option key={value} value={value}>{label}</option>
              ))}
            </select>
          </FormField>
          <FormField label="Session" htmlFor="exam-session">
            <select id="exam-session" className="filter-select" value={form.academic_session_id}
              onChange={(e) => setForm((c) => ({ ...c, academic_session_id: e.target.value }))}>
              <option value="">None</option>
              {(sessionsQuery.data?.sessions ?? []).map((s) => (
                <option key={s.id} value={s.id}>{s.code} — {s.name}</option>
              ))}
            </select>
          </FormField>
        </div>

        <div className="institution-form-grid">
          <FormField label="Programme" htmlFor="exam-programme" required>
            <select id="exam-programme" className="filter-select" value={form.programme_id}
              onChange={(e) => setForm((c) => ({ ...c, programme_id: e.target.value }))}>
              <option value="">Select...</option>
              {(programmesQuery.data?.programmes ?? []).map((p) => (
                <option key={p.id} value={p.id}>{p.code} — {p.name}</option>
              ))}
            </select>
          </FormField>
          <FormField label="Semester" htmlFor="exam-semester">
            <select id="exam-semester" className="filter-select" value={form.semester}
              onChange={(e) => setForm((c) => ({ ...c, semester: Number(e.target.value) }))}>
              {[1,2,3,4,5,6,7,8].map((n) => <option key={n} value={n}>Semester {n}</option>)}
            </select>
          </FormField>
        </div>

        <FormField label="Section" htmlFor="exam-section">
          <Input id="exam-section" maxLength={20} value={form.section}
            onChange={(e) => setForm((c) => ({ ...c, section: e.target.value.toUpperCase() }))} />
        </FormField>

        <FormField label="Instructions for candidates" htmlFor="exam-instructions">
          <textarea id="exam-instructions" className="filter-select" style={{ minHeight: 60, width: "100%" }}
            value={form.instructions}
            onChange={(e) => setForm((c) => ({ ...c, instructions: e.target.value }))}
            placeholder="Hall ticket and photo ID required..." />
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

export default ExamsPage;
