import { useState } from "react";
import { useNavigate } from "react-router-dom";

import {
  Search,
  RefreshCw,
} from "lucide-react";

import {
  useQuery,
  keepPreviousData,
  useMutation,
  useQueryClient,
} from "@tanstack/react-query";
import { toast } from "sonner";

import { platformUserService } from "../api";
import { platformInstitutionsService } from "../../platform-institutions/api";
import type { PlatformUserListParams, UserStatus } from "../types";

import { DataTable, FilterSelect, PaginationControls } from "../../../shared/crud";
import type { DataTableColumn } from "../../../shared/crud";
import { Alert, SearchInput, StatusBadge } from "../../../shared/ui";
import { useDebouncedValue } from "../../../shared/lib/hooks/useDebouncedValue";
import { getApiErrorMessage } from "../../../shared/lib/api-error";

export interface UsersExplorerProps {
  /** Limit the listing to one account type, or allow switching. */
  accountType?: "platform" | "institution" | "all";
  /** When set, hide the account-type filter (Platform Users page). */
  lockAccountType?: boolean;
}

function avatarText(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return "?";
  return (parts[0][0] + (parts.length > 1 ? parts[parts.length - 1][0] : "")).toUpperCase();
}

export default function UsersExplorer({
  accountType = "all",
  lockAccountType = false,
}: UsersExplorerProps) {
  const queryClient = useQueryClient();
  const navigate = useNavigate();

  const [searchInput, setSearchInput] = useState("");
  const search = useDebouncedValue(searchInput.trim(), 400);

  const [type, setType] = useState<"all" | "platform" | "institution">(accountType);
  const [institutionId, setInstitutionId] = useState<string>("all");
  const [status, setStatus] = useState<UserStatus | "all">("all");
  const [currentPage, setCurrentPage] = useState(1);
  const [actionError, setActionError] = useState("");

  const listParams: PlatformUserListParams = {
    page: currentPage,
    per_page: 15,
    sort_by: "name",
    ...(search ? { search } : {}),
    ...(type !== "all" ? { account_type: type } : {}),
    ...(institutionId !== "all" ? { institution_id: Number(institutionId) } : {}),
    ...(status !== "all" ? { status } : {}),
  };

  const usersQuery = useQuery({
    queryKey: ["platform", "users", listParams],
    queryFn: () => platformUserService.getUsers(listParams),
    placeholderData: keepPreviousData,
  });

  const institutionsQuery = useQuery({
    queryKey: ["platform", "institution-options"],
    queryFn: () => platformInstitutionsService.getInstitutions({ per_page: 100, sort: "name" }),
    staleTime: 5 * 60 * 1000,
  });

  const toggleStatus = useMutation({
    mutationFn: ({ id, next }: { id: number; next: UserStatus }) =>
      platformUserService.updateUserStatus(id, next),
    onSuccess: (data) => {
      toast.success(data.message);
      void queryClient.invalidateQueries({ queryKey: ["platform", "users"] });
      setActionError("");
    },
    onError: (error) => {
      setActionError(
        getApiErrorMessage(error, "Unable to update the account status.")
      );
    },
  });

  const data = usersQuery.data?.data ?? [];
  const total = usersQuery.data?.total ?? 0;
  const lastPage = usersQuery.data?.last_page ?? 1;
  const from = usersQuery.data?.from ?? null;
  const to = usersQuery.data?.to ?? null;

  const isFetching = usersQuery.isFetching;

  const columns: DataTableColumn<(typeof data)[number]>[] = [
    {
      key: "user",
      header: "User",
      render: (user) => (
        <div className="entity-cell">
          <span className="entity-avatar" aria-hidden="true">
            {avatarText(user.name)}
          </span>
          <span className="entity-cell-body">
            <button
              type="button"
              className="entity-name-link"
              onClick={() => navigate(`/platform/users/${user.id}`)}
            >
              {user.name}
            </button>
            <span>{user.email}</span>
          </span>
        </div>
      ),
    },
    {
      key: "account",
      header: "Scope",
      render: (user) =>
        user.account_type === "platform" ? (
          <span className="badge badge-info">Platform Admin</span>
        ) : (
          <span className="institution-type-label">
            {user.institution?.name ?? "Institution"}
          </span>
        ),
    },
    {
      key: "roles",
      header: "Roles",
      render: (user) => {
        const roles = user.active_role_assignments ?? [];
        if (roles.length === 0) return <span className="institution-type-label">—</span>;
        return (
          <div className="role-chip-row" title={roles.map((r) => r.role.name).join(", ")}>
            {roles.slice(0, 2).map((assignment) => (
              <span key={assignment.id} className="institution-type-label">
                {assignment.role.code}
              </span>
            ))}
            {roles.length > 2 && (
              <span className="institution-type-label">+{roles.length - 2} more</span>
            )}
          </div>
        );
      },
    },
    {
      key: "status",
      header: "Status",
      render: (user) => <StatusBadge status={user.status} />,
    },
    {
      key: "actions",
      header: "Actions",
      align: "right",
      render: (user) => {
        const next: UserStatus = user.status === "active" ? "inactive" : "active";
        const confirmMessage =
          next === "inactive"
            ? `Deactivate ${user.name}? Their API tokens will be revoked immediately.`
            : `Reactivate ${user.name}?`;

        return (
          <button
            type="button"
            className={`btn btn-ghost btn-sm ${user.status === "inactive" ? "" : "btn-danger-ghost"}`}
            disabled={toggleStatus.isPending}
            onClick={() => {
              if (window.confirm(confirmMessage)) {
                toggleStatus.mutate({ id: user.id, next });
              }
            }}
          >
            {user.status === "active" ? "Deactivate" : "Activate"}
          </button>
        );
      },
    },
  ];

  const resetToFirstPage = () => setCurrentPage(1);

  return (
    <div className="institution-directory">
      <div className="institution-directory-toolbar">
        <SearchInput
          icon={Search}
          placeholder="Search users by name or email..."
          value={searchInput}
          onChange={(event) => {
            setSearchInput(event.target.value);
            resetToFirstPage();
          }}
          aria-label="Search users"
        />

        {!lockAccountType && (
          <FilterSelect
            aria-label="Filter by account type"
            value={type}
            onChange={(value) => {
              setType(value as typeof type);
              resetToFirstPage();
            }}
          >
            <option value="all">All Accounts</option>
            <option value="institution">Institution Users</option>
            <option value="platform">Platform Admins</option>
          </FilterSelect>
        )}

        <FilterSelect
          aria-label="Filter by institution"
          value={institutionId}
          onChange={(value) => {
            setInstitutionId(value);
            resetToFirstPage();
          }}
        >
          <option value="all">All Institutions</option>
          {(institutionsQuery.data?.data ?? []).map((institution) => (
            <option key={institution.id} value={institution.id}>
              {institution.name}
            </option>
          ))}
        </FilterSelect>

        <FilterSelect
          aria-label="Filter by status"
          value={status}
          onChange={(value) => {
            setStatus(value as UserStatus | "all");
            resetToFirstPage();
          }}
        >
          <option value="all">All Statuses</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </FilterSelect>

        <button
          type="button"
          className="institution-directory-refresh"
          onClick={() => void usersQuery.refetch()}
          disabled={usersQuery.isFetching}
          title="Refresh user list"
        >
          <RefreshCw size={17} className={usersQuery.isFetching && !usersQuery.isLoading ? "spinner" : ""} />
          Refresh
        </button>
      </div>

      {actionError && <Alert tone="error">{actionError}</Alert>}
      {usersQuery.isError && (
        <Alert tone="error">
          Unable to load users. Please try again.{" "}
          <button type="button" onClick={() => void usersQuery.refetch()}>
            Retry
          </button>
        </Alert>
      )}

      <DataTable
        columns={columns}
        rows={data}
        rowKey={(user) => user.id}
        loading={usersQuery.isLoading}
        error={usersQuery.isError ? "User records could not be loaded." : null}
        emptyMessage="No users found matching your criteria."
      />

      <PaginationControls
        page={currentPage}
        lastPage={lastPage}
        total={total}
        from={from}
        to={to}
        loading={isFetching}
        onPageChange={setCurrentPage}
        itemLabel="users"
      />
    </div>
  );
}
