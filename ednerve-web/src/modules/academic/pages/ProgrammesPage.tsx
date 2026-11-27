import "../academic.css";
import { GraduationCap, Pencil, Plus, Trash2, BookOpenCheck } from "lucide-react";
import { useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useQuery, useQueryClient, useMutation } from "@tanstack/react-query";
import { toast } from "sonner";

import { academicService } from "../api";
import type { Programme, ProgrammeLevel, AcademicStatus } from "../types";

import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin } from "../../../shared/lib/rbac";
import { useDebouncedValue } from "../../../shared/lib/hooks/useDebouncedValue";
import { getApiErrorMessage } from "../../../shared/lib/api-error";

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

const EMPTY_LIST: never[] = [];

const emptyForm = {
  code: "",
  name: "",
  level: "UG" as ProgrammeLevel,
  duration_semesters: 8,
  status: "active" as AcademicStatus,
};

const ProgrammesPage = () => {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const canManage = isInstitutionAdmin(user);

  const [searchInput, setSearchInput] = useState("");
  const [levelFilter, setLevelFilter] = useState<string>("all");
  const debouncedSearch = useDebouncedValue(searchInput.trim(), 300);

  const programmesQuery = useQuery({
    queryKey: ["academic", "programmes"],
    queryFn: () => academicService.getProgrammes(),
  });

  const allProgrammes = programmesQuery.data?.programmes ?? EMPTY_LIST;

  const filteredProgrammes = useMemo(() => {
    const term = debouncedSearch.toLowerCase();
    return allProgrammes.filter((programme) => {
      const matchesSearch =
        !term ||
        programme.code.toLowerCase().includes(term) ||
        programme.name.toLowerCase().includes(term);
      const matchesLevel =
        levelFilter === "all" || programme.level === levelFilter;
      return matchesSearch && matchesLevel;
    });
  }, [allProgrammes, debouncedSearch, levelFilter]);

  const [modalOpen, setModalOpen] = useState(false);
  const [editingProgramme, setEditingProgramme] = useState<Programme | null>(
    null
  );
  const [form, setForm] = useState(emptyForm);
  const [formError, setFormError] = useState("");

  const openCreateModal = () => {
    setEditingProgramme(null);
    setForm(emptyForm);
    setFormError("");
    setModalOpen(true);
  };

  const openEditModal = (programme: Programme) => {
    setEditingProgramme(programme);
    setForm({
      code: programme.code,
      name: programme.name,
      level: programme.level,
      duration_semesters: programme.duration_semesters,
      status: programme.status,
    });
    setFormError("");
    setModalOpen(true);
  };

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        code: form.code.trim(),
        name: form.name.trim(),
        level: form.level,
        duration_semesters: Number(form.duration_semesters),
        status: form.status,
      };

      if (editingProgramme) {
        return academicService.updateProgramme(editingProgramme.id, payload);
      }
      return academicService.createProgramme(payload);
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["academic", "programmes"] });
      toast.success(editingProgramme ? "Programme updated." : "Programme created.");
      setModalOpen(false);
    },
    onError: (error) => {
      setFormError(getApiErrorMessage(error, "Unable to save the programme."));
    },
  });

  const deactivateMutation = useMutation({
    mutationFn: (programme: Programme) =>
      academicService.deactivateProgramme(programme.id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["academic", "programmes"] });
      toast.success("Programme deactivated.");
    },
    onError: (error) => {
      toast.error(
        getApiErrorMessage(error, "Unable to deactivate this programme.")
      );
    },
  });

  const handleSave = () => {
    setFormError("");

    if (!form.code.trim()) {
      setFormError("Programme code is required.");
      return;
    }
    if (!form.name.trim()) {
      setFormError("Programme name is required.");
      return;
    }
    if (!Number.isFinite(Number(form.duration_semesters)) ||
        Number(form.duration_semesters) < 1) {
      setFormError("Duration must be at least 1 semester.");
      return;
    }

    saveMutation.mutate();
  };

  const columns: DataTableColumn<Programme>[] = [
    {
      key: "programme",
      header: "Programme",
      render: (programme) => (
        <div className="institution-name-cell">
          <div className="institution-avatar">
            <GraduationCap size={17} />
          </div>
          <div>
            <strong>{programme.name}</strong>
            <span className="institution-mono">{programme.code}</span>
          </div>
        </div>
      ),
    },
    {
      key: "level",
      header: "Level",
      render: (programme) => <Badge tone="info">{programme.level}</Badge>,
    },
    {
      key: "duration",
      header: "Duration",
      render: (programme) => (
        <span className="institution-mono">
          {programme.duration_semesters} semesters
        </span>
      ),
    },
    {
      key: "status",
      header: "Status",
      render: (programme) => (
        <Badge tone={programme.status === "active" ? "success" : "warning"}>
          {programme.status}
        </Badge>
      ),
    },
    {
      key: "courses",
      header: "Courses",
      render: (programme) => (
        <button
          type="button"
          className="workspace-action-button academic-programme-manage-button"
          onClick={() =>
            navigate(`/institution/academic/programmes/${programme.id}/courses`)
          }
        >
          <BookOpenCheck size={13} />
          Manage
        </button>
      ),
    },
    ...(canManage
      ? [
          {
            key: "actions",
            header: "",
            render: (programme: Programme) => (
              <span className="institution-row-actions">
                <button
                  type="button"
                  className="institution-icon-button"
                  title="Edit programme"
                  onClick={() => openEditModal(programme)}
                >
                  <Pencil size={16} />
                </button>
                <button
                  type="button"
                  className="institution-icon-button danger"
                  title="Deactivate programme"
                  disabled={programme.status === "inactive"}
                  onClick={() => deactivateMutation.mutate(programme)}
                >
                  <Trash2 size={16} />
                </button>
              </span>
            ),
          } as DataTableColumn<Programme>,
        ]
      : []),
  ];

  return (
    <>
      {programmesQuery.isError && (
        <Alert tone="error" className="institution-directory-error">
          Unable to load programmes. Please try again.
          <button
            type="button"
            onClick={() => void programmesQuery.refetch()}
          >
            Retry
          </button>
        </Alert>
      )}

      <div className="institution-directory-toolbar">
        <SearchInput
          icon={Search}
          placeholder="Search programmes by code or name..."
          value={searchInput}
          onChange={(event) => setSearchInput(event.target.value)}
          aria-label="Search programmes"
        />

        <FilterSelect
          aria-label="Filter by level"
          value={levelFilter}
          onChange={setLevelFilter}
        >
          <option value="all">All Levels</option>
          <option value="UG">Undergraduate (UG)</option>
          <option value="PG">Postgraduate (PG)</option>
        </FilterSelect>

        {canManage && (
          <Button type="button" onClick={openCreateModal}>
            <Plus size={16} />
            Add Programme
          </Button>
        )}
      </div>

      <DataTable<Programme>
        columns={columns}
        rows={filteredProgrammes}
        rowKey={(programme) => programme.id}
        loading={programmesQuery.isLoading}
        error={
          programmesQuery.isError ? "Programmes could not be loaded." : null
        }
        emptyMessage="No programmes yet. Add one to configure its curriculum."
      />

      <Modal
        open={modalOpen}
        title={editingProgramme ? "Edit Programme" : "Add Programme"}
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
              {saveMutation.isPending ? "Saving..." : "Save programme"}
            </Button>
          </>
        }
      >
        {formError && (
          <div className="institution-form-alert" role="alert">
            {formError}
          </div>
        )}

        <FormField label="Code" htmlFor="programme-code" required>
          <Input
            id="programme-code"
            value={form.code}
            onChange={(event) =>
              setForm((current) => ({ ...current, code: event.target.value }))
            }
            placeholder="BTCSE"
          />
        </FormField>

        <FormField label="Name" htmlFor="programme-name" required>
          <Input
            id="programme-name"
            value={form.name}
            onChange={(event) =>
              setForm((current) => ({ ...current, name: event.target.value }))
            }
            placeholder="B.Tech Computer Science and Engineering"
          />
        </FormField>

        <div className="institution-form-grid">
          <FormField label="Level" htmlFor="programme-level">
            <select
              id="programme-level"
              className="filter-select"
              value={form.level}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  level: event.target.value as ProgrammeLevel,
                }))
              }
            >
              <option value="UG">Undergraduate (UG)</option>
              <option value="PG">Postgraduate (PG)</option>
            </select>
          </FormField>

          <FormField label="Duration (semesters)" htmlFor="programme-duration">
            <Input
              id="programme-duration"
              type="number"
              min={1}
              max={20}
              value={form.duration_semesters}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  duration_semesters: Number(event.target.value),
                }))
              }
            />
          </FormField>
        </div>

        <FormField label="Status" htmlFor="programme-status">
          <select
            id="programme-status"
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
      </Modal>
    </>
  );
};

export default ProgrammesPage;
