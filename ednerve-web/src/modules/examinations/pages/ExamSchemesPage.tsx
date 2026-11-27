import "../examinations.css";
import { useMemo, useState } from "react";
import { Layers3, Plus, Trash2, X } from "lucide-react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { examSetupService } from "../setup-api";
import type { AssessmentScheme, SchemeComponent } from "../setup-api";
import { academicService } from "../../academic/api";

import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin } from "../../../shared/lib/rbac";
import { getApiErrorMessage } from "../../../shared/lib/api-error";

import {
  Badge,
  Button,
  FormField,
  Input,
  Modal,
  Spinner,
  Alert,
} from "../../../shared/ui";


const emptyComponent = (): SchemeComponent => ({
  name: "",
  kind: "continuous",
  max_marks: 20,
  weightage: null,
  is_external: false,
  min_percent: null,
});

const emptyForm = () => ({
  code: "",
  name: "",
  scope: "programme" as AssessmentScheme["scope"],
  programme_id: "",
  subject_id: "",
  description: "",
  components: [emptyComponent()],
});

type FormState = ReturnType<typeof emptyForm>;

const weightageSum = (components: SchemeComponent[]) =>
  components.reduce((sum, c) => sum + (Number(c.weightage) || 0), 0);

const EMPTY_SCHEMES: AssessmentScheme[] = [];
const EMPTY_PROGRAMMES: NonNullable<Awaited<ReturnType<typeof academicService.getProgrammes>>["programmes"]> = [];
const EMPTY_SUBJECTS: NonNullable<Awaited<ReturnType<typeof academicService.getSubjects>>["subjects"]> = [];

