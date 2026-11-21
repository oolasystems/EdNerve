import { EmptyState } from "../../shared/ui";

export interface ModulePlaceholderProps {
  label: string;
  description?: string;
}

/**
 * Standard placeholder for declared-but-unimplemented ERP modules.
 * Every module registers its routes; until its screen is built,
 * the route renders this.
 */
const ModulePlaceholder = ({ label, description }: ModulePlaceholderProps) => (
  <div className="workspace-placeholder">
    <EmptyState
      title={`${label} is coming soon`}
      description={
        description ??
        "This module is planned in the OolaEdNerve platform roadmap."
      }
    />
  </div>
);

export default ModulePlaceholder;
