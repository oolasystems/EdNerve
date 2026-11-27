import { useMemo, useState } from "react";

import {
  ShieldCheck,
  Trash2,
  Plus,
  Save,
  Pencil,
} from "lucide-react";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { accessService } from "../api";
import type { Permission } from "../types";
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
} from "../../../shared/ui";
import { getApiErrorMessage } from "../../../shared/lib/api-error";

const EMPTY_LIST: never[] = [];

const ROLE_STATUS_OPTIONS: Array<{ value: "active" | "inactive"; label: string }> = [
  { value: "active", label: "Active" },
  { value: "inactive", label: "Inactive" },
];

const RolesPage = () => {
  const queryClient = useQueryClient();

  const [selectedRoleId, setSelectedRoleId] = useState<number | null>(null);
  const [roleDrafts, setRoleDrafts] = useState<
    Record<number, {
      name: string;
      description: string;
      status: "active" | "inactive";
      permissionIds: Set<number>;
    }>
  >({});
  const [actionError, setActionError] = useState("");
  const [createOpen, setCreateOpen] = useState(false);
  const [newRole, setNewRole] = useState({ code: "", name: "", description: "" });

  const rolesQuery = useQuery({
    queryKey: ["access", "roles"],
    queryFn: () => accessService.getRoles(),
  });

  const permissionsQuery = useQuery({
    queryKey: ["access", "permissions"],
    queryFn: () => accessService.getPermissions(),
    staleTime: 5 * 60 * 1000,
  });

  const roles = useMemo(
    () => rolesQuery.data?.roles ?? [],
    [rolesQuery.data]
  );

  // Use the first available role as the initial selection without effect-driven state.
  const activeRoleId = selectedRoleId ?? roles[0]?.id ?? null;

  const detailQuery = useQuery({
    queryKey: ["access", "role", activeRoleId],
    queryFn: () => accessService.getRole(activeRoleId as number),
    enabled: activeRoleId !== null,
  });

  const selectedRole = detailQuery.data?.role;

  const selectedRoleDraft = selectedRole
    ? roleDrafts[selectedRole.id] ?? {
        name: selectedRole.name,
        description: selectedRole.description ?? "",
        status: selectedRole.status,
        permissionIds: new Set((selectedRole.permissions ?? []).map((p) => p.id)),
      }
    : null;

  const nameDraft = selectedRoleDraft?.name ?? "";
  const descriptionDraft = selectedRoleDraft?.description ?? "";
  const statusDraft = selectedRoleDraft?.status ?? "active";
  const selectedPermissionIds = selectedRoleDraft?.permissionIds ?? new Set<number>();

  const updateSelectedRoleDraft = (
    update: (current: NonNullable<typeof selectedRoleDraft>) => NonNullable<typeof selectedRoleDraft>,
  ) => {
    if (!selectedRole) return;
    setRoleDrafts((current) => ({
      ...current,
      [selectedRole.id]: update(current[selectedRole.id] ?? {
        name: selectedRole.name,
        description: selectedRole.description ?? "",
        status: selectedRole.status,
        permissionIds: new Set((selectedRole.permissions ?? []).map((p) => p.id)),
      }),
    }));
  };

  const clearSelectedRoleDraft = (roleId: number | null = activeRoleId) => {
    if (roleId === null) return;
    setRoleDrafts((current) => {
      const next = { ...current };
      delete next[roleId];
      return next;
    });
  };

  const permissions = permissionsQuery.data?.permissions ?? EMPTY_LIST;

  const permissionGroups = useMemo(() => {
    const map = new Map<string, Permission[]>();
    for (const permission of permissions) {
      const list = map.get(permission.module) ?? [];
      list.push(permission);
      map.set(permission.module, list);
    }
    return [...map.entries()].map(([module, items]) => ({ module, items }));
  }, [permissions]);

  const invalidateRoleQueries = () => {
    void queryClient.invalidateQueries({ queryKey: ["access", "roles"] });
    if (activeRoleId !== null) {
      void queryClient.invalidateQueries({
        queryKey: ["access", "role", activeRoleId],
      });
    }
  };

  const updateMutation = useMutation({
    mutationFn: () =>
      accessService.updateRole(activeRoleId as number, {
        name: nameDraft.trim(),
        description: descriptionDraft.trim() || null,
        status: statusDraft,
      }),
    onSuccess: () => {
      toast.success("Role updated.");
      invalidateRoleQueries();
      clearSelectedRoleDraft();
      setActionError("");
    },
    onError: (error) => {
      setActionError(getApiErrorMessage(error, "Unable to update the role."));
    },
  });

  const syncPermissionsMutation = useMutation({
    mutationFn: () =>
      accessService.syncRolePermissions(
        activeRoleId as number,
        [...selectedPermissionIds]
      ),
    onSuccess: () => {
      toast.success("Permissions updated.");
      invalidateRoleQueries();
      clearSelectedRoleDraft();
      setActionError("");
    },
    onError: (error) => {
      setActionError(getApiErrorMessage(error, "Unable to save permissions."));
    },
  });

  const copyMutation = useMutation({
    mutationFn: (sourceRoleId: number) =>
      accessService.copyPermissionsFromRole(
        activeRoleId as number,
        sourceRoleId
      ),
    onSuccess: () => {
      toast.success("Permissions copied from role.");
      invalidateRoleQueries();
      clearSelectedRoleDraft();
      setActionError("");
    },
    onError: (error) => {
      setActionError(getApiErrorMessage(error, "Unable to copy permissions."));
    },
  });

  const createMutation = useMutation({
    mutationFn: () =>
      accessService.createRole({
        code: newRole.code.trim().toLowerCase(),
        name: newRole.name.trim(),
        description: newRole.description.trim() || null,
      }),
    onSuccess: (data) => {
      toast.success("Role created.");
      setCreateOpen(false);
      setNewRole({ code: "", name: "", description: "" });
      invalidateRoleQueries();
      setSelectedRoleId(data.role.id);
    },
    onError: (error) => {
      setActionError(getApiErrorMessage(error, "Unable to create the role."));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => accessService.deleteRole(id),
    onSuccess: (_data, deletedRoleId) => {
      toast.success("Role deleted.");
      clearSelectedRoleDraft(deletedRoleId);
      setSelectedRoleId(roles.find((role) => role.id !== deletedRoleId)?.id ?? null);
      void queryClient.invalidateQueries({ queryKey: ["access", "roles"] });
    },
    onError: (error) => {
      setActionError(getApiErrorMessage(error, "Unable to delete the role."));
    },
  });

  const togglePermission = (id: number) => {
    updateSelectedRoleDraft((current) => {
      const next = new Set(current.permissionIds);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return { ...current, permissionIds: next };
    });
  };

  const setGroupPermissions = (
    group: { items: Array<{ id: number }> },
    selectAll: boolean
  ) => {
    updateSelectedRoleDraft((current) => {
      const next = new Set(current.permissionIds);
      for (const item of group.items) {
        if (selectAll) next.add(item.id);
        else next.delete(item.id);
      }
      return { ...current, permissionIds: next };
    });
  };

  const detailsDirty =
    selectedRole &&
    (nameDraft.trim() !== selectedRole.name ||
      descriptionDraft.trim() !== (selectedRole.description ?? "") ||
      statusDraft !== selectedRole.status);

  const permissionsDirty =
    selectedRole &&
    (() => {
      const original = new Set((selectedRole.permissions ?? []).map((p) => p.id));
      if (original.size !== selectedPermissionIds.size) return true;
      for (const id of selectedPermissionIds) {
        if (!original.has(id)) return true;
      }
      return false;
    })();

  return (
    <div className="access-layout">
      {/* Role list panel */}
      <section className="access-panel">
        <div className="access-panel-heading">
          <h2>Roles</h2>
          <Button
            size="sm"
            icon={Plus}
            onClick={() => setCreateOpen(true)}
          >
            New Role
          </Button>
        </div>

        {rolesQuery.isError && (
          <Alert tone="error">Unable to load roles.</Alert>
        )}

        <div className="role-list">
          {(rolesQuery.isLoading ? [] : roles).map((role) => (
            <button
              key={role.id}
              type="button"
              className={`role-list-item ${activeRoleId === role.id ? "active" : ""}`}
              onClick={() => setSelectedRoleId(role.id)}
            >
              <span className="role-list-item-main">
                <span className="role-list-item-name">{role.name}</span>
                <span className="role-list-item-code">{role.code}</span>
              </span>
              <span className="role-list-item-meta">
                <Badge tone={role.is_system ? "info" : "neutral"}>
                  {role.is_system ? "System" : "Custom"}
                </Badge>
                <span className="role-list-item-count">
                  {role.permissions_count ?? 0} perms
                </span>
              </span>
            </button>
          ))}
        </div>
      </section>

      {/* Role detail panel */}
      <section className="access-panel">
        {selectedRole ? (
          <>
            <div className="access-panel-heading">
              <div>
                <h2 className="role-detail-title">
                  <ShieldCheck size={18} aria-hidden="true" />
                  {selectedRole.name}
                </h2>
                <p className="dashboard-updated-label access-role-detail-meta">
                  <code className="role-code-inline">{selectedRole.code}</code>
                  {"  "}
                  <StatusBadge status={selectedRole.status} />
                </p>
              </div>

              <div className="access-panel-actions">
                <Button
                  size="sm"
                  variant="secondary"
                  icon={Pencil}
                  disabled={!detailsDirty || updateMutation.isPending}
                  onClick={() => updateMutation.mutate()}
                >
                  Save Details
                </Button>
                {!selectedRole.is_system && (
                  <Button
                    size="sm"
                    variant="ghost"
                    icon={Trash2}
                    loading={deleteMutation.isPending}
                    onClick={() => {
                      if (
                        window.confirm(
                          `Delete the role "${selectedRole.name}"? This cannot be undone.`
                        )
                      ) {
                        deleteMutation.mutate(selectedRole.id);
                      }
                    }}
                  >
                    Delete
                  </Button>
                )}
              </div>
            </div>

            {actionError && <Alert tone="error">{actionError}</Alert>}

            <div className="role-detail-grid">
              <FormField label="Display Name" htmlFor="role-name">
                <Input
                  id="role-name"
                  value={nameDraft}
                  onChange={(event) =>
                    updateSelectedRoleDraft((current) => ({ ...current, name: event.target.value }))
                  }
                />
              </FormField>

              <div className="form-group">
                <label className="form-label" htmlFor="role-status">
                  Status
                </label>
                <select
                  id="role-status"
                  className="form-control"
                  value={statusDraft}
                  onChange={(event) =>
                    updateSelectedRoleDraft((current) => ({
                      ...current,
                      status: event.target.value as "active" | "inactive",
                    }))
                  }
                >
                  {ROLE_STATUS_OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </div>

              <FormField
                label="Description"
                htmlFor="role-description"
                className="institution-form-full"
              >
                <Textarea
                  id="role-description"
                  rows={2}
                  value={descriptionDraft}
                  onChange={(event) =>
                    updateSelectedRoleDraft((current) => ({
                      ...current,
                      description: event.target.value,
                    }))
                  }
                />
              </FormField>
            </div>

            <div className="access-permissions-heading">
              <h3>Permissions</h3>
              <span className="role-list-item-count">
                {selectedPermissionIds.size} selected
              </span>

              <div className="permission-clone-bar">
                <select
                  aria-label="Clone permissions from another role"
                  className="filter-select permission-clone-select"
                  value=""
                  onChange={(event) => {
                    const sourceId = Number(event.target.value);
                    if (sourceId && activeRoleId && window.confirm(
                      "Replace the current permissions with those of the chosen role?"
                    )) {
                      copyMutation.mutate(sourceId);
                    }
                    event.target.value = "";
                  }}
                >
                  <option value="">Clone from…</option>
                  {(rolesQuery.data?.roles ?? [])
                    .filter((r) => r.id !== activeRoleId)
                    .map((r) => (
                      <option key={r.id} value={r.id}>
                        {r.name}
                      </option>
                    ))}
                </select>
              </div>

              <Button
                size="sm"
                icon={Save}
                loading={syncPermissionsMutation.isPending}
                disabled={!permissionsDirty}
                onClick={() => syncPermissionsMutation.mutate()}
              >
                Save Permissions
              </Button>
            </div>

            {permissionsDirty && (
              <Alert tone="warning">You have unsaved permission changes for this role.</Alert>
            )}

            {permissionsQuery.isError && (
              <Alert tone="error">Unable to load the permission catalogue.</Alert>
            )}

            <div className="permission-groups">
              {permissionGroups.map((group) => (
                <section key={group.module} className="permission-group">
                  <div className="permission-group-head">
                    <h4>{group.module === "platform" ? "Platform Administration" : "Institution Administration"}</h4>
                    <div className="permission-group-actions">
                      <button
                        type="button"
                        className="permission-group-toggle"
                        onClick={() => setGroupPermissions(group, true)}
                      >
                        All
                      </button>
                      <button
                        type="button"
                        className="permission-group-toggle"
                        onClick={() => setGroupPermissions(group, false)}
                      >
                        None
                      </button>
                    </div>
                  </div>
                  <div className="permission-list">
                    {group.items.map((permission) => (
                      <label key={permission.id} className="permission-item">
                        <input
                          type="checkbox"
                          checked={selectedPermissionIds.has(permission.id)}
                          onChange={() => togglePermission(permission.id)}
                        />
                        <span className="permission-item-body">
                          <span className="permission-item-name">
                            {permission.name}
                            <code>{permission.code}</code>
                          </span>
                          {permission.description && (
                            <span className="permission-item-desc">
                              {permission.description}
                            </span>
                          )}
                        </span>
                      </label>
                    ))}
                  </div>
                </section>
              ))}
            </div>
          </>
        ) : (
          <p className="dashboard-empty-text">Select a role to manage it.</p>
        )}
      </section>

      {/* Create role modal */}
      <Modal
        open={createOpen}
        title="New Role"
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
              Create Role
            </Button>
          </>
        }
      >
        {actionError && <Alert tone="error">{actionError}</Alert>}

        <form className="auth-form access-create-form">
          <FormField
            label="Code"
            htmlFor="new-role-code"
            required
            hint="Lowercase, numbers, dashes/underscores. e.g. exam_controller"
          >
            <Input
              id="new-role-code"
              placeholder="e.g. exam_controller"
              value={newRole.code}
              onChange={(event) =>
                setNewRole((r) => ({ ...r, code: event.target.value }))
              }
            />
          </FormField>

          <FormField label="Display Name" htmlFor="new-role-name" required>
            <Input
              id="new-role-name"
              placeholder="e.g. Examination Controller"
              value={newRole.name}
              onChange={(event) =>
                setNewRole((r) => ({ ...r, name: event.target.value }))
              }
            />
          </FormField>

          <FormField label="Description" htmlFor="new-role-description">
            <Textarea
              id="new-role-description"
              rows={2}
              placeholder="What this role is responsible for"
              value={newRole.description}
              onChange={(event) =>
                setNewRole((r) => ({ ...r, description: event.target.value }))
              }
            />
          </FormField>
        </form>
      </Modal>
    </div>
  );
};

export default RolesPage;
