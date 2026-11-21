export interface DashboardSummary {
  total_institutions: number;
  active_institutions: number;
  inactive_institutions: number;
  total_institution_users: number;
  active_institution_users: number;
}

export interface InstitutionTypeStatistic {
  type: string;
  total: number;
}

export interface InstitutionStatusStatistic {
  status: string;
  total: number;
}

export interface InstitutionStatistics {
  by_type: InstitutionTypeStatistic[];
  by_status: InstitutionStatusStatistic[];
}

export interface RecentActivity {
  id: number;
  event: string;
  module: string;
  description: string | null;
  actor_name: string | null;
  created_at: string;
}

export interface DashboardResponse {
  summary: DashboardSummary;
  institution_statistics: InstitutionStatistics;
  recent_activities: RecentActivity[];
}