import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { CalendarRange, Plus, Pencil, Trash2, Search } from "lucide-react";
import { toast } from "sonner";

import { academicService } from "../api";
import type { AcademicCalendarEvent, AcademicCalendarEventType, AcademicStatus } from "../types";
import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin } from "../../../shared/lib/rbac";
import { getApiErrorMessage } from "../../../shared/lib/api-error";
import { useDebouncedValue } from "../../../shared/lib/hooks/useDebouncedValue";
import { DataTable, FilterSelect } from "../../../shared/crud";
import type { DataTableColumn } from "../../../shared/crud";
import { Alert, Badge, Button, FormField, Input, Modal, SearchInput } from "../../../shared/ui";

import "../../institution/institution.css";

const EMPTY_LIST: never[] = [];

const EVENT_TYPES: Array<{ value: AcademicCalendarEventType; label: string }> = [
  { value: "holiday", label: "Holiday" },
  { value: "exam", label: "Exam window" },
  { value: "registration", label: "Registration" },
  { value: "fee_deadline", label: "Fee deadline" },
  { value: "class", label: "Class/Teaching" },
  { value: "other", label: "Other" },
];

const emptyForm = {
  academic_session_id: "" as string | number,
  title: "",
  event_type: "holiday" as AcademicCalendarEventType,
  starts_on: "",
  ends_on: "",
  is_all_day: true,
  description: "",
  status: "active" as AcademicStatus,
};

const typeTone = (t: AcademicCalendarEventType): "warning" | "danger" | "info" | "success" | "neutral" => {
  if (t === "holiday") return "warning";
  if (t === "exam") return "danger";
  if (t === "registration" || t === "fee_deadline") return "info";
  if (t === "class") return "success";
  return "neutral";
};

