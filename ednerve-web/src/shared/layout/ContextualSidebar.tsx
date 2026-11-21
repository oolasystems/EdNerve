import { Link, useLocation } from "react-router-dom";

import { findAreaByPath, findItemByPath } from "../../config/navigation/resolve";
import { toRbacSubject, canAccess } from "../lib/rbac";
import { useAuth } from "../../contexts/AuthContext";
import type { NavigationAreaConfig } from "../../config/navigation/navigation.types";

interface ContextualSidebarProps {
  navigation: NavigationAreaConfig[];
}

const ContextualSidebar = ({ navigation }: ContextualSidebarProps) => {
  const { user } = useAuth();
  const subject = toRbacSubject(user);
  const location = useLocation();

  const configuration =
    findAreaByPath(location.pathname, navigation) ??
    // Fallback to a resolved item's area when exact area matching fails.
    findItemByPath(location.pathname, navigation)?.area;

  if (!configuration) {
    return null;
  }

  const activeItemId = findItemByPath(location.pathname, navigation)?.item.id;

  return (
    <aside className="contextual-sidebar">
      <div className="sidebar-heading">
        <h2>{configuration.label}</h2>
      </div>

      <nav
        className="contextual-sidebar-navigation"
        aria-label={`${configuration.label} navigation`}
      >
        {configuration.sections.map((section) => {
          const visibleItems = section.items.filter((item) =>
            canAccess(subject, item.accountTypes, item.permissions)
          );

          if (visibleItems.length === 0) return null;

          return (
            <section
              className="sidebar-section"
              key={section.id}
            >
              <h3 className="sidebar-section-title">
                {section.title}
              </h3>

              <div className="sidebar-section-items">
                {visibleItems.map((item) => {
                  const Icon = item.icon;
                  const isActive = activeItemId === item.id;

                  return (
                    <Link
                      key={item.id}
                      to={item.route}
                      className={`sidebar-nav-item ${isActive ? "active" : ""}`}
                      aria-current={isActive ? "page" : undefined}
                    >
                      <Icon size={17} strokeWidth={1.8} />
                      <span>{item.label}</span>
                    </Link>
                  );
                })}
              </div>
            </section>
          );
        })}
      </nav>
    </aside>
  );
};

export default ContextualSidebar;
