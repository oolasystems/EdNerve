import { Link, useLocation } from "react-router-dom";

import { findAreaByPath } from "../../config/navigation/resolve";
import { toRbacSubject, canAccess } from "../lib/rbac";
import { useAuth } from "../../contexts/AuthContext";
import type { NavigationAreaConfig } from "../../config/navigation/navigation.types";

interface PrimaryNavigationProps {
  navigation: NavigationAreaConfig[];
}

const PrimaryNavigation = ({ navigation }: PrimaryNavigationProps) => {
  const { user } = useAuth();
  const subject = toRbacSubject(user);
  const location = useLocation();
  const activeArea = findAreaByPath(location.pathname, navigation);

  return (
    <nav
      className="primary-navigation"
      aria-label="Primary navigation"
    >
      <div className="primary-navigation-items">
        {navigation
          .filter((area) => canAccess(subject, area.accountTypes, area.permissions))
          .map((area) => {
            const Icon = area.icon;
            const isActive = activeArea?.id === area.id;

            return (
              <Link
                key={area.id}
                to={area.route}
                className={`primary-nav-item ${isActive ? "active" : ""}`}
                title={area.label}
                aria-label={area.label}
                aria-current={isActive ? "page" : undefined}
              >
                <Icon
                  size={21}
                  strokeWidth={isActive ? 2.3 : 1.8}
                />

                <span className="primary-nav-tooltip">
                  {area.label}
                </span>
              </Link>
            );
          })}
      </div>
    </nav>
  );
};

export default PrimaryNavigation;
