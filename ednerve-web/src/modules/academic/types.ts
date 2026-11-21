// =====================================================
// Academic Core — shared contracts
// =====================================================

export type AcademicStatus = "active" | "inactive";
export type SubjectType = "theory" | "practical";
export type ProgrammeLevel = "UG" | "PG";
export type CourseType = "core" | "elective";

export interface AcademicSession {
  id: number;
  institution_id: number;
  code: string;
  name: string;
  start_date: string;
  end_date: string;
  is_active: boolean;
  status: AcademicStatus;
  created_at?: string;
  updated_at?: string;
}

export interface CreateAcademicSessionPayload {
  code: string;
  name: string;
  start_date: string;
  end_date: string;
  is_active?: boolean;
  status?: AcademicStatus;
}

export interface UpdateAcademicSessionPayload {
  code?: string;
  name?: string;
  start_date?: string;
  end_date?: string;
  is_active?: boolean;
  status?: AcademicStatus;
}

export interface AcademicSessionsResponse {
  sessions: AcademicSession[];
}

export interface Subject {
  id: number;
  institution_id: number;
  code: string;
  name: string;
  credits: number;
  subject_type: SubjectType;
  status: AcademicStatus;
  created_at?: string;
  updated_at?: string;
}

export interface CreateSubjectPayload {
  code: string;
  name: string;
  credits?: number;
  subject_type?: SubjectType;
  status?: AcademicStatus;
}

export interface UpdateSubjectPayload {
  code?: string;
  name?: string;
  credits?: number;
  subject_type?: SubjectType;
  status?: AcademicStatus;
}

export interface SubjectsResponse {
  subjects: Subject[];
}

export interface Programme {
  id: number;
  institution_id: number;
  code: string;
  name: string;
  level: ProgrammeLevel;
  duration_semesters: number;
  status: AcademicStatus;
  created_at?: string;
  updated_at?: string;
}

export interface CreateProgrammePayload {
  code: string;
  name: string;
  level: ProgrammeLevel;
  duration_semesters: number;
  status?: AcademicStatus;
}

export interface UpdateProgrammePayload {
  code?: string;
  name?: string;
  level?: ProgrammeLevel;
  duration_semesters?: number;
  status?: AcademicStatus;
}

export interface ProgrammesResponse {
  programmes: Programme[];
}

export interface ProgrammeCourse {
  id: number;
  semester: number;
  course_type: CourseType;
  position: number;
  status: AcademicStatus;
  subject: Subject | null;
}

export interface ProgrammeCoursesResponse {
  programme: Programme;
  courses: ProgrammeCourse[];
}

export interface CreateProgrammeCoursePayload {
  programme_id: number;
  subject_id: number;
  semester: number;
  course_type?: CourseType;
  position?: number;
  status?: AcademicStatus;
}

export interface UpdateProgrammeCoursePayload {
  semester?: number;
  course_type?: CourseType;
  position?: number;
  status?: AcademicStatus;
}

// =====================================================
// Course structure extensions + Syllabus (Phase A)
// =====================================================

export type SubjectComponentKind = "theory" | "practical" | "tutorial";

export interface SubjectComponent {
  id: number;
  institution_id: number;
  subject_id: number;
  kind: SubjectComponentKind;
  credits: number;
  hours_per_week: number;
  max_marks: number | null;
  position: number;
  status: AcademicStatus;
  created_at?: string;
  updated_at?: string;
}

export interface UpsertSubjectComponentPayload {
  kind: SubjectComponentKind;
  credits?: number;
  hours_per_week?: number;
  max_marks?: number | null;
  position?: number;
  status?: AcademicStatus;
}

export interface SubjectComponentsResponse {
  components: SubjectComponent[];
}

export interface SubjectPrerequisite {
  id: number;
  prerequisite: Pick<Subject, "id" | "code" | "name" | "credits">;
}

export interface SubjectPrerequisitesResponse {
  prerequisites: SubjectPrerequisite[];
}

export interface ElectiveGroup {
  id: number;
  institution_id: number;
  programme_id: number;
  semester: number;
  code: string;
  name: string;
  capacity: number | null;
  required_choices: number;
  status: AcademicStatus;
  programme: Pick<Programme, "id" | "code" | "name" | "level"> | null;
  subjects: Pick<Subject, "id" | "code" | "name" | "credits">[];
  created_at?: string;
  updated_at?: string;
}

