import { useState } from "react";
import {
  GraduationCap,
  Mail,
  PhoneCall,
  Pencil,
  X,
} from "lucide-react";
import { useQuery, useQueryClient, useMutation } from "@tanstack/react-query";
import { toast } from "sonner";

import { studentService } from "../api";
import type { StudentProfile, EnrollmentStatus } from "../api";
import "../../institution/institution.css";

import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin } from "../../../shared/lib/rbac";
import { useDebouncedValue } from "../../../shared/lib/hooks/useDebouncedValue";
import { getApiErrorMessage } from "../../../shared/lib/api-error";

import {
  DataTable,
  FilterSelect,
  PaginationControls,
} from "../../../shared/crud";
import type { DataTableColumn } from "../../../shared/crud";
import {
  Alert,
  Badge,
  Button,
  Modal,
  SearchInput,
} from "../../../shared/ui";
import { Search } from "lucide-react";

const STATUS_TONE: Record<EnrollmentStatus, "success" | "warning" | "info" | "danger" | "neutral"> = {
  current: "info",
  leave: "warning",
  withheld: "danger",
  graduated: "success",
  terminated: "neutral",
};

const STATUS_LABEL: Record<EnrollmentStatus, string> = {
  current: "Current",
  leave: "On Leave",
  withheld: "Withheld",
  graduated: "Graduated",
  terminated: "Terminated",
};

const ENROLLMENT_STATUSES: EnrollmentStatus[] = [
  "current",
  "leave",
  "withheld",
  "graduated",
  "terminated",
];

