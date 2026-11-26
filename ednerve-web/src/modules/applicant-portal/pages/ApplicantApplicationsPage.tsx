import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { FilePlus2, RefreshCw } from "lucide-react";
import { applicantApi, type ApplicantApplication } from "../api";
import { getApplicantApiError } from "../api-error";
import { Alert, Button, Card, LoadingState } from "../../../shared/ui";

export default function ApplicantApplicationsPage() {
 const [items,setItems]=useState<ApplicantApplication[]>([]); const [loading,setLoading]=useState(true); const [error,setError]=useState("");
 const load=(showLoading = false)=>{if(showLoading)setLoading(true);applicantApi.applications().then(setItems).catch(e=>setError(getApplicantApiError(e,"Unable to load applications."))).finally(()=>setLoading(false));};
 useEffect(()=>{ void applicantApi.applications().then(setItems).catch(e=>setError(getApplicantApiError(e,"Unable to load applications."))).finally(()=>setLoading(false)); },[]);
 return <div className="applicant-page"><div className="applicant-page-heading"><div><span className="public-portal-eyebrow">Applicant workspace</span><h1 className="public-portal-title">My applications</h1><p className="public-portal-subtitle">Continue a saved draft or start a new application.</p></div><Link className="btn btn-primary" to="/applicant/applications/new"><FilePlus2 size={16}/> New application</Link></div>{error&&<Alert tone="error">{error}</Alert>}{loading?<LoadingState message="Loading applications..."/>:items.length===0?<Card><h2>No applications yet</h2><p>Browse open admission intakes to start your first application.</p><Link to="/applicant/applications/new" className="btn btn-primary">Browse intakes</Link></Card>:<div className="applicant-application-list">{items.map(item=><Card key={item.id}><div className="applicant-application-row"><div><strong>{item.programme || "Programme"}</strong><p>{item.intake} · {item.academic_session}</p><small>{item.application_number}</small></div><div><span className={`applicant-status ${item.status}`}>{item.status.replaceAll("_"," ")}</span><Link to={`/applicant/applications/${item.id}`} className="btn btn-secondary btn-sm">{item.status==="draft"?"Continue draft":"View"}</Link></div></div></Card>)}</div>}<Button variant="ghost" size="sm" icon={RefreshCw} onClick={()=>load(true)}>Refresh</Button></div>;
}
