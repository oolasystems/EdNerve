import apiClient from "../../shared/lib/api-client";

import type {
  PlatformSettingGroup,
  UpdateSettingItem,
} from "./types";

export const settingsService = {
  async getSettings(): Promise<{ settings: PlatformSettingGroup[] }> {
    const response = await apiClient.get<{ settings: PlatformSettingGroup[] }>(
      "/platform/settings"
    );

    return response.data;
  },

  async updateSettings(
    settings: UpdateSettingItem[]
  ): Promise<{ message: string; settings: PlatformSettingGroup[] }> {
    const response = await apiClient.put<{ message: string; settings: PlatformSettingGroup[] }>(
      "/platform/settings",
      { settings }
    );

    return response.data;
  },
};
