import { useEffect, useState, type FormEvent } from "react";
import { applicantApi, type ApplicantProfile } from "../api";
import { getApplicantApiError } from "../api-error";
import { useApplicantAuth } from "../auth-context";
import { Alert, Button, Card, FormField, Input, LoadingState } from "../../../shared/ui";

export default function ApplicantProfilePage() {
  const { applicant } = useApplicantAuth();
  const [profile, setProfile] = useState<ApplicantProfile & { mobile_verified_at?: string | null } | null>(null);
  const [form, setForm] = useState({ full_name: "", mobile_country_code: "+91", mobile: "" });
  const [busy, setBusy] = useState(false); const [loading, setLoading] = useState(true);
  const [message, setMessage] = useState(""); const [error, setError] = useState("");
  useEffect(() => { applicantApi.profile().then(p => { setProfile(p); setForm({ full_name: p.full_name, mobile_country_code: p.mobile_country_code || "+91", mobile: p.mobile || "" }); }).catch(e => setError(getApplicantApiError(e,"Unable to load profile."))).finally(() => setLoading(false)); }, []);
  const submit = async (e: FormEvent) => { e.preventDefault(); setBusy(true); setError(""); setMessage(""); try { const p = await applicantApi.updateProfile(form); setProfile(p); setMessage("Profile updated successfully."); } catch(e) { setError(getApplicantApiError(e,"Unable to save profile.")); } finally { setBusy(false); } };
  if (loading) return <LoadingState message="Loading profile..." />;
  return <div className="applicant-page"><span className="public-portal-eyebrow">Applicant workspace</span><h1 className="public-portal-title">My profile</h1><p className="public-portal-subtitle">Manage your contact details and verification status.</p><Card><div className="applicant-profile-status"><span>Email: <strong>{profile?.email_verified_at ? "Verified" : "Pending"}</strong></span><span>Mobile: <strong>{profile?.mobile_verified_at ? "Verified" : "Not verified"}</strong></span></div><form className="applicant-auth-form" onSubmit={submit}><FormField label="Full name" htmlFor="full_name" required><Input id="full_name" value={form.full_name} onChange={e=>setForm({...form,full_name:e.target.value})} required maxLength={150}/></FormField><FormField label="Email address" htmlFor="email"><Input id="email" value={profile?.email || applicant?.email || ""} disabled/><small className="field-hint">Email address cannot be changed here.</small></FormField><div className="applicant-phone-grid"><FormField label="Country code" htmlFor="mobile_country_code"><Input id="mobile_country_code" value={form.mobile_country_code} onChange={e=>setForm({...form,mobile_country_code:e.target.value})}/></FormField><FormField label="Mobile number" htmlFor="mobile"><Input id="mobile" value={form.mobile} onChange={e=>setForm({...form,mobile:e.target.value})}/></FormField></div>{message && <Alert tone="success">{message}</Alert>}{error && <Alert tone="error">{error}</Alert>}<Button type="submit" loading={busy}>Save profile</Button></form></Card></div>;
}
