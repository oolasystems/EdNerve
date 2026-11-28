
import {
  LayoutDashboard,
  University,
  Building2,
  PlusCircle,
  Users,
  UserCog,
  ShieldCheck,
  KeyRound,
  GraduationCap,
  Settings,
  ClipboardList,
  Activity,
  Plus,
} from "lucide-react";

import type { NavigationAreaConfig } from "./navigation.types";

export const platformAdminNavigation: NavigationAreaConfig[] = [
  // =====================================================
  // 1. OVERVIEW
  // =====================================================
  {
    id: "overview",
    label: "Overview",
    icon: LayoutDashboard,
    route: "/platform/dashboard",
    accountTypes: ["platform"],
    defaultItemId: "dashboard",

    sections: [
      {
        id: "overview",
        title: "DASHBOARD",

        items: [
          {
            id: "dashboard",
            label: "Dashboard",
            icon: LayoutDashboard,
            route: "/platform/dashboard",
            description: 
              "Review platform-wide activity, operational indicators, and a consolidated overview of OolaEdNerve services.",
            accountTypes: ["platform"],
            permissions: ["platform.dashboard.view"],
          },
        ],
      },
    ],
  },

  // =====================================================
  // 2. INSTITUTION MANAGEMENT
  // =====================================================
  {
    id: "institutions",
    label: "Institution Management",
    icon: University,
    route: "/platform/institutions",
    accountTypes: ["platform"],
    defaultItemId: "institution-directory",

    sections: [
      {
        id: "institutions",
        title: "INSTITUTIONS",

        items: [
          {
            id: "institution-directory",
            label: "Institution Directory",
            icon: Building2,
            route: "/platform/institutions",
            description:
              
              "Browse registered universities and colleges, inspect institutional profiles, and manage their platform registration records.",
            accountTypes: ["platform"],
            permissions: ["platform.institutions.view"],

            action: {
              label: "Add Institution",
              targetItemId: "add-institution",
              icon: Plus,
            },
          },

          {
            id: "add-institution",
            label: "Add New Institution",
            icon: PlusCircle,
            route: "/platform/institutions/create",
            description:
              
              "Register a university or college, capture its institutional details, and initiate platform onboarding.",
            accountTypes: ["platform"],
            permissions: ["platform.institutions.create"],
          },
        ],
      },
    ],
  },

  // =====================================================
  // 3. CONFIGURATION
  // =====================================================
  {
    id: "configuration",
    label: "Configuration",
    icon: Settings,
    route: "/platform/configuration",
    accountTypes: ["platform"],
    defaultItemId: "academic-settings",

    sections: [
      {
        id: "academic",
        title: "ACADEMIC CONFIGURATION",

        items: [
          {
            id: "academic-settings",
            label: "Academic Settings",
            icon: GraduationCap,
            route: "/platform/configuration/academic",
            description: 
              "Configure platform-level academic defaults and shared settings used by participating educational institutions.",
            accountTypes: ["platform"],
            permissions: ["platform.configuration.academic"],
          },
        ],
      },

      {
        id: "system",
        title: "SYSTEM",

        items: [
          {
            id: "system-settings",
            label: "System Settings",
            icon: Settings,
            route: "/platform/configuration/system",
            description: 
              "Manage global application settings, platform behaviour, and shared operational configuration values across institutions.",
            accountTypes: ["platform"],
            permissions: ["platform.configuration.system"],
          },
        ],
      },
    ],
  },

  // =====================================================
  // 4. USER MANAGEMENT
  // =====================================================
  {
    id: "users",
    label: "User Management",
    icon: Users,
    route: "/platform/users",
    accountTypes: ["platform"],
    defaultItemId: "all-users",

    sections: [
      {
        id: "users",
        title: "USER MANAGEMENT",

        items: [
          {
            id: "all-users",
            label: "All Users",
            icon: Users,
            route: "/platform/users",
            description: 
              "Search and review user accounts across institutions, inspect account status, and manage platform-wide user records.",
            accountTypes: ["platform"],
            permissions: ["platform.users.view"],
          },

          {
            id: "platform-users",
            label: "Platform Users",
            icon: UserCog,
            route: "/platform/users/platform",
            description: 
              "Create and manage Platform Admin accounts, assign access, and review privileged account status.",
            accountTypes: ["platform"],
            permissions: ["platform.users.manage"],
          },
        ],
      },
    ],
  },

  // =====================================================
  // 5. ACCESS MANAGEMENT
  // =====================================================
  {
    id: "access",
    label: "Access Management",
    icon: ShieldCheck,
    route: "/platform/access",
    accountTypes: ["platform"],
    defaultItemId: "roles-permissions",

    sections: [
      {
        id: "access-control",
        title: "ACCESS CONTROL",

        items: [
          {
            id: "permissions",
            label: "Permissions",
            icon: KeyRound,
            route: "/platform/access/permissions",
            description: 
              "Browse the platform permission catalogue and review the access codes available to application roles.",
            accountTypes: ["platform"],
            permissions: ["platform.permissions.view"],
          },

          {
            id: "roles-permissions",
            label: "Roles & Permissions",
            icon: ShieldCheck,
            route: "/platform/access/roles",
            description: 
              "Configure platform roles and associate the permissions required for their authorised responsibilities.",
            accountTypes: ["platform"],
            permissions: ["platform.roles.view"],
          },

          {
            id: "role-assignments",
            label: "Role Assignments",
            icon: KeyRound,
            route: "/platform/access/assignments",
            description: 
              "Review and manage role assignments granted to users across the OolaEdNerve platform.",
            accountTypes: ["platform"],
            permissions: ["platform.role-assignments.view"],
          },
        ],
      },
    ],
  },

  // =====================================================
  // 6. ADMINISTRATION
  // =====================================================
  {
    id: "administration",
    label: "Administration",
    icon: ClipboardList,
    route: "/platform/administration",
    accountTypes: ["platform"],
    defaultItemId: "system-health",

    sections: [
      {
        id: "administration",
        title: "ADMINISTRATION",

        items: [
          {
            id: "system-health",
            label: "System Health",
            icon: Activity,
            route: "/platform/administration/system-health",
            description: 
              "Monitor application availability, service status, operational indicators, and reported platform health conditions.",
            accountTypes: ["platform"],
            permissions: ["platform.system-health.view"],
          },
          {
            id: "audit-logs",
            label: "Audit Logs",
            icon: ClipboardList,
            route: "/platform/administration/audit-logs",
            description:
              
              "Review recorded platform activities, investigate administrative actions, and trace important security-related events.",
            accountTypes: ["platform"],
            permissions: ["platform.audit-logs.view"],
          },
        ],
      },
    ],
  },
];
