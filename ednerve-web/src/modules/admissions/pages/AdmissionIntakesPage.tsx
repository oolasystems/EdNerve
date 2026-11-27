import { CalendarCheck2, Plus, Pencil, Trash2, DoorOpen, DoorClosed } from "lucide-react";
import "../admissions.css";
import { useMemo, useState } from "react";
import { useQuery, useQueryClient, useMutation } from "@tanstack/react-query";
import { toast } from "sonner";

import { admissionService } from "../api";
import type {
  AdmissionIntake,
  AdmissionIntakeStatus,
  CreateAdmissionIntakePayload,
} from "../types";
import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin } from "../../../shared/lib/rbac";
import { useDebouncedValue } from "../../../shared/lib/hooks/useDebouncedValue";
import { getApiErrorMessage } from "../../../shared/lib/api-error";

import { academicService } from "../../academic/api";

import {
  DataTable,
  FilterSelect,
} from "../../../shared/crud";
import type { DataTableColumn } from "../../../shared/crud";
import {
  Alert,
  Badge,
  Button,
  FormField,
  Input,
  Modal,
  SearchInput,
} from "../../../shared/ui";
import { Search } from "lucide-react";

const EMPTY_LIST: never[] = [];

const emptyForm = {
  name: "",
  programme_id: "" as string | number,
  academic_session_id: "" as string | number,
  open_date: "",
  close_date: "",
  seat_capacity: 60,
  status: "open" as AdmissionIntakeStatus,
  application_fee: "" as string | number,
};

