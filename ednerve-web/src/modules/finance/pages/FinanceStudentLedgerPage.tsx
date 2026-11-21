import { Wallet, Receipt } from "lucide-react";
import { useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { financeService } from "../api";
import "../../institution/institution.css";

import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin } from "../../../shared/lib/rbac";

import { studentService } from "../../students/api";

import {
  Badge,
  Button,
  Modal,
  FormField,
  Input,
  SearchInput,
} from "../../../shared/ui";
import { Search } from "lucide-react";

const FinanceStudentLedgerPage = () => {
  const { user } = useAuth();
  const qc = useQueryClient();
  const canManage = isInstitutionAdmin(user);

  const [searchInput, setSearchInput] = useState("");
  const [selectedId, setSelectedId] = useState<number | null>(null);

  const studentsQuery = useQuery({
    queryKey: ["finance", "students", searchInput],
    queryFn: () => studentService.getStudents({ q: searchInput || undefined, per_page: 50 }),
  });

  const student = studentsQuery.data?.data.find(
    (s) => s.id === selectedId
  );

  const ledgerQuery = useQuery({
    queryKey: ["finance", "ledger", selectedId],
    queryFn: () => financeService.getStudentLedger(selectedId!),
    enabled: selectedId !== null,
  });

  const ledger = ledgerQuery.data;

  // Payment form state
  const [payOpen, setPayOpen] = useState(false);
  const [paymentForm, setPaymentForm] = useState({
    amount: "",
    payment_method: "cash" as
      | "cash" | "cheque" | "demand_draft" | "upi" | "card" | "bank_transfer" | "other",
    reference: "",
    paid_at: new Date().toISOString().slice(0, 10),
    remarks: "",
  });
  const [payError, setPayError] = useState("");
  const [paying, setPaying] = useState(false);

  const recordPayment = async () => {
    if (!ledger?.summary) return;
    setPayError("");

    const amount = Number(paymentForm.amount);
    if (!Number.isFinite(amount) || amount <= 0) {
      setPayError("Amount must be a positive number.");
      return;
    }
    setPaying(true);
    try {
      await financeService.recordPayment(selectedId!, {
        amount,
        payment_method: paymentForm.payment_method,
        reference: paymentForm.reference.trim() || undefined,
        paid_at: paymentForm.paid_at ? `${paymentForm.paid_at}T00:00:00` : undefined,
        remarks: paymentForm.remarks.trim() || undefined,
      });
      await qc.invalidateQueries({ queryKey: ["finance", "ledger", selectedId] });
      toast.success("Payment recorded.");
      setPayOpen(false);
      setPaymentForm({
        amount: "",
        payment_method: "cash",
        reference: "",
        paid_at: new Date().toISOString().slice(0, 10),
        remarks: "",
      });
    } catch (error) {
      setPayError(
        (error as any)?.response?.data?.message ?? "Unable to record payment.",
      );
    } finally {
      setPaying(false);
    }
  };

  return (
    <>
      <div className="institution-directory-toolbar">
        <SearchInput
          icon={Search}
          placeholder="Search student by name, email or application number..."
          value={searchInput}
          onChange={(event) => setSearchInput(event.target.value)}
          aria-label="Search students for ledger"
        />
      </div>

      {/* Student picker */}
      {!student && (
        <div className="institution-directory-toolbar" style={{ flexWrap: "wrap", gap: 8 }}>
          {(studentsQuery.data?.data ?? []).slice(0, 30).map((s) => (
            <button
              key={s.id}
              type="button"
              onClick={() => setSelectedId(s.id)}
              style={{
                border: "1px solid var(--border)",
                borderRadius: "var(--radius-md)",
                padding: "8px 12px",
                background: "var(--surface)",
                color: "var(--text-primary)",
                cursor: "pointer",
                textAlign: "left",
              }}
            >
              <strong style={{ display: "block", fontSize: 13 }}>{s.user?.name}</strong>
              <span className="institution-mono" style={{ fontSize: 11 }}>
                {s.application_number ?? "—"} · {s.programme?.code}
              </span>
            </button>
          ))}
          {studentsQuery.isLoading && <p>Loading...</p>}
          {(studentsQuery.data?.data ?? []).length === 0 && (
            <p style={{ color: "var(--text-muted)" }}>No students found.</p>
          )}
        </div>
      )}

      {student && (
        <div className="institution-two-column" style={{ marginBottom: "var(--space-5)" }}>
          {/* Back + summary */}
          <div>
            <button
              type="button"
              className="btn btn-ghost"
              onClick={() => setSelectedId(null)}
              style={{ marginBottom: 12 }}
            >
              ← Back to search
            </button>

            <div className="institution-admin-panel">
              <h3>Ledger summary</h3>
              {ledgerQuery.isLoading && <p>Loading ledger...</p>}
              {ledger && (
                <div className="institution-admin-grid" style={{ gridTemplateColumns: "repeat(4, 1fr)" }}>
                  <div style={summaryCell}>
                    <span style={summaryValue}>₹{Number(ledger.summary.total_due).toLocaleString("en-IN")}</span>
                    <span style={summaryLabel}>Total due</span>
                  </div>
                  <div style={summaryCell}>
                    <span style={summaryValue}>₹{Number(ledger.summary.total_paid).toLocaleString("en-IN")}</span>
                    <span style={summaryLabel}>Paid</span>
                  </div>
                  <div style={{ ...summaryCell, background: "var(--accent-teal-bg)" }}>
                    <span style={{ ...summaryValue, color: "var(--text-accent)" }}>
                      ₹{Number(ledger.summary.balance).toLocaleString("en-IN")}
                    </span>
                    <span style={summaryLabel}>Balance</span>
                  </div>
                  <div style={summaryCell}>
                    <span style={summaryValue}>{ledger.dues.length}</span>
                    <span style={summaryLabel}>Open dues</span>
                  </div>
                </div>
              )}
            </div>

            {/* Add payment CTA */}
            {canManage && ledger && Number(ledger.summary.balance) > 0 && (
              <div style={{ marginTop: 12 }}>
                <button className="workspace-action-button" onClick={() => setPayOpen(true)}>
                  <Receipt size={16} /> Record Payment
                </button>
              </div>
            )}
          </div>

          {/* Right column: small student meta */}
          <div>
            <div className="institution-admin-panel">
              <h3>Student</h3>
              <strong>{student.user?.name}</strong>
              <div className="institution-mono" style={{ fontSize: 12, marginTop: 2 }}>
                {student.user?.email}
              </div>
              <div className="institution-mono" style={{ fontSize: 12 }}>
                Application: {student.application_number ?? "—"}
              </div>
              <div className="institution-mono" style={{ fontSize: 12 }}>
                Programme: {student.programme?.code} · {student.academic_session?.code ?? "—"}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Dues table */}
      {student && ledger && (
        <div className="institution-admin-panel" style={{ marginBottom: "var(--space-4)" }}>
          <h3>Outstanding dues</h3>
          {ledger.dues.length === 0 ? (
            <p style={{ color: "var(--text-muted)" }}>No outstanding dues — pay up!</p>
          ) : (
            <table className="something-mono" style={tableStyle}>
              <thead>
                <tr>
                  <th style={th}>Fee</th>
                  <th style={th}>Session</th>
                  <th style={th}>Due</th>
                  <th style={th}>Paid</th>
                  <th style={th}>Due date</th>
                  <th style={th}>Status</th>
                </tr>
              </thead>
              <tbody>
                {ledger.dues.map((d) => {
                  const session = d.academic_session?.code ?? "—";
                  return (
                    <tr key={d.id}>
                      <td style={td}>
                        <div className="institution-name-cell" style={{ gap: 8 }}>
                          <div className="institution-avatar" style={{ width: 28, height: 28 }}>
                            <Wallet size={14} />
                          </div>
                          <strong>{d.fee_head?.name ?? String(d.fee_head_id)}</strong>
                        </div>
                      </td>
                      <td style={td}>{session}</td>
                      <td style={td}>₹{Number(d.amount).toLocaleString("en-IN")}</td>
                      <td style={td}>₹{Number(d.paid_amount).toLocaleString("en-IN")}</td>
                      <td style={td}>{d.due_date?.slice(0, 10) ?? "—"}</td>
                      <td style={td}>
                        <Badge tone={d.status === "paid" ? "success" : d.status === "partial" ? "warning" : "info"}>
                          {d.status}
                        </Badge>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          )}
        </div>
      )}

      {/* Payments history */}
      {student && ledger && ledger.payments.length > 0 && (
        <div className="institution-admin-panel">
          <h3>Payments</h3>
          <table style={tableStyle}>
            <thead>
              <tr>
                <th style={th}>Receipt #</th>
                <th style={th}>Amount</th>
                <th style={th}>Method</th>
                <th style={th}>Date</th>
              </tr>
            </thead>
            <tbody>
              {ledger.payments.map((p) => (
                <tr key={p.id}>
                  <td style={td}><span className="institution-mono">{p.receipt_number}</span></td>
                  <td style={td}>₹{Number(p.amount).toLocaleString("en-IN")}</td>
                  <td style={td}>{p.payment_method}</td>
                  <td style={td}>{String(p.paid_at).slice(0, 10)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Record payment modal */}
      <Modal
        open={payOpen}
        title="Record Payment"
        onClose={() => setPayOpen(false)}
        footer={
          <>
            <Button variant="secondary" onClick={() => setPayOpen(false)}>
              Cancel
            </Button>
            <Button onClick={recordPayment} disabled={paying}>
              {paying ? "Recording..." : "Record Payment"}
            </Button>
          </>
        }
      >
        {payError && (
          <div className="institution-form-alert" role="alert">{payError}</div>
        )}
        <div className="institution-form-grid">
          <FormField label="Amount (₹)" htmlFor="pay-amount" required>
            <Input
              id="pay-amount"
              type="number"
              step="0.01"
              autoFocus
              value={paymentForm.amount}
              onChange={(e) =>
                setPaymentForm((c) => ({ ...c, amount: e.target.value }))
              }
              placeholder="5000"
            />
          </FormField>
          <FormField label="Method" htmlFor="pay-method">
            <select
              id="pay-method"
              className="filter-select"
              value={paymentForm.payment_method}
              onChange={(e) =>
                setPaymentForm((c) => ({
                  ...c,
                  payment_method: e.target.value as typeof paymentForm.payment_method,
                }))
              }
            >
              <option value="cash">Cash</option>
              <option value="cheque">Cheque</option>
              <option value="demand_draft">Demand Draft</option>
              <option value="upi">UPI</option>
              <option value="card">Card</option>
              <option value="bank_transfer">Bank transfer</option>
              <option value="other">Other</option>
            </select>
          </FormField>
        </div>

        <FormField label="Reference / UTR / Cheque No." htmlFor="pay-ref">
          <Input
            id="pay-ref"
            value={paymentForm.reference}
            onChange={(e) =>
              setPaymentForm((c) => ({ ...c, reference: e.target.value }))
            }
            placeholder="UTR1234567890"
          />
        </FormField>

        <FormField label="Remarks" htmlFor="pay-remarks">
          <Input
            id="pay-remarks"
            value={paymentForm.remarks}
            onChange={(e) =>
              setPaymentForm((c) => ({ ...c, remarks: e.target.value }))
            }
            placeholder="Optional"
          />
        </FormField>
      </Modal>
    </>
  );
};

const summaryCell: React.CSSProperties = {
  display: "flex",
  flexDirection: "column",
  alignItems: "flex-start",
  justifyContent: "center",
  background: "var(--surface-secondary)",
  borderRadius: "var(--radius-md)",
  padding: "12px",
  gap: 4,
};
const summaryValue: React.CSSProperties = {
  fontSize: "20px",
  fontWeight: 700,
  color: "var(--text-primary)",
};
const summaryLabel: React.CSSProperties = {
  color: "var(--text-secondary)",
  fontSize: "var(--font-size-xs)",
  textTransform: "uppercase",
  letterSpacing: "0.05em",
};
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

export default FinanceStudentLedgerPage;
