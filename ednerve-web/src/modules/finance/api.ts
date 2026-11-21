import apiClient from "../../shared/lib/api-client";

export interface FeeHead {
  id: number;
  code: string;
  name: string;
  description?: string | null;
  status: string;
}

export interface FeeHeadShort {
  id: number;
  code: string;
  name: string;
}

export interface FeeStructure {
  id: number;
  programme_id: number | null;
  academic_session_id: number | null;
  fee_head_id: number;
  amount: string;
  frequency: "annual" | "semester" | "one_time";
  notes?: string | null;
  status: string;
  fee_head?: FeeHeadShort | null;
  academic_session?: { id: number; code: string; name?: string } | null;
  programme?: { id: number; code: string; name: string } | null;
}

export interface FeeDue {
  id: number;
  programme_id: number | null;
  academic_session_id: number | null;
  fee_head_id: number;
  amount: string;
  paid_amount: string;
  due_date: string | null;
  status: "pending" | "partial" | "paid" | "waived";
  waived_reason?: string | null;
  fee_head?: FeeHeadShort | null;
  academic_session?: { id: number; code: string } | null;
}

export interface FeePayment {
  id: number;
  student_profile_id: number;
  receipt_number: string | null;
  amount: string;
  payment_method: string;
  reference?: string | null;
  paid_at: string;
  received_by: number | null;
  remarks?: string | null;
  student_profile?: {
    user?: { id: number; name: string; email: string } | null;
  } | null;
}

export interface StudentLedgerSummary {
  student: {
    id: number;
    application_number: string | null;
    user?: { id: number; name: string; email: string } | null;
    programme?: { id: number; code: string; name: string } | null;
    academic_session?: { id: number; code: string; name: string } | null;
  };
  summary: {
    total_due: number;
    total_paid: number;
    balance: number;
    dues_count: number;
    payments_count: number;
  };
  dues: Array<
    FeeDue & {
      fee_head?: FeeHeadShort | null;
      academic_session?: { id: number; code: string } | null;
    }
  >;
  payments: FeePayment[];
}

export const financeService = {
  async getFeeHeads(): Promise<{ fee_heads: FeeHead[] }> {
    const response = await apiClient.get<{ fee_heads: FeeHead[] }>(
      "/finance/fee-heads"
    );
    return response.data;
  },

  async getStructures(params?: {
    programme_id?: number;
    academic_session_id?: number;
  }): Promise<{ structures: FeeStructure[] }> {
    const response = await apiClient.get<{ structures: FeeStructure[] }>(
      "/finance/structures",
      { params }
    );
    return response.data;
  },

  async createFeeStructure(payload: {
    programme_id?: number | null;
    academic_session_id?: number | null;
    fee_head_id: number;
    amount: number;
    frequency: "annual" | "semester" | "one_time";
    notes?: string;
  }) {
    const response = await apiClient.post("/finance/structures", payload);
    return response.data;
  },

  async getStudentLedger(profileId: number): Promise<StudentLedgerSummary> {
    const response = await apiClient.get<StudentLedgerSummary>(
      `/students/${profileId}/ledger`
    );
    return response.data;
  },

  async recordPayment(
    profileId: number,
    payload: {
      amount: number;
      payment_method:
        | "cash"
        | "cheque"
        | "demand_draft"
        | "upi"
        | "card"
        | "bank_transfer"
        | "other";
      reference?: string;
      paid_at?: string;
      remarks?: string;
    }
  ) {
    const response = await apiClient.post(
      `/students/${profileId}/payments`,
      payload
    );
    return response.data;
  },

  async getReceipts(params?: { per_page?: number; page?: number }) {
    const response = await apiClient.get("/receipts", { params });
    return response.data;
  },
};