const ExamSchemesPage = () => {
  const { user } = useAuth();
  const qc = useQueryClient();
  const canManage = isInstitutionAdmin(user);

  const schemesQuery = useQuery({
    queryKey: ["exam-infra", "schemes"],
    queryFn: () => examSetupService.listSchemes(),
  });
  const programmesQuery = useQuery({
    queryKey: ["academic", "programmes"],
    queryFn: () => academicService.getProgrammes(),
  });
  const subjectsQuery = useQuery({
    queryKey: ["academic", "subjects"],
    queryFn: () => academicService.getSubjects(),
  });

  const schemes = schemesQuery.data ?? EMPTY_SCHEMES;
  const programmes = programmesQuery.data?.programmes ?? EMPTY_PROGRAMMES;
  const subjects = subjectsQuery.data?.subjects ?? EMPTY_SUBJECTS;

  const [editing, setEditing] = useState<AssessmentScheme | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm());
  const [open, setOpen] = useState(false);

  const openNew = () => {
    setEditing(null);
    setForm(emptyForm());
    setOpen(true);
  };

  const openEdit = (scheme: AssessmentScheme) => {
    setEditing(scheme);
    setForm({
      code: scheme.code,
      name: scheme.name,
      scope: scheme.scope,
      programme_id: scheme.programme_id?.toString() ?? "",
      subject_id: scheme.subject_id?.toString() ?? "",
      description: scheme.description ?? "",
      components: scheme.components.map((c) => ({ ...c })),
    });
    setOpen(true);
  };

  const close = () => setOpen(false);

  const setComponent = (i: number, patch: Partial<SchemeComponent>) => {
    setForm((f) => ({ ...f, components: f.components.map((c, idx) => (idx === i ? { ...c, ...patch } : c)) }));
  };

  const addComponent = () => setForm((f) => ({ ...f, components: [...f.components, emptyComponent()] }));

  const removeComponent = (i: number) =>
    setForm((f) => ({ ...f, components: f.components.filter((_, idx) => idx !== i) }));

  const totalWeight = weightageSum(form.components);
  const weightInvalid = form.components.every((c) => c.weightage !== null) && Math.abs(totalWeight - 100) > 0.01;

  const scopeNames = useMemo(() => {
    const map = new Map<number, string>();
    for (const p of programmes) map.set(p.id, p.code);
    for (const s of subjects) map.set(s.id, s.code);
    return map;
  }, [programmes, subjects]);

  const save = async () => {
    if (!form.code.trim() || !form.name.trim()) {
      toast.error("Code and name are required.");
      return;
    }
    if (form.scope === "programme" && !form.programme_id) {
      toast.error("Pick a programme for a programme-scoped scheme.");
      return;
    }
    if (form.scope === "subject" && !form.subject_id) {
      toast.error("Pick a subject for a subject-scoped scheme.");
      return;
    }
    if (weightInvalid) {
      toast.error(`Component weightages sum to ${totalWeight.toFixed(2)} — expected 100.`);
      return;
    }
    const body = {
      code: form.code,
      name: form.name,
      scope: form.scope,
      programme_id: form.scope === "programme" ? Number(form.programme_id) : undefined,
      subject_id: form.scope === "subject" ? Number(form.subject_id) : undefined,
      description: form.description || undefined,
      components: form.components.map((c, i) => ({
        name: c.name,
        kind: c.kind,
        max_marks: Number(c.max_marks),
        weightage: c.weightage === null || c.weightage === "" ? null : Number(c.weightage),
        is_external: c.is_external,
        min_percent: c.min_percent === null || c.min_percent === "" ? null : Number(c.min_percent),
        position: i + 1,
      })),
    };
    try {
      if (editing) await examSetupService.updateScheme(editing.id, body);
      else await examSetupService.createScheme(body);
      await qc.invalidateQueries({ queryKey: ["exam-infra", "schemes"] });
      toast.success(editing ? "Scheme saved." : "Scheme created.");
      setOpen(false);
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to save scheme."));
    }
  };

  const remove = async (scheme: AssessmentScheme) => {
    try {
      await examSetupService.deleteScheme(scheme.id);
      await qc.invalidateQueries({ queryKey: ["exam-infra", "schemes"] });
      toast.success("Scheme deleted.");
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to delete scheme."));
    }
  };

  return (
    <>
      <div className="institution-admin-panel">
        <h3><Layers3 size={16} aria-hidden="true" className="exam-heading-icon" /> Assessment schemes</h3>
        <p className="exam-schemes-description">
          Per-subject or per-programme internal/external splits (e.g. Sessional 10 + Sessional 10 + Mid-sem 10 + End-sem 70).
          The result engine applies them when components exist in the exam. Subject schemes win over programme schemes.
        </p>
        {canManage && <Button onClick={openNew}><Plus size={15} /> New scheme</Button>}
        {schemesQuery.isLoading ? <Spinner size={22} /> : (
          <table className="exam-data-table exam-schemes-table">
            <thead><tr><th className="exam-data-table-heading">Code</th><th className="exam-data-table-heading">Name</th><th className="exam-data-table-heading">Scope</th><th className="exam-data-table-heading">Target</th><th className="exam-data-table-heading">Components</th><th className="exam-data-table-heading">Status</th><th className="exam-data-table-heading"></th></tr></thead>
            <tbody>
              {schemes.map((s) => (
                <tr key={s.id}>
                  <td className="exam-data-table-cell"><button type="button" className="institution-link" onClick={() => openEdit(s)}><span className="institution-mono">{s.code}</span></button></td>
                  <td className="exam-data-table-cell">{s.name}</td>
                  <td className="exam-data-table-cell">{s.scope}</td>
                  <td className="exam-data-table-cell">{scopeNames.get(s.scope === "subject" ? s.subject_id ?? 0 : s.programme_id ?? 0) ?? "—"}</td>
                  <td className="exam-data-table-cell">{s.components.length}</td>
                  <td className="exam-data-table-cell"><Badge tone={s.status === "active" ? "success" : "warning"}>{s.status}</Badge></td>
                  <td className="exam-data-table-cell">
                    {canManage && <button type="button" className="timetable-icon-btn" title="Delete" onClick={() => remove(s)}><Trash2 size={15} /></button>}
                  </td>
                </tr>
              ))}
              {schemes.length === 0 && <tr><td className="exam-data-table-cell exam-data-table-empty" colSpan={7}>No schemes yet — exams without schemes use their ad-hoc components as before.</td></tr>}
            </tbody>
          </table>
        )}
      </div>

      <Modal open={open} title={editing ? `Edit scheme ${editing.code}` : "New assessment scheme"} onClose={close}
        footer={<><Button variant="secondary" onClick={close}>Cancel</Button>{canManage && <Button onClick={save}>Save scheme</Button>}</>}>
        <FormField label="Code" htmlFor="scheme-code" required><Input id="scheme-code" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value.toUpperCase() })} disabled={editing !== null} placeholder="BTCSE-INT" maxLength={30} /></FormField>
        <FormField label="Name" htmlFor="scheme-name" required><Input id="scheme-name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="BTCSE internal assessment" /></FormField>
        <FormField label="Scope" htmlFor="scheme-scope">
          <select id="scheme-scope" className="filter-select" value={form.scope} onChange={(e) => setForm({ ...form, scope: e.target.value as AssessmentScheme["scope"] })}>
            <option value="programme">Programme (default for all subjects)</option>
            <option value="subject">Subject (this subject only)</option>
          </select>
        </FormField>
        {form.scope === "programme" && (
          <FormField label="Programme" htmlFor="scheme-prog" required>
            <select id="scheme-prog" className="filter-select" value={form.programme_id} onChange={(e) => setForm({ ...form, programme_id: e.target.value })}>
              <option value="">Select…</option>
              {programmes.map((p) => <option key={p.id} value={p.id}>{p.code} — {p.name}</option>)}
            </select>
          </FormField>
        )}
        {form.scope === "subject" && (
          <FormField label="Subject" htmlFor="scheme-sub" required>
            <select id="scheme-sub" className="filter-select" value={form.subject_id} onChange={(e) => setForm({ ...form, subject_id: e.target.value })}>
              <option value="">Select…</option>
              {subjects.map((s) => <option key={s.id} value={s.id}>{s.code} — {s.name}</option>)}
            </select>
          </FormField>
        )}
        <FormField label="Description" htmlFor="scheme-desc"><Input id="scheme-desc" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} placeholder="Optional" /></FormField>

        <div className="exam-schemes-components-heading">
          <strong className="exam-schemes-components-title">Components</strong>
          <span className={`exam-schemes-weight-status${weightInvalid ? " is-invalid" : ""}`}>
            Weightage total: {totalWeight.toFixed(0)}{form.components.some((c) => c.weightage !== null) ? " / 100" : " (unset)"}
          </span>
        </div>
        {weightInvalid && <Alert tone="error">Weightage mismatch — components with weightage set must sum to 100.</Alert>}

        <table className="exam-data-table exam-schemes-table">
          <thead><tr><th className="exam-data-table-heading">Name</th><th className="exam-data-table-heading">Kind</th><th className="exam-data-table-heading">Max</th><th className="exam-data-table-heading">Wt %</th><th className="exam-data-table-heading">External</th><th className="exam-data-table-heading"></th></tr></thead>
          <tbody>
            {form.components.map((c, i) => (
              <tr key={i}>
                <td className="exam-data-table-cell"><Input className="exam-schemes-compact-input" value={c.name} onChange={(e) => setComponent(i, { name: e.target.value })} placeholder="Sessional 1" /></td>
                <td className="exam-data-table-cell">
                  <select className="filter-select exam-schemes-kind-select" value={c.kind} onChange={(e) => setComponent(i, { kind: e.target.value as SchemeComponent["kind"] })}>
                    <option value="continuous">Continuous</option>
                    <option value="external">External</option>
                    <option value="practical">Practical</option>
                    <option value="viva">Viva</option>
                    <option value="project">Project</option>
                  </select>
                </td>
                <td className="exam-data-table-cell"><Input className="exam-schemes-compact-input" type="number" min={1} value={c.max_marks} onChange={(e) => setComponent(i, { max_marks: e.target.value })} /></td>
                <td className="exam-data-table-cell"><Input className="exam-schemes-compact-input" type="number" min={0} max={100} value={c.weightage ?? ""} onChange={(e) => setComponent(i, { weightage: e.target.value === "" ? null : Number(e.target.value) })} /></td>
                <td className="exam-data-table-cell"><input type="checkbox" checked={c.is_external} onChange={(e) => setComponent(i, { is_external: e.target.checked })} aria-label="external" /></td>
                <td className="exam-data-table-cell"><button type="button" className="timetable-icon-btn" onClick={() => removeComponent(i)} aria-label="Remove"><X size={15} /></button></td>
              </tr>
            ))}
          </tbody>
        </table>
        <Button variant="secondary" onClick={addComponent}><Plus size={14} /> Add component</Button>
      </Modal>
    </>
  );
};

export default ExamSchemesPage;
