import { Navigate, Outlet, useLocation } from "react-router-dom";

import { useAuth } from "../../contexts/AuthContext";

/**
 * Institution workspace guard.
 *
 * Institution accounts (institution_id set) pass to the Institution
 * workspace. Platform accounts are sent to their own workspace; anything
 * else is sent to login (authentication handles failure).
 */
export default function InstitutionGuard() {
  const { user, isAuthenticated, isLoading } = useAuth();
  const location = useLocation();

  // Wait until the existing session restoration is complete.
  if (isLoading) {
    return (
      <div className="page-container page-container-centered">
        <div className="page-card page-card-auth">
          <p>Restoring your session...</p>
        </div>
      </div>
    );
  }

  // Redirect unauthenticated users to login.
  if (!isAuthenticated || !user) {
    return (
      <Navigate
        to="/login"
        replace
        state={{ from: location }}
      />
    );
  }

  // Platform accounts belong to the Platform Administration workspace.
  if (
    user.account_type === "platform" &&
    user.institution_id === null
  ) {
    return (
      <Navigate
        to="/platform/dashboard"
        replace
      />
    );
  }

  // Any account carrying an institution id may enter the institution
  // workspace; catalogue permissions gate the screens inside.
  if (
    user.account_type === "institution" &&
    user.institution_id !== null
  ) {
    return <Outlet />;
  }

  return (
    <div className="page-container page-container-centered">
      <div className="page-card page-card-auth">
        <h2>Access Denied</h2>
        <p>
          Your account does not have permission to access the
          Institution workspace.
        </p>
      </div>
    </div>
  );
}
