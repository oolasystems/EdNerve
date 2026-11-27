import { useEffect, useState } from "react";
import {
  ShieldCheck,
  ShieldAlert,
  KeyRound,
  Monitor,
  BadgeCheck,
  Copy,
  LogOut,
  RefreshCw,
} from "lucide-react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import type { QueryClient, UseQueryResult } from "@tanstack/react-query";
import QRCode from "qrcode";
import { toast } from "sonner";

import { securityService } from "./api";
import "./security.css";
import { getApiErrorMessage } from "../../shared/lib/api-error";
import { useAuth } from "../../contexts/AuthContext";
import { isInstitutionAdmin, isPlatformAdmin } from "../../shared/lib/rbac";

import {
  Alert,
  Badge,
  Button,
  FormField,
  Input,
  Spinner,
} from "../../shared/ui";


interface MfaSectionProps {
  statusQuery: UseQueryResult<Awaited<ReturnType<typeof securityService.getStatus>>>;
  qc: QueryClient;
}

interface SessionsSectionProps {
  tokensQuery: UseQueryResult<Awaited<ReturnType<typeof securityService.getTokens>>>;
  qc: QueryClient;
}

const SecurityPage = () => {
  const { user } = useAuth();
  const qc = useQueryClient();
  const canAudit = isInstitutionAdmin(user) || isPlatformAdmin(user);

  const statusQuery = useQuery({
    queryKey: ["security", "mfa-status"],
    queryFn: () => securityService.getStatus(),
  });

  const tokensQuery = useQuery({
    queryKey: ["security", "tokens"],
    queryFn: () => securityService.getTokens(),
  });

  return (
    <div className="security-page">
      <h2 className="security-page__title">Account Security</h2>
      <p className="security-page__intro">
        Two-step verification, active sessions and platform integrity checks.
      </p>

      <MfaSection statusQuery={statusQuery} qc={qc} />

      <SessionsSection tokensQuery={tokensQuery} qc={qc} />

      {canAudit && <SecurityAlertsSection />}

      {canAudit && <AuditSection />}
    </div>
  );
};

/* ============================================================
 * MFA enrolment / management
 * ============================================================ */

