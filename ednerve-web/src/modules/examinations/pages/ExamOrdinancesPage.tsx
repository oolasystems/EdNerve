import "../examinations.css";
import { useState } from "react";
import { Copy, ListChecks, Plus, ShieldCheck, Trash2 } from "lucide-react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { examSetupService } from "../setup-api";
import type { GradingBand, Ordinance } from "../setup-api";

import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin } from "../../../shared/lib/rbac";
import { getApiErrorMessage } from "../../../shared/lib/api-error";

import {
  Badge,
  Button,
  FormField,
  Input,
  Spinner,
} from "../../../shared/ui";

const LEGACY_BANDS: GradingBand[] = [
  { min: 90, grade: "O", points: 10 },
  { min: 80, grade: "A+", points: 9 },
  { min: 70, grade: "A", points: 8 },
  { min: 60, grade: "B+", points: 7 },
  { min: 50, grade: "B", points: 6 },
  { min: 40, grade: "C", points: 5 },
  { min: 0, grade: "F", points: 0 },
];


const emptyForm = () => ({
  code: "",
  name: "",
  description: "",
  attendance_threshold: 75,
  bands: LEGACY_BANDS.map((b) => ({ ...b })),
  overall_min_pct: 40,
  external_min_pct: 0,
  condonation_pct: 0,
  max_backlogs: "" as string | number,
  is_default: false,
});

type FormState = ReturnType<typeof emptyForm>;