export interface ElectiveGroupsResponse {
  groups: ElectiveGroup[];
}

export interface CreateElectiveGroupPayload {
  programme_id: number;
  semester: number;
  code: string;
  name: string;
  capacity?: number | null;
  required_choices?: number;
}

export interface UpdateElectiveGroupPayload {
  name?: string;
  capacity?: number | null;
  required_choices?: number;
  status?: AcademicStatus;
}

export type SyllabusStatus =
  | "draft"
  | "in_review"
  | "approved"
  | "published"
  | "archived";

export interface SyllabusUnit {
  id?: number;
  position: number;
  unit_number: string;
  title: string;
  topics?: string | null;
  learning_outcomes?: string | null;
  prescribed_hours: number;
}

export interface Syllabus {
  id: number;
  institution_id: number;
  subject_id: number;
  academic_session_id: number | null;
  version: string;
  title: string;
  status: SyllabusStatus;
  author_id: number;
  approved_by: number | null;
  approved_at: string | null;
  published_by: number | null;
  published_at: string | null;
  superseded_by: number | null;
  subject: Pick<Subject, "id" | "code" | "name" | "credits"> | null;
  academic_session: Pick<AcademicSession, "id" | "code" | "name"> | null;
  author: { id: number; name: string } | null;
  approver: { id: number; name: string } | null;
  units: SyllabusUnit[];
  created_at?: string;
  updated_at?: string;
}

export interface SyllabiResponse {
  syllabi: Syllabus[];
}

export interface SyllabusResponse {
  syllabus: Syllabus;
}

export interface CreateSyllabusPayload {
  subject_id: number;
  academic_session_id?: number | null;
  version: string;
  title: string;
  units?: SyllabusUnit[];
}

export interface UpdateSyllabusPayload {
  academic_session_id?: number | null;
  version?: string;
  title?: string;
  units?: SyllabusUnit[];
}

// =====================================================
// Academic Calendar
// =====================================================

export type AcademicCalendarEventType =
  | "holiday"
  | "exam"
  | "registration"
  | "fee_deadline"
  | "class"
  | "other";

export interface AcademicCalendarEvent {
  id: number;
  institution_id: number;
  academic_session_id: number | null;
  title: string;
  event_type: AcademicCalendarEventType;
  starts_on: string;
  ends_on: string | null;
  is_all_day: boolean;
  description: string | null;
  status: AcademicStatus;
  academic_session?: Pick<AcademicSession, "id" | "code" | "name"> | null;
  created_at?: string;
  updated_at?: string;
}

export interface AcademicCalendarEventsResponse {
  events: AcademicCalendarEvent[];
}

export interface AcademicCalendarEventResponse {
  event: AcademicCalendarEvent;
  message?: string;
}

export interface CreateAcademicCalendarEventPayload {
  academic_session_id?: number | null;
  title: string;
  event_type: AcademicCalendarEventType;
  starts_on: string;
  ends_on?: string | null;
  is_all_day?: boolean;
  description?: string | null;
  status?: AcademicStatus;
}

export interface UpdateAcademicCalendarEventPayload {
  academic_session_id?: number | null;
  title?: string;
  event_type?: AcademicCalendarEventType;
  starts_on?: string;
  ends_on?: string | null;
  is_all_day?: boolean;
  description?: string | null;
  status?: AcademicStatus;
}

// =====================================================
// Faculty Workload & Lesson Plans (lite)
// =====================================================

export interface FacultyWorkloadEntry {
  id: number;
  institution_id: number;
  user_id: number;
  academic_session_id: number | null;
  subject_id: number | null;
  programme_id: number | null;
  semester: number | null;
  section: string | null;
  periods_per_week: number;
  teaching_hours_per_week: string | number | null;
  role: string | null;
  notes: string | null;
  status: AcademicStatus;
  user?: { id: number; name: string; email: string } | null;
  academic_session?: Pick<AcademicSession, "id" | "code" | "name"> | null;
  subject?: Pick<Subject, "id" | "code" | "name"> | null;
  programme?: Pick<Programme, "id" | "code" | "name"> | null;
}

