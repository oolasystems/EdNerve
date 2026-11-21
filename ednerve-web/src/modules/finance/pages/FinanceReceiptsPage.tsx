import { useQuery } from "@tanstack/react-query";

import { financeService } from "../api";
import "../../institution/institution.css";

import {
  Alert,
  Badge,
} from "../../../shared/ui";

const METHOD_LABEL: Record<string, string> = {
  cash: "Cash",
  cheque: "Cheque",
  demand_draft: "Demand Draft",
  upi: "UPI",
  card: "Card",
  bank_transfer: "Bank Transfer",
  other: "Other",
};

const FinanceReceiptsPage = () => {
  const receiptsQuery = useQuery({
    queryKey: ["finance", "receipts"],
    queryFn: () => financeService.getReceipts({ per_page: 50 }),
  });

  const payments = (receiptsQuery.data?.data ?? []) as Array<{
    id: number;
    receipt_number?: string;
    amount: string;
    payment_method: string;
    reference?: string | null;
    paid_at: string;
    student_profile?: { user?: { id: number; name: string; email: string } };
  }>;

  return (
    <>
      {receiptsQuery.isError && (
        <Alert tone="error">
          Unable to load receipts. Please try again.
          <button type="button" onClick={() => void receiptsQuery.refetch()}>
            Retry
          </button>
        </Alert>
      )}

      <div className="institution-admin-panel">
        <h3>Recent receipts</h3>
        {receiptsQuery.isLoading ? (
          <p>Loading receipts...</p>
        ) : payments.length === 0 ? (
          <p style={{ color: "var(--text-muted)" }}>
            No payments recorded yet. Record a payment from a student's ledger.
          </p>
        ) : (
          <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "var(--font-size-sm)" }}>
            <thead>
              <tr>
                <th style={th}>Receipt</th>
                <th style={th}>Student</th>
                <th style={th}>Amount</th>
                <th style={th}>Method</th>
                <th style={th}>Reference</th>
                <th style={th}>Date</th>
              </tr>
            </thead>
            <tbody>
              {payments.map((p) => (
                <tr key={p.id}>
                  <td style={td}>
                    <span className="institution-mono">{p.receipt_number}</span>
                  </td>
                  <td style={td}>
                    <div><strong>{p.student_profile?.user?.name ?? "—"}</strong></div>
                    <div className="institution-mono" style={{ fontSize: 11 }}>
                      {p.student_profile?.user?.email}
                    </div>
                  </td>
                  <td style={td}>₹{Number(p.amount).toLocaleString("en-IN")}</td>
                  <td style={td}>
                    <Badge tone="info">{METHOD_LABEL[p.payment_method] ?? p.payment_method}</Badge>
                  </td>
                  <td style={td}>
                    {p.reference ? <span className="institution-mono">{p.reference}</span> : "—"}
                  </td>
                  <td style={td}>{String(p.paid_at).slice(0, 10)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </>
  );
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

export default FinanceReceiptsPage;
