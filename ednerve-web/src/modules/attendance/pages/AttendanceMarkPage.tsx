import { useMemo, useState } from "react";
import { ClipboardCheck, Save, CheckCheck } from "lucide-react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { attendanceService } from "../api";
import type { AttendanceStatus } from "../api";
import "../../institution/institution.css";

import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin, isFacultyLike } from "../../../shared/lib/rbac";
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

  const slots = canManage
    ? adminSlotsQuery.data?.timetable ?? []
    : facultySlotsQuery.data?.timetable ?? [];

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
        (e as any)?.response?.data?.message ?? "Unable to open the register."
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
        (e as any)?.response?.data?.message ?? "Unable to save attendance."
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
      <div className="institution-directory-toolbar" style={{ gap: 10, flexWrap: "wrap" }}>
        {canManage && (
          <>
            <label htmlFor="am-programme" className="institution-mono" style={labelStyle}>Programme</label>
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

            <label htmlFor="am-semester" className="institution-mono" style={labelStyle}>Sem</label>
            <select id="am-semester" className="filter-select" value={semester}
              onChange={(e) => { setSemester(Number(e.target.value)); setEntryId(null); }}>
              {[1,2,3,4,5,6,7,8].map((n) => <option key={n} value={n}>{n}</option>)}
            </select>

            <label htmlFor="am-section" className="institution-mono" style={labelStyle}>Section</label>
            <select id="am-section" className="filter-select" value={section}
              onChange={(e) => { setSection(e.target.value); setEntryId(null); }}>
              {["A", "B", "C"].map((s) => <option key={s} value={s}>{s}</option>)}
            </select>
          </>
        )}

        <label htmlFor="am-slot" className="institution-mono" style={labelStyle}>
          {canManage ? "Class slot" : "My slot"}
        </label>
        <select
          id="am-slot"
          className="filter-select"
          style={{ minWidth: 260 }}
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

        <label htmlFor="am-date" className="institution-mono" style={labelStyle}>Date</label>
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
        <p style={{ color: "var(--text-muted)" }}>
          {isFaculty && !canManage
            ? "Choose one of your assigned slots and a date to open the register."
            : "Choose a programme, semester, slot and date to open the register."}
        </p>
      )}

      {open && selectedEntry && (
        <div className="institution-admin-panel">
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", gap: 12, flexWrap: "wrap" }}>
            <div>
              <h3 style={{ marginBottom: 2 }}>
                {selectedEntry.subject?.name}
                <span className="institution-mono" style={{ fontSize: 12, color: "var(--text-muted)", marginLeft: 10 }}>
                  {dayName(selectedEntry.day_of_week)} P{selectedEntry.period_no} · {String(date)} · Section {selectedEntry.section}
                </span>
              </h3>
              <div style={{ fontSize: 12, color: "var(--text-secondary)" }}>
                {selectedEntry.subject?.code} · {roster.length} students in roster
              </div>
            </div>
            <div style={{ display: "flex", gap: 8, alignItems: "center" }}>
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

          <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "var(--font-size-sm)", marginTop: 12 }}>
            <thead>
              <tr>
                <th style={th}>Student</th>
                <th style={th}>Application #</th>
                <th style={{ ...th, textAlign: "center" }}>Status</th>
              </tr>
            </thead>
            <tbody>
              {roster.map((r) => {
                const status = statuses[r.student_profile_id] ?? "present";
                return (
                  <tr key={r.student_profile_id}>
                    <td style={td}>
                      <strong>{r.name}</strong>
                      <div className="institution-mono" style={{ fontSize: 11 }}>{r.email}</div>
                    </td>
                    <td style={td}><span className="institution-mono">{r.application_number ?? "—"}</span></td>
                    <td style={{ ...td, textAlign: "center" }}>
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
                  <td style={td} colSpan={3}>
                    <p style={{ color: "var(--text-muted)" }}>No students enrolled in this programme.</p>
                  </td>
                </tr>
              )}
            </tbody>
          </table>

          <p style={{ color: "var(--text-muted)", fontSize: 12, marginTop: 8 }}>
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

const labelStyle: React.CSSProperties = {
  fontSize: "var(--font-size-xs)",
  color: "var(--text-secondary)",
  textTransform: "uppercase",
  letterSpacing: "0.05em",
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

export default AttendanceMarkPage;