const AdmissionIntakesPage = () => {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const canManage = isInstitutionAdmin(user);

  const [searchInput, setSearchInput] = useState("");
  const [statusFilter, setStatusFilter] = useState<string>("all");
  const debouncedSearch = useDebouncedValue(searchInput.trim(), 300);

  const intakesQuery = useQuery({
    queryKey: ["admissions", "intakes"],
    queryFn: () => admissionService.getIntakes(),
  });

  const programmesQuery = useQuery({
    queryKey: ["academic", "programmes"],
    queryFn: () => academicService.getProgrammes(),
  });

  const sessionsQuery = useQuery({
    queryKey: ["academic", "sessions"],
    queryFn: () => academicService.getSessions(),
  });

  const allIntakes = intakesQuery.data?.intakes ?? EMPTY_LIST;

  const filteredIntakes = useMemo(() => {
    const term = debouncedSearch.toLowerCase();
    return allIntakes.filter((intake) => {
      const matchesSearch =
        !term ||
        intake.name.toLowerCase().includes(term) ||
        (intake.programme?.name ?? "").toLowerCase().includes(term) ||
        (intake.session?.code ?? "").toLowerCase().includes(term);
      const matchesStatus =
        statusFilter === "all" || intake.status === statusFilter;
      return matchesSearch && matchesStatus;
    });
  }, [allIntakes, debouncedSearch, statusFilter]);

  const [modalOpen, setModalOpen] = useState(false);
  const [editingIntake, setEditingIntake] = useState<AdmissionIntake | null>(null);
  const [form, setForm] = useState(emptyForm);
  const [formError, setFormError] = useState("");

  const openCreateModal = () => {
    setEditingIntake(null);
    setForm(emptyForm);
    setFormError("");
    setModalOpen(true);
  };

  const openEditModal = (intake: AdmissionIntake) => {
    setEditingIntake(intake);
    setForm({
      name: intake.name,
      programme_id: intake.programme?.id ?? "",
      academic_session_id: intake.session?.id ?? "",
      open_date: intake.open_date ?? "",
      close_date: intake.close_date ?? "",
      seat_capacity: intake.seat_capacity ?? 60,
      status: intake.status,
      application_fee: intake.application_fee ?? "",
    });
    setFormError("");
    setModalOpen(true);
  };

  const payload: CreateAdmissionIntakePayload = {
    name: form.name.trim(),
    programme_id: Number(form.programme_id),
    academic_session_id: Number(form.academic_session_id),
    open_date: form.open_date,
    close_date: form.close_date,
    seat_capacity: Number(form.seat_capacity),
    status: form.status,
    application_fee:
      form.application_fee === "" ? null : Number(form.application_fee),
  };

  const saveMutation = useMutation({
    mutationFn: async () => {
      if (editingIntake) {
        return admissionService.updateIntake(editingIntake.id, payload);
      }
      return admissionService.createIntake(payload);
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["admissions", "intakes"],
      });
      toast.success(editingIntake ? "Intake updated." : "Intake created.");
      setModalOpen(false);
    },
    onError: (error) => {
      setFormError(getApiErrorMessage(error, "Unable to save the intake."));
    },
  });

  const toggleMutation = useMutation({
    mutationFn: (intake: AdmissionIntake) =>
      admissionService.updateIntake(intake.id, {
        status: intake.status === "open" ? "closed" : "open",
      }),
    onSuccess: async (_result, intake) => {
      await queryClient.invalidateQueries({
        queryKey: ["admissions", "intakes"],
      });
      toast.success(intake.status === "open" ? "Intake closed." : "Intake opened.");
    },
    onError: (error) => {
      toast.error(
        getApiErrorMessage(error, "Unable to change intake status.")
      );
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (intake: AdmissionIntake) =>
      admissionService.deleteIntake(intake.id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["admissions", "intakes"],
      });
      toast.success("Intake deleted.");
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error, "Unable to delete this intake."));
    },
  });

  const handleSave = () => {
    setFormError("");

    if (!form.name.trim()) {
      setFormError("Intake name is required.");
      return;
    }
    if (form.programme_id === "") {
      setFormError("Select a programme.");
      return;
    }
    if (form.academic_session_id === "") {
      setFormError("Select an academic session.");
      return;
    }
    if (!form.open_date || !form.close_date) {
      setFormError("Both open and close dates are required.");
      return;
    }
    if (!Number.isFinite(Number(form.seat_capacity)) || Number(form.seat_capacity) < 1) {
      setFormError("Seat capacity must be at least 1.");
      return;
    }

    saveMutation.mutate();
  };

  const columns: DataTableColumn<AdmissionIntake>[] = [
    {
      key: "intake",
      header: "Intake",
      render: (intake) => (
        <div className="admissions-name-cell">
          <div className="admissions-avatar">
            <CalendarCheck2 size={17} />
          </div>
          <div>
            <strong>{intake.name}</strong>
            <span className="admissions-mono">
              {intake.programme?.code} · {intake.session?.code}
            </span>
          </div>
        </div>
      ),
    },
    {
      key: "programme",
      header: "Programme",
      render: (intake) => intake.programme?.name ?? "—",
    },
    {
      key: "dates",
      header: "Window",
      render: (intake) => (
        <span className="admissions-mono">
          {intake.open_date?.slice(0, 10)} → {intake.close_date?.slice(0, 10)}
        </span>
      ),
    },
    {
      key: "applicants",
      header: "Applicants",
      render: (intake) => (
        <span className="admissions-mono">
          {intake.applications_count}
          {intake.applications_count > 0 &&
            ` (${intake.enrolled_count} enrolled)`}
        </span>
      ),
    },
    {
      key: "seats",
      header: "Seats",
      render: (intake) => (
        <span className="admissions-mono" title="Remaining / capacity">
          {intake.seats_remaining}/{intake.seat_capacity}
        </span>
      ),
    },
    {
      key: "status",
      header: "Status",
      render: (intake) =>
        intake.status === "open" ? (
          <Badge tone="success" dot>
            open
          </Badge>
        ) : (
          <Badge tone="warning">closed</Badge>
        ),
    },
    ...(canManage
      ? [
          {
            key: "actions",
            header: "",
            render: (intake: AdmissionIntake) => (
              <span className="admissions-row-actions">
                <button
                  type="button"
                  className="admissions-icon-button"
                  title={intake.status === "open" ? "Close intake" : "Open intake"}
                  aria-label={intake.status === "open" ? "Close intake" : "Open intake"}
                  onClick={() => toggleMutation.mutate(intake)}
                >
                  {intake.status === "open" ? (
                    <DoorClosed size={16} />
                  ) : (
                    <DoorOpen size={16} />
                  )}
                </button>
                <button
                  type="button"
                  className="admissions-icon-button"
                  title="Edit intake"
                  aria-label="Edit intake"
                  onClick={() => openEditModal(intake)}
                >
                  <Pencil size={16} />
                </button>
                <button
                  type="button"
                  className="admissions-icon-button danger"
                  title="Delete intake"
                  aria-label="Delete intake"
                  disabled={intake.applications_count > 0}
                  onClick={() => {
                    if (
                      window.confirm(
                        `Delete intake "${intake.name}"? Only intakes with zero applications can be deleted.`
                      )
                    ) {
                      deleteMutation.mutate(intake);
                    }
                  }}
                >
                  <Trash2 size={16} />
                </button>
              </span>
            ),
          } as DataTableColumn<AdmissionIntake>,
        ]
      : []),
  ];

  const programmes = programmesQuery.data?.programmes ?? EMPTY_LIST;
  const sessions = sessionsQuery.data?.sessions ?? EMPTY_LIST;

  return (
    <>
      {intakesQuery.isError && (
        <Alert tone="error" className="admissions-error">
          Unable to load admission intakes. Please try again.
          <button
            type="button"
            onClick={() => void intakesQuery.refetch()}
          >
            Retry
          </button>
        </Alert>
      )}

      <div className="admissions-toolbar">
        <SearchInput
          icon={Search}
          placeholder="Search intakes..."
          value={searchInput}
          onChange={(event) => setSearchInput(event.target.value)}
          aria-label="Search admission intakes"
        />

        <FilterSelect
          aria-label="Filter by status"
          value={statusFilter}
          onChange={setStatusFilter}
        >
          <option value="all">All Statuses</option>
          <option value="open">Open</option>
          <option value="closed">Closed</option>
        </FilterSelect>

        {canManage && (
          <Button type="button" onClick={openCreateModal}>
            <Plus size={16} />
            Add Intake
          </Button>
        )}
      </div>

      <DataTable<AdmissionIntake>
        columns={columns}
        rows={filteredIntakes}
        rowKey={(intake) => intake.id}
        loading={intakesQuery.isLoading}
        error={
          intakesQuery.isError ? "Admission intakes could not be loaded." : null
        }
        emptyMessage="No intakes yet. Create an admission intake for a programme × session."
      />

      <Modal
        open={modalOpen}
        title={editingIntake ? "Edit Intake" : "Add Admission Intake"}
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
              {saveMutation.isPending ? "Saving..." : "Save intake"}
            </Button>
          </>
        }
      >
        {formError && (
          <div className="admissions-form-alert" role="alert">
            {formError}
          </div>
        )}

        <FormField label="Name" htmlFor="intake-name" required>
          <Input
            id="intake-name"
            value={form.name}
            onChange={(event) =>
              setForm((current) => ({ ...current, name: event.target.value }))
            }
            placeholder="BTech CSE 2026 Admission Intake"
          />
        </FormField>

        <div className="admissions-form-grid">
          <FormField label="Programme" htmlFor="intake-programme" required>
            <select
              id="intake-programme"
              className="filter-select"
              value={form.programme_id}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  programme_id: event.target.value,
                }))
              }
            >
              <option value="">Select programme...</option>
              {programmes.map((programme) => (
                <option key={programme.id} value={programme.id}>
                  {programme.code} — {programme.name}
                </option>
              ))}
            </select>
          </FormField>

          <FormField label="Academic Session" htmlFor="intake-session" required>
            <select
              id="intake-session"
              className="filter-select"
              value={form.academic_session_id}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  academic_session_id: event.target.value,
                }))
              }
            >
              <option value="">Select session...</option>
              {sessions.map((session) => (
                <option key={session.id} value={session.id}>
                  {session.code} — {session.name}
                </option>
              ))}
            </select>
          </FormField>
        </div>

        <div className="admissions-form-grid">
          <FormField label="Opens" htmlFor="intake-open" required>
            <Input
              id="intake-open"
              type="date"
              value={form.open_date}
              onChange={(event) =>
                setForm((current) => ({ ...current, open_date: event.target.value }))
              }
            />
          </FormField>

          <FormField label="Closes" htmlFor="intake-close" required>
            <Input
              id="intake-close"
              type="date"
              value={form.close_date}
              onChange={(event) =>
                setForm((current) => ({ ...current, close_date: event.target.value }))
              }
            />
          </FormField>
        </div>

        <div className="admissions-form-grid">
          <FormField label="Seat Capacity" htmlFor="intake-seats" required>
            <Input
              id="intake-seats"
              type="number"
              min={1}
              max={2000}
              value={form.seat_capacity}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  seat_capacity: Number(event.target.value),
                }))
              }
            />
          </FormField>

          <FormField label="Application Fee (₹)" htmlFor="intake-fee">
            <Input
              id="intake-fee"
              type="number"
              min={0}
              value={form.application_fee}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  application_fee: event.target.value,
                }))
              }
              placeholder="1500"
            />
          </FormField>
        </div>

        <FormField label="Status" htmlFor="intake-status">
          <select
            id="intake-status"
            className="filter-select"
            value={form.status}
            onChange={(event) =>
              setForm((current) => ({
                ...current,
                status: event.target.value as AdmissionIntakeStatus,
              }))
            }
          >
            <option value="open">Open</option>
            <option value="closed">Closed</option>
          </select>
        </FormField>
      </Modal>
    </>
  );
};

export default AdmissionIntakesPage;
