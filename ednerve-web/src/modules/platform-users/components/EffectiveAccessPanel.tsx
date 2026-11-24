import { useMemo } from "react";
import {
  ShieldCheck,
  RefreshCw,
  Copy,
  KeyRound,
} from "lucide-react";
import { useQuery } from "@tanstack/react-query";
import { toast } from "sonner";

import { platformUserService } from "../api";
import type {
  EffectiveAssignment,
  EffectivePermission,
  EffectivePermissionVia,
} from "../types";
import "./effective-access.css";

import { Alert, Badge, Button, LoadingState } from "../../../shared/ui";

export interface EffectiveAccessPanelProps {
  userId: number;
}

function viaLabel(via: EffectivePermissionVia): string {
  const scope = via.scope ? ` @ ${via.scope}` : "";
  const expiry = via.expires
    ? ` · until ${new Date(via.expires).toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" })}`
    : "";
  const primary = via.is_primary ? " · primary" : "";
  return `${via.role_name}${scope}${expiry}${primary}`;
}

const EMPTY_PERMISSIONS: EffectivePermission[] = [];
const EMPTY_ASSIGNMENTS: EffectiveAssignment[] = [];

const EffectiveAccessPanel = ({ userId }: EffectiveAccessPanelProps) => {
  const query = useQuery({
    queryKey: ["users", "effective-permissions", userId],
    queryFn: () => platformUserService.getEffectivePermissions(userId),
    staleTime: 10_000,
  });

  const permissions = query.data?.permissions ?? EMPTY_PERMISSIONS;
  const assignments = query.data?.assignments ?? EMPTY_ASSIGNMENTS;

  const groups = useMemo(() => {
    const map = new Map<string, EffectivePermission[]>();
    for (const permission of permissions) {
      const list = map.get(permission.module) ?? [];
      list.push(permission);
      map.set(permission.module, list);
    }
    return [...map.entries()].map(([module, items]) => ({
      module,
      items,
    }));
  }, [permissions]);

  const handleCopyJson = () => {
    const payload = JSON.stringify(
      {
        user_id: userId,
        assignments: assignments.map((assignment) => ({
          role: assignment.role.code,
          scope: assignment.academic_unit?.name ?? null,
          is_primary: assignment.is_primary,
          ends_at: assignment.ends_at,
        })),
        permissions: permissions.map((permission) => permission.code),
      },
      null,
      2
    );

    void navigator.clipboard?.writeText(payload).then(() => {
      toast.success("Effective access copied to clipboard.");
    });
  };

  if (query.isLoading) {
    return <LoadingState message="Resolving effective access..." spinnerSize={22} />;
  }

  if (query.isError) {
    return (
      <Alert tone="error">
        Unable to resolve effective access.{" "}
        <button type="button" onClick={() => void query.refetch()}>
          Retry
        </button>
      </Alert>
    );
  }

  return (
    <section className="effective-panel" aria-labelledby="effective-access-title">
      <div className="effective-panel-heading">
        <div>
          <h3 id="effective-access-title">
            <KeyRound size={18} aria-hidden="true" />
            Effective Access
          </h3>
          <p className="effective-panel-sub">
            {query.data?.assignment_count ?? 0} active assignment(s) ·{" "}
            {query.data?.permission_count ?? 0} effective permission(s)
            <Badge tone="neutral">additive-only</Badge>
          </p>
        </div>

        <div className="effective-panel-actions">
          <Button variant="ghost" size="sm" icon={RefreshCw} disabled={query.isFetching} onClick={() => void query.refetch()}>
            Refresh
          </Button>
          <Button variant="secondary" size="sm" icon={Copy} onClick={handleCopyJson}>
            Copy JSON
          </Button>
        </div>
      </div>

      <div className="effective-assignments">
        <strong>Granted via:</strong>
        {assignments.length === 0 ? (
          <span className="institution-type-label">No active assignments</span>
        ) : (
          assignments.map((assignment: EffectiveAssignment) => (
            <span key={assignment.id} className="badge badge-info" title={assignment.role.name}>
              {assignment.role.code}
              {assignment.is_primary ? " · primary" : ""}
              {assignment.academic_unit ? ` · ${assignment.academic_unit.name}` : ""}
            </span>
          ))
        )}
      </div>

      {permissions.length === 0 ? (
        <p className="effective-empty">
          <ShieldCheck size={20} aria-hidden="true" />
          No active permissions. Assign a role to see effective access here.
        </p>
      ) : (
        <div className="effective-groups">
          {groups.map((group) => (
            <section key={group.module} className="effective-group">
              <h4>
                {group.module === "platform" ? "Platform Administration" : "Institution Administration"}
                <span className="role-list-item-count">{group.items.length}</span>
              </h4>

              <ul className="effective-list">
                {group.items.map((permission) => (
                  <li key={permission.code} className="effective-item">
                    <code className="effective-item-code">{permission.code}</code>
                    <span className="effective-item-name">{permission.name}</span>
                    <span className="effective-item-via">
                      {permission.via.map((via, index) => (
                        <span key={index} className="badge badge-neutral" title={viaLabel(via)}>
                          via {via.role_code}
                          {via.scope ? ` @ ${via.scope}` : ""}
                        </span>
                      ))}
                    </span>
                  </li>
                ))}
              </ul>
            </section>
          ))}
        </div>
      )}
    </section>
  );
};

export default EffectiveAccessPanel;
