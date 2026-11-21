/**
 * Workspace registry.
 *
 * Layouts are organized around major *workspaces* (e.g. Platform
 * Administration, Institution Administration) — not around individual
 * roles. Multiple roles may share a workspace; RBAC gates areas,
 * screens and actions inside it.
 */

import type { LucideIcon } from "lucide-react";
import { ShieldCheck, University } from "lucide-react";

export type WorkspaceId = "platform-admin" | "institution-admin";

export interface WorkspaceConfig {
  id: WorkspaceId;
  label: string;
  description: string;
  icon: LucideIcon;
  /** Route prefix that owns this workspace. */
  routePrefix: string;
  /** Default screen when the workspace root is visited. */
  defaultRoute: string;
}

export const workspaces: WorkspaceConfig[] = [
  {
    id: "platform-admin",
    label: "Platform Administration",
    description: "Operate and oversee the OolaEdNerve platform.",
    icon: ShieldCheck,
    routePrefix: "/platform",
    defaultRoute: "/platform/dashboard",
  },
  {
    id: "institution-admin",
    label: "Institution Administration",
    description: "Run the institution: organization, users and roles.",
    icon: University,
    routePrefix: "/institution",
    defaultRoute: "/institution/dashboard",
  },
];

export function isWorkspaceEnabled(id: WorkspaceId): boolean {
  return id === "platform-admin" || id === "institution-admin";
}
