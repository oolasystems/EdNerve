import { useState } from "react";
import "../admissions.css";
import {
  Search,
  X,
  PhoneCall,
  Mail,
  ShieldCheck,
  UserCheck,
  GraduationCap,
} from "lucide-react";
import { useQuery, useQueryClient, useMutation } from "@tanstack/react-query";
import { toast } from "sonner";

import { admissionService } from "../api";
import type {
  ApplicationRow,
  ApplicationStatus,
  DecisionPayload,
} from "../types";
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

const STATUS_META: Record<ApplicationStatus, { label: string; tone: "success" | "warning" | "info" | "danger" | "neutral" }> = {
  new: { label: "New", tone: "info" },
  under_review: { label: "Under Review", tone: "info" },
  shortlisted: { label: "Shortlisted", tone: "success" },
  waitlisted: { label: "Waitlisted", tone: "warning" },
  rejected: { label: "Rejected", tone: "danger" },
  admitted: { label: "Admitted", tone: "success" },
  enrolled: { label: "Enrolled", tone: "success" },
};

const DECISIONS: Array<{
  status: DecisionPayload["status"];
  label: string;
  tone: "primary" | "secondary" | "danger";
}> = [
  { status: "under_review", label: "Mark Under Review", tone: "secondary" },
  { status: "shortlisted", label: "Shortlist", tone: "primary" },
  { status: "waitlisted", label: "Waitlist", tone: "secondary" },
  { status: "admitted", label: "Admit", tone: "primary" },
  { status: "rejected", label: "Reject", tone: "danger" },
];

