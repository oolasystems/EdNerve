import { useMemo, useState } from "react";
import {
  Plus,
  Trash2,
  Search,
} from "lucide-react";
import { useQuery, useQueryClient, useMutation } from "@tanstack/react-query";
import { toast } from "sonner";

import { institutionService } from "../api";
import "../institution.css";
import type { RoleAssignment } from "../types";

import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin } from "../../../shared/lib/rbac";
import { useDebouncedValue } from "../../../shared/lib/hooks/useDebouncedValue";
import { getApiErrorMessage } from "../../../shared/lib/api-error";

import {
  DataTable,
  PaginationControls,
  FilterSelect,
} from "../../../shared/crud";
import type { DataTableColumn } from "../../../shared/crud";
import {
  Alert,
  Button,
  FormField,
  Modal,
  SearchInput,
  StatusBadge,
} from "../../../shared/ui";

const EMPTY_LIST: never[] = [];

const emptyForm = {
  user_id: "" as string | number,
  role_id: "" as string | number,
  academic_unit_id: "" as string | number,
  is_primary: false,
  status: "active" as "active" | "inactive",
  starts_at: "",
  ends_at: "",
};

const InstitutionAssignmentsPage = () => {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const canManage = isInstitutionAdmin(user);

  const [page, setPage] = useState(1);
  const [searchInput, setSearchInput] = useState("");
  const [statusFilter, setStatusFilter] = useState<string>("all");
  const debouncedSearch = useDebouncedValue(searchInput.trim(), 300);

  const assignmentsQuery = useQuery({
    queryKey: ["institution", "role-assignments", page],
    queryFn: () => institutionService.getRoleAssignments(page, 50),
  });

  const usersQuery = useQuery({
    queryKey: ["institution", "users", "all"],
    queryFn: () => institutionService.getAllUsers(),
  });

  const unitsQuery = useQuery({
    queryKey: ["institution", "academic-units", "all"],
    queryFn: () => institutionService.getAllAcademicUnits(),
  });

  const rolesQuery = useQuery({
    queryKey: ["institution", "roles"],
    queryFn: () => institutionService.getRoles(),
  });

  const allAssignments = assignmentsQuery.data?.data ?? EMPTY_LIST;

  const filteredAssignments = useMemo(() => {
    const term = debouncedSearch.toLowerCase();
    return allAssignments.filter((assignment) => {
      const haystack = `${assignment.user?.name ?? ""} ${assignment.user?.email ?? ""} ${assignment.role?.name ?? ""} ${assignment.role?.code ?? ""} ${assignment.academic_unit?.name ?? ""}`.toLowerCase();
      const matchesSearch = !term || haystack.includes(term);
      const matchesStatus =
        statusFilter === "all" || assignment.status === statusFilter;
      return matchesSearch && matchesStatus;
    });
  }, [allAssignments, debouncedSearch, statusFilter]);

  // ----- Modal state -----
  const [modalOpen, setModalOpen] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [formError, setFormError] = useState("");

  const openAssignModal = () => {
    setForm(emptyForm);
    setFormError("");
    setModalOpen(true);
  };

  const assignMutation = useMutation({
    mutationFn: () =>
      institutionService.createRoleAssignment({
        user_id: Number(form.user_id),
        role_id: Number(form.role_id),
        academic_unit_id:
          form.academic_unit_id === "" ? null : Number(form.academic_unit_id),
        is_primary: form.is_primary,
        status: form.status,
        starts_at: form.starts_at || null,
        ends_at: form.ends_at || null,
      }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["institution", "role-assignments"],
      });
      await queryClient.invalidateQueries({
        queryKey: ["institution", "users"],
      });
      toast.success("Role assigned.");
      setModalOpen(false);
    },
    onError: (error) => {
      setFormError(
        getApiErrorMessage(error, "Unable to assign this role.")
      );
    },
  });

  const handleAssign = () => {
    setFormError("");

    if (form.user_id === "") {
      setFormError("Select a user.");
      return;
    }
    if (form.role_id === "") {
      setFormError("Select a role.");
      return;
    }
    if (form.starts_at && form.ends_at && form.ends_at < form.starts_at) {
      setFormError("End date cannot be earlier than start date.");
      return;
    }

    assignMutation.mutate();
  };

  const revokeMutation = useMutation({
    mutationFn: (assignment: RoleAssignment) =>
      institutionService.revokeRoleAssignment(assignment.id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["institution", "role-assignments"],
      });
      await queryClient.invalidateQueries({
        queryKey: ["institution", "users"],
      });
      toast.success("Role assignment revoked.");
    },
    onError: (error) => {
      toast.error(
        getApiErrorMessage(error, "Unable to revoke this assignment.")
      );
    },
  });

  const handleRevoke = (assignment: RoleAssignment) => {
    if (
      window.confirm(
        `Revoke "${assignment.role?.name ?? "role"}" from ${assignment.user?.name ?? "user"}?`
      )
    ) {
      revokeMutation.mutate(assignment);
    }
  };

  const formatPeriod = (assignment: RoleAssignment) => {
    const starts = assignment.starts_at ?? "now";
    const ends = assignment.ends_at ?? "no end";
    return `${starts} → ${ends}`;
  };

  const columns: DataTableColumn<RoleAssignment>[] = [
    {
      key: "user",
      header: "User",
      render: (assignment) => (
        <div>
          <strong>{assignment.user?.name ?? `User #${assignment.user_id}`}</strong>
          <br />
          <span className="institution-mono">{assignment.user?.email ?? "—"}</span>
        </div>
      ),
    },
    {
      key: "role",
      header: "Role",
      render: (assignment) => (
        <span className="institution-role-chips">
          <span
            className={`institution-role-chip${assignment.is_primary ? " primary" : ""}`}
          >
            {assignment.role?.name ?? "Role"}
            {assignment.is_primary ? " ★" : ""}
          </span>
        </span>
      ),
    },
    {
      key: "unit",
      header: "Unit",
      render: (assignment) => assignment.academic_unit?.name ?? "Institution-wide",
    },
    {
      key: "period",
      header: "Period",
      render: (assignment) => (
        <span className="institution-mono">{formatPeriod(assignment)}</span>
      ),
    },
    {
      key: "status",
      header: "Status",
      render: (assignment) => <StatusBadge status={assignment.status} />,
    },
    ...(canManage
      ? [
          {
            key: "actions",
            header: "",
            render: (assignment: RoleAssignment) => (
              <button
                type="button"
                className="institution-icon-button danger"
                title="Revoke assignment"
                disabled={assignment.status === "inactive"}
                onClick={() => handleRevoke(assignment)}
              >
                <Trash2 size={16} />
              </button>
            ),
          } as DataTableColumn<RoleAssignment>,
        ]
      : []),
  ];

  const lastPage = assignmentsQuery.data?.last_page ?? 1;
  const total = assignmentsQuery.data?.total ?? 0;
  const from = assignmentsQuery.data?.from ?? null;
  const to = assignmentsQuery.data?.to ?? null;

  const users = usersQuery.data ?? EMPTY_LIST;
  const units = unitsQuery.data ?? EMPTY_LIST;
  const roles = rolesQuery.data?.roles ?? EMPTY_LIST;

  return (
    <>
      {assignmentsQuery.isError && (
        <Alert tone="error" className="institution-directory-error">
          Unable to load role assignments. Please try again.
          <button
            type="button"
            onClick={() => void assignmentsQuery.refetch()}
          >
            Retry
          </button>
        </Alert>
      )}

      <div className="institution-directory-toolbar">
        <SearchInput
          icon={Search}
          placeholder="Search assignments..."
          value={searchInput}
          onChange={(event) => setSearchInput(event.target.value)}
          aria-label="Search role assignments"
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
          <Button type="button" onClick={openAssignModal}>
            <Plus size={16} />
            Assign Role
          </Button>
        )}
      </div>

      <DataTable<RoleAssignment>
        columns={columns}
        rows={filteredAssignments}
        rowKey={(assignment) => assignment.id}
        loading={assignmentsQuery.isLoading}
        error={
          assignmentsQuery.isError
            ? "Role assignments could not be loaded."
            : null
        }
        emptyMessage="No role assignments found for this institution."
      />

      <PaginationControls
        page={page}
        lastPage={lastPage}
        total={total}
        from={from}
        to={to}
        loading={assignmentsQuery.isFetching}
        onPageChange={setPage}
        itemLabel="assignments"
      />

      <Modal
        open={modalOpen}
        title="Assign Role"
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
              onClick={handleAssign}
              disabled={assignMutation.isPending}
            >
              {assignMutation.isPending ? "Assigning..." : "Assign role"}
            </Button>
          </>
        }
      >
        {formError && (
          <div className="institution-form-alert" role="alert">
            {formError}
          </div>
        )}

        <FormField label="User" htmlFor="assign-user" required>
          <select
            id="assign-user"
            className="filter-select"
            value={form.user_id}
            onChange={(event) =>
              setForm((current) => ({ ...current, user_id: event.target.value }))
            }
          >
            <option value="">Select user...</option>
            {users.map((user) => (
              <option key={user.id} value={user.id}>
                {user.name} ({user.email})
              </option>
            ))}
          </select>
        </FormField>

        <FormField label="Role" htmlFor="assign-role" required>
          <select
            id="assign-role"
            className="filter-select"
            value={form.role_id}
            onChange={(event) =>
              setForm((current) => ({ ...current, role_id: event.target.value }))
            }
          >
            <option value="">Select role...</option>
            {roles.map((role) => (
              <option key={role.id} value={role.id}>
                {role.name} ({role.code})
              </option>
            ))}
          </select>
        </FormField>

        <FormField label="Academic Unit" htmlFor="assign-unit">
          <select
            id="assign-unit"
            className="filter-select"
            value={form.academic_unit_id}
            onChange={(event) =>
              setForm((current) => ({
                ...current,
                academic_unit_id: event.target.value,
              }))
            }
          >
            <option value="">Institution-wide (no unit scope)</option>
            {units.map((unit) => (
              <option key={unit.id} value={unit.id}>
                {unit.name} ({unit.code})
              </option>
            ))}
          </select>
        </FormField>

        <div className="institution-form-grid">
          <FormField label="Starts" htmlFor="assign-starts">
            <input
              id="assign-starts"
              type="date"
              className="filter-select"
              value={form.starts_at}
              onChange={(event) =>
                setForm((current) => ({ ...current, starts_at: event.target.value }))
              }
            />
          </FormField>

          <FormField label="Ends" htmlFor="assign-ends">
            <input
              id="assign-ends"
              type="date"
              className="filter-select"
              value={form.ends_at}
              onChange={(event) =>
                setForm((current) => ({ ...current, ends_at: event.target.value }))
              }
            />
          </FormField>
        </div>

        <div className="institution-toggle-row" style={{ marginBottom: "var(--space-4)" }}>
          <input
            id="assign-primary"
            type="checkbox"
            checked={form.is_primary}
            onChange={(event) =>
              setForm((current) => ({
                ...current,
                is_primary: event.target.checked,
              }))
            }
          />
          <label htmlFor="assign-primary">Primary role for this user</label>
        </div>

        <FormField label="Status" htmlFor="assign-status">
          <select
            id="assign-status"
            className="filter-select"
            value={form.status}
            onChange={(event) =>
              setForm((current) => ({
                ...current,
                status: event.target.value as "active" | "inactive",
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

export default InstitutionAssignmentsPage;
