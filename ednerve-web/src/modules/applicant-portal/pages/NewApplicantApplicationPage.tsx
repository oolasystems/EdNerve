import { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { CheckCircle2, Circle, FilePlus2 } from "lucide-react";
import { applicantApi, type AdmissionIntakeOption } from "../api";
import { getApplicantApiError } from "../api-error";
import { Alert, Button, Card, LoadingState } from "../../../shared/ui";

export default function NewApplicantApplicationPage() {
  const [intakes, setIntakes] = useState<AdmissionIntakeOption[]>([]);
  const [selectedIntakeId, setSelectedIntakeId] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);
  const [creating, setCreating] = useState(false);
  const [error, setError] = useState("");
  const navigate = useNavigate();

  const selectedIntake = useMemo(
    () => intakes.find((intake) => intake.id === selectedIntakeId) ?? null,
    [intakes, selectedIntakeId],
  );

  useEffect(() => {
    let active = true;

    applicantApi
      .intakes()
      .then((data) => {
        if (active) setIntakes(data);
      })
      .catch((requestError) => {
        if (active) {
          setError(
            getApplicantApiError(
              requestError,
              "Unable to load open programmes and intakes.",
            ),
          );
        }
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, []);

  const startApplication = async () => {
    if (!selectedIntake) return;

    setCreating(true);
    setError("");

    try {
      // The backend returns the existing application for this intake, if one exists.
      // This prevents creating duplicate applications for the same applicant and intake.
      const application = await applicantApi.createApplication(selectedIntake.id);
      navigate(`/applicant/applications/${application.id}`, { state: { startAt: "personal" } });
    } catch (requestError) {
      setError(
        getApplicantApiError(
          requestError,
          "Unable to start the application. Please try again.",
        ),
      );
    } finally {
      setCreating(false);
    }
  };

  return (
    <div className="applicant-page">
      <Card>
        <div className="applicant-form-progress"><div><strong>Admission application</strong><span>Step 1 of 6</span></div><div className="applicant-form-progress-track"><span className="applicant-form-progress-fill applicant-form-progress-step-1" /></div></div>
        <nav className="applicant-form-steps" aria-label="Application sections">
          {["Programme", "Personal", "Contact", "Academic", "Additional", "Review & Submit"].map((title, index) => (
            <button type="button" key={title} className={index === 0 ? "active" : ""} disabled={index !== 0} aria-current={index === 0 ? "step" : undefined}>
              <span>{index + 1}</span><strong>{title}</strong>
            </button>
          ))}
        </nav>
        <span className="public-portal-eyebrow">Section 1 · Programme</span>
        <h1 className="public-portal-title">Programme and admission details</h1>
        <p className="public-portal-subtitle">
          Select the programme and available intake for which you want to apply. Your draft will be created after you confirm your selection.
        </p>
      </Card>

      {error && <Alert tone="error">{error}</Alert>}

      {loading ? (
        <LoadingState message="Loading available programmes and intakes..." />
      ) : intakes.length === 0 ? (
        <Card>
          <h2>No open intakes</h2>
          <p>
            There are no currently open admission intakes for this institution.
            Please contact the admissions office if you need assistance.
          </p>
        </Card>
      ) : (
        <>
          <div className="applicant-application-list" aria-label="Available programmes">
            {intakes.map((intake) => {
              const isSelected = selectedIntakeId === intake.id;

              return (
                <Card
                  key={intake.id}
                  className={`applicant-intake-card${isSelected ? " selected" : ""}`}
                >
                  <div className="applicant-application-row">
                    <div className="applicant-intake-details">
                      <strong>{intake.programme?.name || "Programme"}</strong>
                      <p>
                        {intake.name}
                        {intake.academic_session
                          ? ` · ${intake.academic_session}`
                          : ""}
                      </p>
                      <small>
                        {intake.programme?.code || ""}
                        {intake.programme?.level
                          ? ` · ${intake.programme.level}`
                          : ""}
                        {intake.close_date ? ` · Apply by ${intake.close_date}` : ""}
                      </small>
                      <p>
                        {intake.application_fee
                          ? `Application fee: ₹${intake.application_fee}`
                          : "No application fee configured"}
                      </p>
                    </div>

                    <Button
                      variant={isSelected ? "primary" : "secondary"}
                      disabled={creating}
                      icon={isSelected ? CheckCircle2 : Circle}
                      aria-pressed={isSelected}
                      onClick={() => setSelectedIntakeId(intake.id)}
                    >
                      {isSelected ? "Selected" : "Select programme"}
                    </Button>
                  </div>
                </Card>
              );
            })}
          </div>

          {selectedIntake && (
            <Card className="applicant-selected-intake">
              <div className="applicant-selected-intake-heading">
                <div>
                  <span className="public-portal-eyebrow">Your selection</span>
                  <h2>Confirm programme and intake</h2>
                </div>
                <Button
                  variant="ghost"
                  size="sm"
                  disabled={creating}
                  onClick={() => setSelectedIntakeId(null)}
                >
                  Change selection
                </Button>
              </div>

              <dl className="applicant-intake-summary">
                <div>
                  <dt>Programme</dt>
                  <dd>{selectedIntake.programme?.name || "Programme"}</dd>
                </div>
                <div>
                  <dt>Programme code</dt>
                  <dd>{selectedIntake.programme?.code || "—"}</dd>
                </div>
                <div>
                  <dt>Intake</dt>
                  <dd>{selectedIntake.name}</dd>
                </div>
                <div>
                  <dt>Academic session</dt>
                  <dd>{selectedIntake.academic_session || "—"}</dd>
                </div>
                <div>
                  <dt>Application deadline</dt>
                  <dd>{selectedIntake.close_date || "—"}</dd>
                </div>
                <div>
                  <dt>Application fee</dt>
                  <dd>
                    {selectedIntake.application_fee
                      ? `₹${selectedIntake.application_fee}`
                      : "Not configured"}
                  </dd>
                </div>
              </dl>

              <div className="applicant-selected-intake-actions">
                <p>
                  Confirming will create or open your application draft for this
                  intake. You can complete the application in the next steps.
                </p>
                <Button
                  icon={FilePlus2}
                  loading={creating}
                  disabled={creating}
                  onClick={startApplication}
                >
                  Create draft and continue
                </Button>
              </div>
            </Card>
          )}
        </>
      )}
    </div>
  );
}
