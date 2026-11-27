import "../examinations.css";
import { useState } from "react";
import { ListChecks, Plus, Pencil, Building2, Clock3, DoorOpen, Tag } from "lucide-react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { examSetupService } from "../setup-api";
import type { ExamCentre, ExamKind, ExamRoom, ExamSession } from "../setup-api";

import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin } from "../../../shared/lib/rbac";
import { getApiErrorMessage } from "../../../shared/lib/api-error";

import {
  Badge,
  Button,
  FormField,
  Input,
  Modal,
  Spinner,
} from "../../../shared/ui";

const TABS = ["Sessions", "Centres & Rooms", "Exam Kinds"] as const;
type Tab = (typeof TABS)[number];

const emptySession = { code: "", label: "", start_time: "09:30", end_time: "12:30", sort_order: 1 };
const emptyCentre = { code: "", name: "", address: "", capacity: 500 };
const emptyRoom = { code: "", name: "", room_type: "theory" as ExamRoom["room_type"], capacity: 30 };
const emptyKind = { code: "", label: "", max_attempts: "" as string | number };


const ExamInfrastructurePage = () => {
  const { user } = useAuth();
  const qc = useQueryClient();
  const canManage = isInstitutionAdmin(user);
  const [tab, setTab] = useState<Tab>("Sessions");

  /* --- sessions --- */
  const sessionsQuery = useQuery({ queryKey: ["exam-infra", "sessions"], queryFn: () => examSetupService.listSessions() });
  const [sessionModal, setSessionModal] = useState<"new" | ExamSession | null>(null);
  const [sessionForm, setSessionForm] = useState(emptySession);

  /* --- centres & rooms --- */
  const centresQuery = useQuery({ queryKey: ["exam-infra", "centres"], queryFn: () => examSetupService.listCentres() });
  const [centreModal, setCentreModal] = useState<"new" | ExamCentre | null>(null);
  const [centreForm, setCentreForm] = useState(emptyCentre);
  const [selectedCentreId, setSelectedCentreId] = useState<number | null>(null);
  const [roomModal, setRoomModal] = useState<"new" | null>(null);
  const [roomForm, setRoomForm] = useState(emptyRoom);

  /* --- exam kinds --- */
  const kindsQuery = useQuery({ queryKey: ["exam-infra", "kinds"], queryFn: () => examSetupService.listKinds() });
  const [kindModal, setKindModal] = useState<"new" | ExamKind | null>(null);
  const [kindForm, setKindForm] = useState(emptyKind);

  const centres = centresQuery.data ?? [];
  const rooms = centres.find((c) => c.id === selectedCentreId)?.rooms ?? [];

  const saveSession = async () => {
    if (!sessionForm.code.trim() || !sessionForm.label.trim()) return toast.error("Code and label are required.");
    try {
      if (sessionModal === "new") await examSetupService.createSession({ ...sessionForm, sort_order: Number(sessionForm.sort_order) });
      else if (sessionModal) await examSetupService.updateSession(sessionModal.id, sessionForm);
      await qc.invalidateQueries({ queryKey: ["exam-infra"] });
      setSessionModal(null);
      toast.success("Session saved.");
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to save session."));
    }
  };

  const saveCentre = async () => {
    if (!centreForm.code.trim() || !centreForm.name.trim()) return toast.error("Code and name are required.");
    try {
      if (centreModal === "new") await examSetupService.createCentre(centreForm);
      else if (centreModal) await examSetupService.updateCentre(centreModal.id, centreForm);
      await qc.invalidateQueries({ queryKey: ["exam-infra"] });
      setCentreModal(null);
      toast.success("Centre saved.");
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to save centre."));
    }
  };

  const saveRoom = async () => {
    if (!selectedCentreId) return toast.error("Select a centre first.");
    if (!roomForm.code.trim() || !roomForm.name.trim()) return toast.error("Room code and name are required.");
    try {
      await examSetupService.createRoom({ ...roomForm, centre_id: selectedCentreId, capacity: Number(roomForm.capacity) });
      await qc.invalidateQueries({ queryKey: ["exam-infra"] });
      setRoomModal(null);
      toast.success("Room added.");
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to add room."));
    }
  };

  const saveKind = async () => {
    if (!kindForm.code.trim() || !kindForm.label.trim()) return toast.error("Code and label are required.");
    try {
      if (kindModal === "new") await examSetupService.createKind({ code: kindForm.code, label: kindForm.label, max_attempts: kindForm.max_attempts === "" ? null : Number(kindForm.max_attempts) });
      else if (kindModal) await examSetupService.updateKind(kindModal.id, { label: kindForm.label });
      await qc.invalidateQueries({ queryKey: ["exam-infra"] });
      setKindModal(null);
      toast.success("Kind saved.");
    } catch (e) {
      toast.error(getApiErrorMessage(e, "Unable to save kind."));
    }
  };

  return (
    <>
      <div className="institution-directory-toolbar exam-infra-tabs">
        {TABS.map((t) => (
          <button key={t} type="button" className={tab === t ? "filter-chip filter-chip-active" : "filter-chip"} onClick={() => setTab(t)}>
            {t === "Sessions" && <Clock3 size={13} aria-hidden="true" className="exam-infra-tab-icon" />}
            {t === "Centres & Rooms" && <Building2 size={13} aria-hidden="true" className="exam-infra-tab-icon" />}
            {t === "Exam Kinds" && <Tag size={13} aria-hidden="true" className="exam-infra-tab-icon" />}
            {t}
          </button>
        ))}
      </div>

      {tab === "Sessions" && (
        <div className="institution-admin-panel">
          <h3><Clock3 size={16} aria-hidden="true" className="exam-heading-icon" /> Exam sessions (day slots)</h3>
          {canManage && <Button onClick={() => { setSessionForm(emptySession); setSessionModal("new"); }}><Plus size={15} /> New session</Button>}
          {sessionsQuery.isLoading ? <Spinner size={22} /> : (
            <table className="exam-data-table exam-infra-table">
              <thead><tr><th className="exam-data-table-heading">Code</th><th className="exam-data-table-heading">Label</th><th className="exam-data-table-heading">Start</th><th className="exam-data-table-heading">End</th><th className="exam-data-table-heading">Order</th><th className="exam-data-table-heading">Status</th><th className="exam-data-table-heading"></th></tr></thead>
              <tbody>
                {(sessionsQuery.data ?? []).map((s) => (
                  <tr key={s.id}>
                    <td className="exam-data-table-cell"><span className="institution-mono">{s.code}</span></td>
                    <td className="exam-data-table-cell">{s.label}</td>
                    <td className="exam-data-table-cell">{s.start_time}</td>
                    <td className="exam-data-table-cell">{s.end_time}</td>
                    <td className="exam-data-table-cell">{s.sort_order}</td>
                    <td className="exam-data-table-cell"><Badge tone={s.active ? "success" : "warning"}>{s.active ? "active" : "inactive"}</Badge></td>
                    <td className="exam-data-table-cell">
                      {canManage && <button type="button" className="timetable-icon-btn" onClick={() => { setSessionForm({ code: s.code, label: s.label, start_time: s.start_time, end_time: s.end_time, sort_order: s.sort_order }); setSessionModal(s); }} aria-label="Edit"><Pencil size={15} /></button>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      )}

      {tab === "Centres & Rooms" && (
        <div className="institution-admin-panel">
          <h3><Building2 size={16} aria-hidden="true" className="exam-heading-icon" /> Examination centres</h3>
          {canManage && <Button onClick={() => { setCentreForm(emptyCentre); setCentreModal("new"); }}><Plus size={15} /> New centre</Button>}
          {centresQuery.isLoading ? <Spinner size={22} /> : (
            <table className="exam-data-table exam-infra-table">
              <thead><tr><th className="exam-data-table-heading">Code</th><th className="exam-data-table-heading">Name</th><th className="exam-data-table-heading">Capacity</th><th className="exam-data-table-heading">Rooms</th><th className="exam-data-table-heading">Status</th><th className="exam-data-table-heading"></th></tr></thead>
              <tbody>
                {centres.map((c) => (
                  <tr key={c.id} className={selectedCentreId === c.id ? "row-selected" : ""}>
                    <td className="exam-data-table-cell"><span className="institution-mono">{c.code}</span></td>
                    <td className="exam-data-table-cell">{c.name}</td>
                    <td className="exam-data-table-cell">{c.capacity}</td>
                    <td className="exam-data-table-cell">{c.rooms?.length ?? 0}</td>
                    <td className="exam-data-table-cell"><Badge tone={c.active ? "success" : "warning"}>{c.active ? "active" : "inactive"}</Badge></td>
                    <td className="exam-data-table-cell"><button type="button" className="institution-link" onClick={() => setSelectedCentreId(c.id)}><DoorOpen size={14} aria-hidden="true" className="exam-infra-inline-icon" /> Rooms</button></td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}

          {selectedCentreId && (
            <div className="exam-infra-rooms-section">
              <div className="institution-admin-panel-subtitle">
                Rooms at {centres.find((c) => c.id === selectedCentreId)?.name}
                {canManage && <button type="button" className="institution-link exam-infra-add-room" onClick={() => { setRoomForm(emptyRoom); setRoomModal("new"); }}><Plus size={12} aria-hidden="true" className="exam-infra-inline-icon" /> Add room</button>}
              </div>
              <table className="exam-data-table exam-infra-table">
                <thead><tr><th className="exam-data-table-heading">Code</th><th className="exam-data-table-heading">Name</th><th className="exam-data-table-heading">Type</th><th className="exam-data-table-heading">Capacity</th></tr></thead>
                <tbody>
                  {rooms.map((r) => (
                    <tr key={r.id}><td className="exam-data-table-cell"><span className="institution-mono">{r.code}</span></td><td className="exam-data-table-cell">{r.name}</td><td className="exam-data-table-cell">{r.room_type}</td><td className="exam-data-table-cell">{r.capacity}</td></tr>
                  ))}
                  {rooms.length === 0 && <tr><td className="exam-data-table-cell exam-data-table-empty" colSpan={4}>No rooms yet.</td></tr>}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {tab === "Exam Kinds" && (
        <div className="institution-admin-panel">
          <h3><Tag size={16} aria-hidden="true" className="exam-heading-icon" /> Exam kinds</h3>
          {canManage && <Button onClick={() => { setKindForm(emptyKind); setKindModal("new"); }}><Plus size={15} /> New kind</Button>}
          {kindsQuery.isLoading ? <Spinner size={22} /> : (
            <table className="exam-data-table exam-infra-table">
              <thead><tr><th className="exam-data-table-heading">Code</th><th className="exam-data-table-heading">Label</th><th className="exam-data-table-heading">Max attempts</th><th className="exam-data-table-heading">Status</th><th className="exam-data-table-heading"></th></tr></thead>
              <tbody>
                {(kindsQuery.data ?? []).map((k) => (
                  <tr key={k.id}>
                    <td className="exam-data-table-cell"><span className="institution-mono">{k.code}</span></td>
                    <td className="exam-data-table-cell">{k.label}</td>
                    <td className="exam-data-table-cell">{k.max_attempts ?? "unlimited"}</td>
                    <td className="exam-data-table-cell"><Badge tone={k.active ? "success" : "warning"}>{k.active ? "active" : "inactive"}</Badge></td>
                    <td className="exam-data-table-cell">
                      {canManage && <button type="button" className="timetable-icon-btn" onClick={() => { setKindForm({ code: k.code, label: k.label, max_attempts: k.max_attempts ?? "" }); setKindModal(k); }} aria-label="Edit"><Pencil size={15} /></button>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      )}

      {/* Session modal */}
      <Modal open={sessionModal !== null} title={sessionModal === "new" ? "New exam session" : `Edit session ${sessionModal?.code}`} onClose={() => setSessionModal(null)}
        footer={<><Button variant="secondary" onClick={() => setSessionModal(null)}>Cancel</Button><Button onClick={saveSession}><ListChecks size={15} /> Save</Button></>}>
        <FormField label="Code" htmlFor="sess-code" required><Input id="sess-code" value={sessionForm.code} onChange={(e) => setSessionForm({ ...sessionForm, code: e.target.value.toUpperCase() })} placeholder="FN" maxLength={20} disabled={sessionModal !== "new"} /></FormField>
        <FormField label="Label" htmlFor="sess-label" required><Input id="sess-label" value={sessionForm.label} onChange={(e) => setSessionForm({ ...sessionForm, label: e.target.value })} placeholder="Forenoon" /></FormField>
        <div className="exam-infra-session-fields">
          <FormField label="Start" htmlFor="sess-start" required><Input id="sess-start" type="time" value={sessionForm.start_time} onChange={(e) => setSessionForm({ ...sessionForm, start_time: e.target.value })} /></FormField>
          <FormField label="End" htmlFor="sess-end" required><Input id="sess-end" type="time" value={sessionForm.end_time} onChange={(e) => setSessionForm({ ...sessionForm, end_time: e.target.value })} /></FormField>
          <FormField label="Sort order" htmlFor="sess-order"><Input id="sess-order" type="number" min={1} max={10} value={sessionForm.sort_order} onChange={(e) => setSessionForm({ ...sessionForm, sort_order: Number(e.target.value) })} /></FormField>
        </div>
      </Modal>

      {/* Centre modal */}
      <Modal open={centreModal !== null} title={centreModal === "new" ? "New exam centre" : `Edit centre ${centreModal?.code}`} onClose={() => setCentreModal(null)}
        footer={<><Button variant="secondary" onClick={() => setCentreModal(null)}>Cancel</Button><Button onClick={saveCentre}><ListChecks size={15} /> Save</Button></>}>
        <FormField label="Code" htmlFor="centre-code" required><Input id="centre-code" value={centreForm.code} onChange={(e) => setCentreForm({ ...centreForm, code: e.target.value.toUpperCase() })} placeholder="MAIN" maxLength={20} disabled={centreModal !== "new"} /></FormField>
        <FormField label="Name" htmlFor="centre-name" required><Input id="centre-name" value={centreForm.name} onChange={(e) => setCentreForm({ ...centreForm, name: e.target.value })} placeholder="Main Campus" /></FormField>
        <FormField label="Address" htmlFor="centre-addr"><Input id="centre-addr" value={centreForm.address} onChange={(e) => setCentreForm({ ...centreForm, address: e.target.value })} placeholder="Civil Lines, Aligarh" /></FormField>
        <FormField label="Total capacity" htmlFor="centre-cap"><Input id="centre-cap" type="number" min={0} value={centreForm.capacity} onChange={(e) => setCentreForm({ ...centreForm, capacity: Number(e.target.value) })} /></FormField>
      </Modal>

      {/* Room modal */}
      <Modal open={roomModal !== null} title="Add room" onClose={() => setRoomModal(null)}
        footer={<><Button variant="secondary" onClick={() => setRoomModal(null)}>Cancel</Button><Button onClick={saveRoom}><ListChecks size={15} /> Add</Button></>}>
        <FormField label="Code" htmlFor="room-code" required><Input id="room-code" value={roomForm.code} onChange={(e) => setRoomForm({ ...roomForm, code: e.target.value.toUpperCase() })} placeholder="B12" maxLength={20} /></FormField>
        <FormField label="Name" htmlFor="room-name" required><Input id="room-name" value={roomForm.name} onChange={(e) => setRoomForm({ ...roomForm, name: e.target.value })} placeholder="Room B12" /></FormField>
        <FormField label="Type" htmlFor="room-type">
          <select id="room-type" className="filter-select" value={roomForm.room_type} onChange={(e) => setRoomForm({ ...roomForm, room_type: e.target.value as ExamRoom["room_type"] })}>
            <option value="theory">Theory</option>
            <option value="lab">Lab</option>
            <option value="viva">Viva</option>
            <option value="server_room">Server room</option>
          </select>
        </FormField>
        <FormField label="Capacity" htmlFor="room-cap" required><Input id="room-cap" type="number" min={1} value={roomForm.capacity} onChange={(e) => setRoomForm({ ...roomForm, capacity: Number(e.target.value) })} /></FormField>
      </Modal>

      {/* Kind modal */}
      <Modal open={kindModal !== null} title={kindModal === "new" ? "New exam kind" : `Edit kind ${kindModal?.code}`} onClose={() => setKindModal(null)}
        footer={<><Button variant="secondary" onClick={() => setKindModal(null)}>Cancel</Button><Button onClick={saveKind}><ListChecks size={15} /> Save</Button></>}>
        <FormField label="Code" htmlFor="kind-code" required><Input id="kind-code" value={kindForm.code} onChange={(e) => setKindForm({ ...kindForm, code: e.target.value.toLowerCase() })} placeholder="class_test" maxLength={30} disabled={kindModal !== "new"} /></FormField>
        <FormField label="Label" htmlFor="kind-label" required><Input id="kind-label" value={kindForm.label} onChange={(e) => setKindForm({ ...kindForm, label: e.target.value })} placeholder="Class Test / Unit Test" /></FormField>
        <FormField label="Max attempts" htmlFor="kind-max"><Input id="kind-max" type="number" min={1} value={kindForm.max_attempts} onChange={(e) => setKindForm({ ...kindForm, max_attempts: e.target.value })} placeholder="unlimited" /></FormField>
      </Modal>
    </>
  );
};

export default ExamInfrastructurePage;
