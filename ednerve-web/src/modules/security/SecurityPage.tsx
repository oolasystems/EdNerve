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
import QRCode from "qrcode";
import { toast } from "sonner";

import { securityService } from "./api";
import "../institution/institution.css";

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
    <div style={{ maxWidth: 880, margin: "0 auto" }}>
      <h2 style={{ marginBottom: 4 }}>Account Security</h2>
      <p style={{ color: "var(--text-secondary)", marginTop: 0, marginBottom: 18 }}>
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

const MfaSection = ({ statusQuery, qc }: any) => {
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
    } catch (e: any) {
      setError(e?.response?.data?.message ?? "Unable to start MFA enrolment.");
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
    } catch (e: any) {
      setError(e?.response?.data?.errors?.code?.[0] ?? e?.response?.data?.message ?? "Invalid code.");
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
    } catch (e: any) {
      setError(e?.response?.data?.errors?.code?.[0] ?? e?.response?.data?.message ?? "Invalid code.");
    } finally {
      setBusy(false);
    }
  };

  if (statusQuery.isLoading) {
    return <div className="institution-admin-panel"><Spinner size={20} /></div>;
  }

  const enabled = status?.confirmed;

  return (
    <div className="institution-admin-panel" style={{ marginBottom: 18 }}>
      <h3>
        <ShieldCheck size={18} style={{ verticalAlign: "-3px" }} /> Two-step verification (TOTP)
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
              <p style={{ color: "var(--text-secondary)", fontSize: 14 }}>
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
              <p style={{ color: "var(--text-secondary)", fontSize: 14 }}>
                Scan this QR code with your authenticator app, or enter the secret manually.
              </p>
              {qrDataUrl && (
                <div style={{ textAlign: "center", padding: "12px 0" }}>
                  <img src={qrDataUrl} alt="TOTP QR code" width={220} height={220} />
                </div>
              )}
              <div className="institution-mono" style={{ fontSize: 13, background: "var(--surface-secondary)", borderRadius: "var(--radius-md)", padding: "8px 12px", wordBreak: "break-all", marginBottom: 12 }}>
                {secret}
                <button type="button" className="timetable-icon-btn" style={{ marginLeft: 8, verticalAlign: "-2px" }}
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
          <div className="institution-mono" style={{ display: "grid", gridTemplateColumns: "repeat(2, 1fr)", gap: 8, margin: "12px 0", fontSize: 14 }}>
            {recoveryCodes.map((code) => (
              <div key={code} style={{ background: "var(--surface-secondary)", borderRadius: "var(--radius-md)", padding: "8px 12px", textAlign: "center" }}>{code}</div>
            ))}
          </div>
          <Button onClick={() => { navigator.clipboard?.writeText(recoveryCodes.join("\n")); toast.success("Recovery codes copied."); }}>
            <Copy size={15} /> Copy recovery codes
          </Button>
        </>
      )}

      {enabled && enrolStep !== "confirmed" && (
        <>
          <p style={{ color: "var(--text-secondary)", fontSize: 14 }}>
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

const SessionsSection = ({ tokensQuery, qc }: any) => {
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
    <div className="institution-admin-panel" style={{ marginBottom: 18 }}>
      <h3>
        <Monitor size={18} style={{ verticalAlign: "-3px" }} /> Active sessions
        {tokens.length > 1 && (
          <Button variant="secondary" style={{ float: "right" }} onClick={revokeOthers} disabled={busyId !== null}>
            <LogOut size={14} /> Sign out everywhere else
          </Button>
        )}
      </h3>

      {tokensQuery.isLoading ? (
        <Spinner size={20} />
      ) : (
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "var(--font-size-sm)" }}>
          <thead>
            <tr>
              <th style={th}>Session</th>
              <th style={th}>Last used</th>
              <th style={th}>Created</th>
              <th style={th}></th>
            </tr>
          </thead>
          <tbody>
            {tokens.map((token: any) => (
              <tr key={token.id}>
                <td style={td}>
                  <strong>{token.name}</strong>
                  {token.current && <span className="institution-mono" style={{ fontSize: 11, marginLeft: 8 }}>(this device)</span>}
                </td>
                <td style={td}>{token.last_used_at ? String(token.last_used_at).slice(0, 16).replace("T", " ") : "—"}</td>
                <td style={td}>{String(token.created_at).slice(0, 10)}</td>
                <td style={td}>
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
              <tr><td style={td} colSpan={4}>No active sessions.</td></tr>
            )}
          </tbody>
        </table>
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
    <div className="institution-admin-panel" style={{ marginBottom: 18 }}>
      <h3>
        <ShieldAlert size={18} style={{ verticalAlign: "-3px" }} /> Security alerts
        {typeof data?.unacknowledged_count === "number" && data.unacknowledged_count > 0 && (
          <Badge tone="danger">{data.unacknowledged_count} unacknowledged</Badge>
        )}
      </h3>
      {alertsQuery.isLoading ? (
        <Spinner size={20} />
      ) : alerts.length === 0 ? (
        <p style={{ color: "var(--text-muted)" }}>No unacknowledged alerts.</p>
      ) : (
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "var(--font-size-sm)" }}>
          <thead>
            <tr>
              <th style={th}>Alert</th>
              <th style={th}>Kind</th>
              <th style={th}>When</th>
              <th style={th}></th>
            </tr>
          </thead>
          <tbody>
            {alerts.map((alert) => (
              <tr key={alert.id}>
                <td style={td}>
                  <Badge tone={SEVERITY_TONE[alert.severity] ?? "info"}>{alert.severity}</Badge>{" "}
                  <span style={{ marginLeft: 8 }}>{alert.message}</span>
                </td>
                <td style={td}><span className="institution-mono" style={{ fontSize: 11 }}>{alert.kind}</span></td>
                <td style={td}>{String(alert.created_at).slice(0, 16).replace("T", " ")}</td>
                <td style={td}>
                  <Button variant="secondary" onClick={() => void ack(alert.id)}>Ack</Button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
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
        <BadgeCheck size={18} style={{ verticalAlign: "-3px" }} /> Audit integrity (tamper-evident)
      </h3>
      <p style={{ color: "var(--text-secondary)", fontSize: 14 }}>
        Every audit event since the control was switched on is chained with SHA-256 into the previous record.
        Tampering with history breaks the chain.
      </p>
      <Button variant="secondary" onClick={run} disabled={checking}>
        <RefreshCw size={14} /> {checking ? "Verifying..." : "Verify audit chain"}
      </Button>

      {result && (
        <div style={{ marginTop: 12, display: "flex", gap: 12, alignItems: "center", flexWrap: "wrap" }}>
          {result.chain_valid ? (
            <Badge tone="success"><ShieldCheck size={12} /> Chain valid — {result.entries_verified} entries verified</Badge>
          ) : (
            <Badge tone="danger"><ShieldAlert size={12} /> Chain broken at entry #{result.broken_at?.id}</Badge>
          )}
          <span className="institution-mono" style={{ fontSize: 12, color: "var(--text-muted)" }}>
            checkpoints: {result.checkpoints_count} · sealed & signed
          </span>
        </div>
      )}
    </div>
  );
};

const th: React.CSSProperties = {
  textAlign: "left",
  padding: "8px 10px",
  borderBottom: "1px solid var(--border)",
  color: "var(--text-muted)",
  fontSize: "var(--font-size-xs)",
  textTransform: "uppercase",
  letterSpacing: "0.05em",
};
const td: React.CSSProperties = {
  padding: "10px",
  borderBottom: "1px solid var(--border)",
  verticalAlign: "middle",
};

export default SecurityPage;
