import apiClient from "../../shared/lib/api-client";

import type {
  DashboardResponse,
} from "./types";

export const dashboardService = {
  async getDashboard(): Promise<DashboardResponse> {
    const response = await apiClient.get<DashboardResponse>(
      "/platform/dashboard"
    );

    return response.data;
  },
};