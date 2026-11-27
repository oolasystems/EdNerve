import {
  RefreshCw,
  Database,
  Layers,
  HardDrive,
  FolderCog,
  ListChecks,
  FileText,
  CheckCircle2,
  TriangleAlert,
  XCircle,
  MonitorCheck,
} from "lucide-react";

import { useQuery } from "@tanstack/react-query";

import { administrationService } from "../api";
import "../administration.css";

import { Alert, Badge, Button } from "../../../shared/ui";
import type { HealthStatus, SystemHealth } from "../types";

const CHECK_ICONS = {
  database: Database,
  cache: Layers,
  storage: HardDrive,
  logs: FileText,
  queue: ListChecks,
  disk: FolderCog,
} as const;

function statusIcon(status: HealthStatus) {
  switch (status) {
    case "ok":
      return CheckCircle2;
    case "warning":
      return TriangleAlert;
    default:
      return XCircle;
  }
}

function statusClass(status: HealthStatus) {
  switch (status) {
    case "ok":
      return "health-card-ok";
    case "warning":
      return "health-card-warning";
    default:
      return "health-card-critical";
  }
}

const SystemHealthPage = () => {
  const healthQuery = useQuery<SystemHealth>({
    queryKey: ["administration", "system-health"],
    queryFn: () => administrationService.getSystemHealth(),
    staleTime: 15_000,
    refetchInterval: 30_000,
  });

  const data = healthQuery.data;

  return (
    <div className="health-page">
      <div className="health-toolbar">
        <p className="dashboard-updated-label health-toolbar-description">
          Live probe of the services this application depends on. Auto-refreshes every 30 seconds.
        </p>
        <Button
          variant="secondary"
          size="sm"
          icon={RefreshCw}
          disabled={healthQuery.isFetching}
          onClick={() => void healthQuery.refetch()}
        >
          Refresh
        </Button>
      </div>

      {healthQuery.isError && (
        <Alert tone="error">Unable to reach the health endpoint.</Alert>
      )}

      {data && (
        <>
          <div className={`health-overall ${statusClass(data.status)}`} role="status">
            <div className="health-overall-status">
              <MonitorCheck size={26} aria-hidden="true" />
              <div>
                <strong>Overall status: {data.status.toUpperCase()}</strong>
                <span>
                  Checked at {new Date(data.checked_at).toLocaleString("en-IN")}
                </span>
              </div>
            </div>
            <span className={`status-${data.status}`}>{data.status.toUpperCase()}</span>
          </div>

          <div className="health-grid">
            {data.checks.map((check) => {
              const Icon = CHECK_ICONS[check.key as keyof typeof CHECK_ICONS] ?? Database;
              const StatusIcon = statusIcon(check.status);

              return (
                <article key={check.key} className={`health-card ${statusClass(check.status)}`}>
                  <div className="health-card-head">
                    <Icon size={20} aria-hidden="true" />
                    <h3>{check.label}</h3>
                    <span className={`status-${check.status}`}>
                      <StatusIcon size={12} aria-hidden="true" />
                      {check.status.toUpperCase()}
                    </span>
                  </div>
                  <p>{check.message}</p>
                </article>
              );
            })}
          </div>

          <section className="health-info">
            <div className="settings-section-heading health-runtime-heading">
              <MonitorCheck size={18} aria-hidden="true" />
              <h3>Runtime Environment</h3>
            </div>

            <dl className="kv-grid">
              <div>
                <dt>App Name</dt>
                <dd>{data.info.app_name}</dd>
              </div>
              <div>
                <dt>Environment</dt>
                <dd>
                  {data.info.environment}
                  {data.info.debug && (
                    <Badge tone="warning">DEBUG ON</Badge>
                  )}
                </dd>
              </div>
              <div>
                <dt>Laravel Version</dt>
                <dd>{data.info.laravel_version}</dd>
              </div>
              <div>
                <dt>PHP Version</dt>
                <dd>{data.info.php_version}</dd>
              </div>
              <div>
                <dt>DB Driver</dt>
                <dd>{data.info.db_connection}</dd>
              </div>
              <div>
                <dt>App URL</dt>
                <dd>{data.info.app_url}</dd>
              </div>
              <div>
                <dt>Timezone</dt>
                <dd>{data.info.timezone}</dd>
              </div>
              <div>
                <dt>Server Time</dt>
                <dd>{new Date(data.info.server_time).toLocaleString("en-IN")}</dd>
              </div>
            </dl>
          </section>
        </>
      )}
    </div>
  );
};

export default SystemHealthPage;
