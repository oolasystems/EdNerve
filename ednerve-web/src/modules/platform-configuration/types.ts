/** Platform settings types. */

export type SettingType = "string" | "boolean" | "integer" | "json";

export interface PlatformSettingItem {
  id: number;
  key: string;
  group: string;
  type: SettingType;
  label: string;
  description: string | null;
  value: string | boolean | number | unknown[] | Record<string, unknown> | null;
}

export interface PlatformSettingGroup {
  group: string;
  items: PlatformSettingItem[];
}

export interface UpdateSettingItem {
  key: string;
  value: PlatformSettingItem["value"];
}
