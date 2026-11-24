import { useMemo, useState } from "react";
import { CalendarDays, Plus, Pencil, Trash2 } from "lucide-react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { attendanceService } from "../api";
import type { TimetableEntry } from "../api";
import "../../institution/institution.css";

import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin, isFacultyLike } from "../../../shared/lib/rbac";
import { getApiErrorMessage } from "../../../shared/lib/api-error";
import { academicService } from "../../academic/api";
import { institutionService } from "../../institution/api";

import {
  Button,
  Modal,
  FormField,
  Input,
  Spinner,
} from "../../../shared/ui";

const DAYS = [
  { value: 1, short: "Mon", label: "Monday" },
  { value: 2, short: "Tue", label: "Tuesday" },
  { value: 3, short: "Wed", label: "Wednesday" },
  { value: 4, short: "Thu", label: "Thursday" },
  { value: 5, short: "Fri", label: "Friday" },
  { value: 6, short: "Sat", label: "Saturday" },
];
const PERIODS = [1, 2, 3, 4, 5, 6, 7, 8];
const FACULTY_ROLES = ["faculty", "hod", "dean"];

const entryLabel = (e: TimetableEntry) =>
  `${DAYS.find((d) => d.value === e.day_of_week)?.short ?? e.day_of_week} · P${e.period_no}`;

const emptyForm = {
  semester: 3,
  section: "A",
  day_of_week: 1,
  period_no: 1,
  subject_id: "" as string | number,
  faculty_user_id: "" as string | number,
  room: "",
};

