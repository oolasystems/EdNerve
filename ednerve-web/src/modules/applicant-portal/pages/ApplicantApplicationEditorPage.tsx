import { useEffect, useMemo, useState, type FormEvent } from "react";
import { useLocation, useNavigate, useParams } from "react-router-dom";
import { applicantApi, type ApplicantApplication, type ApplicantApplicationForm, type ApplicantQualification, type ApplicantFormField, type ApplicantApplicationDocument, type ApplicantClarification } from "../api";
import { useApplicantAuth } from "../auth-context";
import { getApplicantApiError } from "../api-error";
import { Alert, Button, Card, FormField, Input, LoadingState, Select } from "../../../shared/ui";
import "../applicant-portal.css";

type Step = "programme" | "personal" | "contact" | "academic" | "additional" | "review";
const steps: { key: Step; title: string; description: string }[] = [
  { key: "programme", title: "Programme", description: "Programme and admission intake" },
  { key: "personal", title: "Personal", description: "Your identity and family details" },
  { key: "contact", title: "Contact", description: "Your correspondence details" },
  { key: "academic", title: "Academic", description: "Your educational history" },
  { key: "additional", title: "Additional", description: "Other institution-configured information" },
  { key: "review", title: "Review & Submit", description: "Review your application" },
];
const emptyQualification = (): ApplicantQualification => ({ qualification_level: "higher_secondary", qualification_name: "", result_status: "passed" });

function DynamicField({
  field, value, onChange, disabled, document, onUpload,
}: {
  field: ApplicantFormField;
  value: unknown;
  onChange: (value: unknown) => void;
  disabled: boolean;
  document?: ApplicantApplicationDocument;
  onUpload: (fieldKey: string, file: File) => Promise<void>;
}) {
  const id = `form-field-${field.id}`;
  const current = value == null ? "" : String(value);

  if (field.field_type === "file") {
    return <FormField label={field.label} htmlFor={id} required={field.is_required}>
      <input
        id={id}
        className="applicant-document-input"
        type="file"
        accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
        disabled={disabled}
        onChange={event => {
          const file = event.target.files?.[0];
          if (file) void onUpload(field.field_key, file);
          event.currentTarget.value = "";
        }}
      />
      {document && <small className="applicant-document-uploaded">Uploaded: {document.original_filename} · {document.verification_status}{document.review_notes ? ` · Reviewer: ${document.review_notes}` : ""}</small>}
      {!document && field.is_required && <small className="applicant-document-required">Required document — PDF, JPG or PNG, maximum 10 MB.</small>}
      {field.help_text && <small>{field.help_text}</small>}
    </FormField>;
  }

  if (field.field_type === "boolean") {
    return <div className="applicant-dynamic-checkbox">
      <label htmlFor={id}>
        <input id={id} type="checkbox" checked={value === true || value === 1 || value === "1" || value === "true"} disabled={disabled}
          onChange={event => onChange(event.target.checked)} />
        <span>{field.label}{field.is_required ? " *" : ""}</span>
      </label>
      {field.help_text && <small>{field.help_text}</small>}
    </div>;
  }

  if (["select", "dropdown", "radio"].includes(field.field_type) && field.options.length) {
    return <FormField label={field.label} htmlFor={id} required={field.is_required}>
      <Select id={id} value={current} onChange={e => onChange(e.target.value)} disabled={disabled}>
        <option value="">Select</option>{field.options.map(option => <option key={option.value} value={option.value}>{option.label}</option>)}
      </Select>{field.help_text && <small>{field.help_text}</small>}
    </FormField>;
  }

  const type = field.field_type === "date" ? "date" : field.field_type === "number" ? "number" : field.field_type === "email" ? "email" : "text";
  return <FormField label={field.label} htmlFor={id} required={field.is_required}>
    <Input id={id} type={type} required={field.is_required} value={current} onChange={e => onChange(type === "number" ? (e.target.value === "" ? "" : Number(e.target.value)) : e.target.value)} disabled={disabled}/>
    {field.help_text && <small>{field.help_text}</small>}
  </FormField>;
}

