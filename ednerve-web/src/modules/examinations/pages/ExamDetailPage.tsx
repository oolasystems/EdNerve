import { useMemo, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import {
  Printer,
  Plus,
  Trash2,
  RefreshCw,
  Users,
  AlertTriangle,
  Send,
  CheckCircle,
  XCircle,
  ShieldCheck,
  Clock,
} from "lucide-react";
import { toast } from "sonner";

import { examinationService, examDutyService } from "../api";
import type { DutyRosterSlot, ExamDutyRole } from "../api";
import { institutionService } from "../../institution/api";
import type { InstitutionUser } from "../../institution/types";
import type { BadgeTone } from "../../../shared/ui/Badge";
import { getApiErrorMessage } from "../../../shared/lib/api-error";
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

import "../../institution/institution.css";

const STAFF_ROLES = ["faculty", "hod", "dean", "registrar", "examination_officer", "institution_admin"];
const tableStyle: React.CSSProperties = { width: "100%", borderCollapse: "collapse", fontSize: "var(--font-size-sm)" };
const th: React.CSSProperties = { textAlign: "left", padding: "8px 10px", borderBottom: "1px solid var(--border)", color: "var(--text-muted)", fontSize: "var(--font-size-xs)", textTransform: "uppercase", letterSpacing: "0.05em" };
const td: React.CSSProperties = { padding: "10px", borderBottom: "1px solid var(--border)", verticalAlign: "top" };

const statusTone = (status: string): BadgeTone => {
  if (status === "accepted") return "success";
  if (status === "declined") return "danger";
  if (status === "replaced") return "warning";
  return "info";
};

const rosterStatusTone = (status: string): BadgeTone => {
  if (status === "published" || status === "approved") return "success";
  if (status === "rejected") return "danger";
  if (status === "submitted") return "warning";
  return "neutral";
};

const DutyRosterPage = () => {
  const { user } = useAuth();
  const qc = useQueryClient();
  const canManage = isInstitutionAdmin(user) || isExamOfficer(user);

  const examsQuery = useQuery({ queryKey: ["examinations", "list"], queryFn: () => examinationService.getExams() });
  const exams = examsQuery.data?.exams ?? [];
  const [examId, setExamId] = useState<number | "">("");

  const rosterQuery = useQuery({
    queryKey: ["examinations", "duty-roster", examId],
    queryFn: () => examDutyService.getRoster(Number(examId)),
    enabled: examId !== "",
  });

  const rosterHistoryQuery = useQuery({
    queryKey: ["examinations", "duty-roster-history", examId],
    queryFn: () => examDutyService.getRosterHistory(Number(examId)),
    enabled: examId !== "" && canManage,
  });

  const usersQuery = useQuery({
    queryKey: ["institution", "users", "duty-roster"],
    queryFn: () => institutionService.getAllUsers(),
    enabled: canManage,
  });

  const staffOptions = useMemo(
    () => (usersQuery.data ?? []).filter((u: InstitutionUser) => u.active_role_assignments?.some((a) => STAFF_ROLES.includes(a.role?.code ?? ""))),
    [usersQuery.data]
  );

  const roster = rosterQuery.data;
  const roles: ExamDutyRole[] = roster?.roles ?? [];
  const slots: DutyRosterSlot[] = roster?.slots ?? [];
  const rosterMeta = roster?.roster;
  const currentVersionHistory = rosterHistoryQuery.data?.versions?.find(
    (version) => version.version === rosterMeta?.version
  );
  const submittedByCurrentUser =
    !!currentVersionHistory?.submitted_by &&
    Number(currentVersionHistory.submitted_by.id) === Number(user?.id);
  const canEditRoster = canManage && rosterMeta?.status === "draft";
  const canReviewRoster =
    canManage &&
    rosterMeta?.status === "submitted" &&
    !rosterHistoryQuery.isLoading &&
    !rosterHistoryQuery.isError &&
    !!currentVersionHistory?.submitted_by &&
    !submittedByCurrentUser;

  const [lifecycleBusy, setLifecycleBusy] = useState(false);

  const refreshRosterData = async () => {
    await Promise.all([
      qc.invalidateQueries({ queryKey: ["examinations", "duty-roster", examId] }),
      qc.invalidateQueries({ queryKey: ["examinations", "duty-roster-history", examId] }),
      qc.invalidateQueries({ queryKey: ["examinations", "detail", examId] }),
    ]);
  };

  const runLifecycleAction = async (
    action: "submit" | "approve" | "reject" | "publish"
  ) => {
    if (examId === "" || lifecycleBusy) return;

    let rejectionReason = "";
    if (action === "reject") {
      const enteredReason = window.prompt("Enter the reason for rejecting this duty roster:");
      if (enteredReason === null) return;
      rejectionReason = enteredReason.trim();
      if (!rejectionReason) {
        toast.error("A rejection reason is required.");
        return;
      }
      if (rejectionReason.length > 1000) {
        toast.error("The rejection reason cannot exceed 1000 characters.");
        return;
      }
    }

    const confirmationMessages = {
      submit: "Submit this duty roster for independent approval?",
      approve: "Approve this duty roster?",
      reject: "Reject this duty roster?",
      publish: "Publish this approved duty roster? This will synchronise the published roster with the legacy invigilation records.",
    };
    if (!window.confirm(confirmationMessages[action])) return;

    setLifecycleBusy(true);
    try {
      if (action === "submit") {
        await examDutyService.submitRoster(Number(examId));
        toast.success("Duty roster submitted for approval.");
      } else if (action === "approve") {
        await examDutyService.approveRoster(Number(examId));
        toast.success("Duty roster approved.");
      } else if (action === "reject") {
        await examDutyService.rejectRoster(Number(examId), rejectionReason);
        toast.success("Duty roster rejected.");
      } else {
        await examDutyService.publishRoster(Number(examId));
        toast.success("Duty roster published successfully.");
      }
      await refreshRosterData();
    } catch (e) {
      toast.error(getApiErrorMessage(e, `Unable to ${action} duty roster.`));
    } finally {
      setLifecycleBusy(false);
    }
  };

  const createNewRosterVersion = async () => {
    if (examId === "" || lifecycleBusy) return;
    if (!rosterMeta || !["rejected", "published"].includes(rosterMeta.status)) {
      toast.error("A new version can only be created from a rejected or published roster.");
      return;
    }

    const sourceVersion = rosterMeta.version;
    if (!window.confirm(
      `Create roster version ${sourceVersion + 1} from version ${sourceVersion}? ` +
      "The previous version will remain unchanged. Active assigned and accepted duties will be copied into a new draft, with staff responses reset to Assigned."
    )) return;

    setLifecycleBusy(true);
    try {
      const result = await examDutyService.createRosterVersion(Number(examId));
      toast.success(
        `Version ${result.roster.version} created as a draft. ${result.copied_assignments} active assignment(s) copied.`
      );
      await refreshRosterData();
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to create a new duty roster version."));
    } finally {
      setLifecycleBusy(false);
    }
  };

  const formatHistoryDate = (value: string | null) =>
    value ? new Date(value).toLocaleString() : "—";

  // Assign modal
  const [assignSlot, setAssignSlot] = useState<DutyRosterSlot | null>(null);
  const [assignUser, setAssignUser] = useState("");
  const [assignRole, setAssignRole] = useState("");
  const [assignBusy, setAssignBusy] = useState(false);

  const openAssign = (slot: DutyRosterSlot) => {
    setAssignSlot(slot);
    setAssignUser("");
    setAssignRole(roles.find((r) => r.code === "INVIGILATOR")?.id?.toString() ?? (roles[0]?.id?.toString() ?? ""));
  };

  const saveAssign = async () => {
    if (!assignSlot || examId === "" || !assignUser || !assignRole || !canEditRoster) return;
    setAssignBusy(true);
    try {
      await examDutyService.assign(Number(examId), {
        exam_subject_id: assignSlot.exam_subject_id,
        user_id: Number(assignUser),
        duty_role_id: Number(assignRole),
      });
      await qc.invalidateQueries({ queryKey: ["examinations", "duty-roster", examId] });
      await qc.invalidateQueries({ queryKey: ["examinations", "detail", examId] });
      toast.success("Duty assigned.");
      setAssignSlot(null);
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to assign duty."));
    } finally {
      setAssignBusy(false);
    }
  };

  const removeAssignment = async (id: number) => {
    if (examId === "" || !canEditRoster) return;
    if (!window.confirm("Remove this duty assignment?")) return;
    try {
      await examDutyService.removeAssignment(Number(examId), id);
      await qc.invalidateQueries({ queryKey: ["examinations", "duty-roster", examId] });
      await qc.invalidateQueries({ queryKey: ["examinations", "detail", examId] });
      toast.success("Assignment removed.");
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to remove assignment."));
    }
  };

  // Auto-assign modal
  const [autoOpen, setAutoOpen] = useState(false);
  const [autoRole, setAutoRole] = useState("");
  const [autoPerSlot, setAutoPerSlot] = useState("1");
  const [autoMax, setAutoMax] = useState("");
  const [autoBusy, setAutoBusy] = useState(false);

  const runAutoAssign = async () => {
    if (examId === "" || !autoRole || !canEditRoster) return;
    setAutoBusy(true);
    try {
      const res = await examDutyService.autoAssign(Number(examId), {
        duty_role_id: Number(autoRole),
        required_per_slot: Number(autoPerSlot) || 1,
        max_per_staff: autoMax ? Number(autoMax) : undefined,
      });
      await qc.invalidateQueries({ queryKey: ["examinations", "duty-roster", examId] });
      toast.success(`Auto-assign done: ${res.created} created, ${res.skipped_unfilled ?? 0} unfilled.`);
      setAutoOpen(false);
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Auto-assign failed."));
    } finally {
      setAutoBusy(false);
    }
  };

  // Availability modal
  const [availOpen, setAvailOpen] = useState(false);
  const [availUser, setAvailUser] = useState("");
  const [availDate, setAvailDate] = useState("");
  const [availAvailable, setAvailAvailable] = useState("false");
  const [availReason, setAvailReason] = useState("");
  const [availBusy, setAvailBusy] = useState(false);

  const saveAvailability = async () => {
    if (examId === "" || !availUser || !canEditRoster) return;
    setAvailBusy(true);
    try {
      await examDutyService.storeAvailability(Number(examId), {
        user_id: Number(availUser),
        duty_date: availDate || null,
        available: availAvailable === "true",
        reason: availReason.trim() || null,
      });
      await qc.invalidateQueries({ queryKey: ["examinations", "duty-roster", examId] });
      toast.success("Availability saved.");
      setAvailOpen(false);
      setAvailReason("");
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to save availability."));
    } finally {
      setAvailBusy(false);
    }
  };

  const printRoster = async () => {
    if (examId === "") return;
    try {
      const data = await examDutyService.getPrintRoster(Number(examId));
      const win = window.open("", "_blank");
      if (!win) { toast.error("Popup blocked. Allow popups to print."); return; }
      const rows = (data.slots || []).map((s) => {
        const assigns = (s.assignments || []).map((a) => `${a.user?.name ?? "—"} (${a.duty_role?.name ?? a.duty_role?.code ?? "—"}, ${a.status})`).join("<br/>") || "—";
        return `<tr><td>${String(s.exam_date ?? "").slice(0,10)}</td><td>${s.start_time ?? ""}-${s.end_time ?? ""}</td><td>${s.subject?.code ?? ""} ${s.subject?.name ?? ""}</td><td>${s.venue ?? s.room?.code ?? "—"}</td><td>${assigns}</td></tr>`;
      }).join("");
      const workload = (data.workload || []).map((w) => `<tr><td>${w.user?.name ?? ""}</td><td>${w.duty_count}</td><td>${w.weighted_load}</td><td>${w.accepted}/${w.assigned}</td></tr>`).join("");
      win.document.write(`<!doctype html><html><head><title>Duty Roster - ${data.exam?.title ?? ""}</title>
        <style>body{font-family:Arial,sans-serif;padding:20px;color:#111}h1{font-size:20px;margin:0 0 4px}h2{font-size:14px;margin:16px 0 6px}table{width:100%;border-collapse:collapse;margin-top:8px}th,td{border:1px solid #ccc;padding:6px 8px;font-size:12px;text-align:left;vertical-align:top}th{background:#f5f5f5}.meta{color:#555;font-size:12px;margin-bottom:12px}@media print{button{display:none}}</style>
        </head><body>
        <button onclick="window.print()" style="margin-bottom:12px;padding:6px 12px">Print</button>
        <h1>Examination Duty Roster</h1>
        <div class="meta"><strong>${data.exam?.title ?? ""}</strong> · ${data.exam?.programme?.code ?? ""} · Sem ${data.exam?.semester ?? ""} · Sec ${data.exam?.section ?? ""} · Generated ${String(data.generated_at ?? "").slice(0,19).replace("T"," ")}</div>
        <h2>Slot-wise assignments</h2>
        <table><thead><tr><th>Date</th><th>Time</th><th>Subject</th><th>Venue/Room</th><th>Assigned staff</th></tr></thead><tbody>${rows || '<tr><td colspan="5">No slots.</td></tr>'}</tbody></table>
        <h2>Workload summary</h2>
        <table><thead><tr><th>Staff</th><th>Duties</th><th>Weighted load</th><th>Accepted/Assigned</th></tr></thead><tbody>${workload || '<tr><td colspan="4">No assignments.</td></tr>'}</tbody></table>
        </body></html>`);
      win.document.close();
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to prepare print view."));
    }
  };

  return (
    <>
      <div className="institution-admin-panel" style={{ marginBottom: "var(--space-4)" }}>
        <h3><Users size={17} style={{ verticalAlign: "-3px" }} /> Duty Roster</h3>
        <p style={{ color: "var(--text-secondary)", fontSize: 13, marginTop: 4 }}>
          Configure duty roles, mark staff availability, assign duties per exam slot, auto-balance workload, and print the roster.
        </p>
        <div className="institution-form-grid" style={{ marginTop: 12, alignItems: "end" }}>
          <FormField label="Examination" htmlFor="duty-exam">
            <select id="duty-exam" className="filter-select" value={examId} onChange={(e) => setExamId(e.target.value ? Number(e.target.value) : "")}> 
              <option value="">Select examination...</option>
              {exams.map((e) => <option key={e.id} value={e.id}>{e.title} · Sem {e.semester} {e.section}</option>)}
            </select>
          </FormField>
          <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
            <Button variant="secondary" disabled={examId === "" || rosterQuery.isFetching} onClick={() => qc.invalidateQueries({ queryKey: ["examinations", "duty-roster", examId] })}>
              <RefreshCw size={14} /> Refresh
            </Button>
            {canEditRoster && examId !== "" && (
              <>
                <Button variant="secondary" onClick={() => { setAutoRole(roles.find(r=>r.code==="INVIGILATOR")?.id?.toString() ?? ""); setAutoOpen(true); }} disabled={!roles.length}>
                  Auto-assign
                </Button>
                <Button variant="secondary" onClick={() => setAvailOpen(true)}>Availability</Button>
              </>
            )}
            <Button variant="secondary" disabled={examId === ""} onClick={printRoster}><Printer size={14} /> Print roster</Button>
          </div>
        </div>
      </div>

      {examId === "" && <p style={{ color: "var(--text-muted)" }}>Select an examination to manage its duty roster.</p>}
      {examId !== "" && rosterQuery.isLoading && <div className="dashboard-state"><Spinner size={22} /><p>Loading roster...</p></div>}

      {roster && (
        <>
          <div className="institution-admin-panel" style={{ marginBottom: "var(--space-4)" }}>
            <div style={{ display: "flex", justifyContent: "space-between", gap: 12, alignItems: "flex-start", flexWrap: "wrap" }}>
              <div>
                <h3><ShieldCheck size={17} style={{ verticalAlign: "-3px" }} /> Roster approval workflow</h3>
                <p style={{ color: "var(--text-secondary)", fontSize: 13, marginTop: 5 }}>
                  Version {rosterMeta?.version ?? "—"} · Current status{" "}
                  <Badge tone={rosterStatusTone(rosterMeta?.status ?? "draft")}>
                    {rosterMeta?.status ?? "unknown"}
                  </Badge>
                </p>
                {rosterMeta?.status === "submitted" && (
                  <p style={{ color: "var(--text-secondary)", fontSize: 12, marginTop: 6 }}>
                    {submittedByCurrentUser
                      ? "You submitted this roster. Another authorised officer must approve or reject it."
                      : currentVersionHistory?.submitted_by
                        ? `Submitted by ${currentVersionHistory.submitted_by.name}. Approval or rejection requires a different authorised officer.`
                        : "Approval and rejection are restricted to an authorised officer other than the submitter."}
                  </p>
                )}
                {rosterMeta?.status === "rejected" && currentVersionHistory?.rejection_reason && (
                  <p style={{ color: "var(--text-secondary)", fontSize: 12, marginTop: 6 }}>
                    Rejection reason: {currentVersionHistory.rejection_reason}
                  </p>
                )}
                {rosterMeta?.status === "published" && rosterMeta.published_at && (
                  <p style={{ color: "var(--text-secondary)", fontSize: 12, marginTop: 6 }}>
                    <Clock size={12} style={{ verticalAlign: "-2px" }} /> Published {new Date(rosterMeta.published_at).toLocaleString()}
                  </p>
                )}
              </div>
              {canManage && (
                <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
                  {rosterMeta?.status === "draft" && (
                    <Button onClick={() => runLifecycleAction("submit")} disabled={lifecycleBusy}>
                      <Send size={14} /> Submit for approval
                    </Button>
                  )}
                  {canReviewRoster && (
                    <>
                      <Button onClick={() => runLifecycleAction("approve")} disabled={lifecycleBusy}>
                        <CheckCircle size={14} /> Approve
                      </Button>
                      <Button variant="danger" onClick={() => runLifecycleAction("reject")} disabled={lifecycleBusy}>
                        <XCircle size={14} /> Reject
                      </Button>
                    </>
                  )}
                  {rosterMeta?.status === "approved" && (
                    <Button onClick={() => runLifecycleAction("publish")} disabled={lifecycleBusy}>
                      <CheckCircle size={14} /> Publish roster
                    </Button>
                  )}
                  {(rosterMeta?.status === "rejected" || rosterMeta?.status === "published") && (
                    <Button variant="secondary" onClick={createNewRosterVersion} disabled={lifecycleBusy}>
                      <Plus size={14} /> Create next version
                    </Button>
                  )}
                </div>
              )}
            </div>
            {canManage && !canEditRoster && rosterMeta?.status !== "submitted" && (
              <p style={{ color: "var(--text-muted)", fontSize: 12, marginTop: 10 }}>
                {rosterMeta?.status === "published"
                  ? "This version is published and read-only."
                  : rosterMeta?.status === "approved"
                    ? "This approved version is read-only until it is published."
                    : rosterMeta?.status === "rejected"
                      ? "This version is rejected and read-only."
                      : "Only a draft roster can be edited."}
              </p>
            )}
            {canManage && rosterMeta?.status === "submitted" && submittedByCurrentUser && (
              <p style={{ color: "var(--text-muted)", fontSize: 12, marginTop: 10 }}>
                Separation of duties is enforced by the API. You cannot approve or reject a roster that you submitted.
              </p>
            )}
            {canManage && rosterHistoryQuery.isError && (
              <p style={{ color: "var(--text-muted)", fontSize: 12, marginTop: 8 }}>
                Submission details could not be loaded. The API will enforce independent approval.
              </p>
            )}
          </div>

          {canManage && (
            <div className="institution-admin-panel" style={{ marginBottom: "var(--space-4)" }}>
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", gap: 12, flexWrap: "wrap" }}>
                <div>
                  <h3>Roster version history</h3>
                  <p style={{ color: "var(--text-secondary)", fontSize: 12, marginTop: 4 }}>
                    Previous versions are preserved. A new version starts as a draft and does not publish automatically.
                  </p>
                </div>
                <Button
                  variant="secondary"
                  onClick={() => qc.invalidateQueries({ queryKey: ["examinations", "duty-roster-history", examId] })}
                  disabled={rosterHistoryQuery.isFetching}
                >
                  <RefreshCw size={14} /> Refresh history
                </Button>
              </div>

              {rosterHistoryQuery.isLoading ? (
                <div style={{ display: "flex", alignItems: "center", gap: 8, padding: "14px 0" }}>
                  <Spinner size={18} /><span style={{ color: "var(--text-muted)", fontSize: 13 }}>Loading roster history...</span>
                </div>
              ) : rosterHistoryQuery.isError ? (
                <p style={{ color: "var(--text-muted)", fontSize: 13, marginTop: 12 }}>
                  Roster history could not be loaded. Refresh to try again.
                </p>
              ) : !rosterHistoryQuery.data?.versions.length ? (
                <p style={{ color: "var(--text-muted)", fontSize: 13, marginTop: 12 }}>No roster version history is available.</p>
              ) : (
                <div style={{ overflowX: "auto", marginTop: 12 }}>
                  <table style={tableStyle}>
                    <thead>
                      <tr>
                        <th style={th}>Version</th>
                        <th style={th}>Status</th>
                        <th style={th}>Assignments</th>
                        <th style={th}>Submitted</th>
                        <th style={th}>Approved</th>
                        <th style={th}>Rejected</th>
                        <th style={th}>Published</th>
                      </tr>
                    </thead>
                    <tbody>
                      {rosterHistoryQuery.data.versions.map((version) => (
                        <tr key={version.id}>
                          <td style={td}>
                            <strong>v{version.version}</strong>
                            {version.version === rosterMeta?.version && (
                              <span style={{ color: "var(--text-muted)", fontSize: 11, marginLeft: 6 }}>Current</span>
                            )}
                          </td>
                          <td style={td}>
                            <Badge tone={rosterStatusTone(version.status)}>{version.status}</Badge>
                          </td>
                          <td style={td}>{version.assignment_count}</td>
                          <td style={td}>
                            <div>{version.submitted_by?.name ?? "—"}</div>
                            <div style={{ color: "var(--text-muted)", fontSize: 11 }}>{formatHistoryDate(version.submitted_at)}</div>
                          </td>
                          <td style={td}>
                            <div>{version.approved_by?.name ?? "—"}</div>
                            <div style={{ color: "var(--text-muted)", fontSize: 11 }}>{formatHistoryDate(version.approved_at)}</div>
                          </td>
                          <td style={td}>
                            <div>{version.rejected_by?.name ?? "—"}</div>
                            <div style={{ color: "var(--text-muted)", fontSize: 11 }}>{formatHistoryDate(version.rejected_at)}</div>
                            {version.rejection_reason && (
                              <div style={{ color: "var(--text-secondary)", fontSize: 11, maxWidth: 220, whiteSpace: "normal" }}>
                                {version.rejection_reason}
                              </div>
                            )}
                          </td>
                          <td style={td}>
                            <div>{version.published_by?.name ?? "—"}</div>
                            <div style={{ color: "var(--text-muted)", fontSize: 11 }}>{formatHistoryDate(version.published_at)}</div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          )}

          <div className="institution-two-column">
            <div className="institution-admin-panel">
              <h3>Slots & assignments</h3>
              {!slots.length ? (
                <p style={{ color: "var(--text-muted)" }}>No exam subjects/slots scheduled yet. Add subjects in Exam detail first.</p>
              ) : (
                <table style={tableStyle}>
                  <thead>
                    <tr>
                      <th style={th}>Slot</th>
                      <th style={th}>Assignments</th>
                      {canEditRoster && <th style={th}></th>}
                    </tr>
                  </thead>
                  <tbody>
                    {slots.map((slot) => (
                      <tr key={slot.exam_subject_id}>
                        <td style={td}>
                          <strong>{slot.subject?.code ?? "—"}</strong>
                          <div className="institution-mono" style={{ fontSize: 11 }}>{slot.subject?.name}</div>
                          <div style={{ fontSize: 12, color: "var(--text-secondary)", marginTop: 4 }}>
                            {String(slot.exam_date ?? "").slice(0, 10)} · {slot.start_time ?? "—"}–{slot.end_time ?? "—"}
                          </div>
                          <div style={{ fontSize: 12, color: "var(--text-muted)" }}>{slot.venue || slot.room?.code || "No venue"} {slot.session ? `· ${slot.session.code}` : ""}</div>
                        </td>
                        <td style={td}>
                          {!slot.assignments.length ? <span style={{ color: "var(--text-muted)" }}>No duties assigned</span> : (
                            <div style={{ display: "flex", flexDirection: "column", gap: 6 }}>
                              {slot.assignments.map((a) => (
                                <div key={a.id} style={{ display: "flex", gap: 8, alignItems: "center", flexWrap: "wrap" }}>
                                  <span>{a.user?.name ?? "—"}</span>
                                  <Badge tone="neutral">{a.duty_role?.name ?? "—"}</Badge>
                                  <Badge tone={statusTone(a.status)}>{a.status}</Badge>
                                  {canEditRoster && (
                                    <button type="button" className="timetable-icon-btn" aria-label="Remove assignment" onClick={() => removeAssignment(a.id)}><Trash2 size={13} /></button>
                                  )}
                                </div>
                              ))}
                            </div>
                          )}
                        </td>
                        {canManage && (
                          <td style={td}>
                            <Button variant="secondary" onClick={() => openAssign(slot)}><Plus size={13} /> Assign</Button>
                          </td>
                        )}
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>

            <div className="institution-admin-panel">
              <h3>Workload (this exam)</h3>
              {!roster.workload?.length ? (
                <p style={{ color: "var(--text-muted)" }}>No assignments yet.</p>
              ) : (
                <table style={tableStyle}>
                  <thead><tr><th style={th}>Staff</th><th style={th}>Duties</th><th style={th}>Weighted</th><th style={th}>Accepted</th></tr></thead>
                  <tbody>
                    {roster.workload.map((w) => (
                      <tr key={w.user.id}>
                        <td style={td}>{w.user.name}</td>
                        <td style={td}>{w.duty_count}</td>
                        <td style={td}>{w.weighted_load}</td>
                        <td style={td}>{w.accepted}/{w.assigned + w.accepted}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}

              <h3 style={{ marginTop: 16 }}>Availability notes</h3>
              {!roster.availability?.length ? (
                <p style={{ color: "var(--text-muted)" }}>No availability records for this exam.</p>
              ) : (
                <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
                  {roster.availability.map((a) => (
                    <div key={a.user.id} style={{ fontSize: 13, borderBottom: "1px solid var(--border)", paddingBottom: 6 }}>
                      <strong>{a.user.name}</strong>
                      <div style={{ color: "var(--text-secondary)", fontSize: 12 }}>
                        {a.entries.map((e) => (
                          <span key={e.id} style={{ marginRight: 8 }}>
                            <Badge tone={e.available ? "success" : "danger"}>{e.available ? "available" : "unavailable"}</Badge>
                            {e.duty_date ? ` ${String(e.duty_date).slice(0,10)}` : ""} {e.session?.code ? ` ${e.session.code}` : ""} {e.reason ? `— ${e.reason}` : ""}
                          </span>
                        ))}
                      </div>
                    </div>
                  ))}
                </div>
              )}
              {canManage && (
                <div style={{ marginTop: 12, fontSize: 12, color: "var(--text-muted)", display: "flex", gap: 6, alignItems: "center" }}>
                  <AlertTriangle size={14} /> Unavailable staff are skipped during auto-assign for matching date/slot.
                </div>
              )}
            </div>
          </div>
        </>
      )}

      {/* Assign modal */}
      <Modal open={!!assignSlot} title="Assign duty" onClose={() => setAssignSlot(null)}
        footer={<><Button variant="secondary" onClick={() => setAssignSlot(null)}>Cancel</Button><Button onClick={saveAssign} disabled={assignBusy || !assignUser || !assignRole}>{assignBusy ? "Assigning..." : "Assign"}</Button></>}>
        {assignSlot && (
          <>
            <p style={{ fontSize: 13, color: "var(--text-secondary)", marginBottom: 10 }}>
              {assignSlot.subject?.code} · {String(assignSlot.exam_date ?? "").slice(0,10)} · {assignSlot.start_time ?? "—"}
            </p>
            <FormField label="Staff" htmlFor="duty-staff" required>
              <select id="duty-staff" className="filter-select" value={assignUser} onChange={(e) => setAssignUser(e.target.value)}>
                <option value="">Select staff...</option>
                {staffOptions.map((u) => <option key={u.id} value={u.id}>{u.name} ({u.email})</option>)}
              </select>
            </FormField>
            <FormField label="Duty role" htmlFor="duty-role" required>
              <select id="duty-role" className="filter-select" value={assignRole} onChange={(e) => setAssignRole(e.target.value)}>
                <option value="">Select role...</option>
                {roles.map((r) => <option key={r.id} value={r.id}>{r.name} ({r.code})</option>)}
              </select>
            </FormField>
          </>
        )}
      </Modal>

      {/* Auto-assign modal */}
      <Modal open={autoOpen} title="Auto-assign duties" onClose={() => setAutoOpen(false)}
        footer={<><Button variant="secondary" onClick={() => setAutoOpen(false)}>Cancel</Button><Button onClick={runAutoAssign} disabled={autoBusy || !autoRole}>{autoBusy ? "Running..." : "Run auto-assign"}</Button></>}>
        <p style={{ fontSize: 13, color: "var(--text-secondary)", marginBottom: 10 }}>
          Assigns the selected role to eligible, available staff with lowest workload for each slot (round-robin by weighted load). Existing active assignments for the role are respected.
        </p>
        <FormField label="Duty role" htmlFor="auto-role" required>
          <select id="auto-role" className="filter-select" value={autoRole} onChange={(e) => setAutoRole(e.target.value)}>
            <option value="">Select role...</option>
            {roles.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
          </select>
        </FormField>
        <div className="institution-form-grid">
          <FormField label="Required per slot" htmlFor="auto-per">
            <Input id="auto-per" type="number" min={1} max={20} value={autoPerSlot} onChange={(e) => setAutoPerSlot(e.target.value)} />
          </FormField>
          <FormField label="Max duties per staff (optional)" htmlFor="auto-max">
            <Input id="auto-max" type="number" min={1} placeholder="no cap" value={autoMax} onChange={(e) => setAutoMax(e.target.value)} />
          </FormField>
        </div>
      </Modal>

      {/* Availability modal */}
      <Modal open={availOpen} title="Mark availability" onClose={() => setAvailOpen(false)}
        footer={<><Button variant="secondary" onClick={() => setAvailOpen(false)}>Cancel</Button><Button onClick={saveAvailability} disabled={availBusy || !availUser}>{availBusy ? "Saving..." : "Save"}</Button></>}>
        <FormField label="Staff" htmlFor="avail-user" required>
          <select id="avail-user" className="filter-select" value={availUser} onChange={(e) => setAvailUser(e.target.value)}>
            <option value="">Select staff...</option>
            {staffOptions.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
          </select>
        </FormField>
        <div className="institution-form-grid">
          <FormField label="Date (optional)" htmlFor="avail-date">
            <Input id="avail-date" type="date" value={availDate} onChange={(e) => setAvailDate(e.target.value)} />
          </FormField>
          <FormField label="Availability" htmlFor="avail-flag">
            <select id="avail-flag" className="filter-select" value={availAvailable} onChange={(e) => setAvailAvailable(e.target.value)}>
              <option value="false">Unavailable</option>
              <option value="true">Available</option>
            </select>
          </FormField>
        </div>
        <FormField label="Reason (optional)" htmlFor="avail-reason">
          <Input id="avail-reason" value={availReason} onChange={(e) => setAvailReason(e.target.value)} placeholder="e.g. medical leave, external duty" />
        </FormField>
      </Modal>
    </>
  );
};

export default DutyRosterPage;
