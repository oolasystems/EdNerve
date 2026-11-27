import { useCallback, useEffect, useState } from "react";
import { ArrowRight, ClipboardList, FilePlus2, FileText, RefreshCw, UserRound } from "lucide-react";
import { Link } from "react-router-dom";
import { useApplicantAuth } from "../auth-context";
import { applicantApi, type ApplicantApplication } from "../api";
import { getApplicantApiError } from "../api-error";
import { Alert, Button, Card, LoadingState } from "../../../shared/ui";

export default function ApplicantDashboardPage() {
  const { applicant } = useApplicantAuth();
  const [applications, setApplications] = useState<ApplicantApplication[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const loadApplications = useCallback(async () => {
    try {
      setApplications(await applicantApi.applications());
      setError("");
    } catch (requestError) {
      setError(getApplicantApiError(requestError, "Unable to load your application summary."));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    let active = true;

    const fetchInitialApplications = async () => {
      try {
        const result = await applicantApi.applications();
        if (active) {
          setApplications(result);
          setError("");
        }
      } catch (requestError) {
        if (active) {
          setError(getApplicantApiError(requestError, "Unable to load your application summary."));
        }
      } finally {
        if (active) {
          setLoading(false);
        }
      }
    };

    void fetchInitialApplications();

    return () => {
      active = false;
    };
  }, []);

  const drafts = applications.filter(item => item.status === "draft");
  const submitted = applications.filter(item => item.status !== "draft");
  const recent = [...applications].sort((a, b) =>
    (b.updated_at || "").localeCompare(a.updated_at || ""),
  ).slice(0, 3);

  return (
    <div className="applicant-page applicant-dashboard">
      <div className="applicant-page-heading applicant-dashboard-heading">
        <div>
          <span className="public-portal-eyebrow">Applicant workspace</span>
          <h1 className="public-portal-title">Welcome, {applicant?.full_name || "Applicant"}</h1>
          <p className="public-portal-subtitle">Manage your profile and keep track of your admission applications.</p>
        </div>
        <Link to="/applicant/applications/new" className="btn btn-primary"><FilePlus2 size={16} /> Start an application</Link>
      </div>

      {!applicant?.email_verified_at && (
        <Alert tone="warning">Your email verification is pending. Please complete email verification to access all applicant services.</Alert>
      )}
      {error && <Alert tone="error">{error}</Alert>}

      <div className="applicant-dashboard-grid">
        <Card className="applicant-dashboard-stat">
          <span className="applicant-dashboard-stat-icon"><FileText size={20} /></span>
          <span className="applicant-dashboard-stat-label">Total applications</span>
          <strong>{loading ? "—" : applications.length}</strong>
          <Link to="/applicant/applications">View all applications <ArrowRight size={14} /></Link>
        </Card>
        <Card className="applicant-dashboard-stat">
          <span className="applicant-dashboard-stat-icon"><ClipboardList size={20} /></span>
          <span className="applicant-dashboard-stat-label">Drafts to complete</span>
          <strong>{loading ? "—" : drafts.length}</strong>
          <Link to="/applicant/applications">Continue a draft <ArrowRight size={14} /></Link>
        </Card>
        <Card className="applicant-dashboard-stat">
          <span className="applicant-dashboard-stat-icon"><FilePlus2 size={20} /></span>
          <span className="applicant-dashboard-stat-label">Non-draft applications</span>
          <strong>{loading ? "—" : submitted.length}</strong>
          <Link to="/applicant/applications">Check application status <ArrowRight size={14} /></Link>
        </Card>
      </div>

      <section className="applicant-dashboard-section">
        <div className="applicant-section-heading">
          <div><h2>Recent applications</h2><p>Your latest application activity.</p></div>
          <Button variant="ghost" size="sm" icon={RefreshCw} onClick={() => { setLoading(true); void loadApplications(); }} loading={loading}>Refresh</Button>
        </div>
        {loading ? <LoadingState message="Loading your applications..." /> : recent.length === 0 ? (
          <Card className="applicant-workspace-empty">
            <span><FileText size={22} /></span>
            <h3>Your admission journey starts here</h3>
            <p>You have not started an application yet. Browse the available programmes and choose an intake to create your first draft.</p>
            <Link to="/applicant/applications/new" className="btn btn-primary">Browse available programmes</Link>
          </Card>
        ) : (
          <div className="applicant-application-list">
            {recent.map(item => (
              <Card key={item.id}>
                <div className="applicant-application-row">
                  <div>
                    <strong>{item.programme || "Programme"}</strong>
                    <p>{[item.intake, item.academic_session].filter(Boolean).join(" · ") || "Admission intake"}</p>
                    <small>{item.application_number}</small>
                  </div>
                  <div>
                    <span className={`applicant-status ${item.status}`}>{item.status.replaceAll("_", " ")}</span>
                    <Link to={`/applicant/applications/${item.id}`} className="btn btn-secondary btn-sm">{item.status === "draft" ? "Continue draft" : "View details"}</Link>
                  </div>
                </div>
              </Card>
            ))}
          </div>
        )}
      </section>

      <section className="applicant-dashboard-profile">
        <Card>
          <div className="applicant-dashboard-profile-icon"><UserRound size={20} /></div>
          <div><h2>Your applicant profile</h2><p>{applicant?.email || "Email address unavailable"}</p></div>
          <Link to="/applicant/profile" className="btn btn-secondary btn-sm">View profile <ArrowRight size={14} /></Link>
        </Card>
      </section>
    </div>
  );
}
