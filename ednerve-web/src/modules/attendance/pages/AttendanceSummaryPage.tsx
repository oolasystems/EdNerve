import "../attendance.css";
import { useState } from "react";
import { ChartBar, Info } from "lucide-react";
import { useQuery } from "@tanstack/react-query";

import { attendanceService } from "../api";

import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin, isFacultyLike } from "../../../shared/lib/rbac";
import { academicService } from "../../academic/api";

import { Spinner } from "../../../shared/ui";


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
      <div className="institution-directory-toolbar attendance-toolbar">
        <label htmlFor="sum-programme" className="institution-mono attendance-filter-label">Programme</label>
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

        <label htmlFor="sum-semester" className="institution-mono attendance-filter-label">Semester</label>
        <select
          id="sum-semester"
          className="filter-select"
          value={semester ?? ""}
          onChange={(e) => setSemester(e.target.value === "" ? undefined : Number(e.target.value))}
        >
          <option value="">All</option>
          {[1,2,3,4,5,6,7,8].map((n) => <option key={n} value={n}>{n}</option>)}
        </select>

        <label htmlFor="sum-section" className="institution-mono attendance-filter-label">Section</label>
        <select id="sum-section" className="filter-select" value={section} onChange={(e) => setSection(e.target.value)}>
          {["A", "B", "C"].map((s) => <option key={s} value={s}>{s}</option>)}
        </select>
      </div>

      {isFaculty && !canManage && (
        <p className="attendance-faculty-note">
          <Info size={13} /> You are viewing summaries for your own subjects only.
        </p>
      )}

      {programmeId === null && (
        <p className="attendance-muted-message">Select a programme to view attendance summaries.</p>
      )}

      {programmeId !== null && summaryQuery.isLoading && (
        <div className="dashboard-state"><Spinner size={22} /><p>Loading summary...</p></div>
      )}

      {summary && (
        <div className="institution-admin-panel">
          <h3>
            <ChartBar size={17} aria-hidden="true" /> Attendance summary — {summary.programme.code} · Semester {semester ?? "All"} · Section {summary.section}
          </h3>
          {rows.length === 0 ? (
            <p className="attendance-muted-message">
              No attendance recorded yet. Open a class register from the Attendance screen and mark today's class.
            </p>
          ) : (
            <div className="attendance-table-scroll">
              <table className="attendance-summary-table">
                <thead>
                  <tr>
                    <th className="attendance-table-heading">Student</th>
                    {subjects.map((s) => (
                      <th key={s.id} className="attendance-table-heading attendance-cell-centered">{s.code}</th>
                    ))}
                    <th className="attendance-table-heading attendance-cell-centered">TOTAL</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row) => {
                    const bySubject = new Map(row.subjects.map((s) => [s.subject_id, s]));
                    return (
                      <tr key={row.student.id}>
                        <td className="attendance-table-cell">
                          <strong>{row.student.name}</strong>
                          <div className="institution-mono attendance-secondary-text">{row.student.application_number ?? "—"}</div>
                        </td>
                        {subjects.map((s) => {
                          const stat = bySubject.get(s.id);
                          return (
                            <td key={s.id} className="attendance-table-cell attendance-cell-centered">
                              {stat ? (
                                <span className={`attendance-percent-pill ${stat.percent >= 75 ? "attendance-percent-high" : stat.percent >= 50 ? "attendance-percent-medium" : "attendance-percent-low"}`} title={`${stat.attended}/${stat.total} classes`}>
                                  {stat.percent}%
                                </span>
                              ) : (
                                <span className="attendance-muted-dash">—</span>
                              )}
                            </td>
                          );
                        })}
                        <td className="attendance-table-cell attendance-cell-centered">
                          <span className={`attendance-percent-pill ${row.total.percent >= 75 ? "attendance-percent-high" : row.total.percent >= 50 ? "attendance-percent-medium" : "attendance-percent-low"}`} title={`${row.total.attended}/${row.total.total} classes`}>
                            {row.total.percent}%
                          </span>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
              <p className="attendance-table-note">
                Present and late count as attended. Percentages over classes marked so far.
              </p>
            </div>
          )}
        </div>
      )}
    </>
  );
};


export default AttendanceSummaryPage;
