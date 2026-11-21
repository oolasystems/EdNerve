/**
 * Frontend RBAC helpers (defense-in-depth only).
 *
 * The backend (Laravel policies / middleware) is the source of truth for
 * authorization and institution isolation. These helpers only decide which
 * navigation entries and actions the UI exposes.
 *
 * Permission subjects:
 * - platform accounts keep the legacy behaviour (permissions undefined,
 *   backend enforces; the audit catalogue is not surfaced client-side).
 * - institution accounts derive a *client-side* permission set from their
 *   role assignments. Only the `institution_admin` role is mapped today
 *   (mirrors PermissionSeeder::institutionAdminGrants); everyone else is
 *   limited to public items. Backend re-checks every request.
 */

import type { AuthUser } from "../types/auth";
import type { AccountType } from "../../config/navigation/navigation.types";

export interface RbacSubject {
  accountType: AccountType | null;
  /** Optional permission codes; reserved for when the backend catalogue lands. */
  permissions?: string[];
}

/** Mirrors the institution-admin grants seeded in PermissionSeeder (module=institution + invite-only/users). */
export const INSTITUTION_ADMIN_GRANTS: readonly string[] = [
  "institution.settings.view",
  "institution.settings.manage",
  "academic_units.view",
  "academic_units.manage",
  "institution_users.view",
  "institution_users.manage",
  "role_assignments.view",
  "role_assignments.manage",
  "roles.view",
  "academic.sessions.view",
  "academic.sessions.manage",
  "academic.programmes.view",
  "academic.programmes.manage",
  "academic.subjects.view",
  "academic.subjects.manage",
  "academic.syllabi.view",
  "academic.syllabi.manage",
  "academic.elective_groups.view",
  "academic.elective_groups.manage",
  "admissions.intakes.view",
  "admissions.intakes.manage",
  "admissions.applications.view",
  "admissions.applications.review",
  "admissions.applications.enroll",
  "students.view",
  "students.manage",
  "finance.view",
  "finance.manage",
  "finance.record_payments",
  "timetable.view",
  "timetable.manage",
  "attendance.view",
  "attendance.mark",
  "examinations.view",
  "examinations.manage",
  "examinations.record_marks",
  "examinations.publish",
  "question_bank.view",
  "question_bank.manage",
];

/** Mirrors the faculty staff grants (faculty / hod / dean): view timetable + mark own classes. */
export const INSTITUTION_FACULTY_GRANTS: readonly string[] = [
  "timetable.view",
  "attendance.view",
  "attendance.mark",
  "examinations.view",
];

/** Mirrors the registrar grants: read-only visibility. */
export const INSTITUTION_REGISTRAR_GRANTS: readonly string[] = [
  "timetable.view",
  "attendance.view",
  "examinations.view",
];

/** Mirrors the examination-officer grants: full examination lifecycle. */
export const INSTITUTION_EXAM_OFFICER_GRANTS: readonly string[] = [
  "examinations.view",
  "examinations.manage",
  "examinations.record_marks",
  "examinations.publish",
  "question_bank.view",
  "question_bank.manage",
];

/** Role codes used by this client. */
export const INSTITUTION_ADMIN_ROLE = "institution_admin";
export const INSTITUTION_FACULTY_ROLES = ["faculty", "hod", "dean"] as const;
export const INSTITUTION_REGISTRAR_ROLE = "registrar";
export const INSTITUTION_EXAM_OFFICER_ROLE = "examination_officer";

export function isInstitutionAdminRoleCode(code: string | null | undefined): boolean {
  return code === INSTITUTION_ADMIN_ROLE;
}

export function hasInstitutionRole(
  user: AuthUser | null,
  roleCode: string
): boolean {
  if (!user?.active_role_assignments) return false;
  return user.active_role_assignments.some(
    (assignment) =>
      assignment.role?.code === roleCode &&
      (assignment.status === undefined || assignment.status === "active")
  );
}

export function isInstitutionAdmin(user: AuthUser | null): boolean {
  return Boolean(
    user &&
      user.account_type === "institution" &&
      user.institution_id !== null &&
      hasInstitutionRole(user, INSTITUTION_ADMIN_ROLE)
  );
}

/** True for faculty-capacity staff (faculty / HOD / dean). */
export function isFacultyLike(user: AuthUser | null): boolean {
  return Boolean(
    user &&
      user.account_type === "institution" &&
      user.institution_id !== null &&
      INSTITUTION_FACULTY_ROLES.some((code) => hasInstitutionRole(user, code))
  );
}

/** True for examination officers (the examination lifecycle role). */
export function isExamOfficer(user: AuthUser | null): boolean {
  return Boolean(
    user &&
      user.account_type === "institution" &&
      user.institution_id !== null &&
      hasInstitutionRole(user, INSTITUTION_EXAM_OFFICER_ROLE)
  );
}

/** True when the user is an institution member (any role). Backend gates activation. */
export function isInstitutionMember(user: AuthUser | null): boolean {
  return Boolean(
    user && user.account_type === "institution" && user.institution_id !== null
  );
}

function institutionGrants(user: AuthUser): string[] {
  if (!user.active_role_assignments) {
    return [];
  }
  const roleCodes = new Set(
    user.active_role_assignments
      .filter((a) => a.status === undefined || a.status === "active")
      .map((a) => a.role?.code)
      .filter((c): c is string => Boolean(c))
  );

  const grants = new Set<string>();
  if (roleCodes.has(INSTITUTION_ADMIN_ROLE)) {
    INSTITUTION_ADMIN_GRANTS.forEach((g) => grants.add(g));
  }
  if ([...roleCodes].some((code) => (INSTITUTION_FACULTY_ROLES as readonly string[]).includes(code))) {
    INSTITUTION_FACULTY_GRANTS.forEach((g) => grants.add(g));
  }
  if (roleCodes.has(INSTITUTION_REGISTRAR_ROLE)) {
    INSTITUTION_REGISTRAR_GRANTS.forEach((g) => grants.add(g));
  }
  if (roleCodes.has(INSTITUTION_EXAM_OFFICER_ROLE)) {
    INSTITUTION_EXAM_OFFICER_GRANTS.forEach((g) => grants.add(g));
  }
  return [...grants];
}

/** Platform console = platform-admin workspace. */
export function isPlatformAdmin(user: AuthUser | null): boolean {
  return Boolean(
    user && user.account_type === "platform" && user.institution_id === null
  );
}

export function toRbacSubject(user: AuthUser | null): RbacSubject {
  if (!user) {
    return { accountType: null, permissions: undefined };
  }

  if (user.account_type === "institution") {
    return {
      accountType: "institution",
      permissions: institutionGrants(user),
    };
  }

  // Platform: catalogue is not surfaced client-side; backend enforces authorization.
  return { accountType: user.account_type, permissions: undefined };
}

export function hasAccountType(
  subject: RbacSubject,
  allowed: AccountType[]
): boolean {
  return Boolean(subject.accountType && allowed.includes(subject.accountType));
}

export function hasPermissions(
  subject: RbacSubject,
  required: string[] | undefined
): boolean {
  if (!required || required.length === 0) return true;
  if (!subject.permissions) return true; // catalogue not wired yet — backend enforces
  return required.every((permission) => subject.permissions!.includes(permission));
}

/** Can this subject see this nav item/area? */
export function canAccess(
  subject: RbacSubject,
  allowedAccountTypes: AccountType[],
  permissions?: string[]
): boolean {
  return (
    hasAccountType(subject, allowedAccountTypes) &&
    hasPermissions(subject, permissions)
  );
}
