import { useState } from "react";
import { Plus, Layers } from "lucide-react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { financeService } from "../api";
import "../../institution/institution.css";

import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin } from "../../../shared/lib/rbac";
import { academicService } from "../../academic/api";

import {
  Badge,
  Button,
  FormField,
  Input,
  Modal,
  Spinner,
} from "../../../shared/ui";

const emptyForm = {
  programme_id: "" as string | number,
  academic_session_id: "" as string | number,
  fee_head_id: "" as string | number,
  amount: "",
  frequency: "annual" as "annual" | "semester" | "one_time",
  notes: "",
};

const FinanceStructuresPage = () => {
  const { user } = useAuth();
  const qc = useQueryClient();
  const canManage = isInstitutionAdmin(user);

  const headsQuery = useQuery({
    queryKey: ["finance", "fee-heads"],
    queryFn: () => financeService.getFeeHeads(),
  });

  const programmesQuery = useQuery({
    queryKey: ["academic", "programmes"],
    queryFn: () => academicService.getProgrammes(),
  });

  const sessionsQuery = useQuery({
    queryKey: ["academic", "sessions"],
    queryFn: () => academicService.getSessions(),
  });

  const programmeFilter = "all"; // local scope — keep this page simple for v1

  const structuresQuery = useQuery({
    queryKey: ["finance", "structures", programmeFilter],
    queryFn: () => financeService.getStructures(),
  });

  const structures = structuresQuery.data?.structures ?? [];

  const [modalOpen, setModalOpen] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [error, setError] = useState("");

  const saveStructure = async () => {
    setError("");
    if (form.fee_head_id === "") { setError("Select a fee head."); return; }
    if (!Number.isFinite(Number(form.amount)) || Number(form.amount) < 0) {
      setError("Amount must be a non-negative number.");
      return;
    }
    try {
      await financeService.createFeeStructure({
        programme_id: form.programme_id === "" ? null : Number(form.programme_id),
        academic_session_id: form.academic_session_id === "" ? null : Number(form.academic_session_id),
        fee_head_id: Number(form.fee_head_id),
        amount: Number(form.amount),
        frequency: form.frequency,
        notes: form.notes.trim() || undefined,
      });
      await qc.invalidateQueries({ queryKey: ["finance"] });
      toast.success("Fee structure created.");
      setModalOpen(false);
      setForm(emptyForm);
    } catch (e) {
      setError((e as any)?.response?.data?.message ?? "Unable to save fee structure.");
    }
  };

  if (structuresQuery.isLoading) {
    return (
      <div className="dashboard-state">
        <Spinner size={22} />
        <p>Loading fee structures...</p>
      </div>
    );
  }

  const heads = headsQuery.data?.fee_heads ?? [];

  return (
    <>
      <div className="institution-admin-panel" style={{ marginBottom: "var(--space-4)" }}>
        <h3>
          <Layers size={17} style={{ verticalAlign: "-3px" }} /> Fee heads
        </h3>
        <div style={{ display: "flex", flexWrap: "wrap", gap: 8 }}>
          {heads.map((h) => (
            <Badge key={h.id} tone="info">
              {h.code} — {h.name}
            </Badge>
          ))}
        </div>
      </div>

      <div className="institution-directory-toolbar">
        {canManage && (
          <Button onClick={() => { setForm(emptyForm); setError(""); setModalOpen(true); }}>
            <Plus size={16} />
            Add Fee Structure
          </Button>
        )}
      </div>

      <div className="institution-admin-panel">
        <h3>Configured fee structures</h3>
        {structures.length === 0 ? (
          <p style={{ color: "var(--text-muted)" }}>
            No fee structures defined yet. Add one to begin generating dues for enrolled students.
          </p>
        ) : (
          <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "var(--font-size-sm)" }}>
            <thead>
              <tr>
                <th style={th}>Fee Head</th>
                <th style={th}>Programme</th>
                <th style={th}>Session</th>
                <th style={th}>Amount</th>
                <th style={th}>Frequency</th>
              </tr>
            </thead>
            <tbody>
              {structures.map((s) => (
                <tr key={s.id}>
                  <td style={td}><strong>{s.fee_head?.name ?? s.fee_head_id}</strong></td>
                  <td style={td}>{s.programme ? `${s.programme.code} (${s.programme.name})` : "All programmes"}</td>
                  <td style={td}>{s.academic_session?.code ?? "All sessions"}</td>
                  <td style={td}>₹{Number(s.amount).toLocaleString("en-IN")}</td>
                  <td style={td}><Badge tone="info">{s.frequency}</Badge></td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      <Modal
        open={modalOpen}
        title="Add Fee Structure"
        onClose={() => setModalOpen(false)}
        footer={
          <>
            <Button variant="secondary" onClick={() => setModalOpen(false)}>Cancel</Button>
            <Button onClick={saveStructure}>Save Structure</Button>
          </>
        }
      >
        {error && <div className="institution-form-alert" role="alert">{error}</div>}

        <FormField label="Programme" htmlFor="fs-programme">
          <select id="fs-programme" className="filter-select"
            value={form.programme_id}
            onChange={(e) => setForm((c) => ({ ...c, programme_id: e.target.value }))}
          >
            <option value="">All programmes</option>
            {(programmesQuery.data?.programmes ?? []).map((p) => (
              <option key={p.id} value={p.id}>{p.code} — {p.name}</option>
            ))}
          </select>
        </FormField>

        <FormField label="Session" htmlFor="fs-session">
          <select id="fs-session" className="filter-select"
            value={form.academic_session_id}
            onChange={(e) => setForm((c) => ({ ...c, academic_session_id: e.target.value }))}
          >
            <option value="">All sessions</option>
            {(sessionsQuery.data?.sessions ?? []).map((s) => (
              <option key={s.id} value={s.id}>{s.code} — {s.name}</option>
            ))}
          </select>
        </FormField>

        <FormField label="Fee Head" htmlFor="fs-head" required>
          <select id="fs-head" className="filter-select"
            value={form.fee_head_id}
            onChange={(e) => setForm((c) => ({ ...c, fee_head_id: e.target.value }))}
          >
            <option value="">Select fee head...</option>
            {heads.map((h) => (<option key={h.id} value={h.id}>{h.code} — {h.name}</option>))}
          </select>
        </FormField>

        <div className="institution-form-grid">
          <FormField label="Amount (₹)" htmlFor="fs-amount" required>
            <Input id="fs-amount" type="number" step="0.01"
              value={form.amount}
              onChange={(e) => setForm((c) => ({ ...c, amount: e.target.value }))}
              placeholder="85000"
            />
          </FormField>
          <FormField label="Frequency" htmlFor="fs-freq">
            <select id="fs-freq" className="filter-select"
              value={form.frequency}
              onChange={(e) => setForm((c) => ({ ...c, frequency: e.target.value as any }))}
            >
              <option value="annual">Annual</option>
              <option value="semester">Per semester</option>
              <option value="one_time">One-time</option>
            </select>
          </FormField>
        </div>
      </Modal>
    </>
  );
};

const th: React.CSSProperties = { textAlign: "left", padding: "8px 10px", borderBottom: "1px solid var(--border)", color: "var(--text-muted)", fontSize: "var(--font-size-xs)", textTransform: "uppercase", letterSpacing: "0.05em" };
const td: React.CSSProperties = { padding: "10px", borderBottom: "1px solid var(--border)", verticalAlign: "middle" };

export default FinanceStructuresPage;
