import { useMemo, useState } from "react";

import {
  RefreshCw,
  Save,
  Settings,
  GraduationCap,
  Sparkles,
  ShieldCheck,
} from "lucide-react";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { settingsService } from "../api";
import type { PlatformSettingItem, SettingType } from "../types";
import "../configuration.css";

import {
  Alert,
  Button,
  Input,
} from "../../../shared/ui";
import { getApiErrorMessage } from "../../../shared/lib/api-error";

export interface SettingsPageProps {
  /** Groups to display; every group the backend returns is eligible. */
  groups: Array<"system" | "academic" | "features">;
  heading: string;
  description: string;
}

const GROUP_ICONS = {
  system: Settings,
  academic: GraduationCap,
  features: Sparkles,
} as const;

const GROUP_LABELS = {
  system: "System",
  academic: "Academic",
  features: "Feature Flags",
} as const;

function draftValue(item: PlatformSettingItem): string | boolean {
  if (item.type === "boolean") return Boolean(item.value);
  if (item.type === "json") return JSON.stringify(item.value, null, 2);
  return String(item.value ?? "");
}

const SettingsPage = ({ groups, heading, description }: SettingsPageProps) => {
  const queryClient = useQueryClient();

  // Store only user-edited values. Untouched fields are derived from the latest
  // server response, so query hydration does not require a state-setting effect.
  const [drafts, setDrafts] = useState<Record<string, string | boolean>>({});
  const [pageError, setPageError] = useState("");

  const settingsQuery = useQuery({
    queryKey: ["platform", "settings"],
    queryFn: () => settingsService.getSettings(),
  });

  const groupsPayload = settingsQuery.data?.settings;

  const visible = useMemo(
    () => (groupsPayload ?? []).filter((group) => groups.includes(group.group as (typeof groups)[number])),
    [groupsPayload, groups]
  );

  const valueFor = (item: PlatformSettingItem): string | boolean =>
    drafts[item.key] ?? draftValue(item);

  const dirtyKeys = (groupsPayload ?? []).flatMap((group) =>
    group.items
      .filter((item) => Object.hasOwn(drafts, item.key) && drafts[item.key] !== draftValue(item))
      .map((item) => item.key)
  );

  const setDraft = (key: string, value: string | boolean) => {
    setDrafts((current) => ({ ...current, [key]: value }));
  };

  const resetDrafts = () => {
    setDrafts({});
    setPageError("");
  };

  const saveMutation = useMutation({
    mutationFn: () => {
      const changed = dirtyKeys.map((key) => {
        const item = (groupsPayload ?? [])
          .flatMap((group) => group.items)
          .find((candidate) => candidate.key === key);
        const type = item?.type;
        const raw = drafts[key];

        let value: unknown = raw;
        if (type === "integer" && typeof raw === "string") {
          value = Number(raw);
        }
        if (type === "json" && typeof raw === "string") {
          value = JSON.parse(raw); // throws on invalid JSON
        }

        return { key, value: value as string | boolean | number | unknown[] | Record<string, unknown> | null };
      });

      return settingsService.updateSettings(changed);
    },
    onSuccess: (data) => {
      toast.success(data.message);
      setPageError("");
      setDrafts({});
      void queryClient.invalidateQueries({ queryKey: ["platform", "settings"] });
    },
    onError: (error) => {
      const message =
        error instanceof SyntaxError
          ? "A JSON field contains invalid JSON."
          : getApiErrorMessage(error, "Unable to save the settings.");
      setPageError(message);
    },
  });

  const inputTypeFor = (type: SettingType) => {
    switch (type) {
      case "integer":
        return "number";
      default:
        return "text";
    }
  };

  const isDirty = dirtyKeys.length > 0;

  return (
    <div className="settings-page">
      <div className="settings-page-header">
        <div>
          <h2 className="settings-page-title">
            <ShieldCheck size={20} aria-hidden="true" />
            {heading}
          </h2>
          <p className="settings-page-description">{description}</p>
        </div>

        <div className="settings-page-actions">
          <Button
            variant="ghost"
            size="sm"
            icon={RefreshCw}
            disabled={settingsQuery.isFetching}
            onClick={() => void settingsQuery.refetch()}
          >
            Reload
          </Button>
          <Button
            variant="secondary"
            size="sm"
            disabled={!isDirty || saveMutation.isPending}
            onClick={resetDrafts}
          >
            Reset
          </Button>
          <Button
            variant="primary"
            size="sm"
            icon={Save}
            loading={saveMutation.isPending}
            disabled={!isDirty}
            onClick={() => {
              setPageError("");
              saveMutation.mutate();
            }}
          >
            Save {isDirty ? `(${dirtyKeys.length})` : "Changes"}
          </Button>
        </div>
      </div>

      {pageError && <Alert tone="error">{pageError}</Alert>}

      {settingsQuery.isLoading ? (
        <div className="settings-empty">
          <RefreshCw size={22} className="spinner" aria-hidden="true" />
          <p>Loading settings...</p>
        </div>
      ) : settingsQuery.isError ? (
        <Alert tone="error">Unable to load settings.</Alert>
      ) : (
        <div className="settings-groups">
          {visible.map((group) => {
            const Icon = GROUP_ICONS[group.group as keyof typeof GROUP_ICONS] ?? Settings;

            return (
              <section key={group.group} className="settings-section">
                <div className="settings-section-heading">
                  <Icon size={18} aria-hidden="true" />
                  <h3>{GROUP_LABELS[group.group as keyof typeof GROUP_LABELS] ?? group.group}</h3>
                </div>

                <div className="settings-rows">
                  {group.items.map((item) => (
                    <div key={item.key} className="setting-row">
                      <div className="setting-row-label">
                        <strong>{item.label}</strong>
                        {item.description && <span>{item.description}</span>}
                        <code>{item.key}</code>
                      </div>

                      <div className="setting-row-control">
                        {item.type === "boolean" ? (
                          <label className="setting-toggle">
                            <input
                              type="checkbox"
                              checked={valueFor(item) === true}
                              onChange={(event) => setDraft(item.key, event.target.checked)}
                            />
                            <span>{valueFor(item) === true ? "Enabled" : "Disabled"}</span>
                          </label>
                        ) : item.type === "json" ? (
                          <textarea
                            className="form-control setting-json"
                            rows={3}
                            value={String(valueFor(item))}
                            onChange={(event) => setDraft(item.key, event.target.value)}
                            spellCheck={false}
                          />
                        ) : (
                          <Input
                            type={inputTypeFor(item.type)}
                            value={String(valueFor(item))}
                            onChange={(event) => setDraft(item.key, event.target.value)}
                          />
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              </section>
            );
          })}
        </div>
      )}

      {isDirty && (
        <p className="settings-dirty-note">
          {dirtyKeys.length} unsaved {dirtyKeys.length === 1 ? "change" : "changes"} —
          remember to save before leaving.
        </p>
      )}
    </div>
  );
};

export default SettingsPage;