const StudentsPage = () => {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const canManage = isInstitutionAdmin(user);

  const [page, setPage] = useState(1);
  const [searchInput, setSearchInput] = useState("");
  const [statusFilter, setStatusFilter] = useState<string>("");
  const debouncedSearch = useDebouncedValue(searchInput.trim(), 400);

  const studentsQuery = useQuery({
    queryKey: ["students", "list", page, debouncedSearch, statusFilter],
    queryFn: () =>
      studentService.getStudents({
        page,
        q: debouncedSearch || undefined,
        status: (statusFilter as EnrollmentStatus | undefined) || undefined,
        per_page: 20,
      }),
  });

  const students = studentsQuery.data?.data ?? [];
  const lastPage = studentsQuery.data?.last_page ?? 1;
  const total = studentsQuery.data?.total ?? 0;
  const from = studentsQuery.data?.from ?? null;
  const to = studentsQuery.data?.to ?? null;

  // ---------- Modal state ----------
  const [selectedId, setSelectedId] = useState<number | null>(null);

  const selectedProfileQuery = useQuery({
    queryKey: ["students", "profile", selectedId],
    queryFn: () => studentService.getStudent(selectedId!),
    enabled: selectedId !== null,
  });

  const detail = selectedProfileQuery.data?.student ?? null;

  const updateStatusMutation = useMutation({
    mutationFn: ({ status, remarks }: { status: EnrollmentStatus; remarks?: string }) =>
      studentService.updateStudentStatus(selectedId!, {
        enrollment_status: status,
        left_on: status === "graduated" ? new Date().toISOString().slice(0, 10) : undefined,
        remarks,
      }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["students"] });
      toast.success("Enrollment status updated.");
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error, "Unable to update status."));
    },
  });

  const columns: DataTableColumn<StudentProfile>[] = [
    {
      key: "student",
      header: "Student",
      render: (student) => (
        <div className="institution-name-cell">
          <div className="institution-avatar">
            <GraduationCap size={17} />
          </div>
          <div>
            <strong>{student.user?.name ?? "—"}</strong>
            <span className="institution-mono">{student.user?.email ?? "—"}</span>
          </div>
        </div>
      ),
    },
    {
      key: "application",
      header: "Application #",
      render: (student) => (
        <span className="institution-mono">{student.application_number ?? "—"}</span>
      ),
    },
    {
      key: "programme",
      header: "Programme",
      render: (student) =>
        student.programme ? `${student.programme.code} — ${student.programme.name}` : "—",
    },
    {
      key: "session",
      header: "Session",
      render: (student) => (
        <span className="institution-mono">
          {student.academic_session?.code ?? "—"}
        </span>
      ),
    },
    {
      key: "status",
      header: "Status",
      render: (student) => (
        <Badge tone={STATUS_TONE[student.enrollment_status]}>
          {STATUS_LABEL[student.enrollment_status]}
        </Badge>
      ),
    },
    {
      key: "admitted",
      header: "Admitted",
      render: (student) => (
        <span className="institution-mono">
          {student.admitted_on?.slice(0, 10) ?? "—"}
        </span>
      ),
    },
    ...(canManage
      ? [
          {
            key: "actions",
            header: "",
            render: (student: StudentProfile) => (
              <button
                type="button"
                className="institution-icon-button"
                title="View / edit student profile"
                onClick={() => setSelectedId(student.id)}
              >
                <Pencil size={16} />
              </button>
            ),
          } as DataTableColumn<StudentProfile>,
        ]
      : []),
  ];

  return (
    <>
      {studentsQuery.isError && (
        <Alert tone="error" className="institution-directory-error">
          Unable to load students. Please try again.
          <button
            type="button"
            onClick={() => void studentsQuery.refetch()}
          >
            Retry
          </button>
        </Alert>
      )}

      <div className="institution-directory-toolbar">
        <SearchInput
          icon={Search}
          placeholder="Search by name, email or application number..."
          value={searchInput}
          onChange={(event) => setSearchInput(event.target.value)}
          aria-label="Search students"
        />

        <FilterSelect
          aria-label="Filter by enrollment status"
          value={statusFilter}
          onChange={setStatusFilter}
        >
          <option value="">All Statuses</option>
          {ENROLLMENT_STATUSES.map((s) => (
            <option key={s} value={s}>
              {STATUS_LABEL[s]}
            </option>
          ))}
        </FilterSelect>
      </div>

      <DataTable<StudentProfile>
        columns={columns}
        rows={students}
        rowKey={(student) => student.id}
        loading={studentsQuery.isLoading}
        error={
          studentsQuery.isError
            ? "Students could not be loaded."
            : null
        }
        emptyMessage="No students match the current filters."
      />

      <PaginationControls
        page={page}
        lastPage={lastPage}
        total={total}
        from={from}
        to={to}
        loading={studentsQuery.isFetching}
        onPageChange={setPage}
        itemLabel="students"
      />

      <Modal
        open={selectedId !== null}
        title={detail ? `${detail.user?.name ?? "Student"} · ${detail.application_number ?? "unknown student"}` : "Student"}
        onClose={() => setSelectedId(null)}
        maxWidth={720}
        footer={
          <div style={{ display: "flex", gap: 8, alignItems: "center", flexWrap: "wrap" }}>
            {detail && (
              <select
                className="filter-select"
                style={{ minWidth: 160 }}
                value={detail.enrollment_status}
                disabled={updateStatusMutation.isPending || !canManage}
                onChange={(event) => {
                  const next = event.target.value as EnrollmentStatus;
                  if (next !== detail.enrollment_status) {
                    updateStatusMutation.mutate({ status: next });
                  }
                }}
              >
                {ENROLLMENT_STATUSES.map((s) => (
                  <option key={s} value={s}>
                    {STATUS_LABEL[s]}
                  </option>
                ))}
              </select>
            )}
            <Button type="button" variant="secondary" size="sm" onClick={() => setSelectedId(null)}>
              <X size={14} /> Close
            </Button>
          </div>
        }
      >
        {detail ? (
          <div style={{ display: "grid", gap: "var(--space-3)" }}>
            <div
              style={{
                display: "flex",
                alignItems: "center",
                justifyContent: "space-between",
                gap: 12,
              }}
            >
              <div>
                <div style={{ fontSize: 18, fontWeight: 700, color: "var(--text-primary)" }}>
                  {detail.user?.name}
                </div>
                <div className="institution-mono">
                  <Mail size={12} style={{ verticalAlign: "-1px" }} /> {detail.user?.email}
                  {detail.user?.phone && (
                    <> · <PhoneCall size={12} style={{ verticalAlign: "-1px" }} /> {detail.user.phone}</>
                  )}
                </div>
              </div>
              <Badge tone={STATUS_TONE[detail.enrollment_status]}>
                {STATUS_LABEL[detail.enrollment_status]}
              </Badge>
            </div>

            <div className="institution-admin-detail-list">
              <div>
                <dt>Application</dt>
                <dd>{detail.application_number ?? "—"}</dd>
              </div>
              <div>
                <dt>Programme</dt>
                <dd>{detail.programme ? `${detail.programme.code} — ${detail.programme.name}` : "—"}</dd>
              </div>
              <div>
                <dt>Academic session</dt>
                <dd>{detail.academic_session?.name ?? "—"}</dd>
              </div>
              <div>
                <dt>Gender / Category</dt>
                <dd>{detail.gender ?? "—"} · {detail.category ?? "—"}</dd>
              </div>
              <div>
                <dt>Date of birth</dt>
                <dd>{detail.dob ? String(detail.dob).slice(0, 10) : "—"}</dd>
              </div>
              <div>
                <dt>Guardian</dt>
                <dd>
                  {detail.guardian_name ?? "—"}
                  {detail.guardian_relation ? ` (${detail.guardian_relation})` : ""}
                  {detail.guardian_phone ? ` · ${detail.guardian_phone}` : ""}
                </dd>
              </div>
              <div>
                <dt>Address</dt>
                <dd>
                  {[detail.address_line, detail.city, detail.state, detail.pin_code]
                    .filter(Boolean)
                    .join(", ") || "Not provided"}
                </dd>
              </div>
              <div>
                <dt>Admitted</dt>
                <dd>
                  {detail.admitted_on ? String(detail.admitted_on).slice(0, 10) : "—"}
                  {detail.left_on && ` · Left: ${String(detail.left_on).slice(0, 10)}`}
                </dd>
              </div>
              <div>
                <dt>Remarks</dt>
                <dd>{detail.remarks ?? "—"}</dd>
              </div>
            </div>
          </div>
        ) : (
          <p>Loading student...</p>
        )}
      </Modal>
    </>
  );
};

export default StudentsPage;
