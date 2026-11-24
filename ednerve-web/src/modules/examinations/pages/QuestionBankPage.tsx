import { useState } from "react";
import { BookOpenCheck, Plus, Pencil, Archive, ChevronDown, ChevronUp } from "lucide-react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { questionBankService } from "../question-bank-api";
import type { BankQuestion, QuestionDifficulty, QuestionType, QuestionStatus } from "../question-bank-api";
import "../../institution/institution.css";

import { academicService } from "../../academic/api";
import { getApiErrorMessage } from "../../../shared/lib/api-error";
import {
  Alert,
  Badge,
  Button,
  FormField,
  Input,
  Modal,
  Spinner,
} from "../../../shared/ui";

const DIFFICULTY_TONE: Record<QuestionDifficulty, "success" | "info" | "danger"> = {
  easy: "success",
  medium: "info",
  hard: "danger",
};

const TYPE_LABEL: Record<QuestionType, string> = {
  mcq: "MCQ",
  short: "Short",
  long: "Long",
  numerical: "Numerical",
};

const emptyForm = {
  subject_id: "" as string | number,
  unit: "",
  difficulty: "medium" as QuestionDifficulty,
  type: "long" as QuestionType,
  marks: 10,
  question_text: "",
  model_answer: "",
  tags: "",
  status: "active" as QuestionStatus,
};

