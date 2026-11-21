import { BookOpen, GitBranch, Layers, Pencil, Plus, Trash2, X, Check } from "lucide-react";
import { useMemo, useState } from "react";
import { useQuery, useQueryClient, useMutation } from "@tanstack/react-query";
import { toast } from "sonner";

import { academicService, academicStructureService } from "../api";
import type { Subject, SubjectType, AcademicStatus, SubjectComponent, SubjectComponentKind, SubjectPrerequisite } from "../types";
import "../../institution/institution.css";

import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin } from "../../../shared/lib/rbac";
import { getApiErrorMessage } from "../../../shared/lib/api-error";
import { useDebouncedValue } from "../../../shared/lib/hooks/useDebouncedValue";

import {
  DataTable,
  FilterSelect,
} from "../../../shared/crud";
import type { DataTableColumn } from "../../../shared/crud";
import {
  Alert,
  Badge,
  Button,
  FormField,
  Input,
  Modal,
  SearchInput,
} from "../../../shared/ui";
import { Search } from "lucide-react";

const emptyForm = {
  code: "",
  name: "",
  credits: 3,
  subject_type: "theory" as SubjectType,
  status: "active" as AcademicStatus,
};

const AcademicSubjectsPage = () => {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const canManage = isInstitutionAdmin(user);

  const [searchInput, setSearchInput] = useState("");
  const [typeFilter, setTypeFilter] = useState<string>("all");
  const debouncedSearch = useDebouncedValue(searchInput.trim(), 300);

  const subjectsQuery = useQuery({
    queryKey: ["academic", "subjects"],
    queryFn: () => academicService.getSubjects(),
  });

  const allSubjects = subjectsQuery.data?.subjects ?? [];

  const filteredSubjects = useMemo(() => {
    const term = debouncedSearch.toLowerCase();
    return allSubjects.filter((subject) => {
      const matchesSearch =
        !term ||
        subject.code.toLowerCase().includes(term) ||
        subject.name.toLowerCase().includes(term);
      const matchesType =
        typeFilter === "all" || subject.subject_type === typeFilter;
      return matchesSearch && matchesType;
    });
  }, [allSubjects, debouncedSearch, typeFilter]);

  const [modalOpen, setModalOpen] = useState(false);
  const [editingSubject, setEditingSubject] = useState<Subject | null>(null);
  const [form, setForm] = useState(emptyForm);
  const [formError, setFormError] = useState("");

  const openCreateModal = () => {
    setEditingSubject(null);
    setForm(emptyForm);
    setFormError("");
    setModalOpen(true);
  };

  const openEditModal = (subject: Subject) => {
    setEditingSubject(subject);
    setForm({
      code: subject.code,
      name: subject.name,
      credits: subject.credits,
      subject_type: subject.subject_type,
      status: subject.status,
    });
    setFormError("");
    setModalOpen(true);
  };

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        code: form.code.trim(),
        name: form.name.trim(),
        credits: Number(form.credits),
        subject_type: form.subject_type,
        status: form.status,
      };

      if (editingSubject) {
        return academicService.updateSubject(editingSubject.id, payload);
      }
      return academicService.createSubject(payload);
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["academic", "subjects"] });
      toast.success(editingSubject ? "Subject updated." : "Subject created.");
      setModalOpen(false);
    },
    onError: (error) => {
      setFormError(getApiErrorMessage(error, "Unable to save the subject."));
    },
  });

  const deactivateMutation = useMutation({
    mutationFn: (subject: Subject) =>
      academicService.deactivateSubject(subject.id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["academic", "subjects"] });
      toast.success("Subject deactivated.");
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error, "Unable to deactivate this subject."));
    },
  });

  // -------- Components & prerequisites modals (Phase A) --------
  const [componentsSubject, setComponentsSubject] = useState<Subject | null>(null);
  const [prereqSubject, setPrereqSubject] = useState<Subject | null>(null);

  const componentsQuery = useQuery({
    queryKey: ["academic", "components", componentsSubject?.id],
    queryFn: () => academicStructureService.getSubjectComponents(componentsSubject!.id),
    enabled: componentsSubject !== null,
  });
  const prereqsQuery = useQuery({
    queryKey: ["academic", "prerequisites", prereqSubject?.id],
    queryFn: () => academicStructureService.getSubjectPrerequisites(prereqSubject!.id),
    enabled: prereqSubject !== null,
  });

  const [componentForm, setComponentForm] = useState({
    kind: "theory" as SubjectComponentKind,
    credits: 0,
    hours_per_week: 0,
    max_marks: "" as string | number,
    position: 1,
  });
  const [prereqPickId, setPrereqPickId] = useState<number | null>(null);

  const emptyComponentForm = () => ({
    kind: "theory" as SubjectComponentKind,
    credits: 0,
    hours_per_week: 0,
    max_marks: "" as string | number,
    position: 1,
  });

  const upsertComponentMutation = useMutation({
    mutationFn: () =>
      academicStructureService.upsertSubjectComponent(componentsSubject!.id, {
        kind: componentForm.kind,
        credits: Number(componentForm.credits),
        hours_per_week: Number(componentForm.hours_per_week),
        max_marks: componentForm.max_marks === "" ? null : Number(componentForm.max_marks),
        position: Number(componentForm.position),
      }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["academic", "components", componentsSubject?.id] });
      toast.success("Component saved.");
      setComponentForm(emptyComponentForm());
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error, "Unable to save the component."));
    },
  });

  const removeComponentMutation = useMutation({
    mutationFn: (kind: string) =>
      academicStructureService.removeSubjectComponent(componentsSubject!.id, kind),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["academic", "components", componentsSubject?.id] });
      toast.success("Component removed.");
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error, "Unable to remove the component."));
    },
  });

  const addPrereqMutation = useMutation({
    mutationFn: (subjectId: number) =>
      academicStructureService.addPrerequisite(prereqSubject!.id, subjectId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["academic", "prerequisites", prereqSubject?.id] });
      toast.success("Prerequisite added.");
      setPrereqPickId(null);
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error, "Unable to add the prerequisite."));
    },
  });

  const removePrereqMutation = useMutation({
    mutationFn: (prerequisiteId: number) =>
      academicStructureService.removePrerequisite(prereqSubject!.id, prerequisiteId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["academic", "prerequisites", prereqSubject?.id] });
      toast.success("Prerequisite removed.");
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error, "Unable to remove the prerequisite."));
    },
  });

  const components: SubjectComponent[] = componentsQuery.data?.components ?? [];
  const prereqs: SubjectPrerequisite[] = prereqsQuery.data?.prerequisites ?? [];
  const availablePrereqs = allSubjects.filter(
    (s) =>
      prereqSubject !== null &&
      s.id !== prereqSubject.id &&
      !prereqs.some((p) => p.prerequisite.id === s.id)
  );

  const handleSave = () => {
    setFormError("");

    if (!form.code.trim()) {
      setFormError("Subject code is required.");
      return;
    }
    if (!form.name.trim()) {
      setFormError("Subject name is required.");
      return;
    }

    saveMutation.mutate();
  };

  const columns: DataTableColumn<Subject>[] = [
    {
      key: "subject",
      header: "Subject",
      render: (subject) => (
        <div className="institution-name-cell">
          <div className="institution-avatar">
            <BookOpen size={17} />
          </div>
          <div>
            <strong>{subject.name}</strong>
            <span className="institution-mono">{subject.code}</span>
          </div>
        </div>
      ),
    },
    {
      key: "credits",
      header: "Credits",
      render: (subject) => (
        <span className="institution-mono">{subject.credits}</span>
      ),
    },
    {
      key: "type",
      header: "Type",
      render: (subject) => <Badge tone="info">{subject.subject_type}</Badge>,
    },
    {
      key: "status",
      header: "Status",
      render: (subject) => (
        <Badge tone={subject.status === "active" ? "success" : "warning"}>
          {subject.status}
        </Badge>
      ),
    },
    ...(canManage
      ? [
          {
            key: "actions",
            header: "",
            render: (subject: Subject) => (
              <span className="institution-row-actions">
                <button
                  type="button"
                  className="institution-icon-button"
                  title="Theory/Practical components"
                  onClick={() => {
                    setComponentsSubject(subject);
                    setComponentForm(emptyComponentForm());
                  }}
                >
                  <Layers size={16} />
                </button>
                <button
                  type="button"
                  className="institution-icon-button"
                  title="Prerequisites"
                  onClick={() => {
                    setPrereqSubject(subject);
                    setPrereqPickId(null);
                  }}
                >
                  <GitBranch size={16} />
                </button>
                <button
                  type="button"
                  className="institution-icon-button"
                  title="Edit subject"
                  onClick={() => openEditModal(subject)}
                >
                  <Pencil size={16} />
                </button>
                <button
                  type="button"
                  className="institution-icon-button danger"
                  title="Deactivate subject"
                  disabled={subject.status === "inactive"}
                  onClick={() => deactivateMutation.mutate(subject)}
                >
                  <Trash2 size={16} />
                </button>
              </span>
            ),
          } as DataTableColumn<Subject>,
        ]
      : []),
  ];

  return (
    <>
      {subjectsQuery.isError && (
        <Alert tone="error" className="institution-directory-error">
          Unable to load subjects. Please try again.
          <button
            type="button"
            onClick={() => void subjectsQuery.refetch()}
          >
            Retry
          </button>
        </Alert>
      )}

      <div className="institution-directory-toolbar">
        <SearchInput
          icon={Search}
          placeholder="Search subjects by code or name..."
          value={searchInput}
          onChange={(event) => setSearchInput(event.target.value)}
          aria-label="Search subjects"
        />

        <FilterSelect
          aria-label="Filter by subject type"
          value={typeFilter}
          onChange={setTypeFilter}
        >
          <option value="all">All Subject Types</option>
          <option value="theory">Theory</option>
          <option value="practical">Practical</option>
        </FilterSelect>

        {canManage && (
          <Button type="button" onClick={openCreateModal}>
            <Plus size={16} />
            Add Subject
          </Button>
        )}
      </div>

      <DataTable<Subject>
        columns={columns}
        rows={filteredSubjects}
        rowKey={(subject) => subject.id}
        loading={subjectsQuery.isLoading}
        error={
          subjectsQuery.isError ? "Subjects could not be loaded." : null
        }
        emptyMessage="No subjects yet. Add the first subject of a programme to get started."
      />

      <Modal
        open={componentsSubject !== null}
        title={componentsSubject ? `Components — ${componentsSubject.code} ${componentsSubject.name}` : "Components"}
        onClose={() => setComponentsSubject(null)}
        footer={
          <Button type="button" variant="secondary" onClick={() => setComponentsSubject(null)}>
            Close
          </Button>
        }
      >
        {componentsSubject && (
          <div className="institution-subject-components">
            <div className="institution-form-grid">
              <FormField label="Component" htmlFor="component-kind">
                <select
                  id="component-kind"
                  className="filter-select"
                  value={componentForm.kind}
                  onChange={(event) =>
                    setComponentForm((c) => ({ ...c, kind: event.target.value as SubjectComponentKind }))
                  }
                >
                  <option value="theory">Theory</option>
                  <option value="practical">Practical</option>
                  <option value="tutorial">Tutorial</option>
                </select>
              </FormField>
              <FormField label="Credits" htmlFor="component-credits">
                <Input
                  id="component-credits"
                  type="number"
                  min={0}
                  max={30}
                  value={componentForm.credits}
                  onChange={(event) =>
                    setComponentForm((c) => ({ ...c, credits: Number(event.target.value) }))
                  }
                />
              </FormField>
              <FormField label="Hours/week" htmlFor="component-hours">
                <Input
                  id="component-hours"
                  type="number"
                  min={0}
                  max={40}
                  value={componentForm.hours_per_week}
                  onChange={(event) =>
                    setComponentForm((c) => ({ ...c, hours_per_week: Number(event.target.value) }))
                  }
                />
              </FormField>
              <FormField label="Max marks" htmlFor="component-marks">
                <Input
                  id="component-marks"
                  type="number"
                  min={0}
                  value={componentForm.max_marks}
                  onChange={(event) =>
                    setComponentForm((c) => ({ ...c, max_marks: event.target.value }))
                  }
                  placeholder="optional"
                />
              </FormField>
            </div>
            <Button type="button" onClick={() => upsertComponentMutation.mutate()} disabled={upsertComponentMutation.isPending}>
              <Check size={14} /> Save component
            </Button>

            <table className="institution-mini-table">
              <thead>
                <tr><th>Kind</th><th>Credits</th><th>Hours/wk</th><th>Max marks</th><th></th></tr>
              </thead>
              <tbody>
                {components.map((component) => (
                  <tr key={component.id}>
                    <td><span className="institution-component-kind">{component.kind}</span></td>
                    <td>{component.credits}</td>
                    <td>{component.hours_per_week}</td>
                    <td>{component.max_marks ?? "—"}</td>
                    <td>
                      <button
                        type="button"
                        className="institution-icon-button"
                        title="Remove component"
                        onClick={() => removeComponentMutation.mutate(component.kind)}
                      >
                        <X size={16} />
                      </button>
                    </td>
                  </tr>
                ))}
                {components.length === 0 && (
                  <tr><td colSpan={5}>No components yet (subject is a single block).</td></tr>
                )}
              </tbody>
            </table>
            {componentsQuery.isError && <Alert tone="error">Unable to load components.</Alert>}
          </div>
        )}
      </Modal>

      <Modal
        open={prereqSubject !== null}
        title={prereqSubject ? `Prerequisites — ${prereqSubject.code} ${prereqSubject.name}` : "Prerequisites"}
        onClose={() => setPrereqSubject(null)}
        footer={
          <Button type="button" variant="secondary" onClick={() => setPrereqSubject(null)}>
            Close
          </Button>
        }
      >
        {prereqSubject && (
          <>
            <div className="institution-elective-attach">
              <select
                className="filter-select"
                value={prereqPickId ?? ""}
                onChange={(event) =>
                  setPrereqPickId(event.target.value ? Number(event.target.value) : null)
                }
                aria-label="Add prerequisite"
              >
                <option value="">Select a subject...</option>
                {availablePrereqs.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.code} — {s.name}
                  </option>
                ))}
              </select>
              <Button
                type="button"
                disabled={!prereqPickId || addPrereqMutation.isPending}
                onClick={() => prereqPickId && addPrereqMutation.mutate(prereqPickId)}
              >
                <Plus size={14} /> Add
              </Button>
            </div>
            <ul className="institution-elective-list">
              {prereqs.map((row) => (
                <li key={row.id}>
                  <span>
                    <strong>{row.prerequisite.code}</strong> — {row.prerequisite.name}
                  </span>
                  <button
                    type="button"
                    className="institution-icon-button"
                    title="Remove prerequisite"
                    onClick={() => removePrereqMutation.mutate(row.id)}
                  >
                    <X size={16} />
                  </button>
                </li>
              ))}
              {prereqs.length === 0 && <li>No prerequisites — this subject can be taken from day one.</li>}
            </ul>
            {prereqsQuery.isError && <Alert tone="error">Unable to load prerequisites.</Alert>}
          </>
        )}
      </Modal>

      <Modal
        open={modalOpen}
        title={editingSubject ? "Edit Subject" : "Add Subject"}
        onClose={() => setModalOpen(false)}
        footer={
          <>
            <Button
              type="button"
              variant="secondary"
              onClick={() => setModalOpen(false)}
            >
              Cancel
            </Button>
            <Button
              type="button"
              onClick={handleSave}
              disabled={saveMutation.isPending}
            >
              {saveMutation.isPending ? "Saving..." : "Save subject"}
            </Button>
          </>
        }
      >
        {formError && (
          <div className="institution-form-alert" role="alert">
            {formError}
          </div>
        )}

        <div className="institution-form-grid">
          <FormField label="Code" htmlFor="subject-code" required>
            <Input
              id="subject-code"
              value={form.code}
              onChange={(event) =>
                setForm((current) => ({ ...current, code: event.target.value }))
              }
              placeholder="CS301"
            />
          </FormField>

          <FormField label="Credits" htmlFor="subject-credits">
            <Input
              id="subject-credits"
              type="number"
              min={0}
              max={30}
              value={form.credits}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  credits: Number(event.target.value),
                }))
              }
            />
          </FormField>
        </div>

        <FormField label="Name" htmlFor="subject-name" required>
          <Input
            id="subject-name"
            value={form.name}
            onChange={(event) =>
              setForm((current) => ({ ...current, name: event.target.value }))
            }
            placeholder="Data Structures and Algorithms"
          />
        </FormField>

        <div className="institution-form-grid">
          <FormField label="Type" htmlFor="subject-type">
            <select
              id="subject-type"
              className="filter-select"
              value={form.subject_type}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  subject_type: event.target.value as SubjectType,
                }))
              }
            >
              <option value="theory">Theory</option>
              <option value="practical">Practical</option>
            </select>
          </FormField>

          <FormField label="Status" htmlFor="subject-status">
            <select
              id="subject-status"
              className="filter-select"
              value={form.status}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  status: event.target.value as AcademicStatus,
                }))
              }
            >
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </FormField>
        </div>
      </Modal>
    </>
  );
};

export default AcademicSubjectsPage;
