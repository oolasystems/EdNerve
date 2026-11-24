import { CalendarDays, CalendarCheck, Plus, Pencil, Trash2 } from "lucide-react";
import { useMemo, useState } from "react";
import { useQuery, useQueryClient, useMutation } from "@tanstack/react-query";
import { toast } from "sonner";

import { academicService } from "../api";
import "../../institution/institution.css";
import type { AcademicSession, AcademicStatus } from "../types";

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

const EMPTY_LIST: never[] = [];

const emptyForm = {
  code: "",
  name: "",
  start_date: "",
  end_date: "",
  is_active: false,
  status: "active" as AcademicStatus,
};

const AcademicSessionsPage = () => {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const canManage = isInstitutionAdmin(user);

  const [searchInput, setSearchInput] = useState("");
  const [statusFilter, setStatusFilter] = useState<string>("all");
  const debouncedSearch = useDebouncedValue(searchInput.trim(), 300);

  const sessionsQuery = useQuery({
    queryKey: ["academic", "sessions"],
    queryFn: () => academicService.getSessions(),
  });

  const allSessions = sessionsQuery.data?.sessions ?? EMPTY_LIST;

  const filteredSessions = useMemo(() => {
    const term = debouncedSearch.toLowerCase();
    return allSessions.filter((session) => {
      const matchesSearch =
        !term ||
        session.code.toLowerCase().includes(term) ||
        session.name.toLowerCase().includes(term);
      const matchesStatus =
        statusFilter === "all" || session.status === statusFilter;
      return matchesSearch && matchesStatus;
    });
  }, [allSessions, debouncedSearch, statusFilter]);

  // ----- Modal -----
  const [modalOpen, setModalOpen] = useState(false);
  const [editingSession, setEditingSession] = useState<AcademicSession | null>(
    null
  );
  const [form, setForm] = useState(emptyForm);
  const [formError, setFormError] = useState("");

  const openCreateModal = () => {
    setEditingSession(null);
    setForm(emptyForm);
    setFormError("");
    setModalOpen(true);
  };

  const openEditModal = (session: AcademicSession) => {
    setEditingSession(session);
    setForm({
      code: session.code,
      name: session.name,
      start_date: session.start_date.slice(0, 10),
      end_date: session.end_date.slice(0, 10),
      is_active: session.is_active,
      status: session.status,
    });
    setFormError("");
    setModalOpen(true);
  };

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        code: form.code.trim(),
        name: form.name.trim(),
        start_date: form.start_date,
        end_date: form.end_date,
        is_active: form.is_active,
        status: form.status,
      };

      if (editingSession) {
        return academicService.updateSession(editingSession.id, payload);
      }
      return academicService.createSession(payload);
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["academic", "sessions"] });
      toast.success(editingSession ? "Session updated." : "Session created.");
      setModalOpen(false);
    },
    onError: (error) => {
      setFormError(getApiErrorMessage(error, "Unable to save the session."));
    },
  });

  const activateMutation = useMutation({
    mutationFn: (session: AcademicSession) =>
      academicService.activateSession(session.id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["academic", "sessions"] });
      toast.success("Session activated.");
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error, "Unable to activate the session."));
    },
  });

  const deactivateMutation = useMutation({
    mutationFn: (session: AcademicSession) =>
      academicService.deactivateSession(session.id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["academic", "sessions"] });
      toast.success("Session deactivated.");
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error, "Unable to deactivate the session."));
    },
  });

  const handleSave = () => {
    setFormError("");

    if (!form.code.trim()) {
      setFormError("Session code is required.");
      return;
    }
    if (!form.name.trim()) {
      setFormError("Session name is required.");
      return;
    }
    if (!form.start_date) {
      setFormError("Start date is required.");
      return;
    }
    if (!form.end_date) {
      setFormError("End date is required.");
      return;
    }

    saveMutation.mutate();
  };

  const columns: DataTableColumn<AcademicSession>[] = [
    {
      key: "session",
      header: "Session",
      render: (session) => (
        <div className="institution-name-cell">
          <div className="institution-avatar">
            <CalendarDays size={17} />
          </div>
          <div>
            <strong>{session.name}</strong>
            <span className="institution-mono">{session.code}</span>
          </div>
        </div>
      ),
    },
    {
      key: "period",
      header: "Period",
      render: (session) => (
        <span className="institution-mono">
          {session.start_date.slice(0, 10)} → {session.end_date.slice(0, 10)}
        </span>
      ),
    },
    {
      key: "active",
      header: "Active",
      render: (session) =>
        session.is_active ? (
          <Badge tone="success" dot>
            Current
          </Badge>
        ) : (
          <Badge tone="neutral">No</Badge>
        ),
    },
    {
      key: "status",
      header: "Status",
      render: (session) =>
        session.status === "active" ? (
          <Badge tone="success">active</Badge>
        ) : (
          <Badge tone="warning">inactive</Badge>
        ),
    },
    ...(canManage
      ? [
          {
            key: "actions",
            header: "",
            render: (session: AcademicSession) => (
              <span className="institution-row-actions">
                <button
                  type="button"
                  className="institution-icon-button"
                  title="Make active"
                  disabled={session.is_active || session.status === "inactive"}
                  onClick={() => activateMutation.mutate(session)}
                >
                  <CalendarCheck size={16} />
                </button>
                <button
                  type="button"
                  className="institution-icon-button"
                  title="Edit session"
                  onClick={() => openEditModal(session)}
                >
                  <Pencil size={16} />
                </button>
                <button
                  type="button"
                  className="institution-icon-button danger"
                  title="Deactivate session"
                  disabled={session.status === "inactive"}
                  onClick={() => deactivateMutation.mutate(session)}
                >
                  <Trash2 size={16} />
                </button>
              </span>
            ),
          } as DataTableColumn<AcademicSession>,
        ]
      : []),
  ];

  if (sessionsQuery.isError) {
    return (
      <Alert tone="error" className="institution-directory-error">
        Unable to load academic sessions.
        <button
          type="button"
          onClick={() => void sessionsQuery.refetch()}
        >
          Retry
        </button>
      </Alert>
    );
  }

  return (
    <>
      <div className="institution-directory-toolbar">
        <SearchInput
          icon={Search}
          placeholder="Search sessions..."
          value={searchInput}
          onChange={(event) => setSearchInput(event.target.value)}
          aria-label="Search academic sessions"
        />

        <FilterSelect
          aria-label="Filter by status"
          value={statusFilter}
          onChange={setStatusFilter}
        >
          <option value="all">All Statuses</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </FilterSelect>

        {canManage && (
          <Button type="button" onClick={openCreateModal}>
            <Plus size={16} />
            Add Session
          </Button>
        )}
      </div>

      <DataTable<AcademicSession>
        columns={columns}
        rows={filteredSessions}
        rowKey={(session) => session.id}
        loading={sessionsQuery.isLoading}
        error={
          sessionsQuery.isError
            ? "Academic sessions could not be loaded."
            : null
        }
        emptyMessage="No academic sessions yet. Add the current session to get started."
      />

      <Modal
        open={modalOpen}
        title={editingSession ? "Edit Academic Session" : "Add Academic Session"}
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
              {saveMutation.isPending ? "Saving..." : "Save session"}
            </Button>
          </>
        }
      >
        {formError && (
          <div className="institution-form-alert" role="alert">
            {formError}
          </div>
        )}

        <FormField label="Code" htmlFor="session-code" required>
          <Input
            id="session-code"
            value={form.code}
            onChange={(event) =>
              setForm((current) => ({ ...current, code: event.target.value }))
            }
            placeholder="2026-27"
          />
        </FormField>

        <FormField label="Name" htmlFor="session-name" required>
          <Input
            id="session-name"
            value={form.name}
            onChange={(event) =>
              setForm((current) => ({ ...current, name: event.target.value }))
            }
            placeholder="2026-27 Academic Session"
          />
        </FormField>

        <div className="institution-form-grid">
          <FormField label="Start Date" htmlFor="session-start" required>
            <Input
              id="session-start"
              type="date"
              value={form.start_date}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  start_date: event.target.value,
                }))
              }
            />
          </FormField>

          <FormField label="End Date" htmlFor="session-end" required>
            <Input
              id="session-end"
              type="date"
              value={form.end_date}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  end_date: event.target.value,
                }))
              }
            />
          </FormField>
        </div>

        <div className="institution-toggle-row" style={{ marginBottom: "var(--space-4)" }}>
          <input
            id="session-active"
            type="checkbox"
            checked={form.is_active}
            onChange={(event) =>
              setForm((current) => ({
                ...current,
                is_active: event.target.checked,
              }))
            }
          />
          <label htmlFor="session-active">Make this the active academic session</label>
        </div>

        <FormField label="Status" htmlFor="session-status">
          <select
            id="session-status"
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

export default AcademicSessionsPage;