export interface FacultyWorkloadResponse { entries: FacultyWorkloadEntry[]; entry?: FacultyWorkloadEntry; message?: string; }
export interface CreateFacultyWorkloadPayload {
  user_id: number; academic_session_id?: number | null; subject_id?: number | null; programme_id?: number | null;
  semester?: number | null; section?: string | null; periods_per_week?: number; teaching_hours_per_week?: number | null;
  role?: string | null; notes?: string | null; status?: AcademicStatus;
}
export type UpdateFacultyWorkloadPayload = Partial<CreateFacultyWorkloadPayload>;

export type LessonPlanStatus = "planned" | "in_progress" | "completed" | "deferred";

export interface LessonPlanEntry {
  id: number;
  institution_id: number;
  subject_id: number;
  academic_session_id: number | null;
  syllabus_id: number | null;
  faculty_user_id: number | null;
  programme_id: number | null;
  semester: number | null;
  section: string | null;
  unit_number: string | null;
  topic: string;
  planned_hours: string | number;
  taught_hours: string | number;
  planned_date: string | null;
  taught_date: string | null;
  status: LessonPlanStatus;
  remarks: string | null;
  subject?: Pick<Subject, "id" | "code" | "name"> | null;
  academic_session?: Pick<AcademicSession, "id" | "code" | "name"> | null;
  faculty?: { id: number; name: string; email: string } | null;
  programme?: Pick<Programme, "id" | "code" | "name"> | null;
  syllabus?: { id: number; version: string; title: string } | null;
}

export interface LessonPlanResponse { entries: LessonPlanEntry[]; entry?: LessonPlanEntry; message?: string; }
export interface CreateLessonPlanPayload {
  subject_id: number; academic_session_id?: number | null; syllabus_id?: number | null; faculty_user_id?: number | null;
  programme_id?: number | null; semester?: number | null; section?: string | null; unit_number?: string | null;
  topic: string; planned_hours?: number; taught_hours?: number; planned_date?: string | null; taught_date?: string | null;
  status?: LessonPlanStatus; remarks?: string | null;
}
export type UpdateLessonPlanPayload = Partial<CreateLessonPlanPayload>;

// =====================================================
// Academic Sections / Batches (first-class entity)
// =====================================================

export interface AcademicSection {
  id: number;
  institution_id: number;
  academic_session_id: number | null;
  programme_id: number | null;
  semester: number | null;
  code: string;
  name: string;
  capacity: number | null;
  status: AcademicStatus;
  academic_session?: Pick<AcademicSession, "id" | "code" | "name"> | null;
  programme?: Pick<Programme, "id" | "code" | "name" | "level"> | null;
  created_at?: string;
  updated_at?: string;
}

export interface AcademicSectionsResponse { sections: AcademicSection[]; section?: AcademicSection; message?: string; }
export interface CreateAcademicSectionPayload {
  academic_session_id?: number | null; programme_id?: number | null; semester?: number | null;
  code: string; name: string; capacity?: number | null; status?: AcademicStatus;
}
export type UpdateAcademicSectionPayload = Partial<CreateAcademicSectionPayload>;

// =====================================================
// Course Registration
// =====================================================

export type CourseRegistrationStatus = "registered" | "dropped" | "completed";

export interface CourseRegistration {
  id: number;
  institution_id: number;
  student_profile_id: number;
  academic_session_id: number;
  programme_course_id: number;
  elective_group_id: number | null;
  semester: number | null;
  status: CourseRegistrationStatus;
  registered_at: string | null;
  student_profile?: {
    id: number; user_id: number; programme_id: number | null; enrollment_status: string;
    user?: { id: number; name: string; email: string } | null;
    programme?: Pick<Programme, "id" | "code" | "name"> | null;
  } | null;
  academic_session?: Pick<AcademicSession, "id" | "code" | "name"> | null;
  programme_course?: {
    id: number; programme_id: number; subject_id: number; semester: number; course_type: CourseType;
    subject?: Pick<Subject, "id" | "code" | "name" | "credits"> | null;
    programme?: Pick<Programme, "id" | "code" | "name"> | null;
  } | null;
  elective_group?: { id: number; code: string; name: string; required_choices?: number; capacity?: number | null } | null;
}

export interface CourseRegistrationsResponse { registrations: CourseRegistration[]; registration?: CourseRegistration; message?: string; errors?: string[]; }
export interface RegisterCoursesPayload {
  student_profile_id: number;
  academic_session_id: number;
  semester?: number | null;
  items: Array<{ programme_course_id: number; elective_group_id?: number | null }>;
}
