import { useState, type FormEvent } from "react";
import { useNavigate, useLocation, Navigate } from "react-router-dom";
import { Eye, EyeOff, LockKeyhole, Sun, Moon, Monitor, ShieldCheck } from "lucide-react";
import axios from "axios";

import { useAuth } from "../../../contexts/AuthContext";
import { useTheme } from "../../../contexts/ThemeProvider";
import "../auth.css";
import logo from "../../../assets/brand/oolaednerve-logo.svg";
import logoLight from "../../../assets/brand/oolaednerve-logo-light.svg";

const AuthThemeToggle = () => {
  const { preference, toggle } = useTheme();

  const Icon =
    preference === "dark" ? Moon : preference === "system" ? Monitor : Sun;

  const label =
    preference === "dark"
      ? "Switch to System theme"
      : preference === "system"
        ? "Switch to Light theme"
        : "Switch to Dark theme";

  return (
    <button
      type="button"
      className="theme-toggle auth-theme-toggle"
      onClick={toggle}
      aria-label={label}
      title={label}
    >
      <Icon size={18} strokeWidth={1.8} />
    </button>
  );
};

function MfaChallengePage() {
  const { completeMfa } = useAuth();
  const { resolvedTheme } = useTheme();
  const navigate = useNavigate();
  const location = useLocation();

  const mfaToken = (location.state as { mfaToken?: string } | null)?.mfaToken;

  const [code, setCode] = useState("");
  const [showCode, setShowCode] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState("");

  // No token means we arrived without a password step — bounce to login.
  if (!mfaToken) {
    return <Navigate to="/login" replace />;
  }

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    setErrorMessage("");
    setIsSubmitting(true);

    try {
      await completeMfa(mfaToken, code.trim());
      // Authenticated now — the home route redirects to the right workspace.
      navigate("/", { replace: true });
    } catch (error) {
      if (axios.isAxiosError(error)) {
        setErrorMessage(
          error.response?.data?.errors?.code?.[0] ||
            error.response?.data?.message ||
            "Invalid code. Try again."
        );
      } else if (error instanceof Error) {
        setErrorMessage(error.message);
      } else {
        setErrorMessage("An unexpected error occurred.");
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="auth-page">
      <AuthThemeToggle />

      <div className="page-card auth-card">
        <div className="auth-brand">
          <img
            src={resolvedTheme === "dark" ? logoLight : logo}
            alt="OolaEdNerve - Higher Education ERP Platform"
          />
        </div>

        <div className="page-heading">
          <h1>Two-step verification</h1>
          <p>Enter the 6-digit code from your authenticator app, or one of your recovery codes.</p>
        </div>

        <form onSubmit={handleSubmit} className="auth-form">
          {errorMessage && (
            <div className="form-error" role="alert">
              {errorMessage}
            </div>
          )}

          <div className="form-group">
            <label htmlFor="mfa-code" className="form-label">
              Verification code
            </label>

            <div className="form-control-wrapper">
              <ShieldCheck size={19} className="form-control-icon" />
              <input
                id="mfa-code"
                type={showCode ? "text" : "password"}
                inputMode="numeric"
                className="form-control form-control-with-icon"
                placeholder="123456"
                value={code}
                onChange={(event) => setCode(event.target.value)}
                autoComplete="one-time-code"
                autoFocus
                required
                minLength={6}
                disabled={isSubmitting}
              />
              <button
                type="button"
                className="form-control-toggle"
                onClick={() => setShowCode((v) => !v)}
                aria-label={showCode ? "Hide code" : "Show code"}
                tabIndex={-1}
              >
                {showCode ? <EyeOff size={18} /> : <Eye size={18} />}
              </button>
            </div>
          </div>

          <button type="submit" className="btn btn-primary btn-block" disabled={isSubmitting}>
            <LockKeyhole size={17} />
            {isSubmitting ? "Verifying..." : "Verify & sign in"}
          </button>

          <button
            type="button"
            className="btn btn-ghost"
            onClick={() => navigate("/login")}
            disabled={isSubmitting}
          >
            Use a different account
          </button>
        </form>
      </div>
    </div>
  );
}

export default MfaChallengePage;
