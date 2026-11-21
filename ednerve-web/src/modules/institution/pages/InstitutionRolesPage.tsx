import { KeyRound } from "lucide-react";
import { useQuery } from "@tanstack/react-query";

import { institutionService } from "../api";
import "../institution.css";

import {
  Alert,
  StatusBadge,
} from "../../../shared/ui";

const InstitutionRolesPage = () => {
  const rolesQuery = useQuery({
    queryKey: ["institution", "roles"],
    queryFn: () => institutionService.getRoles(),
  });

  const roles = rolesQuery.data?.roles ?? [];

  if (rolesQuery.isLoading) {
    return (
      <div className="dashboard-state">
        <p>Loading the role catalogue...</p>
      </div>
    );
  }

  if (rolesQuery.isError) {
    return (
      <div className="dashboard-state">
        <Alert tone="error">
          Unable to load the role catalogue. Please try again.
        </Alert>
        <button
          type="button"
          className="workspace-action-button"
          onClick={() => void rolesQuery.refetch()}
        >
          Retry
        </button>
      </div>
    );
  }

  return (
    <>
      <div className="institution-admin-panel" style={{ marginBottom: "var(--space-4)" }}>
        <p style={{ margin: 0, color: "var(--text-secondary)" }}>
          Role <strong>definitions</strong> are managed by the Platform
          Administration team. Institution administrators assign roles to
          their own users from{" "}
          <a href="/institution/access/assignments">Role Assignments</a>.
        </p>
      </div>

      <div className="institution-admin-panel">
        <table
          style={{ width: "100%", borderCollapse: "collapse", fontSize: "var(--font-size-sm)" }}
        >
          <thead>
            <tr>
              <th style={headerStyle}>Code</th>
              <th style={headerStyle}>Role</th>
              <th style={headerStyle}>Description</th>
              <th style={headerStyle}>Status</th>
            </tr>
          </thead>
          <tbody>
            {roles.map((role) => (
              <tr key={role.id}>
                <td style={cellStyle}>
                  <span className="institution-mono">{role.code}</span>
                </td>
                <td style={cellStyle}>
                  <div
                    style={{
                      display: "flex",
                      alignItems: "center",
                      gap: "var(--space-2)",
                    }}
                  >
                    <KeyRound size={15} color="var(--text-accent)" />
                    <strong>{role.name}</strong>
                  </div>
                </td>
                <td style={{ ...cellStyle, color: "var(--text-secondary)" }}>
                  {role.description ?? "—"}
                </td>
                <td style={cellStyle}>
                  <StatusBadge status={role.status} />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </>
  );
};

const headerStyle: React.CSSProperties = {
  textAlign: "left",
  padding: "var(--space-2) var(--space-3)",
  borderBottom: "1px solid var(--border)",
  color: "var(--text-muted)",
  fontSize: "var(--font-size-xs)",
  textTransform: "uppercase",
  letterSpacing: "0.06em",
};

const cellStyle: React.CSSProperties = {
  padding: "var(--space-2) var(--space-3)",
  borderBottom: "1px solid var(--border)",
  verticalAlign: "middle",
};

export default InstitutionRolesPage;
