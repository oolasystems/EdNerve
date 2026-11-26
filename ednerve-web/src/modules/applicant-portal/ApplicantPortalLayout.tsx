import { Outlet, useNavigate } from "react-router-dom";
import { FileText, LogOut, UserRound } from "lucide-react";
import { NavLink } from "react-router-dom";
import { useApplicantAuth } from "./auth-context";
import { applicantInstitutionCode } from "./api";
import "../public-portal/public.css";
import "./applicant-portal.css";
import logo from "../../assets/brand/oolaednerve-logo-light.svg";

export default function ApplicantPortalLayout() {
  const { applicant, logout } = useApplicantAuth();
  const navigate = useNavigate();
  const handleLogout = async () => {
    const code = applicantInstitutionCode.get();
    await logout();
    navigate(code ? `/apply/${encodeURIComponent(code)}/login` : "/", { replace: true });
  };

  return (
    <div className="public-portal applicant-portal">
      <div className="applicant-portal-header">
        <img src={logo} alt="OolaEdNerve" />
        <div className="applicant-portal-user">
          <span><UserRound size={16} /> {applicant?.full_name}</span>
          <button type="button" onClick={handleLogout}><LogOut size={15} /> Sign out</button>
        </div>
      </div>
      <nav className="applicant-workspace-nav"><NavLink to="/applicant/dashboard">Dashboard</NavLink><NavLink to="/applicant/profile">My profile</NavLink><NavLink to="/applicant/applications"><FileText size={15}/> My applications</NavLink></nav>
      <main className="applicant-portal-main"><Outlet /></main>
      <footer className="public-footer">Powered by OolaEdNerve · Oola Systems</footer>
    </div>
  );
}
