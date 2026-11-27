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
      <div className="institution-admin-panel institution-roles-note-panel">
        <p className="institution-roles-note">
          Role <strong>definitions</strong> are managed by the Platform
          Administration team. Institution administrators assign roles to
          their own users from{" "}
          <a href="/institution/access/assignments">Role Assignments</a>.
        </p>
      </div>

      <div className="institution-admin-panel">
        <div className="institution-roles-table-wrap">
          <table className="institution-roles-table">
          <thead>
            <tr>
              <th className="institution-roles-table-heading">Code</th>
              <th className="institution-roles-table-heading">Role</th>
              <th className="institution-roles-table-heading">Description</th>
              <th className="institution-roles-table-heading">Status</th>
            </tr>
          </thead>
          <tbody>
            {roles.map((role) => (
              <tr key={role.id}>
                <td className="institution-roles-table-cell">
                  <span className="institution-mono">{role.code}</span>
                </td>
                <td className="institution-roles-table-cell">
                  <div className="institution-role-name">
                    <KeyRound size={15} className="institution-role-icon" />
                    <strong>{role.name}</strong>
                  </div>
                </td>
                <td className="institution-roles-table-cell institution-roles-description">
                  {role.description ?? "—"}
                </td>
                <td className="institution-roles-table-cell">
                  <StatusBadge status={role.status} />
                </td>
              </tr>
            ))}
          </tbody>
          </table>
        </div>
      </div>
    </>
  );
};

export default InstitutionRolesPage;
