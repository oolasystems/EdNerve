import "../../attendance/attendance.css";
import "../examinations.css";
import { useMemo, useState } from "react";
import { useSearchParams } from "react-router-dom";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { ClipboardPen, Save } from "lucide-react";
import { toast } from "sonner";

import { examinationService } from "../api";
import type { MarksGridResponse } from "../api";

import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin, isExamOfficer } from "../../../shared/lib/rbac";

import { Badge, Button, Spinner } from "../../../shared/ui";
import { getApiErrorMessage } from "../../../shared/lib/api-error";

type GridNotes = "present" | "absent";

const MarksEntryPage = () => {
  const [searchParams] = useSearchParams();
  const { user } = useAuth();
  const qc = useQueryClient();

  const [examId, setExamId] = useState<number | null>(() => {
    const q = searchParams.get("exam");
    return q && Number.isFinite(Number(q)) ? Number(q) : null;
  });
  const [subjectEntryId, setSubjectEntryId] = useState<number | null>(null);
  const [componentId, setComponentId] = useState<number | null>(null);
  const [draft, setDraft] = useState<Record<number, { status: GridNotes; marks: string }>>({});
  const [saving, setSaving] = useState(false);

  const examsQuery = useQuery({
    queryKey: ["examinations", "list"],
    queryFn: () => examinationService.getExams(),
  });

  const examQuery = useQuery({
    queryKey: ["examinations", "detail", examId],
    queryFn: () => examinationService.getExam(examId!),
    enabled: examId !== null,
  });

  const marksQuery = useQuery({
    queryKey: ["examinations", "marks", examId, subjectEntryId],
    queryFn: () => examinationService.getMarksGrid(examId!, subjectEntryId!),
    enabled: examId !== null && subjectEntryId !== null,
  });

  const detail = examQuery.data;
  const isFrozen = detail?.exam?.status === "published";
  const canMark = (isInstitutionAdmin(user) || isExamOfficer(user)) && !isFrozen;
  const subjects = detail?.subjects ?? [];
  const selectedSubjectEntry = subjects.find((s) => s.id === subjectEntryId) ?? null;
  const components = selectedSubjectEntry?.components ?? [];
  const selectedComponent = components.find((c) => c.id === componentId) ?? null;
  const grid = marksQuery.data ?? null;

  const dirty = useMemo(() => Object.keys(draft).length > 0, [draft]);

  const openGrid = (entryId: number, componentForEntry: number | null) => {
    setSubjectEntryId(entryId);
    setComponentId(componentForEntry);
    setDraft({});
  };

  const setMark = (studentId: number, value: string) => {
    setDraft((prev) => ({
      ...prev,
      [studentId]: { status: "present", marks: value },
    }));
  };

  const setAbsent = (studentId: number) => {
    setDraft((prev) => ({
      ...prev,
      [studentId]: { status: "absent", marks: "" },
    }));
  };

  const resetToPresent = (studentId: number) => {
    setDraft((prev) => ({
      ...prev,
      [studentId]: { status: "present", marks: "" },
    }));
  };

  const getValue = (studentId: number, current: MarksGridResponse["students"][number] | undefined): { status: GridNotes; marks: string } => {
    if (draft[studentId]) return draft[studentId];
    const existing = current?.marks[String(componentId)];
    if (existing) {
      return { status: existing.status === "absent" ? "absent" : "present", marks: existing.marks_obtained != null ? String(existing.marks_obtained) : "" };
    }
    return { status: "present", marks: "" };
  };

  const save = async () => {
    if (!examId || !subjectEntryId || !componentId) return;
    setSaving(true);
    try {
      const records = Object.entries(draft).map(([id, value]) => ({
        student_profile_id: Number(id),
        status: value.status,
        marks_obtained: value.status === "absent" ? null : (value.marks.trim() === "" ? null : Number(value.marks)),
      }));
      // Present students must have marks
      const missing = records.filter((r) => r.status === "present" && r.marks_obtained === null);
      if (missing.length) {
        toast.error(`${missing.length} present student(s) have no marks entered.`);
        setSaving(false);
        return;
      }
      const result = await examinationService.saveMarks(examId, subjectEntryId, componentId, records);
      await qc.invalidateQueries({ queryKey: ["examinations", "marks", examId, subjectEntryId] });
      setDraft({});
      toast.success(`Marks saved (${result.saved} students).`);
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to save marks."));
    } finally {
      setSaving(false);
    }
  };

  return (
    <>
      <div className="institution-directory-toolbar marks-entry-toolbar">
        <label htmlFor="me-exam" className="institution-mono exam-field-label">Examination</label>
        <select
          id="me-exam"
          className="filter-select marks-entry-exam-select"
          value={examId ?? ""}
          onChange={(e) => { setExamId(e.target.value ? Number(e.target.value) : null); setSubjectEntryId(null); setComponentId(null); setDraft({}); }}
        >
          <option value="">Select examination...</option>
          {(examsQuery.data?.exams ?? []).map((exam) => (
            <option key={exam.id} value={exam.id}>
              {exam.title} ({exam.semester} sem · {exam.section}) — {exam.status}
            </option>
          ))}
        </select>

        {examId !== null && (
          <>
            <label htmlFor="me-subject" className="institution-mono exam-field-label">Subject</label>
            <select
              id="me-subject"
              className="filter-select"
              value={subjectEntryId ?? ""}
              onChange={(e) => {
                const next = e.target.value ? Number(e.target.value) : null;
                if (next === null) {
                  setSubjectEntryId(null);
                  setComponentId(null);
                  setDraft({});
                  return;
                }
                const entry = subjects.find((s) => s.id === next) ?? null;
                openGrid(next, entry?.components[0]?.id ?? null);
              }}
            >
              <option value="">Select subject...</option>
              {subjects.map((entry) => (
                <option key={entry.id} value={entry.id}>
                  {entry.subject?.code} — {entry.subject?.name}
                </option>
              ))}
            </select>
          </>
        )}
      </div>

      {examId !== null && selectedSubjectEntry && (
        <div className="institution-directory-toolbar marks-entry-component-toolbar">
          <span className="institution-mono exam-field-label marks-entry-component-label">Component</span>
          {components.map((component) => (
            <button
              key={component.id}
              type="button"
              className={`btn ${componentId === component.id ? "btn-primary" : "btn-secondary"}`}
              onClick={() => { setComponentId(component.id); setDraft({}); }}
            >
              {component.name} ({Number(component.max_marks)})
            </button>
          ))}
          {canMark && (
            <Button onClick={save} disabled={saving || !dirty} className="marks-entry-save-button">
              <Save size={15} /> {saving ? "Saving..." : dirty ? "Save changes" : "Saved"}
            </Button>
          )}
        </div>
      )}

      {marksQuery.isLoading && (
        <div className="dashboard-state"><Spinner size={22} /><p>Loading register...</p></div>
      )}

      {grid && selectedComponent && (
        <div className="institution-admin-panel marks-entry-panel">
          <h3>
            <ClipboardPen size={17} aria-hidden="true" className="exam-heading-icon" /> {selectedSubjectEntry?.subject?.code} · {selectedComponent.name}
            <span className="institution-mono marks-entry-component-meta">
              Max {Number(selectedComponent.max_marks)}
            </span>
          </h3>
          {isFrozen && (
            <p className="marks-entry-guidance">
              Results are published — marks are frozen.
            </p>
          )}
          {!canMark && !isFrozen && (
            <p className="marks-entry-readonly-note">Read-only — only administrators and examination officers may enter marks.</p>
          )}
          <table className="exam-data-table marks-entry-table">
            <thead>
              <tr>
                <th className="exam-data-table-heading">Student</th>
                <th className="exam-data-table-heading">Application #</th>
                <th className="exam-data-table-heading marks-entry-marks-heading">Marks</th>
                <th className="exam-data-table-heading exam-cell-centered">Status</th>
              </tr>
            </thead>
            <tbody>
              {grid.students.map((student) => {
                const value = getValue(student.student_profile_id, student);
                const isAbsent = value.status === "absent";
                return (
                  <tr key={student.student_profile_id}>
                    <td className="exam-data-table-cell">
                      <strong>{student.name}</strong>
                      <div className="institution-mono exam-record-meta">{student.email}</div>
                    </td>
                    <td className="exam-data-table-cell"><span className="institution-mono">{student.application_number ?? "—"}</span></td>
                    <td className="exam-data-table-cell">
                      <input
                        type="number"
                        className="filter-select marks-entry-score-input"
                        min={0}
                        max={Number(selectedComponent.max_marks)}
                        step="0.5"
                        disabled={!canMark || isAbsent}
                        value={isAbsent ? "" : value.marks}
                        placeholder={isAbsent ? "—" : "0"}
                        onChange={(e) => setMark(student.student_profile_id, e.target.value)}
                        aria-label={`Marks for ${student.name}`}
                      />
                    </td>
                    <td className="exam-data-table-cell exam-cell-centered">
                      {canMark ? (
                        <div className="attendance-segment" role="group" aria-label={`Status for ${student.name}`}>
                          <button type="button" className={`attendance-segment-btn attendance-segment-present${!isAbsent ? " attendance-segment-active" : ""}`}
                            onClick={() => resetToPresent(student.student_profile_id)} aria-pressed={!isAbsent}>P</button>
                          <button type="button" className={`attendance-segment-btn attendance-segment-absent${isAbsent ? " attendance-segment-active" : ""}`}
                            onClick={() => setAbsent(student.student_profile_id)} aria-pressed={isAbsent}>A</button>
                        </div>
                      ) : (
                        <Badge tone={isAbsent ? "danger" : "success"}>{isAbsent ? "absent" : "present"}</Badge>
                      )}
                    </td>
                  </tr>
                );
              })}
              {grid.students.length === 0 && (
                <tr><td colSpan={4} className="exam-data-table-cell">No students enrolled for this programme.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {examId === null && (
        <p className="exam-muted">Select an examination to enter marks.</p>
      )}
    </>
  );
};


export default MarksEntryPage;
