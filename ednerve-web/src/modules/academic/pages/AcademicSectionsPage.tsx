import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { LayoutGrid, Plus, Pencil, Trash2, Search } from "lucide-react";
import { toast } from "sonner";

import { academicService } from "../api";
import type { AcademicSection, AcademicStatus } from "../types";
import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin } from "../../../shared/lib/rbac";
import { getApiErrorMessage } from "../../../shared/lib/api-error";
import { useDebouncedValue } from "../../../shared/lib/hooks/useDebouncedValue";
import { DataTable, FilterSelect } from "../../../shared/crud";
import type { DataTableColumn } from "../../../shared/crud";
import { Alert, Badge, Button, FormField, Input, Modal, SearchInput } from "../../../shared/ui";
import "../../institution/institution.css";

const emptyForm = {
  academic_session_id: "" as string | number,
  programme_id: "" as string | number,
  semester: "",
  code: "",
  name: "",
  capacity: "",
  status: "active" as AcademicStatus,
};

const AcademicSectionsPage = () => {
  const { user } = useAuth();
  const qc = useQueryClient();
  const canManage = isInstitutionAdmin(user);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const debounced = useDebouncedValue(search.trim(), 300);

  const sectionsQ = useQuery({
    queryKey: ["academic", "sections", statusFilter],
    queryFn: () => academicService.getAcademicSections({ status: statusFilter !== "all" ? statusFilter : undefined }),
  });
  const sessionsQ = useQuery({ queryKey: ["academic", "sessions"], queryFn: () => academicService.getSessions() });
  const programmesQ = useQuery({ queryKey: ["academic", "programmes"], queryFn: () => academicService.getProgrammes() });

  const sections = sectionsQ.data?.sections ?? [];
  const filtered = useMemo(() => {
    const t = debounced.toLowerCase();
    return sections.filter((s) => !t || s.code.toLowerCase().includes(t) || s.name.toLowerCase().includes(t) || (s.programme?.code ?? "").toLowerCase().includes(t));
  }, [sections, debounced]);

  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<AcademicSection | null>(null);
  const [form, setForm] = useState(emptyForm);
  const [err, setErr] = useState("");

  const openCreate = () => { setEditing(null); setForm(emptyForm); setErr(""); setOpen(true); };
  const openEdit = (s: AcademicSection) => {
    setEditing(s);
    setForm({
      academic_session_id: s.academic_session_id ?? "",
      programme_id: s.programme_id ?? "",
      semester: s.semester?.toString() ?? "",
      code: s.code,
      name: s.name,
      capacity: s.capacity?.toString() ?? "",
      status: s.status,
    });
    setErr(""); setOpen(true);
  };

  const save = useMutation({
    mutationFn: async () => {
      const payload = {
        academic_session_id: form.academic_session_id === "" ? null : Number(form.academic_session_id),
        programme_id: form.programme_id === "" ? null : Number(form.programme_id),
        semester: form.semester ? Number(form.semester) : null,
        code: form.code.trim(),
        name: form.name.trim(),
        capacity: form.capacity === "" ? null : Number(form.capacity),
        status: form.status,
      };
      if (editing) return academicService.updateAcademicSection(editing.id, payload);
      return academicService.createAcademicSection(payload);
    },
    onSuccess: async () => {
      await qc.invalidateQueries({ queryKey: ["academic", "sections"] });
      toast.success(editing ? "Section/batch updated." : "Section/batch created.");
      setOpen(false);
    },
    onError: (e) => setErr(getApiErrorMessage(e, "Unable to save section/batch.")),
  });

  const del = useMutation({
    mutationFn: (s: AcademicSection) => academicService.deleteAcademicSection(s.id),
    onSuccess: async () => { await qc.invalidateQueries({ queryKey: ["academic", "sections"] }); toast.success("Section/batch deleted."); },
    onError: (e) => toast.error(getApiErrorMessage(e, "Unable to delete section/batch.")),
  });

  const columns: DataTableColumn<AcademicSection>[] = [
    {
      key: "section", header: "Section / Batch",
      render: (s) => (
        <div className="institution-name-cell">
          <div className="institution-avatar"><LayoutGrid size={17} /></div>
          <div>
            <strong>{s.code} — {s.name}</strong>
            <span className="institution-mono">{s.programme?.code ?? "General"} {s.semester ? `· Sem ${s.semester}` : ""} {s.academic_session ? `· ${s.academic_session.code}` : ""}</span>
          </div>
        </div>
      ),
    },
    { key: "capacity", header: "Capacity", render: (s) => <span>{s.capacity ?? "—"}</span> },
    { key: "status", header: "Status", render: (s) => <Badge tone={s.status === "active" ? "success" : "warning"}>{s.status}</Badge> },
    ...(canManage ? [{
      key: "actions", header: "",
      render: (s: AcademicSection) => (
        <span className="institution-row-actions">
          <button type="button" className="institution-icon-button" title="Edit" onClick={() => openEdit(s)}><Pencil size={16} /></button>
          <button type="button" className="institution-icon-button danger" title="Delete" onClick={() => { if (window.confirm("Delete this section/batch?")) del.mutate(s); }}><Trash2 size={16} /></button>
        </span>
      ),
    } as DataTableColumn<AcademicSection>] : []),
  ];

  if (sectionsQ.isError) {
    return <Alert tone="error" className="institution-directory-error">Unable to load sections/batches. <button type="button" onClick={() => void sectionsQ.refetch()}>Retry</button></Alert>;
  }

  return (
    <>
      <div className="institution-directory-toolbar">
        <SearchInput icon={Search} placeholder="Search section/batch..." value={search} onChange={(e) => setSearch(e.target.value)} aria-label="Search sections" />
        <FilterSelect aria-label="Filter status" value={statusFilter} onChange={setStatusFilter}>
          <option value="all">All Statuses</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </FilterSelect>
        {canManage && <Button type="button" onClick={openCreate}><Plus size={16} /> Add Section/Batch</Button>}
      </div>

      <DataTable<AcademicSection> columns={columns} rows={filtered} rowKey={(s) => s.id} loading={sectionsQ.isLoading} emptyMessage="No academic sections/batches yet." />

      <Modal open={open} title={editing ? "Edit Section/Batch" : "Add Section/Batch"} onClose={() => setOpen(false)}
        footer={<><Button variant="secondary" onClick={() => setOpen(false)}>Cancel</Button><Button onClick={() => { setErr(""); if (!form.code.trim() || !form.name.trim()) { setErr("Code and name are required."); return; } save.mutate(); }} disabled={save.isPending}>{save.isPending ? "Saving..." : "Save"}</Button></>}>
        {err && <div className="institution-form-alert" role="alert">{err}</div>}
        <div className="institution-form-grid">
          <FormField label="Code" htmlFor="sec-code" required><Input id="sec-code" value={form.code} onChange={(e) => setForm(c => ({ ...c, code: e.target.value }))} placeholder="A / B1" /></FormField>
          <FormField label="Name" htmlFor="sec-name" required><Input id="sec-name" value={form.name} onChange={(e) => setForm(c => ({ ...c, name: e.target.value }))} placeholder="Section A" /></FormField>
        </div>
        <div className="institution-form-grid">
          <FormField label="Programme" htmlFor="sec-prog">
            <select id="sec-prog" className="filter-select" value={form.programme_id} onChange={(e) => setForm(c => ({ ...c, programme_id: e.target.value }))}>
              <option value="">General / not programme-specific</option>
              {(programmesQ.data?.programmes ?? []).map(p => <option key={p.id} value={p.id}>{p.code} — {p.name}</option>)}
            </select>
          </FormField>
          <FormField label="Academic session" htmlFor="sec-session">
            <select id="sec-session" className="filter-select" value={form.academic_session_id} onChange={(e) => setForm(c => ({ ...c, academic_session_id: e.target.value }))}>
              <option value="">Any / not session-specific</option>
              {(sessionsQ.data?.sessions ?? []).map(s => <option key={s.id} value={s.id}>{s.code} — {s.name}</option>)}
            </select>
          </FormField>
        </div>
        <div className="institution-form-grid">
          <FormField label="Semester" htmlFor="sec-sem"><Input id="sec-sem" type="number" min={1} value={form.semester} onChange={(e) => setForm(c => ({ ...c, semester: e.target.value }))} /></FormField>
          <FormField label="Capacity" htmlFor="sec-cap"><Input id="sec-cap" type="number" min={1} value={form.capacity} onChange={(e) => setForm(c => ({ ...c, capacity: e.target.value }))} /></FormField>
        </div>
        <FormField label="Status" htmlFor="sec-status">
          <select id="sec-status" className="filter-select" value={form.status} onChange={(e) => setForm(c => ({ ...c, status: e.target.value as AcademicStatus }))}>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </FormField>
      </Modal>
    </>
  );
};

export default AcademicSectionsPage;
