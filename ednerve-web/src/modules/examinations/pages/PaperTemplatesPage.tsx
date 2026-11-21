import {
  Archive,
  ListChecks,
  Pencil,
  Plus,
  Trash2,
} from "lucide-react";
import { useState } from "react";
import { useQuery, useQueryClient, useMutation } from "@tanstack/react-query";
import { toast } from "sonner";

import { questionBankService } from "../question-bank-api";
import type { QuestionPaperTemplate } from "../question-bank-api";
import "../../institution/institution.css";

import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin, isExamOfficer } from "../../../shared/lib/rbac";
import { getApiErrorMessage } from "../../../shared/lib/api-error";

import {
  Alert,
  Badge,
  Button,
  FormField,
  Input,
  Modal,
  Textarea,
} from "../../../shared/ui";

type PatternSection = {
  title: string;
  position: number;
  instruction: string;
  items: number;
  marks: number;
};

const KINDS: Array<QuestionPaperTemplate["assessment_kind"]> = [
  "exam",
  "unit_test",
  "mid",
  "sessional",
];
const KIND_LABEL: Record<QuestionPaperTemplate["assessment_kind"], string> = {
  exam: "Examination",
  unit_test: "Unit Test",
  mid: "Mid Semester",
  sessional: "Sessional",
};

const emptySection = (position: number): PatternSection => ({
  title: `Section ${String.fromCharCode(64 + position)}`,
  position,
  instruction: "Attempt all questions.",
  items: 5,
  marks: 10,
});

const emptyForm = {
  name: "",
  assessment_kind: "exam" as QuestionPaperTemplate["assessment_kind"],
  total_marks: 100,
  instructions_md: "## General Instructions\n- Write your application number clearly.\n- Bring your hall ticket.\n- Duration: 3 hours.",
  pattern: [emptySection(1)] as PatternSection[],
};

