import { useState, type FormEvent } from "react";
import { Eye, EyeOff, ShieldCheck } from "lucide-react";
import { Link, useNavigate, useParams, useSearchParams } from "react-router-dom";
import { applicantApi } from "../api";
import { getApplicantApiError } from "../api-error";
import { Alert, Button, FormField, Input } from "../../../shared/ui";
import logo from "../../../assets/brand/oolaednerve-logo-light.svg";
import "../applicant-portal.css";

export default function SetPasswordPage() {
  const [params] = useSearchParams();
  const { institutionCode: routeInstitutionCode = "" } = useParams();
  const institutionCode = routeInstitutionCode || params.get("institution_code") || "";
  const token = params.get("setup_token") ?? "";
  const email = params.get("email") ?? "";
  const navigate = useNavigate();
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmation, setShowConfirmation] = useState(false);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const [complete, setComplete] = useState(false);

  const submit = async (event: FormEvent) => {
    event.preventDefault(); setError("");
    if (password !== confirmation) { setError("Passwords do not match."); return; }
    setBusy(true);
    try {
      await applicantApi.setPassword({ institution_code: institutionCode, email, setup_token: token, password, password_confirmation: confirmation });
      setComplete(true);
    } catch (err) {
      setError(getApplicantApiError(err, "Unable to set your password."));
    } finally { setBusy(false); }
  };

  return (
    <div className="public-portal applicant-auth-page">
      <div className="applicant-auth-wrap">
        <Link to="/" className="applicant-brand"><img src={logo} alt="OolaEdNerve" /></Link>
        <section className="public-portal-card applicant-auth-card">
          <span className="public-portal-eyebrow">Account activation</span>
          <h1 className="public-portal-title">{complete ? "Your account is ready" : "Set your password"}</h1>
          {complete ? (
            <>
              <Alert tone="success">Your password has been set. You can now sign in to the applicant portal.</Alert>
              <Button fullWidth onClick={() => navigate(`/apply/${institutionCode}/login`)}>Continue to sign in</Button>
            </>
          ) : (
            <form className="applicant-auth-form" onSubmit={submit}>
              {!institutionCode || !token || !email ? <Alert tone="error">The password setup link is incomplete. Please use the link from your email.</Alert> : null}
              <FormField label="New password" htmlFor="password" required hint="Use at least 12 characters (the server enforces this requirement).">
                <div className="applicant-password-control">
                  <Input id="password" type={showPassword ? "text" : "password"} value={password} onChange={e => setPassword(e.target.value)} minLength={12} maxLength={128} autoComplete="new-password" required />
                  <button type="button" className="applicant-password-toggle" onClick={() => setShowPassword(value => !value)} aria-label={showPassword ? "Hide password" : "Show password"} aria-pressed={showPassword}>
                    {showPassword ? <EyeOff size={17} /> : <Eye size={17} />}
                  </button>
                </div>
              </FormField>
              <FormField label="Confirm password" htmlFor="confirmation" required>
                <div className="applicant-password-control">
                  <Input id="confirmation" type={showConfirmation ? "text" : "password"} value={confirmation} onChange={e => setConfirmation(e.target.value)} minLength={12} maxLength={128} autoComplete="new-password" required />
                  <button type="button" className="applicant-password-toggle" onClick={() => setShowConfirmation(value => !value)} aria-label={showConfirmation ? "Hide confirmation" : "Show confirmation"} aria-pressed={showConfirmation}>
                    {showConfirmation ? <EyeOff size={17} /> : <Eye size={17} />}
                  </button>
                </div>
              </FormField>
              <p className="applicant-auth-security"><ShieldCheck size={15} /> Your password is transmitted over the configured secure API connection and is never displayed in confirmation messages.</p>
              {error && <Alert tone="error">{error}</Alert>}
              <Button type="submit" fullWidth loading={busy} disabled={!institutionCode || !token || !email}>Set password</Button>
            </form>
          )}
        </section>
      </div>
    </div>
  );
}
