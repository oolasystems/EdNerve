import { useState } from "react";
import { NavLink, Outlet, useNavigate } from "react-router-dom";
import { FileText, LayoutDashboard, LogOut, Menu, UserRound, X } from "lucide-react";
import { useApplicantAuth } from "./auth-context";
import { applicantInstitutionCode } from "./api";
import "../public-portal/public.css";
import "./applicant-portal.css";
import logo from "../../assets/brand/oolaednerve-logo-light.svg";

export default function ApplicantPortalLayout() {
  const { applicant, logout } = useApplicantAuth();
  const navigate = useNavigate();
  const [menuOpen, setMenuOpen] = useState(false);

  const handleLogout = async () => {
    const code = applicantInstitutionCode.get();
    await logout();
    navigate(code ? `/apply/${encodeURIComponent(code)}/login` : "/", { replace: true });
  };

  const closeMenu = () => setMenuOpen(false);

  return (
    <div className="public-portal applicant-portal">
      <header className="applicant-portal-header">
        <NavLink to="/applicant/dashboard" className="applicant-portal-brand" aria-label="OolaEdNerve applicant dashboard">
          <img src={logo} alt="OolaEdNerve" />
          <span>Applicant Portal</span>
        </NavLink>
        <div className="applicant-portal-user">
          <span className="applicant-portal-user-name"><UserRound size={16} aria-hidden="true" /> {applicant?.full_name || "Applicant"}</span>
          <button type="button" onClick={handleLogout}><LogOut size={15} aria-hidden="true" /> Sign out</button>
          <button type="button" className="applicant-mobile-menu-toggle" onClick={() => setMenuOpen(value => !value)} aria-expanded={menuOpen} aria-controls="applicant-workspace-navigation" aria-label={menuOpen ? "Close navigation menu" : "Open navigation menu"}>
            {menuOpen ? <X size={19} /> : <Menu size={19} />}
          </button>
        </div>
      </header>

      <nav id="applicant-workspace-navigation" className={`applicant-workspace-nav${menuOpen ? " is-open" : ""}`} aria-label="Applicant workspace">
        <NavLink to="/applicant/dashboard" onClick={closeMenu} end><LayoutDashboard size={16} aria-hidden="true" /> Dashboard</NavLink>
        <NavLink to="/applicant/profile" onClick={closeMenu}><UserRound size={16} aria-hidden="true" /> My profile</NavLink>
        <NavLink to="/applicant/applications" onClick={closeMenu}><FileText size={16} aria-hidden="true" /> My applications</NavLink>
      </nav>

      <main className="applicant-portal-main"><Outlet /></main>
      <footer className="public-footer">Powered by OolaEdNerve · Oola Systems</footer>
    </div>
  );
}
