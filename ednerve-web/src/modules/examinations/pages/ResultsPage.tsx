import { useMemo, useState } from "react";
import { useSearchParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { BadgeCheck, Printer, Info } from "lucide-react";

import { examinationService } from "../api";
import "../../institution/institution.css";

import { Badge, Spinner } from "../../../shared/ui";

const chipStyle = (pass: boolean): React.CSSProperties => ({
  display: "inline-block",
  minWidth: 52,
  textAlign: "center",
  padding: "4px 8px",
  borderRadius: "var(--radius-md)",
  fontWeight: 700,
  fontSize: 12,
  background: pass ? "var(--accent-teal-bg, #d7f5ee)" : "#fee2e2",
  color: pass ? "var(--text-accent, #0f766e)" : "#b91c1c",
});

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
  const rows = data?.results ?? [];
  const subjects = useMemo(() => rows[0]?.subjects ?? [], [rows]);

  return (
    <>
      <div className="institution-directory-toolbar" style={{ gap: 10, flexWrap: "wrap" }}>
        <label htmlFor="res-exam" className="institution-mono" style={labelStyle}>Examination</label>
        <select
          id="res-exam"
          className="filter-select"
          style={{ minWidth: 300 }}
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
        <p style={{ color: "var(--text-secondary)", fontSize: 12, display: "flex", gap: 6, alignItems: "center" }}>
          <Info size={13} /> This is a live preview computed from current marks. Publish from the examination page to freeze results.
        </p>
      )}

      {examId === null && (
        <p style={{ color: "var(--text-muted)" }}>Select an examination to view results.</p>
      )}
      {examId !== null && resultsQuery.isLoading && (
        <div className="dashboard-state"><Spinner size={22} /><p>Computing results...</p></div>
      )}

      {data && (
        <div className="institution-admin-panel">
          <h3>Results — {data.exam.title}</h3>
          {rows.length === 0 ? (
            <p style={{ color: "var(--text-muted)" }}>No students enrolled in this programme.</p>
          ) : (
            <div style={{ overflowX: "auto" }}>
              <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "var(--font-size-sm)" }}>
                <thead>
                  <tr>
                    <th style={th}>Student</th>
                    {subjects.map((s) => (
                      <th key={s.subject_id} style={{ ...th, textAlign: "center" }}>
                        {s.code}<br />
                        <span style={{ fontWeight: 400, textTransform: "none", letterSpacing: 0 }}>{s.obtained}/{s.max}</span>
                      </th>
                    ))}
                    <th style={{ ...th, textAlign: "center" }}>TOTAL</th>
                    <th style={{ ...th, textAlign: "center" }}>GRADE</th>
                    <th style={{ ...th, textAlign: "center" }}>RESULT</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row) => (
                    <tr key={row.student.id}>
                      <td style={td}>
                        <strong>{row.student.name}</strong>
                        <div className="institution-mono" style={{ fontSize: 11 }}>{row.student.application_number ?? "—"}</div>
                      </td>
                      {row.subjects.map((s) => (
                        <td key={s.subject_id} style={{ ...td, textAlign: "center" }}>
                          <span style={chipStyle(s.pass)} title={`${s.obtained}/${s.max} · ${s.percent}%`}>
                            {s.percent}%
                          </span>
                          <div className="institution-mono" style={{ fontSize: 10, color: "var(--text-muted)" }}>{s.grade}</div>
                        </td>
                      ))}
                      <td style={{ ...td, textAlign: "center" }}>
                        <strong>{row.total.obtained}</strong> / {row.total.max}
                        <div className="institution-mono" style={{ fontSize: 11 }}>{row.total.percent}%</div>
                      </td>
                      <td style={{ ...td, textAlign: "center" }}><strong>{row.total.grade}</strong></td>
                      <td style={{ ...td, textAlign: "center" }}>
                        <Badge tone={row.total.status === "pass" ? "success" : "danger"}>
                          {row.total.status.toUpperCase()}
                        </Badge>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
              <p style={{ color: "var(--text-muted)", fontSize: 11, marginTop: 8, display: "flex", gap: 6, alignItems: "center" }}>
                <Printer size={12} /> Printable hall tickets are available from the examination page → Hall tickets.
              </p>
            </div>
          )}
        </div>
      )}
    </>
  );
};

const labelStyle: React.CSSProperties = {
  fontSize: "var(--font-size-xs)",
  color: "var(--text-secondary)",
  textTransform: "uppercase",
  letterSpacing: "0.05em",
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

export default ResultsPage;
