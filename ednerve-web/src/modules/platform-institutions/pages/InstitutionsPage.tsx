import { useState } from "react";
import { useNavigate } from "react-router-dom";
import {
  Search,
  RefreshCw,
  Building2,
  Users,
  Network,
  MapPin,
} from "lucide-react";

import { useQuery, keepPreviousData } from "@tanstack/react-query";

import { platformInstitutionsService } from "../api";
import "../../institution/institution.css";
import type {
  PaginatedResponse,
  Institution,
  InstitutionListParams,
  InstitutionStatus,
} from "../types";

import {
  DataTable,
  PaginationControls,
  FilterSelect,
} from "../../../shared/crud";
import type { DataTableColumn } from "../../../shared/crud";
import {
  Alert,
  SearchInput,
  StatusBadge,
} from "../../../shared/ui";

import { useDebouncedValue } from "../../../shared/lib/hooks/useDebouncedValue";
import { formatInstitutionType, joinLocation } from "../../../shared/lib/format";

const PAGE_SIZE = 10;

const InstitutionsPage = () => {
  const navigate = useNavigate();

  const [searchInput, setSearchInput] = useState("");
  const search = useDebouncedValue(searchInput.trim(), 400);

  const [status, setStatus] = useState<InstitutionStatus | "all">("all");
  const [institutionType, setInstitutionType] = useState<string>("all");
  const [currentPage, setCurrentPage] = useState(1);

  const listParams: InstitutionListParams = {
    page: currentPage,
    per_page: PAGE_SIZE,
    sort: "name",
    ...(search ? { search } : {}),
    ...(status !== "all" ? { status } : {}),
    ...(institutionType !== "all" ? { institution_type: institutionType } : {}),
  };

  const { data, isFetching, isLoading, isError, refetch } =
    useQuery<PaginatedResponse<Institution>>({
      queryKey: ["platform", "institutions", listParams],
      queryFn: () => platformInstitutionsService.getInstitutions(listParams),
      placeholderData: keepPreviousData,
    });

  const institutions = data?.data ?? [];

  const total = data?.total ?? 0;
  const from = data?.from ?? null;
  const to = data?.to ?? null;
  const lastPage = data?.last_page ?? 1;

  const loading = isLoading;
  const refreshing = isFetching && !isLoading;

  const handleSearchChange = (value: string) => {
    setSearchInput(value);
    setCurrentPage(1);
  };

  const handleStatusChange = (value: string) => {
    setStatus(value as InstitutionStatus | "all");
    setCurrentPage(1);
  };

  const handleTypeChange = (value: string) => {
    setInstitutionType(value);
    setCurrentPage(1);
  };

  const columns: DataTableColumn<Institution>[] = [
    {
      key: "institution",
      header: "Institution",
      render: (institution) => (
        <div className="institution-name-cell">
          <div className="institution-avatar">
            <Building2 size={20} />
          </div>

          <div>
            <button
              type="button"
              className="institution-name-link"
              onClick={() => navigate(`/platform/institutions/${institution.id}`)}
            >
              {institution.name}
            </button>
            <span>{institution.code}</span>
          </div>
        </div>
      ),
    },
    {
      key: "type",
      header: "Type",
      render: (institution) => (
        <span className="institution-type-label">
          {formatInstitutionType(institution.institution_type)}
        </span>
      ),
    },
    {
      key: "location",
      header: "Location",
      render: (institution) => (
        <div className="institution-location-cell">
          <MapPin size={15} />
          <span>
            {joinLocation([institution.city, institution.state, institution.country]) ||
              "Not specified"}
          </span>
        </div>
      ),
    },
    {
      key: "users",
      header: "Users",
      render: (institution) => (
        <div className="institution-count-cell">
          <Users size={16} />
          <div>
            <strong>{institution.users_count}</strong>
            <span>{institution.active_users_count} active</span>
          </div>
        </div>
      ),
    },
    {
      key: "units",
      header: "Academic Units",
      render: (institution) => (
        <div className="institution-count-cell">
          <Network size={16} />
          <strong>{institution.academic_units_count}</strong>
        </div>
      ),
    },
    {
      key: "status",
      header: "Status",
      render: (institution) => <StatusBadge status={institution.status} />,
    },
  ];

  return (
    <div className="institution-directory">
      <div className="institution-directory-toolbar">
        <SearchInput
          icon={Search}
          placeholder="Search by institution name or code..."
          value={searchInput}
          onChange={(event) => handleSearchChange(event.target.value)}
          aria-label="Search institutions"
        />

        <FilterSelect
          aria-label="Filter by institution type"
          value={institutionType}
          onChange={handleTypeChange}
        >
          <option value="all">All Institution Types</option>
          <option value="university">University</option>
          <option value="college">College</option>
          <option value="college_group">College Group</option>
          <option value="autonomous_college">Autonomous College</option>
          <option value="other">Other</option>
        </FilterSelect>

        <FilterSelect
          aria-label="Filter by institution status"
          value={status}
          onChange={handleStatusChange}
        >
          <option value="all">All Statuses</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </FilterSelect>

        <button
          type="button"
          className="institution-directory-refresh"
          onClick={() => void refetch()}
          disabled={isFetching}
          title="Refresh institution list"
        >
          <RefreshCw
            size={17}
            className={refreshing ? "spinner" : ""}
          />
          Refresh
        </button>
      </div>

      <div className="institution-directory-summary">
        <div>
          <strong>{total}</strong>
          <span>Institutions registered</span>
        </div>

        <div>
          <strong>
            {institutions.reduce((sum, institution) => sum + institution.users_count, 0)}
          </strong>
          <span>Users on current page</span>
        </div>

        <div>
          <strong>
            {institutions.reduce(
              (sum, institution) => sum + institution.academic_units_count,
              0
            )}
          </strong>
          <span>Organization units on current page</span>
        </div>
      </div>

      {isError && (
        <Alert tone="error" className="institution-directory-error">
          Unable to load institutions. Please check your connection and try
          again.
          <button type="button" onClick={() => void refetch()}>
            Retry
          </button>
        </Alert>
      )}

      <DataTable
        columns={columns}
        rows={institutions}
        rowKey={(institution) => institution.id}
        loading={loading}
        error={isError ? "Institution records could not be loaded." : null}
        emptyMessage="No institutions found matching your criteria."
      />

      <PaginationControls
        page={currentPage}
        lastPage={lastPage}
        total={total}
        from={from}
        to={to}
        loading={isFetching}
        onPageChange={setCurrentPage}
        itemLabel="institutions"
      />
    </div>
  );
};

export default InstitutionsPage;
