import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useQuery, useQueryClient } from "@tanstack/react-query";
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
import "../../institution/institution.css";

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
    } catch (e: any) {
      setGenError(e?.response?.data?.message ?? "Unable to generate. Bank lacks enough active questions.");
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
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? "Action failed.");
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
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? "Unable to send back.");
    }
  };

  const patchStatus = async (next: PaperStatus) => {
    if (!paper) return;
    try {
      await questionBankService.updatePaper(paper.id, { status: next });
      await qc.invalidateQueries({ queryKey: ["question-bank", "paper", paper.id] });
      await qc.invalidateQueries({ queryKey: ["question-bank", "papers"] });
      toast.success(`Paper marked ${next}.`);
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? "Unable to update status.");
    }
  };

  const deletePaper = async (id: number) => {
    if (!window.confirm("Delete this draft paper? The questions stay in the bank.")) return;
    try {
      await questionBankService.deletePaper(id);
      if (selectedId === id) setSelectedId(null);
      await qc.invalidateQueries({ queryKey: ["question-bank", "papers"] });
      toast.success("Paper deleted.");
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? "Unable to delete.");
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
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? "Unable to add the section.");
    }
  };

  const addItemToSection = async (sectionId: number, questionId: number) => {
    if (!paper) return;
    try {
      await questionBankService.addPaperItem(paper.id, { section_id: sectionId, question_id: questionId });
      await qc.invalidateQueries({ queryKey: ["question-bank", "paper", paper.id] });
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? "Unable to add the question.");
    }
  };

  const removeItem = async (itemId: number) => {
    if (!paper) return;
    try {
      await questionBankService.removePaperItem(paper.id, itemId);
      await qc.invalidateQueries({ queryKey: ["question-bank", "paper", paper.id] });
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? "Unable to remove.");
    }
  };

  const bankQuestions = bankQuery.data?.questions ?? [];

  return (
    <div className="institution-two-column">
      {/* ============ Papers list ============ */}
      <div>
        <div className="institution-directory-toolbar" style={{ gap: 10, flexWrap: "wrap" }}>
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
            <Layers3 size={17} style={{ verticalAlign: "-3px" }} /> Question papers — {papers.length}
          </h3>
          {papersQuery.isLoading ? (
            <Spinner size={20} />
          ) : papers.length === 0 ? (
            <p style={{ color: "var(--text-muted)" }}>No papers yet. Generate one from the bank, or build manually.</p>
          ) : (
            <table style={tableStyle}>
              <thead>
                <tr>
                  <th style={th}>Code</th>
                  <th style={th}>Subject</th>
                  <th style={th}>Marks</th>
                  <th style={th}>Items</th>
                  <th style={th}>Status</th>
                  <th style={th}>Source</th>
                  <th style={th}></th>
                </tr>
              </thead>
              <tbody>
                {papers.map((p) => (
                  <tr key={p.id} className={selectedId === p.id ? "row-selected" : ""}>
                    <td style={td}><strong className="institution-mono" style={{ fontSize: 12 }}>{p.code}</strong></td>
                    <td style={td}>{p.subject?.code ?? "—"}</td>
                    <td style={td}>{p.total_marks}</td>
                    <td style={td}>{p.items_count}</td>
                    <td style={td}><Badge tone={STATUS_TONE[p.status]}>{p.status}</Badge></td>
                    <td style={td}><span className="institution-mono" style={{ fontSize: 11 }}>{p.source}</span></td>
                    <td style={td}>
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
            <p style={{ color: "var(--text-muted)" }}>Open a paper from the list to edit its sections, questions and instructions, or print it.</p>
          </>
        )}

        {paper && (
          <>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", gap: 10, flexWrap: "wrap" }}>
              <div>
                <h3 style={{ marginBottom: 2 }} className="institution-mono">{paper.code}</h3>
                <div className="institution-mono" style={{ fontSize: 12, color: "var(--text-secondary)" }}>
                  {paper.subject?.code} · {paper.total_marks} marks · {paper.source} · {paper.status}
                </div>
                <div className="institution-mono" style={{ fontSize: 11, color: "var(--text-muted)", marginTop: 2 }}>
                  {paper.approvals && paper.approvals.length > 0
                    ? `Workflow: ${paper.approvals.map((a) => `${a.stage}${a.decision === "rejected" ? "x" : ""}`).join(" > ")}`
                    : "Not submitted yet"}
                </div>
              </div>
              <div style={{ display: "flex", gap: 8, alignItems: "center" }}>
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

            <p style={{ color: "var(--text-muted)", fontSize: 12, marginTop: 6 }}>
              {paper.instructions ?? "No instructions set — edit in paper settings below."}
            </p>

            {/* Sections */}
            {paper.sections.map((section) => (
              <div key={section.id} style={{ marginTop: 14, border: "1px solid var(--border)", borderRadius: "var(--radius-md)", padding: "10px 12px" }}>
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "baseline", gap: 10 }}>
                  <strong>{section.title}</strong>
                  <span className="institution-mono" style={{ fontSize: 11, color: "var(--text-muted)" }}>
                    {section.instruction}
                  </span>
                </div>
                <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "var(--font-size-sm)", marginTop: 6 }}>
                  <tbody>
                    {section.items.map((item) => (
                      <tr key={item.id}>
                        <td style={td}>
                          <span className="institution-mono" style={{ fontSize: 11 }}>Q{item.position}</span>
                        </td>
                        <td style={{ ...td, maxWidth: 0 }}>
                          <div style={{ whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{item.question_text}</div>
                          <div className="institution-mono" style={{ fontSize: 11, color: "var(--text-muted)" }}>
                            {item.type?.toUpperCase()} · {item.difficulty} · {item.unit ?? "general"}
                          </div>
                        </td>
                        <td style={{ ...td, textAlign: "right" }}>{item.marks}m</td>
                        {paper.status === "draft" && (
                          <td style={td}>
                            <button type="button" className="timetable-icon-btn" onClick={() => removeItem(item.id)} aria-label="Remove question">
                              <Trash2 size={13} />
                            </button>
                          </td>
                        )}
                      </tr>
                    ))}
                    {section.items.length === 0 && (
                      <tr><td colSpan={4} style={{ ...td, color: "var(--text-muted)" }}>Empty section.</td></tr>
                    )}
                  </tbody>
                </table>

                {/* Add question picker */}
                {paper.status === "draft" && (
                  <div style={{ marginTop: 8 }}>
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
              <div style={{ marginTop: 12 }}>
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

        <fieldset style={{ border: "1px solid var(--border)", borderRadius: "var(--radius-md)", padding: "8px 12px", marginTop: 8 }}>
          <legend style={{ fontSize: 12, color: "var(--text-secondary)", padding: "0 6px" }}>Section blueprint</legend>
          {gen.sections.map((section, index) => (
            <div key={index} className="institution-form-grid" style={{ alignItems: "end", marginBottom: 8 }}>
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
                  onChange={(e) => setGen((c) => ({ ...c, sections: c.sections.map((s, i) => i === index ? { ...s, difficulty: e.target.value as any } : s) }))}>
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
          <span className="institution-mono" style={{ marginLeft: 12, fontSize: 13 }}>
            Total: {gen.sections.reduce((sum, s) => sum + Number(s.count) * Number(s.marks), 0)} marks
          </span>
        </fieldset>

        <p style={{ color: "var(--text-muted)", fontSize: 12, marginTop: 8 }}>
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

const tableStyle: React.CSSProperties = {
  width: "100%",
  borderCollapse: "collapse",
  fontSize: "var(--font-size-sm)",
};
const th: React.CSSProperties = {
  textAlign: "left",
  padding: "8px 10px",
  borderBottom: "1px solid var(--border)",
  color: "var(--text-muted)",
  fontSize: "var(--font-size-xs)",
  textTransform: "uppercase",
  letterSpacing: "0.05em",
};
const td: React.CSSProperties = {
  padding: "10px",
  borderBottom: "1px solid var(--border)",
  verticalAlign: "middle",
};

export default QuestionPapersPage;
