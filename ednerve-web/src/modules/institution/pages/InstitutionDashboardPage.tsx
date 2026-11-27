import {
  Building2,
  KeyRound,
  Network,
  RefreshCw,
  Users,
} from "lucide-react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Link } from "react-router-dom";

import { institutionService } from "../api";
import "../institution.css";

import {
  Alert,
  Spinner,
  StatusBadge,
} from "../../../shared/ui";
import type { InstitutionUser } from "../types";

const InstitutionDashboardPage = () => {
  const queryClient = useQueryClient();

  const profileQuery = useQuery({
    queryKey: ["institution", "profile"],
    queryFn: () => institutionService.getProfile(),
  });

  const usersQuery = useQuery({
    queryKey: ["institution", "users", "first-page"],
    queryFn: () => institutionService.getUsers(1, 5),
  });

  const usersTotalQuery = useQuery({
    queryKey: ["institution", "users", "total"],
    queryFn: () => institutionService.getUsers(1, 1),
  });

  const unitsQuery = useQuery({
    queryKey: ["institution", "academic-units", "total"],
    queryFn: () => institutionService.getAcademicUnits(),
  });

  const assignmentsQuery = useQuery({
    queryKey: ["institution", "role-assignments", "total"],
    queryFn: () => institutionService.getRoleAssignments(1, 1),
  });

  const rolesQuery = useQuery({
    queryKey: ["institution", "roles"],
    queryFn: () => institutionService.getRoles(),
  });

  const institution = profileQuery.data?.institution;
  const users: InstitutionUser[] = usersQuery.data?.data ?? [];
  const roles = rolesQuery.data?.roles ?? [];

  const refreshAll = () => {
    void queryClient.invalidateQueries({ queryKey: ["institution"] });
  };

  if (profileQuery.isLoading) {
    return (
      <div className="dashboard-state">
        <Spinner size={22} />
        <p>Loading institution dashboard...</p>
      </div>
    );
  }

  if (profileQuery.isError || !institution) {
    return (
      <div className="dashboard-state">
        <Alert tone="error">
          Unable to load the institution profile. Please try again.
        </Alert>
        <button
          type="button"
          className="workspace-action-button"
          onClick={refreshAll}
        >
          <RefreshCw size={15} />
          Retry
        </button>
      </div>
    );
  }

  const statCards = [
    {
      icon: Users,
      value: usersTotalQuery.data?.total ?? 0,
      label: "Institution users",
      hint: "Active and inactive",
    },
    {
      icon: Network,
      value: unitsQuery.data?.academic_units?.length ?? 0,
      label: "Academic units",
      hint: "Faculties, schools, departments",
    },
    {
      icon: KeyRound,
      value: assignmentsQuery.data?.total ?? 0,
      label: "Role assignments",
      hint: "Active and historical",
    },
    {
      icon: Building2,
      value: roles.length,
      label: "Roles in catalogue",
      hint: "Managed by the platform",
    },
  ];

  return (
    <>
      <div className="dashboard-toolbar">
        <span className="dashboard-updated-label" />
        <button
          type="button"
          className="workspace-action-button"
          onClick={refreshAll}
        >
          <RefreshCw
            size={15}
            className={
              usersQuery.isFetching || profileQuery.isFetching
                ? "spinner"
                : undefined
            }
          />
          Refresh
        </button>
      </div>

      <div className="institution-admin-grid" aria-label="Institution summary">
        {statCards.map((card) => {
          const Icon = card.icon;
          return (
            <div key={card.label} className="institution-admin-panel">
<div className="institution-dashboard-stat-header">
                <div>
<strong className="institution-dashboard-stat-value">
                    {card.value}
                  </strong>
<span className="institution-dashboard-stat-label">
                    {card.label}
                  </span>
                </div>
                <Icon size={26} color="var(--text-accent)" strokeWidth={1.6} />
              </div>
<span className="institution-dashboard-stat-hint">
                {card.hint}
              </span>
            </div>
          );
        })}
      </div>

      <div className="institution-two-column">
        <section
          className="institution-admin-panel"
          aria-label="Recent users"
        >
          <h3>Recent institution users</h3>
          {usersQuery.isLoading ? (
            <div className="dashboard-state">
              <Spinner size={18} />
            </div>
          ) : users.length === 0 ? (
            <p className="institution-dashboard-empty">
              No users found for this institution.
            </p>
          ) : (
            <ul className="institution-user-list">
              {users.map((user) => {
                const nameParts = user.name.trim().split(/\s+/);
                const initials = nameParts
                  .slice(0, 2)
                  .map((part) => part[0]?.toUpperCase() ?? "")
                  .join("");

                const assignments = user.active_role_assignments ?? [];

                return (
                  <li key={user.id} className="institution-user-row">
                    <span
                      className="institution-user-avatar"
                      aria-hidden="true"
                    >
                      {initials}
                    </span>

                    <span className="institution-user-meta">
                      <span className="institution-user-name">{user.name}</span>
                      <span className="institution-user-email">
                        {user.email}
                      </span>

                      {assignments.length > 0 ? (
                        <span className="institution-role-chips">
                          {assignments.map((assignment) => (
                            <span
                              key={assignment.id}
                              className={`institution-role-chip${
                                assignment.is_primary ? " primary" : ""
                              }`}
                            >
                              {assignment.role?.name ?? "Role"}
                              {assignment.is_primary ? " ★" : ""}
                            </span>
                          ))}
                        </span>
                      ) : (
                        <span className="institution-user-no-role">
                          No active role
                        </span>
                      )}
                    </span>

                    <span className="institution-user-status">
                      <StatusBadge status={user.status} />
                    </span>
                  </li>
                );
              })}
            </ul>
          )}
          <p className="institution-dashboard-section-link">
            <Link to="/institution/users">View all users →</Link>
          </p>
        </section>

        <section
          className="institution-admin-panel"
          aria-label="Institution profile"
        >
          <h3>Institution profile</h3>
          <dl className="institution-admin-detail-list">
            <div>
              <dt>Name</dt>
              <dd>{institution.name}</dd>
            </div>
            <div>
              <dt>Code</dt>
              <dd className="institution-mono">{institution.code}</dd>
            </div>
            <div>
              <dt>Type</dt>
              <dd>{institution.institution_type}</dd>
            </div>
            <div>
              <dt>Status</dt>
              <dd>
                <StatusBadge status={institution.status} />
              </dd>
            </div>
            <div>
              <dt>Location</dt>
              <dd>
                {[institution.city, institution.state, institution.country]
                  .filter(Boolean)
                  .join(", ") || "Not specified"}
              </dd>
            </div>
            <div>
              <dt>Email</dt>
              <dd>{institution.email ?? "Not specified"}</dd>
            </div>
            <div>
              <dt>Phone</dt>
              <dd>{institution.phone ?? "Not specified"}</dd>
            </div>
          </dl>
          <p className="institution-dashboard-section-link">
            <Link to="/institution/profile">Edit profile →</Link>
          </p>
        </section>
      </div>
    </>
  );
};

export default InstitutionDashboardPage;
