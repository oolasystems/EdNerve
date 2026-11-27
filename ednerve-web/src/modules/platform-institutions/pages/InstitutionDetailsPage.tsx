import "../platform-institutions.css";
import { useNavigate, useParams } from "react-router-dom";
import {
  ArrowLeft,
  Building2,
  Users,
  UserCheck,
  UserX,
  Network,
  MapPin,
  Mail,
  Phone,
  Globe,
  CalendarDays,
  RefreshCw,
  UserRound,
} from "lucide-react";
import { useQuery } from "@tanstack/react-query";

import { platformInstitutionsService } from "../api";
import type { Institution } from "../types";

import {
  Alert,
  LoadingState,
  StatusBadge,
} from "../../../shared/ui";

import {
  formatDate,
  formatInstitutionType,
  joinLocation,
} from "../../../shared/lib/format";

interface InstitutionAdmin {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  status: string;
}

interface InstitutionDetailsResponse {
  institution: Institution;
  institution_admin: InstitutionAdmin | null;
}

const InstitutionDetailsPage = () => {
  const { institutionId } = useParams<{ institutionId: string }>();
  const navigate = useNavigate();

  const numericId = Number(institutionId);
  const validId = Number.isFinite(numericId) && numericId > 0;

  const {
    data,
    isLoading,
    isFetching,
    isError,
    refetch,
  } = useQuery<InstitutionDetailsResponse>({
    queryKey: ["platform", "institutions", numericId],
    queryFn: () => platformInstitutionsService.getInstitution(numericId),
    enabled: validId,
  });

  const handleBack = () => navigate("/platform/institutions");

  if (!validId) {
    return (
      <div className="institution-details-state institution-details-error">
        <Alert tone="error">Invalid institution identifier.</Alert>
        <button className="btn btn-secondary" onClick={handleBack}>
          Back to Directory
        </button>
      </div>
    );
  }

  if (isLoading) {
    return (
      <LoadingState
        message="Loading institution details..."
        spinnerSize={25}
      />
    );
  }

  const institution = data?.institution;
  const admin = data?.institution_admin ?? null;

  if (isError || !institution) {
    return (
      <div className="institution-details-state institution-details-error">
        <Alert tone="error">
          Unable to load institution details. Please try again.
        </Alert>
        <button
          className="btn btn-secondary"
          onClick={() => void refetch()}
        >
          Retry
        </button>
        <button className="btn btn-secondary" onClick={handleBack}>
          Back to Directory
        </button>
      </div>
    );
  }

  const location = joinLocation([institution.city, institution.state, institution.country]);

  const statistics = [
    {
      label: "Total Users",
      value: institution.users_count,
      icon: Users,
      color: "blue",
    },
    {
      label: "Active Users",
      value: institution.active_users_count,
      icon: UserCheck,
      color: "green",
    },
    {
      label: "Inactive Users",
      value:
        institution.inactive_users_count ??
        Math.max(0, institution.users_count - institution.active_users_count),
      icon: UserX,
      color: "orange",
    },
    {
      label: "Academic Units",
      value: institution.academic_units_count,
      icon: Network,
      color: "purple",
    },
  ];

  return (
    <div className="institution-details">

      <div className="institution-details-toolbar">
        <button
          type="button"
          className="btn btn-secondary"
          onClick={handleBack}
        >
          <ArrowLeft size={16} />
          Back to Directory
        </button>

        <button
          type="button"
          className="btn btn-ghost btn-sm"
          onClick={() => void refetch()}
          disabled={isFetching}
          title="Refresh details"
        >
          <RefreshCw
            size={15}
            className={isFetching ? "spinner" : ""}
          />
          Refresh
        </button>
      </div>

      {/* Institution Identity */}
      <section className="institution-details-profile">
        <div className="institution-details-profile-icon">
          <Building2 size={30} />
        </div>

        <div className="institution-details-profile-info">
          <h2>{institution.name}</h2>
          <div className="institution-details-profile-meta">
            <span>{institution.code}</span>
            <span className="institution-type-label">
              {formatInstitutionType(institution.institution_type)}
            </span>
            <StatusBadge status={institution.status} />
          </div>
        </div>
      </section>

      {/* Institution Statistics */}
      <div className="institution-details-statistics">
        {statistics.map((item) => {
          const Icon = item.icon;

          return (
            <div className="institution-details-stat-card" key={item.label}>
              <div className={`institution-details-stat-icon ${item.color}`}>
                <Icon size={21} />
              </div>
              <div>
                <strong>{item.value}</strong>
                <span>{item.label}</span>
              </div>
            </div>
          );
        })}
      </div>

      {/* Institution Profile */}
      <section className="institution-details-section">
        <div className="institution-details-section-heading">
          <Building2 size={19} />
          <h3>Institution Profile</h3>
        </div>

        <div className="institution-details-grid">
          <div className="institution-details-field">
            <label>Institution Name</label>
            <strong>{institution.name}</strong>
          </div>

          <div className="institution-details-field">
            <label>Institution Code</label>
            <strong>{institution.code}</strong>
          </div>

          <div className="institution-details-field">
            <label>Institution Type</label>
            <strong>{formatInstitutionType(institution.institution_type)}</strong>
          </div>

          <div className="institution-details-field">
            <label>URL Slug</label>
            <strong>{institution.slug}</strong>
          </div>

          <div className="institution-details-field">
            <label>Official Email</label>
            <strong>
              {institution.email || "Not specified"}
            </strong>
          </div>

          <div className="institution-details-field">
            <label>Contact Phone</label>
            <strong>
              {institution.phone || "Not specified"}
            </strong>
          </div>

          <div className="institution-details-field">
            <label>Website</label>
            {institution.website ? (
              <a href={institution.website} target="_blank" rel="noreferrer">
                {institution.website}
                <Globe size={14} />
              </a>
            ) : (
              <strong>Not specified</strong>
            )}
          </div>

          <div className="institution-details-field">
            <label>Address</label>
            <strong>{institution.address || "Not specified"}</strong>
          </div>

          <div className="institution-details-field">
            <label>Location</label>
            <strong>
              {location || "Not specified"}
              {location && <MapPin size={14} />}
            </strong>
          </div>

          <div className="institution-details-field">
            <label>Country</label>
            <strong>{institution.country}</strong>
          </div>

          <div className="institution-details-field">
            <label>Created On</label>
            <strong>
              <CalendarDays size={14} />
              {formatDate(institution.created_at)}
            </strong>
          </div>
        </div>
      </section>

      {/* Initial Administrator */}
      <section className="institution-details-section">
        <div className="institution-details-section-heading">
          <UserRound size={19} />
          <h3>Institution Administrator</h3>
        </div>

        {admin ? (
          <div className="institution-admin-card">
            <div className="institution-admin-avatar">
              <UserRound size={23} />
            </div>

            <div className="institution-admin-info">
              <strong>{admin.name}</strong>
              <span>
                <Mail size={14} />
                {admin.email}
              </span>
              <span>
                <Phone size={14} />
                {admin.phone || "Phone not specified"}
              </span>
            </div>

            <StatusBadge status={admin.status} />
          </div>
        ) : (
          <p className="institution-details-empty">
            No institution administrator found.
          </p>
        )}
      </section>

    </div>
  );
};

export default InstitutionDetailsPage;
