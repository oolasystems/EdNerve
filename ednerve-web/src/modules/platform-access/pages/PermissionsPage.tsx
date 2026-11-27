import { useState } from "react";
import {
  KeyRound,
  Plus,
  RefreshCw,
  ToggleLeft,
  ToggleRight,
} from "lucide-react";

import {
  useMutation,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import { toast } from "sonner";

import { accessService } from "../api";
import type { PermissionListParams, RoleStatus } from "../types";
import "../access.css";

import {
  Alert,
  Badge,
  Button,
  Modal,
  FormField,
  Input,
  Textarea,
  StatusBadge,
  SearchInput,
} from "../../../shared/ui";
import {
  DataTable,
  FilterSelect,
} from "../../../shared/crud";
import type { DataTableColumn } from "../../../shared/crud";
import { useDebouncedValue } from "../../../shared/lib/hooks/useDebouncedValue";
import { getApiErrorMessage } from "../../../shared/lib/api-error";

const moduleOptionsFrom = (items: Array<{ module: string }>) =>
  [...new Set(items.map((item) => item.module))].sort();

const PermissionsPage = () => {
  const queryClient = useQueryClient();

  const [searchInput, setSearchInput] = useState("");
  const search = useDebouncedValue(searchInput.trim(), 350);

  const [module, setModule] = useState("all");
  const [status, setStatus] = useState<RoleStatus | "all">("all");
  const [system, setSystem] = useState<"system" | "custom" | "all">("all");
  const [createOpen, setCreateOpen] = useState(false);
  const [actionError, setActionError] = useState("");

  const [newPermission, setNewPermission] = useState({
    code: "",
    name: "",
    module: "platform",
    description: "",
  });

  const listParams: PermissionListParams = {
    ...(search ? { search } : {}),
    ...(module !== "all" ? { module } : {}),
    ...(status !== "all" ? { status } : {}),
    ...(system !== "all" ? { system } : {}),
  };

  // Default to the full (incl. inactive) catalogue for this admin screen,
  // keeping roles UI compatible through the same parameter.
  const params: PermissionListParams = { ...listParams, status: listParams.status ?? "all" };

  const permissionsQuery = useQuery({
    queryKey: ["access", "permissions", params],
    queryFn: () => accessService.getPermissions(params),
  });

  const allQuery = useQuery({
    queryKey: ["access", "permissions", "all-modules"],
    queryFn: () => accessService.getPermissions({ status: "all" }),
    staleTime: 5 * 60 * 1000,
  });

  const items = permissionsQuery.data?.permissions ?? [];
  const moduleOptions = moduleOptionsFrom(allQuery.data?.permissions ?? []);

  const invalidate = () =>
    queryClient.invalidateQueries({ queryKey: ["access", "permissions"] });

  const createMutation = useMutation({
    mutationFn: () =>
      accessService.createPermission({
        code: newPermission.code.trim().toLowerCase(),
        name: newPermission.name.trim(),
        module: newPermission.module.trim().toLowerCase(),
        description: newPermission.description.trim() || null,
      }),
    onSuccess: () => {
      toast.success("Permission created.");
      setCreateOpen(false);
      setNewPermission({ code: "", name: "", module: "platform", description: "" });
      setActionError("");
      void invalidate();
    },
    onError: (error) => {
      setActionError(getApiErrorMessage(error, "Unable to create the permission."));
    },
  });

  const toggleMutation = useMutation({
    mutationFn: (permission: { id: number; status: RoleStatus }) =>
      accessService.updatePermission(permission.id, {
        status: permission.status === "active" ? "inactive" : "active",
      }),
    onSuccess: () => {
      toast.success("Permission status updated.");
      setActionError("");
      void invalidate();
    },
    onError: (error) => {
      setActionError(getApiErrorMessage(error, "Unable to update the permission."));
    },
  });

  const columns: DataTableColumn<(typeof items)[number]>[] = [
    {
      key: "code",
      header: "Code",
      render: (permission) => (
        <div className="permission-cell-main">
          <code className="audit-event">{permission.code}</code>
          <span className="permission-cell-name">{permission.name}</span>
        </div>
      ),
    },
    {
      key: "module",
      header: "Module",
      render: (permission) => (
        <span className="institution-type-label">{permission.module}</span>
      ),
    },
    {
      key: "status",
      header: "Status",
      render: (permission) => <StatusBadge status={permission.status} />,
    },
    {
      key: "system",
      header: "Type",
      render: (permission) => (
        <Badge tone={permission.is_system ? "info" : "neutral"}>
          {permission.is_system ? "System" : "Custom"}
        </Badge>
      ),
    },
    {
      key: "actions",
      header: "Actions",
      align: "right",
      render: (permission) =>
        permission.is_system ? (
          <span className="role-list-item-count">Locked</span>
        ) : (
          <button
            type="button"
            className="btn btn-ghost btn-sm"
            title={permission.status === "active" ? "Deactivate" : "Activate"}
            onClick={() => toggleMutation.mutate(permission)}
            disabled={toggleMutation.isPending}
          >
            {permission.status === "active" ? (
              <ToggleRight size={18} aria-label="Deactivate permission" />
            ) : (
              <ToggleLeft size={18} aria-label="Activate permission" />
            )}
          </button>
        ),
    },
  ];

  const isFetching = permissionsQuery.isFetching;

  return (
    <div className="institution-directory">
      <div className="institution-directory-toolbar">
        <SearchInput
          icon={KeyRound}
          placeholder="Search by code, name or description..."
          value={searchInput}
          onChange={(event) => setSearchInput(event.target.value)}
          aria-label="Search permissions"
        />

        <FilterSelect
          aria-label="Filter by module"
          value={module}
          onChange={setModule}
        >
          <option value="all">All Modules</option>
          {moduleOptions.map((option) => (
            <option key={option} value={option}>
              {option === "platform" ? "Platform" : option === "institution" ? "Institution" : option}
            </option>
          ))}
        </FilterSelect>

        <FilterSelect
          aria-label="Filter by status"
          value={status}
          onChange={(value) => setStatus(value as RoleStatus | "all")}
        >
          <option value="all">All Statuses</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </FilterSelect>

        <FilterSelect
          aria-label="Filter by type"
          value={system}
          onChange={(value) => setSystem(value as "system" | "custom" | "all")}
        >
          <option value="all">All Types</option>
          <option value="system">System</option>
          <option value="custom">Custom</option>
        </FilterSelect>

        <button
          type="button"
          className="institution-directory-refresh"
          onClick={() => void permissionsQuery.refetch()}
          disabled={permissionsQuery.isFetching}
          title="Refresh permissions"
        >
          <RefreshCw size={17} className={isFetching ? "spinner" : ""} />
          Refresh
        </button>

        <Button size="sm" icon={Plus} onClick={() => setCreateOpen(true)}>
          New Permission
        </Button>
      </div>

      {actionError && <Alert tone="error">{actionError}</Alert>}
      {permissionsQuery.isError && (
        <Alert tone="error">
          Unable to load the permission catalogue.{" "}
          <button type="button" onClick={() => void permissionsQuery.refetch()}>
            Retry
          </button>
        </Alert>
      )}

      <DataTable
        columns={columns}
        rows={items}
        rowKey={(item) => item.id}
        loading={permissionsQuery.isLoading}
        error={permissionsQuery.isError ? "Catalogue could not be loaded." : null}
        emptyMessage="No permissions match your filters."
      />

      <div className="pagination-bar access-permission-count">
        <span className="role-list-item-count">{items.length} permission(s)</span>
      </div>

      {/* Create custom permission modal */}
      <Modal
        open={createOpen}
        title="New Permission"
        onClose={() => setCreateOpen(false)}
        footer={
          <>
            <Button variant="secondary" onClick={() => setCreateOpen(false)}>
              Cancel
            </Button>
            <Button
              variant="primary"
              loading={createMutation.isPending}
              onClick={() => {
                setActionError("");
                createMutation.mutate();
              }}
            >
              Create Permission
            </Button>
          </>
        }
      >
        {actionError && <Alert tone="error">{actionError}</Alert>}

        <form className="auth-form access-create-form">
          <FormField label="Code" htmlFor="permission-code" required hint="e.g. library.manage — lowercase, dots/underscores only">
            <Input
              id="permission-code"
              placeholder="e.g. library.manage"
              value={newPermission.code}
              onChange={(event) =>
                setNewPermission((p) => ({ ...p, code: event.target.value }))
              }
            />
          </FormField>

          <FormField label="Display Name" htmlFor="permission-name" required>
            <Input
              id="permission-name"
              placeholder="e.g. Manage library services"
              value={newPermission.name}
              onChange={(event) =>
                setNewPermission((p) => ({ ...p, name: event.target.value }))
              }
            />
          </FormField>

          <FormField label="Module" htmlFor="permission-module" required>
            <Input
              id="permission-module"
              placeholder="e.g. library"
              value={newPermission.module}
              onChange={(event) =>
                setNewPermission((p) => ({ ...p, module: event.target.value }))
              }
            />
          </FormField>

          <FormField label="Description" htmlFor="permission-description">
            <Textarea
              id="permission-description"
              rows={2}
              placeholder="What this permission allows"
              value={newPermission.description}
              onChange={(event) =>
                setNewPermission((p) => ({ ...p, description: event.target.value }))
              }
            />
          </FormField>
        </form>
      </Modal>
    </div>
  );
};

export default PermissionsPage;
