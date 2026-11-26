import { useEffect, useMemo, useState, type FormEvent } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { applicantApi, type ApplicantApplication, type ApplicantApplicationForm, type ApplicantQualification, type ApplicantFormField } from "../api";
import { getApplicantApiError } from "../api-error";
import { Alert, Button, Card, FormField, Input, LoadingState, Select } from "../../../shared/ui";
import "../applicant-portal.css";

type Step = "personal" | "contact" | "academic" | "programme" | "review";
const steps: { key: Step; title: string; description: string }[] = [
  { key: "personal", title: "Personal information", description: "Your identity and family details" },
  { key: "contact", title: "Contact and address", description: "Your correspondence details" },
  { key: "academic", title: "Academic qualifications", description: "Your educational history" },
  { key: "programme", title: "Programme information", description: "Additional institution-specific questions" },
  { key: "review", title: "Review", description: "Review your saved information" },
];
const emptyQualification = (): ApplicantQualification => ({ qualification_level: "higher_secondary", qualification_name: "", result_status: "passed" });

function DynamicField({ field, value, onChange, disabled }: { field: ApplicantFormField; value: unknown; onChange: (value: unknown) => void; disabled: boolean }) {
  const id = `form-field-${field.id}`;
  const current = value == null ? "" : String(value);
  if (["select", "dropdown", "radio"].includes(field.field_type) && field.options.length) {
    return <FormField label={field.label} htmlFor={id}>
      <Select id={id} value={current} onChange={e => onChange(e.target.value)} disabled={disabled}>
        <option value="">Select</option>{field.options.map(option => <option key={option.value} value={option.value}>{option.label}</option>)}
      </Select>{field.help_text && <small>{field.help_text}</small>}
    </FormField>;
  }
  const type = field.field_type === "date" ? "date" : field.field_type === "number" ? "number" : field.field_type === "email" ? "email" : "text";
  return <FormField label={field.label} htmlFor={id}>
    <Input id={id} type={type} required={field.is_required} value={current} onChange={e => onChange(type === "number" ? (e.target.value === "" ? "" : Number(e.target.value)) : e.target.value)} disabled={disabled}/>
    {field.help_text && <small>{field.help_text}</small>}
  </FormField>;
}

