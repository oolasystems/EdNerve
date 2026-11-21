import { useState } from "react";
import { Search, RefreshCw, Eye, ScrollText } from "lucide-react";

import { useQuery, keepPreviousData } from "@tanstack/react-query";

import { administrationService } from "../api";
import { platformInstitutionsService } from "../../platform-institutions/api";
import "../administration.css";

import {
  DataTable,
  FilterSelect,
  PaginationControls,
} from "../../../shared/crud";
import type { DataTableColumn } from "../../../shared/crud";
import {
  Alert,
  Input,
  Modal,
  SearchInput,
} from "../../../shared/ui";
import { useDebouncedValue } from "../../../shared/lib/hooks/useDebouncedValue";
import type { AuditLogEntry, AuditLogListParams } from "../types";

const MODULE_OPTIONS = [
  { value: "all", label: "All Modules" },
  { value: "auth", label: "Authentication" },
  { value: "platform", label: "Platform" },
  { value: "institution", label: "Institution" },
];

function formatTimestamp(value: string): string {
  const date = new Date(value);
  return date.toLocaleString("en-IN", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

const AuditLogsPage = () => {
  const [searchInput, setSearchInput] = useState("");
  const search = useDebouncedValue(searchInput.trim(), 400);

  const [module, setModule] = useState("all");
  const [institutionId, setInstitutionId] = useState("all");
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");
  const [currentPage, setCurrentPage] = useState(1);
  const [inspected, setInspected] = useState<AuditLogEntry | null>(null);

  const listParams: AuditLogListParams = {
    page: currentPage,
    per_page: 20,
    ...(search ? { q: search } : {}),
    ...(module !== "all" ? { module } : {}),
    ...(institutionId !== "all" ? { institution_id: Number(institutionId) } : {}),
    ...(dateFrom ? { date_from: dateFrom } : {}),
    ...(dateTo ? { date_to: dateTo } : {}),
  };

  const logsQuery = useQuery({
    queryKey: ["administration", "audit-logs", listParams],
    queryFn: () => administrationService.getAuditLogs(listParams),
    placeholderData: keepPreviousData,
    staleTime: 10_000,
  });

  const institutionsQuery = useQuery({
    queryKey: ["platform", "institution-options"],
    queryFn: () => platformInstitutionsService.getInstitutions({ per_page: 100, sort: "name" }),
    staleTime: 5 * 60 * 1000,
  });

  const data = logsQuery.data?.data ?? [];
  const total = logsQuery.data?.total ?? 0;
  const lastPage = logsQuery.data?.last_page ?? 1;
  const from = logsQuery.data?.from ?? null;
  const to = logsQuery.data?.to ?? null;

  const resetToFirstPage = () => setCurrentPage(1);

  const columns: DataTableColumn<AuditLogEntry>[] = [
    {
      key: "when",
      header: "When",
      render: (entry) => (
        <time className="audit-time" dateTime={entry.created_at}>
          {formatTimestamp(entry.created_at)}
        </time>
      ),
    },
    {
      key: "actor",
      header: "Actor",
      render: (entry) => (
        <div className="audit-actor">
          <strong>{entry.actor?.name ?? "System"}</strong>
          <span>{entry.module}</span>
        </div>
      ),
    },
    {
      key: "event",
      header: "Event",
      render: (entry) => <code className="audit-event">{entry.event}</code>,
    },
    {
      key: "description",
      header: "Description",
      render: (entry) =>
        entry.description ? (
          <span className="audit-description">{entry.description}</span>
        ) : (
          <span className="institution-type-label">—</span>
        ),
    },
    {
      key: "institution",
      header: "Institution",
      render: (entry) => (
        <span className="institution-type-label">
          {entry.institution?.name ?? (entry.institution_id ? `#${entry.institution_id}` : "Platform")}
        </span>
      ),
    },
    {
      key: "actions",
      header: "",
      align: "right",
      render: (entry) => (
        <button
          type="button"
          className="btn btn-ghost btn-sm"
          onClick={() => setInspected(entry)}
          aria-label="Inspect audit entry"
        >
          <Eye size={16} />
          Inspect
        </button>
      ),
    },
  ];

  return (
    <div className="institution-directory">
      <div className="institution-directory-toolbar" style={{ alignItems: "end" }}>
        <SearchInput
          icon={Search}
          placeholder="Search description, IP, event, module..."
          value={searchInput}
          onChange={(event) => {
            setSearchInput(event.target.value);
            resetToFirstPage();
          }}
          aria-label="Search audit logs"
        />

        <FilterSelect
          aria-label="Filter by module"
          value={module}
          onChange={(value) => {
            setModule(value);
            resetToFirstPage();
          }}
        >
          {MODULE_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </FilterSelect>

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

        <label className="form-group audit-date-field">
          <span className="form-label">From</span>
          <Input
            type="date"
            value={dateFrom}
            onChange={(event) => {
              setDateFrom(event.target.value);
              resetToFirstPage();
            }}
          />
        </label>

        <label className="form-group audit-date-field">
          <span className="form-label">To</span>
          <Input
            type="date"
            value={dateTo}
            onChange={(event) => {
              setDateTo(event.target.value);
              resetToFirstPage();
            }}
          />
        </label>

        <button
          type="button"
          className="institution-directory-refresh"
          onClick={() => void logsQuery.refetch()}
          disabled={logsQuery.isFetching}
          title="Refresh audit logs"
        >
          <RefreshCw size={17} className={logsQuery.isFetching && !logsQuery.isLoading ? "spinner" : ""} />
          Refresh
        </button>
      </div>

      {logsQuery.isError && <Alert tone="error">Unable to load the audit trail.</Alert>}

      <DataTable
        columns={columns}
        rows={data}
        rowKey={(entry) => entry.id}
        loading={logsQuery.isLoading}
        error={logsQuery.isError ? "Audit records could not be loaded." : null}
        emptyMessage="No audit events match your criteria."
      />

      <PaginationControls
        page={currentPage}
        lastPage={lastPage}
        total={total}
        from={from}
        to={to}
        loading={logsQuery.isFetching}
        onPageChange={setCurrentPage}
        itemLabel="events"
      />

      <Modal
        open={inspected !== null}
        title="Audit Entry"
        onClose={() => setInspected(null)}
        maxWidth={640}
        footer={
          <button
            type="button"
            className="btn btn-secondary"
            onClick={() => setInspected(null)}
          >
            Close
          </button>
        }
      >
        {inspected && (
          <div className="audit-detail">
            <p className="dashboard-updated-label" style={{ marginTop: 0 }}>
              <ScrollText size={13} style={{ verticalAlign: "-2px", marginRight: 6 }} />
              {formatTimestamp(inspected.created_at)} · {inspected.module} ·{" "}
              <code className="audit-event">{inspected.event}</code>
            </p>
            <pre className="audit-json">{JSON.stringify(inspected, null, 2)}</pre>
          </div>
        )}
      </Modal>
    </div>
  );
};

export default AuditLogsPage;