const MfaSection = ({ statusQuery, qc }: MfaSectionProps) => {
  const [enrolStep, setEnrolStep] = useState<"idle" | "enrolled" | "confirmed">("idle");
  const [secret, setSecret] = useState("");
  const [otpauthUri, setOtpauthUri] = useState("");
  const [qrDataUrl, setQrDataUrl] = useState<string>("");
  const [confirmCode, setConfirmCode] = useState("");
  const [recoveryCodes, setRecoveryCodes] = useState<string[] | null>(null);
  const [disableCode, setDisableCode] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");

  const status = statusQuery.data;

  useEffect(() => {
    if (otpauthUri) {
      QRCode.toDataURL(otpauthUri, { width: 220, margin: 1 }).then(setQrDataUrl);
    }
  }, [otpauthUri]);

  const startEnrol = async () => {
    setError("");
    setBusy(true);
    try {
      const data = await securityService.enrol();
      setSecret(data.secret);
      setOtpauthUri(data.otpauth_uri);
      setEnrolStep("enrolled");
    } catch (error: unknown) {
      setError(getApiErrorMessage(error, "Unable to start MFA enrolment."));
    } finally {
      setBusy(false);
    }
  };

  const confirmEnrol = async () => {
    setError("");
    setBusy(true);
    try {
      const data = await securityService.confirm(confirmCode.trim());
      setRecoveryCodes(data.recovery_codes ?? []);
      setEnrolStep("confirmed");
      await qc.invalidateQueries({ queryKey: ["security", "mfa-status"] });
      toast.success("MFA enabled. Save your recovery codes.");
    } catch (error: unknown) {
      setError(getApiErrorMessage(error, "Invalid code."));
    } finally {
      setBusy(false);
    }
  };

  const disableMfa = async () => {
    setError("");
    setBusy(true);
    try {
      await securityService.disable(disableCode.trim());
      setDisableCode("");
      setEnrolStep("idle");
      await qc.invalidateQueries({ queryKey: ["security", "mfa-status"] });
      toast.success("MFA disabled.");
    } catch (error: unknown) {
      setError(getApiErrorMessage(error, "Invalid code."));
    } finally {
      setBusy(false);
    }
  };

  if (statusQuery.isLoading) {
    return <div className="institution-admin-panel"><Spinner size={20} /></div>;
  }

  const enabled = status?.confirmed;

  return (
    <div className="institution-admin-panel security-panel">
      <h3>
        <ShieldCheck size={18} className="security-heading-icon" aria-hidden="true" /> Two-step verification (TOTP)
        {enabled ? (
          <Badge tone="success">Enabled</Badge>
        ) : (
          <Badge tone="warning">Not enabled</Badge>
        )}
      </h3>

      {error && <div className="institution-form-alert" role="alert">{error}</div>}

      {enrolStep !== "confirmed" && !enabled && (
        <>
          {enrolStep === "idle" && (
            <>
              <p className="security-description">
                Add an authenticator app (Google Authenticator, 1Password, Microsoft Authenticator).
                {status?.required && (
                  <strong> Required for your role — finish setup to access marks & payments.</strong>
                )}
              </p>
              <Button onClick={startEnrol} disabled={busy}>
                <KeyRound size={16} /> Set up authenticator
              </Button>
            </>
          )}

          {enrolStep === "enrolled" && (
            <>
              <p className="security-description">
                Scan this QR code with your authenticator app, or enter the secret manually.
              </p>
              {qrDataUrl && (
                <div className="security-qr">
                  <img src={qrDataUrl} alt="TOTP QR code" width={220} height={220} />
                </div>
              )}
              <div className="institution-mono security-secret">
                {secret}
                <button type="button" className="timetable-icon-btn security-copy-secret"
                  onClick={() => { navigator.clipboard?.writeText(secret); toast.success("Secret copied."); }}
                  aria-label="Copy secret">
                  <Copy size={13} />
                </button>
              </div>
              <FormField label="Enter the 6-digit code from the app" htmlFor="mfa-confirm">
                <Input id="mfa-confirm" inputMode="numeric" placeholder="123456" autoComplete="one-time-code"
                  value={confirmCode} onChange={(e) => setConfirmCode(e.target.value)} disabled={busy} />
              </FormField>
              <Button onClick={confirmEnrol} disabled={busy || confirmCode.trim().length !== 6}>
                {busy ? "Verifying..." : "Confirm & enable"}
              </Button>
            </>
          )}
        </>
      )}

      {enrolStep === "confirmed" && recoveryCodes && (
        <>
          <Alert tone="warning">
            Save these recovery codes now. They are shown <strong>once</strong>. Each code works a single time.
          </Alert>
          <div className="institution-mono security-recovery-codes">
            {recoveryCodes.map((code) => (
              <div key={code} className="security-recovery-code">{code}</div>
            ))}
          </div>
          <Button onClick={() => { navigator.clipboard?.writeText(recoveryCodes.join("\n")); toast.success("Recovery codes copied."); }}>
            <Copy size={15} /> Copy recovery codes
          </Button>
        </>
      )}

      {enabled && enrolStep !== "confirmed" && (
        <>
          <p className="security-description">
            Two-step verification is active on your account.
            {typeof status?.recovery_codes_remaining === "number" && (
              <> Recovery codes remaining: <strong>{status.recovery_codes_remaining}</strong>.</>
            )}
          </p>
          <FormField label="Enter a code from your app to disable MFA" htmlFor="mfa-disable">
            <Input id="mfa-disable" inputMode="numeric" placeholder="123456" autoComplete="one-time-code"
              value={disableCode} onChange={(e) => setDisableCode(e.target.value)} disabled={busy} />
          </FormField>
          <Button variant="secondary" onClick={disableMfa} disabled={busy || disableCode.trim().length < 6}>
            {busy ? "Disabling..." : "Disable MFA"}
          </Button>
        </>
      )}
    </div>
  );
};

/* ============================================================
 * Active sessions
 * ============================================================ */

const SessionsSection = ({ tokensQuery, qc }: SessionsSectionProps) => {
  const [busyId, setBusyId] = useState<number | "all" | null>(null);

  const revokeOne = async (id: number) => {
    setBusyId(id);
    try {
      await securityService.revokeToken(id);
      await qc.invalidateQueries({ queryKey: ["security", "tokens"] });
      toast.success("Session revoked.");
    } catch {
      toast.error("Unable to revoke that session.");
    } finally {
      setBusyId(null);
    }
  };

  const revokeOthers = async () => {
    setBusyId("all");
    try {
      await securityService.revokeOtherTokens();
      await qc.invalidateQueries({ queryKey: ["security", "tokens"] });
      toast.success("Other sessions signed out.");
    } catch {
      toast.error("Unable to revoke sessions.");
    } finally {
      setBusyId(null);
    }
  };

  const tokens = tokensQuery.data?.tokens ?? [];

  return (
    <div className="institution-admin-panel security-panel">
      <h3>
        <Monitor size={18} className="security-heading-icon" aria-hidden="true" /> Active sessions
        {tokens.length > 1 && (
          <Button variant="secondary" className="security-session-action" onClick={revokeOthers} disabled={busyId !== null}>
            <LogOut size={14} /> Sign out everywhere else
          </Button>
        )}
      </h3>

      {tokensQuery.isLoading ? (
        <Spinner size={20} />
      ) : (
        <div className="security-table-wrap">
          <table className="security-table">
          <thead>
            <tr>
              <th className="security-table__heading">Session</th>
              <th className="security-table__heading">Last used</th>
              <th className="security-table__heading">Created</th>
              <th className="security-table__heading"></th>
            </tr>
          </thead>
          <tbody>
            {tokens.map((token) => (
              <tr key={token.id}>
                <td className="security-table__cell">
                  <strong>{token.name}</strong>
                  {token.current && <span className="institution-mono security-current-device">(this device)</span>}
                </td>
                <td className="security-table__cell">{token.last_used_at ? String(token.last_used_at).slice(0, 16).replace("T", " ") : "—"}</td>
                <td className="security-table__cell">{String(token.created_at).slice(0, 10)}</td>
                <td className="security-table__cell">
                  {!token.current && (
                    <button type="button" className="timetable-icon-btn" disabled={busyId !== null}
                      onClick={() => revokeOne(token.id)} aria-label="Revoke session">
                      <LogOut size={13} />
                    </button>
                  )}
                </td>
              </tr>
            ))}
            {tokens.length === 0 && (
              <tr><td className="security-table__cell" colSpan={4}>No active sessions.</td></tr>
            )}
          </tbody>
          </table>
        </div>
      )}
    </div>
  );
};