const AdmissionApplicationsPage = () => {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const canReview = isInstitutionAdmin(user);

  const [page, setPage] = useState(1);
  const [searchInput, setSearchInput] = useState("");
  const [statusFilter, setStatusFilter] = useState<string>("all");
  const [intakeFilter, setIntakeFilter] = useState<string>("all");
  const debouncedSearch = useDebouncedValue(searchInput.trim(), 400);

  const intakesQuery = useQuery({
    queryKey: ["admissions", "intakes"],
    queryFn: () => admissionService.getIntakes(),
  });

  const applicationsQuery = useQuery({
    queryKey: [
      "admissions",
      "applications",
      intakeFilter,
      statusFilter,
      debouncedSearch,
      page,
    ],
    queryFn: () =>
      admissionService.getApplications({
        page,
        per_page: 20,
        intake_id: intakeFilter === "all" ? undefined : Number(intakeFilter),
        status: statusFilter === "all" ? undefined : statusFilter,
        q: debouncedSearch || undefined,
      }),
  });

  const applications = applicationsQuery.data?.data ?? [];

  // ----- Detail modal state -----
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const detailQuery = useQuery({
    queryKey: ["admissions", "application", selectedId],
    queryFn: () => admissionService.getApplication(selectedId!),
    enabled: selectedId !== null && Number.isFinite(selectedId),
  });

  // ----- Credentials modal (shown once after enrollment) -----
  const [credentials, setCredentials] = useState<{
    name: string;
    email: string;
    temporary_password: string;
  } | null>(null);

  const decideMutation = useMutation({
    mutationFn: ({
      id,
      status,
      notes,
    }: {
      id: number;
      status: DecisionPayload["status"];
      notes?: string;
    }) =>
      admissionService.decideApplication(id, { status, notes }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["admissions", "applications"] });
      await queryClient.invalidateQueries({ queryKey: ["admissions", "application"] });
      toast.success("Decision recorded.");
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error, "Unable to record the decision."));
    },
  });

  const enrollMutation = useMutation({
    mutationFn: (id: number) => admissionService.enrollApplicant(id),
    onSuccess: async (data) => {
      await queryClient.invalidateQueries({ queryKey: ["admissions", "applications"] });
      await queryClient.invalidateQueries({ queryKey: ["admissions", "application"] });
      if (data.credentials) {
        setCredentials(data.credentials);
      } else {
        toast.success(data.message);
      }
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error, "Unable to enroll the applicant."));
    },
  });

  const selected = detailQuery.data?.application;

  const columns: DataTableColumn<ApplicationRow>[] = [
    {
      key: "application",
      header: "Application #",
      render: (row) => (
        <button
          type="button"
          className="admissions-applicant-link"
          onClick={() => setSelectedId(row.id)}
        >
          {row.application_number}
        </button>
      ),
    },
    {
      key: "applicant",
      header: "Applicant",
      render: (row) => (
        <div>
          <strong className="admissions-applicant-name">
            {row.first_name} {row.last_name}
          </strong>
          <div className="admissions-mono admissions-applicant-email">
            {row.email}
          </div>
        </div>
      ),
    },
    {
      key: "intake",
      header: "Intake",
      render: (row) => row.intake?.name ?? "—",
    },
    {
      key: "category",
      header: "Cat.",
      render: (row) => row.category,
    },
    {
      key: "status",
      header: "Status",
      render: (row) => {
        const meta = STATUS_META[row.status];
        return <Badge tone={meta.tone}>{meta.label}</Badge>;
      },
    },
    {
      key: "applied",
      header: "Applied",
      render: (row) => (
        <span className="admissions-mono">
          {row.created_at?.slice(0, 10) ?? "—"}
        </span>
      ),
    },
  ];

  const intakes = intakesQuery.data?.intakes ?? [];

  return (
    <>
      {applicationsQuery.isError && (
        <Alert tone="error" className="admissions-error">
          Unable to load applications. Please try again.
          <button
            type="button"
            onClick={() => void applicationsQuery.refetch()}
          >
            Retry
          </button>
        </Alert>
      )}

      <div className="admissions-toolbar">
        <SearchInput
          icon={Search}
          placeholder="Search application number, name, email, phone..."
          value={searchInput}
          onChange={(event) => setSearchInput(event.target.value)}
          aria-label="Search applications"
        />

        <FilterSelect
          aria-label="Filter by intake"
          value={intakeFilter}
          onChange={setIntakeFilter}
        >
          <option value="all">All Intakes</option>
          {intakes.map((intake) => (
            <option key={intake.id} value={intake.id}>
              {intake.name}
            </option>
          ))}
        </FilterSelect>

        <FilterSelect
          aria-label="Filter by status"
          value={statusFilter}
          onChange={setStatusFilter}
        >
          <option value="all">All Statuses</option>
          <option value="new">New</option>
          <option value="under_review">Under Review</option>
          <option value="shortlisted">Shortlisted</option>
          <option value="waitlisted">Waitlisted</option>
          <option value="admitted">Admitted</option>
          <option value="enrolled">Enrolled</option>
          <option value="rejected">Rejected</option>
        </FilterSelect>
      </div>

      <DataTable<ApplicationRow>
        columns={columns}
        rows={applications}
        rowKey={(row) => row.id}
        loading={applicationsQuery.isLoading}
        error={
          applicationsQuery.isError
            ? "Applications could not be loaded."
            : null
        }
        emptyMessage="No applications match the current filters."
      />

      <PaginationControls
        page={page}
        lastPage={applicationsQuery.data?.last_page ?? 1}
        total={applicationsQuery.data?.total ?? 0}
        from={applicationsQuery.data?.from ?? null}
        to={applicationsQuery.data?.to ?? null}
        loading={applicationsQuery.isFetching}
        onPageChange={setPage}
        itemLabel="applications"
      />

      {/* Detail + review modal */}
      <Modal
        open={selectedId !== null}
        title={selected ? `${selected.application_number}` : "Application"}
        onClose={() => setSelectedId(null)}
        maxWidth={720}
        footer={
          canReview ? (
            <div className="admissions-decision-actions">
              {selected && selected.status !== "enrolled" && selected.status !== "rejected" && (
                <>
                  {DECISIONS.map((decision) => (
                    <Button
                      key={decision.status}
                      type="button"
                      variant={decision.tone === "primary" ? "primary" : decision.tone === "danger" ? "danger" : "secondary"}
                      size="sm"
                      onClick={() =>
                        decideMutation.mutate({ id: selected.id, status: decision.status })
                      }
                      disabled={decideMutation.isPending}
                    >
                      {decision.label}
                    </Button>
                  ))}
                  {selected.status === "admitted" && (
                    <Button
                      type="button"
                      size="sm"
                      disabled={enrollMutation.isPending}
                      onClick={() => enrollMutation.mutate(selected.id)}
                    >
                      <UserCheck size={14} />
                      Enroll
                    </Button>
                  )}
                </>
              )}
              <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={() => setSelectedId(null)}
              >
                <X size={14} />
                Close
              </Button>
            </div>
          ) : (
            <Button
              type="button"
              variant="secondary"
              size="sm"
              onClick={() => setSelectedId(null)}
            >
              Close
            </Button>
          )
        }
      >
        {detailQuery.isLoading || !selected ? (
          <p>Loading application details...</p>
        ) : (
          <div className="admissions-stack">
            <div className="admissions-applicant-summary">
              <div>
                <div className="admissions-applicant-heading">
                  {selected.first_name} {selected.middle_name ?? ""} {selected.last_name}
                </div>
                <div className="admissions-mono">
                  {selected.application_number}
                </div>
              </div>
              <Badge tone={STATUS_META[selected.status].tone}>
                {STATUS_META[selected.status].label}
              </Badge>
            </div>

            <div className="admissions-detail-list">
              <div>
                <dt>Date of birth</dt>
                <dd>{selected.dob?.slice(0, 10)}</dd>
              </div>
              <div>
                <dt>Gender / Category</dt>
                <dd>{selected.gender ?? "—"} · {selected.category}</dd>
              </div>
              <div>
                <dt>
                  <Mail size={12} className="admissions-inline-icon" /> Contact
                </dt>
                <dd>
                  {selected.email} · {selected.phone}
                </dd>
              </div>
              <div>
                <dt>Address</dt>
                <dd>
                  {[selected.address_line, selected.city, selected.state, selected.pin_code]
                    .filter(Boolean)
                    .join(", ") || "Not provided"}
                </dd>
              </div>
              <div>
                <dt>Previous exam</dt>
                <dd>
                  {selected.last_exam_name ?? "—"}
                  {selected.last_exam_board_or_univ ? ` · ${selected.last_exam_board_or_univ}` : ""}
                  {selected.last_exam_year ? ` · ${selected.last_exam_year}` : ""}
                  {selected.last_exam_percentage
                    ? ` · ${Number(selected.last_exam_percentage).toFixed(2)}%`
                    : ""}
                </dd>
              </div>
              <div>
                <dt>Applicant contact</dt>
                <dd>
                  <PhoneCall size={12} className="admissions-inline-icon" />{" "}
                  {selected.father_name ? `Father: ${selected.father_name}` : ""}
                  {selected.father_name && selected.mother_name ? " · " : ""}
                  {selected.mother_name ? `Mother: ${selected.mother_name}` : ""}
                </dd>
              </div>
              <div>
                <dt>Intake</dt>
                <dd>{selected.intake?.name}</dd>
              </div>
              <div>
                <dt>Programme</dt>
                <dd>{selected.intake?.programme?.name}</dd>
              </div>
              {selected.review_notes && (
                <div>
                  <dt>Review notes</dt>
                  <dd>{selected.review_notes}</dd>
                </div>
              )}
              {selected.reviewed_at && (
                <div>
                  <dt>Last decision</dt>
                  <dd>
                    {String(selected.reviewed_at).replace("T", " ").slice(0, 16)}
                    {selected.reviewer ? ` by ${selected.reviewer.name}` : ""}
                  </dd>
                </div>
              )}
              {selected.enrolledUser && (
                <div>
                  <dt>Enrolled account</dt>
                  <dd>
                    <ShieldCheck size={12} className="admissions-inline-icon admissions-accent-icon" />{" "}
                    {selected.enrolledUser.name} ({selected.enrolledUser.email})
                  </dd>
                </div>
              )}
            </div>
          </div>
        )}
      </Modal>

      {/* One-time credentials modal */}
      <Modal
        open={credentials !== null}
        title="Student Account Created"
        onClose={() => setCredentials(null)}
        footer={
          <Button type="button" onClick={() => setCredentials(null)}>
            <GraduationCap size={14} />
            I have saved the password
          </Button>
        }
      >
        {credentials && (
          <div className="admissions-stack">
            <Alert tone="warning">
              The temporary password below is shown <strong>only once</strong>.
              Share it with the student through a secure channel.
            </Alert>
            <div className="admissions-detail-list">
              <div>
                <dt>Name</dt>
                <dd>{credentials.name}</dd>
              </div>
              <div>
                <dt>Email (login)</dt>
                <dd>{credentials.email}</dd>
              </div>
              <div>
                <dt>Temporary password</dt>
                <dd className="admissions-temporary-password">
                  {credentials.temporary_password}
                </dd>
              </div>
            </div>
            <Button
              type="button"
              variant="secondary"
              onClick={() => navigator.clipboard?.writeText(credentials.temporary_password).catch(() => undefined)}
            >
              Copy password
            </Button>
          </div>
        )}
      </Modal>
    </>
  );
};

export default AdmissionApplicationsPage;