export default function ApplicantApplicationEditorPage() {
  const { applicationId = "" } = useParams();
  const id = Number(applicationId);
  const navigate = useNavigate();
  const [application, setApplication] = useState<(ApplicantApplication & Record<string, unknown>) | null>(null);
  const [formData, setFormData] = useState<ApplicantApplicationForm | null>(null);
  const [step, setStep] = useState<Step>("personal");
  const [personal, setPersonal] = useState<Record<string, string>>({});
  const [answers, setAnswers] = useState<Record<string, unknown>>({});
  const [qualifications, setQualifications] = useState<ApplicantQualification[]>([]);
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
      else if (step === "programme" && Object.keys(answers).length > 0) await applicantApi.saveApplicationAnswers(id, answers);
      setMessage("This section has been saved to your application draft.");
      return true;
    } catch (e) { setError(getApplicantApiError(e, "Unable to save this section.")); return false; }
    finally { setBusy(false); }
  };
  const move = async (next: Step) => { if (await saveCurrent()) setStep(next); };
  const updateQualification = (index: number, key: keyof ApplicantQualification, value: string) => setQualifications(current => current.map((item, i) => i === index ? { ...item, [key]: value } : item));
  const field = (key: string, label: string, type = "text") => <FormField key={key} label={label} htmlFor={key}><Input id={key} type={type} value={personal[key] ?? ""} onChange={e => updatePersonal(key, e.target.value)} disabled={!editable}/></FormField>;

  if (loading) return <LoadingState message="Loading admission application..."/>;
  if (!application || !formData) return <div className="applicant-page"><Alert tone="error">{error || "Application not found."}</Alert></div>;
  return <div className="applicant-page applicant-application-form-page">
    <span className="public-portal-eyebrow">{application.application_number}</span>
    <h1 className="public-portal-title">{application.programme || "Admission application"}</h1>
    <p className="public-portal-subtitle">{application.intake} · {application.academic_session}</p>
    {!editable && <Alert tone="warning">This application is no longer a draft and cannot be edited.</Alert>}
    {error && <Alert tone="error">{error}</Alert>}{message && <Alert tone="success">{message}</Alert>}
    <Card>
      <div className="applicant-form-progress"><div><strong>Application form</strong><span>Step {stepIndex + 1} of {steps.length}</span></div><div className="applicant-form-progress-track"><span style={{ width: `${((stepIndex + 1) / steps.length) * 100}%` }}/></div></div>
      <nav className="applicant-form-steps" aria-label="Application sections">{steps.map((item, index) => <button type="button" key={item.key} className={step === item.key ? "active" : index < stepIndex ? "complete" : ""} onClick={() => setStep(item.key)}><span>{index + 1}</span><strong>{item.title}</strong></button>)}</nav>
      <form className="applicant-editor-form" onSubmit={saveCurrent}>
        {step === "personal" && <><h2>Personal information</h2><p>Enter your personal and family details as they appear in your official records.</p><div className="applicant-form-grid">{field("first_name", "First name")}{field("middle_name", "Middle name")}{field("last_name", "Last name")}{field("dob", "Date of birth", "date")}<FormField label="Gender" htmlFor="gender"><Select id="gender" value={personal.gender ?? ""} onChange={e => updatePersonal("gender", e.target.value)} disabled={!editable}><option value="">Select</option>{["Female", "Male", "Other", "Prefer not to say"].map(v => <option key={v}>{v}</option>)}</Select></FormField><FormField label="Category" htmlFor="category"><Select id="category" value={personal.category || "General"} onChange={e => updatePersonal("category", e.target.value)} disabled={!editable}>{["General", "OBC", "SC", "ST", "EWS", "Other"].map(v => <option key={v}>{v}</option>)}</Select></FormField>{field("nationality", "Nationality")}{field("father_name", "Father's / guardian's name")}{field("mother_name", "Mother's name")}</div></>}
        {step === "contact" && <><h2>Contact and address</h2><p>Provide your current contact details and correspondence address.</p><div className="applicant-form-grid">{field("phone", "Mobile number")}</div>{field("address_line", "Address") }<div className="applicant-form-grid">{field("city", "City")}{field("state", "State")}{field("pin_code", "PIN / postal code")}</div></>}
        {step === "academic" && <><h2>Academic qualifications</h2><p>Add your educational qualifications, starting with your most recent qualification.</p>{qualifications.map((item, index) => <section className="applicant-qualification-card" key={item.id ?? `new-${index}`}><div className="applicant-qualification-heading"><strong>Qualification {index + 1}</strong><Button type="button" variant="ghost" size="sm" disabled={!editable} onClick={() => setQualifications(current => current.filter((_, i) => i !== index))}>Remove</Button></div><div className="applicant-form-grid"><FormField label="Qualification level" htmlFor={`level-${index}`}><Select id={`level-${index}`} value={item.qualification_level} disabled={!editable} onChange={e => updateQualification(index, "qualification_level", e.target.value)}>{[["secondary","Secondary / 10th"],["higher_secondary","Higher Secondary / 12th"],["diploma","Diploma"],["undergraduate","Undergraduate"],["postgraduate","Postgraduate"],["doctorate","Doctorate"],["other","Other"]].map(([v,l]) => <option key={v} value={v}>{l}</option>)}</Select></FormField><FormField label="Qualification / examination" htmlFor={`name-${index}`}><Input id={`name-${index}`} value={item.qualification_name} disabled={!editable} onChange={e => updateQualification(index,"qualification_name",e.target.value)}/></FormField><FormField label="Board / University" htmlFor={`board-${index}`}><Input id={`board-${index}`} value={item.board_or_university ?? ""} disabled={!editable} onChange={e => updateQualification(index,"board_or_university",e.target.value)}/></FormField><FormField label="School / College" htmlFor={`institution-${index}`}><Input id={`institution-${index}`} value={item.institution_name ?? ""} disabled={!editable} onChange={e => updateQualification(index,"institution_name",e.target.value)}/></FormField><FormField label="Passing year" htmlFor={`year-${index}`}><Input id={`year-${index}`} type="number" value={item.passing_year ?? ""} disabled={!editable} onChange={e => updateQualification(index,"passing_year",e.target.value)}/></FormField><FormField label="Result status" htmlFor={`result-${index}`}><Select id={`result-${index}`} value={item.result_status ?? "passed"} disabled={!editable} onChange={e => updateQualification(index,"result_status",e.target.value)}>{[["passed","Passed"],["appearing","Appearing"],["awaiting_result","Awaiting result"],["failed","Failed"]].map(([v,l]) => <option key={v} value={v}>{l}</option>)}</Select></FormField><FormField label="Percentage" htmlFor={`percentage-${index}`}><Input id={`percentage-${index}`} type="number" value={item.percentage ?? ""} disabled={!editable} onChange={e => updateQualification(index,"percentage",e.target.value)}/></FormField><FormField label="CGPA" htmlFor={`cgpa-${index}`}><Input id={`cgpa-${index}`} type="number" value={item.cgpa ?? ""} disabled={!editable} onChange={e => updateQualification(index,"cgpa",e.target.value)}/></FormField><FormField label="Stream / subjects" htmlFor={`subjects-${index}`}><Input id={`subjects-${index}`} value={item.stream_or_subjects ?? ""} disabled={!editable} onChange={e => updateQualification(index,"stream_or_subjects",e.target.value)}/></FormField></div></section>)}{editable && <Button type="button" variant="secondary" onClick={() => setQualifications(current => [...current, emptyQualification()])}>Add qualification</Button>}</>}
        {step === "programme" && <><h2>Programme-specific information</h2><p>Complete any additional questions configured by the institution for this admission intake.</p>{customSections.length === 0 ? <Alert tone="info">No published programme-specific form fields are configured for this intake. You can continue to review your application.</Alert> : customSections.map(section => <section key={section.id} className="applicant-custom-section"><h3>{section.title}</h3>{section.description && <p>{section.description}</p>}<div className="applicant-form-grid">{section.fields.map(item => <DynamicField key={item.id} field={item} value={answers[item.field_key]} disabled={!editable} onChange={value => setAnswers(current => ({ ...current, [item.field_key]: value }))}/>)}</div></section>)}</>}
        {step === "review" && <><h2>Review your application</h2><p>Review the information you have entered. You can return to any section to make changes.</p><div className="applicant-review-list"><div><strong>Applicant</strong><span>{[personal.first_name,personal.middle_name,personal.last_name].filter(Boolean).join(" ") || "Not provided"}</span></div><div><strong>Date of birth</strong><span>{personal.dob || "Not provided"}</span></div><div><strong>Contact</strong><span>{personal.phone || "Not provided"}</span></div><div><strong>Address</strong><span>{[personal.address_line,personal.city,personal.state,personal.pin_code].filter(Boolean).join(", ") || "Not provided"}</span></div><div><strong>Academic qualifications</strong><span>{qualifications.length} record(s)</span></div><div><strong>Programme-specific answers</strong><span>{Object.values(answers).filter(value => value !== null && value !== "").length} answer(s)</span></div></div><Alert tone="info">Your information is saved as a draft. Final submission and declaration confirmation will be enabled in a subsequent admission workflow phase.</Alert></>}
        <div className="applicant-editor-actions"><Button type="button" variant="secondary" onClick={() => navigate("/applicant/applications")}>Back to applications</Button><span className="applicant-form-nav-actions">{stepIndex > 0 && <Button type="button" variant="secondary" onClick={() => setStep(steps[stepIndex - 1].key)}>Previous</Button>}{step !== "review" ? <Button type="button" disabled={!editable} loading={busy} onClick={() => void move(steps[stepIndex + 1].key)}>Save and continue</Button> : editable && <Button type="button" loading={busy} onClick={() => void saveCurrent()}>Save draft</Button>}</span></div>
      </form>
    </Card>
  </div>;
}
