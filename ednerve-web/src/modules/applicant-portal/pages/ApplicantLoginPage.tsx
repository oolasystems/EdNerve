import { useState, type FormEvent } from "react";
import { Link, useNavigate, useParams, useSearchParams } from "react-router-dom";
import { Eye, EyeOff, LogIn, ShieldCheck } from "lucide-react";
import { useApplicantAuth } from "../auth-context";
import { getApplicantApiError } from "../api-error";
import { Alert, Button, FormField, Input } from "../../../shared/ui";
import logo from "../../../assets/brand/oolaednerve-logo-light.svg";
import "../applicant-portal.css";

export default function ApplicantLoginPage() {
  const { institutionCode = "" } = useParams();
  const [searchParams] = useSearchParams();
  const { login } = useApplicantAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  const submit = async (event: FormEvent) => {
    event.preventDefault(); setError(""); setBusy(true);
    try {
      await login({ institution_code: institutionCode, email: email.trim().toLowerCase(), password });
      const returnTo = searchParams.get("returnTo");
      const safeReturnTo = returnTo && returnTo.startsWith("/") && !returnTo.startsWith("//")
        ? returnTo
        : `/applicant/dashboard?institutionCode=${encodeURIComponent(institutionCode)}`;
      navigate(safeReturnTo, { replace: true });
    } catch (err) {
      setError(getApplicantApiError(err, "Unable to sign in. Check your institution code, email and password."));
    } finally { setBusy(false); }
  };

  return (
    <div className="public-portal applicant-auth-page">
      <div className="applicant-auth-wrap">
        <Link to={`/apply/${institutionCode}`} className="applicant-brand"><img src={logo} alt="OolaEdNerve" /></Link>
        <section className="public-portal-card applicant-auth-card">
          <span className="public-portal-eyebrow">Applicant portal · {institutionCode.toUpperCase()}</span>
          <h1 className="public-portal-title">Welcome back</h1>
          <p className="public-portal-subtitle">Sign in to continue to your applicant account.</p>
          <form className="applicant-auth-form" onSubmit={submit}>
            <FormField label="Email address" htmlFor="email" required>
              <Input id="email" type="email" value={email} onChange={e => setEmail(e.target.value)} autoComplete="username" required />
            </FormField>
            <FormField label="Password" htmlFor="password" required>
              <div className="applicant-password-control">
                <Input id="password" type={showPassword ? "text" : "password"} value={password} onChange={e => setPassword(e.target.value)} autoComplete="current-password" required />
                <button type="button" className="applicant-password-toggle" onClick={() => setShowPassword(value => !value)} aria-label={showPassword ? "Hide password" : "Show password"} aria-pressed={showPassword}>
                  {showPassword ? <EyeOff size={17} /> : <Eye size={17} />}
                </button>
              </div>
            </FormField>
            {error && <Alert tone="error">{error}</Alert>}
            <Button type="submit" fullWidth loading={busy} icon={LogIn}>Sign in</Button>
          </form>
          <div className="applicant-auth-links applicant-auth-links-stack">
            <p>New applicant? <Link to={`/apply/${institutionCode}/register`}>Create an account</Link></p>
            <p>Have not received the activation email? <Link to={`/apply/${institutionCode}/resend-verification`}>Resend verification</Link></p>
            <p>Already submitted an application? <Link to={`/apply/${institutionCode}/status`}>Track your application</Link></p>
          </div>
          <p className="applicant-auth-security"><ShieldCheck size={15} /> Your applicant account is protected by secure sign-in.</p>
        </section>
      </div>
    </div>
  );
}
