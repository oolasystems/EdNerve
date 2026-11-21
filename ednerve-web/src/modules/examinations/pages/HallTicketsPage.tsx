import { useState } from "react";
import { useSearchParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { Printer } from "lucide-react";

import { examinationService } from "../api";
import "../../institution/institution.css";

import { Button, Spinner } from "../../../shared/ui";

const HallTicketsPage = () => {
  const [searchParams] = useSearchParams();
  const [examId, setExamId] = useState<number | null>(() => {
    const q = searchParams.get("exam");
    return q && Number.isFinite(Number(q)) ? Number(q) : null;
  });
  const [studentProfileId, setStudentProfileId] = useState<number | null>(null);

  const examsQuery = useQuery({
    queryKey: ["examinations", "list"],
    queryFn: () => examinationService.getExams(),
  });

  const ticketQuery = useQuery({
    queryKey: ["examinations", "hall-ticket", examId, studentProfileId],
    queryFn: () => examinationService.getHallTicket(examId!, studentProfileId!),
    enabled: examId !== null && studentProfileId !== null,
  });

  // Students for the roster come from the results computation (enrollment ∩ programme).
  const resultsQuery = useQuery({
    queryKey: ["examinations", "results", examId],
    queryFn: () => examinationService.getResults(examId!),
    enabled: examId !== null,
  });

  const roster = resultsQuery.data?.results ?? [];
  const ticket = ticketQuery.data;

  return (
    <>
      <div className="institution-directory-toolbar no-print" style={{ gap: 10, flexWrap: "wrap" }}>
        <label htmlFor="ht-exam" className="institution-mono" style={labelStyle}>Examination</label>
        <select id="ht-exam" className="filter-select" style={{ minWidth: 280 }} value={examId ?? ""}
          onChange={(e) => { setExamId(e.target.value ? Number(e.target.value) : null); setStudentProfileId(null); }}>
          <option value="">Select examination...</option>
          {(examsQuery.data?.exams ?? []).map((exam) => (
            <option key={exam.id} value={exam.id}>{exam.title}</option>
          ))}
        </select>

        {examId !== null && (
          <>
            <label htmlFor="ht-student" className="institution-mono" style={labelStyle}>Candidate</label>
            <select id="ht-student" className="filter-select" style={{ minWidth: 220 }} value={studentProfileId ?? ""}
              onChange={(e) => setStudentProfileId(e.target.value ? Number(e.target.value) : null)}>
              <option value="">Select candidate...</option>
              {roster.map((row) => (
                <option key={row.student.id} value={row.student.id}>
                  {row.student.name} — {row.student.application_number ?? row.student.id}
                </option>
              ))}
            </select>
          </>
        )}

        {ticket && (
          <Button onClick={() => window.print()}>
            <Printer size={15} /> Print hall ticket
          </Button>
        )}
      </div>

      {examId === null && (
        <p style={{ color: "var(--text-muted)" }}>Select an examination and a candidate to preview the hall ticket.</p>
      )}

      {resultsQuery.isLoading && (
        <div className="dashboard-state"><Spinner size={22} /><p>Loading candidates...</p></div>
      )}

      {ticket && (
        <div className="hall-ticket institution-admin-panel">
          <div className="hall-ticket-header">
            <div className="hall-ticket-institution">{ticket.institution.name}</div>
            <div className="hall-ticket-number institution-mono">HALL TICKET No: {ticket.hall_ticket_number}</div>
          </div>

          <div className="hall-ticket-exam">{ticket.exam.title}</div>
          <div className="institution-mono" style={{ fontSize: 12, color: "var(--text-secondary)", marginBottom: 12 }}>
            {ticket.exam.programme?.code} · Semester {ticket.exam.semester} · Section {ticket.exam.section}
            {ticket.exam.academic_session ? ` · ${ticket.exam.academic_session.code}` : ""}
          </div>

          <table className="hall-ticket-table">
            <tbody>
              <tr>
                <th>Candidate name</th>
                <td>{ticket.student.user?.name}</td>
              </tr>
              <tr>
                <th>Application number</th>
                <td>{ticket.student.application_number ?? "—"}</td>
              </tr>
              <tr>
                <th>Email</th>
                <td>{ticket.student.user?.email}</td>
              </tr>
            </tbody>
          </table>

          <table className="hall-ticket-table" style={{ marginTop: 12 }}>
            <thead>
              <tr>
                <th>Date</th>
                <th>Paper</th>
                <th>Time</th>
                <th>Venue</th>
              </tr>
            </thead>
            <tbody>
              {ticket.subjects.map((s) => (
                <tr key={s.code}>
                  <td>{s.date}</td>
                  <td>{s.code} — {s.name}</td>
                  <td>{s.start_time?.slice(0, 5)}–{s.end_time?.slice(0, 5)}</td>
                  <td>{s.venue ?? "—"}</td>
                </tr>
              ))}
            </tbody>
          </table>

          {ticket.exam.instructions && (
            <div className="hall-ticket-instructions">
              <strong>Instructions:</strong> {ticket.exam.instructions}
            </div>
          )}

          <div className="hall-ticket-footer">
            <div>Signature of candidate</div>
            <div>Controller of Examinations</div>
          </div>
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

export default HallTicketsPage;
