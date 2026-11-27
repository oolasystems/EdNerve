import { Wallet, Receipt } from "lucide-react";
import { useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { financeService } from "../api";
import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin } from "../../../shared/lib/rbac";
import { getApiErrorMessage } from "../../../shared/lib/api-error";

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

import "../finance.css";

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
        getApiErrorMessage(error, "Unable to record payment."),
      );
    } finally {
      setPaying(false);
    }
  };

  return (
    <div className="finance-page">
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
        <div className="institution-directory-toolbar finance-student-picker">
          {(studentsQuery.data?.data ?? []).slice(0, 30).map((s) => (
            <button
              key={s.id}
              type="button"
              onClick={() => setSelectedId(s.id)}
              className="finance-student-option"
            >
              <strong className="finance-student-option-name">{s.user?.name}</strong>
              <span className="institution-mono finance-student-option-meta">
                {s.application_number ?? "—"} · {s.programme?.code}
              </span>
            </button>
          ))}
          {studentsQuery.isLoading && <p>Loading...</p>}
          {(studentsQuery.data?.data ?? []).length === 0 && (
            <p className="finance-muted">No students found.</p>
          )}
        </div>
      )}

      {student && (
        <div className="institution-two-column finance-ledger-overview">
          {/* Back + summary */}
          <div>
            <button
              type="button"
              className="btn btn-ghost finance-back-button"
              onClick={() => setSelectedId(null)}
            >
              ← Back to search
            </button>

            <div className="institution-admin-panel">
              <h3>Ledger summary</h3>
              {ledgerQuery.isLoading && <p>Loading ledger...</p>}
              {ledger && (
                <div className="institution-admin-grid finance-summary-grid">
                  <div className="finance-summary-cell">
                    <span className="finance-summary-value">₹{Number(ledger.summary.total_due).toLocaleString("en-IN")}</span>
                    <span className="finance-summary-label">Total due</span>
                  </div>
                  <div className="finance-summary-cell">
                    <span className="finance-summary-value">₹{Number(ledger.summary.total_paid).toLocaleString("en-IN")}</span>
                    <span className="finance-summary-label">Paid</span>
                  </div>
                  <div className="finance-summary-cell finance-summary-cell-balance">
                    <span className="finance-summary-value finance-summary-value-balance">
                      ₹{Number(ledger.summary.balance).toLocaleString("en-IN")}
                    </span>
                    <span className="finance-summary-label">Balance</span>
                  </div>
                  <div className="finance-summary-cell">
                    <span className="finance-summary-value">{ledger.dues.length}</span>
                    <span className="finance-summary-label">Open dues</span>
                  </div>
                </div>
              )}
            </div>

            {/* Add payment CTA */}
            {canManage && ledger && Number(ledger.summary.balance) > 0 && (
              <div className="finance-payment-action">
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
              <div className="institution-mono finance-student-detail">
                {student.user?.email}
              </div>
              <div className="institution-mono finance-student-detail">
                Application: {student.application_number ?? "—"}
              </div>
              <div className="institution-mono finance-student-detail">
                Programme: {student.programme?.code} · {student.academic_session?.code ?? "—"}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Dues table */}
      {student && ledger && (
        <div className="institution-admin-panel finance-panel-spaced">
          <h3>Outstanding dues</h3>
          {ledger.dues.length === 0 ? (
            <p className="finance-muted">No outstanding dues — pay up!</p>
          ) : (
            <div className="finance-table-wrap"><table className="something-mono finance-table">
              <thead>
                <tr>
                  <th className="finance-table-heading">Fee</th>
                  <th className="finance-table-heading">Session</th>
                  <th className="finance-table-heading finance-amount-heading">Due</th>
                  <th className="finance-table-heading finance-amount-heading">Paid</th>
                  <th className="finance-table-heading">Due date</th>
                  <th className="finance-table-heading">Status</th>
                </tr>
              </thead>
              <tbody>
                {ledger.dues.map((d) => {
                  const session = d.academic_session?.code ?? "—";
                  return (
                    <tr key={d.id}>
                      <td className="finance-table-cell">
                        <div className="institution-name-cell finance-fee-name">
                          <div className="institution-avatar finance-fee-avatar">
                            <Wallet size={14} />
                          </div>
                          <strong>{d.fee_head?.name ?? String(d.fee_head_id)}</strong>
                        </div>
                      </td>
                      <td className="finance-table-cell">{session}</td>
                      <td className="finance-table-cell finance-amount-cell">₹{Number(d.amount).toLocaleString("en-IN")}</td>
                      <td className="finance-table-cell finance-amount-cell">₹{Number(d.paid_amount).toLocaleString("en-IN")}</td>
                      <td className="finance-table-cell">{d.due_date?.slice(0, 10) ?? "—"}</td>
                      <td className="finance-table-cell">
                        <Badge tone={d.status === "paid" ? "success" : d.status === "partial" ? "warning" : "info"}>
                          {d.status}
                        </Badge>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table></div>
          )}
        </div>
      )}

      {/* Payments history */}
      {student && ledger && ledger.payments.length > 0 && (
        <div className="institution-admin-panel">
          <h3>Payments</h3>
          <div className="finance-table-wrap"><table className="finance-table">
            <thead>
              <tr>
                <th className="finance-table-heading">Receipt #</th>
                <th className="finance-table-heading finance-amount-heading">Amount</th>
                <th className="finance-table-heading">Method</th>
                <th className="finance-table-heading">Date</th>
              </tr>
            </thead>
            <tbody>
              {ledger.payments.map((p) => (
                <tr key={p.id}>
                  <td className="finance-table-cell"><span className="institution-mono">{p.receipt_number}</span></td>
                  <td className="finance-table-cell finance-amount-cell">₹{Number(p.amount).toLocaleString("en-IN")}</td>
                  <td className="finance-table-cell">{p.payment_method}</td>
                  <td className="finance-table-cell">{String(p.paid_at).slice(0, 10)}</td>
                </tr>
              ))}
            </tbody>
          </table></div>
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
    </div>
  );
};


export default FinanceStudentLedgerPage;
