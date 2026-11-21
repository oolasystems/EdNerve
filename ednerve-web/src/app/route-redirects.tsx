import { Navigate } from "react-router-dom";

import { useAuth } from "../contexts/AuthContext";
import LoginPage from "../modules/auth/pages/LoginPage";
import { ROUTE_PATHS } from "./route-paths";

function SessionLoading() {
  return (
    <div className="auth-page">
      <div className="auth-card">
        <p>Restoring your session...</p>
      </div>
    </div>
  );
}

function isSupportedAccount(
  user: {
    account_type: string;
    institution_id: number | null;
  } | null | undefined
): boolean {
  return Boolean(
    user &&
      ((user.account_type === "platform" && user.institution_id === null) ||
        (user.account_type === "institution" &&
          user.institution_id !== null))
  );
}

/** Where an authenticated user lands — workspace determined by account type. */
function landingRoute(user: {
  account_type: string;
}): string {
  return user.account_type === "institution"
    ? ROUTE_PATHS.institution.dashboard
    : ROUTE_PATHS.platform.dashboard;
}

/**
 * Handles the login route.
 *
 * Once successfully authenticated, send the user to the workspace shell
 * matching their account type (institution or platform).
 */
export function LoginRoute() {
  const { user, isAuthenticated, isLoading } = useAuth();

  if (isLoading) {
    return <SessionLoading />;
  }

  if (isAuthenticated && isSupportedAccount(user)) {
    return (
      <Navigate
        to={landingRoute(user!)}
        replace
      />
    );
  }

  return <LoginPage />;
}

/**
 * Handles the application root route.
 */
export function HomeRoute() {
  const { user, isAuthenticated, isLoading } = useAuth();

  if (isLoading) {
    return <SessionLoading />;
  }

  if (!isAuthenticated || !user) {
    return (
      <Navigate
        to={ROUTE_PATHS.login}
        replace
      />
    );
  }

  if (isSupportedAccount(user)) {
    return (
      <Navigate
        to={landingRoute(user)}
        replace
      />
    );
  }

  return (
    <Navigate
      to={ROUTE_PATHS.login}
      replace
    />
  );
}
