import apiClient from "../../shared/lib/api-client";

import type {
  AuditLogEntry,
  AuditLogListParams,
  SystemHealth,
} from "./types";
import type { PaginatedResponse } from "../../shared/types/api";

export const administrationService = {
  async getAuditLogs(
    params: AuditLogListParams = {}
  ): Promise<PaginatedResponse<AuditLogEntry>> {
    const response = await apiClient.get<PaginatedResponse<AuditLogEntry>>(
      "/platform/audit-logs",
      { params }
    );

    return response.data;
  },

  async getSystemHealth(): Promise<SystemHealth> {
    const response = await apiClient.get<SystemHealth>(
      "/platform/system-health"
    );

    return response.data;
  },
};
