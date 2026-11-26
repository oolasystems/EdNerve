import { useState, type FormEvent } from "react";
import { Link, useParams } from "react-router-dom";
import { applicantApi } from "../api";
import { getApplicantApiError } from "../api-error";
import { Alert, Button, FormField, Input } from "../../../shared/ui";
import logo from "../../../assets/brand/oolaednerve-logo-light.svg";
import "../applicant-portal.css";

export default function ResendVerificationPage() {
  const { institutionCode = "" } = useParams();
  const [email, setEmail] = useState("");
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  const submit = async (event: FormEvent) => {
    event.preventDefault(); setError(""); setMessage(""); setBusy(true);
    try {
      const result = await applicantApi.resendVerification({
        institution_code: institutionCode.toUpperCase(),
        email: email.trim().toLowerCase(),
      });
      setMessage(result.message || "If the account requires verification, a new email has been sent.");
    } catch (err) {
      setError(getApplicantApiError(err, "Unable to process your request."));
    } finally { setBusy(false); }
  };

  return (
    <div className="public-portal applicant-auth-page">
      <div className="applicant-auth-wrap">
        <Link to={`/apply/${institutionCode}/login`} className="applicant-brand"><img src={logo} alt="OolaEdNerve" /></Link>
        <section className="public-portal-card applicant-auth-card">
          <span className="public-portal-eyebrow">Email verification</span>
          <h1 className="public-portal-title">Resend verification email</h1>
          <form className="applicant-auth-form" onSubmit={submit}>
            <FormField label="Email address" htmlFor="email" required>
              <Input id="email" type="email" value={email} onChange={e => setEmail(e.target.value)} required autoComplete="email" />
            </FormField>
            {error && <Alert tone="error">{error}</Alert>}
            {message && <Alert tone="success">{message}</Alert>}
            <Button type="submit" fullWidth loading={busy}>Send verification email</Button>
          </form>
          <p className="applicant-auth-links"><Link to={`/apply/${institutionCode}/login`}>Back to sign in</Link></p>
        </section>
      </div>
    </div>
  );
}
