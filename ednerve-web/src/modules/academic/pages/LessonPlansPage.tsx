import "../academic.css";
import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { BookMarked, Plus, Pencil, Trash2, Search } from "lucide-react";
import { toast } from "sonner";

import { academicService } from "../api";
import type { LessonPlanEntry, LessonPlanStatus } from "../types";
import { institutionService } from "../../institution/api";
import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin, isFacultyLike } from "../../../shared/lib/rbac";
import { getApiErrorMessage } from "../../../shared/lib/api-error";
import { useDebouncedValue } from "../../../shared/lib/hooks/useDebouncedValue";
import { DataTable, FilterSelect } from "../../../shared/crud";
import type { DataTableColumn } from "../../../shared/crud";
import { Alert, Badge, Button, FormField, Input, Modal, SearchInput } from "../../../shared/ui";

const EMPTY_LIST: never[] = [];

const STAFF_ROLES = ["faculty", "hod", "dean", "registrar", "examination_officer", "institution_admin"];
const STATUSES: LessonPlanStatus[] = ["planned", "in_progress", "completed", "deferred"];
const statusTone: Record<LessonPlanStatus, "neutral" | "info" | "success" | "warning"> = {
  planned: "neutral",
  in_progress: "info",
  completed: "success",
  deferred: "warning",
};

type LessonPlanPayload = {
  subject_id: number;
  academic_session_id: number | null;
  faculty_user_id: number | null;
  programme_id: number | null;
  semester: number | null;
  section: string | null;
  unit_number: string | null;
  topic: string;
  planned_hours: number;
  taught_hours: number;
  planned_date: string | null;
  taught_date: string | null;
  status: LessonPlanStatus;
  remarks: string | null;
};

const emptyForm = {
  subject_id: "" as string | number, academic_session_id: "" as string | number, faculty_user_id: "" as string | number,
  programme_id: "" as string | number, semester: "", section: "", unit_number: "", topic: "",
  planned_hours: "0", taught_hours: "0", planned_date: "", taught_date: "", status: "planned" as LessonPlanStatus, remarks: "",
};

