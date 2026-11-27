import { useQuery } from "@tanstack/react-query";

import { financeService } from "../api";
import "../finance.css";

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
    <div className="finance-page">
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
          <p className="finance-muted">
            No payments recorded yet. Record a payment from a student's ledger.
          </p>
        ) : (
          <div className="finance-table-wrap"><table className="finance-table">
            <thead>
              <tr>
                <th className="finance-table-heading">Receipt</th>
                <th className="finance-table-heading">Student</th>
                <th className="finance-table-heading finance-amount-heading">Amount</th>
                <th className="finance-table-heading">Method</th>
                <th className="finance-table-heading">Reference</th>
                <th className="finance-table-heading">Date</th>
              </tr>
            </thead>
            <tbody>
              {payments.map((p) => (
                <tr key={p.id}>
                  <td className="finance-table-cell">
                    <span className="institution-mono">{p.receipt_number}</span>
                  </td>
                  <td className="finance-table-cell">
                    <div><strong>{p.student_profile?.user?.name ?? "—"}</strong></div>
                    <div className="institution-mono finance-receipt-email">
                      {p.student_profile?.user?.email}
                    </div>
                  </td>
                  <td className="finance-table-cell finance-amount-cell">₹{Number(p.amount).toLocaleString("en-IN")}</td>
                  <td className="finance-table-cell">
                    <Badge tone="info">{METHOD_LABEL[p.payment_method] ?? p.payment_method}</Badge>
                  </td>
                  <td className="finance-table-cell">
                    {p.reference ? <span className="institution-mono">{p.reference}</span> : "—"}
                  </td>
                  <td className="finance-table-cell">{String(p.paid_at).slice(0, 10)}</td>
                </tr>
              ))}
            </tbody>
          </table></div>
        )}
      </div>
    </div>
  );
};


export default FinanceReceiptsPage;