const TimetablePage = () => {
  const { user } = useAuth();
  const qc = useQueryClient();
  const canManage = isInstitutionAdmin(user);
  const isFaculty = isFacultyLike(user);

  const [programmeId, setProgrammeId] = useState<number | null>(null);
  const [semester, setSemester] = useState<number>(3);
  const [section, setSection] = useState("A");

  const programmesQuery = useQuery({
    queryKey: ["academic", "programmes"],
    queryFn: () => academicService.getProgrammes(),
  });

  const subjectsQuery = useQuery({
    queryKey: ["academic", "subjects"],
    queryFn: () => academicService.getSubjects(),
  });

  const usersQuery = useQuery({
    queryKey: ["institution", "users", "timetable-picker"],
    queryFn: () => institutionService.getAllUsers(),
    enabled: canManage,
  });

  const timetableQuery = useQuery({
    queryKey: ["attendance", "timetable", programmeId, semester, section],
    queryFn: () =>
      attendanceService.getTimetable({
        programme_id: programmeId ?? undefined,
        semester,
        section,
      }),
    enabled: programmeId !== null,
  });

  const entries = useMemo(() => timetableQuery.data?.timetable ?? [], [timetableQuery.data]);
  const grid = useMemo(() => {
    const map = new Map<string, TimetableEntry>();
    for (const entry of entries) {
      if (entry.status === "active") {
        map.set(`${entry.day_of_week}-${entry.period_no}`, entry);
      }
    }
    return map;
  }, [entries]);

  const staffOptions = useMemo(() => {
    const users = usersQuery.data ?? [];
    return users.filter((u) =>
      u.active_role_assignments?.some((a) =>
        FACULTY_ROLES.includes(a.role?.code ?? "")
      )
    );
  }, [usersQuery.data]);

  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<TimetableEntry | null>(null);
  const [form, setForm] = useState(emptyForm);
  const [error, setError] = useState("");

  const openAdd = () => {
    setEditing(null);
    setError("");
    setForm({ ...emptyForm, semester, section });
    setModalOpen(true);
  };

  const openEdit = (entry: TimetableEntry) => {
    setEditing(entry);
    setError("");
    setForm({
      semester: entry.semester,
      section: entry.section,
      day_of_week: entry.day_of_week,
      period_no: entry.period_no,
      subject_id: entry.subject_id,
      faculty_user_id: entry.faculty_user_id ?? "",
      room: entry.room ?? "",
    });
    setModalOpen(true);
  };

  const saveEntry = async () => {
    setError("");
    if (form.subject_id === "") {
      setError("Select a subject.");
      return;
    }
    try {
      const payload = {
        programme_id: programmeId!,
        semester: Number(form.semester),
        section: form.section.trim() || "A",
        day_of_week: Number(form.day_of_week),
        period_no: Number(form.period_no),
        subject_id: Number(form.subject_id),
        faculty_user_id: form.faculty_user_id === "" ? null : Number(form.faculty_user_id),
        room: form.room.trim() || undefined,
      };
      if (editing) {
        await attendanceService.updateEntry(editing.id, payload);
      } else {
        await attendanceService.createEntry(payload);
      }
      await qc.invalidateQueries({ queryKey: ["attendance", "timetable"] });
      toast.success(editing ? "Timetable slot updated." : "Timetable slot added.");
      setModalOpen(false);
    } catch (e) {
      setError(
        getApiErrorMessage(e, "Unable to save the timetable slot.")
      );
    }
  };

  const deleteEntry = async (entry: TimetableEntry) => {
    if (!window.confirm(`Remove ${entry.subject?.code ?? "slot"} from ${entryLabel(entry)}?`)) {
      return;
    }
    try {
      await attendanceService.deleteEntry(entry.id);
      await qc.invalidateQueries({ queryKey: ["attendance", "timetable"] });
      toast.success("Timetable slot removed.");
    } catch (e) {
      toast.error(
        getApiErrorMessage(e, "Unable to remove the slot.")
      );
    }
  };

  const subjects = subjectsQuery.data?.subjects ?? [];

  return (
    <>
      <div className="institution-directory-toolbar" style={{ gap: 10, flexWrap: "wrap" }}>
        <label htmlFor="tt-programme" className="institution-mono" style={labelStyle}>Programme</label>
        <select
          id="tt-programme"
          className="filter-select"
          value={programmeId ?? ""}
          onChange={(e) => setProgrammeId(e.target.value ? Number(e.target.value) : null)}
        >
          <option value="">Select programme...</option>
          {(programmesQuery.data?.programmes ?? []).map((p) => (
            <option key={p.id} value={p.id}>{p.code} — {p.name}</option>
          ))}
        </select>

        <label htmlFor="tt-semester" className="institution-mono" style={labelStyle}>Semester</label>
        <select id="tt-semester" className="filter-select" value={semester} onChange={(e) => setSemester(Number(e.target.value))}>
          {[1, 2, 3, 4, 5, 6, 7, 8].map((n) => (
            <option key={n} value={n}>Semester {n}</option>
          ))}
        </select>

        <label htmlFor="tt-section" className="institution-mono" style={labelStyle}>Section</label>
        <select id="tt-section" className="filter-select" value={section} onChange={(e) => setSection(e.target.value)}>
          {["A", "B", "C"].map((s) => <option key={s} value={s}>{s}</option>)}
        </select>

        {canManage && (
          <Button onClick={openAdd} disabled={programmeId === null}>
            <Plus size={16} /> Add slot
          </Button>
        )}
      </div>

      {programmeId === null && (
        <p style={{ color: "var(--text-muted)" }}>
          Select a programme to view its weekly timetable.
        </p>
      )}

      {programmeId !== null && timetableQuery.isLoading && (
        <div className="dashboard-state">
          <Spinner size={22} />
          <p>Loading timetable...</p>
        </div>
      )}

      {programmeId !== null && !timetableQuery.isLoading && (
        <div className="institution-admin-panel">
          <h3>
            <CalendarDays size={17} style={{ verticalAlign: "-3px" }} /> Weekly grid — Section {section}
            {isFaculty && !canManage && (
              <span className="institution-mono" style={{ fontSize: 11, color: "var(--text-muted)", marginLeft: 10 }}>
                read-only · your slots are highlighted
              </span>
            )}
          </h3>

          <div className="timetable-grid" role="grid" aria-label="Weekly timetable">
            <div className="timetable-cell timetable-head" />
            {DAYS.map((d) => (
              <div key={d.value} className="timetable-cell timetable-head">{d.short}</div>
            ))}
            {PERIODS.map((period) => (
              <div key={period} style={{ display: "contents" }}>
                <div className="timetable-cell timetable-period">P{period}</div>
                {DAYS.map((d) => {
                  const entry = grid.get(`${d.value}-${period}`);
                  const mine = entry && isFaculty && entry.faculty_user_id === user?.id;
                  return (
                    <div
                      key={d.value}
                      className={`timetable-cell timetable-slot${mine ? " timetable-slot-mine" : ""}`}
                    >
                      {entry && (
                        <>
                          <div className="timetable-subject">{entry.subject?.code}</div>
                          <div className="timetable-subject-name">{entry.subject?.name}</div>
                          <div className="timetable-meta">
                            {entry.room && <span>{entry.room}</span>}
                            {entry.faculty && <span>· {entry.faculty.name.split(" ").slice(-1)[0]}</span>}
                          </div>
                          {canManage && (
                            <div className="timetable-actions">
                              <button type="button" className="timetable-icon-btn" onClick={() => openEdit(entry)} aria-label="Edit slot">
                                <Pencil size={12} />
                              </button>
                              <button type="button" className="timetable-icon-btn" onClick={() => void deleteEntry(entry)} aria-label="Delete slot">
                                <Trash2 size={12} />
                              </button>
                            </div>
                          )}
                        </>
                      )}
                    </div>
                  );
                })}
              </div>
            ))}
          </div>

          <p style={{ color: "var(--text-muted)", fontSize: 12, marginTop: 10 }}>
            {entries.length} active slot{entries.length === 1 ? "" : "s"} this week · {facultyLegends(entries)} faculty assigned
          </p>
        </div>
      )}

      <Modal
        open={modalOpen}
        title={editing ? "Edit timetable slot" : "Add timetable slot"}
        onClose={() => setModalOpen(false)}
        footer={
          <>
            <Button variant="secondary" onClick={() => setModalOpen(false)}>Cancel</Button>
            <Button onClick={saveEntry}>{editing ? "Save changes" : "Add slot"}</Button>
          </>
        }
      >
        {error && <div className="institution-form-alert" role="alert">{error}</div>}

        <div className="institution-form-grid">
          <FormField label="Semester" htmlFor="tt-f-semester">
            <select id="tt-f-semester" className="filter-select" value={form.semester}
              onChange={(e) => setForm((c) => ({ ...c, semester: Number(e.target.value) }))}>
              {[1,2,3,4,5,6,7,8].map((n) => <option key={n} value={n}>Semester {n}</option>)}
            </select>
          </FormField>
          <FormField label="Section" htmlFor="tt-f-section">
            <Input id="tt-f-section" maxLength={20} value={form.section}
              onChange={(e) => setForm((c) => ({ ...c, section: e.target.value.toUpperCase() }))} />
          </FormField>
        </div>

        <div className="institution-form-grid">
          <FormField label="Day" htmlFor="tt-f-day">
            <select id="tt-f-day" className="filter-select" value={form.day_of_week}
              onChange={(e) => setForm((c) => ({ ...c, day_of_week: Number(e.target.value) }))}>
              {DAYS.map((d) => <option key={d.value} value={d.value}>{d.label}</option>)}
            </select>
          </FormField>
          <FormField label="Period" htmlFor="tt-f-period">
            <select id="tt-f-period" className="filter-select" value={form.period_no}
              onChange={(e) => setForm((c) => ({ ...c, period_no: Number(e.target.value) }))}>
              {PERIODS.map((p) => <option key={p} value={p}>Period {p}</option>)}
            </select>
          </FormField>
        </div>

        <FormField label="Subject" htmlFor="tt-f-subject" required>
          <select id="tt-f-subject" className="filter-select" value={form.subject_id}
            onChange={(e) => setForm((c) => ({ ...c, subject_id: e.target.value }))}>
            <option value="">Select subject...</option>
            {subjects.map((s) => (
              <option key={s.id} value={s.id}>{s.code} — {s.name}</option>
            ))}
          </select>
        </FormField>

        <div className="institution-form-grid">
          <FormField label="Faculty" htmlFor="tt-f-faculty">
            <select id="tt-f-faculty" className="filter-select" value={form.faculty_user_id}
              onChange={(e) => setForm((c) => ({ ...c, faculty_user_id: e.target.value }))}>
              <option value="">Unassigned</option>
              {staffOptions.map((u) => (
                <option key={u.id} value={u.id}>
                  {u.name}{u.active_role_assignments?.some(a => a.role?.code === "hod" || a.role?.code === "dean") ? " (HOD/Dean)" : ""}
                </option>
              ))}
            </select>
          </FormField>
          <FormField label="Room" htmlFor="tt-f-room">
            <Input id="tt-f-room" placeholder="FET-304" value={form.room}
              onChange={(e) => setForm((c) => ({ ...c, room: e.target.value }))} />
          </FormField>
        </div>

        <p style={{ color: "var(--text-muted)", fontSize: 12, marginTop: 8 }}>
          Slots are unique per programme · semester · section · day · period.
        </p>
      </Modal>
    </>
  );
};

const labelStyle: React.CSSProperties = {
  fontSize: "var(--font-size-xs)",
  color: "var(--text-secondary)",
  textTransform: "uppercase",
  letterSpacing: "0.05em",
};

const facultyLegends = (entries: TimetableEntry[]): number =>
  new Set(entries.map((e) => e.faculty_user_id).filter((v) => v !== null)).size;

export default TimetablePage;
