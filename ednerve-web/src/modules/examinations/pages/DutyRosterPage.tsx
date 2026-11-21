import { useMemo, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Printer, Plus, Trash2, RefreshCw, Users, AlertTriangle } from "lucide-react";
import { toast } from "sonner";

import { examinationService } from "../api";
import { examDutyService } from "../api";
import type { DutyRosterSlot, ExamDutyRole } from "../api";
import { institutionService } from "../../institution/api";
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

const statusTone = (status: string) => {
  if (status === "accepted") return "success";
  if (status === "declined") return "danger";
  if (status === "replaced") return "warning";
  return "info";
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

  const usersQuery = useQuery({
    queryKey: ["institution", "users", "duty-roster"],
    queryFn: () => institutionService.getAllUsers(),
    enabled: canManage,
  });

  const staffOptions = useMemo(
    () => (usersQuery.data ?? []).filter((u: any) => u.active_role_assignments?.some((a: any) => STAFF_ROLES.includes(a.role?.code ?? ""))),
    [usersQuery.data]
  );

  const roster = rosterQuery.data;
  const roles: ExamDutyRole[] = roster?.roles ?? [];
  const slots: DutyRosterSlot[] = roster?.slots ?? [];

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
    if (!assignSlot || examId === "" || !assignUser || !assignRole) return;
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
    if (examId === "") return;
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

  const updateStatus = async (id: number, status: string) => {
    if (examId === "") return;
    try {
      await examDutyService.updateAssignment(Number(examId), id, { status });
      await qc.invalidateQueries({ queryKey: ["examinations", "duty-roster", examId] });
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to update assignment."));
    }
  };

  // Auto-assign modal
  const [autoOpen, setAutoOpen] = useState(false);
  const [autoRole, setAutoRole] = useState("");
  const [autoPerSlot, setAutoPerSlot] = useState("1");
  const [autoMax, setAutoMax] = useState("");
  const [autoBusy, setAutoBusy] = useState(false);

  const runAutoAssign = async () => {
    if (examId === "" || !autoRole) return;
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
    if (examId === "" || !availUser) return;
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
            {canManage && examId !== "" && (
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
                      {canManage && <th style={th}></th>}
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
                                  <Badge tone={statusTone(a.status) as any}>{a.status}</Badge>
                                  {canManage && a.status === "assigned" && (
                                    <>
                                      <button type="button" className="btn btn-ghost" style={{ padding: "2px 6px", fontSize: 11 }} onClick={() => updateStatus(a.id, "accepted")}>Accept</button>
                                      <button type="button" className="btn btn-ghost" style={{ padding: "2px 6px", fontSize: 11 }} onClick={() => updateStatus(a.id, "declined")}>Decline</button>
                                    </>
                                  )}
                                  {canManage && (
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
                {staffOptions.map((u: any) => <option key={u.id} value={u.id}>{u.name} ({u.email})</option>)}
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
            {staffOptions.map((u: any) => <option key={u.id} value={u.id}>{u.name}</option>)}
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
