import { useEffect, useRef, useState } from "react";
import { Link, useNavigate, useParams, useSearchParams } from "react-router-dom";
import { applicantApi } from "../api";
import { getApplicantApiError } from "../api-error";
import { Alert, LoadingState } from "../../../shared/ui";
import logo from "../../../assets/brand/oolaednerve-logo-light.svg";
import "../applicant-portal.css";

export default function VerifyEmailPage() {
  const [params] = useSearchParams();
  const { institutionCode: routeInstitutionCode = "" } = useParams();
  const institutionCode = routeInstitutionCode || params.get("institution_code") || "";
  const token = params.get("token") ?? "";
  const email = params.get("email") ?? "";
  const attempted = useRef(false);
  const navigate = useNavigate();
  const linkIsComplete = Boolean(token && institutionCode && email);
  const [state, setState] = useState<"loading" | "success" | "error">(linkIsComplete ? "loading" : "error");
  const [message, setMessage] = useState(linkIsComplete ? "" : "The verification link is incomplete.");


  useEffect(() => {
    if (!linkIsComplete) return;
    if (attempted.current) return;
    attempted.current = true;
    applicantApi.verifyEmail({ institution_code: institutionCode, email, token })
      .then(result => {
        setState("success");
        setMessage(result.message || "Your email has been verified.");
        if (result.setup_token) {
          navigate(
            `/apply/${encodeURIComponent(institutionCode)}/set-password?email=${encodeURIComponent(email)}&setup_token=${encodeURIComponent(result.setup_token)}`,
            { replace: true }
          );
        }
      })
      .catch(error => {
        setState("error");
        setMessage(getApplicantApiError(error, "Unable to verify this email link."));
      });
  }, [email, institutionCode, token, linkIsComplete, navigate]);

  return (
    <div className="public-portal applicant-auth-page">
      <div className="applicant-auth-wrap">
        <Link to="/" className="applicant-brand"><img src={logo} alt="OolaEdNerve" /></Link>
        <section className="public-portal-card applicant-auth-card">
          <span className="public-portal-eyebrow">Email verification</span>
          <h1 className="public-portal-title">Verify your email</h1>
          {state === "loading" ? <LoadingState message="Verifying your email..." /> : (
            <>
              <Alert tone={state === "success" ? "success" : "error"}>{message}</Alert>
              {state === "success" && (
                <div className="applicant-auth-actions">
                  <p>Your email address is verified. Continue to create your password and activate your applicant account.</p>
                </div>
              )}
              {state === "error" && (
                <div className="applicant-auth-recovery">
                  <p className="applicant-auth-links"><Link to={`/apply/${institutionCode}/resend-verification`}>Request a new verification email</Link></p>
                  <p className="applicant-auth-links"><Link to={`/apply/${institutionCode}/register`}>Back to registration</Link></p>
                </div>
              )}
            </>
          )}
        </section>
      </div>
    </div>
  );
}
