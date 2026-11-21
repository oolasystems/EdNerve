/** Audit log & system health types. */

export interface AuditLogActor {
  id: number;
  name: string;
  email: string;
}

export interface AuditLogInstitution {
  id: number;
  code: string;
  name: string;
}

export interface AuditLogEntry {
  id: number;
  institution_id: number | null;
  actor_user_id: number | null;
  event: string;
  module: string;
  actor_type: string;
  subject_type: string | null;
  subject_id: string | null;
  description: string | null;
  old_values: unknown;
  new_values: unknown;
  ip_address: string | null;
  request_id: string | null;
  user_agent: string | null;
  created_at: string;
  actor: AuditLogActor | null;
  institution: AuditLogInstitution | null;
}

export interface AuditLogListParams {
  page?: number;
  per_page?: number;
  q?: string;
  module?: string;
  event?: string;
  institution_id?: number;
  actor_user_id?: number;
  date_from?: string;
  date_to?: string;
}

export type HealthStatus = "ok" | "warning" | "critical";

export interface HealthCheck {
  key: string;
  label: string;
  status: HealthStatus;
  message: string;
}

export interface SystemHealth {
  status: HealthStatus;
  checked_at: string;
  checks: HealthCheck[];
  info: {
    app_name: string;
    environment: string;
    debug: boolean;
    laravel_version: string;
    php_version: string;
    db_connection: string;
    app_url: string;
    timezone: string;
    server_time: string;
  };
}
