import "../examinations.css";
import { useMemo, useState } from "react";
import { useSearchParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { BadgeCheck, Printer, Info } from "lucide-react";

import { examinationService } from "../api";
import type { ResultRow } from "../api";

import { Badge, Spinner } from "../../../shared/ui";

const resultChipClass = (pass: boolean) =>
  `results-score-chip${pass ? " is-pass" : " is-fail"}`;

const EMPTY_RESULT_ROWS: ResultRow[] = [];

const ResultsPage = () => {
  const [searchParams] = useSearchParams();
  const [examId, setExamId] = useState<number | null>(() => {
    const q = searchParams.get("exam");
    return q && Number.isFinite(Number(q)) ? Number(q) : null;
  });

  const examsQuery = useQuery({
    queryKey: ["examinations", "list"],
    queryFn: () => examinationService.getExams(),
  });

  const resultsQuery = useQuery({
    queryKey: ["examinations", "results", examId],
    queryFn: () => examinationService.getResults(examId!),
    enabled: examId !== null,
  });

  const data = resultsQuery.data;
  const rows = data?.results ?? EMPTY_RESULT_ROWS;
  const subjects = useMemo(() => rows[0]?.subjects ?? [], [rows]);

  return (
    <>
      <div className="institution-directory-toolbar results-toolbar">
        <label htmlFor="res-exam" className="institution-mono exam-field-label">Examination</label>
        <select
          id="res-exam"
          className="filter-select results-exam-select"
          value={examId ?? ""}
          onChange={(e) => setExamId(e.target.value ? Number(e.target.value) : null)}
        >
          <option value="">Select examination...</option>
          {(examsQuery.data?.exams ?? []).map((exam) => (
            <option key={exam.id} value={exam.id}>
              {exam.title} — {exam.status}
            </option>
          ))}
        </select>
        {data?.published && <Badge tone="success"><BadgeCheck size={12} /> Published</Badge>}
        {data && !data.published && <Badge tone="info">Preview — not published</Badge>}
      </div>

      {data && !data.published && (
        <p className="results-info-note">
          <Info size={13} /> This is a live preview computed from current marks. Publish from the examination page to freeze results.
        </p>
      )}

      {examId === null && (
        <p className="exam-muted">Select an examination to view results.</p>
      )}
      {examId !== null && resultsQuery.isLoading && (
        <div className="dashboard-state"><Spinner size={22} /><p>Computing results...</p></div>
      )}

      {data && (
        <div className="institution-admin-panel">
          <h3>Results — {data.exam.title}</h3>
          {rows.length === 0 ? (
            <p className="exam-muted">No students enrolled in this programme.</p>
          ) : (
            <div className="results-table-scroll">
              <table className="exam-data-table results-table">
                <thead>
                  <tr>
                    <th className="exam-data-table-heading">Student</th>
                    {subjects.map((s) => (
                      <th key={s.subject_id} className="exam-data-table-heading exam-cell-centered">
                        {s.code}<br />
                        <span className="results-score-subheading">{s.obtained}/{s.max}</span>
                      </th>
                    ))}
                    <th className="exam-data-table-heading exam-cell-centered">TOTAL</th>
                    <th className="exam-data-table-heading exam-cell-centered">GRADE</th>
                    <th className="exam-data-table-heading exam-cell-centered">RESULT</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row) => (
                    <tr key={row.student.id}>
                      <td className="exam-data-table-cell">
                        <strong>{row.student.name}</strong>
                        <div className="institution-mono exam-record-meta">{row.student.application_number ?? "—"}</div>
                      </td>
                      {row.subjects.map((s) => (
                        <td key={s.subject_id} className="exam-data-table-cell exam-cell-centered">
                          <span className={resultChipClass(s.pass)} title={`${s.obtained}/${s.max} · ${s.percent}%`}>
                            {s.percent}%
                          </span>
                          <div className="institution-mono results-grade-meta">{s.grade}</div>
                        </td>
                      ))}
                      <td className="exam-data-table-cell exam-cell-centered">
                        <strong>{row.total.obtained}</strong> / {row.total.max}
                        <div className="institution-mono exam-record-meta">{row.total.percent}%</div>
                      </td>
                      <td className="exam-data-table-cell exam-cell-centered"><strong>{row.total.grade}</strong></td>
                      <td className="exam-data-table-cell exam-cell-centered">
                        <Badge tone={row.total.status === "pass" ? "success" : "danger"}>
                          {row.total.status.toUpperCase()}
                        </Badge>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
              <p className="results-print-note">
                <Printer size={12} aria-hidden="true" /> Printable hall tickets are available from the examination page → Hall tickets.
              </p>
            </div>
          )}
        </div>
      )}
    </>
  );
};


export default ResultsPage;
