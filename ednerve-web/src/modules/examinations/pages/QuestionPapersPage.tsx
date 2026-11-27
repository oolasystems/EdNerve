import "../examinations.css";
import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { getApiErrorMessage } from "../../../shared/lib/api-error";
import {
  Layers3,
  Plus,
  Printer,
  Trash2,
  Wand2,
  BadgeCheck,
  Archive,
  Eye,
  SendHorizonal,
  CheckCircle2,
  XCircle,
  ShieldCheck,
} from "lucide-react";
import { toast } from "sonner";
import { useAuth } from "../../../contexts/AuthContext";

import { questionBankService } from "../question-bank-api";
import type { PaperStatus, QuestionDifficulty } from "../question-bank-api";

import { academicService } from "../../academic/api";

import {
  Alert,
  Badge,
  Button,
  FormField,
  Input,
  Modal,
  Spinner,
} from "../../../shared/ui";

const STATUS_TONE: Record<PaperStatus, "info" | "warning" | "success" | "danger"> = {
  draft: "info",
  prepared: "warning",
  checked: "warning",
  approved: "success",
  ratified: "success",
  final: "success",
  retired: "danger",
};

const QuestionPapersPage = () => {
  const { user } = useAuth();
  const qc = useQueryClient();
  const navigate = useNavigate();

  const [subjectId, setSubjectId] = useState<number | "">("");
  const [statusFilter, setStatusFilter] = useState<"" | PaperStatus>("");

  const subjectsQuery = useQuery({
    queryKey: ["academic", "subjects"],
    queryFn: () => academicService.getSubjects(),
  });

  const papersQuery = useQuery({
    queryKey: ["question-bank", "papers", subjectId, statusFilter],
    queryFn: () =>
      questionBankService.getPapers({
        subject_id: subjectId === "" ? undefined : Number(subjectId),
        status: statusFilter || undefined,
      }),
  });

  const papers = papersQuery.data?.papers ?? [];

  const [selectedId, setSelectedId] = useState<number | null>(null);
  const paperQuery = useQuery({
    queryKey: ["question-bank", "paper", selectedId],
    queryFn: () => questionBankService.getPaper(selectedId!),
    enabled: selectedId !== null,
  });
  const paper = paperQuery.data;

  const bankQuery = useQuery({
    queryKey: ["question-bank", "questions", "bank-picker", paper?.subject?.id],
    queryFn: () => questionBankService.getQuestions({ subject_id: paper?.subject?.id, status: "active" }),
    enabled: paper !== null && paper !== undefined,
  });

  const subjects = subjectsQuery.data?.subjects ?? [];

  /* ---------- blueprint generation modal ---------- */
  const [genOpen, setGenOpen] = useState(false);
  const [gen, setGen] = useState({
    subject_id: "" as string | number,
    code: "",
    instructions: "Answer all questions. Write your application number on the answer booklet.",
    sections: [
      { title: "Section A", count: 3, marks: 5, difficulty: "" as "" | QuestionDifficulty },
      { title: "Section B", count: 2, marks: 10, difficulty: "" as "" | QuestionDifficulty },
    ] as Array<{ title: string; count: number; marks: number; difficulty: "" | QuestionDifficulty }>,
  });
  const [genError, setGenError] = useState("");
  const [generating, setGenerating] = useState(false);

  const generate = async () => {
    setGenError("");
    if (gen.subject_id === "") { setGenError("Select a subject."); return; }
    setGenerating(true);
    try {
      const result = await questionBankService.generatePaper({
        subject_id: Number(gen.subject_id),
        code: gen.code.trim() || undefined,
        instructions: gen.instructions.trim() || undefined,
        sections: gen.sections.map((s) => ({
          title: s.title.trim(),
          count: Number(s.count),
          marks: Number(s.marks),
          difficulty: s.difficulty || undefined,
        })),
      });
      toast.success(`Paper ${result.paper?.code ?? ""} generated.`);
      setGenOpen(false);
      setSubjectId(Number(gen.subject_id));
      await qc.invalidateQueries({ queryKey: ["question-bank", "papers"] });
    } catch (e) {
      setGenError(getApiErrorMessage(e, "Unable to generate. Bank lacks enough active questions."));
    } finally {
      setGenerating(false);
    }
  };

  const workflowAction = async (action: "submit" | "check" | "approve" | "ratify") => {
    if (!paper) return;
    const messages = {
      submit: "Paper submitted for checking.",
      check: "Paper marked as checked.",
      approve: "Paper approved.",
      ratify: "Paper ratified and finalized.",
    };
    try {
      switch (action) {
        case "submit":
          await questionBankService.submitPaper(paper.id);
          break;
        case "check":
          await questionBankService.checkPaper(paper.id);
          break;
        case "approve":
          await questionBankService.approvePaper(paper.id);
          break;
        case "ratify":
          await questionBankService.ratifyPaper(paper.id);
          break;
      }
      await qc.invalidateQueries({ queryKey: ["question-bank", "paper", paper.id] });
      await qc.invalidateQueries({ queryKey: ["question-bank", "papers"] });
      toast.success(messages[action]);
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Action failed."));
    }
  };

  const workflowReject = async () => {
    if (!paper) return;
    const remarks = window.prompt("Reason for sending back (min 5 characters):");
    if (!remarks || remarks.trim().length < 5) {
      toast.error("Please provide a reason (at least 5 characters).");
      return;
    }
    try {
      await questionBankService.rejectPaper(paper.id, remarks.trim());
      await qc.invalidateQueries({ queryKey: ["question-bank", "paper", paper.id] });
      await qc.invalidateQueries({ queryKey: ["question-bank", "papers"] });
      toast.success("Paper sent back to draft.");
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to send back."));
    }
  };

  const patchStatus = async (next: PaperStatus) => {
    if (!paper) return;
    try {
      await questionBankService.updatePaper(paper.id, { status: next });
      await qc.invalidateQueries({ queryKey: ["question-bank", "paper", paper.id] });
      await qc.invalidateQueries({ queryKey: ["question-bank", "papers"] });
      toast.success(`Paper marked ${next}.`);
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to update status."));
    }
  };

  const deletePaper = async (id: number) => {
    if (!window.confirm("Delete this draft paper? The questions stay in the bank.")) return;
    try {
      await questionBankService.deletePaper(id);
      if (selectedId === id) setSelectedId(null);
      await qc.invalidateQueries({ queryKey: ["question-bank", "papers"] });
      toast.success("Paper deleted.");
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to delete."));
    }
  };

  const [addSectionOpen, setAddSectionOpen] = useState(false);
  const [sectionForm, setSectionForm] = useState({ title: "", instruction: "" });

  const addSection = async () => {
    if (!paper) return;
    if (!sectionForm.title.trim()) return;
    try {
      await questionBankService.addPaperSection(paper.id, {
        title: sectionForm.title.trim(),
        instruction: sectionForm.instruction.trim() || undefined,
      });
      setAddSectionOpen(false);
      setSectionForm({ title: "", instruction: "" });
      await qc.invalidateQueries({ queryKey: ["question-bank", "paper", paper.id] });
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to add the section."));
    }
  };

  const addItemToSection = async (sectionId: number, questionId: number) => {
    if (!paper) return;
    try {
      await questionBankService.addPaperItem(paper.id, { section_id: sectionId, question_id: questionId });
      await qc.invalidateQueries({ queryKey: ["question-bank", "paper", paper.id] });
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to add the question."));
    }
  };

  const removeItem = async (itemId: number) => {
    if (!paper) return;
    try {
      await questionBankService.removePaperItem(paper.id, itemId);
      await qc.invalidateQueries({ queryKey: ["question-bank", "paper", paper.id] });
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to remove."));
    }
  };

  const bankQuestions = bankQuery.data?.questions ?? [];

  return (
    <div className="institution-two-column">
      {/* ============ Papers list ============ */}
      <div>
        <div className="institution-directory-toolbar exam-papers-toolbar">
          <select className="filter-select" value={subjectId} onChange={(e) => setSubjectId(e.target.value === "" ? "" : Number(e.target.value))} aria-label="Filter by subject">
            <option value="">All subjects</option>
            {subjects.map((s) => (
              <option key={s.id} value={s.id}>{s.code} — {s.name}</option>
            ))}
          </select>
          <select className="filter-select" value={statusFilter} onChange={(e) => setStatusFilter(e.target.value as "" | PaperStatus)} aria-label="Filter by status">
            <option value="">All statuses</option>
            {(["draft", "prepared", "checked", "approved", "ratified", "final", "retired"] as PaperStatus[]).map((s) => <option key={s} value={s}>{s}</option>)}
          </select>
          <Button onClick={() => setGenOpen(true)}>
            <Wand2 size={16} /> Generate paper
          </Button>
        </div>

        {papersQuery.isError && (
          <Alert tone="error">
            Unable to load papers right now.
            <button type="button" onClick={() => void papersQuery.refetch()}>Retry</button>
          </Alert>
        )}
        <div className="institution-admin-panel">
          <h3>
            <Layers3 size={17} className="exam-papers-heading-icon" /> Question papers — {papers.length}
          </h3>
          {papersQuery.isLoading ? (
            <Spinner size={20} />
          ) : papers.length === 0 ? (
            <p className="exam-papers-muted">No papers yet. Generate one from the bank, or build manually.</p>
          ) : (
            <table className="exam-papers-table">
              <thead>
                <tr>
                  <th className="exam-papers-table-heading">Code</th>
                  <th className="exam-papers-table-heading">Subject</th>
                  <th className="exam-papers-table-heading">Marks</th>
                  <th className="exam-papers-table-heading">Items</th>
                  <th className="exam-papers-table-heading">Status</th>
                  <th className="exam-papers-table-heading">Source</th>
                  <th className="exam-papers-table-heading"></th>
                </tr>
              </thead>
              <tbody>
                {papers.map((p) => (
                  <tr key={p.id} className={selectedId === p.id ? "row-selected" : ""}>
                    <td className="exam-papers-table-cell"><strong className="institution-mono exam-papers-code">{p.code}</strong></td>
                    <td className="exam-papers-table-cell">{p.subject?.code ?? "—"}</td>
                    <td className="exam-papers-table-cell">{p.total_marks}</td>
                    <td className="exam-papers-table-cell">{p.items_count}</td>
                    <td className="exam-papers-table-cell"><Badge tone={STATUS_TONE[p.status]}>{p.status}</Badge></td>
                    <td className="exam-papers-table-cell"><span className="institution-mono exam-papers-source">{p.source}</span></td>
                    <td className="exam-papers-table-cell">
                      <button type="button" className="timetable-icon-btn" onClick={() => setSelectedId(p.id)} aria-label="Open">
                        <Eye size={13} />
                      </button>
                      <button type="button" className="timetable-icon-btn" onClick={() => void deletePaper(p.id)} aria-label="Delete draft paper" disabled={p.status !== "draft"}>
                        <Trash2 size={13} />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>

      {/* ============ Paper detail ============ */}
      <div className="institution-admin-panel">
        {!paper && (
          <>
            <h3>Select a paper</h3>
            <p className="exam-papers-muted">Open a paper from the list to edit its sections, questions and instructions, or print it.</p>
          </>
        )}

        {paper && (
          <>
            <div className="exam-papers-detail-header">
              <div>
                <h3 className="institution-mono exam-papers-detail-code">{paper.code}</h3>
                <div className="institution-mono exam-papers-meta">
                  {paper.subject?.code} · {paper.total_marks} marks · {paper.source} · {paper.status}
                </div>
                <div className="institution-mono exam-papers-workflow">
                  {paper.approvals && paper.approvals.length > 0
                    ? `Workflow: ${paper.approvals.map((a) => `${a.stage}${a.decision === "rejected" ? "x" : ""}`).join(" > ")}`
                    : "Not submitted yet"}
                </div>
              </div>
              <div className="exam-papers-actions">
                <Badge tone={STATUS_TONE[paper.status]}>{paper.status}</Badge>
                <Button variant="secondary" onClick={() => navigate(`/institution/examinations/papers/${paper.id}`)}>
                  <Printer size={15} /> Print paper
                </Button>
                {paper.status === "draft" && (
                  <Button variant="secondary" onClick={() => workflowAction("submit")}>
                    <SendHorizonal size={15} /> Submit for check
                  </Button>
                )}
                {paper.status === "prepared" && (
                  <Button
                    variant="secondary"
                    title={paper.prepared_by === user?.id ? "Separation of duties: the preparer cannot check their own paper" : "Mark paper as checked"}
                    disabled={paper.prepared_by === user?.id}
                    onClick={() => workflowAction("check")}
                  >
                    <BadgeCheck size={15} /> Mark checked
                  </Button>
                )}
                {paper.status === "checked" && (
                  <Button
                    variant="secondary"
                    title={[paper.prepared_by, paper.checked_by].includes(user?.id ?? -1) ? "Separation of duties: approver must differ from preparer and checker" : "Approve paper"}
                    disabled={[paper.prepared_by, paper.checked_by].includes(user?.id ?? -1)}
                    onClick={() => workflowAction("approve")}
                  >
                    <CheckCircle2 size={15} /> Approve
                  </Button>
                )}
                {paper.status === "approved" && (
                  <Button
                    variant="secondary"
                    title={[paper.prepared_by, paper.checked_by, paper.approved_by].includes(user?.id ?? -1) ? "Separation of duties: ratifier must differ from preparer, checker and approver" : "Ratify paper (requires MFA)"}
                    disabled={[paper.prepared_by, paper.checked_by, paper.approved_by].includes(user?.id ?? -1)}
                    onClick={() => workflowAction("ratify")}
                  >
                    <ShieldCheck size={15} /> Ratify (MFA)
                  </Button>
                )}
                {["prepared", "checked", "approved"].includes(paper.status) && (
                  <Button variant="secondary" onClick={() => workflowReject()}>
                    <XCircle size={15} /> Send back
                  </Button>
                )}
                {(paper.status === "ratified" || paper.status === "final") && (
                  <Button variant="secondary" onClick={() => patchStatus("retired")}>
                    <Archive size={15} /> Retire
                  </Button>
                )}
              </div>
            </div>

            <p className="exam-papers-instructions">
              {paper.instructions ?? "No instructions set — edit in paper settings below."}
            </p>

            {/* Sections */}
            {paper.sections.map((section) => (
              <div key={section.id} className="exam-papers-section">
                <div className="exam-papers-section-header">
                  <strong>{section.title}</strong>
                  <span className="institution-mono exam-papers-section-instruction">
                    {section.instruction}
                  </span>
                </div>
                <table className="exam-papers-items-table">
                  <tbody>
                    {section.items.map((item) => (
                      <tr key={item.id}>
                        <td className="exam-papers-table-cell">
                          <span className="institution-mono exam-papers-source">Q{item.position}</span>
                        </td>
                        <td className="exam-papers-table-cell exam-papers-question-cell">
                          <div className="exam-papers-question-text">{item.question_text}</div>
                          <div className="institution-mono exam-papers-question-meta">
                            {item.type?.toUpperCase()} · {item.difficulty} · {item.unit ?? "general"}
                          </div>
                        </td>
                        <td className="exam-papers-table-cell exam-papers-marks-cell">{item.marks}m</td>
                        {paper.status === "draft" && (
                          <td className="exam-papers-table-cell">
                            <button type="button" className="timetable-icon-btn" onClick={() => removeItem(item.id)} aria-label="Remove question">
                              <Trash2 size={13} />
                            </button>
                          </td>
                        )}
                      </tr>
                    ))}
                    {section.items.length === 0 && (
                      <tr><td colSpan={4} className="exam-papers-table-cell exam-papers-empty-cell">Empty section.</td></tr>
                    )}
                  </tbody>
                </table>

                {/* Add question picker */}
                {paper.status === "draft" && (
                  <div className="exam-papers-add-question">
                    <select className="filter-select" defaultValue=""
                      onChange={(e) => { if (e.target.value) { addItemToSection(section.id, Number(e.target.value)); e.target.value = ""; } }}
                      aria-label={`Add question to ${section.title}`}>
                      <option value="">+ Add question to {section.title}...</option>
                      {bankQuestions
                        .filter((q) => !paper.sections.some((s) => s.items.some((i) => i.question_id === q.id)))
                        .map((q) => (
                          <option key={q.id} value={q.id}>
                            [{q.difficulty}] {q.unit ?? "general"} — {q.question_text.slice(0, 72)} ({Number(q.marks)}m)
                          </option>
                        ))}
                    </select>
                  </div>
                )}
              </div>
            ))}

            {paper.status === "draft" && (
              <div className="exam-papers-add-section">
                <Button variant="secondary" onClick={() => setAddSectionOpen(true)}>
                  <Plus size={15} /> Add section
                </Button>
              </div>
            )}
          </>
        )}
      </div>

      {/* Generate modal */}
      <Modal
        open={genOpen}
        title="Generate paper from blueprint"
        onClose={() => setGenOpen(false)}
        footer={
          <>
            <Button variant="secondary" onClick={() => setGenOpen(false)}>Cancel</Button>
            <Button onClick={generate} disabled={generating}>{generating ? "Generating..." : "Generate paper"}</Button>
          </>
        }
      >
        {genError && <div className="institution-form-alert" role="alert">{genError}</div>}

        <FormField label="Subject" htmlFor="gen-subject" required>
          <select id="gen-subject" className="filter-select" value={gen.subject_id}
            onChange={(e) => setGen((c) => ({ ...c, subject_id: e.target.value }))}>
            <option value="">Select subject...</option>
            {subjects.map((s) => (
              <option key={s.id} value={s.id}>{s.code} — {s.name}</option>
            ))}
          </select>
        </FormField>

        <div className="institution-form-grid">
          <FormField label="Paper code (optional)" htmlFor="gen-code">
            <Input id="gen-code" placeholder="BTCSE-CS301-2026-002" value={gen.code}
              onChange={(e) => setGen((c) => ({ ...c, code: e.target.value.toUpperCase() }))} />
          </FormField>
        </div>

        <FormField label="Instructions for candidates" htmlFor="gen-instructions">
          <Input id="gen-instructions" value={gen.instructions}
            onChange={(e) => setGen((c) => ({ ...c, instructions: e.target.value }))} />
        </FormField>

        <fieldset className="exam-papers-blueprint">
          <legend className="exam-papers-blueprint-legend">Section blueprint</legend>
          {gen.sections.map((section, index) => (
            <div key={index} className="institution-form-grid exam-papers-blueprint-row">
              <FormField label="Title" htmlFor={`sec-title-${index}`}>
                <Input id={`sec-title-${index}`} value={section.title}
                  onChange={(e) => setGen((c) => ({ ...c, sections: c.sections.map((s, i) => i === index ? { ...s, title: e.target.value } : s) }))} />
              </FormField>
              <FormField label="Questions" htmlFor={`sec-count-${index}`}>
                <Input id={`sec-count-${index}`} type="number" min={1} max={30} value={section.count}
                  onChange={(e) => setGen((c) => ({ ...c, sections: c.sections.map((s, i) => i === index ? { ...s, count: Number(e.target.value) } : s) }))} />
              </FormField>
              <FormField label="Marks each" htmlFor={`sec-marks-${index}`}>
                <Input id={`sec-marks-${index}`} type="number" min={0.5} step={0.5} value={section.marks}
                  onChange={(e) => setGen((c) => ({ ...c, sections: c.sections.map((s, i) => i === index ? { ...s, marks: Number(e.target.value) } : s) }))} />
              </FormField>
              <FormField label="Difficulty" htmlFor={`sec-diff-${index}`}>
                <select id={`sec-diff-${index}`} className="filter-select" value={section.difficulty}
                  onChange={(e) => setGen((c) => ({ ...c, sections: c.sections.map((s, i) => i === index ? { ...s, difficulty: e.target.value as "" | QuestionDifficulty } : s) }))}>
                  <option value="">Any</option>
                  {["easy", "medium", "hard"].map((d) => <option key={d} value={d}>{d}</option>)}
                </select>
              </FormField>
              <button
                type="button"
                className="timetable-icon-btn"
                aria-label="Remove section"
                disabled={gen.sections.length <= 1}
                onClick={() => setGen((c) => ({ ...c, sections: c.sections.filter((_, i) => i !== index) }))}
              >
                <Trash2 size={14} />
              </button>
            </div>
          ))}
          <Button variant="secondary" onClick={() => setGen((c) => ({ ...c, sections: [...c.sections, { title: `Section ${String.fromCharCode(65 + c.sections.length)}`, count: 2, marks: 10, difficulty: "" }] }))}>
            <Plus size={14} /> Add section
          </Button>
          <span className="institution-mono exam-papers-blueprint-total">
            Total: {gen.sections.reduce((sum, s) => sum + Number(s.count) * Number(s.marks), 0)} marks
          </span>
        </fieldset>

        <p className="exam-papers-blueprint-help">
          Draws randomly from active questions for the subject. Not enough questions in the bank → clean 422.
        </p>
      </Modal>

      {/* Add section modal */}
      <Modal
        open={addSectionOpen}
        title="Add section"
        onClose={() => setAddSectionOpen(false)}
        footer={
          <>
            <Button variant="secondary" onClick={() => setAddSectionOpen(false)}>Cancel</Button>
            <Button onClick={addSection}>Add section</Button>
          </>
        }
      >
        <FormField label="Section title" htmlFor="sec-new-title" required>
          <Input id="sec-new-title" autoFocus placeholder="Section C" value={sectionForm.title}
            onChange={(e) => setSectionForm((c) => ({ ...c, title: e.target.value }))} />
        </FormField>
        <FormField label="Instruction (optional)" htmlFor="sec-new-instruction">
          <Input id="sec-new-instruction" placeholder="Answer any two questions." value={sectionForm.instruction}
            onChange={(e) => setSectionForm((c) => ({ ...c, instruction: e.target.value }))} />
        </FormField>
      </Modal>
    </div>
  );
};

export default QuestionPapersPage;
