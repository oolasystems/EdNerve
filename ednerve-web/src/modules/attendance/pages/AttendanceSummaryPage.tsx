import { useState } from "react";
import { ChartBar, Info } from "lucide-react";
import { useQuery } from "@tanstack/react-query";

import { attendanceService } from "../api";
import "../../institution/institution.css";

import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin, isFacultyLike } from "../../../shared/lib/rbac";
import { academicService } from "../../academic/api";

import { Spinner } from "../../../shared/ui";

const pillStyle = (percent: number): React.CSSProperties => ({
  display: "inline-block",
  minWidth: 58,
  textAlign: "center",
  padding: "4px 8px",
  borderRadius: "var(--radius-md)",
  fontWeight: 700,
  fontSize: 12,
  background:
    percent >= 75
      ? "var(--accent-teal-bg, #d7f5ee)"
      : percent >= 50
        ? "#fef3c7"
        : "#fee2e2",
  color:
    percent >= 75
      ? "var(--text-accent, #0f766e)"
      : percent >= 50
        ? "#92400e"
        : "#b91c1c",
});

const AttendanceSummaryPage = () => {
  const { user } = useAuth();
  const canManage = isInstitutionAdmin(user);
  const isFaculty = isFacultyLike(user);

  const [programmeId, setProgrammeId] = useState<number | null>(null);
  const [semester, setSemester] = useState<number | undefined>(3);
  const [section, setSection] = useState("A");

  const programmesQuery = useQuery({
    queryKey: ["academic", "programmes"],
    queryFn: () => academicService.getProgrammes(),
  });

  const summaryQuery = useQuery({
    queryKey: ["attendance", "summary", programmeId, semester, section],
    queryFn: () =>
      attendanceService.getSummary({
        programme_id: programmeId!,
        semester,
        section,
      }),
    enabled: programmeId !== null,
  });

  const summary = summaryQuery.data;
  const rows = summary?.rows ?? [];
  const subjects = summary?.subjects ?? [];

  return (
    <>
      <div className="institution-directory-toolbar" style={{ gap: 10, flexWrap: "wrap" }}>
        <label htmlFor="sum-programme" className="institution-mono" style={labelStyle}>Programme</label>
        <select
          id="sum-programme"
          className="filter-select"
          value={programmeId ?? ""}
          onChange={(e) => setProgrammeId(e.target.value ? Number(e.target.value) : null)}
        >
          <option value="">Select programme...</option>
          {(programmesQuery.data?.programmes ?? []).map((p) => (
            <option key={p.id} value={p.id}>{p.code} — {p.name}</option>
          ))}
        </select>

        <label htmlFor="sum-semester" className="institution-mono" style={labelStyle}>Semester</label>
        <select
          id="sum-semester"
          className="filter-select"
          value={semester ?? ""}
          onChange={(e) => setSemester(e.target.value === "" ? undefined : Number(e.target.value))}
        >
          <option value="">All</option>
          {[1,2,3,4,5,6,7,8].map((n) => <option key={n} value={n}>{n}</option>)}
        </select>

        <label htmlFor="sum-section" className="institution-mono" style={labelStyle}>Section</label>
        <select id="sum-section" className="filter-select" value={section} onChange={(e) => setSection(e.target.value)}>
          {["A", "B", "C"].map((s) => <option key={s} value={s}>{s}</option>)}
        </select>
      </div>

      {isFaculty && !canManage && (
        <p style={{ color: "var(--text-secondary)", fontSize: 12, display: "flex", gap: 6, alignItems: "center" }}>
          <Info size={13} /> You are viewing summaries for your own subjects only.
        </p>
      )}

      {programmeId === null && (
        <p style={{ color: "var(--text-muted)" }}>Select a programme to view attendance summaries.</p>
      )}

      {programmeId !== null && summaryQuery.isLoading && (
        <div className="dashboard-state"><Spinner size={22} /><p>Loading summary...</p></div>
      )}

      {summary && (
        <div className="institution-admin-panel">
          <h3>
            <ChartBar size={17} style={{ verticalAlign: "-3px" }} /> Attendance summary — {summary.programme.code} · Semester {semester ?? "All"} · Section {summary.section}
          </h3>
          {rows.length === 0 ? (
            <p style={{ color: "var(--text-muted)" }}>
              No attendance recorded yet. Open a class register from the Attendance screen and mark today's class.
            </p>
          ) : (
            <div style={{ overflowX: "auto" }}>
              <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "var(--font-size-sm)" }}>
                <thead>
                  <tr>
                    <th style={th}>Student</th>
                    {subjects.map((s) => (
                      <th key={s.id} style={{ ...th, textAlign: "center" }}>{s.code}</th>
                    ))}
                    <th style={{ ...th, textAlign: "center" }}>TOTAL</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row) => {
                    const bySubject = new Map(row.subjects.map((s) => [s.subject_id, s]));
                    return (
                      <tr key={row.student.id}>
                        <td style={td}>
                          <strong>{row.student.name}</strong>
                          <div className="institution-mono" style={{ fontSize: 11 }}>{row.student.application_number ?? "—"}</div>
                        </td>
                        {subjects.map((s) => {
                          const stat = bySubject.get(s.id);
                          return (
                            <td key={s.id} style={{ ...td, textAlign: "center" }}>
                              {stat ? (
                                <span style={pillStyle(stat.percent)} title={`${stat.attended}/${stat.total} classes`}>
                                  {stat.percent}%
                                </span>
                              ) : (
                                <span style={{ color: "var(--text-muted)" }}>—</span>
                              )}
                            </td>
                          );
                        })}
                        <td style={{ ...td, textAlign: "center" }}>
                          <span style={pillStyle(row.total.percent)} title={`${row.total.attended}/${row.total.total} classes`}>
                            {row.total.percent}%
                          </span>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
              <p style={{ color: "var(--text-muted)", fontSize: 11, marginTop: 8 }}>
                Present and late count as attended. Percentages over classes marked so far.
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

export default AttendanceSummaryPage;