const QuestionBankPage = () => {
  const qc = useQueryClient();

  const [subjectId, setSubjectId] = useState<number | "">("");
  const [difficulty, setDifficulty] = useState<"" | QuestionDifficulty>("");
  const [type, setType] = useState<"" | QuestionType>("");
  const [statusFilter, setStatusFilter] = useState<"" | QuestionStatus>("active");
  const [search, setSearch] = useState("");

  const subjectsQuery = useQuery({
    queryKey: ["academic", "subjects"],
    queryFn: () => academicService.getSubjects(),
  });

  const questionsQuery = useQuery({
    queryKey: ["question-bank", "questions", subjectId, difficulty, type, statusFilter, search],
    queryFn: () =>
      questionBankService.getQuestions({
        subject_id: subjectId === "" ? undefined : Number(subjectId),
        difficulty: difficulty || undefined,
        type: type || undefined,
        status: statusFilter || undefined,
        q: search.trim() || undefined,
      }),
  });

  const questions = questionsQuery.data?.questions ?? [];
  const subjects = subjectsQuery.data?.subjects ?? [];

  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<BankQuestion | null>(null);
  const [form, setForm] = useState(emptyForm);
  const [error, setError] = useState("");
  const [saving, setSaving] = useState(false);

  const openAdd = () => {
    setEditing(null);
    setError("");
    setForm(emptyForm);
    setModalOpen(true);
  };

  const openEdit = (question: BankQuestion) => {
    setEditing(question);
    setError("");
    setForm({
      subject_id: question.subject_id,
      unit: question.unit ?? "",
      difficulty: question.difficulty,
      type: question.type,
      marks: Number(question.marks),
      question_text: question.question_text,
      model_answer: question.model_answer,
      tags: question.tags ?? "",
      status: question.status,
    });
    setModalOpen(true);
  };

  const saveQuestion = async () => {
    setError("");
    if (form.subject_id === "") { setError("Select a subject."); return; }
    if (!form.question_text.trim() || !form.model_answer.trim()) {
      setError("Question text and model answer are required.");
      return;
    }
    setSaving(true);
    const payload = {
      subject_id: Number(form.subject_id),
      unit: form.unit.trim() || undefined,
      difficulty: form.difficulty,
      type: form.type,
      marks: Number(form.marks),
      question_text: form.question_text.trim(),
      model_answer: form.model_answer.trim(),
      tags: form.tags.trim() || undefined,
      status: form.status,
    };
    try {
      if (editing) {
        await questionBankService.updateQuestion(editing.id, payload);
        toast.success("Question updated.");
      } else {
        await questionBankService.createQuestion(payload);
        toast.success("Question added to the bank.");
      }
      await qc.invalidateQueries({ queryKey: ["question-bank"] });
      setModalOpen(false);
    } catch (e) {
      setError(getApiErrorMessage(e, "Unable to save the question."));
    } finally {
      setSaving(false);
    }
  };

  const archiveQuestion = async (question: BankQuestion) => {
    if (!window.confirm("Archive this question? Papers already printed keep their questions.")) return;
    try {
      await questionBankService.archiveQuestion(question.id);
      await qc.invalidateQueries({ queryKey: ["question-bank"] });
      toast.success("Question archived.");
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to archive."));
    }
  };

  return (
    <>
      <div className="institution-directory-toolbar" style={{ gap: 10, flexWrap: "wrap" }}>
        <select className="filter-select" value={subjectId} onChange={(e) => setSubjectId(e.target.value === "" ? "" : Number(e.target.value))} aria-label="Filter by subject">
          <option value="">All subjects</option>
          {subjects.map((s) => (
            <option key={s.id} value={s.id}>{s.code} — {s.name}</option>
          ))}
        </select>
        <select className="filter-select" value={difficulty} onChange={(e) => setDifficulty(e.target.value as "" | QuestionDifficulty)} aria-label="Difficulty">
          <option value="">All difficulties</option>
          {(["easy", "medium", "hard"] as QuestionDifficulty[]).map((d) => <option key={d} value={d}>{d}</option>)}
        </select>
        <select className="filter-select" value={type} onChange={(e) => setType(e.target.value as "" | QuestionType)} aria-label="Type">
          <option value="">All types</option>
          {(Object.keys(TYPE_LABEL) as QuestionType[]).map((t) => <option key={t} value={t}>{TYPE_LABEL[t]}</option>)}
        </select>
        <select className="filter-select" value={statusFilter} onChange={(e) => setStatusFilter(e.target.value as "" | QuestionStatus)} aria-label="Status">
          <option value="active">Active</option>
          <option value="draft">Draft</option>
          <option value="archived">Archived</option>
          <option value="">All statuses</option>
        </select>
        <input
          type="text"
          className="filter-select"
          placeholder="Search tags / unit..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          aria-label="Search question bank"
        />
        <Button onClick={openAdd}>
          <Plus size={16} /> Add question
        </Button>
      </div>

      {questionsQuery.isError && (
        <Alert tone="error">
          Unable to load the question bank right now — the API may be warming up.
          <button type="button" onClick={() => void questionsQuery.refetch()}>Retry</button>
        </Alert>
      )}
      <div className="institution-admin-panel">
        <h3>
          <BookOpenCheck size={17} style={{ verticalAlign: "-3px" }} /> Question bank — {questions.length} question{questions.length === 1 ? "" : "s"}
        </h3>
        {questionsQuery.isLoading ? (
          <Spinner size={20} />
        ) : questions.length === 0 ? (
          <p style={{ color: "var(--text-muted)" }}>No questions match. Add the first one — encrypted at rest, visible only to administrators and examination officers.</p>
        ) : (
          <table style={tableStyle}>
            <thead>
              <tr>
                <th style={th}>Subject</th>
                <th style={th}>Unit</th>
                <th style={th}>Difficulty</th>
                <th style={th}>Type</th>
                <th style={th}>Marks</th>
                <th style={th}>Question preview</th>
                <th style={th}>Status</th>
                <th style={th}></th>
              </tr>
            </thead>
            <tbody>
              {questions.map((q) => {
                const expanded = expandedId === q.id;
                return (
                  <FragmentRow
                    key={q.id}
                    question={q}
                    expanded={expanded}
                    onToggle={() => setExpandedId(expanded ? null : q.id)}
                    onEdit={() => openEdit(q)}
                    onArchive={() => void archiveQuestion(q)}
                  />
                );
              })}
            </tbody>
          </table>
        )}
      </div>

      <Modal
        open={modalOpen}
        title={editing ? "Edit question" : "Add question"}
        onClose={() => setModalOpen(false)}
        footer={
          <>
            <Button variant="secondary" onClick={() => setModalOpen(false)}>Cancel</Button>
            <Button onClick={saveQuestion} disabled={saving}>{saving ? "Saving..." : editing ? "Save changes" : "Add question"}</Button>
          </>
        }
      >
        {error && <div className="institution-form-alert" role="alert">{error}</div>}

        <div className="institution-form-grid">
          <FormField label="Subject" htmlFor="qb-subject" required>
            <select id="qb-subject" className="filter-select" value={form.subject_id}
              onChange={(e) => setForm((c) => ({ ...c, subject_id: e.target.value }))}>
              <option value="">Select subject...</option>
              {subjects.map((s) => (
                <option key={s.id} value={s.id}>{s.code} — {s.name}</option>
              ))}
            </select>
          </FormField>
          <FormField label="Unit" htmlFor="qb-unit">
            <Input id="qb-unit" placeholder="Graphs / SQL / Synchronization" value={form.unit}
              onChange={(e) => setForm((c) => ({ ...c, unit: e.target.value }))} />
          </FormField>
        </div>

        <div className="institution-form-grid">
          <FormField label="Difficulty" htmlFor="qb-difficulty">
            <select id="qb-difficulty" className="filter-select" value={form.difficulty}
              onChange={(e) => setForm((c) => ({ ...c, difficulty: e.target.value as QuestionDifficulty }))}>
              {["easy", "medium", "hard"].map((d) => <option key={d} value={d}>{d}</option>)}
            </select>
          </FormField>
          <FormField label="Type" htmlFor="qb-type">
            <select id="qb-type" className="filter-select" value={form.type}
              onChange={(e) => setForm((c) => ({ ...c, type: e.target.value as QuestionType }))}>
              {(Object.keys(TYPE_LABEL) as QuestionType[]).map((t) => <option key={t} value={t}>{TYPE_LABEL[t]}</option>)}
            </select>
          </FormField>
          <FormField label="Marks" htmlFor="qb-marks">
            <Input id="qb-marks" type="number" step="0.5" min={0.5} value={form.marks}
              onChange={(e) => setForm((c) => ({ ...c, marks: Number(e.target.value) }))} />
          </FormField>
        </div>

        <FormField label="Question text" htmlFor="qb-text" required>
          <textarea id="qb-text" className="filter-select" style={{ width: "100%", minHeight: 70 }}
            value={form.question_text} onChange={(e) => setForm((c) => ({ ...c, question_text: e.target.value }))} />
        </FormField>

        <FormField label="Model answer" htmlFor="qb-answer" required>
          <textarea id="qb-answer" className="filter-select" style={{ width: "100%", minHeight: 70 }}
            value={form.model_answer} onChange={(e) => setForm((c) => ({ ...c, model_answer: e.target.value }))} />
        </FormField>

        <FormField label="Tags (comma separated)" htmlFor="qb-tags">
          <Input id="qb-tags" placeholder="graphs, complexity" value={form.tags}
            onChange={(e) => setForm((c) => ({ ...c, tags: e.target.value }))} />
        </FormField>
      </Modal>
    </>
  );
};

