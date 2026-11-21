import {
  Building2,
  CheckCircle2,
  Users,
  UserCheck,
  University,
  RefreshCw,
  Activity,
} from "lucide-react";
import { useQuery } from "@tanstack/react-query";
import "./dashboard.css";

import { dashboardService } from "./api";

import { Alert, Spinner, StatCard } from "../../shared/ui";

import type { DashboardResponse } from "./types";

function PlatformDashboard() {
  const {
    data,
    isLoading,
    isFetching,
    error,
    refetch,
  } = useQuery<DashboardResponse>({
    queryKey: ["platform", "dashboard"],
    queryFn: () => dashboardService.getDashboard(),
  });

  const loadDashboard = () => void refetch();

  if (isLoading) {
    return (
      <div className="dashboard-state">
        <Spinner size={22} />
        <p>Loading platform dashboard...</p>
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="dashboard-state">
        <Alert tone="error">
          Unable to load dashboard information. Please try again.
        </Alert>
        <button
          type="button"
          className="btn btn-primary"
          onClick={loadDashboard}
        >
          <RefreshCw size={16} />
          Retry
        </button>
      </div>
    );
  }

  const summary = data.summary;

  const typeTotal = data.institution_statistics.by_type.reduce(
    (total, item) => total + item.total,
    0
  );

  const statusTotal = data.institution_statistics.by_status.reduce(
    (total, item) => total + item.total,
    0
  );

  return (
    <div className="dashboard-container">
      <div className="dashboard-toolbar">
        <div>
          <p className="dashboard-updated-label">
            Platform overview
          </p>
        </div>

        <button
          type="button"
          className="btn btn-secondary"
          onClick={loadDashboard}
          disabled={isFetching}
        >
          <RefreshCw size={15} className={isFetching ? "spinner" : ""} />
          Refresh
        </button>
      </div>

      <div className="dashboard-metrics-grid">
        <StatCard
          title="Total Institutions"
          value={summary.total_institutions}
          description="Registered institutions"
          icon={<University size={22} />}
          iconTone="blue"
        />
        <StatCard
          title="Active Institutions"
          value={summary.active_institutions}
          description="Currently active"
          icon={<CheckCircle2 size={22} />}
          iconTone="green"
        />
        <StatCard
          title="Inactive Institutions"
          value={summary.inactive_institutions}
          description="Currently inactive"
          icon={<Building2 size={22} />}
          iconTone="orange"
        />
        <StatCard
          title="Total Institution Users"
          value={summary.total_institution_users}
          description="Users across institutions"
          icon={<Users size={22} />}
          iconTone="purple"
        />
        <StatCard
          title="Active Institution Users"
          value={summary.active_institution_users}
          description="Currently active users"
          icon={<UserCheck size={22} />}
          iconTone="teal"
        />
      </div>

      <div className="dashboard-statistics-grid">
        <section className="page-card dashboard-panel">
          <div className="dashboard-panel-heading">
            <div>
              <h3>Institutions by Type</h3>
              <p>Distribution across institution categories</p>
            </div>
            <University size={20} />
          </div>

          {data.institution_statistics.by_type.length === 0 ? (
            <p className="dashboard-empty-text">
              No institution type statistics available.
            </p>
          ) : (
            <div className="dashboard-stat-list">
              {data.institution_statistics.by_type.map((item) => (
                <div className="dashboard-stat-item" key={item.type}>
                  <div className="dashboard-stat-label">
                    <span>{item.type.replaceAll("_", " ")}</span>
                    <strong>{item.total}</strong>
                  </div>

                  <div className="dashboard-progress-track">
                    <div
                      className="dashboard-progress-fill blue"
                      style={{
                        width: `${
                          typeTotal > 0
                            ? (item.total / typeTotal) * 100
                            : 0
                        }%`,
                      }}
                    />
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>

        <section className="page-card dashboard-panel">
          <div className="dashboard-panel-heading">
            <div>
              <h3>Institutions by Status</h3>
              <p>Current institution status overview</p>
            </div>
            <Activity size={20} />
          </div>

          {data.institution_statistics.by_status.length === 0 ? (
            <p className="dashboard-empty-text">
              No institution status statistics available.
            </p>
          ) : (
            <div className="dashboard-stat-list">
              {data.institution_statistics.by_status.map((item) => (
                <div className="dashboard-stat-item" key={item.status}>
                  <div className="dashboard-stat-label">
                    <span>{item.status}</span>
                    <strong>{item.total}</strong>
                  </div>

                  <div className="dashboard-progress-track">
                    <div
                      className={`dashboard-progress-fill ${
                        item.status === "active" ? "green" : "orange"
                      }`}
                      style={{
                        width: `${
                          statusTotal > 0
                            ? (item.total / statusTotal) * 100
                            : 0
                        }%`,
                      }}
                    />
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>
      </div>

      <section className="page-card dashboard-panel dashboard-activities">
        <div className="dashboard-panel-heading">
          <div>
            <h3>Recent Activities</h3>
            <p>Latest platform administration activities</p>
          </div>
          <Activity size={20} />
        </div>

        {data.recent_activities.length === 0 ? (
          <div className="dashboard-empty-state">
            <Activity size={30} />
            <strong>No recent activities</strong>
            <p>
              Platform activity records will appear here as administrators
              sign in and make changes.
            </p>
          </div>
        ) : (
          <ul className="activity-list">
            {data.recent_activities.map((activity) => (
              <li key={activity.id} className="activity-item">
                <code className="activity-event audit-event">{activity.event}</code>
                <div className="activity-body">
                  <span className="activity-description">
                    {activity.description ?? activity.event}
                  </span>
                  <span className="activity-meta">
                    {activity.actor_name ?? "System"} ·{" "}
                    {new Date(activity.created_at).toLocaleString("en-IN", {
                      day: "2-digit",
                      month: "short",
                      hour: "2-digit",
                      minute: "2-digit",
                    })}
                    {" · "}
                    {activity.module}
                  </span>
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  );
}

export default PlatformDashboard;
