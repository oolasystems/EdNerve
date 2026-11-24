import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ClipboardList, Plus, Trash2, Search } from "lucide-react";
import { toast } from "sonner";

import { academicService, academicStructureService } from "../api";
import type { ProgrammeCourse, CourseRegistration } from "../types";
import { studentService } from "../../students/api";
import type { StudentProfile } from "../../students/api";
import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin } from "../../../shared/lib/rbac";
import { getApiErrorMessage } from "../../../shared/lib/api-error";
import { DataTable, FilterSelect } from "../../../shared/crud";
import type { DataTableColumn } from "../../../shared/crud";
import { Alert, Badge, Button, FormField, Modal, SearchInput } from "../../../shared/ui";
import "../../institution/institution.css";

type StudentListResponse = {
  data?: StudentProfile[];
  students?: StudentProfile[];
};

type ProgrammeCourseWithSubject = ProgrammeCourse & {
  subject_id?: number;
  subject?: { id?: number; code?: string; name?: string };
};

type ElectiveGroupOption = {
  id: number;
  code: string;
  name: string;
  semester: number;
  required_choices: number;
  subjects?: Array<{ id: number }>;
};

const CourseRegistrationPage = () => {
  const { user } = useAuth();
  const qc = useQueryClient();
  const canManage = isInstitutionAdmin(user);

  const [studentSearch, setStudentSearch] = useState("");
  const [selectedStudentId, setSelectedStudentId] = useState<number | "">("");
  const [selectedSessionId, setSelectedSessionId] = useState<number | "">("");
  const [semester, setSemester] = useState<string>("");

  const sessionsQ = useQuery({ queryKey: ["academic", "sessions"], queryFn: () => academicService.getSessions() });
  const studentsQ = useQuery({ queryKey: ["students", "all", studentSearch], queryFn: () => studentService.getStudents({ q: studentSearch || undefined, per_page: 50 }) });

  const students = useMemo(() => {
    const data = studentsQ.data as StudentListResponse | undefined;
    return data?.data ?? data?.students ?? [];
  }, [studentsQ.data]);

  const selectedStudent = useMemo(() => students.find(s => s.id === Number(selectedStudentId)) ?? null, [students, selectedStudentId]);

  const programmeId = selectedStudent?.programme_id ?? undefined;
  const programmeCoursesQ = useQuery({
    queryKey: ["academic", "programme-courses", programmeId],
    queryFn: () => academicService.getProgrammeCourses(programmeId!),
    enabled: !!programmeId,
  });
  const electiveGroupsQ = useQuery({
    queryKey: ["academic", "elective-groups", programmeId, semester],
    queryFn: () => academicStructureService.getElectiveGroups({ programme_id: programmeId, semester: semester ? Number(semester) : undefined }),
    enabled: !!programmeId,
  });

  const regsQ = useQuery({
    queryKey: ["academic", "course-registrations", selectedStudentId, selectedSessionId, semester],
    queryFn: () => academicService.getCourseRegistrations({
      student_profile_id: selectedStudentId ? Number(selectedStudentId) : undefined,
      academic_session_id: selectedSessionId ? Number(selectedSessionId) : undefined,
      semester: semester ? Number(semester) : undefined,
    }),
    enabled: !!selectedStudentId && !!selectedSessionId,
  });

  const courses = useMemo(() => {
    const all = (programmeCoursesQ.data?.courses ?? []) as ProgrammeCourse[];
    if (!semester) return all;
    return all.filter(c => String(c.semester) === String(semester));
  }, [programmeCoursesQ.data, semester]);
  const registrations = regsQ.data?.registrations ?? [];
  const registeredCourseIds = new Set(registrations.filter(r => r.status === "registered").map(r => r.programme_course_id));
  const electiveGroups = (electiveGroupsQ.data as { groups?: ElectiveGroupOption[] } | undefined)?.groups ?? [];

  const [open, setOpen] = useState(false);
  const [picked, setPicked] = useState<Record<number, { checked: boolean; elective_group_id: string | number }>>({});
  const [err, setErr] = useState("");

  const openRegister = () => {
    const init: Record<number, { checked: boolean; elective_group_id: string | number }> = {};
    courses.forEach(c => {
      if (!registeredCourseIds.has(c.id)) init[c.id] = { checked: false, elective_group_id: "" };
    });
    setPicked(init);
    setErr("");
    setOpen(true);
  };

  const registerMut = useMutation({
    mutationFn: async () => {
      if (!selectedStudentId || !selectedSessionId) throw new Error("Select student and session.");
      const items = Object.entries(picked)
        .filter(([, v]) => v.checked)
        .map(([courseId, v]) => ({
          programme_course_id: Number(courseId),
          elective_group_id: v.elective_group_id === "" ? undefined : Number(v.elective_group_id),
        }));
      if (items.length === 0) throw new Error("Select at least one course.");
      return academicService.registerCourses({
        student_profile_id: Number(selectedStudentId),
        academic_session_id: Number(selectedSessionId),
        semester: semester ? Number(semester) : undefined,
        items,
      });
    },
    onSuccess: async (res) => {
      await qc.invalidateQueries({ queryKey: ["academic", "course-registrations"] });
      const warn = res.errors && res.errors.length ? ` (${res.errors.length} skipped)` : "";
      toast.success((res.message || "Courses registered.") + warn);
      if (res.errors && res.errors.length) toast.message(res.errors.join(" "));
      setOpen(false);
    },
    onError: (e) => setErr(getApiErrorMessage(e, "Unable to register courses.")),
  });

  const dropMut = useMutation({
    mutationFn: (reg: CourseRegistration) => academicService.updateCourseRegistration(reg.id, "dropped"),
    onSuccess: async () => { await qc.invalidateQueries({ queryKey: ["academic", "course-registrations"] }); toast.success("Registration marked dropped."); },
    onError: (e) => toast.error(getApiErrorMessage(e, "Unable to update registration.")),
  });
  const delMut = useMutation({
    mutationFn: (reg: CourseRegistration) => academicService.deleteCourseRegistration(reg.id),
    onSuccess: async () => { await qc.invalidateQueries({ queryKey: ["academic", "course-registrations"] }); toast.success("Registration removed."); },
    onError: (e) => toast.error(getApiErrorMessage(e, "Unable to remove registration.")),
  });

  const findGroupsForCourse = (c: ProgrammeCourse) => {
    if (c.course_type !== "elective") return [];
    const course = c as ProgrammeCourseWithSubject;
    const subjectId = course.subject_id ?? course.subject?.id;
    return electiveGroups.filter((group) =>
      group.semester === c.semester && (group.subjects ?? []).some((subject) => subject.id === subjectId)
    );
  };

  const cols: DataTableColumn<CourseRegistration>[] = [
    { key: "course", header: "Course", render: (r) => <div className="institution-name-cell"><div className="institution-avatar"><ClipboardList size={17} /></div><div><strong>{r.programme_course?.subject?.code ?? ""} — {r.programme_course?.subject?.name ?? "Course"}</strong><span className="institution-mono">Sem {r.semester ?? r.programme_course?.semester ?? "—"} · {r.programme_course?.course_type ?? ""}{r.elective_group ? ` · ${r.elective_group.code}` : ""}</span></div></div> },
    { key: "session", header: "Session", render: (r) => <span>{r.academic_session?.code ?? r.academic_session_id}</span> },
    { key: "status", header: "Status", render: (r) => <Badge tone={r.status === "registered" ? "success" : r.status === "completed" ? "info" : "warning"}>{r.status}</Badge> },
    ...(canManage ? [{ key: "actions", header: "", render: (r: CourseRegistration) => (
      <span className="institution-row-actions">
        {r.status === "registered" && <button type="button" className="institution-icon-button" title="Mark dropped" onClick={() => { if (window.confirm("Mark this registration as dropped?")) dropMut.mutate(r); }}>Drop</button>}
        <button type="button" className="institution-icon-button danger" title="Remove" onClick={() => { if (window.confirm("Remove this registration record?")) delMut.mutate(r); }}><Trash2 size={16} /></button>
      </span>
    )} as DataTableColumn<CourseRegistration>] : []),
  ];

  return (
    <>
      <div className="institution-directory-toolbar" style={{ flexWrap: "wrap", gap: 8 }}>
        <SearchInput icon={Search} placeholder="Search students by name/email..." value={studentSearch} onChange={(e) => setStudentSearch(e.target.value)} aria-label="Search students" />
        <FilterSelect aria-label="Select student" value={selectedStudentId === "" ? "" : String(selectedStudentId)} onChange={(v) => setSelectedStudentId(v ? Number(v) : "")}>
          <option value="">Select student...</option>
          {students.map(s => <option key={s.id} value={String(s.id)}>{s.user?.name ?? `Student #${s.id}`} ({s.user?.email ?? s.application_number ?? s.id})</option>)}
        </FilterSelect>
        <FilterSelect aria-label="Select session" value={selectedSessionId === "" ? "" : String(selectedSessionId)} onChange={(v) => setSelectedSessionId(v ? Number(v) : "")}>
          <option value="">Select session...</option>
          {(sessionsQ.data?.sessions ?? []).map(s => <option key={s.id} value={String(s.id)}>{s.code} — {s.name}</option>)}
        </FilterSelect>
        <FilterSelect aria-label="Semester" value={semester} onChange={setSemester}>
          <option value="">All semesters</option>
          {Array.from({ length: 12 }, (_, i) => i + 1).map(n => <option key={n} value={String(n)}>Semester {n}</option>)}
        </FilterSelect>
        {canManage && <Button type="button" onClick={openRegister} disabled={!selectedStudentId || !selectedSessionId || !programmeId}><Plus size={16} /> Register Courses</Button>}
      </div>

      {!selectedStudentId || !selectedSessionId ? (
        <Alert tone="info">Select a student and an academic session to view and manage course registrations.</Alert>
      ) : !programmeId ? (
        <Alert tone="warning">Selected student has no programme assigned. Assign a programme to the student profile first.</Alert>
      ) : (
        <DataTable<CourseRegistration> columns={cols} rows={registrations} rowKey={(r) => r.id} loading={regsQ.isLoading} error={regsQ.isError ? "Could not load registrations." : null} emptyMessage="No course registrations for this selection yet." />
      )}

      <Modal open={open} title="Register Courses" onClose={() => setOpen(false)}
        footer={<><Button variant="secondary" onClick={() => setOpen(false)}>Cancel</Button><Button onClick={() => { setErr(""); registerMut.mutate(); }} disabled={registerMut.isPending}>{registerMut.isPending ? "Registering..." : "Register selected"}</Button></>}>
        {err && <div className="institution-form-alert" role="alert">{err}</div>}
        <p className="institution-muted" style={{ marginBottom: 12 }}>Select programme courses to register for <strong>{selectedStudent?.user?.name ?? "student"}</strong> in the chosen session. Elective choices are enforced against elective group rules and capacity.</p>
        {courses.filter(c => !registeredCourseIds.has(c.id)).length === 0 ? (
          <Alert tone="info">No unregistered courses found for this programme/semester selection.</Alert>
        ) : (
          <div style={{ display: "grid", gap: 8, maxHeight: 420, overflow: "auto" }}>
            {courses.filter(c => !registeredCourseIds.has(c.id)).map(c => {
              const groups = findGroupsForCourse(c);
              const row = picked[c.id] ?? { checked: false, elective_group_id: "" };
              return (
                <label key={c.id} style={{ display: "grid", gridTemplateColumns: "auto 1fr", gap: 10, alignItems: "start", padding: "8px 10px", border: "1px solid var(--border-color, #333)", borderRadius: 8 }}>
                  <input type="checkbox" checked={row.checked} onChange={(e) => setPicked(p => ({ ...p, [c.id]: { ...row, checked: e.target.checked } }))} style={{ marginTop: 4 }} />
                  <div>
                    <div><strong>{(c as ProgrammeCourseWithSubject).subject?.code ?? ""} {(c as ProgrammeCourseWithSubject).subject?.name ?? `Course #${c.id}`}</strong> <Badge tone={c.course_type === "elective" ? "info" : "neutral"}>{c.course_type}</Badge> <span className="institution-mono">Sem {c.semester}</span></div>
                    {c.course_type === "elective" && groups.length > 0 && (
                      <FormField label="Elective group" htmlFor={`eg-${c.id}`}>
                        <select id={`eg-${c.id}`} className="filter-select" value={row.elective_group_id} onChange={(e) => setPicked(p => ({ ...p, [c.id]: { ...row, elective_group_id: e.target.value } }))}>
                          <option value="">{groups.length === 1 ? `${groups[0].code} (auto)` : "Select group..."}</option>
                          {groups.map((g) => <option key={g.id} value={g.id}>{g.code} — {g.name} (need {g.required_choices})</option>)}
                        </select>
                      </FormField>
                    )}
                    {c.course_type === "elective" && groups.length === 0 && <div className="institution-muted">No elective group found for this subject in this programme/semester (backend will reject if not valid).</div>}
                  </div>
                </label>
              );
            })}
          </div>
        )}
      </Modal>
    </>
  );
};

export default CourseRegistrationPage;