export default function ApplicantApplicationEditorPage() {
  const { applicationId = "" } = useParams();
  const id = Number(applicationId);
  const navigate = useNavigate();
  const location = useLocation();
  const { applicant } = useApplicantAuth();
  const [application, setApplication] = useState<(ApplicantApplication & Record<string, unknown>) | null>(null);
  const [formData, setFormData] = useState<ApplicantApplicationForm | null>(null);
  const [step, setStep] = useState<Step>(() =>
    location.state?.startAt === "personal" ? "personal" : "programme",
  );
  const [personal, setPersonal] = useState<Record<string, string>>({});
  const [answers, setAnswers] = useState<Record<string, unknown>>({});
  const [qualifications, setQualifications] = useState<ApplicantQualification[]>([]);
  const [documents, setDocuments] = useState<ApplicantApplicationDocument[]>([]);
  const [clarifications, setClarifications] = useState<ApplicantClarification[]>([]);
  const [clarificationResponses, setClarificationResponses] = useState<Record<number, string>>({});
  const [declarationAccepted, setDeclarationAccepted] = useState(false);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const editable = application?.status === "draft";

  useEffect(() => {
    let active = true;
    const load = async () => {
      try {
        const [app, details] = await Promise.all([applicantApi.application(id), applicantApi.applicationForm(id)]);
        if (!active) return;
        setApplication(app);
        setFormData(details);
        setAnswers(details.answers ?? {});
        setQualifications(details.qualifications ?? []);
        setDocuments(details.documents ?? []);
        if (app.status !== "draft") {
          const requests = await applicantApi.clarifications(id);
          if (active) setClarifications(requests);
        }
        const keys = ["first_name", "middle_name", "last_name", "father_name", "mother_name", "dob", "gender", "category", "nationality", "phone", "address_line", "city", "state", "pin_code"];
        const values: Record<string, string> = {};
        keys.forEach(key => { values[key] = String(app[key] ?? ""); });
        setPersonal(values);
      } catch (e) {
        if (active) setError(getApplicantApiError(e, "Unable to load the admission application."));
      } finally {
        if (active) setLoading(false);
      }
    };
    void load();
    return () => { active = false; };
  }, [id]);

  const customSections = useMemo(() => formData?.sections ?? [], [formData]);
  const stepIndex = steps.findIndex(item => item.key === step);
  const updatePersonal = (key: string, value: string) => setPersonal(current => ({ ...current, [key]: value }));
  const saveCurrent = async (event?: FormEvent): Promise<boolean> => {
    event?.preventDefault(); if (!editable) return false;
    setBusy(true); setError(""); setMessage("");
    try {
      if (step === "personal" || step === "contact") await applicantApi.updateApplication(id, personal);
      else if (step === "academic") await applicantApi.saveQualifications(id, qualifications);
      else if (step === "additional" && Object.keys(answers).length > 0) await applicantApi.saveApplicationAnswers(id, answers);
      setMessage("This section has been saved to your application draft.");
      return true;
    } catch (e) { setError(getApplicantApiError(e, "Unable to save this section.")); return false; }
    finally { setBusy(false); }
  };
  const uploadDocument = async (documentType: string, file: File) => {
    const isReturnedReplacement = documents.some(document => document.document_type === documentType && document.verification_status === "returned");
    if (!editable && !isReturnedReplacement) return;
    if (file.size > 10 * 1024 * 1024) {
      setError("Each document must be 10 MB or smaller.");
      return;
    }
    setBusy(true);
    setError("");
    setMessage("");
    try {
      const uploaded = await applicantApi.uploadApplicationDocument(id, documentType, file);
      setDocuments(current => [...current.filter(item => item.document_type !== documentType), uploaded]);
      setMessage(`${uploaded.original_filename} uploaded successfully.`);
    } catch (uploadError) {
      setError(getApplicantApiError(uploadError, "Unable to upload this document."));
    } finally {
      setBusy(false);
    }
  };

  const submitClarificationResponse = async (clarificationId: number) => {
    const response = (clarificationResponses[clarificationId] ?? "").trim();
    if (response.length < 2) return;
    setBusy(true); setError(""); setMessage("");
    try {
      await applicantApi.respondToClarification(id, clarificationId, response);
      setClarificationResponses(current => ({ ...current, [clarificationId]: "" }));
      setClarifications(await applicantApi.clarifications(id));
      setMessage("Your clarification response has been submitted.");
    } catch (requestError) {
      setError(getApplicantApiError(requestError, "Unable to submit your clarification response."));
    } finally { setBusy(false); }
  };

  const submitApplication = async () => {
    if (!editable || !declarationAccepted) return;
    setBusy(true);
    setError("");
    setMessage("");
    try {
      if (Object.keys(answers).length > 0) {
        await applicantApi.saveApplicationAnswers(id, answers);
      }
      const result = await applicantApi.submitApplication(id);
      setApplication(current => current ? { ...current, status: result.application.status } : current);
      setMessage(`${result.message} Your application number is ${result.application.application_number}.`);
      setDeclarationAccepted(false);
    } catch (submitError) {
      setError(getApplicantApiError(submitError, "Unable to submit your application. Please review the required information and try again."));
    } finally {
      setBusy(false);
    }
  };

  const move = async (next: Step) => {
    if (!editable) { setStep(next); return; }
    if (await saveCurrent()) setStep(next);
  };
  const updateQualification = (index: number, key: keyof ApplicantQualification, value: string) => setQualifications(current => current.map((item, i) => i === index ? { ...item, [key]: value } : item));
  const field = (key: string, label: string, type = "text") => <FormField key={key} label={label} htmlFor={key}><Input id={key} type={type} value={personal[key] ?? ""} onChange={e => updatePersonal(key, e.target.value)} disabled={!editable}/></FormField>;

  if (loading) return <LoadingState message="Loading admission application..."/>;
  if (!application || !formData) return <div className="applicant-page"><Alert tone="error">{error || "Application not found."}</Alert></div>;
  return <div className="applicant-page applicant-application-form-page">
    {!editable && <Alert tone="warning">This application is no longer a draft and cannot be edited.</Alert>}
    {error && <Alert tone="error">{error}</Alert>}{message && <Alert tone="success">{message}</Alert>}
    <Card>
      <div className="applicant-form-identity"><span className="public-portal-eyebrow">Application {application.application_number}</span><span className={`applicant-status ${application.status}`}>{application.status.replaceAll("_", " ")}</span></div>
      <div className="applicant-form-progress"><div><strong>Admission application</strong><span>Step {stepIndex + 1} of {steps.length}</span></div><div className="applicant-form-progress-track"><span className={`applicant-form-progress-fill applicant-form-progress-step-${stepIndex + 1}`} /></div></div>
      <nav className="applicant-form-steps" aria-label="Application sections">{steps.map((item, index) => <button type="button" key={item.key} aria-current={step === item.key ? "step" : undefined} aria-label={`${item.title}${index < stepIndex ? ", completed" : step === item.key ? ", current step" : ""}`} className={step === item.key ? "active" : index < stepIndex ? "complete" : ""} onClick={() => void move(item.key)}><span>{index + 1}</span><strong>{item.title}</strong></button>)}</nav>
      <form className="applicant-editor-form" onSubmit={saveCurrent}>
        {step === "programme" && <><h2>Programme and admission details</h2><p>Review the programme and intake selected for this application. Contact the admissions office if any displayed detail appears incorrect.</p><div className="applicant-programme-summary"><div><span>University / institution</span><strong>{applicant?.institution?.name || "Your institution"}</strong></div><div><span>Programme</span><strong>{application.programme || "Not available"}</strong></div><div><span>Programme code</span><strong>{application.programme_code || "—"}</strong></div><div><span>Admission intake</span><strong>{application.intake || "—"}</strong></div><div><span>Academic session</span><strong>{application.academic_session || "—"}</strong></div></div></>}
        {step === "personal" && <><h2>Personal information</h2><p>Enter your personal and family details as they appear in your official records.</p><div className="applicant-form-grid">{field("first_name", "First name")}{field("middle_name", "Middle name")}{field("last_name", "Last name")}{field("dob", "Date of birth", "date")}<FormField label="Gender" htmlFor="gender"><Select id="gender" value={personal.gender ?? ""} onChange={e => updatePersonal("gender", e.target.value)} disabled={!editable}><option value="">Select</option>{["Female", "Male", "Other", "Prefer not to say"].map(v => <option key={v}>{v}</option>)}</Select></FormField><FormField label="Category" htmlFor="category"><Select id="category" value={personal.category || "General"} onChange={e => updatePersonal("category", e.target.value)} disabled={!editable}>{["General", "OBC", "SC", "ST", "EWS", "Other"].map(v => <option key={v}>{v}</option>)}</Select></FormField>{field("nationality", "Nationality")}{field("father_name", "Father's / guardian's name")}{field("mother_name", "Mother's name")}</div></>}
        {step === "contact" && <><h2>Contact and address</h2><p>Provide your current contact details and correspondence address.</p><div className="applicant-form-grid">{field("phone", "Mobile number")}</div>{field("address_line", "Address") }<div className="applicant-form-grid">{field("city", "City")}{field("state", "State")}{field("pin_code", "PIN / postal code")}</div></>}
        {step === "academic" && <><h2>Academic qualifications</h2><p>Add your educational qualifications, starting with your most recent qualification.</p>{qualifications.map((item, index) => <section className="applicant-qualification-card" key={item.id ?? `new-${index}`}><div className="applicant-qualification-heading"><strong>Qualification {index + 1}</strong><Button type="button" variant="ghost" size="sm" disabled={!editable} onClick={() => setQualifications(current => current.filter((_, i) => i !== index))}>Remove</Button></div><div className="applicant-form-grid"><FormField label="Qualification level" htmlFor={`level-${index}`}><Select id={`level-${index}`} value={item.qualification_level} disabled={!editable} onChange={e => updateQualification(index, "qualification_level", e.target.value)}>{[["secondary","Secondary / 10th"],["higher_secondary","Higher Secondary / 12th"],["diploma","Diploma"],["undergraduate","Undergraduate"],["postgraduate","Postgraduate"],["doctorate","Doctorate"],["other","Other"]].map(([v,l]) => <option key={v} value={v}>{l}</option>)}</Select></FormField><FormField label="Qualification / examination" htmlFor={`name-${index}`}><Input id={`name-${index}`} value={item.qualification_name} disabled={!editable} onChange={e => updateQualification(index,"qualification_name",e.target.value)}/></FormField><FormField label="Board / University" htmlFor={`board-${index}`}><Input id={`board-${index}`} value={item.board_or_university ?? ""} disabled={!editable} onChange={e => updateQualification(index,"board_or_university",e.target.value)}/></FormField><FormField label="School / College" htmlFor={`institution-${index}`}><Input id={`institution-${index}`} value={item.institution_name ?? ""} disabled={!editable} onChange={e => updateQualification(index,"institution_name",e.target.value)}/></FormField><FormField label="Passing year" htmlFor={`year-${index}`}><Input id={`year-${index}`} type="number" value={item.passing_year ?? ""} disabled={!editable} onChange={e => updateQualification(index,"passing_year",e.target.value)}/></FormField><FormField label="Result status" htmlFor={`result-${index}`}><Select id={`result-${index}`} value={item.result_status ?? "passed"} disabled={!editable} onChange={e => updateQualification(index,"result_status",e.target.value)}>{[["passed","Passed"],["appearing","Appearing"],["awaiting_result","Awaiting result"],["failed","Failed"]].map(([v,l]) => <option key={v} value={v}>{l}</option>)}</Select></FormField><FormField label="Percentage" htmlFor={`percentage-${index}`}><Input id={`percentage-${index}`} type="number" value={item.percentage ?? ""} disabled={!editable} onChange={e => updateQualification(index,"percentage",e.target.value)}/></FormField><FormField label="CGPA" htmlFor={`cgpa-${index}`}><Input id={`cgpa-${index}`} type="number" value={item.cgpa ?? ""} disabled={!editable} onChange={e => updateQualification(index,"cgpa",e.target.value)}/></FormField><FormField label="Stream / subjects" htmlFor={`subjects-${index}`}><Input id={`subjects-${index}`} value={item.stream_or_subjects ?? ""} disabled={!editable} onChange={e => updateQualification(index,"stream_or_subjects",e.target.value)}/></FormField></div></section>)}{editable && <Button type="button" variant="secondary" onClick={() => setQualifications(current => [...current, emptyQualification()])}>Add qualification</Button>}</>}
        {step === "additional" && <><h2>Additional information</h2><p>Complete the additional questions configured by the institution for this admission intake.</p>{customSections.length === 0 ? <Alert tone="info">No additional form fields are currently configured for this intake.</Alert> : customSections.map(section => <section key={section.id} className="applicant-custom-section"><h3>{section.title}</h3>{section.description && <p>{section.description}</p>}<div className="applicant-form-grid">{section.fields.map(item => <DynamicField key={item.id} field={item} value={answers[item.field_key]} disabled={(!editable && documents.find(document => document.document_type === item.field_key)?.verification_status !== "returned") || busy} document={documents.find(document => document.document_type === item.field_key)} onUpload={uploadDocument} onChange={value => setAnswers(current => ({ ...current, [item.field_key]: value }))}/>)}</div></section>)}</>}
        {step === "review" && <><h2>Review and submit your application</h2><p>Review your details and uploaded documents. You can return to any section to make corrections before submission.</p><div className="applicant-review-list"><div><strong>Institution</strong><span>{applicant?.institution?.name || "Not available"}</span></div><div><strong>Programme</strong><span>{application.programme || "Not available"} · {application.intake || ""} · {application.academic_session || ""}</span></div><div><strong>Applicant</strong><span>{[personal.first_name,personal.middle_name,personal.last_name].filter(Boolean).join(" ") || "Not provided"}</span></div><div><strong>Date of birth</strong><span>{personal.dob || "Not provided"}</span></div><div><strong>Contact</strong><span>{personal.phone || "Not provided"}</span></div><div><strong>Address</strong><span>{[personal.address_line,personal.city,personal.state,personal.pin_code].filter(Boolean).join(", ") || "Not provided"}</span></div><div><strong>Academic qualifications</strong><span>{qualifications.length} record(s)</span></div><div><strong>Additional answers</strong><span>{Object.values(answers).filter(value => value !== null && value !== "" && value !== false).length} answer(s)</span></div><div><strong>Uploaded documents</strong><span>{documents.length} document(s)</span></div></div><div className="applicant-review-documents"><h3>Documents</h3>{documents.length ? documents.map(document => <div key={document.id}><span>{document.original_filename}</span><small>{document.document_type.replaceAll("_", " ")} · {document.verification_status}</small></div>) : <p>No documents uploaded yet.</p>}</div>{editable && <div className="applicant-final-declaration"><label><input type="checkbox" checked={declarationAccepted} onChange={event => setDeclarationAccepted(event.target.checked)} disabled={busy}/><span>I declare that the information provided in this application is true and complete to the best of my knowledge, and I agree to the institution’s applicable admission rules and privacy notice.</span></label><p>After submission, your application will move to <strong>Under Review</strong> and you will no longer be able to edit it.</p></div>}{!editable && <Alert tone="success">Your application has been submitted and is now under review.</Alert>}</>}
        <div className="applicant-editor-actions"><Button type="button" variant="secondary" onClick={() => navigate("/applicant/applications")}>Back to applications</Button><span className="applicant-form-nav-actions">{stepIndex > 0 && <Button type="button" variant="secondary" onClick={() => void move(steps[stepIndex - 1].key)}>Previous</Button>}{step !== "review" ? <Button type="button" disabled={!editable || busy} loading={busy} onClick={() => void move(steps[stepIndex + 1].key)}>{step === "programme" ? "Continue" : "Save and continue"}</Button> : editable ? <Button type="button" disabled={busy || !declarationAccepted} loading={busy} onClick={() => void submitApplication()}>Submit application</Button> : <Button type="button" variant="secondary" onClick={() => navigate("/applicant/applications")}>Done</Button>}</span></div>
      </form>
    </Card>
    {!editable && <Card className="applicant-clarifications-panel">
      <h2>Institution clarification requests</h2>
      <p>Respond to requests from the admissions team here. Your submitted application remains locked; this response does not edit your original answers.</p>
      {clarifications.length === 0 ? <p>No clarification requests are currently pending.</p> : clarifications.map(item => <section className="applicant-clarification-item" key={item.id}>
        <div><strong>{item.request_text}</strong><span className={`applicant-status ${item.status}`}>{item.status.replaceAll("_", " ")}</span></div>
        {item.requested_fields.length > 0 && <small>Requested information: {item.requested_fields.join(", ")}</small>}
        {item.due_at && <small>Response due: {new Date(item.due_at).toLocaleString("en-IN")}</small>}
        {item.applicant_response && <p><strong>Your response:</strong> {item.applicant_response}</p>}
        {item.status === "open" && <><label htmlFor={`clarification-response-${item.id}`}>Your response</label><textarea id={`clarification-response-${item.id}`} rows={4} maxLength={5000} value={clarificationResponses[item.id] ?? ""} onChange={event => setClarificationResponses(current => ({ ...current, [item.id]: event.target.value }))} /><Button type="button" disabled={busy || (clarificationResponses[item.id] ?? "").trim().length < 2} loading={busy} onClick={() => void submitClarificationResponse(item.id)}>Submit response</Button></>}
      </section>)}
    </Card>}
  </div>;
}