const ExamOrdinancesPage = () => {
  const { user } = useAuth();
  const qc = useQueryClient();
  const canManage = isInstitutionAdmin(user);

  const ordinancesQuery = useQuery({
    queryKey: ["exam-infra", "ordinances"],
    queryFn: () => examSetupService.listOrdinances(),
  });
  const ordinances = ordinancesQuery.data ?? [];

  const [editing, setEditing] = useState<Ordinance | null>(null);
  const [creating, setCreating] = useState(false);
  const [form, setForm] = useState<FormState>(emptyForm());

  const openEdit = (o: Ordinance) => {
    setEditing(o);
    setCreating(false);
    setForm({
      code: o.code,
      name: o.name,
      description: o.description ?? "",
      attendance_threshold: Number(o.attendance_threshold),
      bands: o.grading_bands.map((b) => ({ ...b })),
      overall_min_pct: o.pass_criteria?.overall_min_pct ?? 40,
      external_min_pct: o.pass_criteria?.external_min_pct ?? 0,
      condonation_pct: o.pass_criteria?.condonation_pct ?? 0,
      max_backlogs: o.pass_criteria?.max_backlogs ?? "",
      is_default: o.is_default,
    });
  };

  const openNew = () => {
    setEditing(null);
    setCreating(true);
    setForm(emptyForm());
  };

  const closeEditor = () => {
    setEditing(null);
    setCreating(false);
  };

  const setBand = (i: number, patch: Partial<GradingBand>) => {
    setForm((f) => ({ ...f, bands: f.bands.map((b, idx) => (idx === i ? { ...b, ...patch } : b)) }));
  };

  const save = async () => {
    if (!form.code.trim() || !form.name.trim()) {
      toast.error("Code and name are required.");
      return;
    }
    const body = {
      code: form.code,
      name: form.name,
      description: form.description || undefined,
      attendance_threshold: Number(form.attendance_threshold),
      grading_bands: form.bands.map((b) => ({ min: Number(b.min), grade: b.grade, points: Number(b.points) })),
      pass_criteria: {
        overall_min_pct: Number(form.overall_min_pct),
        external_min_pct: Number(form.external_min_pct),
        condonation_pct: Number(form.condonation_pct),
        max_backlogs: form.max_backlogs === "" ? null : Number(form.max_backlogs),
      },
      is_default: form.is_default,
    };
    try {
      if (creating) await examSetupService.createOrdinance(body);
      else if (editing) await examSetupService.updateOrdinance(editing.id, body);
      await qc.invalidateQueries({ queryKey: ["exam-infra", "ordinances"] });
      toast.success(creating ? "Ordinance created." : "Ordinance saved.");
      closeEditor();
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to save ordinance."));
    }
  };

  const setDefault = async (o: Ordinance) => {
    try {
      await examSetupService.setDefaultOrdinance(o.id);
      await qc.invalidateQueries({ queryKey: ["exam-infra", "ordinances"] });
      toast.success(`${o.name} is now the default ordinance.`);
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to set default."));
    }
  };

  const archive = async (o: Ordinance) => {
    try {
      await examSetupService.archiveOrdinance(o.id);
      await qc.invalidateQueries({ queryKey: ["exam-infra", "ordinances"] });
      toast.success("Ordinance archived.");
      if (editing?.id === o.id) closeEditor();
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to archive."));
    }
  };

  const editorOpen = creating || editing !== null;

  return (
    <div className="institution-two-column">
      <div className="institution-admin-panel">
        <h3><ShieldCheck size={16} aria-hidden="true" className="exam-heading-icon" /> Examination ordinances</h3>
        {canManage && <Button onClick={openNew}><Plus size={15} /> New ordinance</Button>}
        {ordinancesQuery.isLoading ? <Spinner size={22} /> : (
          <table className="exam-data-table exam-ordinances-table">
            <thead><tr><th className="exam-data-table-heading">Code</th><th className="exam-data-table-heading">Name</th><th className="exam-data-table-heading">Attendance</th><th className="exam-data-table-heading">Default</th><th className="exam-data-table-heading">Status</th><th className="exam-data-table-heading"></th></tr></thead>
            <tbody>
              {ordinances.map((o) => (
                <tr key={o.id}>
                  <td className="exam-data-table-cell"><button type="button" className="institution-link" onClick={() => openEdit(o)}><span className="institution-mono">{o.code}</span></button></td>
                  <td className="exam-data-table-cell">{o.name}</td>
                  <td className="exam-data-table-cell">{Number(o.attendance_threshold)}%</td>
                  <td className="exam-data-table-cell">{o.is_default && <Badge tone="info">default</Badge>}</td>
                  <td className="exam-data-table-cell"><Badge tone={o.status === "active" ? "success" : "warning"}>{o.status}</Badge></td>
                  <td className="exam-data-table-cell">
                    <button type="button" className="timetable-icon-btn" title="Set as default" onClick={() => setDefault(o)}><Copy size={15} /></button>
                    {!o.is_default && canManage && (
                      <button type="button" className="timetable-icon-btn" title="Archive" onClick={() => archive(o)}><Trash2 size={15} /></button>
                    )}
                  </td>
                </tr>
              ))}
              {ordinances.length === 0 && <tr><td className="exam-data-table-cell exam-data-table-empty" colSpan={6}>No ordinances yet — the result engine falls back to the legacy bands (O ≥ 90 … F &lt; 40, pass ≥ 40).</td></tr>}
            </tbody>
          </table>
        )}
      </div>

      {editorOpen && (
        <div className="institution-admin-panel">
          <h3>{creating ? "New ordinance" : `Editing ${editing?.code}`}</h3>
          <FormField label="Code" htmlFor="ord-code" required><Input id="ord-code" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value.toUpperCase() })} disabled={!creating} placeholder="AMU-BTCSE" maxLength={20} /></FormField>
          <FormField label="Name" htmlFor="ord-name" required><Input id="ord-name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="AMU B.Tech (CSE) ordinance" /></FormField>
          <FormField label="Description" htmlFor="ord-desc"><Input id="ord-desc" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} placeholder="Optional description" /></FormField>

          <FormField label="Grading bands (min %)" htmlFor="bands">
            <table className="exam-data-table exam-ordinances-table">
              <thead><tr><th className="exam-data-table-heading">Min %</th><th className="exam-data-table-heading">Grade</th><th className="exam-data-table-heading">Points</th></tr></thead>
              <tbody>
                {form.bands.map((b, i) => (
                  <tr key={i}>
                    <td className="exam-data-table-cell"><Input className="exam-ordinances-compact-input" type="number" min={0} max={100} value={b.min} onChange={(e) => setBand(i, { min: Number(e.target.value) })} /></td>
                    <td className="exam-data-table-cell"><Input className="exam-ordinances-compact-input" value={b.grade} onChange={(e) => setBand(i, { grade: e.target.value.toUpperCase() })} maxLength={3} /></td>
                    <td className="exam-data-table-cell"><Input className="exam-ordinances-compact-input" type="number" min={0} value={b.points} onChange={(e) => setBand(i, { points: Number(e.target.value) })} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </FormField>

          <div className="exam-ordinances-criteria-grid">
            <FormField label="Overall pass %" htmlFor="crit-overall" required><Input id="crit-overall" type="number" min={0} max={100} value={form.overall_min_pct} onChange={(e) => setForm({ ...form, overall_min_pct: Number(e.target.value) })} className="exam-ordinances-criteria-input" /></FormField>
            <FormField label="External pass %" htmlFor="crit-ext"><Input id="crit-ext" type="number" min={0} max={100} value={form.external_min_pct} onChange={(e) => setForm({ ...form, external_min_pct: Number(e.target.value) })} className="exam-ordinances-criteria-input" /></FormField>
            <FormField label="Condonation %" htmlFor="crit-cond"><Input id="crit-cond" type="number" min={0} max={100} value={form.condonation_pct} onChange={(e) => setForm({ ...form, condonation_pct: Number(e.target.value) })} className="exam-ordinances-criteria-input" /></FormField>
            <FormField label="Max backlogs" htmlFor="crit-bl"><Input id="crit-bl" type="number" min={0} value={form.max_backlogs} onChange={(e) => setForm({ ...form, max_backlogs: e.target.value })} className="exam-ordinances-criteria-input" /></FormField>
            <FormField label="Attendance threshold %" htmlFor="crit-att"><Input id="crit-att" type="number" min={0} max={100} value={form.attendance_threshold} onChange={(e) => setForm({ ...form, attendance_threshold: Number(e.target.value) })} className="exam-ordinances-criteria-input" /></FormField>
          </div>

          <div className="exam-ordinances-form-actions">
            <Button variant="secondary" onClick={closeEditor}>Cancel</Button>
            {canManage && <Button onClick={save}><ListChecks size={15} /> {creating ? "Create" : "Save"}</Button>}
          </div>
        </div>
      )}
    </div>
  );
};

export default ExamOrdinancesPage;
