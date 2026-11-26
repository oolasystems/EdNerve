import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { CalendarDays, CheckCircle, RefreshCw, XCircle } from "lucide-react";
import { toast } from "sonner";

import { myDutiesService } from "../api";
import type { MyDutyAssignment } from "../api";
import { getApiErrorMessage } from "../../../shared/lib/api-error";
import type { BadgeTone } from "../../../shared/ui/Badge";
import { Badge, Button, FormField, Input, Modal, Spinner } from "../../../shared/ui";

import "../../institution/institution.css";

const tableStyle: React.CSSProperties = {
  width: "100%",
  borderCollapse: "collapse",
  fontSize: "var(--font-size-sm)",
};
const th: React.CSSProperties = {
  textAlign: "left",
  padding: "10px",
  borderBottom: "1px solid var(--border)",
  color: "var(--text-muted)",
  fontSize: "var(--font-size-xs)",
  textTransform: "uppercase",
  letterSpacing: "0.05em",
};
const td: React.CSSProperties = {
  padding: "12px 10px",
  borderBottom: "1px solid var(--border)",
  verticalAlign: "top",
};

const statusTone = (status: MyDutyAssignment["status"]): BadgeTone => {
  if (status === "accepted") return "success";
  if (status === "declined") return "danger";
  if (status === "replaced") return "warning";
  return "info";
};

const formatDate = (value: string | null) => {
  if (!value) return "—";
  const date = new Date(`${value.slice(0, 10)}T00:00:00`);
  return Number.isNaN(date.getTime())
    ? value
    : date.toLocaleDateString(undefined, { day: "2-digit", month: "short", year: "numeric" });
};

