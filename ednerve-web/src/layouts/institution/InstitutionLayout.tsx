import { useEffect, useState } from "react";
import { Outlet, useLocation, useNavigate } from "react-router-dom";

import { useAuth } from "../../contexts/AuthContext";

import GlobalHeader from "../../shared/layout/GlobalHeader";
import PrimaryNavigation from "../../shared/layout/PrimaryNavigation";
import ContextualSidebar from "../../shared/layout/ContextualSidebar";
import Workspace from "../../shared/layout/Workspace";

import { findItemByPath } from "../../config/navigation/resolve";
import { institutionNavigation } from "../../config/navigation/institution.navigation";

/**
 * Institution Administration workspace shell.
 *
 * Layouts are based on workspaces, not roles — this shell renders for
 * everyone allowed into the institution area; RBAC gates navigation
 * items and actions inside (see lib/rbac).
 */
const InstitutionLayout = () => {
  const { user, logout } = useAuth();
  const location = useLocation();
  const navigate = useNavigate();

  const [drawerOpenPath, setDrawerOpenPath] = useState<string | null>(null);
  const mobileDrawerOpen = drawerOpenPath === location.pathname;

  const resolved = findItemByPath(location.pathname);
  const activeArea = resolved?.area ?? institutionNavigation[0];
  const activeItem =
    resolved?.item ??
    activeArea.sections
      .flatMap((section) => section.items)
      .find((item) => item.id === activeArea.defaultItemId) ??
    activeArea.sections[0]?.items[0];

  // The drawer is open only for the route on which it was opened.
  // A route change therefore closes it without a state update in an effect.

  // Close mobile navigation using Escape.
  useEffect(() => {
    if (!mobileDrawerOpen) return;

    const handleEscape = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setDrawerOpenPath(null);
      }
    };

    document.addEventListener("keydown", handleEscape);
    return () => document.removeEventListener("keydown", handleEscape);
  }, [mobileDrawerOpen]);

  // Prevent background scrolling while the drawer is open.
  useEffect(() => {
    document.body.style.overflow = mobileDrawerOpen ? "hidden" : "";
    return () => {
      document.body.style.overflow = "";
    };
  }, [mobileDrawerOpen]);

  const handleLogout = async () => {
    try {
      await logout();
      navigate("/login", { replace: true });
    } catch (error) {
      console.error("Logout request failed:", error);
    }
  };

  const ActionIcon = activeItem?.action?.icon;

  const handlePageAction = () => {
    if (!activeItem?.action) return;

    const target = activeArea.sections
      .flatMap((section) => section.items)
      .find((item) => item.id === activeItem.action?.targetItemId);

    if (target) {
      navigate(target.route);
    }
  };

  if (!user) {
    return null; // PlatformAdminGuard handles unauthenticated users.
  }

  return (
    <div className="app-container">
      <GlobalHeader
        onMenuClick={() => setDrawerOpenPath(location.pathname)}
        user={user}
        onLogout={handleLogout}
      />

      <div className="app-body">
        <PrimaryNavigation navigation={institutionNavigation} />
        <ContextualSidebar navigation={institutionNavigation} />

        <Workspace
          breadcrumbs={[
            { label: "Institution" },
            { label: activeArea.label },
            { label: activeItem?.label ?? "" },
          ]}
          title={activeItem?.label ?? "Institution Administration"}
          description={activeItem?.description ?? ""}
          actions={
            activeItem?.action ? (
              <button
                type="button"
                className="workspace-action-button"
                onClick={handlePageAction}
              >
                {ActionIcon && <ActionIcon size={17} />}
                {activeItem.action.label}
              </button>
            ) : undefined
          }
        >
          <Outlet />
        </Workspace>
      </div>

      {/* Mobile Navigation Drawer */}
      {mobileDrawerOpen && (
        <div
          className="mobile-drawer-overlay"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget) {
              setDrawerOpenPath(null);
            }
          }}
        >
          <div
            className="mobile-drawer"
            role="dialog"
            aria-modal="true"
            aria-label="Application navigation"
          >
            <div className="mobile-drawer-header">
              <strong>Navigation</strong>
              <button
                type="button"
                className="mobile-drawer-close"
                onClick={() => setDrawerOpenPath(null)}
                aria-label="Close navigation menu"
              >
                ×
              </button>
            </div>

            <div className="mobile-drawer-content">
              <PrimaryNavigation navigation={institutionNavigation} />
              <ContextualSidebar navigation={institutionNavigation} />
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default InstitutionLayout;
