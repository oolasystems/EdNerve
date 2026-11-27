import "../examinations.css";
import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { CalendarDays, CheckCircle, RefreshCw, XCircle } from "lucide-react";
import { toast } from "sonner";

import { myDutiesService } from "../api";
import type { MyDutyAssignment } from "../api";
import { getApiErrorMessage } from "../../../shared/lib/api-error";
import type { BadgeTone } from "../../../shared/ui/Badge";
import { Badge, Button, FormField, Input, Modal, Spinner } from "../../../shared/ui";



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
        <div className="institution-admin-panel my-duties-header">
          <div>
            <h1>My Examination Duties</h1>
            <p>Review and respond to your published examination duty assignments.</p>
          </div>
          <Button variant="secondary" onClick={() => void dutiesQuery.refetch()}>
            <RefreshCw size={16} /> Retry
          </Button>
        </div>
        <div role="alert" className="my-duties-error">
          {getApiErrorMessage(dutiesQuery.error, "Unable to load your examination duties.")}
        </div>
      </div>
    );
  }

  return (
    <>
      <div>
        <div className="institution-admin-panel my-duties-header">
          <div>
            <h1>My Examination Duties</h1>
            <p>Review your current published duty assignments and submit your response.</p>
          </div>
          <Button variant="secondary" onClick={() => void dutiesQuery.refetch()} disabled={dutiesQuery.isFetching}>
            <RefreshCw size={16} /> Refresh
          </Button>
        </div>

        <div className="institution-admin-panel my-duties-summary">
          <div className="my-duties-stat"><span className="my-duties-stat-label">Total duties</span><div className="my-duties-stat-value">{duties.length}</div></div>
          <div className="my-duties-stat"><span className="my-duties-stat-label">Awaiting response</span><div className="my-duties-stat-value">{duties.filter((d) => d.status === "assigned").length}</div></div>
          <div className="my-duties-stat"><span className="my-duties-stat-label">Accepted</span><div className="my-duties-stat-value">{duties.filter((d) => d.status === "accepted").length}</div></div>
        </div>

        <section className="institution-admin-panel">
          <div className="my-duties-section-heading">
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
            <div className="my-duties-table-scroll">
              <table className="exam-data-table my-duties-table">
                <thead>
                  <tr>
                    <th className="exam-data-table-heading">Examination / Subject</th>
                    <th className="exam-data-table-heading">Date &amp; time</th>
                    <th className="exam-data-table-heading">Venue</th>
                    <th className="exam-data-table-heading">Duty role</th>
                    <th className="exam-data-table-heading">Roster</th>
                    <th className="exam-data-table-heading">Status</th>
                    <th className="exam-data-table-heading">Action</th>
                  </tr>
                </thead>
                <tbody>
                  {duties.map((duty) => (
                    <tr key={duty.id}>
                      <td className="exam-data-table-cell my-duties-cell">
                        <strong>{duty.exam_title}</strong>
                        <div className="my-duties-secondary-text">
                          {duty.subject ? `${duty.subject.code} — ${duty.subject.name}` : "Subject details unavailable"}
                        </div>
                      </td>
                      <td className="exam-data-table-cell my-duties-cell">
                        <strong>{formatDate(duty.exam_date)}</strong>
                        <div className="my-duties-secondary-text">
                          {duty.start_time ?? "—"}{duty.end_time ? ` – ${duty.end_time}` : ""}
                        </div>
                        {duty.session && <div className="my-duties-secondary-text">{duty.session.label}</div>}
                      </td>
                      <td className="exam-data-table-cell my-duties-cell">
                        {duty.venue || duty.room?.name || "—"}
                        {duty.room?.code && <div className="my-duties-secondary-text">{duty.room.code}</div>}
                      </td>
                      <td className="exam-data-table-cell my-duties-cell">{duty.duty_role?.name ?? "—"}</td>
                      <td className="exam-data-table-cell my-duties-cell">Version {duty.roster_version}</td>
                      <td className="exam-data-table-cell my-duties-cell">
                        <Badge tone={statusTone(duty.status)}>{duty.status.replace("_", " ")}</Badge>
                        {duty.responded_at && (
                          <div className="my-duties-secondary-text">
                            Responded {new Date(duty.responded_at).toLocaleString()}
                          </div>
                        )}
                      </td>
                      <td className="exam-data-table-cell my-duties-cell">
                        {duty.can_respond ? (
                          <div className="my-duties-response-actions">
                            <Button size="sm" onClick={() => openResponse(duty, "accepted")}>
                              <CheckCircle size={14} /> Accept
                            </Button>
                            <Button size="sm" variant="secondary" onClick={() => openResponse(duty, "declined")}>
                              <XCircle size={14} /> Decline
                            </Button>
                          </div>
                        ) : (
                          <span className="my-duties-secondary-text">No action required</span>
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
          <div className="my-duties-modal-actions">
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
