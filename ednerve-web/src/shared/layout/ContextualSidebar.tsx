import { useState } from "react";
import { ChevronDown, ChevronRight } from "lucide-react";
import { Link, useLocation } from "react-router-dom";

import { findAreaByPath, findItemByPath } from "../../config/navigation/resolve";
import { toRbacSubject, canAccess } from "../lib/rbac";
import { useAuth } from "../../contexts/AuthContext";
import type { NavigationAreaConfig } from "../../config/navigation/navigation.types";

interface ContextualSidebarProps {
  navigation: NavigationAreaConfig[];
}

const ContextualSidebar = ({ navigation }: ContextualSidebarProps) => {
  const [expandedSections, setExpandedSections] = useState<
    Record<string, boolean>
  >({});
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
        {configuration.sections.map((section, sectionIndex) => {
          const visibleItems = section.items.filter((item) =>
            canAccess(subject, item.accountTypes, item.permissions)
          );

          if (visibleItems.length === 0) return null;

          // Expand the first group by default. Also reveal the group containing
          // the current page when navigating directly to an item in another group.
          const sectionKey = `${configuration.id}:${section.id}`;
          const defaultExpanded =
            sectionIndex === 0 || section.items.some((item) => item.id === activeItemId);
          const isExpanded = expandedSections[sectionKey] ?? defaultExpanded;

          return (
            <section
              className="sidebar-section"
              key={section.id}
            >
              {section.collapsible ? (
                <button
                  type="button"
                  className="sidebar-section-toggle"
                  aria-expanded={isExpanded}
                  aria-controls={`sidebar-section-${configuration.id}-${section.id}`}
                  onClick={() =>
                    setExpandedSections((current) => ({
                      ...current,
                      [sectionKey]: !isExpanded,
                    }))
                  }
                >
                  <span className="sidebar-section-title">{section.title}</span>
                  {isExpanded ? (
                    <ChevronDown size={15} aria-hidden="true" />
                  ) : (
                    <ChevronRight size={15} aria-hidden="true" />
                  )}
                </button>
              ) : (
                <h3 className="sidebar-section-title">{section.title}</h3>
              )}

              {(!section.collapsible || isExpanded) && (
                <div
                  className="sidebar-section-items"
                  id={`sidebar-section-${configuration.id}-${section.id}`}
                >
                  {visibleItems.map((item) => {
                    const Icon = item.icon;
                    const isActive = activeItemId === item.id;

                    return (
                      <Link
                        key={item.id}
                        to={item.route}
                        className={`sidebar-nav-item ${isActive ? "active" : ""}`}
                        title={item.description}
                        aria-label={`${item.label}: ${item.description}`}
                        aria-current={isActive ? "page" : undefined}
                      >
                        <Icon size={17} strokeWidth={1.8} />
                        <span>{item.label}</span>
                      </Link>
                    );
                  })}
                </div>
              )}
            </section>
          );
        })}
      </nav>
    </aside>
  );
};

export default ContextualSidebar;
