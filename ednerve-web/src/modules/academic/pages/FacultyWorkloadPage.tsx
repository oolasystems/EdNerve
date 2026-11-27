import "../academic.css";
import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Briefcase, Plus, Pencil, Trash2, Search } from "lucide-react";
import { toast } from "sonner";

import { academicService } from "../api";
import type { FacultyWorkloadEntry, AcademicStatus } from "../types";
import { institutionService } from "../../institution/api";
import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin, isFacultyLike } from "../../../shared/lib/rbac";
import { getApiErrorMessage } from "../../../shared/lib/api-error";
import { useDebouncedValue } from "../../../shared/lib/hooks/useDebouncedValue";
import { DataTable, FilterSelect } from "../../../shared/crud";
import type { DataTableColumn } from "../../../shared/crud";
import { Alert, Badge, Button, FormField, Input, Modal, SearchInput } from "../../../shared/ui";

const EMPTY_LIST: never[] = [];

const STAFF_ROLES = ["faculty", "hod", "dean", "registrar", "examination_officer", "institution_admin"] as const;

type FacultyWorkloadPayload = {
  user_id: number;
  academic_session_id: number | null;
  subject_id: number | null;
  programme_id: number | null;
  semester: number | null;
  section: string | null;
  periods_per_week: number;
  teaching_hours_per_week: number | null;
  role: string | null;
  notes: string | null;
  status: AcademicStatus;
};
const emptyForm = {
  user_id: "" as string | number, academic_session_id: "" as string | number, subject_id: "" as string | number,
  programme_id: "" as string | number, semester: "", section: "", periods_per_week: "0", teaching_hours_per_week: "",
  role: "", notes: "", status: "active" as AcademicStatus,
};

