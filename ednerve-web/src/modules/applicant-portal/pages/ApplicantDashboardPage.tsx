import { FileText, MailCheck, UserRound } from "lucide-react";
import { useApplicantAuth } from "../auth-context";
import { Alert, Card } from "../../../shared/ui";

export default function ApplicantDashboardPage() {
  const { applicant } = useApplicantAuth();
  return (
    <div className="applicant-dashboard">
      <span className="public-portal-eyebrow">Applicant workspace</span>
      <h1 className="public-portal-title">Welcome, {applicant?.full_name}</h1>
      <p className="public-portal-subtitle">Your applicant account is active. This workspace will provide access to your admission applications and updates.</p>
      {!applicant?.email_verified_at && <Alert tone="warning">Your email verification is pending. Please verify your email to access all applicant services.</Alert>}
      <div className="applicant-dashboard-grid">
        <Card><UserRound size={22}/><h2>Profile</h2><p>{applicant?.full_name}</p><small>{applicant?.email}</small></Card>
        <Card><MailCheck size={22}/><h2>Email status</h2><p>{applicant?.email_verified_at ? "Verified" : "Pending verification"}</p></Card>
        <Card><FileText size={22}/><h2>Applications</h2><p>Application management will be available in the next portal module.</p></Card>
      </div>
    </div>
  );
}
