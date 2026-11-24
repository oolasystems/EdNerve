import { useMemo, useState } from "react";
import {
  AlertTriangle,
  CalendarClock,
  CheckCircle2,
  DoorOpen,
  ListChecks,
  ShieldCheck,
  Users,
  Ban,
} from "lucide-react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { examinationService } from "../api";
import type { Exam, ExamSubjectEntry } from "../api";
import { examSetupService } from "../setup-api";
import type { Conflict, EligibilityRow } from "../setup-api";
import "../../institution/institution.css";

import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin, isExamOfficer } from "../../../shared/lib/rbac";
import { getApiErrorMessage } from "../../../shared/lib/api-error";

import {
  Badge,
  Button,
  FormField,
  Input,
  Modal,
  Spinner,
} from "../../../shared/ui";

const tableStyle: React.CSSProperties = { width: "100%", borderCollapse: "collapse", fontSize: "var(--font-size-sm)" };
const th: React.CSSProperties = { textAlign: "left", padding: "8px 10px", borderBottom: "1px solid var(--border)", color: "var(--text-muted)", fontSize: "var(--font-size-xs)", textTransform: "uppercase", letterSpacing: "0.05em" };
const td: React.CSSProperties = { padding: "10px", borderBottom: "1px solid var(--border)", verticalAlign: "middle" };

const STATUS_TONE: Record<string, "success" | "warning" | "danger"> = {
  eligible: "success",
  overridden: "warning",
  short: "danger",
};

const EMPTY_SESSIONS: Awaited<ReturnType<typeof examSetupService.listSessions>> = [];
const EMPTY_CENTRES: Awaited<ReturnType<typeof examSetupService.listCentres>> = [];

