import { useCallback, useEffect, useState, type FormEvent } from "react";
import { BadgeCheck, Mail, Phone, Save, ShieldCheck, UserRound } from "lucide-react";
import { applicantApi, type ApplicantProfile } from "../api";
import { getApplicantApiError } from "../api-error";
import { useApplicantAuth } from "../auth-context";
import { Alert, Button, Card, FormField, Input, LoadingState } from "../../../shared/ui";

type EditableProfile = { full_name: string; mobile_country_code: string; mobile: string };
const emptyForm: EditableProfile = { full_name: "", mobile_country_code: "+91", mobile: "" };

export default function ApplicantProfilePage() {
  const { applicant } = useApplicantAuth();
  const [profile, setProfile] = useState<(ApplicantProfile & { mobile_verified_at?: string | null }) | null>(null);
  const [form, setForm] = useState<EditableProfile>(emptyForm);
  const [busy, setBusy] = useState(false);
  const [loading, setLoading] = useState(true);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  const loadProfile = useCallback(async () => {
    try {
      const result = await applicantApi.profile();
      setProfile(result);
      setError("");
      setForm({
        full_name: result.full_name || "",
        mobile_country_code: result.mobile_country_code || "+91",
        mobile: result.mobile || "",
      });
    } catch (requestError) {
      setError(getApplicantApiError(requestError, "Unable to load your profile."));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    let active = true;

    const fetchInitialProfile = async () => {
      try {
        const result = await applicantApi.profile();
        if (active) {
          setProfile(result);
          setError("");
          setForm({
            full_name: result.full_name || "",
            mobile_country_code: result.mobile_country_code || "+91",
            mobile: result.mobile || "",
          });
        }
      } catch (requestError) {
        if (active) {
          setError(getApplicantApiError(requestError, "Unable to load your profile."));
        }
      } finally {
        if (active) {
          setLoading(false);
        }
      }
    };

    void fetchInitialProfile();

    return () => {
      active = false;
    };
  }, []);

  const update = (key: keyof EditableProfile, value: string) => {
    setForm(current => ({ ...current, [key]: value }));
    setMessage("");
  };

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    setBusy(true);
    setError("");
    setMessage("");
    try {
      const updated = await applicantApi.updateProfile({
        full_name: form.full_name.trim(),
        mobile_country_code: form.mobile_country_code.trim() || null,
        mobile: form.mobile.trim() || null,
      });
      setProfile(updated);
      setForm({
        full_name: updated.full_name || "",
        mobile_country_code: updated.mobile_country_code || "+91",
        mobile: updated.mobile || "",
      });
      setMessage("Your profile details have been updated.");
    } catch (requestError) {
      setError(getApplicantApiError(requestError, "Unable to save your profile."));
    } finally {
      setBusy(false);
    }
  };

  if (loading) return <LoadingState message="Loading your profile..." />;

  if (!profile) {
    return (
      <div className="applicant-page">
        <span className="public-portal-eyebrow">Applicant workspace</span>
        <h1 className="public-portal-title">My profile</h1>
        {error && <Alert tone="error">{error}</Alert>}
        <Button variant="secondary" onClick={() => { setLoading(true); void loadProfile(); }}>Try again</Button>
      </div>
    );
  }

  return (
    <div className="applicant-page applicant-profile-page">
      <div className="applicant-page-heading">
        <div>
          <span className="public-portal-eyebrow">Applicant workspace</span>
          <h1 className="public-portal-title">My profile</h1>
          <p className="public-portal-subtitle">Keep your contact information accurate so the admissions office can reach you.</p>
        </div>
      </div>

      <Card className="applicant-profile-summary">
        <div className="applicant-profile-avatar"><UserRound size={25} /></div>
        <div className="applicant-profile-summary-copy">
          <h2>{profile.full_name}</h2>
          <p>{profile.institution?.name || applicant?.institution?.name || "Applicant account"}</p>
          <span><Mail size={14} /> {profile.email}</span>
        </div>
        <div className="applicant-profile-verification">
          <span className={profile.email_verified_at ? "is-verified" : "is-pending"}><BadgeCheck size={15} /> Email {profile.email_verified_at ? "verified" : "pending"}</span>
          <span className={profile.mobile_verified_at ? "is-verified" : "is-pending"}><Phone size={14} /> Mobile {profile.mobile_verified_at ? "verified" : "not verified"}</span>
        </div>
      </Card>

      {error && <Alert tone="error">{error}</Alert>}
      {message && <Alert tone="success">{message}</Alert>}

      <Card className="applicant-profile-edit-card">
        <div className="applicant-profile-card-heading">
          <div><h2>Personal and contact details</h2><p>Fields marked with an asterisk are required.</p></div>
          <ShieldCheck size={21} aria-hidden="true" />
        </div>
        <form className="applicant-profile-form" onSubmit={submit}>
          <FormField label="Full name" htmlFor="full_name" required>
            <Input id="full_name" value={form.full_name} onChange={event => update("full_name", event.target.value)} autoComplete="name" maxLength={150} required />
          </FormField>
          <FormField label="Email address" htmlFor="email">
            <Input id="email" type="email" value={profile.email || applicant?.email || ""} disabled />
            <small className="field-hint">Your registered email address cannot be changed here.</small>
          </FormField>
          <div className="applicant-profile-phone-grid">
            <FormField label="Country calling code" htmlFor="mobile_country_code">
              <Input id="mobile_country_code" type="tel" value={form.mobile_country_code} onChange={event => update("mobile_country_code", event.target.value)} autoComplete="tel-country-code" maxLength={8} />
            </FormField>
            <FormField label="Mobile number" htmlFor="mobile">
              <Input id="mobile" type="tel" value={form.mobile} onChange={event => update("mobile", event.target.value)} autoComplete="tel-national" maxLength={20} />
            </FormField>
          </div>
          <div className="applicant-profile-form-actions">
            <p>Changes are saved to your applicant profile.</p>
            <Button type="submit" icon={Save} loading={busy} disabled={busy || !form.full_name.trim()}>Save changes</Button>
          </div>
        </form>
      </Card>
    </div>
  );
}
