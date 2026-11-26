import { Navigate, Outlet, useLocation } from "react-router-dom";
import { useApplicantAuth } from "./auth-context";
import { applicantInstitutionCode } from "./api";
import { LoadingState } from "../../shared/ui";

export default function ApplicantGuard() {
  const { isAuthenticated, isLoading } = useApplicantAuth();
  const location = useLocation();

  if (isLoading) return <LoadingState message="Loading applicant portal..." />;

  if (isAuthenticated) return <Outlet />;

  const params = new URLSearchParams(location.search);
  const institutionCode =
    params.get("institutionCode") ||
    applicantInstitutionCode.get();

  if (!institutionCode) {
    return <Navigate to="/" replace />;
  }

  const returnTo = `${location.pathname}${location.search}`;
  return (
    <Navigate
      to={`/apply/${encodeURIComponent(institutionCode)}/login?returnTo=${encodeURIComponent(returnTo)}`}
      replace
    />
  );
}