const FacultyWorkloadPage = () => {
  const { user } = useAuth();
  const qc = useQueryClient();
  const canManage = isInstitutionAdmin(user) || isFacultyLike(user);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const debounced = useDebouncedValue(search.trim(), 300);

  const entriesQ = useQuery({ queryKey: ["academic", "workload", statusFilter], queryFn: () => academicService.getFacultyWorkload({ status: statusFilter !== "all" ? statusFilter : undefined }) });
  const sessionsQ = useQuery({ queryKey: ["academic", "sessions"], queryFn: () => academicService.getSessions() });
  const subjectsQ = useQuery({ queryKey: ["academic", "subjects"], queryFn: () => academicService.getSubjects() });
  const programmesQ = useQuery({ queryKey: ["academic", "programmes"], queryFn: () => academicService.getProgrammes() });
  const usersQ = useQuery({ queryKey: ["institution", "users", "workload"], queryFn: () => institutionService.getAllUsers(), enabled: !!canManage });

  const entries = entriesQ.data?.entries ?? EMPTY_LIST;
  const staff = useMemo(
    () => (usersQ.data ?? []).filter((u) =>
      u.active_role_assignments?.some((assignment) =>
        STAFF_ROLES.some((staffRole) => staffRole === assignment.role?.code)
      )
    ),
    [usersQ.data]
  );
  const filtered = useMemo(() => {
    const t = debounced.toLowerCase();
    return entries.filter((e) => !t || (e.user?.name ?? "").toLowerCase().includes(t) || (e.subject?.name ?? e.subject?.code ?? "").toLowerCase().includes(t) || (e.programme?.code ?? "").toLowerCase().includes(t));
  }, [entries, debounced]);

  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<FacultyWorkloadEntry | null>(null);
  const [form, setForm] = useState(emptyForm);
  const [err, setErr] = useState("");

  const openCreate = () => { setEditing(null); setForm(emptyForm); setErr(""); setOpen(true); };
  const openEdit = (e: FacultyWorkloadEntry) => {
    setEditing(e);
    setForm({
      user_id: e.user_id, academic_session_id: e.academic_session_id ?? "", subject_id: e.subject_id ?? "", programme_id: e.programme_id ?? "",
      semester: e.semester?.toString() ?? "", section: e.section ?? "", periods_per_week: String(e.periods_per_week ?? 0),
      teaching_hours_per_week: e.teaching_hours_per_week?.toString() ?? "", role: e.role ?? "", notes: e.notes ?? "", status: e.status,
    });
    setErr(""); setOpen(true);
  };

  const save = useMutation({
    mutationFn: async () => {
      const payload: FacultyWorkloadPayload = {
        user_id: Number(form.user_id),
        academic_session_id: form.academic_session_id === "" ? null : Number(form.academic_session_id),
        subject_id: form.subject_id === "" ? null : Number(form.subject_id),
        programme_id: form.programme_id === "" ? null : Number(form.programme_id),
        semester: form.semester ? Number(form.semester) : null,
        section: form.section.trim() || null,
        periods_per_week: Number(form.periods_per_week || 0),
        teaching_hours_per_week: form.teaching_hours_per_week === "" ? null : Number(form.teaching_hours_per_week),
        role: form.role.trim() || null,
        notes: form.notes.trim() || null,
        status: form.status,
      };
      if (editing) return academicService.updateFacultyWorkload(editing.id, payload);
      return academicService.createFacultyWorkload(payload);
    },
    onSuccess: async () => { await qc.invalidateQueries({ queryKey: ["academic", "workload"] }); toast.success(editing ? "Workload updated." : "Workload entry created."); setOpen(false); },
    onError: (e) => setErr(getApiErrorMessage(e, "Unable to save workload entry.")),
  });

  const del = useMutation({
    mutationFn: (e: FacultyWorkloadEntry) => academicService.deleteFacultyWorkload(e.id),
    onSuccess: async () => { await qc.invalidateQueries({ queryKey: ["academic", "workload"] }); toast.success("Workload entry deleted."); },
    onError: (e) => toast.error(getApiErrorMessage(e, "Unable to delete workload entry.")),
  });

  const cols: DataTableColumn<FacultyWorkloadEntry>[] = [
    { key: "faculty", header: "Faculty", render: (e) => <div className="institution-name-cell"><div className="institution-avatar"><Briefcase size={17} /></div><div><strong>{e.user?.name ?? "—"}</strong><span className="institution-mono">{e.subject ? `${e.subject.code} ${e.subject.name}` : "General"}</span></div></div> },
    { key: "context", header: "Context", render: (e) => <span>{e.programme?.code ?? "—"} {e.semester ? `Sem ${e.semester}` : ""} {e.section ? `Sec ${e.section}` : ""} {e.academic_session ? `· ${e.academic_session.code}` : ""}</span> },
    { key: "load", header: "Load", render: (e) => <span className="institution-mono">{e.periods_per_week} periods/wk{e.teaching_hours_per_week ? ` · ${e.teaching_hours_per_week} hrs` : ""}{e.role ? ` · ${e.role}` : ""}</span> },
    { key: "status", header: "Status", render: (e) => <Badge tone={e.status === "active" ? "success" : "warning"}>{e.status}</Badge> },
    ...(canManage ? [{ key: "actions", header: "", render: (e: FacultyWorkloadEntry) => <span className="institution-row-actions"><button type="button" className="institution-icon-button" onClick={() => openEdit(e)}><Pencil size={16} /></button><button type="button" className="institution-icon-button danger" onClick={() => { if (window.confirm("Delete this workload entry?")) del.mutate(e); }}><Trash2 size={16} /></button></span> } as DataTableColumn<FacultyWorkloadEntry>] : []),
  ];

  if (entriesQ.isError) return <Alert tone="error" className="institution-directory-error">Unable to load faculty workload. <button type="button" onClick={() => void entriesQ.refetch()}>Retry</button></Alert>;

  return (
    <>
      <div className="institution-directory-toolbar">
        <SearchInput icon={Search} placeholder="Search faculty, subject, programme..." value={search} onChange={(e) => setSearch(e.target.value)} aria-label="Search workload" />
        <FilterSelect aria-label="Filter status" value={statusFilter} onChange={setStatusFilter}><option value="all">All Statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></FilterSelect>
        {canManage && <Button type="button" onClick={openCreate}><Plus size={16} /> Add Workload</Button>}
      </div>
      <DataTable<FacultyWorkloadEntry> columns={cols} rows={filtered} rowKey={(e) => e.id} loading={entriesQ.isLoading} emptyMessage="No faculty workload entries yet." />

      <Modal open={open} title={editing ? "Edit Workload Entry" : "Add Workload Entry"} onClose={() => setOpen(false)}
        footer={<><Button variant="secondary" onClick={() => setOpen(false)}>Cancel</Button><Button onClick={() => { setErr(""); if (!form.user_id) { setErr("Faculty/staff is required."); return; } save.mutate(); }} disabled={save.isPending}>{save.isPending ? "Saving..." : "Save"}</Button></>}>
        {err && <div className="institution-form-alert" role="alert">{err}</div>}
        <FormField label="Faculty / staff" htmlFor="wl-user" required>
          <select id="wl-user" className="filter-select" value={form.user_id} onChange={(e) => setForm(c => ({...c, user_id: e.target.value}))}>
            <option value="">Select...</option>{staff.map((u) => <option key={u.id} value={u.id}>{u.name} ({u.email})</option>)}
          </select>
        </FormField>
        <div className="institution-form-grid">
          <FormField label="Academic session" htmlFor="wl-session"><select id="wl-session" className="filter-select" value={form.academic_session_id} onChange={(e)=>setForm(c=>({...c, academic_session_id:e.target.value}))}><option value="">None</option>{(sessionsQ.data?.sessions ?? []).map(s => <option key={s.id} value={s.id}>{s.code}</option>)}</select></FormField>
          <FormField label="Subject" htmlFor="wl-subject"><select id="wl-subject" className="filter-select" value={form.subject_id} onChange={(e)=>setForm(c=>({...c, subject_id:e.target.value}))}><option value="">None</option>{(subjectsQ.data?.subjects ?? []).map(s => <option key={s.id} value={s.id}>{s.code} — {s.name}</option>)}</select></FormField>
        </div>
        <div className="institution-form-grid">
          <FormField label="Programme" htmlFor="wl-prog"><select id="wl-prog" className="filter-select" value={form.programme_id} onChange={(e)=>setForm(c=>({...c, programme_id:e.target.value}))}><option value="">None</option>{(programmesQ.data?.programmes ?? []).map(p => <option key={p.id} value={p.id}>{p.code}</option>)}</select></FormField>
          <FormField label="Semester" htmlFor="wl-sem"><Input id="wl-sem" type="number" min={1} value={form.semester} onChange={(e)=>setForm(c=>({...c, semester:e.target.value}))} /></FormField>
        </div>
        <div className="institution-form-grid">
          <FormField label="Section" htmlFor="wl-sec"><Input id="wl-sec" value={form.section} onChange={(e)=>setForm(c=>({...c, section:e.target.value}))} placeholder="A" /></FormField>
          <FormField label="Role (optional)" htmlFor="wl-role"><Input id="wl-role" value={form.role} onChange={(e)=>setForm(c=>({...c, role:e.target.value}))} placeholder="Instructor / Coordinator" /></FormField>
        </div>
        <div className="institution-form-grid">
          <FormField label="Periods / week" htmlFor="wl-periods"><Input id="wl-periods" type="number" min={0} value={form.periods_per_week} onChange={(e)=>setForm(c=>({...c, periods_per_week:e.target.value}))} /></FormField>
          <FormField label="Teaching hours / week" htmlFor="wl-hours"><Input id="wl-hours" type="number" step="0.25" min={0} value={form.teaching_hours_per_week} onChange={(e)=>setForm(c=>({...c, teaching_hours_per_week:e.target.value}))} /></FormField>
        </div>
        <FormField label="Notes" htmlFor="wl-notes"><textarea id="wl-notes" className="form-control" rows={2} value={form.notes} onChange={(e)=>setForm(c=>({...c, notes:e.target.value}))} /></FormField>
        <FormField label="Status" htmlFor="wl-status"><select id="wl-status" className="filter-select" value={form.status} onChange={(e)=>setForm(c=>({...c, status: e.target.value as AcademicStatus}))}><option value="active">Active</option><option value="inactive">Inactive</option></select></FormField>
      </Modal>
    </>
  );
};

export default FacultyWorkloadPage;
