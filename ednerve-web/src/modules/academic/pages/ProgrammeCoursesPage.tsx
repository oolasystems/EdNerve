import { ArrowLeft, BookOpenCheck, Plus, Trash2 } from "lucide-react";
import { useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { useQuery, useQueryClient, useMutation } from "@tanstack/react-query";
import { toast } from "sonner";

import { academicService } from "../api";
import "../../institution/institution.css";

import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin } from "../../../shared/lib/rbac";
import { getApiErrorMessage } from "../../../shared/lib/api-error";

import {
  Alert,
  Badge,
  Button,
  FormField,
  Modal,
  Spinner,
} from "../../../shared/ui";

const EMPTY_LIST: never[] = [];

const ProgrammeCoursesPage = () => {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const canManage = isInstitutionAdmin(user);
  const { programmeId } = useParams<{ programmeId: string }>();
  const id = Number(programmeId);

  const programmeQuery = useQuery({
    queryKey: ["academic", "programme", id],
    queryFn: () => academicService.getProgrammeCourses(id),
    enabled: Number.isFinite(id) && id > 0,
  });

  const subjectsQuery = useQuery({
    queryKey: ["academic", "subjects"],
    queryFn: () => academicService.getSubjects(),
  });

  const mappedCourses = programmeQuery.data?.courses ?? EMPTY_LIST;
  const allSubjects = subjectsQuery.data?.subjects ?? EMPTY_LIST;

  const mappedSubjectIds = useMemo(
    () => new Set(mappedCourses.map((course) => course.subject?.id)),
    [mappedCourses]
  );

  const subjectsBySemester = useMemo(
    () =>
      mappedCourses.reduce<Record<number, typeof mappedCourses>>((acc, course) => {
        (acc[course.semester] ||= []).push(course);
        return acc;
      }, {}),
    [mappedCourses]
  );

  const [modalOpen, setModalOpen] = useState(false);
  const [form, setForm] = useState({
    subject_id: "" as string | number,
    semester: 1,
    course_type: "core" as "core" | "elective",
  });
  const [formError, setFormError] = useState("");

  const openAddModal = () => {
    setForm({ subject_id: "", semester: 1, course_type: "core" });
    setFormError("");
    setModalOpen(true);
  };

  const mapMutation = useMutation({
    mutationFn: () =>
      academicService.mapProgrammeCourse({
        programme_id: id,
        subject_id: Number(form.subject_id),
        semester: Number(form.semester),
        course_type: form.course_type,
      }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["academic", "programme", id],
      });
      toast.success("Subject mapped to programme.");
      setModalOpen(false);
    },
    onError: (error) => {
      setFormError(getApiErrorMessage(error, "Unable to map this subject."));
    },
  });

  const removeMutation = useMutation({
    mutationFn: (courseId: number) =>
      academicService.removeProgrammeCourse(courseId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["academic", "programme", id],
      });
      toast.success("Subject removed from programme.");
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error, "Unable to remove this mapping."));
    },
  });

  const handleMap = () => {
    setFormError("");

    if (form.subject_id === "") {
      setFormError("Select a subject.");
      return;
    }
    if (!Number.isFinite(Number(form.semester)) || Number(form.semester) < 1) {
      setFormError("Semester must be at least 1.");
      return;
    }

    mapMutation.mutate();
  };

  const handleRemove = (courseId: number, subjectName: string) => {
    if (window.confirm(`Remove "${subjectName}" from this programme?`)) {
      removeMutation.mutate(courseId);
    }
  };

  if (programmeQuery.isLoading) {
    return (
      <div className="dashboard-state">
        <Spinner size={20} />
        <p>Loading programme curriculum...</p>
      </div>
    );
  }

  if (programmeQuery.isError || !programmeQuery.data) {
    return (
      <div className="dashboard-state">
        <Alert tone="error">
          Unable to load the programme curriculum. Please try again.
        </Alert>
        <button
          type="button"
          className="workspace-action-button"
          onClick={() => void programmeQuery.refetch()}
        >
          Retry
        </button>
      </div>
    );
  }

  const programme = programmeQuery.data.programme;

  return (
    <>
      <div className="institution-directory-toolbar">
        <Button type="button" variant="secondary" onClick={() => navigate(-1)}>
          <ArrowLeft size={16} />
          Back
        </Button>

        <div className="institution-name-cell" style={{ marginLeft: "var(--space-4)" }}>
          <div className="institution-avatar">
            <BookOpenCheck size={17} />
          </div>
          <div>
            <strong>{programme.name}</strong>
            <span className="institution-mono">
              {programme.code} · {programme.level} · {programme.duration_semesters} semesters
            </span>
          </div>
        </div>

        {canManage && (
          <div style={{ marginLeft: "auto" }}>
            <Button type="button" onClick={openAddModal}>
              <Plus size={16} />
              Add Subject
            </Button>
          </div>
        )}
      </div>

      {mappedCourses.length === 0 ? (
        <div className="institution-admin-panel" style={{ marginTop: "var(--space-5)" }}>
          <p style={{ color: "var(--text-secondary)", margin: 0 }}>
            No subjects mapped yet. Add subjects semester-wise to build the
            curriculum for this programme.
          </p>
        </div>
      ) : (
        Object.keys(subjectsBySemester)
          .map(Number)
          .sort((a, b) => a - b)
          .map((semester) => (
            <section
              key={semester}
              className="institution-admin-panel"
              style={{ marginTop: "var(--space-5)" }}
            >
              <h3>Semester {semester}</h3>

              <div style={{ display: "flex", flexDirection: "column", gap: "var(--space-2)" }}>
                {subjectsBySemester[semester].map((course) => (
                  <div
                    key={course.id}
                    style={{
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "space-between",
                      gap: "var(--space-4)",
                      padding: "var(--space-2) var(--space-3)",
                      border: "1px solid var(--border)",
                      borderRadius: "var(--radius-md)",
                      background: "var(--surface-secondary)",
                    }}
                  >
                    <div>
                      <strong>{course.subject?.name ?? "Unknown subject"}</strong>
                      <div className="institution-mono" style={{ marginTop: 2 }}>
                        {course.subject?.code} · {course.subject?.credits} credits · {course.subject?.subject_type}
                      </div>
                    </div>
                    <div style={{ display: "flex", alignItems: "center", gap: "var(--space-3)" }}>
                      <Badge tone={course.course_type === "core" ? "info" : "warning"}>
                        {course.course_type}
                      </Badge>
                      {canManage && (
                        <button
                          type="button"
                          className="institution-icon-button danger"
                          title="Remove from programme"
                          onClick={() =>
                            handleRemove(
                              course.id,
                              course.subject?.name ?? "Subject"
                            )
                          }
                        >
                          <Trash2 size={16} />
                        </button>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            </section>
          ))
      )}

      <Modal
        open={modalOpen}
        title="Add Subject to Programme"
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
              onClick={handleMap}
              disabled={mapMutation.isPending}
            >
              {mapMutation.isPending ? "Adding..." : "Add subject"}
            </Button>
          </>
        }
      >
        {formError && (
          <div className="institution-form-alert" role="alert">
            {formError}
          </div>
        )}

        <FormField label="Subject" htmlFor="map-subject" required>
          <select
            id="map-subject"
            className="filter-select"
            value={form.subject_id}
            onChange={(event) =>
              setForm((current) => ({ ...current, subject_id: event.target.value }))
            }
          >
            <option value="">Select subject...</option>
            {allSubjects
              .filter((subject) => !mappedSubjectIds.has(subject.id))
              .map((subject) => (
                <option key={subject.id} value={subject.id}>
                  {subject.code} — {subject.name} ({subject.credits} credits)
                </option>
              ))}
          </select>
        </FormField>

        <div className="institution-form-grid">
          <FormField label="Semester" htmlFor="map-semester">
            <input
              id="map-semester"
              type="number"
              min={1}
              max={20}
              className="filter-select"
              value={form.semester}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  semester: Number(event.target.value),
                }))
              }
            />
          </FormField>

          <FormField label="Type" htmlFor="map-type">
            <select
              id="map-type"
              className="filter-select"
              value={form.course_type}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  course_type: event.target.value as "core" | "elective",
                }))
              }
            >
              <option value="core">Core</option>
              <option value="elective">Elective</option>
            </select>
          </FormField>
        </div>
      </Modal>
    </>
  );
};

export default ProgrammeCoursesPage;
