
import { useEffect, useRef, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import {
  Menu,
  Search,
  Bell,
  CircleHelp,
  UserCircle,
  ChevronDown,
  Settings,
  LogOut,
  X,
  Sun,
  Moon,
  Monitor,
} from "lucide-react";

import { useTheme } from "../../contexts/ThemeProvider";
import { ROUTE_PATHS } from "../../app/route-paths";

import type { AuthUser } from "../types/auth";
import logo from "../../assets/brand/oolaednerve-logo.svg";
import logoLight from "../../assets/brand/oolaednerve-logo-light.svg";

type ActiveMenu = "notifications" | "help" | "profile" | null;

interface GlobalHeaderProps {
  onMenuClick: () => void;
  user: AuthUser;
  onLogout: () => Promise<void>;
}

const GlobalHeader = ({
  onMenuClick,
  user,
  onLogout,
}: GlobalHeaderProps) => {
  const [searchValue, setSearchValue] = useState("");
  const [activeMenu, setActiveMenu] = useState<ActiveMenu>(null);
  const { preference, resolvedTheme, toggle: toggleTheme } = useTheme();

  const ThemeIcon =
    preference === "dark" ? Moon : preference === "system" ? Monitor : Sun;

  const themeLabel =
    preference === "dark"
      ? "Theme: Dark — click to switch to System"
      : preference === "system"
        ? "Theme: System (follows OS) — click to switch to Light"
        : "Theme: Light — click to switch to Dark";

  const headerActionsRef = useRef<HTMLDivElement>(null);

  const toggleMenu = (menu: Exclude<ActiveMenu, null>) => {
    setActiveMenu((current) => (current === menu ? null : menu));
  };

  useEffect(() => {
    const handleOutsideClick = (event: MouseEvent) => {
      if (
        headerActionsRef.current &&
        !headerActionsRef.current.contains(event.target as Node)
      ) {
        setActiveMenu(null);
      }
    };

    const handleEscape = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setActiveMenu(null);
      }
    };

    document.addEventListener("mousedown", handleOutsideClick);
    document.addEventListener("keydown", handleEscape);

    return () => {
      document.removeEventListener("mousedown", handleOutsideClick);
      document.removeEventListener("keydown", handleEscape);
    };
  }, []);

  const navigate = useNavigate();
  const securityTarget =
    user.account_type === "institution"
      ? ROUTE_PATHS.institution.security
      : ROUTE_PATHS.platform.security;

  const handleLogout = () => {
    setActiveMenu(null);
    void onLogout();
  };

  return (
    <header className="global-header">
      <button
        type="button"
        className="mobile-menu-button"
        onClick={onMenuClick}
        aria-label="Open navigation menu"
      >
        <Menu size={23} />
      </button>

      <div className="header-brand">
        <Link to="/" aria-label="OolaEdNerve home">
          <img
            src={resolvedTheme === "dark" ? logoLight : logo}
            alt="OolaEdNerve"
            className="header-logo"
          />
        </Link>
      </div>

      <div className="header-actions" ref={headerActionsRef}>
        {/* Global Search */}
        <div className="header-search">
          <Search size={18} />

          <input
            type="search"
            placeholder="Search anything..."
            value={searchValue}
            onChange={(event) => setSearchValue(event.target.value)}
            aria-label="Global search"
          />

          {searchValue && (
            <button
              type="button"
              className="header-search-clear"
              onClick={() => setSearchValue("")}
              aria-label="Clear search"
            >
              <X size={15} />
            </button>
          )}
        </div>

        {/* Notifications */}
        <div className="header-menu-container">
          <button
            type="button"
            className="header-icon-button"
            onClick={() => toggleMenu("notifications")}
            aria-label="Notifications"
            aria-expanded={activeMenu === "notifications"}
          >
            <Bell size={19} />
            <span className="notification-indicator" />
          </button>

          {activeMenu === "notifications" && (
            <div className="header-dropdown">
              <strong>Notifications</strong>
              <p className="dropdown-empty">
                No new notifications.
              </p>
            </div>
          )}
        </div>

        {/* Theme toggle */}
        <div className="header-menu-container">
          <button
            type="button"
            className="theme-toggle"
            onClick={toggleTheme}
            aria-label={themeLabel}
            title={themeLabel}
          >
            <ThemeIcon size={18} strokeWidth={1.8} />
          </button>
        </div>

        {/* Help */}
        <div className="header-menu-container">
          <button
            type="button"
            className="header-icon-button"
            onClick={() => toggleMenu("help")}
            aria-label="Help and support"
            aria-expanded={activeMenu === "help"}
          >
            <CircleHelp size={19} />
          </button>

          {activeMenu === "help" && (
            <div className="header-dropdown">
              <strong>Help & Support</strong>
              <p className="dropdown-empty">
                Help resources will be available here.
              </p>
            </div>
          )}
        </div>

        {/* Profile */}
        <div className="header-menu-container">
          <button
            type="button"
            className="header-profile-button"
            onClick={() => toggleMenu("profile")}
            aria-expanded={activeMenu === "profile"}
            aria-label="Open profile menu"
          >
            <UserCircle size={22} />
            <span className="profile-label">{user.name}</span>
            <ChevronDown size={15} />
          </button>

          {activeMenu === "profile" && (
            <div className="header-dropdown profile-dropdown">
              <div className="profile-dropdown-heading">
                <strong>{user.name}</strong>
                <span>{user.email}</span>
                <span>
                  {user.account_type === "institution"
                    ? user.active_role_assignments?.[0]?.role?.name ??
                      "Institution User"
                    : "Platform Administrator"}
                </span>
              </div>

              <div className="dropdown-divider" />

              <button type="button" disabled>
                <UserCircle size={16} />
                My Profile
              </button>

              <button type="button" onClick={() => { setActiveMenu(null); navigate(securityTarget); }}>
                <Settings size={16} />
                Account Settings
              </button>

              <div className="dropdown-divider" />

              <button
                type="button"
                onClick={handleLogout}
              >
                <LogOut size={16} />
                Sign Out
              </button>
            </div>
          )}
        </div>
      </div>
    </header>
  );
};

export default GlobalHeader;