const FragmentRow = ({
  question, expanded, onToggle, onEdit, onArchive,
}: {
  question: BankQuestion;
  expanded: boolean;
  onToggle: () => void;
  onEdit: () => void;
  onArchive: () => void;
}) => (
  <>
    <tr>
      <td style={td}>{question.subject?.code ?? "—"}</td>
      <td style={td}>{question.unit ?? "—"}</td>
      <td style={td}><Badge tone={DIFFICULTY_TONE[question.difficulty]}>{question.difficulty}</Badge></td>
      <td style={td}>{TYPE_LABEL[question.type]}</td>
      <td style={td}>{Number(question.marks)}</td>
      <td style={{ ...td, maxWidth: 320 }}>
        <div style={{ whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{question.question_text}</div>
      </td>
      <td style={td}><Badge tone={question.status === "active" ? "success" : question.status === "draft" ? "info" : "danger"}>{question.status}</Badge></td>
      <td style={td}>
        <button type="button" className="timetable-icon-btn" onClick={onToggle} aria-label={expanded ? "Collapse" : "Expand"}>
          {expanded ? <ChevronUp size={13} /> : <ChevronDown size={13} />}
        </button>
        <button type="button" className="timetable-icon-btn" onClick={onEdit} aria-label="Edit">
          <Pencil size={13} />
        </button>
        <button type="button" className="timetable-icon-btn" onClick={onArchive} aria-label="Archive">
          <Archive size={13} />
        </button>
      </td>
    </tr>
    {expanded && (
      <tr>
        <td colSpan={8} style={{ ...td, background: "var(--surface-secondary)" }}>
          <div style={{ fontSize: 12, color: "var(--text-secondary)", marginBottom: 6 }}>Model answer</div>
          <div style={{ whiteSpace: "pre-wrap", fontSize: 13 }}>{question.model_answer}</div>
        </td>
      </tr>
    )}
  </>
);

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

export default QuestionBankPage;