const MyDutiesPage = () => {
  const queryClient = useQueryClient();
  const [respondingDuty, setRespondingDuty] = useState<MyDutyAssignment | null>(null);
  const [response, setResponse] = useState<"accepted" | "declined">("accepted");
  const [remarks, setRemarks] = useState("");

  const dutiesQuery = useQuery({
    queryKey: ["examinations", "my-duties"],
    queryFn: () => myDutiesService.getMine(),
  });

  const duties = dutiesQuery.data?.duties ?? [];

  const responseMutation = useMutation({
    mutationFn: async () => {
      if (!respondingDuty) throw new Error("No duty assignment selected.");
      return myDutiesService.respond(respondingDuty.exam_id, respondingDuty.id, {
        response,
        remarks: remarks.trim() || undefined,
      });
    },
    onSuccess: async () => {
      toast.success(response === "accepted" ? "Duty accepted." : "Duty declined.");
      setRespondingDuty(null);
      setRemarks("");
      await queryClient.invalidateQueries({ queryKey: ["examinations", "my-duties"] });
      await queryClient.invalidateQueries({ queryKey: ["examinations", "duty-roster"] });
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error, "Unable to submit your response."));
    },
  });

  const openResponse = (duty: MyDutyAssignment, nextResponse: "accepted" | "declined") => {
    setRespondingDuty(duty);
    setResponse(nextResponse);
    setRemarks("");
  };

  if (dutiesQuery.isLoading) {
    return <div className="dashboard-state"><Spinner size={22} /><p>Loading your examination duties...</p></div>;
  }

  if (dutiesQuery.isError) {
    return (
      <div>
        <div className="institution-admin-panel" style={{ marginBottom: "var(--space-4)" }}>
          <div>
            <h1>My Examination Duties</h1>
            <p>Review and respond to your published examination duty assignments.</p>
          </div>
          <Button variant="secondary" onClick={() => void dutiesQuery.refetch()}>
            <RefreshCw size={16} /> Retry
          </Button>
        </div>
        <div role="alert" style={{ padding: 12, border: "1px solid var(--danger)", borderRadius: 8, color: "var(--danger)" }}>
          {getApiErrorMessage(dutiesQuery.error, "Unable to load your examination duties.")}
        </div>
      </div>
    );
  }

  return (
    <>
      <div>
        <div className="institution-admin-panel" style={{ marginBottom: "var(--space-4)" }}>
          <div>
            <h1>My Examination Duties</h1>
            <p>Review your current published duty assignments and submit your response.</p>
          </div>
          <Button variant="secondary" onClick={() => void dutiesQuery.refetch()} disabled={dutiesQuery.isFetching}>
            <RefreshCw size={16} /> Refresh
          </Button>
        </div>

        <div className="institution-admin-panel" style={{ display: "flex", gap: 28, flexWrap: "wrap", marginBottom: "var(--space-4)" }}>
          <div><span style={{ color: "var(--text-secondary)", fontSize: 12 }}>Total duties</span><div style={{ fontSize: 24, fontWeight: 700 }}>{duties.length}</div></div>
          <div><span style={{ color: "var(--text-secondary)", fontSize: 12 }}>Awaiting response</span><div style={{ fontSize: 24, fontWeight: 700 }}>{duties.filter((d) => d.status === "assigned").length}</div></div>
          <div><span style={{ color: "var(--text-secondary)", fontSize: 12 }}>Accepted</span><div style={{ fontSize: 24, fontWeight: 700 }}>{duties.filter((d) => d.status === "accepted").length}</div></div>
        </div>

        <section className="institution-admin-panel">
          <div style={{ marginBottom: 14 }}>
            <div>
              <h2>Current published assignments</h2>
              <p>Your duties are shown only when the latest roster version for an examination is published.</p>
            </div>
          </div>
          {duties.length === 0 ? (
            <div className="dashboard-state">
              <CalendarDays size={28} />
              <h3>No current examination duties</h3>
              <p>You do not have assignments in a currently published duty roster.</p>
            </div>
          ) : (
            <div style={{ overflowX: "auto" }}>
              <table style={tableStyle}>
                <thead>
                  <tr>
                    <th style={th}>Examination / Subject</th>
                    <th style={th}>Date &amp; time</th>
                    <th style={th}>Venue</th>
                    <th style={th}>Duty role</th>
                    <th style={th}>Roster</th>
                    <th style={th}>Status</th>
                    <th style={th}>Action</th>
                  </tr>
                </thead>
                <tbody>
                  {duties.map((duty) => (
                    <tr key={duty.id}>
                      <td style={td}>
                        <strong>{duty.exam_title}</strong>
                        <div style={{ color: "var(--text-secondary)", fontSize: 12 }}>
                          {duty.subject ? `${duty.subject.code} — ${duty.subject.name}` : "Subject details unavailable"}
                        </div>
                      </td>
                      <td style={td}>
                        <strong>{formatDate(duty.exam_date)}</strong>
                        <div style={{ color: "var(--text-secondary)", fontSize: 12 }}>
                          {duty.start_time ?? "—"}{duty.end_time ? ` – ${duty.end_time}` : ""}
                        </div>
                        {duty.session && <div style={{ color: "var(--text-secondary)", fontSize: 12 }}>{duty.session.label}</div>}
                      </td>
                      <td style={td}>
                        {duty.venue || duty.room?.name || "—"}
                        {duty.room?.code && <div style={{ color: "var(--text-secondary)", fontSize: 12 }}>{duty.room.code}</div>}
                      </td>
                      <td style={td}>{duty.duty_role?.name ?? "—"}</td>
                      <td style={td}>Version {duty.roster_version}</td>
                      <td style={td}>
                        <Badge tone={statusTone(duty.status)}>{duty.status.replace("_", " ")}</Badge>
                        {duty.responded_at && (
                          <div style={{ color: "var(--text-secondary)", fontSize: 12 }}>
                            Responded {new Date(duty.responded_at).toLocaleString()}
                          </div>
                        )}
                      </td>
                      <td style={td}>
                        {duty.can_respond ? (
                          <div style={{ display: "flex", gap: 6, flexWrap: "wrap" }}>
                            <Button size="sm" onClick={() => openResponse(duty, "accepted")}>
                              <CheckCircle size={14} /> Accept
                            </Button>
                            <Button size="sm" variant="secondary" onClick={() => openResponse(duty, "declined")}>
                              <XCircle size={14} /> Decline
                            </Button>
                          </div>
                        ) : (
                          <span style={{ color: "var(--text-secondary)", fontSize: 12 }}>No action required</span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      </div>

      <Modal
        open={respondingDuty !== null}
        onClose={() => {
          if (!responseMutation.isPending) setRespondingDuty(null);
        }}
        title={response === "accepted" ? "Accept examination duty" : "Decline examination duty"}
        footer={
          <div style={{ display: "flex", justifyContent: "flex-end", gap: 8 }}>
            <Button variant="secondary" onClick={() => setRespondingDuty(null)} disabled={responseMutation.isPending}>Cancel</Button>
            <Button onClick={() => responseMutation.mutate()} disabled={responseMutation.isPending}>
              {responseMutation.isPending ? "Submitting..." : response === "accepted" ? "Confirm acceptance" : "Confirm decline"}
            </Button>
          </div>
        }
      >
        <div>
          <p>
            {response === "accepted"
              ? "Confirm that you are available to perform this examination duty."
              : "Please provide a reason for declining. The examination office will be able to review your remarks."}
          </p>
          <FormField label="Remarks" htmlFor="duty-response-remarks">
            <Input
              id="duty-response-remarks"
              value={remarks}
              onChange={(event) => setRemarks(event.target.value)}
              placeholder={response === "declined" ? "Reason for declining (optional)" : "Additional remarks (optional)"}
              maxLength={500}
            />
          </FormField>
        </div>
      </Modal>
    </>
  );
};

export default MyDutiesPage;
