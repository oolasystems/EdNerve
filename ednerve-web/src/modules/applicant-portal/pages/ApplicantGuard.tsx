import { Navigate, Outlet, useSearchParams } from "react-router-dom";
import { useApplicantAuth } from "../auth-context";
import { LoadingState } from "../../../shared/ui";

export default function ApplicantGuard() {
  const { isAuthenticated, isLoading } = useApplicantAuth();
  const [searchParams] = useSearchParams();
  const institutionCode = searchParams.get("institution_code")?.trim();

  if (isLoading) return <LoadingState message="Loading applicant portal..." />;

  if (!isAuthenticated) {
    const loginPath = institutionCode
      ? `/apply/${encodeURIComponent(institutionCode)}/login`
      : "/";

    return <Navigate to={loginPath} replace />;
  }

  return <Outlet />;
}