const LessonPlansPage = () => {
  const { user } = useAuth();
  const qc = useQueryClient();
  const canManage = isInstitutionAdmin(user) || isFacultyLike(user);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const debounced = useDebouncedValue(search.trim(), 300);

  const entriesQ = useQuery({ queryKey: ["academic", "lesson-plans", statusFilter], queryFn: () => academicService.getLessonPlans({ status: statusFilter !== "all" ? statusFilter : undefined }) });
  const sessionsQ = useQuery({ queryKey: ["academic", "sessions"], queryFn: () => academicService.getSessions() });
  const subjectsQ = useQuery({ queryKey: ["academic", "subjects"], queryFn: () => academicService.getSubjects() });
  const programmesQ = useQuery({ queryKey: ["academic", "programmes"], queryFn: () => academicService.getProgrammes() });
  const usersQ = useQuery({ queryKey: ["institution", "users", "lesson-plans"], queryFn: () => institutionService.getAllUsers(), enabled: !!canManage });

  const entries = entriesQ.data?.entries ?? EMPTY_LIST;
  const staff = useMemo(
    () => (usersQ.data ?? []).filter((u) =>
      u.active_role_assignments?.some((assignment) =>
        STAFF_ROLES.includes(assignment.role?.code ?? "")
      )
    ),
    [usersQ.data]
  );
  const filtered = useMemo(() => {
    const t = debounced.toLowerCase();
    return entries.filter((e) => !t || e.topic.toLowerCase().includes(t) || (e.subject?.name ?? e.subject?.code ?? "").toLowerCase().includes(t) || (e.unit_number ?? "").toLowerCase().includes(t));
  }, [entries, debounced]);

  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<LessonPlanEntry | null>(null);
  const [form, setForm] = useState(emptyForm);
  const [err, setErr] = useState("");

  const openCreate = () => { setEditing(null); setForm(emptyForm); setErr(""); setOpen(true); };
  const openEdit = (e: LessonPlanEntry) => {
    setEditing(e);
    setForm({
      subject_id: e.subject_id, academic_session_id: e.academic_session_id ?? "", faculty_user_id: e.faculty_user_id ?? "",
      programme_id: e.programme_id ?? "", semester: e.semester?.toString() ?? "", section: e.section ?? "", unit_number: e.unit_number ?? "",
      topic: e.topic, planned_hours: String(e.planned_hours ?? 0), taught_hours: String(e.taught_hours ?? 0),
      planned_date: e.planned_date ? String(e.planned_date).slice(0,10) : "", taught_date: e.taught_date ? String(e.taught_date).slice(0,10) : "",
      status: e.status, remarks: e.remarks ?? "",
    });
    setErr(""); setOpen(true);
  };

  const save = useMutation({
    mutationFn: async () => {
      const payload: LessonPlanPayload = {
        subject_id: Number(form.subject_id), academic_session_id: form.academic_session_id === "" ? null : Number(form.academic_session_id),
        faculty_user_id: form.faculty_user_id === "" ? null : Number(form.faculty_user_id), programme_id: form.programme_id === "" ? null : Number(form.programme_id),
        semester: form.semester ? Number(form.semester) : null, section: form.section.trim() || null, unit_number: form.unit_number.trim() || null,
        topic: form.topic.trim(), planned_hours: Number(form.planned_hours || 0), taught_hours: Number(form.taught_hours || 0),
        planned_date: form.planned_date || null, taught_date: form.taught_date || null, status: form.status, remarks: form.remarks.trim() || null,
      };
      if (editing) return academicService.updateLessonPlan(editing.id, payload);
      return academicService.createLessonPlan(payload);
    },
    onSuccess: async () => { await qc.invalidateQueries({ queryKey: ["academic", "lesson-plans"] }); toast.success(editing ? "Lesson plan updated." : "Lesson plan entry created."); setOpen(false); },
    onError: (e) => setErr(getApiErrorMessage(e, "Unable to save lesson plan entry.")),
  });
  const del = useMutation({
    mutationFn: (e: LessonPlanEntry) => academicService.deleteLessonPlan(e.id),
    onSuccess: async () => { await qc.invalidateQueries({ queryKey: ["academic", "lesson-plans"] }); toast.success("Lesson plan entry deleted."); },
    onError: (e) => toast.error(getApiErrorMessage(e, "Unable to delete lesson plan entry.")),
  });

  const cols: DataTableColumn<LessonPlanEntry>[] = [
    { key: "topic", header: "Topic", render: (e) => <div className="institution-name-cell"><div className="institution-avatar"><BookMarked size={17} /></div><div><strong>{e.unit_number ? `${e.unit_number}: ` : ""}{e.topic}</strong><span className="institution-mono">{e.subject?.code ?? ""} {e.subject?.name ?? ""}</span></div></div> },
    { key: "context", header: "Context", render: (e) => <span>{e.programme?.code ?? "—"} {e.semester ? `Sem ${e.semester}` : ""} {e.section ? `Sec ${e.section}` : ""} {e.academic_session ? `· ${e.academic_session.code}` : ""}{e.faculty ? ` · ${e.faculty.name}` : ""}</span> },
    { key: "progress", header: "Hours (planned/taught)", render: (e) => <span className="institution-mono">{Number(e.planned_hours)}/{Number(e.taught_hours)}</span> },
    { key: "status", header: "Status", render: (e) => <Badge tone={statusTone[e.status]}>{e.status.replace("_", " ")}</Badge> },
    ...(canManage ? [{ key: "actions", header: "", render: (e: LessonPlanEntry) => <span className="institution-row-actions"><button type="button" className="institution-icon-button" onClick={() => openEdit(e)}><Pencil size={16} /></button><button type="button" className="institution-icon-button danger" onClick={() => { if (window.confirm("Delete this lesson plan entry?")) del.mutate(e); }}><Trash2 size={16} /></button></span> } as DataTableColumn<LessonPlanEntry>] : []),
  ];

  if (entriesQ.isError) return <Alert tone="error" className="institution-directory-error">Unable to load lesson plans. <button type="button" onClick={() => void entriesQ.refetch()}>Retry</button></Alert>;

  return (
    <>
      <div className="institution-directory-toolbar">
        <SearchInput icon={Search} placeholder="Search topic, unit, subject..." value={search} onChange={(e) => setSearch(e.target.value)} aria-label="Search lesson plans" />
        <FilterSelect aria-label="Filter status" value={statusFilter} onChange={setStatusFilter}><option value="all">All Statuses</option>{STATUSES.map(s => <option key={s} value={s}>{s.replace("_", " ")}</option>)}</FilterSelect>
        {canManage && <Button type="button" onClick={openCreate}><Plus size={16} /> Add Entry</Button>}
      </div>
      <DataTable<LessonPlanEntry> columns={cols} rows={filtered} rowKey={(e) => e.id} loading={entriesQ.isLoading} emptyMessage="No lesson plan entries yet." />

      <Modal open={open} title={editing ? "Edit Lesson Plan Entry" : "Add Lesson Plan Entry"} onClose={() => setOpen(false)}
        footer={<><Button variant="secondary" onClick={() => setOpen(false)}>Cancel</Button><Button onClick={() => { setErr(""); if (!form.subject_id) { setErr("Subject is required."); return; } if (!form.topic.trim()) { setErr("Topic is required."); return; } save.mutate(); }} disabled={save.isPending}>{save.isPending ? "Saving..." : "Save"}</Button></>}>
        {err && <div className="institution-form-alert" role="alert">{err}</div>}
        <div className="institution-form-grid">
          <FormField label="Subject" htmlFor="lp-subj" required><select id="lp-subj" className="filter-select" value={form.subject_id} onChange={(e)=>setForm(c=>({...c, subject_id:e.target.value}))}><option value="">Select...</option>{(subjectsQ.data?.subjects ?? []).map(s => <option key={s.id} value={s.id}>{s.code} — {s.name}</option>)}</select></FormField>
          <FormField label="Unit / module" htmlFor="lp-unit"><Input id="lp-unit" value={form.unit_number} onChange={(e)=>setForm(c=>({...c, unit_number:e.target.value}))} placeholder="Unit 1" /></FormField>
        </div>
        <FormField label="Topic" htmlFor="lp-topic" required><Input id="lp-topic" value={form.topic} onChange={(e)=>setForm(c=>({...c, topic:e.target.value}))} /></FormField>
        <div className="institution-form-grid">
          <FormField label="Academic session" htmlFor="lp-session"><select id="lp-session" className="filter-select" value={form.academic_session_id} onChange={(e)=>setForm(c=>({...c, academic_session_id:e.target.value}))}><option value="">None</option>{(sessionsQ.data?.sessions ?? []).map(s => <option key={s.id} value={s.id}>{s.code}</option>)}</select></FormField>
          <FormField label="Faculty" htmlFor="lp-fac"><select id="lp-fac" className="filter-select" value={form.faculty_user_id} onChange={(e)=>setForm(c=>({...c, faculty_user_id:e.target.value}))}><option value="">None</option>{staff.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}</select></FormField>
        </div>
        <div className="institution-form-grid">
          <FormField label="Programme" htmlFor="lp-prog"><select id="lp-prog" className="filter-select" value={form.programme_id} onChange={(e)=>setForm(c=>({...c, programme_id:e.target.value}))}><option value="">None</option>{(programmesQ.data?.programmes ?? []).map(p => <option key={p.id} value={p.id}>{p.code}</option>)}</select></FormField>
          <FormField label="Semester" htmlFor="lp-sem"><Input id="lp-sem" type="number" min={1} value={form.semester} onChange={(e)=>setForm(c=>({...c, semester:e.target.value}))} /></FormField>
        </div>
        <div className="institution-form-grid">
          <FormField label="Section" htmlFor="lp-sec"><Input id="lp-sec" value={form.section} onChange={(e)=>setForm(c=>({...c, section:e.target.value}))} /></FormField>
          <FormField label="Status" htmlFor="lp-status"><select id="lp-status" className="filter-select" value={form.status} onChange={(e)=>setForm(c=>({...c, status: e.target.value as LessonPlanStatus}))}>{STATUSES.map(s => <option key={s} value={s}>{s.replace("_"," ")}</option>)}</select></FormField>
        </div>
        <div className="institution-form-grid">
          <FormField label="Planned hours" htmlFor="lp-ph"><Input id="lp-ph" type="number" step="0.25" min={0} value={form.planned_hours} onChange={(e)=>setForm(c=>({...c, planned_hours:e.target.value}))} /></FormField>
          <FormField label="Taught hours" htmlFor="lp-th"><Input id="lp-th" type="number" step="0.25" min={0} value={form.taught_hours} onChange={(e)=>setForm(c=>({...c, taught_hours:e.target.value}))} /></FormField>
        </div>
        <div className="institution-form-grid">
          <FormField label="Planned date" htmlFor="lp-pd"><Input id="lp-pd" type="date" value={form.planned_date} onChange={(e)=>setForm(c=>({...c, planned_date:e.target.value}))} /></FormField>
          <FormField label="Taught date" htmlFor="lp-td"><Input id="lp-td" type="date" value={form.taught_date} onChange={(e)=>setForm(c=>({...c, taught_date:e.target.value}))} /></FormField>
        </div>
        <FormField label="Remarks" htmlFor="lp-rem"><textarea id="lp-rem" className="form-control" rows={2} value={form.remarks} onChange={(e)=>setForm(c=>({...c, remarks:e.target.value}))} /></FormField>
      </Modal>
    </>
  );
};

export default LessonPlansPage;