const AcademicCalendarPage = () => {
  const { user } = useAuth();
  const qc = useQueryClient();
  const canManage = isInstitutionAdmin(user);

  const [searchInput, setSearchInput] = useState("");
  const [sessionFilter, setSessionFilter] = useState("all");
  const [typeFilter, setTypeFilter] = useState("all");
  const [statusFilter, setStatusFilter] = useState("all");
  const debouncedSearch = useDebouncedValue(searchInput.trim(), 300);

  const sessionsQuery = useQuery({ queryKey: ["academic", "sessions"], queryFn: () => academicService.getSessions() });
  const eventsQuery = useQuery({
    queryKey: ["academic", "calendar", sessionFilter, typeFilter, statusFilter],
    queryFn: () => academicService.getCalendarEvents({
      academic_session_id: sessionFilter !== "all" ? Number(sessionFilter) : undefined,
      event_type: typeFilter !== "all" ? typeFilter : undefined,
      status: statusFilter !== "all" ? statusFilter : undefined,
    }),
  });

  const sessions = sessionsQuery.data?.sessions ?? EMPTY_LIST;
  const events = eventsQuery.data?.events ?? EMPTY_LIST;

  const filtered = useMemo(() => {
    const term = debouncedSearch.toLowerCase();
    return events.filter((e) =>
      !term || e.title.toLowerCase().includes(term) || (e.description ?? "").toLowerCase().includes(term)
    );
  }, [events, debouncedSearch]);

  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<AcademicCalendarEvent | null>(null);
  const [form, setForm] = useState(emptyForm);
  const [formError, setFormError] = useState("");

  const openCreate = () => {
    setEditing(null);
    setForm(emptyForm);
    setFormError("");
    setModalOpen(true);
  };

  const openEdit = (event: AcademicCalendarEvent) => {
    setEditing(event);
    setForm({
      academic_session_id: event.academic_session_id ?? "",
      title: event.title,
      event_type: event.event_type,
      starts_on: String(event.starts_on).slice(0, 10),
      ends_on: event.ends_on ? String(event.ends_on).slice(0, 10) : "",
      is_all_day: event.is_all_day,
      description: event.description ?? "",
      status: event.status,
    });
    setFormError("");
    setModalOpen(true);
  };

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        academic_session_id: form.academic_session_id === "" ? null : Number(form.academic_session_id),
        title: form.title.trim(),
        event_type: form.event_type,
        starts_on: form.starts_on,
        ends_on: form.ends_on || null,
        is_all_day: form.is_all_day,
        description: form.description.trim() || null,
        status: form.status,
      };
      if (editing) return academicService.updateCalendarEvent(editing.id, payload);
      return academicService.createCalendarEvent(payload);
    },
    onSuccess: async () => {
      await qc.invalidateQueries({ queryKey: ["academic", "calendar"] });
      toast.success(editing ? "Calendar event updated." : "Calendar event created.");
      setModalOpen(false);
    },
    onError: (err) => setFormError(getApiErrorMessage(err, "Unable to save the event.")),
  });

  const deleteMutation = useMutation({
    mutationFn: (event: AcademicCalendarEvent) => academicService.deleteCalendarEvent(event.id),
    onSuccess: async () => {
      await qc.invalidateQueries({ queryKey: ["academic", "calendar"] });
      toast.success("Calendar event deleted.");
    },
    onError: (err) => toast.error(getApiErrorMessage(err, "Unable to delete the event.")),
  });

  const handleSave = () => {
    setFormError("");
    if (!form.title.trim()) { setFormError("Title is required."); return; }
    if (!form.starts_on) { setFormError("Start date is required."); return; }
    if (form.ends_on && form.ends_on < form.starts_on) { setFormError("End date cannot be before start date."); return; }
    saveMutation.mutate();
  };

  const columns: DataTableColumn<AcademicCalendarEvent>[] = [
    {
      key: "event",
      header: "Event",
      render: (event) => (
        <div className="institution-name-cell">
          <div className="institution-avatar"><CalendarRange size={17} /></div>
          <div>
            <strong>{event.title}</strong>
            <span className="institution-mono">{event.academic_session?.code ?? "All sessions"}</span>
          </div>
        </div>
      ),
    },
    {
      key: "type",
      header: "Type",
      render: (event) => <Badge tone={typeTone(event.event_type)}>{EVENT_TYPES.find(t => t.value === event.event_type)?.label ?? event.event_type}</Badge>,
    },
    {
      key: "dates",
      header: "Dates",
      render: (event) => (
        <span className="institution-mono">
          {String(event.starts_on).slice(0, 10)}{event.ends_on ? ` → ${String(event.ends_on).slice(0, 10)}` : ""}{event.is_all_day ? " · all day" : ""}
        </span>
      ),
    },
    {
      key: "status",
      header: "Status",
      render: (event) => <Badge tone={event.status === "active" ? "success" : "warning"}>{event.status}</Badge>,
    },
    ...(canManage ? [{
      key: "actions",
      header: "",
      render: (event: AcademicCalendarEvent) => (
        <span className="institution-row-actions">
          <button type="button" className="institution-icon-button" title="Edit event" onClick={() => openEdit(event)}><Pencil size={16} /></button>
          <button type="button" className="institution-icon-button danger" title="Delete event" onClick={() => { if (window.confirm("Delete this calendar event?")) deleteMutation.mutate(event); }}><Trash2 size={16} /></button>
        </span>
      ),
    } as DataTableColumn<AcademicCalendarEvent>] : []),
  ];

  if (eventsQuery.isError) {
    return (
      <Alert tone="error" className="institution-directory-error">
        Unable to load academic calendar.
        <button type="button" onClick={() => void eventsQuery.refetch()}>Retry</button>
      </Alert>
    );
  }

  return (
    <>
      <div className="institution-directory-toolbar">
        <SearchInput icon={Search} placeholder="Search events..." value={searchInput} onChange={(e) => setSearchInput(e.target.value)} aria-label="Search calendar events" />
        <FilterSelect aria-label="Filter by session" value={sessionFilter} onChange={setSessionFilter}>
          <option value="all">All Sessions</option>
          {sessions.map((s) => <option key={s.id} value={String(s.id)}>{s.code} — {s.name}</option>)}
        </FilterSelect>
        <FilterSelect aria-label="Filter by type" value={typeFilter} onChange={setTypeFilter}>
          <option value="all">All Types</option>
          {EVENT_TYPES.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
        </FilterSelect>
        <FilterSelect aria-label="Filter by status" value={statusFilter} onChange={setStatusFilter}>
          <option value="all">All Statuses</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </FilterSelect>
        {canManage && <Button type="button" onClick={openCreate}><Plus size={16} /> Add Event</Button>}
      </div>

      <DataTable<AcademicCalendarEvent>
        columns={columns}
        rows={filtered}
        rowKey={(event) => event.id}
        loading={eventsQuery.isLoading}
        error={eventsQuery.isError ? "Academic calendar could not be loaded." : null}
        emptyMessage="No calendar events yet. Add holidays, exam windows, registration or fee deadlines."
      />

      <Modal
        open={modalOpen}
        title={editing ? "Edit Calendar Event" : "Add Calendar Event"}
        onClose={() => setModalOpen(false)}
        footer={
          <>
            <Button type="button" variant="secondary" onClick={() => setModalOpen(false)}>Cancel</Button>
            <Button type="button" onClick={handleSave} disabled={saveMutation.isPending}>{saveMutation.isPending ? "Saving..." : "Save event"}</Button>
          </>
        }
      >
        {formError && <div className="institution-form-alert" role="alert">{formError}</div>}

        <FormField label="Title" htmlFor="cal-title" required>
          <Input id="cal-title" value={form.title} onChange={(e) => setForm((c) => ({ ...c, title: e.target.value }))} placeholder="e.g. Winter Recess, Mid-Semester Exams" />
        </FormField>

        <div className="institution-form-grid">
          <FormField label="Event type" htmlFor="cal-type" required>
            <select id="cal-type" className="filter-select" value={form.event_type} onChange={(e) => setForm((c) => ({ ...c, event_type: e.target.value as AcademicCalendarEventType }))}>
              {EVENT_TYPES.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
            </select>
          </FormField>
          <FormField label="Academic session" htmlFor="cal-session">
            <select id="cal-session" className="filter-select" value={form.academic_session_id} onChange={(e) => setForm((c) => ({ ...c, academic_session_id: e.target.value }))}>
              <option value="">All sessions / general</option>
              {sessions.map((s) => <option key={s.id} value={s.id}>{s.code} — {s.name}</option>)}
            </select>
          </FormField>
        </div>

        <div className="institution-form-grid">
          <FormField label="Starts on" htmlFor="cal-start" required>
            <Input id="cal-start" type="date" value={form.starts_on} onChange={(e) => setForm((c) => ({ ...c, starts_on: e.target.value }))} />
          </FormField>
          <FormField label="Ends on (optional)" htmlFor="cal-end">
            <Input id="cal-end" type="date" value={form.ends_on} onChange={(e) => setForm((c) => ({ ...c, ends_on: e.target.value }))} />
          </FormField>
        </div>

        <div className="institution-toggle-row" style={{ marginBottom: "var(--space-4)" }}>
          <input id="cal-allday" type="checkbox" checked={form.is_all_day} onChange={(e) => setForm((c) => ({ ...c, is_all_day: e.target.checked }))} />
          <label htmlFor="cal-allday">All-day event</label>
        </div>

        <FormField label="Description (optional)" htmlFor="cal-desc">
          <textarea id="cal-desc" className="form-control" rows={3} value={form.description} onChange={(e) => setForm((c) => ({ ...c, description: e.target.value }))} placeholder="Notes, instructions, or details" />
        </FormField>

        <FormField label="Status" htmlFor="cal-status">
          <select id="cal-status" className="filter-select" value={form.status} onChange={(e) => setForm((c) => ({ ...c, status: e.target.value as AcademicStatus }))}>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </FormField>
      </Modal>
    </>
  );
};

export default AcademicCalendarPage;
