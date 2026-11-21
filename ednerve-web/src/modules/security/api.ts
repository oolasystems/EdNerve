import apiClient from "../../shared/lib/api-client";

export interface MfaStatus {
  enrolled: boolean;
  confirmed: boolean;
  recovery_codes_remaining?: number;
  required?: boolean;
}

export interface TokenInfo {
  id: number;
  name: string;
  abilities: unknown;
  last_used_at: string | null;
  created_at: string;
  expires_at: string | null;
  current: boolean;
}

export interface AuditVerifyResult {
  chain_valid: boolean;
  entries_verified: number;
  broken_at: { id: number; created_at: string } | null;
  checkpoints: Array<{
    for_date: string;
    record_count: number;
    last_entry_hash: string;
    signature_valid: boolean;
  }>;
  checkpoints_count: number;
}

export interface SecurityAlertItem {
  id: number;
  kind: string;
  severity: "info" | "warning" | "high" | "critical";
  message: string;
  context?: Record<string, unknown> | null;
  ip_address: string | null;
  created_at: string;
  acknowledged_at: string | null;
}

export const securityService = {
  async getStatus(): Promise<MfaStatus> {
    const response = await apiClient.post<MfaStatus>("/auth/mfa/status");
    return response.data;
  },

  async enrol(): Promise<{ secret: string; otpauth_uri: string }> {
    const response = await apiClient.post<{ secret: string; otpauth_uri: string }>("/auth/mfa/enrol");
    return response.data;
  },

  async confirm(code: string): Promise<{ message: string; recovery_codes: string[] }> {
    const response = await apiClient.post<{ message: string; recovery_codes: string[] }>(
      "/auth/mfa/confirm",
      { code }
    );
    return response.data;
  },

  async disable(code: string): Promise<{ message: string }> {
    const response = await apiClient.post<{ message: string }>("/auth/mfa/disable", { code });
    return response.data;
  },

  async getTokens(): Promise<{ tokens: TokenInfo[] }> {
    const response = await apiClient.get<{ tokens: TokenInfo[] }>("/auth/tokens");
    return response.data;
  },

  async revokeToken(id: number): Promise<void> {
    await apiClient.delete(`/auth/tokens/${id}`);
  },

  async revokeOtherTokens(): Promise<void> {
    await apiClient.delete("/auth/tokens");
  },

  async verifyAudit(): Promise<AuditVerifyResult> {
    const response = await apiClient.get<AuditVerifyResult>("/security/audit/verify");
    return response.data;
  },

  async getAlerts(status?: "unacknowledged" | "acknowledged"): Promise<{ alerts: SecurityAlertItem[]; unacknowledged_count: number }> {
    const response = await apiClient.get<{ alerts: SecurityAlertItem[]; unacknowledged_count: number }>(
      "/security/alerts",
      { params: status ? { status } : undefined }
    );
    return response.data;
  },

  async acknowledgeAlert(id: number): Promise<void> {
    await apiClient.patch(`/security/alerts/${id}/ack`);
  },
};