const PaperTemplatesPage = () => {
  const { user } = useAuth();
  const qc = useQueryClient();
  const canManage = isInstitutionAdmin(user) || isExamOfficer(user);

  const templatesQuery = useQuery({
    queryKey: ["question-bank", "templates"],
    queryFn: () => questionBankService.getTemplates(),
  });
  const templates = templatesQuery.data?.templates ?? [];

  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<QuestionPaperTemplate | null>(null);
  const [form, setForm] = useState(emptyForm);
  const [formError, setFormError] = useState("");

  const openCreate = () => {
    setEditing(null);
    setForm({ ...emptyForm, pattern: [emptySection(1), emptySection(2)] });
    setFormError("");
    setModalOpen(true);
  };

  const openEdit = (template: QuestionPaperTemplate) => {
    setEditing(template);
    setForm({
      name: template.name,
      assessment_kind: template.assessment_kind,
      total_marks: Number(template.total_marks),
      instructions_md: template.instructions_md ?? "",
      pattern:
        template.pattern && template.pattern.length > 0
          ? template.pattern.map((s) => ({
              title: s.title,
              position: s.position,
              instruction: s.instruction ?? "",
              items: s.items,
              marks: s.marks,
            }))
          : [emptySection(1)],
    });
    setFormError("");
    setModalOpen(true);
  };

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        name: form.name.trim(),
        assessment_kind: form.assessment_kind,
        total_marks: Number(form.total_marks),
        instructions_md: form.instructions_md,
        pattern: form.pattern.map((s, i) => ({
          title: s.title.trim(),
          position: i + 1,
          instruction: s.instruction.trim() || null,
          items: Number(s.items),
          marks: Number(s.marks),
        })),
      };
      if (editing) {
        return questionBankService.updateTemplate(editing.id, payload);
      }
      return questionBankService.createTemplate(payload);
    },
    onSuccess: async () => {
      await qc.invalidateQueries({ queryKey: ["question-bank", "templates"] });
      toast.success(editing ? "Template updated." : "Template created.");
      setModalOpen(false);
    },
    onError: (error) => {
      setFormError(getApiErrorMessage(error, "Unable to save the template."));
    },
  });

  const deactivateMutation = useMutation({
    mutationFn: (template: QuestionPaperTemplate) => questionBankService.deactivateTemplate(template.id),
    onSuccess: async () => {
      await qc.invalidateQueries({ queryKey: ["question-bank", "templates"] });
      toast.success("Template deactivated.");
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error, "Unable to deactivate the template."));
    },
  });

  const patternMarks = form.pattern.reduce((sum, s) => sum + (Number(s.items) || 0) * (Number(s.marks) || 0), 0);

  const updateSection = (index: number, patch: Partial<PatternSection>) =>
    setForm((current) => ({
      ...current,
      pattern: current.pattern.map((s, i) => (i === index ? { ...s, ...patch } : s)),
    }));

  const addSection = () =>
    setForm((current) => ({
      ...current,
      pattern: [...current.pattern, emptySection(current.pattern.length + 1)],
    }));

  const removeSection = (index: number) =>
    setForm((current) => ({
      ...current,
      pattern: current.pattern.filter((_, i) => i !== index),
    }));

  const rows = templates.map((template) => (
    <li key={template.id} className="institution-elective-list-item">
      <div>
        <strong>{template.name}</strong>
        <div className="institution-mono" style={{ fontSize: 11, color: "var(--text-muted)" }}>
          {KIND_LABEL[template.assessment_kind]} · {template.total_marks} marks
          {template.pattern && template.pattern.length > 0 && ` · ${template.pattern.length} sections`}
        </div>
      </div>
      <span className="institution-row-actions">
        <Badge tone={template.status === "active" ? "success" : "warning"}>{template.status}</Badge>
        {canManage && (
          <>
            <button
              type="button"
              className="institution-icon-button"
              title="Edit template"
              onClick={() => openEdit(template)}
            >
              <Pencil size={16} />
            </button>
            <button
              type="button"
              className="institution-icon-button danger"
              title="Deactivate template"
              disabled={template.status !== "active"}
              onClick={() => deactivateMutation.mutate(template)}
            >
              <Archive size={16} />
            </button>
          </>
        )}
      </span>
    </li>
  ));

  return (
    <>
      <div className="institution-directory-toolbar">
        <h2 style={{ display: "flex", alignItems: "center", gap: 10, margin: 0 }}>
          <ListChecks size={20} /> Paper Templates
        </h2>
        {canManage && (
          <Button type="button" onClick={openCreate}>
            <Plus size={16} /> New Template
          </Button>
        )}
      </div>

      {templatesQuery.isError && (
        <Alert tone="error" className="institution-directory-error">
          Unable to load templates.
          <button type="button" onClick={() => void templatesQuery.refetch()}>Retry</button>
        </Alert>
      )}

      <div className="institution-admin-panel">
        {templates.length === 0 && !templatesQuery.isLoading ? (
          <p style={{ color: "var(--text-muted)" }}>
            No templates yet. Create a blueprint like "End Semester 100 — Section A: 10 × 2 marks, Section B: 5 × 16 marks".
          </p>
        ) : (
          <ul className="institution-elective-list">{rows}</ul>
        )}
      </div>

      <Modal
        open={modalOpen}
        title={editing ? "Edit Template" : "New Paper Template"}
        onClose={() => setModalOpen(false)}
        maxWidth={720}
        footer={
          <>
            <Button type="button" variant="secondary" onClick={() => setModalOpen(false)}>
              Cancel
            </Button>
            <Button type="button" onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending}>
              {saveMutation.isPending ? "Saving..." : "Save template"}
            </Button>
          </>
        }
      >
        {formError && <div className="institution-form-alert" role="alert">{formError}</div>}

        <div className="institution-form-grid">
          <FormField label="Template name" htmlFor="tpl-name" required>
            <Input
              id="tpl-name"
              value={form.name}
              onChange={(e) => setForm((c) => ({ ...c, name: e.target.value }))}
              placeholder="End Semester 100"
            />
          </FormField>
          <FormField label="Type" htmlFor="tpl-kind">
            <select
              id="tpl-kind"
              className="filter-select"
              value={form.assessment_kind}
              onChange={(e) =>
                setForm((c) => ({
                  ...c,
                  assessment_kind: e.target.value as QuestionPaperTemplate["assessment_kind"],
                }))
              }
            >
              {KINDS.map((kind) => (
                <option key={kind} value={kind}>
                  {KIND_LABEL[kind]}
                </option>
              ))}
            </select>
          </FormField>
        </div>

        <div className="institution-form-grid">
          <FormField label="Total marks" htmlFor="tpl-marks" required>
            <Input
              id="tpl-marks"
              type="number"
              min={0.5}
              value={form.total_marks}
              onChange={(e) => setForm((c) => ({ ...c, total_marks: Number(e.target.value) }))}
            />
          </FormField>
          <div className="institution-paper-template-sum">
            <span>Pattern total:</span>
            <strong>{patternMarks} marks</strong>
            {patternMarks !== Number(form.total_marks) && (
              <span className="institution-muted"> (≠ total)</span>
            )}
          </div>
        </div>

        <FormField label="General instructions (CommonMark)" htmlFor="tpl-instructions">
          <Textarea
            id="tpl-instructions"
            rows={3}
            value={form.instructions_md}
            onChange={(e) => setForm((c) => ({ ...c, instructions_md: e.target.value }))}
          />
        </FormField>

        <div className="institution-syllabus-units">
          <div className="institution-syllabus-units__header">
            <h4>Sections / blueprint</h4>
            <Button type="button" variant="secondary" onClick={addSection}>
              <Plus size={14} /> Add section
            </Button>
          </div>
          {form.pattern.map((section, index) => (
            <div className="institution-syllabus-unit" key={index}>
              <div className="institution-form-grid institution-form-grid--unit">
                <FormField label="Section" htmlFor={`sec-${index}-title`} required>
                  <Input
                    id={`sec-${index}-title`}
                    value={section.title}
                    onChange={(e) => updateSection(index, { title: e.target.value })}
                  />
                </FormField>
                <FormField label="Instruction" htmlFor={`sec-${index}-instr`}>
                  <Input
                    id={`sec-${index}-instr`}
                    value={section.instruction}
                    onChange={(e) => updateSection(index, { instruction: e.target.value })}
                  />
                </FormField>
                <div className="institution-form-grid" style={{ gridTemplateColumns: "1fr 1fr", gap: "var(--space-2)" }}>
                  <FormField label="Items" htmlFor={`sec-${index}-items`}>
                    <Input
                      id={`sec-${index}-items`}
                      type="number"
                      min={1}
                      value={section.items}
                      onChange={(e) => updateSection(index, { items: Number(e.target.value) })}
                    />
                  </FormField>
                  <FormField label="Marks each" htmlFor={`sec-${index}-marks`}>
                    <Input
                      id={`sec-${index}-marks`}
                      type="number"
                      min={0.5}
                      value={section.marks}
                      onChange={(e) => updateSection(index, { marks: Number(e.target.value) })}
                    />
                  </FormField>
                </div>
                <div className="institution-syllabus-unit__remove">
                  <button
                    type="button"
                    className="institution-icon-button danger"
                    title="Remove section"
                    onClick={() => removeSection(index)}
                  >
                    <Trash2 size={16} />
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>
      </Modal>
    </>
  );
};

export default PaperTemplatesPage;