const ExamSetupPage = () => {
  const { user } = useAuth();
  const qc = useQueryClient();
  const canManage = isInstitutionAdmin(user) || isExamOfficer(user);

  const examsQuery = useQuery({
    queryKey: ["examinations", "list"],
    queryFn: () => examinationService.getExams(),
  });
  const exams = examsQuery.data?.exams ?? [];

  const [examId, setExamId] = useState<number | "">("");
  const selectedExam: Exam | undefined = exams.find((e) => e.id === examId);

  const detailQuery = useQuery({
    queryKey: ["examinations", "detail", examId],
    queryFn: () => examinationService.getExam(Number(examId)),
    enabled: examId !== "",
  });

  const sessionsQuery = useQuery({ queryKey: ["exam-infra", "sessions"], queryFn: () => examSetupService.listSessions() });
  const centresQuery = useQuery({ queryKey: ["exam-infra", "centres"], queryFn: () => examSetupService.listCentres() });

  const sessions = sessionsQuery.data ?? EMPTY_SESSIONS;
  const centres = centresQuery.data ?? EMPTY_CENTRES;
  const rooms = useMemo(() => centres.flatMap((c) => (c.rooms ?? []).map((r) => ({ ...r, centre_code: c.code }))), [centres]);
  const roomById = useMemo(() => new Map(rooms.map((r) => [r.id, r])), [rooms]);
  const sessionById = useMemo(() => new Map(sessions.map((s) => [s.id, s])), [sessions]);

  const entries: ExamSubjectEntry[] = detailQuery.data?.subjects ?? [];

  // --- assign modal ---
  const [assignEntry, setAssignEntry] = useState<ExamSubjectEntry | null>(null);
  const [assignSession, setAssignSession] = useState<string>("");
  const [assignRoom, setAssignRoom] = useState<string>("");
  const [assignThreshold, setAssignThreshold] = useState<string>("");
  const [assignInstructions, setAssignInstructions] = useState<string>("");

  const openAssign = (entry: ExamSubjectEntry) => {
    setAssignEntry(entry);
    setAssignSession(entry.exam_session_id?.toString() ?? "");
    setAssignRoom(entry.exam_room_id?.toString() ?? "");
    setAssignThreshold(entry.eligibility_threshold?.toString() ?? "");
    setAssignInstructions(entry.instructions ?? "");
  };

  const saveAssign = async () => {
    if (!assignEntry || examId === "") return;
    try {
      await examSetupService.assignSchedule(Number(examId), assignEntry.id, {
        exam_session_id: assignSession === "" ? null : Number(assignSession),
        exam_room_id: assignRoom === "" ? null : Number(assignRoom),
        eligibility_threshold: assignThreshold === "" ? null : Number(assignThreshold),
        instructions: assignInstructions.trim() || null,
      });
      await qc.invalidateQueries({ queryKey: ["examinations", "detail", examId] });
      setAssignEntry(null);
      toast.success("Schedule saved.");
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to save schedule."));
    }
  };

  // --- conflicts ---
  const [conflicts, setConflicts] = useState<Conflict[] | null>(null);
  const [checking, setChecking] = useState(false);

  const checkConflicts = async () => {
    if (examId === "") return;
    setChecking(true);
    try {
      const result = await examSetupService.checkConflicts(Number(examId));
      setConflicts(result.conflicts);
      toast.success(`Conflict check complete — ${result.count} issue(s).`);
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to check conflicts."));
    } finally {
      setChecking(false);
    }
  };

  // --- eligibility ---
  const [eligibilityOpen, setEligibilityOpen] = useState(false);
  const [overrideTarget, setOverrideTarget] = useState<EligibilityRow | null>(null);
  const [overrideReason, setOverrideReason] = useState("");
  const [computing, setComputing] = useState(false);

  const eligibilityQuery = useQuery({
    queryKey: ["examinations", "eligibility", examId],
    queryFn: () => examSetupService.eligibilityReport(Number(examId)),
    enabled: eligibilityOpen && examId !== "",
  });

  const computeEligibility = async () => {
    if (examId === "") return;
    setComputing(true);
    try {
      const result = await examSetupService.computeEligibility(Number(examId));
      await qc.invalidateQueries({ queryKey: ["examinations", "eligibility", examId] });
      const total = Number(result.summary.eligible ?? 0) + Number(result.summary.short ?? 0) + Number(result.summary.overridden ?? 0);
      toast.success(`Eligibility computed for ${total} student-subject pair(s).`);
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to compute eligibility."));
    } finally {
      setComputing(false);
    }
  };

  const submitOverride = async () => {
    if (!overrideTarget) return;
    if (overrideReason.trim().length < 5) {
      toast.error("Reason must be at least 5 characters.");
      return;
    }
    try {
      await examSetupService.overrideEligibility({
        exam_subject_id: overrideTarget.exam_subject_id,
        student_profile_id: overrideTarget.student_profile_id,
        reason: overrideReason.trim(),
      });
      await qc.invalidateQueries({ queryKey: ["examinations", "eligibility", examId] });
      setOverrideTarget(null);
      setOverrideReason("");
      toast.success("Eligibility override recorded (audited).");
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to record override (MFA required)."));
    }
  };

  const eligibilitySummary = eligibilityQuery.data?.summary;
  const eligibilityRows = eligibilityQuery.data?.data ?? [];

  return (
    <>
      <div className="institution-directory-toolbar" style={{ gap: 10, flexWrap: "wrap" }}>
        <label className="institution-mono" htmlFor="setup-exam" style={{ fontSize: 12, color: "var(--text-secondary)", textTransform: "uppercase" }}>Exam</label>
        <select
          id="setup-exam"
          className="filter-select"
          style={{ minWidth: 320 }}
          value={examId}
          onChange={(e) => { setExamId(e.target.value === "" ? "" : Number(e.target.value)); setConflicts(null); }}
          aria-label="Select exam"
        >
          <option value="">Select an examination…</option>
          {exams.map((e) => (
            <option key={e.id} value={e.id}>{e.title} ({e.status})</option>
          ))}
        </select>
        {selectedExam && <span className="institution-mono" style={{ fontSize: 12 }}>{selectedExam.programme?.code} · {selectedExam.kind} · Sem {selectedExam.semester}</span>}
        <div style={{ display: "flex", gap: 8 }}>
          <Button variant="secondary" onClick={checkConflicts} disabled={examId === "" || checking}>
            <AlertTriangle size={14} /> {checking ? "Checking…" : "Check conflicts"}
          </Button>
          {canManage && (
            <Button variant="secondary" onClick={() => setEligibilityOpen(true)} disabled={examId === ""}>
              <Users size={14} /> Eligibility
            </Button>
          )}
        </div>
      </div>

      {examId === "" && (
        <div className="institution-admin-panel"><p style={{ color: "var(--text-muted)" }}>Pick an exam to assign rooms, sessions and attendance-eligibility thresholds.</p></div>
      )}

      {conflicts !== null && (
        <div className="institution-admin-panel" style={{ borderColor: conflicts.length ? "#f59e0b" : "var(--border)" }}>
          <h3>
            {conflicts.length === 0
              ? <><CheckCircle2 size={17} style={{ verticalAlign: "-3px", color: "#10b981" }} /> No conflicts detected</>
              : <><AlertTriangle size={17} style={{ verticalAlign: "-3px", color: "#f59e0b" }} /> {conflicts.length} conflict(s) found</>}
          </h3>
          <ul style={{ margin: 0, paddingLeft: 18, fontSize: 13, lineHeight: 1.7 }}>
            {conflicts.map((c, i) => <li key={i}><span className="institution-mono" style={{ fontSize: 11 }}>{c.type}</span> — {c.message}</li>)}
          </ul>
        </div>
      )}

      {examId !== "" && (
        <div className="institution-admin-panel">
          <h3><CalendarClock size={16} style={{ verticalAlign: "-3px" }} /> Schedule &amp; rooms</h3>
          {detailQuery.isLoading ? <Spinner size={22} /> : (
            <table style={tableStyle}>
              <thead>
                <tr><th style={th}>Subject</th><th style={th}>Date</th><th style={th}>Time</th><th style={th}>Session</th><th style={th}>Room</th><th style={th}>Threshold</th><th style={th}></th></tr>
              </thead>
              <tbody>
                {entries.map((entry) => {
                  const room = entry.exam_room_id ? roomById.get(entry.exam_room_id) : undefined;
                  const session = entry.exam_session_id ? sessionById.get(entry.exam_session_id) : undefined;
                  return (
                    <tr key={entry.id}>
                      <td style={td}><strong>{entry.subject?.code}</strong><div style={{ fontSize: 11, color: "var(--text-muted)" }}>{entry.subject?.name}</div></td>
                      <td style={td}>{entry.exam_date?.slice(0, 10)}</td>
                      <td style={td}>{entry.start_time ? `${entry.start_time}–${entry.end_time}` : "—"}</td>
                      <td style={td}>{session ? <Badge tone="info">{session.code}</Badge> : "—"}</td>
                      <td style={td}>{room ? <span><DoorOpen size={12} style={{ verticalAlign: "-2px" }} /> {room.centre_code}/{room.code} <span style={{ color: "var(--text-muted)" }}>({room.capacity})</span></span> : "—"}</td>
                      <td style={td}>{entry.eligibility_threshold ? `${Number(entry.eligibility_threshold)}%` : "org default"}</td>
                      <td style={td}>
                        {canManage && <button type="button" className="timetable-icon-btn" onClick={() => openAssign(entry)} aria-label="Assign room and session"><ListChecks size={15} /></button>}
                      </td>
                    </tr>
                  );
                })}
                {entries.length === 0 && <tr><td style={{ ...td, color: "var(--text-muted)" }} colSpan={7}>No subjects scheduled yet.</td></tr>}
              </tbody>
            </table>
          )}
        </div>
      )}

      {/* Assign modal */}
      <Modal open={assignEntry !== null} title="Assign schedule" onClose={() => setAssignEntry(null)}
        footer={<><Button variant="secondary" onClick={() => setAssignEntry(null)}>Cancel</Button><Button onClick={saveAssign}><ListChecks size={15} /> Save</Button></>}>
        <p style={{ fontSize: 13, color: "var(--text-secondary)" }}>{assignEntry?.subject?.code} — {assignEntry?.subject?.name}</p>
        <FormField label="Exam session" htmlFor="assign-session">
          <select id="assign-session" className="filter-select" value={assignSession} onChange={(e) => setAssignSession(e.target.value)}>
            <option value="">None</option>
            {sessions.map((s) => <option key={s.id} value={s.id}>{s.code} — {s.label} ({s.start_time}–{s.end_time})</option>)}
          </select>
        </FormField>
        <FormField label="Room" htmlFor="assign-room">
          <select id="assign-room" className="filter-select" value={assignRoom} onChange={(e) => setAssignRoom(e.target.value)}>
            <option value="">None</option>
            {rooms.map((r) => <option key={r.id} value={r.id}>{r.centre_code}/{r.code} — {r.name} (capacity {r.capacity})</option>)}
          </select>
        </FormField>
        <FormField label="Eligibility threshold (%)" htmlFor="assign-threshold"><Input id="assign-threshold" type="number" min={0} max={100} value={assignThreshold} onChange={(e) => setAssignThreshold(e.target.value)} placeholder="org default (75)" /></FormField>
        <FormField label="Instructions" htmlFor="assign-instr"><Input id="assign-instr" value={assignInstructions} onChange={(e) => setAssignInstructions(e.target.value)} placeholder="Bring admit card and ID" /></FormField>
      </Modal>

      {/* Eligibility modal */}
      <Modal open={eligibilityOpen} title="Attendance eligibility" onClose={() => setEligibilityOpen(false)}
        footer={<>
          <Button variant="secondary" onClick={computeEligibility} disabled={computing}><Users size={14} /> {computing ? "Computing…" : "Recompute eligibility"}</Button>
          <Button variant="secondary" onClick={() => setEligibilityOpen(false)}>Close</Button>
        </>}>
        {eligibilitySummary && (
          <p style={{ fontSize: 13, color: "var(--text-secondary)" }}>
            <strong>{eligibilitySummary.eligible ?? 0}</strong> eligible · <strong>{eligibilitySummary.short ?? 0}</strong> short · <strong>{eligibilitySummary.overridden ?? 0}</strong> overridden
          </p>
        )}
        {eligibilityQuery.isLoading ? <Spinner size={22} /> : (
          <table style={tableStyle}>
            <thead><tr><th style={th}>App no.</th><th style={th}>Student</th><th style={th}>Subject</th><th style={th}>Attendance</th><th style={th}>Status</th><th style={th}></th></tr></thead>
            <tbody>
              {eligibilityRows.map((row) => (
                <tr key={row.id}>
                  <td style={td}><span className="institution-mono">{row.application_number}</span></td>
                  <td style={td}>{row.student_name}</td>
                  <td style={td}>{eligibilityQuery.data?.subjects.find((s) => s.id === row.exam_subject_id)?.code ?? row.exam_subject_id}</td>
                  <td style={td}>{row.attendance_pct ?? "no data"}%</td>
                  <td style={td}><Badge tone={STATUS_TONE[row.status] ?? "info"}>{row.status}</Badge></td>
                  <td style={td}>
                    {row.status === "short" && canManage && (
                      <button type="button" className="timetable-icon-btn" title="Override (audited, MFA required)" onClick={() => { setOverrideTarget(row); setOverrideReason(""); }}>
                        <Ban size={14} />
                      </button>
                    )}
                  </td>
                </tr>
              ))}
              {eligibilityRows.length === 0 && <tr><td style={{ ...td, color: "var(--text-muted)" }} colSpan={6}>No eligibility computed yet — click Recompute.</td></tr>}
            </tbody>
          </table>
        )}
      </Modal>

      {/* Override modal */}
      <Modal open={overrideTarget !== null} title="Eligibility override" onClose={() => setOverrideTarget(null)}
        footer={<><Button variant="secondary" onClick={() => setOverrideTarget(null)}>Cancel</Button><Button onClick={submitOverride}><ShieldCheck size={15} /> Record override</Button></>}>
        <p style={{ fontSize: 13 }}>
          Overriding <strong>{overrideTarget?.application_number}</strong> ({overrideTarget?.student_name}) — hall ticket gate passes for this subject.
        </p>
        <p style={{ fontSize: 12, color: "var(--text-muted)" }}>Reason is mandatory and audited (who, why, when). Overriding needs MFA.</p>
        <FormField label="Reason" htmlFor="override-reason" required>
          <Input id="override-reason" autoFocus value={overrideReason} onChange={(e) => setOverrideReason(e.target.value)} placeholder="Bunked 2 days with medical certificate; condoned by Principal." />
        </FormField>
      </Modal>
    </>
  );
};

export default ExamSetupPage;
