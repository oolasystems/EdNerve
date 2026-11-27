import { useCallback, useEffect, useState } from "react";
import { FilePlus2, RefreshCw } from "lucide-react";
import { Link } from "react-router-dom";
import { applicantApi, type ApplicantApplication } from "../api";
import { getApplicantApiError } from "../api-error";
import { Alert, Button, Card, LoadingState } from "../../../shared/ui";

const formatStatus = (status: string) => status.replaceAll("_", " ");

export default function ApplicantApplicationsPage() {
  const [items, setItems] = useState<ApplicantApplication[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const load = useCallback(async () => {
    try {
      const applications = await applicantApi.applications();
      setItems([...applications].sort((a, b) => (b.updated_at || "").localeCompare(a.updated_at || "")));
      setError("");
    } catch (requestError) {
      setError(getApplicantApiError(requestError, "Unable to load applications."));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    let active = true;

    const fetchInitialApplications = async () => {
      try {
        const applications = await applicantApi.applications();
        if (active) {
          setItems([...applications].sort((a, b) => (b.updated_at || "").localeCompare(a.updated_at || "")));
          setError("");
        }
      } catch (requestError) {
        if (active) {
          setError(getApplicantApiError(requestError, "Unable to load applications."));
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

  return (
    <div className="applicant-page">
      <div className="applicant-page-heading">
        <div>
          <span className="public-portal-eyebrow">Applicant workspace</span>
          <h1 className="public-portal-title">My applications</h1>
          <p className="public-portal-subtitle">Review your applications or continue completing a saved draft.</p>
        </div>
        <Link className="btn btn-primary" to="/applicant/applications/new"><FilePlus2 size={16} /> New application</Link>
      </div>

      <div className="applicant-applications-toolbar">
        <p>{loading ? "Loading applications…" : `${items.length} ${items.length === 1 ? "application" : "applications"} found`}</p>
        <Button variant="secondary" size="sm" icon={RefreshCw} onClick={() => { setLoading(true); void load(); }} loading={loading}>Refresh</Button>
      </div>

      {error && <Alert tone="error">{error}</Alert>}

      {loading ? <LoadingState message="Loading applications..." /> : items.length === 0 ? (
        <Card className="applicant-workspace-empty">
          <span><FileTextIcon /></span>
          <h2>No applications yet</h2>
          <p>Browse the currently open admission intakes and select a programme to start your first application.</p>
          <Link to="/applicant/applications/new" className="btn btn-primary">Browse available programmes</Link>
        </Card>
      ) : (
        <div className="applicant-application-list">
          {items.map(item => (
            <Card key={item.id} className="applicant-application-card">
              <div className="applicant-application-row">
                <div className="applicant-application-main">
                  <span className="applicant-application-overline">Application {item.application_number}</span>
                  <strong>{item.programme || "Programme details unavailable"}</strong>
                  <p>{[item.intake, item.academic_session].filter(Boolean).join(" · ") || "Admission intake details unavailable"}</p>
                  {item.updated_at && <small>Last updated: {new Date(item.updated_at).toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" })}</small>}
                </div>
                <div className="applicant-application-actions">
                  <span className={`applicant-status ${item.status}`}>{formatStatus(item.status)}</span>
                  <Link to={`/applicant/applications/${item.id}`} className="btn btn-secondary btn-sm">{item.status === "draft" ? "Continue draft" : "View application"}</Link>
                </div>
              </div>
            </Card>
          ))}
        </div>
      )}
      <p className="applicant-applications-note">Application status and available actions are determined by your institution.</p>
    </div>
  );
}

function FileTextIcon() {
  return <svg aria-hidden="true" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/></svg>;
}