/* ============================================================
 * Security alerts (admin only)
 * ============================================================ */

const SecurityAlertsSection = () => {
  const qc = useQueryClient();
  const alertsQuery = useQuery({
    queryKey: ["security", "alerts"],
    queryFn: () => securityService.getAlerts("unacknowledged"),
  });

  const data = alertsQuery.data;
  const alerts = data?.alerts ?? [];

  const ack = async (id: number) => {
    try {
      await securityService.acknowledgeAlert(id);
      await qc.invalidateQueries({ queryKey: ["security", "alerts"] });
      toast.success("Alert acknowledged.");
    } catch {
      toast.error("Unable to acknowledge the alert.");
    }
  };

  const SEVERITY_TONE: Record<string, "info" | "warning" | "danger"> = {
    info: "info",
    warning: "warning",
    high: "warning",
    critical: "danger",
  };

  return (
    <div className="institution-admin-panel security-panel">
      <h3>
        <ShieldAlert size={18} className="security-heading-icon" aria-hidden="true" /> Security alerts
        {typeof data?.unacknowledged_count === "number" && data.unacknowledged_count > 0 && (
          <Badge tone="danger">{data.unacknowledged_count} unacknowledged</Badge>
        )}
      </h3>
      {alertsQuery.isLoading ? (
        <Spinner size={20} />
      ) : alerts.length === 0 ? (
        <p className="security-muted-message">No unacknowledged alerts.</p>
      ) : (
        <div className="security-table-wrap">
          <table className="security-table">
          <thead>
            <tr>
              <th className="security-table__heading">Alert</th>
              <th className="security-table__heading">Kind</th>
              <th className="security-table__heading">When</th>
              <th className="security-table__heading"></th>
            </tr>
          </thead>
          <tbody>
            {alerts.map((alert) => (
              <tr key={alert.id}>
                <td className="security-table__cell">
                  <Badge tone={SEVERITY_TONE[alert.severity] ?? "info"}>{alert.severity}</Badge>{" "}
                  <span className="security-alert-message">{alert.message}</span>
                </td>
                <td className="security-table__cell"><span className="institution-mono security-alert-kind">{alert.kind}</span></td>
                <td className="security-table__cell">{String(alert.created_at).slice(0, 16).replace("T", " ")}</td>
                <td className="security-table__cell">
                  <Button variant="secondary" onClick={() => void ack(alert.id)}>Ack</Button>
                </td>
              </tr>
            ))}
          </tbody>
          </table>
        </div>
      )}
    </div>
  );
};

/* ============================================================
 * Audit integrity verify (admin/platform only)
 * ============================================================ */

const AuditSection = () => {
  const [checking, setChecking] = useState(false);
  const verifyQuery = useQuery({
    queryKey: ["security", "audit-verify"],
    queryFn: () => securityService.verifyAudit(),
    enabled: false,
  });

  const run = async () => {
    setChecking(true);
    try {
      await verifyQuery.refetch();
    } finally {
      setChecking(false);
    }
  };

  const result = verifyQuery.data;

  return (
    <div className="institution-admin-panel">
      <h3>
        <BadgeCheck size={18} className="security-heading-icon" aria-hidden="true" /> Audit integrity (tamper-evident)
      </h3>
      <p className="security-description">
        Every audit event since the control was switched on is chained with SHA-256 into the previous record.
        Tampering with history breaks the chain.
      </p>
      <Button variant="secondary" onClick={run} disabled={checking}>
        <RefreshCw size={14} /> {checking ? "Verifying..." : "Verify audit chain"}
      </Button>

      {result && (
        <div className="security-audit-result">
          {result.chain_valid ? (
            <Badge tone="success"><ShieldCheck size={12} /> Chain valid — {result.entries_verified} entries verified</Badge>
          ) : (
            <Badge tone="danger"><ShieldAlert size={12} /> Chain broken at entry #{result.broken_at?.id}</Badge>
          )}
          <span className="institution-mono security-checkpoints">
            checkpoints: {result.checkpoints_count} · sealed & signed
          </span>
        </div>
      )}
    </div>
  );
};


export default SecurityPage;
