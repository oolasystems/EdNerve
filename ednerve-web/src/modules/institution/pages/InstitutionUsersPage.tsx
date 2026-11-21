import { useMemo, useState } from "react";
import {
  Plus,
  Pencil,
  Trash2,
  User as UserIcon,
} from "lucide-react";
import { useQuery, useQueryClient, useMutation } from "@tanstack/react-query";
import { toast } from "sonner";

import { institutionService } from "../api";
import "../institution.css";
import type { InstitutionUser, InstitutionUserRoleAssignment } from "../types";

import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin } from "../../../shared/lib/rbac";
import { getApiErrorMessage } from "../../../shared/lib/api-error";
import { useDebouncedValue } from "../../../shared/lib/hooks/useDebouncedValue";

import {
  DataTable,
  FilterSelect,
  PaginationControls,
} from "../../../shared/crud";
import type { DataTableColumn } from "../../../shared/crud";
import {
  Alert,
  Button,
  FormField,
  Input,
  Modal,
  SearchInput,
  StatusBadge,
} from "../../../shared/ui";
import { Search } from "lucide-react";

const emptyForm = {
  name: "",
  email: "",
  phone: "",
  password: "",
  status: "active" as "active" | "inactive",
};

const InstitutionUsersPage = () => {
  const { user: actor } = useAuth();
  const queryClient = useQueryClient();
  const canManage = isInstitutionAdmin(actor);

  const [page, setPage] = useState(1);
  const [searchInput, setSearchInput] = useState("");
  const [statusFilter, setStatusFilter] = useState<string>("all");
  const debouncedSearch = useDebouncedValue(searchInput.trim(), 300);

  const usersQuery = useQuery({
    queryKey: ["institution", "users", page],
    queryFn: () => institutionService.getUsers(page, 20),
  });

  const allUsers = usersQuery.data?.data ?? [];

  const filteredUsers = useMemo(() => {
    const term = debouncedSearch.toLowerCase();
    return allUsers.filter((user) => {
      const matchesSearch =
        !term ||
        user.name.toLowerCase().includes(term) ||
        user.email.toLowerCase().includes(term) ||
        (user.phone ?? "").includes(term);
      const matchesStatus =
        statusFilter === "all" || user.status === statusFilter;
      return matchesSearch && matchesStatus;
    });
  }, [allUsers, debouncedSearch, statusFilter]);

  // ----- Modal state -----
  const [modalOpen, setModalOpen] = useState(false);
  const [editingUser, setEditingUser] = useState<InstitutionUser | null>(null);
  const [form, setForm] = useState(emptyForm);
  const [formError, setFormError] = useState("");

  const openCreateModal = () => {
    setEditingUser(null);
    setForm(emptyForm);
    setFormError("");
    setModalOpen(true);
  };

  const openEditModal = (user: InstitutionUser) => {
    setEditingUser(user);
    setForm({
      name: user.name,
      email: user.email,
      phone: user.phone ?? "",
      password: "",
      status: user.status,
    });
    setFormError("");
    setModalOpen(true);
  };

  const saveMutation = useMutation({
    mutationFn: async () => {
      if (editingUser) {
        const payload: Record<string, unknown> = {
          name: form.name.trim(),
          email: form.email.trim(),
          phone: form.phone.trim() || null,
          status: form.status,
        };
        if (form.password.trim()) {
          payload.password = form.password.trim();
        }
        return institutionService.updateUser(
          editingUser.id,
          payload
        );
      }
      return institutionService.createUser({
        name: form.name.trim(),
        email: form.email.trim(),
        phone: form.phone.trim() || null,
        password: form.password.trim(),
        status: form.status,
      });
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["institution", "users"],
      });
      toast.success(editingUser ? "User updated." : "User created.");
      setModalOpen(false);
    },
    onError: (error) => {
      setFormError(
        getApiErrorMessage(error, "Unable to save the user account.")
      );
    },
  });

  const handleSave = () => {
    setFormError("");

    if (!form.name.trim()) {
      setFormError("Name is required.");
      return;
    }
    if (!form.email.trim()) {
      setFormError("Email is required.");
      return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) {
      setFormError("Enter a valid email address.");
      return;
    }
    if (!editingUser && form.password.trim().length < 8) {
      setFormError("Password must be at least 8 characters.");
      return;
    }
    if (editingUser && form.password && form.password.trim().length < 8) {
      setFormError("Password must be at least 8 characters.");
      return;
    }

    saveMutation.mutate();
  };

  const deactivateMutation = useMutation({
    mutationFn: (user: InstitutionUser) =>
      institutionService.deactivateUser(user.id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["institution", "users"],
      });
      toast.success("User deactivated.");
    },
    onError: (error) => {
      toast.error(
        getApiErrorMessage(error, "Unable to deactivate this user.")
      );
    },
  });

  const handleDeactivate = (user: InstitutionUser) => {
    if (
      window.confirm(`Deactivate "${user.name}"? They will not be able to sign in.`)
    ) {
      deactivateMutation.mutate(user);
    }
  };

  const renderRoles = (user: InstitutionUser) => {
    const assignments = user.active_role_assignments ?? [];
    if (assignments.length === 0) {
      return <span className="institution-mono">—</span>;
    }

    return (
      <span className="institution-role-chips">
        {assignments.map((assignment: InstitutionUserRoleAssignment) => (
          <span
            key={assignment.id}
            className={`institution-role-chip${
              assignment.is_primary ? " primary" : ""
            }`}
            title={
              assignment.academic_unit
                ? `Unit: ${assignment.academic_unit.name}`
                : "Institution-wide"
            }
          >
            {assignment.role?.name ?? "Role"}
            {assignment.is_primary ? " ★" : ""}
          </span>
        ))}
      </span>
    );
  };

  const columns: DataTableColumn<InstitutionUser>[] = [
    {
      key: "user",
      header: "User",
      render: (user) => (
        <div className="institution-name-cell">
          <div className="institution-avatar">
            <UserIcon size={18} />
          </div>
          <div>
            <strong>{user.name}</strong>
            <span className="institution-mono">{user.email}</span>
          </div>
        </div>
      ),
    },
    {
      key: "phone",
      header: "Phone",
      render: (user) => user.phone ?? "—",
    },
    {
      key: "roles",
      header: "Roles",
      render: renderRoles,
    },
    {
      key: "status",
      header: "Status",
      render: (user) => <StatusBadge status={user.status} />,
    },
    ...(canManage
      ? [
          {
            key: "actions",
            header: "",
            render: (user: InstitutionUser) => (
              <span className="institution-row-actions">
                <button
                  type="button"
                  className="institution-icon-button"
                  title="Edit user"
                  onClick={() => openEditModal(user)}
                >
                  <Pencil size={16} />
                </button>
                <button
                  type="button"
                  className="institution-icon-button danger"
                  title="Deactivate user"
                  disabled={user.status === "inactive" || user.id === actor?.id}
                  onClick={() => handleDeactivate(user)}
                >
                  <Trash2 size={16} />
                </button>
              </span>
            ),
          } as DataTableColumn<InstitutionUser>,
        ]
      : []),
  ];

  const lastPage = usersQuery.data?.last_page ?? 1;
  const total = usersQuery.data?.total ?? 0;
  const from = usersQuery.data?.from ?? null;
  const to = usersQuery.data?.to ?? null;

  return (
    <>
      {usersQuery.isError && (
        <Alert tone="error" className="institution-directory-error">
          Unable to load institution users. Please try again.
          <button
            type="button"
            onClick={() => void usersQuery.refetch()}
          >
            Retry
          </button>
        </Alert>
      )}

      <div className="institution-directory-toolbar">
        <SearchInput
          icon={Search}
          placeholder="Search users by name, email or phone..."
          value={searchInput}
          onChange={(event) => setSearchInput(event.target.value)}
          aria-label="Search institution users"
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
            Add User
          </Button>
        )}
      </div>

      <DataTable
        columns={columns}
        rows={filteredUsers}
        rowKey={(user) => user.id}
        loading={usersQuery.isLoading}
        error={
          usersQuery.isError ? "Institution users could not be loaded." : null
        }
        emptyMessage="No users found for this institution."
      />

      <PaginationControls
        page={page}
        lastPage={lastPage}
        total={total}
        from={from}
        to={to}
        loading={usersQuery.isFetching}
        onPageChange={setPage}
        itemLabel="users"
      />

      <Modal
        open={modalOpen}
        title={editingUser ? "Edit User" : "Add User"}
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
              {saveMutation.isPending ? "Saving..." : "Save user"}
            </Button>
          </>
        }
      >
        {formError && (
          <div className="institution-form-alert" role="alert">
            {formError}
          </div>
        )}

        <FormField label="Full Name" htmlFor="user-name" required>
          <Input
            id="user-name"
            value={form.name}
            onChange={(event) =>
              setForm((current) => ({ ...current, name: event.target.value }))
            }
            placeholder="Aisha Khan"
          />
        </FormField>

        <FormField label="Email" htmlFor="user-email" required>
          <Input
            id="user-email"
            type="email"
            value={form.email}
            onChange={(event) =>
              setForm((current) => ({ ...current, email: event.target.value }))
            }
            placeholder="name@amu.ac.in"
          />
        </FormField>

        <FormField label="Phone" htmlFor="user-phone">
          <Input
            id="user-phone"
            value={form.phone}
            onChange={(event) =>
              setForm((current) => ({ ...current, phone: event.target.value }))
            }
            placeholder="+91 00000 00000"
          />
        </FormField>

        <FormField
          label={editingUser ? "New Password (optional)" : "Password"}
          htmlFor="user-password"
          required={!editingUser}
          hint={
            editingUser
              ? "Leave blank to keep the current password."
              : "At least 8 characters."
          }
        >
          <Input
            id="user-password"
            type="password"
            value={form.password}
            onChange={(event) =>
              setForm((current) => ({ ...current, password: event.target.value }))
            }
            placeholder="••••••••"
          />
        </FormField>

        <FormField label="Status" htmlFor="user-status">
          <select
            id="user-status"
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

export default InstitutionUsersPage;
