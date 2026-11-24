import {
  BookOpen,
  CheckCircle2,
  FileText,
  ListPlus,
  Search,
  SendHorizonal,
  Trash2,
  UploadCloud,
  Eye,
} from "lucide-react";
import { useMemo, useState } from "react";
import { useQuery, useQueryClient, useMutation } from "@tanstack/react-query";
import { toast } from "sonner";

import { academicService } from "../api";
import { academicStructureService } from "../api";
import type {
  AcademicSession,
  Syllabus,
  SyllabusStatus,
  SyllabusUnit,
} from "../types";
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
  Textarea,
} from "../../../shared/ui";

const EMPTY_LIST: never[] = [];

const STATUS_LABEL: Record<SyllabusStatus, string> = {
  draft: "Draft",
  in_review: "In Review",
  approved: "Approved",
  published: "Published",
  archived: "Archived",
};

const STATUS_TONE: Record<SyllabusStatus, "success" | "warning" | "info" | "neutral"> = {
  draft: "neutral",
  in_review: "warning",
  approved: "info",
  published: "success",
  archived: "neutral",
};

const emptyUnit = (position: number): SyllabusUnit => ({
  position,
  unit_number: "",
  title: "",
  topics: "",
  learning_outcomes: "",
  prescribed_hours: 0,
});

const emptyForm = {
  subject_id: 0,
  academic_session_id: null as number | null,
  version: "",
  title: "",
  units: [emptyUnit(1)] as SyllabusUnit[],
};

