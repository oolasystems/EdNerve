import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { Search, RefreshCw, Link2 } from "lucide-react";

import { useMutation, useQuery, useQueryClient, keepPreviousData } from "@tanstack/react-query";
import { toast } from "sonner";

import { accessService } from "../api";
import { platformInstitutionsService } from "../../platform-institutions/api";
import type { PlatformAssignmentListParams, RoleStatus } from "../types";
import "../access.css";

import { DataTable, FilterSelect, PaginationControls } from "../../../shared/crud";
import type { DataTableColumn } from "../../../shared/crud";
import { Alert, SearchInput, StatusBadge } from "../../../shared/ui";
import { useDebouncedValue } from "../../../shared/lib/hooks/useDebouncedValue";
import { getApiErrorMessage } from "../../../shared/lib/api-error";
import type { PlatformAssignment } from "../types";

const AssignmentsPage = () => {
  const queryClient = useQueryClient();
  const navigate = useNavigate();

  const [searchInput, setSearchInput] = useState("");
  const search = useDebouncedValue(searchInput.trim(), 400);

  const [institutionId, setInstitutionId] = useState<string>("all");
  const [roleId, setRoleId] = useState<string>("all");
  const [status, setStatus] = useState<RoleStatus | "all">("all");
  const [expiry, setExpiry] = useState<"all" | "expiring" | "expired" | "permanent">("all");
  const [currentPage, setCurrentPage] = useState(1);
  const [actionError, setActionError] = useState("");

  const listParams: PlatformAssignmentListParams = {
    page: currentPage,
    per_page: 15,
    ...(search ? { search } : {}),
    ...(institutionId !== "all" ? { institution_id: Number(institutionId) } : {}),
    ...(roleId !== "all" ? { role_id: Number(roleId) } : {}),
    ...(status !== "all" ? { status } : {}),
    ...(expiry !== "all" ? { expiry } : {}),
  };

  const assignmentsQuery = useQuery({
    queryKey: ["access", "assignments", listParams],
    queryFn: () => accessService.getAssignments(listParams),
    placeholderData: keepPreviousData,
  });

  const rolesQuery = useQuery({
    queryKey: ["access", "roles"],
    queryFn: () => accessService.getRoles(),
    staleTime: 5 * 60 * 1000,
  });

  const institutionsQuery = useQuery({
    queryKey: ["platform", "institution-options"],
    queryFn: () => platformInstitutionsService.getInstitutions({ per_page: 100, sort: "name" }),
    staleTime: 5 * 60 * 1000,
  });

  const toggleStatus = useMutation({
    mutationFn: ({ id, next }: { id: number; next: RoleStatus }) =>
      accessService.updateAssignmentStatus(id, next),
    onSuccess: () => {
      toast.success("Assignment status updated.");
      setActionError("");
      void queryClient.invalidateQueries({ queryKey: ["access", "assignments"] });
    },
    onError: (error) => {
      setActionError(getApiErrorMessage(error, "Unable to update the assignment."));
    },
  });

  const data = assignmentsQuery.data?.data ?? [];
  const total = assignmentsQuery.data?.total ?? 0;
  const lastPage = assignmentsQuery.data?.last_page ?? 1;
  const from = assignmentsQuery.data?.from ?? null;
  const to = assignmentsQuery.data?.to ?? null;

  const resetToFirstPage = () => setCurrentPage(1);

  const columns: DataTableColumn<PlatformAssignment>[] = [
    {
      key: "user",
      header: "User",
      render: (assignment) => (
        <div className="entity-cell">
          <span className="entity-avatar" aria-hidden="true">
            {(assignment.user?.name ?? "?")
              .trim()
              .split(/\s+/)
              .slice(0, 2)
              .map((part) => part[0]?.toUpperCase() ?? "")
              .join("")}
          </span>
          <span className="entity-cell-body">
            <button
              type="button"
              className="entity-name-link"
              onClick={() => navigate(`/platform/users/${assignment.user?.id ?? assignment.user_id}`)}
            >
              {assignment.user?.name ?? "Unknown"}
            </button>
            <span>{assignment.user?.email ?? "—"}</span>
          </span>
        </div>
      ),
    },
    {
      key: "institution",
      header: "Institution",
      render: (assignment) => (
        <span className="institution-type-label">
          {assignment.user?.institution?.name ?? "Platform"}
        </span>
      ),
    },
    {
      key: "role",
      header: "Role",
      render: (assignment) => (
        <span className="role-chip-row">
          <span className="institution-type-label">
            {assignment.role?.code ?? "—"}
          </span>
          {assignment.academic_unit && (
            <span className="institution-type-label">
              · {assignment.academic_unit.code}
            </span>
          )}
          {assignment.is_primary && (
            <span className="badge badge-info" title="Primary role">
              <Link2 size={11} aria-hidden="true" /> Primary
            </span>
          )}
        </span>
      ),
    },
    {
      key: "status",
      header: "Status",
      render: (assignment) => <StatusBadge status={assignment.status} />,
    },
    {
      key: "actions",
      header: "Actions",
      align: "right",
      render: (assignment) => {
        const next: RoleStatus = assignment.status === "active" ? "inactive" : "active";

        return (
          <button
            type="button"
            className={`btn btn-ghost btn-sm ${assignment.status === "inactive" ? "" : "btn-danger-ghost"}`}
            disabled={toggleStatus.isPending}
            onClick={() => {
              if (window.confirm(`${next === "inactive" ? "Deactivate" : "Reactivate"} this assignment?`)) {
                toggleStatus.mutate({ id: assignment.id, next });
              }
            }}
          >
            {assignment.status === "active" ? "Deactivate" : "Activate"}
          </button>
        );
      },
    },
  ];

  return (
    <div className="institution-directory">
      <div className="institution-directory-toolbar">
        <SearchInput
          icon={Search}
          placeholder="Search assignments by user name or email..."
          value={searchInput}
          onChange={(event) => {
            setSearchInput(event.target.value);
            resetToFirstPage();
          }}
          aria-label="Search assignments"
        />

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
          aria-label="Filter by role"
          value={roleId}
          onChange={(value) => {
            setRoleId(value);
            resetToFirstPage();
          }}
        >
          <option value="all">All Roles</option>
          {(rolesQuery.data?.roles ?? []).map((role) => (
            <option key={role.id} value={role.id}>
              {role.name}
            </option>
          ))}
        </FilterSelect>

        <FilterSelect
          aria-label="Filter by expiry"
          value={expiry}
          onChange={(value) => {
            setExpiry(value as typeof expiry);
            resetToFirstPage();
          }}
        >
          <option value="all">All Expiry</option>
          <option value="permanent">No Expiry</option>
          <option value="expiring">Expiring Soon</option>
          <option value="expired">Expired</option>
        </FilterSelect>

        <FilterSelect
          aria-label="Filter by status"
          value={status}
          onChange={(value) => {
            setStatus(value as RoleStatus | "all");
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
          onClick={() => void assignmentsQuery.refetch()}
          disabled={assignmentsQuery.isFetching}
          title="Refresh assignment list"
        >
          <RefreshCw size={17} className={assignmentsQuery.isFetching && !assignmentsQuery.isLoading ? "spinner" : ""} />
          Refresh
        </button>
      </div>

      {actionError && <Alert tone="error">{actionError}</Alert>}

      <DataTable
        columns={columns}
        rows={data}
        rowKey={(assignment) => assignment.id}
        loading={assignmentsQuery.isLoading}
        error={assignmentsQuery.isError ? "Assignment records could not be loaded." : null}
        emptyMessage="No role assignments found matching your criteria."
      />

      <PaginationControls
        page={currentPage}
        lastPage={lastPage}
        total={total}
        from={from}
        to={to}
        loading={assignmentsQuery.isFetching}
        onPageChange={setCurrentPage}
        itemLabel="assignments"
      />
    </div>
  );
};

export default AssignmentsPage;
