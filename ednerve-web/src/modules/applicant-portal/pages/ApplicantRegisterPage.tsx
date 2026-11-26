import { useState, type FormEvent } from "react";
import { Link, useParams } from "react-router-dom";
import { UserPlus } from "lucide-react";
import { applicantApi } from "../api";
import { getApplicantApiError } from "../api-error";
import { Alert, Button } from "../../../shared/ui";
import { FormField, Input } from "../../../shared/ui";
import logo from "../../../assets/brand/oolaednerve-logo-light.svg";
import "../applicant-portal.css";

export default function ApplicantRegisterPage() {
  const { institutionCode = "" } = useParams();
  const [form, setForm] = useState({ full_name: "", email: "", mobile_country_code: "+91", mobile: "" });
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const [sent, setSent] = useState(false);

  const update = (key: keyof typeof form) => (value: string) =>
    setForm((current) => ({ ...current, [key]: value }));

  const submit = async (event: FormEvent) => {
    event.preventDefault(); setError(""); setBusy(true);
    try {
      await applicantApi.register({
        institution_code: institutionCode.toUpperCase(),
        full_name: form.full_name.trim(),
        email: form.email.trim().toLowerCase(),
        mobile_country_code: form.mobile_country_code.trim(),
        mobile: form.mobile.trim(),
      });
      setSent(true);
    } catch (err) {
      setError(getApplicantApiError(err, "Unable to register. Please try again."));
    } finally { setBusy(false); }
  };

  return (
    <div className="public-portal applicant-auth-page">
      <div className="applicant-auth-wrap">
        <Link to={`/apply/${institutionCode}`} className="applicant-brand"><img src={logo} alt="OolaEdNerve" /></Link>
        <section className="public-portal-card applicant-auth-card">
          {sent ? (
            <>
              <span className="public-portal-eyebrow">Registration received</span>
              <h1 className="public-portal-title">Check your email</h1>
              <p className="public-portal-subtitle">If the registration can be completed, an email verification link has been sent to <strong>{form.email}</strong>. Follow the link to verify your email and set your password.</p>
              <Alert tone="success">Please check your inbox and spam folder.</Alert>
              <p className="applicant-auth-links"><Link to={`/apply/${institutionCode}/login`}>Already verified? Sign in</Link></p>
            </>
          ) : (
            <>
              <span className="public-portal-eyebrow">Applicant account</span>
              <h1 className="public-portal-title">Create your account</h1>
              <p className="public-portal-subtitle">Register to access your admission applications for {institutionCode.toUpperCase()}.</p>
              <form className="applicant-auth-form" onSubmit={submit}>
                <FormField label="Full name" htmlFor="full_name" required>
                  <Input id="full_name" value={form.full_name} onChange={e => update("full_name")(e.target.value)} autoComplete="name" required maxLength={180} />
                </FormField>
                <FormField label="Email address" htmlFor="email" required>
                  <Input id="email" type="email" value={form.email} onChange={e => update("email")(e.target.value)} autoComplete="email" required maxLength={190} />
                </FormField>
                <div className="applicant-phone-grid">
                  <FormField label="Code" htmlFor="mobile_country_code">
                    <Input id="mobile_country_code" value={form.mobile_country_code} onChange={e => update("mobile_country_code")(e.target.value)} maxLength={8} />
                  </FormField>
                  <FormField label="Mobile number" htmlFor="mobile" required>
                    <Input id="mobile" type="tel" value={form.mobile} onChange={e => update("mobile")(e.target.value)} autoComplete="tel-national" required maxLength={30} />
                  </FormField>
                </div>
                {error && <Alert tone="error">{error}</Alert>}
                <Button type="submit" fullWidth loading={busy} icon={UserPlus}>Create account</Button>
              </form>
              <p className="applicant-auth-links">Already registered? <Link to={`/apply/${institutionCode}/login`}>Sign in</Link></p>
            </>
          )}
        </section>
        <p className="public-footer">© {new Date().getFullYear()} Oola Systems. All rights reserved.</p>
      </div>
    </div>
  );
}