const SyllabiPage = () => {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const canManage = isInstitutionAdmin(user);

  const [subjectFilter, setSubjectFilter] = useState<string>("all");
  const [statusFilter, setStatusFilter] = useState<string>("all");
  const [search, setSearch] = useState("");
  const debouncedSearch = useDebouncedValue(search.trim(), 300);

  const subjectsQuery = useQuery({
    queryKey: ["academic", "subjects"],
    queryFn: () => academicService.getSubjects(),
  });
  const sessionsQuery = useQuery({
    queryKey: ["academic", "sessions"],
    queryFn: () => academicService.getSessions(),
  });
  const subjects = subjectsQuery.data?.subjects ?? EMPTY_LIST;
  const sessions = sessionsQuery.data?.sessions ?? EMPTY_LIST;

  const syllabiQuery = useQuery({
    queryKey: ["academic", "syllabi", subjectFilter, statusFilter],
    queryFn: () =>
      academicStructureService.getSyllabi({
        subject_id: subjectFilter !== "all" ? Number(subjectFilter) : undefined,
        status: statusFilter !== "all" ? statusFilter : undefined,
      }),
  });

  const allSyllabi = syllabiQuery.data?.syllabi ?? EMPTY_LIST;

  const filteredSyllabi = useMemo(() => {
    const term = debouncedSearch.toLowerCase();
    return allSyllabi.filter((s) => {
      if (!term) return true;
      return (
        s.version.toLowerCase().includes(term) ||
        s.title.toLowerCase().includes(term) ||
        (s.subject?.code.toLowerCase().includes(term) ?? false) ||
        (s.subject?.name.toLowerCase().includes(term) ?? false)
      );
    });
  }, [allSyllabi, debouncedSearch]);

  const [modalOpen, setModalOpen] = useState(false);
  const [editingSyllabus, setEditingSyllabus] = useState<Syllabus | null>(null);
  const [viewingSyllabus, setViewingSyllabus] = useState<Syllabus | null>(null);
  const [form, setForm] = useState(emptyForm);
  const [formError, setFormError] = useState("");

  const openCreate = () => {
    setEditingSyllabus(null);
    setForm({
      ...emptyForm,
      subject_id: subjects[0]?.id ?? 0,
      academic_session_id: sessions.find((s) => s.is_active)?.id ?? null,
    });
    setFormError("");
    setModalOpen(true);
  };

  const openEdit = (syllabus: Syllabus) => {
    setEditingSyllabus(syllabus);
    setForm({
      subject_id: syllabus.subject_id,
      academic_session_id: syllabus.academic_session_id,
      version: syllabus.version,
      title: syllabus.title,
      units:
        syllabus.units.length > 0
          ? syllabus.units.map((u, i) => ({ ...u, position: u.position ?? i + 1 }))
          : [emptyUnit(1)],
    });
    setFormError("");
    setModalOpen(true);
  };

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        subject_id: form.subject_id,
        academic_session_id: form.academic_session_id,
        version: form.version.trim(),
        title: form.title.trim(),
        units: form.units.map((u, i) => ({
          ...u,
          position: i + 1,
          unit_number: u.unit_number.trim(),
          title: u.title.trim(),
          prescribed_hours: Number(u.prescribed_hours) || 0,
        })),
      };

      if (editingSyllabus) {
        return academicStructureService.updateSyllabus(editingSyllabus.id, payload);
      }
      return academicStructureService.createSyllabus(payload);
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["academic", "syllabi"] });
      toast.success(editingSyllabus ? "Syllabus updated." : "Syllabus draft created.");
      setModalOpen(false);
    },
    onError: (error) => {
      setFormError(getApiErrorMessage(error, "Unable to save the syllabus."));
    },
  });

  const actionMutation = useMutation({
    mutationFn: async ({ action, id }: { action: string; id: number }) => {
      switch (action) {
        case "submit":
          return academicStructureService.submitSyllabus(id);
        case "approve":
          return academicStructureService.approveSyllabus(id);
        case "request-changes":
          return academicStructureService.requestSyllabusChanges(id);
        case "publish":
          return academicStructureService.publishSyllabus(id);
        case "archive":
          return academicStructureService.archiveSyllabus(id);
        case "clone":
          return academicStructureService.cloneSyllabus(id);
        default:
          throw new Error("Unknown action");
      }
    },
    onSuccess: async (_, { action }) => {
      await queryClient.invalidateQueries({ queryKey: ["academic", "syllabi"] });
      const messages: Record<string, string> = {
        submit: "Syllabus submitted for review.",
        approve: "Syllabus approved.",
        "request-changes": "Syllabus returned to draft.",
        publish: "Syllabus published.",
        archive: "Syllabus archived.",
        clone: "Syllabus cloned as a new draft.",
      };
      toast.success(messages[action] ?? "Done.");
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error, "Action failed."));
    },
  });

  const setUnit = (index: number, patch: Partial<SyllabusUnit>) =>
    setForm((current) => ({
      ...current,
      units: current.units.map((u, i) => (i === index ? { ...u, ...patch } : u)),
    }));

  const addUnit = () =>
    setForm((current) => ({ ...current, units: [...current.units, emptyUnit(current.units.length + 1)] }));

  const removeUnit = (index: number) =>
    setForm((current) => ({ ...current, units: current.units.filter((_, i) => i !== index) }));

  const columns: DataTableColumn<Syllabus>[] = [
    {
      key: "subject",
      header: "Subject",
      render: (s) => (
        <div className="institution-name-cell">
          <div className="institution-avatar">
            <BookOpen size={17} />
          </div>
          <div>
            <strong>{s.subject?.name ?? "—"}</strong>
            <span className="institution-mono">{s.subject?.code}</span>
          </div>
        </div>
      ),
    },
    {
      key: "version",
      header: "Version",
      render: (s) => <span className="institution-mono">{s.version}</span>,
    },
    {
      key: "session",
      header: "Session",
      render: (s) =>
        s.academic_session ? (
          <span className="institution-mono">{s.academic_session.code}</span>
        ) : (
          <span className="institution-muted">All sessions</span>
        ),
    },
    {
      key: "status",
      header: "Status",
      render: (s) => <Badge tone={STATUS_TONE[s.status]}>{STATUS_LABEL[s.status]}</Badge>,
    },
    {
      key: "title",
      header: "Title",
      render: (s) => <span>{s.title}</span>,
    },
    {
      key: "actions",
      header: "",
      render: (s) => (
        <span className="institution-row-actions">
          <button
            type="button"
            className="institution-icon-button"
            title="View syllabus"
            onClick={() => setViewingSyllabus(s)}
          >
            <Eye size={16} />
          </button>
          {canManage && s.status === "draft" && (
            <>
              <button
                type="button"
                className="institution-icon-button"
                title="Edit draft"
                onClick={() => openEdit(s)}
              >
                <FileText size={16} />
              </button>
              <button
                type="button"
                className="institution-icon-button"
                title="Submit for review"
                onClick={() => actionMutation.mutate({ action: "submit", id: s.id })}
              >
                <SendHorizonal size={16} />
              </button>
            </>
          )}
          {canManage && s.status === "in_review" && (
            <>
              <button
                type="button"
                className="institution-icon-button"
                title="Approve (separation of duties applies)"
                onClick={() => actionMutation.mutate({ action: "approve", id: s.id })}
              >
                <CheckCircle2 size={16} />
              </button>
              <button
                type="button"
                className="institution-icon-button"
                title="Request changes"
                onClick={() => actionMutation.mutate({ action: "request-changes", id: s.id })}
              >
                <FileText size={16} />
              </button>
            </>
          )}
          {canManage && s.status === "approved" && (
            <button
              type="button"
              className="institution-icon-button"
              title="Publish syllabus"
              onClick={() => actionMutation.mutate({ action: "publish", id: s.id })}
            >
              <UploadCloud size={16} />
            </button>
          )}
          {canManage && (s.status === "published" || s.status === "archived") && (
            <button
              type="button"
              className="institution-icon-button"
              title="Clone as new draft"
              onClick={() => actionMutation.mutate({ action: "clone", id: s.id })}
            >
              <ListPlus size={16} />
            </button>
          )}
          {canManage && (s.status === "draft" || s.status === "in_review") && (
            <button
              type="button"
              className="institution-icon-button danger"
              title="Archive"
              onClick={() => actionMutation.mutate({ action: "archive", id: s.id })}
            >
              <Trash2 size={16} />
            </button>
          )}
        </span>
      ),
    },
  ];

  return (
    <>
      <div className="institution-directory-toolbar">
        <SearchInput
          icon={Search}
          placeholder="Search syllabi by version, title or subject..."
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          aria-label="Search syllabi"
        />
        <FilterSelect
          aria-label="Filter by subject"
          value={subjectFilter}
          onChange={setSubjectFilter}
        >
          <option value="all">All Subjects</option>
          {subjects.map((s) => (
            <option key={s.id} value={s.id}>
              {s.code} — {s.name}
            </option>
          ))}
        </FilterSelect>
        <FilterSelect
          aria-label="Filter by status"
          value={statusFilter}
          onChange={setStatusFilter}
        >
          <option value="all">All Statuses</option>
          {(Object.keys(STATUS_LABEL) as SyllabusStatus[]).map((status) => (
            <option key={status} value={status}>
              {STATUS_LABEL[status]}
            </option>
          ))}
        </FilterSelect>
        {canManage && (
          <Button type="button" onClick={openCreate}>
            New Syllabus
          </Button>
        )}
      </div>

      {syllabiQuery.isError && (
        <Alert tone="error" className="institution-directory-error">
          Unable to load syllabi. Please try again.
          <button type="button" onClick={() => void syllabiQuery.refetch()}>
            Retry
          </button>
        </Alert>
      )}

      <DataTable<Syllabus>
        columns={columns}
        rows={filteredSyllabi}
        rowKey={(s) => s.id}
        loading={syllabiQuery.isLoading}
        error={syllabiQuery.isError ? "Syllabi could not be loaded." : null}
        emptyMessage="No syllabi yet. Create the first syllabus for a subject."
      />

      <Modal
        open={modalOpen}
        title={editingSyllabus ? `Edit Draft — ${editingSyllabus.title}` : "New Syllabus"}
        onClose={() => { setModalOpen(false); setForm(emptyForm); }}
        maxWidth={760}
        footer={
          <>
            <Button type="button" variant="secondary" onClick={() => { setModalOpen(false); setForm(emptyForm); }}>
              Cancel
            </Button>
            <Button type="button" onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending}>
              {saveMutation.isPending ? "Saving..." : "Save draft"}
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
          <FormField label="Subject" htmlFor="syllabus-subject" required>
            <select
              id="syllabus-subject"
              className="filter-select"
              value={form.subject_id}
              onChange={(event) => setForm((c) => ({ ...c, subject_id: Number(event.target.value) }))}
              disabled={Boolean(editingSyllabus)}
            >
              {subjects.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.code} — {s.name}
                </option>
              ))}
            </select>
          </FormField>

          <FormField label="Session" htmlFor="syllabus-session">
            <select
              id="syllabus-session"
              className="filter-select"
              value={form.academic_session_id ?? ""}
              onChange={(event) =>
                setForm((c) => ({
                  ...c,
                  academic_session_id: event.target.value
                    ? Number(event.target.value)
                    : null,
                }))
              }
            >
              <option value="">All sessions (always applicable)</option>
              {sessions.map((session: AcademicSession) => (
                <option key={session.id} value={session.id}>
                  {session.code} — {session.name}
                </option>
              ))}
            </select>
          </FormField>
        </div>

        <div className="institution-form-grid">
          <FormField label="Version" htmlFor="syllabus-version" required>
            <Input
              id="syllabus-version"
              value={form.version}
              onChange={(event) => setForm((c) => ({ ...c, version: event.target.value }))}
              placeholder="2026.1"
            />
          </FormField>
          <FormField label="Title" htmlFor="syllabus-title" required>
            <Input
              id="syllabus-title"
              value={form.title}
              onChange={(event) => setForm((c) => ({ ...c, title: event.target.value }))}
              placeholder="CS301 Syllabus 2026.1"
            />
          </FormField>
        </div>

        <div className="institution-syllabus-units">
          <div className="institution-syllabus-units__header">
            <h4>Units</h4>
            <Button type="button" variant="secondary" onClick={addUnit}>
              <ListPlus size={14} /> Add unit
            </Button>
          </div>
          {form.units.map((unit, index) => (
            <div className="institution-syllabus-unit" key={index}>
              <div className="institution-form-grid institution-form-grid--unit">
                <FormField label="Unit #" htmlFor={`unit-${index}-number`} required>
                  <Input
                    id={`unit-${index}-number`}
                    value={unit.unit_number}
                    onChange={(event) => setUnit(index, { unit_number: event.target.value })}
                    placeholder="1"
                  />
                </FormField>
                <FormField label="Unit title" htmlFor={`unit-${index}-title`} required>
                  <Input
                    id={`unit-${index}-title`}
                    value={unit.title}
                    onChange={(event) => setUnit(index, { title: event.target.value })}
                    placeholder="ER Modelling"
                  />
                </FormField>
                <FormField label="Hours" htmlFor={`unit-${index}-hours`}>
                  <Input
                    id={`unit-${index}-hours`}
                    type="number"
                    min={0}
                    value={unit.prescribed_hours}
                    onChange={(event) =>
                      setUnit(index, { prescribed_hours: Number(event.target.value) })
                    }
                  />
                </FormField>
                <div className="institution-syllabus-unit__remove">
                  <button
                    type="button"
                    className="institution-icon-button danger"
                    title="Remove unit"
                    onClick={() => removeUnit(index)}
                  >
                    <Trash2 size={16} />
                  </button>
                </div>
              </div>
              <FormField label="Topics (CommonMark)" htmlFor={`unit-${index}-topics`}>
                <Textarea
                  id={`unit-${index}-topics`}
                  value={unit.topics ?? ""}
                  onChange={(event) => setUnit(index, { topics: event.target.value })}
                  rows={2}
                />
              </FormField>
              <FormField label="Learning outcomes" htmlFor={`unit-${index}-outcomes`}>
                <Textarea
                  id={`unit-${index}-outcomes`}
                  value={unit.learning_outcomes ?? ""}
                  onChange={(event) => setUnit(index, { learning_outcomes: event.target.value })}
                  rows={2}
                />
              </FormField>
            </div>
          ))}
        </div>
      </Modal>

      <Modal
        open={viewingSyllabus !== null}
        title={viewingSyllabus ? `Syllabus ${viewingSyllabus.version} — ${viewingSyllabus.title}` : "Syllabus"}
        onClose={() => setViewingSyllabus(null)}
        maxWidth={760}
        footer={
          <Button type="button" variant="secondary" onClick={() => setViewingSyllabus(null)}>
            Close
          </Button>
        }
      >
        {viewingSyllabus && (
          <div className="institution-syllabus-view">
            <p className="institution-muted">
              Status: <Badge tone={STATUS_TONE[viewingSyllabus.status]}>{STATUS_LABEL[viewingSyllabus.status]}</Badge>
              {viewingSyllabus.approved_at && (
                <> · Approved {new Date(viewingSyllabus.approved_at).toLocaleDateString()}</>
              )}
              {viewingSyllabus.published_at && (
                <> · Published {new Date(viewingSyllabus.published_at).toLocaleDateString()}</>
              )}
            </p>
            {viewingSyllabus.units.map((unit) => (
              <section key={unit.unit_number} className="institution-syllabus-unit">
                <h4>
                  Unit {unit.unit_number}. {unit.title}
                  <span className="institution-muted"> · {unit.prescribed_hours}h</span>
                </h4>
                {unit.topics && <pre>{unit.topics}</pre>}
                {unit.learning_outcomes && (
                  <p>
                    <strong>Learning outcomes:</strong> {unit.learning_outcomes}
                  </p>
                )}
              </section>
            ))}
            {viewingSyllabus.units.length === 0 && <p>No units attached.</p>}
          </div>
        )}
      </Modal>
    </>
  );
};

export default SyllabiPage;
