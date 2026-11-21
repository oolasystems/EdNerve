import { useNavigate, useParams } from "react-router-dom";
import {
  ArrowLeft,
  UserRound,
  Mail,
  Phone,
  Building2,
  CalendarDays,
  RefreshCw,
} from "lucide-react";
import { useQuery } from "@tanstack/react-query";

import { platformUserService } from "../api";
import EffectiveAccessPanel from "../components/EffectiveAccessPanel";
import "../users.css";

import { Alert, LoadingState, StatusBadge, Badge } from "../../../shared/ui";

const UserDetailsPage = () => {
  const { userId } = useParams<{ userId: string }>();
  const navigate = useNavigate();

  const numericId = Number(userId);
  const validId = Number.isFinite(numericId) && numericId > 0;

  const userQuery = useQuery({
    queryKey: ["users", numericId],
    queryFn: () => platformUserService.getUser(numericId),
    enabled: validId,
  });

  const user = userQuery.data?.user;

  const handleBack = () => navigate("/platform/users");

  if (!validId) {
    return (
      <div className="institution-details-state institution-details-error">
        <Alert tone="error">Invalid user identifier.</Alert>
        <button className="btn btn-secondary" onClick={handleBack}>
          Back to Users
        </button>
      </div>
    );
  }

  if (userQuery.isLoading) {
    return <LoadingState message="Loading user details..." spinnerSize={25} />;
  }

  if (userQuery.isError || !user) {
    return (
      <div className="institution-details-state institution-details-error">
        <Alert tone="error">Unable to load user details. Please try again.</Alert>
        <button className="btn btn-secondary" onClick={() => void userQuery.refetch()}>
          Retry
        </button>
        <button className="btn btn-secondary" onClick={handleBack}>
          Back to Users
        </button>
      </div>
    );
  }

  const assignments = user.active_role_assignments ?? [];

  return (
    <div className="institution-details">
      <div className="institution-details-toolbar">
        <button type="button" className="btn btn-secondary" onClick={handleBack}>
          <ArrowLeft size={16} />
          Back to Users
        </button>

        <button
          type="button"
          className="btn btn-ghost btn-sm"
          onClick={() => void userQuery.refetch()}
          disabled={userQuery.isFetching}
        >
          <RefreshCw size={15} className={userQuery.isFetching ? "spinner" : ""} />
          Refresh
        </button>
      </div>

      {/* Identity */}
      <section className="institution-details-profile">
        <div className="institution-details-profile-icon">
          <UserRound size={30} />
        </div>

        <div className="institution-details-profile-info">
          <h2>{user.name}</h2>
          <div className="institution-details-profile-meta">
            {user.account_type === "platform" ? (
              <Badge tone="info">Platform Admin</Badge>
            ) : (
              <span className="institution-type-label">
                {user.institution?.name ?? "Institution"}
              </span>
            )}
            <StatusBadge status={user.status} />
            <span>{user.email}</span>
          </div>
        </div>
      </section>

      {/* Profile grid */}
      <section className="institution-details-section">
        <div className="institution-details-section-heading">
          <UserRound size={19} />
          <h3>Account Profile</h3>
        </div>

        <div className="institution-details-grid">
          <div className="institution-details-field">
            <label>Account Type</label>
            <strong>{user.account_type === "platform" ? "Platform" : "Institution"}</strong>
          </div>

          <div className="institution-details-field">
            <label>Institution</label>
            <strong>
              {user.institution?.name ??
                (user.account_type === "platform" ? "Platform Administration" : "Not specified")}
            </strong>
          </div>

          <div className="institution-details-field">
            <label>Email</label>
            <strong>
              <Mail size={14} />
              {user.email}
            </strong>
          </div>

          <div className="institution-details-field">
            <label>Phone</label>
            <strong>
              <Phone size={14} />
              {user.phone || "Not specified"}
            </strong>
          </div>

          <div className="institution-details-field">
            <label>Last Login</label>
            <strong>
              <CalendarDays size={14} />
              {user.last_login_at
                ? new Date(user.last_login_at).toLocaleDateString("en-IN", {
                    day: "2-digit",
                    month: "short",
                    year: "numeric",
                  })
                : "Never"}
            </strong>
          </div>

          <div className="institution-details-field">
            <label>Member Since</label>
            <strong>
              <CalendarDays size={14} />
              {new Date(user.created_at).toLocaleDateString("en-IN", {
                day: "2-digit",
                month: "short",
                year: "numeric",
              })}
            </strong>
          </div>
        </div>
      </section>

      {/* Assignments */}
      <section className="institution-details-section">
        <div className="institution-details-section-heading">
          <Building2 size={19} />
          <h3>Role Assignments</h3>
        </div>

        {assignments.length === 0 ? (
          <p className="institution-details-empty">No active role assignments.</p>
        ) : (
          <ul className="user-assignments">
            {assignments.map((assignment) => (
              <li key={assignment.id} className="user-assignment-item">
                <code>{assignment.role.code}</code>
                <span>{assignment.role.name}</span>
                {assignment.is_primary && (
                  <Badge tone="info">Primary</Badge>
                )}
              </li>
            ))}
          </ul>
        )}
      </section>

      {/* Effective access lens */}
      <EffectiveAccessPanel userId={numericId} />
    </div>
  );
};

export default UserDetailsPage;
