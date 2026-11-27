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
  FileText,
  Download,
  History,
} from "lucide-react";
import { useQuery, useQueryClient, useMutation } from "@tanstack/react-query";
import { toast } from "sonner";

import { admissionService } from "../api";
import type {
  ApplicationRow,
  ApplicationStatus,
  DecisionPayload,
  ApplicationReviewDocument,
} from "../types";
import { useAuth } from "../../../contexts/AuthContext";
import { canViewAdmissionDocuments, isInstitutionAdmin } from "../../../shared/lib/rbac";
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
  const canViewDocuments = canViewAdmissionDocuments(user);

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
  const [clarificationText, setClarificationText] = useState("");
  const [clarificationDueAt, setClarificationDueAt] = useState("");
  const [reviewNotes, setReviewNotes] = useState<Record<number, string>>({});
  const [decisionNotes, setDecisionNotes] = useState("");
  const detailQuery = useQuery({
    queryKey: ["admissions", "application", selectedId],
    queryFn: () => admissionService.getApplication(selectedId!),
    enabled: selectedId !== null && Number.isFinite(selectedId),
  });

  const openApplication = (id: number) => {
    setDecisionNotes("");
    setReviewNotes({});
    setClarificationText("");
    setClarificationDueAt("");
    setSelectedId(id);
  };

  const closeApplication = () => {
    setSelectedId(null);
    setDecisionNotes("");
    setReviewNotes({});
    setClarificationText("");
    setClarificationDueAt("");
  };

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
      setDecisionNotes("");
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
      await queryClient.invalidateQueries({ queryKey: ["admissions", "intakes"] });
      toast.success(data.message);
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error, "Unable to enroll the applicant."));
    },
  });

  const downloadDocumentMutation = useMutation({
    mutationFn: ({ applicationId, document }: { applicationId: number; document: ApplicationReviewDocument }) =>
      admissionService.downloadApplicationDocument(applicationId, document),
    onSuccess: (blob, variables) => {
      const url = window.URL.createObjectURL(blob);
      const anchor = window.document.createElement("a");
      anchor.href = url;
      anchor.download = variables.document.original_filename;
      window.document.body.appendChild(anchor);
      anchor.click();
      anchor.remove();
      window.URL.revokeObjectURL(url);
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error, "Unable to download this document."));
    },
  });

  const refreshApplication = async () => {
    await queryClient.invalidateQueries({ queryKey: ["admissions", "application", selectedId] });
  };

  const clarificationMutation = useMutation({
    mutationFn: () => admissionService.createClarification(selectedId!, {
      request_text: clarificationText,
      due_at: clarificationDueAt ? new Date(clarificationDueAt).toISOString() : null,
    }),
    onSuccess: async (result) => {
      setClarificationText("");
      setClarificationDueAt("");
      toast.success(result.message);
      await refreshApplication();
    },
    onError: (error) => toast.error(getApiErrorMessage(error, "Unable to send clarification.")),
  });

  const resolveClarificationMutation = useMutation({
    mutationFn: (clarificationId: number) => admissionService.resolveClarification(selectedId!, clarificationId),
    onSuccess: async (result) => { toast.success(result.message); await refreshApplication(); },
    onError: (error) => toast.error(getApiErrorMessage(error, "Unable to resolve clarification.")),
  });

  const verifyDocumentMutation = useMutation({
    mutationFn: ({ documentId, verification_status, review_notes }: { documentId: number; verification_status: "verified" | "returned"; review_notes?: string }) => admissionService.verifyDocument(selectedId!, documentId, { verification_status, review_notes }),
    onSuccess: async (result) => { toast.success(result.message); await refreshApplication(); },
    onError: (error) => toast.error(getApiErrorMessage(error, "Unable to update document verification.")),
  });

  const selected = detailQuery.data?.application;
  const review = detailQuery.data?.review;

  const columns: DataTableColumn<ApplicationRow>[] = [
    {
      key: "application",
      header: "Application #",
      render: (row) => (
        <button
          type="button"
          className="admissions-applicant-link"
          onClick={() => openApplication(row.id)}
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
          onChange={(event) => { setSearchInput(event.target.value); setPage(1); }}
          aria-label="Search applications"
        />

        <FilterSelect
          aria-label="Filter by intake"
          value={intakeFilter}
          onChange={(value) => { setIntakeFilter(value); setPage(1); }}
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
          onChange={(value) => { setStatusFilter(value); setPage(1); }}
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
        onClose={closeApplication}
        maxWidth={900}
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
                        decideMutation.mutate({ id: selected.id, status: decision.status, notes: decisionNotes.trim() || undefined })
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
                onClick={closeApplication}
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
              onClick={closeApplication}
            >
              Close
            </Button>
          )
        }
      >
        {detailQuery.isLoading ? (
          <p>Loading application details...</p>
        ) : detailQuery.isError ? (
          <Alert tone="error" className="admissions-error">
            Unable to load this application. Please try again.
            <button type="button" onClick={() => void detailQuery.refetch()}>
              Retry
            </button>
          </Alert>
        ) : !selected ? (
          <p>Application details are unavailable.</p>
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

            {selected.applicant && (
              <section className="admissions-review-section">
                <h3>Applicant Portal Account</h3>
                <p>
                  {selected.applicant.full_name} · {selected.applicant.email}
                  {selected.applicant.mobile ? ` · ${selected.applicant.mobile_country_code ?? ""} ${selected.applicant.mobile}` : ""}
                </p>
              </section>
            )}

            {(review?.form_sections ?? []).map((section) => (
              <section className="admissions-review-section" key={section.id}>
                <h3>{section.title}</h3>
                {section.description && <p className="admissions-review-description">{section.description}</p>}
                {section.fields.length === 0 ? (
                  <p className="admissions-review-empty">No fields configured in this section.</p>
                ) : (
                  <dl className="admissions-review-fields">
                    {section.fields.filter((field) => field.type !== "file").map((field) => {
                      const raw = field.answer;
                      const option = field.options?.find((item) => item.value === String(raw));
                      const answer = raw === null || raw === undefined || raw === ""
                        ? "—"
                        : typeof raw === "boolean"
                          ? (raw ? "Yes" : "No")
                          : Array.isArray(raw)
                            ? raw.join(", ")
                            : option?.label ?? (typeof raw === "object" ? JSON.stringify(raw) : String(raw));
                      return (
                        <div key={field.key}>
                          <dt>{field.label}</dt>
                          <dd>{answer}</dd>
                        </div>
                      );
                    })}
                  </dl>
                )}
              </section>
            ))}

            {canReview && selected.status !== "enrolled" && selected.status !== "rejected" && (
              <section className="admissions-review-section">
                <h3>Decision remarks</h3>
                <label className="admissions-decision-notes-label" htmlFor="admissions-decision-notes">
                  Optional remarks recorded with the next decision
                </label>
                <textarea
                  id="admissions-decision-notes"
                  rows={3}
                  maxLength={2000}
                  value={decisionNotes}
                  onChange={(event) => setDecisionNotes(event.target.value)}
                  placeholder="Add review remarks or the reason for the decision."
                />
              </section>
            )}

            <section className="admissions-review-section">
              <h3><GraduationCap size={16} /> Academic Qualifications</h3>
              {(review?.qualifications ?? []).length === 0 ? (
                <p className="admissions-review-empty">No academic qualifications were recorded.</p>
              ) : (
                <div className="admissions-review-table-wrap">
                  <table className="admissions-review-table">
                    <thead><tr><th>Qualification</th><th>Board / University</th><th>Year</th><th>Result</th></tr></thead>
                    <tbody>
                      {(review?.qualifications ?? []).map((qualification) => (
                        <tr key={qualification.id}>
                          <td><strong>{qualification.qualification_name}</strong><small>{String(qualification.qualification_level).replaceAll("_", " ")}</small></td>
                          <td>{String(qualification.board_or_university ?? "—")}</td>
                          <td>{String(qualification.passing_year ?? "—")}</td>
                          <td>{qualification.percentage != null ? `${qualification.percentage}%` : qualification.cgpa != null ? `CGPA ${qualification.cgpa}` : String(qualification.result_status ?? "—")}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </section>

            <section className="admissions-review-section">
              <h3><FileText size={16} /> Uploaded Documents</h3>
              {!canViewDocuments || !review?.can_view_documents ? (
                <p className="admissions-review-empty">Your role does not have permission to view applicant documents.</p>
              ) : (review?.documents ?? []).length === 0 ? (
                <p className="admissions-review-empty">No documents were uploaded for this application.</p>
              ) : (
                <div className="admissions-document-list">
                  {(review?.documents ?? []).map((document) => (
                    <div className="admissions-document-row" key={document.id}>
                      <div className="admissions-document-info">
                        <FileText size={18} />
                        <div><strong>{document.original_filename}</strong><small>{document.document_type.replaceAll("_", " ")} · {document.file_size ? `${(document.file_size / 1024).toFixed(0)} KB` : "Size unavailable"} · {document.verification_status}</small>
                          {document.review_notes && <small className="admissions-document-review-note">Reviewer: {document.review_notes}</small>}
                          {review?.can_verify_documents && ["pending", "returned"].includes(document.verification_status) && <textarea aria-label={`Return remarks for ${document.original_filename}`} placeholder="Required remarks when returning this document" value={reviewNotes[document.id] ?? ""} onChange={(event) => setReviewNotes(current => ({ ...current, [document.id]: event.target.value }))} />}
                        </div>
                      </div>
                      <div className="admissions-document-actions">
                        <Button type="button" size="sm" variant="secondary" disabled={downloadDocumentMutation.isPending} onClick={() => downloadDocumentMutation.mutate({ applicationId: selected.id, document })}>
                          <Download size={14} /> Download
                        </Button>
                        {review?.can_verify_documents && ["pending", "returned"].includes(document.verification_status) && (
                          <>
                            <Button type="button" size="sm" disabled={verifyDocumentMutation.isPending} onClick={() => verifyDocumentMutation.mutate({ documentId: document.id, verification_status: "verified" })}>Verify</Button>
                            <Button type="button" size="sm" variant="danger" disabled={verifyDocumentMutation.isPending || !(reviewNotes[document.id] ?? "").trim()} onClick={() => verifyDocumentMutation.mutate({ documentId: document.id, verification_status: "returned", review_notes: reviewNotes[document.id] })}>Return</Button>
                          </>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </section>

            <section className="admissions-review-section">
              <h3>Clarification requests</h3>
              {(review?.clarifications ?? []).length === 0 ? <p className="admissions-review-empty">No clarification requests have been raised.</p> : (review?.clarifications ?? []).map((item) => (
                <div className="admissions-clarification-card" key={item.id}>
                  <div><strong>{item.request_text}</strong><Badge tone={item.status === "resolved" ? "success" : item.status === "responded" ? "info" : "warning"}>{item.status}</Badge></div>
                  {item.requested_fields.length > 0 && <small>Requested fields: {item.requested_fields.join(", ")}</small>}
                  {item.due_at && <small>Due: {new Date(item.due_at).toLocaleString()}</small>}
                  {item.applicant_response && <p><strong>Applicant response:</strong> {item.applicant_response}</p>}
                  {item.status === "responded" && review?.can_scrutinize && <Button type="button" size="sm" disabled={resolveClarificationMutation.isPending} onClick={() => resolveClarificationMutation.mutate(item.id)}>Mark resolved</Button>}
                </div>
              ))}
              {review?.can_scrutinize && ["under_review", "shortlisted", "waitlisted"].includes(selected.status) && (
                <div className="admissions-clarification-compose">
                  <label>Request clarification<textarea value={clarificationText} onChange={(event) => setClarificationText(event.target.value)} rows={3} maxLength={5000} placeholder="Describe the information or correction required from the applicant." /></label>
                  <label>Response due date<input type="datetime-local" value={clarificationDueAt} onChange={(event) => setClarificationDueAt(event.target.value)} /></label>
                  <Button type="button" disabled={clarificationMutation.isPending || clarificationText.trim().length < 5} onClick={() => clarificationMutation.mutate()}>Send clarification</Button>
                </div>
              )}
            </section>

            <section className="admissions-review-section">
              <h3><History size={16} /> Application History</h3>
              {(review?.events ?? []).length === 0 ? (
                <p className="admissions-review-empty">No workflow events have been recorded.</p>
              ) : (
                <ol className="admissions-event-list">
                  {(review?.events ?? []).map((event) => (
                    <li key={event.id}>
                      <div><strong>{event.event_type.replaceAll("_", " ")}</strong><small>{String(event.occurred_at).replace("T", " ").slice(0, 16)} · {event.actor_type ?? "system"}</small></div>
                      {event.from_status || event.to_status ? <span>{event.from_status ?? "—"} → {event.to_status ?? "—"}</span> : null}
                      {event.remarks && <p>{event.remarks}</p>}
                    </li>
                  ))}
                </ol>
              )}
            </section>
          </div>
        )}
      </Modal>


    </>
  );
};

export default AdmissionApplicationsPage;
