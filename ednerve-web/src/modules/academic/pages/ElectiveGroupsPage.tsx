import "../academic.css";
import {
  ListChecks,
  Pencil,
  Plus,
  Search,
  Trash2,
  X,
} from "lucide-react";
import { useMemo, useState } from "react";
import { useQuery, useQueryClient, useMutation } from "@tanstack/react-query";
import { toast } from "sonner";

import { academicService, academicStructureService } from "../api";
import type { ElectiveGroup } from "../types";

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

const EMPTY_LIST: never[] = [];

const emptyForm = {
  programme_id: 0,
  semester: 1,
  code: "",
  name: "",
  required_choices: 1,
};

const ElectiveGroupsPage = () => {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const canManage = isInstitutionAdmin(user);

  const [programmeFilter, setProgrammeFilter] = useState<string>("all");
  const [semesterFilter, setSemesterFilter] = useState<string>("all");
  const [search, setSearch] = useState("");
  const debouncedSearch = useDebouncedValue(search.trim(), 300);

  const programmesQuery = useQuery({
    queryKey: ["academic", "programmes"],
    queryFn: () => academicService.getProgrammes(),
  });
  const programmes = programmesQuery.data?.programmes ?? EMPTY_LIST;

  const groupsQuery = useQuery({
    queryKey: ["academic", "elective-groups", programmeFilter, semesterFilter],
    queryFn: () =>
      academicStructureService.getElectiveGroups({
        programme_id: programmeFilter !== "all" ? Number(programmeFilter) : undefined,
        semester: semesterFilter !== "all" ? Number(semesterFilter) : undefined,
      }),
  });

  const allGroups = groupsQuery.data?.groups ?? EMPTY_LIST;

  const filteredGroups = useMemo(() => {
    const term = debouncedSearch.toLowerCase();
    return allGroups.filter((group) => {
      if (!term) return true;
      return (
        group.code.toLowerCase().includes(term) ||
        group.name.toLowerCase().includes(term) ||
        group.programme_subjects.some((ps) => ps.subject?.code.toLowerCase().includes(term) || ps.subject?.name.toLowerCase().includes(term))
      );
    });
  }, [allGroups, debouncedSearch]);

  const [modalOpen, setModalOpen] = useState(false);
  const [editingGroup, setEditingGroup] = useState<ElectiveGroup | null>(null);
  const [form, setForm] = useState(emptyForm);
  const [formError, setFormError] = useState("");

  // subject attachment modal
  const [attachGroup, setAttachGroup] = useState<ElectiveGroup | null>(null);
  const [selectedSubjectId, setSelectedSubjectId] = useState<number | null>(null);

  const programmeSubjectsQuery = useQuery({
    queryKey: ["academic", "programme-subjects", attachGroup?.programme_id],
    queryFn: () => academicService.getProgrammeSubjects(attachGroup!.programme_id),
    enabled: !!attachGroup,
  });

  const openCreateModal = () => {
    setEditingGroup(null);
    setForm({ ...emptyForm, programme_id: programmes[0]?.id ?? 0 });
    setFormError("");
    setModalOpen(true);
  };

  const openEditModal = (group: ElectiveGroup) => {
    setEditingGroup(group);
    setForm({
      programme_id: group.programme_id,
      semester: group.semester,
      code: group.code,
      name: group.name,
      required_choices: group.required_choices,
    });
    setFormError("");
    setModalOpen(true);
  };

  const saveMutation = useMutation({
    mutationFn: async () => {
      if (editingGroup) {
        return academicStructureService.updateElectiveGroup(editingGroup.id, {
          name: form.name.trim(),
          required_choices: Number(form.required_choices),
          status: editingGroup.status,
        });
      }
      return academicStructureService.createElectiveGroup({
        programme_id: form.programme_id,
        semester: Number(form.semester),
        code: form.code.trim(),
        name: form.name.trim(),
        required_choices: Number(form.required_choices),
      });
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["academic", "elective-groups"] });
      toast.success(editingGroup ? "Elective group updated." : "Elective group created.");
      setModalOpen(false);
    },
    onError: (error) => {
      setFormError(getApiErrorMessage(error, "Unable to save the elective group."));
    },
  });

  const deactivateMutation = useMutation({
    mutationFn: (group: ElectiveGroup) => academicStructureService.deactivateElectiveGroup(group.id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["academic", "elective-groups"] });
      toast.success("Elective group deactivated.");
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error, "Unable to deactivate the elective group."));
    },
  });

  const attachMutation = useMutation({
    mutationFn: ({ groupId, programmeSubjectId }: { groupId: number; programmeSubjectId: number }) =>
      academicStructureService.attachElectiveGroupSubject(groupId, programmeSubjectId),
    onSuccess: async (data) => {
      await queryClient.invalidateQueries({ queryKey: ["academic", "elective-groups"] });
      toast.success("Subject attached.");
      if (attachGroup) {
        const updated = (data as { group?: ElectiveGroup })?.group;
        setAttachGroup(updated ?? attachGroup);
      }
      setSelectedSubjectId(null);
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error, "Unable to attach the subject."));
    },
  });

  const detachMutation = useMutation({
    mutationFn: ({ groupId, programmeSubjectId }: { groupId: number; programmeSubjectId: number }) =>
      academicStructureService.detachElectiveGroupSubject(groupId, programmeSubjectId),
    onSuccess: async (data) => {
      await queryClient.invalidateQueries({ queryKey: ["academic", "elective-groups"] });
      toast.success("Subject detached.");
      if (attachGroup) {
        const updated = (data as { group?: ElectiveGroup })?.group;
        setAttachGroup(updated ?? attachGroup);
      }
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error, "Unable to detach the subject."));
    },
  });

  const handleSave = () => {
    setFormError("");
    if (!form.code.trim()) {
      setFormError("Group code is required.");
      return;
    }
    if (!form.name.trim()) {
      setFormError("Group name is required.");
      return;
    }
    if (!editingGroup && !form.programme_id) {
      setFormError("Programme is required.");
      return;
    }
    saveMutation.mutate();
  };

  const availableSubjects = (programmeSubjectsQuery.data?.subjects ?? []).filter(
    (item) => item.requirement_type === "elective" &&
      !attachGroup?.programme_subjects.some((attached) => attached.id === item.id)
  );

  const columns: DataTableColumn<ElectiveGroup>[] = [
    {
      key: "group",
      header: "Elective group",
      render: (group) => (
        <div className="institution-name-cell">
          <div className="institution-avatar">
            <ListChecks size={17} />
          </div>
          <div>
            <strong>{group.name}</strong>
            <span className="institution-mono">{group.code}</span>
          </div>
        </div>
      ),
    },
    {
      key: "programme",
      header: "Programme",
      render: (group) =>
        group.programme ? (
          <span className="institution-mono">
            {group.programme.code} · Sem {group.semester}
          </span>
        ) : (
          <span className="institution-muted">—</span>
        ),
    },
    {
      key: "requirements",
      header: "Required choices",
      render: (group) => (
        <span className="institution-mono">
          {group.required_choices} choice(s) required
        </span>
      ),
    },
    {
      key: "subjects",
      header: "Offered subjects",
      render: (group) => (
        <span>{group.programme_subjects.map((s) => s.subject?.code ?? "").join(", ") || "—"}</span>
      ),
    },
    {
      key: "status",
      header: "Status",
      render: (group) => (
        <Badge tone={group.status === "active" ? "success" : "warning"}>{group.status}</Badge>
      ),
    },
    {
      key: "actions",
      header: "",
      render: (group) => (
        <span className="institution-row-actions">
          {canManage && (
            <>
              <button
                type="button"
                className="institution-icon-button"
                title="Manage offered subjects"
                onClick={() => setAttachGroup(group)}
              >
                <ListChecks size={16} />
              </button>
              <button
                type="button"
                className="institution-icon-button"
                title="Edit group"
                onClick={() => openEditModal(group)}
              >
                <Pencil size={16} />
              </button>
              <button
                type="button"
                className="institution-icon-button danger"
                title="Deactivate group"
                disabled={group.status === "inactive"}
                onClick={() => deactivateMutation.mutate(group)}
              >
                <Trash2 size={16} />
              </button>
            </>
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
          placeholder="Search elective groups or subjects..."
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          aria-label="Search elective groups"
        />
        <FilterSelect
          aria-label="Filter by programme"
          value={programmeFilter}
          onChange={setProgrammeFilter}
        >
          <option value="all">All Programmes</option>
          {programmes.map((p) => (
            <option key={p.id} value={p.id}>
              {p.code} — {p.name}
            </option>
          ))}
        </FilterSelect>
        <FilterSelect
          aria-label="Filter by semester"
          value={semesterFilter}
          onChange={setSemesterFilter}
        >
          <option value="all">All Semesters</option>
          {Array.from({ length: 12 }, (_, i) => i + 1).map((n) => (
            <option key={n} value={n}>
              Semester {n}
            </option>
          ))}
        </FilterSelect>
        {canManage && (
          <Button type="button" onClick={openCreateModal}>
            <Plus size={16} />
            Add Elective Group
          </Button>
        )}
      </div>

      {groupsQuery.isError && (
        <Alert tone="error" className="institution-directory-error">
          Unable to load elective groups. Please try again.
          <button type="button" onClick={() => void groupsQuery.refetch()}>
            Retry
          </button>
        </Alert>
      )}

      <DataTable<ElectiveGroup>
        columns={columns}
        rows={filteredGroups}
        rowKey={(group) => group.id}
        loading={groupsQuery.isLoading}
        error={groupsQuery.isError ? "Elective groups could not be loaded." : null}
        emptyMessage="No elective groups yet. Create a group such as 'Open Elective II'."
      />

      <Modal
        open={modalOpen}
        title={editingGroup ? "Edit Elective Group" : "New Elective Group"}
        onClose={() => setModalOpen(false)}
        footer={
          <>
            <Button type="button" variant="secondary" onClick={() => setModalOpen(false)}>
              Cancel
            </Button>
            <Button type="button" onClick={handleSave} disabled={saveMutation.isPending}>
              {saveMutation.isPending ? "Saving..." : "Save group"}
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
          {!editingGroup && (
            <FormField label="Programme" htmlFor="eg-programme" required>
              <select
                id="eg-programme"
                className="filter-select"
                value={form.programme_id}
                onChange={(event) => setForm((c) => ({ ...c, programme_id: Number(event.target.value) }))}
              >
                {programmes.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.code} — {p.name}
                  </option>
                ))}
              </select>
            </FormField>
          )}
          <FormField label="Semester" htmlFor="eg-semester" required>
            <select
              id="eg-semester"
              className="filter-select"
              value={form.semester}
              onChange={(event) => setForm((c) => ({ ...c, semester: Number(event.target.value) }))}
              disabled={Boolean(editingGroup)}
            >
              {Array.from({ length: 12 }, (_, i) => i + 1).map((n) => (
                <option key={n} value={n}>
                  Semester {n}
                </option>
              ))}
            </select>
          </FormField>
        </div>

        <div className="institution-form-grid">
          <FormField label="Code" htmlFor="eg-code" required>
            <Input
              id="eg-code"
              value={form.code}
              onChange={(event) => setForm((c) => ({ ...c, code: event.target.value }))}
              placeholder="OPEN-II"
              disabled={Boolean(editingGroup)}
            />
          </FormField>
</div>

        <FormField label="Name" htmlFor="eg-name" required>
          <Input
            id="eg-name"
            value={form.name}
            onChange={(event) => setForm((c) => ({ ...c, name: event.target.value }))}
            placeholder="Open Elective II (choose 1 of 5)"
          />
        </FormField>

        <FormField label="Required choices" htmlFor="eg-choices">
          <Input
            id="eg-choices"
            type="number"
            min={1}
            value={form.required_choices}
            onChange={(event) =>
              setForm((c) => ({ ...c, required_choices: Number(event.target.value) }))
            }
          />
        </FormField>
      </Modal>

      <Modal
        open={attachGroup !== null}
        title={attachGroup ? `Elective Subject Options — ${attachGroup.code}` : "Elective Subject Options"}
        onClose={() => setAttachGroup(null)}
        footer={
          <Button type="button" variant="secondary" onClick={() => setAttachGroup(null)}>
            Close
          </Button>
        }
      >
        {attachGroup && (
          <>
            <div className="institution-elective-attach">
              <select
                className="filter-select"
                value={selectedSubjectId ?? ""}
                onChange={(event) =>
                  setSelectedSubjectId(event.target.value ? Number(event.target.value) : null)
                }
                aria-label="Add subject to group"
              >
                <option value="">Select a programme subject to add...</option>
                {availableSubjects.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.subject?.code} — {s.subject?.name}
                  </option>
                ))}
              </select>
              <Button
                type="button"
                disabled={!selectedSubjectId || attachMutation.isPending}
                onClick={() =>
                  selectedSubjectId &&
                  attachMutation.mutate({ groupId: attachGroup.id, programmeSubjectId: selectedSubjectId })
                }
              >
                <Plus size={14} /> Add
              </Button>
            </div>

            <ul className="institution-elective-list">
              {attachGroup.programme_subjects.map((programmeSubject) => (
                <li key={programmeSubject.id}>
                  <span>
                    <strong>{programmeSubject.subject?.code}</strong> — {programmeSubject.subject?.name} ({programmeSubject.subject?.credits} credits)
                  </span>
                  {canManage && (
                    <button
                      type="button"
                      className="institution-icon-button"
                      title="Remove from group"
                      onClick={() =>
                        detachMutation.mutate({ groupId: attachGroup.id, programmeSubjectId: programmeSubject.id })
                      }
                    >
                      <X size={16} />
                    </button>
                  )}
                </li>
              ))}
              {attachGroup.programme_subjects.length === 0 && <li>No subjects offered yet.</li>}
            </ul>
          </>
        )}
      </Modal>
    </>
  );
};

export default ElectiveGroupsPage;
