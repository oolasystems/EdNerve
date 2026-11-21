import { useMemo, useState } from "react";
import { useNavigate, useParams, Link } from "react-router-dom";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import {
  CalendarClock,
  ClipboardPen,
  Printer,
  Plus,
  Trash2,
  Users,
  BadgeCheck,
  ArrowLeft,
} from "lucide-react";
import { toast } from "sonner";

import { examinationService } from "../api";
import type { ExamSubjectEntry } from "../api";
import "../../institution/institution.css";

import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin, isExamOfficer } from "../../../shared/lib/rbac";
import { academicService } from "../../academic/api";
import { institutionService } from "../../institution/api";

import {
  Badge,
  Button,
  FormField,
  Input,
  Modal,
  Spinner,
} from "../../../shared/ui";

const STAFF_ROLES = ["faculty", "hod", "dean", "registrar", "examination_officer", "institution_admin"];

const emptySubjectForm = {
  subject_id: "" as string | number,
  exam_date: "",
  start_time: "10:00",
  end_time: "13:00",
  venue: "",
  pass_percent: 40,
  components: [
    { name: "Internal", max_marks: 20 },
    { name: "Theory", max_marks: 80 },
  ] as Array<{ name: string; max_marks: number }>,
};

const ExamDetailPage = () => {
  const { examId } = useParams<{ examId: string }>();
  const id = Number(examId);
  const { user } = useAuth();
  const qc = useQueryClient();
  const navigate = useNavigate();
  const canManage = isInstitutionAdmin(user) || isExamOfficer(user);

  const examQuery = useQuery({
    queryKey: ["examinations", "detail", id],
    queryFn: () => examinationService.getExam(id),
    enabled: Number.isFinite(id),
  });

  const subjectsQuery = useQuery({
    queryKey: ["academic", "subjects"],
    queryFn: () => academicService.getSubjects(),
  });

  const usersQuery = useQuery({
    queryKey: ["institution", "users", "exam-detail"],
    queryFn: () => institutionService.getAllUsers(),
    enabled: canManage,
  });

  const detail = examQuery.data;
  const exam = detail?.exam;
  const isScheduled = exam?.status === "scheduled";
  const subjects = detail?.subjects ?? [];

  const staffOptions = useMemo(
    () =>
      (usersQuery.data ?? []).filter((u) =>
        u.active_role_assignments?.some((a) => STAFF_ROLES.includes(a.role?.code ?? ""))
      ),
    [usersQuery.data]
  );

  const availableSubjects = useMemo(() => {
    const used = new Set(subjects.map((s) => s.subject_id));
    return (subjectsQuery.data?.subjects ?? []).filter((s) => !used.has(s.id));
  }, [subjectsQuery.data, subjects]);

  /* ---------- subject add/edit modal ---------- */
  const [subjectModal, setSubjectModal] = useState(false);
  const [subjectForm, setSubjectForm] = useState(emptySubjectForm);
  const [subjectError, setSubjectError] = useState("");
  const [savingSubject, setSavingSubject] = useState(false);

  const openAddSubject = () => {
    setSubjectError("");
    setSubjectForm(emptySubjectForm);
    setSubjectModal(true);
  };

  const saveSubject = async () => {
    setSubjectError("");
    if (subjectForm.subject_id === "") { setSubjectError("Select a subject."); return; }
    if (!subjectForm.exam_date) { setSubjectError("Pick a date."); return; }
    if (subjectForm.components.length === 0 || subjectForm.components.some((c) => !c.name.trim() || Number(c.max_marks) <= 0)) {
      setSubjectError("Every component needs a name and a positive max mark.");
      return;
    }
    setSavingSubject(true);
    try {
      await examinationService.addSubject(id, {
        subject_id: Number(subjectForm.subject_id),
        exam_date: subjectForm.exam_date,
        start_time: subjectForm.start_time || undefined,
        end_time: subjectForm.end_time || undefined,
        venue: subjectForm.venue.trim() || undefined,
        pass_percent: Number(subjectForm.pass_percent),
        components: subjectForm.components.map((c, i) => ({
          name: c.name.trim(),
          max_marks: Number(c.max_marks),
          position: i + 1,
        })),
      });
      await qc.invalidateQueries({ queryKey: ["examinations", "detail", id] });
      toast.success("Subject added.");
      setSubjectModal(false);
    } catch (e: any) {
      setSubjectError(e?.response?.data?.message ?? "Unable to add the subject.");
    } finally {
      setSavingSubject(false);
    }
  };

  const removeSubject = async (entry: ExamSubjectEntry) => {
    if (!window.confirm(`Remove ${entry.subject?.code} from this exam?`)) return;
    try {
      await examinationService.deleteSubject(id, entry.id);
      await qc.invalidateQueries({ queryKey: ["examinations", "detail", id] });
      toast.success("Subject removed.");
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? "Unable to remove the subject.");
    }
  };

  /* ---------- publish ---------- */
  const [publishing, setPublishing] = useState(false);
  const publishResults = async () => {
    if (!window.confirm("Publish results? This freezes the snapshot; marks can no longer change.")) return;
    setPublishing(true);
    try {
      await examinationService.publish(id);
      await qc.invalidateQueries({ queryKey: ["examinations", "detail", id] });
      toast.success("Results published.");
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? "Unable to publish results.");
    } finally {
      setPublishing(false);
    }
  };

  /* ---------- invigilation ---------- */
  const [dutyStaff, setDutyStaff] = useState("");
  const [dutySubjectEntry, setDutySubjectEntry] = useState("");
  const [dutyType, setDutyType] = useState<"invigilator" | "chief">("invigilator");
  const assignInvigilator = async () => {
    if (!dutyStaff || !dutySubjectEntry) return;
    try {
      await examinationService.assignInvigilator(id, Number(dutySubjectEntry), {
        user_id: Number(dutyStaff),
        duty: dutyType,
      });
      await qc.invalidateQueries({ queryKey: ["examinations", "detail", id] });
      setDutyStaff("");
      toast.success("Invigilator assigned.");
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? "Unable to assign invigilator.");
    }
  };
  const removeInvigilator = async (invigilationId: number) => {
    try {
      await examinationService.removeInvigilator(id, invigilationId);
      await qc.invalidateQueries({ queryKey: ["examinations", "detail", id] });
    } catch {
      toast.error("Unable to remove the invigilator.");
    }
  };

  if (examQuery.isLoading) {
    return <div className="dashboard-state"><Spinner size={22} /><p>Loading examination...</p></div>;
  }
  if (!exam) {
    return <p>Examination not found.</p>;
  }

  const statusTone = exam.status === "published" ? "success" : exam.status === "completed" ? "warning" : "info";

  return (
    <>
      <div className="institution-directory-toolbar" style={{ justifyContent: "space-between", flexWrap: "wrap", gap: 10 }}>
        <button type="button" className="btn btn-ghost" onClick={() => navigate("/institution/examinations")}>
          <ArrowLeft size={15} /> Back to examinations
        </button>
        <div style={{ display: "flex", gap: 10, alignItems: "center" }}>
          <Badge tone={statusTone}>{exam.status}</Badge>
          <Link className="btn btn-ghost" to={`/institution/examinations/marks?exam=${exam.id}`}>
            <ClipboardPen size={14} /> Marks
          </Link>
          <Link className="btn btn-ghost" to={`/institution/examinations/results?exam=${exam.id}`}>
            Results
          </Link>
          <Link className="btn btn-ghost" to={`/institution/examinations/hall-tickets?exam=${exam.id}`}>
            <Printer size={14} /> Hall tickets
          </Link>
          {canManage && exam.status !== "published" && (
            <Button onClick={publishResults} disabled={publishing}>
              <BadgeCheck size={15} /> {publishing ? "Publishing..." : "Publish results"}
            </Button>
          )}
        </div>
      </div>

      <div className="institution-admin-panel" style={{ marginBottom: "var(--space-4)" }}>
        <h3>{exam.title}</h3>
        <div className="institution-mono" style={{ fontSize: 12, color: "var(--text-secondary)", marginBottom: 8 }}>
          {exam.programme?.code} · Semester {exam.semester} · Section {exam.section}
          {exam.academic_session ? ` · ${exam.academic_session.code}` : ""}
        </div>
        {exam.instructions && (
          <div style={{ background: "var(--surface-secondary)", borderRadius: "var(--radius-md)", padding: "8px 12px", fontSize: 13, color: "var(--text-secondary)" }}>
            {exam.instructions}
          </div>
        )}
        <div style={{ display: "flex", gap: 14, marginTop: 10, fontSize: 12, color: "var(--text-muted)" }}>
          <span><strong>{detail?.marks_state.students_with_marks ?? 0}</strong> / {detail?.marks_state.total_students ?? 0} students marked</span>
          <span><strong>{subjects.length}</strong> subjects scheduled</span>
          <span><strong>{(detail?.marks_state.components ?? 0)}</strong> marking components</span>
        </div>
      </div>

      <div className="institution-two-column">
        {/* Schedule */}
        <div className="institution-admin-panel">
          <h3>
            <CalendarClock size={17} style={{ verticalAlign: "-3px" }} /> Schedule & components
            {canManage && isScheduled && (
              <Button style={{ float: "right" }} onClick={openAddSubject}>
                <Plus size={14} /> Add subject
              </Button>
            )}
          </h3>
          {subjects.length === 0 ? (
            <p style={{ color: "var(--text-muted)" }}>
              No subjects yet. Add the first subject and its components.
            </p>
          ) : (
            <table style={tableStyle}>
              <thead>
                <tr>
                  <th style={th}>Paper</th>
                  <th style={th}>Date / Time</th>
                  <th style={th}>Components</th>
                  <th style={th}>Total</th>
                  <th style={th}>Pass %</th>
                  {canManage && isScheduled && <th style={th}></th>}
                </tr>
              </thead>
              <tbody>
                {subjects.map((entry) => (
                  <tr key={entry.id}>
                    <td style={td}>
                      <strong>{entry.subject?.code}</strong>
                      <div className="institution-mono" style={{ fontSize: 11 }}>{entry.subject?.name}</div>
                    </td>
                    <td style={td}>
                      {String(entry.exam_date).slice(0, 10)}
                      <div className="institution-mono" style={{ fontSize: 11 }}>
                        {entry.start_time?.slice(0, 5)}–{entry.end_time?.slice(0, 5)} · {entry.venue ?? "—"}
                      </div>
                    </td>
                    <td style={td}>
                      {entry.components.map((c) => (
                        <div key={c.id} className="institution-mono" style={{ fontSize: 12 }}>
                          {c.name} — {Number(c.max_marks)}
                        </div>
                      ))}
                    </td>
                    <td style={td}>{Number(entry.total_max)}</td>
                    <td style={td}>{entry.pass_percent}%</td>
                    {canManage && isScheduled && (
                      <td style={td}>
                        <button type="button" className="timetable-icon-btn" onClick={() => void removeSubject(entry)} aria-label="Remove subject">
                          <Trash2 size={13} />
                        </button>
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>

        {/* Invigilation */}
        <div className="institution-admin-panel">
          <h3>
            <Users size={17} style={{ verticalAlign: "-3px" }} /> Invigilation duties
          </h3>
          <div className="institution-form-grid" style={{ marginBottom: 10 }}>
            <FormField label="Exam slot" htmlFor="inv-subject">
              <select id="inv-subject" className="filter-select" value={dutySubjectEntry}
                onChange={(e) => setDutySubjectEntry(e.target.value)}>
                <option value="">Select slot...</option>
                {subjects.map((entry) => (
                  <option key={entry.id} value={entry.id}>
                    {entry.subject?.code} · {String(entry.exam_date).slice(0, 10)}
                  </option>
                ))}
              </select>
            </FormField>
            <FormField label="Staff" htmlFor="inv-user">
              <select id="inv-user" className="filter-select" value={dutyStaff}
                onChange={(e) => setDutyStaff(e.target.value)}>
                <option value="">Select staff...</option>
                {staffOptions.map((u) => (
                  <option key={u.id} value={u.id}>{u.name}</option>
                ))}
              </select>
            </FormField>
          </div>

          <div style={{ display: "flex", gap: 8, marginBottom: 12 }}>
            <select className="filter-select" value={dutyType}
              onChange={(e) => setDutyType(e.target.value as "invigilator" | "chief")}
              aria-label="Duty type">
              <option value="invigilator">Invigilator</option>
              <option value="chief">Chief invigilator</option>
            </select>
            <Button variant="secondary" onClick={assignInvigilator} disabled={!dutyStaff || !dutySubjectEntry}>
              Assign
            </Button>
          </div>

          {detail?.invigilations.length ? (
            <table style={tableStyle}>
              <thead>
                <tr>
                  <th style={th}>Slot</th>
                  <th style={th}>Staff</th>
                  <th style={th}>Duty</th>
                  {canManage && <th style={th}></th>}
                </tr>
              </thead>
              <tbody>
                {detail.invigilations.map((duty) => (
                  <tr key={duty.id}>
                    <td style={td}>{duty.subject_code ?? "—"}</td>
                    <td style={td}>{duty.user?.name ?? "—"}</td>
                    <td style={td}><Badge tone={duty.duty === "chief" ? "warning" : "info"}>{duty.duty}</Badge></td>
                    {canManage && (
                      <td style={td}>
                        <button type="button" className="timetable-icon-btn" onClick={() => void removeInvigilator(duty.id)} aria-label="Remove duty">
                          <Trash2 size={13} />
                        </button>
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          ) : (
            <p style={{ color: "var(--text-muted)" }}>No hall duties assigned yet.</p>
          )}
        </div>
      </div>

      {/* Add subject modal */}
      <Modal
        open={subjectModal}
        title="Add subject to examination"
        onClose={() => setSubjectModal(false)}
        footer={
          <>
            <Button variant="secondary" onClick={() => setSubjectModal(false)}>Cancel</Button>
            <Button onClick={saveSubject} disabled={savingSubject}>{savingSubject ? "Adding..." : "Add subject"}</Button>
          </>
        }
      >
        {subjectError && <div className="institution-form-alert" role="alert">{subjectError}</div>}

        <FormField label="Subject" htmlFor="subj-subject" required>
          <select id="subj-subject" className="filter-select" value={subjectForm.subject_id}
            onChange={(e) => setSubjectForm((c) => ({ ...c, subject_id: e.target.value }))}>
            <option value="">Select subject...</option>
            {availableSubjects.map((s) => (
              <option key={s.id} value={s.id}>{s.code} — {s.name}</option>
            ))}
          </select>
        </FormField>

        <div className="institution-form-grid">
          <FormField label="Exam date" htmlFor="subj-date" required>
            <Input id="subj-date" type="date" value={subjectForm.exam_date}
              onChange={(e) => setSubjectForm((c) => ({ ...c, exam_date: e.target.value }))} />
          </FormField>
          <FormField label="Venue" htmlFor="subj-venue">
            <Input id="subj-venue" placeholder="FET-201" value={subjectForm.venue}
              onChange={(e) => setSubjectForm((c) => ({ ...c, venue: e.target.value }))} />
          </FormField>
        </div>

        <div className="institution-form-grid">
          <FormField label="Start" htmlFor="subj-start">
            <Input id="subj-start" type="time" value={subjectForm.start_time}
              onChange={(e) => setSubjectForm((c) => ({ ...c, start_time: e.target.value }))} />
          </FormField>
          <FormField label="End" htmlFor="subj-end">
            <Input id="subj-end" type="time" value={subjectForm.end_time}
              onChange={(e) => setSubjectForm((c) => ({ ...c, end_time: e.target.value }))} />
          </FormField>
        </div>

        <FormField label="Pass %" htmlFor="subj-pass">
          <Input id="subj-pass" type="number" step="0.5" min={0} max={100} value={subjectForm.pass_percent}
            onChange={(e) => setSubjectForm((c) => ({ ...c, pass_percent: Number(e.target.value) }))} />
        </FormField>

        <fieldset style={{ border: "1px solid var(--border)", borderRadius: "var(--radius-md)", padding: "8px 12px", marginTop: 8 }}>
          <legend style={{ fontSize: 12, color: "var(--text-secondary)", padding: "0 6px" }}>Marking components</legend>
          {subjectForm.components.map((component, index) => (
            <div key={index} className="institution-form-grid" style={{ alignItems: "end", marginBottom: 8 }}>
              <FormField label={`Name`} htmlFor={`comp-name-${index}`}>
                <Input id={`comp-name-${index}`} value={component.name}
                  onChange={(e) => setSubjectForm((c) => ({
                    ...c,
                    components: c.components.map((x, i) => (i === index ? { ...x, name: e.target.value } : x)),
                  }))} />
              </FormField>
              <FormField label="Max marks" htmlFor={`comp-max-${index}`}>
                <Input id={`comp-max-${index}`} type="number" min={1} value={component.max_marks}
                  onChange={(e) => setSubjectForm((c) => ({
                    ...c,
                    components: c.components.map((x, i) => (i === index ? { ...x, max_marks: Number(e.target.value) } : x)),
                  }))} />
              </FormField>
              <button
                type="button"
                className="timetable-icon-btn"
                aria-label="Remove component"
                disabled={subjectForm.components.length <= 1}
                onClick={() => setSubjectForm((c) => ({ ...c, components: c.components.filter((_, i) => i !== index) }))}
              >
                <Trash2 size={14} />
              </button>
            </div>
          ))}
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
            <Button variant="secondary" onClick={() => setSubjectForm((c) => ({ ...c, components: [...c.components, { name: "", max_marks: 10 }] }))}>
              <Plus size={14} /> Add component
            </Button>
            <span className="institution-mono" style={{ fontSize: 13 }}>
              Total: {subjectForm.components.reduce((sum, c) => sum + (Number(c.max_marks) || 0), 0)}
            </span>
          </div>
        </fieldset>
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

export default ExamDetailPage;
