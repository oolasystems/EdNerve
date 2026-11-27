import "../attendance.css";
import { useMemo, useState } from "react";
import { ClipboardCheck, Save, CheckCheck } from "lucide-react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { attendanceService } from "../api";
import type { AttendanceStatus } from "../api";

import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin, isFacultyLike } from "../../../shared/lib/rbac";
import { getApiErrorMessage } from "../../../shared/lib/api-error";
import { academicService } from "../../academic/api";

import {
  Badge,
  Button,
  Spinner,
} from "../../../shared/ui";

const STATUS_META: Record<AttendanceStatus, { label: string; tone: "success" | "info" | "danger"; short: string }> = {
  present: { label: "Present", tone: "success", short: "P" },
  late: { label: "Late", tone: "info", short: "L" },
  absent: { label: "Absent", tone: "danger", short: "A" },
};

const dayName = (d: number) =>
  ["", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"][d] ?? String(d);

const AttendanceMarkPage = () => {
  const { user } = useAuth();
  const qc = useQueryClient();
  const canManage = isInstitutionAdmin(user);
  const isFaculty = isFacultyLike(user);

  const [programmeId, setProgrammeId] = useState<number | null>(null);
  const [semester, setSemester] = useState<number>(3);
  const [section, setSection] = useState("A");
  const [entryId, setEntryId] = useState<number | null>(null);
  const [date, setDate] = useState(() => new Date().toISOString().slice(0, 10));

  const programmesQuery = useQuery({
    queryKey: ["academic", "programmes"],
    queryFn: () => academicService.getProgrammes(),
    enabled: canManage,
  });

  // Slots: admin filters by programme/semester/section; faculty gets own slots.
  const adminSlotsQuery = useQuery({
    queryKey: ["attendance", "slots", programmeId, semester, section],
    queryFn: () =>
      attendanceService.getTimetable({
        programme_id: programmeId ?? undefined,
        semester,
        section,
      }),
    enabled: canManage && programmeId !== null,
  });

  const facultySlotsQuery = useQuery({
    queryKey: ["attendance", "my-slots"],
    queryFn: () => attendanceService.getMyTimetable(),
    enabled: !canManage,
  });

  const adminSlots = adminSlotsQuery.data?.timetable;
  const facultySlots = facultySlotsQuery.data?.timetable;
  const slots = useMemo(
    () => (canManage ? adminSlots ?? [] : facultySlots ?? []),
    [canManage, adminSlots, facultySlots]
  );

  const selectedEntry = useMemo(
    () => slots.find((s) => s.id === entryId) ?? null,
    [slots, entryId]
  );

  const [open, setOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [statuses, setStatuses] = useState<Record<number, AttendanceStatus>>({});
  const [roster, setRoster] = useState<
    Array<{ student_profile_id: number; application_number: string | null; name: string; email: string; status: AttendanceStatus | null }>
  >([]);
  const [sheetId, setSheetId] = useState<number | null>(null);

  const loadSheet = async () => {
    if (!entryId) return;
    try {
      const data = await attendanceService.openSheet(entryId, date);
      setSheetId(data.sheet.id);
      setRoster(data.roster);
      setStatuses(
        Object.fromEntries(
          data.roster.map((r) => [r.student_profile_id, r.status ?? "present"])
        ) as Record<number, AttendanceStatus>
      );
      setOpen(true);
    } catch (e) {
      toast.error(
        getApiErrorMessage(e, "Unable to open the register.")
      );
    }
  };

  const setStatus = (id: number, status: AttendanceStatus) => {
    setStatuses((prev) => ({ ...prev, [id]: status }));
  };

  const markAllPresent = () => {
    setStatuses((prev) =>
      Object.fromEntries(Object.keys(prev).map((k) => [k, "present"])) as Record<number, AttendanceStatus>
    );
  };

  const save = async () => {
    if (!sheetId) return;
    setSaving(true);
    try {
      const records = Object.entries(statuses).map(([id, status]) => ({
        student_profile_id: Number(id),
        status,
      }));
      const result = await attendanceService.saveSheet(sheetId, records);
      toast.success(`Attendance saved (${result.saved} students).`);
      await qc.invalidateQueries({ queryKey: ["attendance", "summary"] });
    } catch (e) {
      toast.error(
        getApiErrorMessage(e, "Unable to save attendance.")
      );
    } finally {
      setSaving(false);
    }
  };

  const presentCount = Object.values(statuses).filter((s) => s === "present").length;
  const lateCount = Object.values(statuses).filter((s) => s === "late").length;
  const absentCount = Object.values(statuses).filter((s) => s === "absent").length;

  return (
    <>
      <div className="institution-directory-toolbar attendance-toolbar">
        {canManage && (
          <>
            <label htmlFor="am-programme" className="institution-mono attendance-filter-label">Programme</label>
            <select
              id="am-programme"
              className="filter-select"
              value={programmeId ?? ""}
              onChange={(e) => { setProgrammeId(e.target.value ? Number(e.target.value) : null); setEntryId(null); }}
            >
              <option value="">Select programme...</option>
              {(programmesQuery.data?.programmes ?? []).map((p) => (
                <option key={p.id} value={p.id}>{p.code} — {p.name}</option>
              ))}
            </select>

            <label htmlFor="am-semester" className="institution-mono attendance-filter-label">Sem</label>
            <select id="am-semester" className="filter-select" value={semester}
              onChange={(e) => { setSemester(Number(e.target.value)); setEntryId(null); }}>
              {[1,2,3,4,5,6,7,8].map((n) => <option key={n} value={n}>{n}</option>)}
            </select>

            <label htmlFor="am-section" className="institution-mono attendance-filter-label">Section</label>
            <select id="am-section" className="filter-select" value={section}
              onChange={(e) => { setSection(e.target.value); setEntryId(null); }}>
              {["A", "B", "C"].map((s) => <option key={s} value={s}>{s}</option>)}
            </select>
          </>
        )}

        <label htmlFor="am-slot" className="institution-mono attendance-filter-label">
          {canManage ? "Class slot" : "My slot"}
        </label>
        <select
          id="am-slot"
          className="filter-select attendance-slot-select"
          value={entryId ?? ""}
          onChange={(e) => setEntryId(e.target.value ? Number(e.target.value) : null)}
          aria-label="Class slot to mark"
        >
          <option value="">
            {canManage
              ? (programmeId === null ? "Select a programme first" : "Select slot...")
              : (slots.length === 0 ? "No slots assigned to you yet" : "Select slot...")}
          </option>
          {slots.map((s) => (
            <option key={s.id} value={s.id}>
              {dayName(s.day_of_week)} P{s.period_no} · {s.subject?.code} — {s.subject?.name}
              {s.room ? ` (${s.room})` : ""} · Sem {s.semester} · {s.section}
            </option>
          ))}
        </select>

        <label htmlFor="am-date" className="institution-mono attendance-filter-label">Date</label>
        <input
          id="am-date"
          type="date"
          className="filter-select"
          max={new Date().toISOString().slice(0, 10)}
          value={date}
          onChange={(e) => setDate(e.target.value)}
        />

        <Button className="btn-primary" onClick={loadSheet} disabled={!entryId}>
          <ClipboardCheck size={16} /> Open register
        </Button>
      </div>

      {!open && (
        <p className="attendance-muted-message">
          {isFaculty && !canManage
            ? "Choose one of your assigned slots and a date to open the register."
            : "Choose a programme, semester, slot and date to open the register."}
        </p>
      )}

      {open && selectedEntry && (
        <div className="institution-admin-panel">
          <div className="attendance-register-header">
            <div>
              <h3 className="attendance-register-title">
                {selectedEntry.subject?.name}
                <span className="institution-mono attendance-register-meta">
                  {dayName(selectedEntry.day_of_week)} P{selectedEntry.period_no} · {String(date)} · Section {selectedEntry.section}
                </span>
              </h3>
              <div className="attendance-register-subtitle">
                {selectedEntry.subject?.code} · {roster.length} students in roster
              </div>
            </div>
            <div className="attendance-register-actions">
              <Badge tone="success">{presentCount} present</Badge>
              <Badge tone="info">{lateCount} late</Badge>
              <Badge tone="danger">{absentCount} absent</Badge>
              <Button variant="secondary" onClick={markAllPresent} title="Set everyone to present">
                <CheckCheck size={15} /> All present
              </Button>
              <Button onClick={save} disabled={saving}>
                <Save size={15} /> {saving ? "Saving..." : "Save"}
              </Button>
            </div>
          </div>

          <table className="attendance-record-table">
            <thead>
              <tr>
                <th className="attendance-table-heading">Student</th>
                <th className="attendance-table-heading">Application #</th>
                <th className="attendance-table-heading attendance-cell-centered">Status</th>
              </tr>
            </thead>
            <tbody>
              {roster.map((r) => {
                const status = statuses[r.student_profile_id] ?? "present";
                return (
                  <tr key={r.student_profile_id}>
                    <td className="attendance-table-cell">
                      <strong>{r.name}</strong>
                      <div className="institution-mono attendance-secondary-text">{r.email}</div>
                    </td>
                    <td className="attendance-table-cell"><span className="institution-mono">{r.application_number ?? "—"}</span></td>
                    <td className="attendance-table-cell attendance-cell-centered">
                      <div className="attendance-segment" role="group" aria-label={`Attendance for ${r.name}`}>
                        {(["present", "late", "absent"] as AttendanceStatus[]).map((s) => (
                          <button
                            key={s}
                            type="button"
                            className={`attendance-segment-btn attendance-segment-${s}${status === s ? " attendance-segment-active" : ""}`}
                            onClick={() => setStatus(r.student_profile_id, s)}
                            aria-pressed={status === s}
                          >
                            {STATUS_META[s].short}
                          </button>
                        ))}
                      </div>
                    </td>
                  </tr>
                );
              })}
              {roster.length === 0 && (
                <tr>
                  <td className="attendance-table-cell" colSpan={3}>
                    <p className="attendance-muted-message">No students enrolled in this programme.</p>
                  </td>
                </tr>
              )}
            </tbody>
          </table>

          <p className="attendance-table-note">
            Tip: use "All present" first, then flip the absentees and latecomers with the P / L / A buttons.
          </p>
        </div>
      )}

      {(adminSlotsQuery.isLoading || facultySlotsQuery.isLoading) && (
        <div className="dashboard-state"><Spinner size={22} /><p>Loading slots...</p></div>
      )}
    </>
  );
};


export default AttendanceMarkPage;
