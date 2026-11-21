/**
 * Route → navigation resolution helpers.
 *
 * Areas and items own absolute routes in the navigation config;
 * these helpers map the current location onto them. Deep links such as
 * /platform/institutions/12 resolve to the Institution Directory item via
 * longest-prefix matching.
 */

import {
  platformAdminNavigation,
} from "./platform-admin.navigation";
import {
  institutionNavigation,
} from "./institution.navigation";

import type {
  NavigationAreaConfig,
  NavigationItem,
} from "./navigation.types";

export const platformAreas = platformAdminNavigation;
export const institutionAreas = institutionNavigation;

const allAreas = [...platformAdminNavigation, ...institutionNavigation];

export interface ResolvedNavigation {
  area: NavigationAreaConfig;
  item: NavigationItem;
}

function areaMatches(pathname: string, area: NavigationAreaConfig): boolean {
  return (
    pathname === area.route ||
    pathname.startsWith(`${area.route}/`) ||
    area.sections.some((section) =>
      section.items.some(
        (item) =>
          pathname === item.route || pathname.startsWith(`${item.route}/`)
      )
    )
  );
}

/** Find the navigation area owning a path (longest area-route prefix wins). */
export function findAreaByPath(
  pathname: string,
  areas: NavigationAreaConfig[] = allAreas
): NavigationAreaConfig | undefined {
  const matches = areas.filter((area) => areaMatches(pathname, area));

  if (matches.length <= 1) return matches[0];

  return matches.reduce((best, area) =>
    deepestRoute([area, best]) === area ? area : best
  );
}

function deepestRoute<T extends { route: string }>(candidates: [T, T]): T {
  return candidates[0].route.length >= candidates[1].route.length
    ? candidates[0]
    : candidates[1];
}

/** Find the navigation item owning a path (longest item-route prefix wins). */
export function findItemByPath(
  pathname: string,
  areas: NavigationAreaConfig[] = allAreas
): ResolvedNavigation | undefined {
  let best: ResolvedNavigation | undefined;

  for (const area of areas) {
    for (const section of area.sections) {
      for (const item of section.items) {
        const matches =
          pathname === item.route || pathname.startsWith(`${item.route}/`);

        if (matches && (!best || item.route.length > best.item.route.length)) {
          best = { area, item };
        }
      }
    }
  }

  return best;
}

/** All items across all areas (used for placeholder resolution). */
export function findItemByRoute(route: string): ResolvedNavigation | undefined {
  return findItemByPath(route);
}

/** Convenience: which workspace owns this path, if any. */
export function workspaceForPath(
  pathname: string
): "platform-admin" | "institution-admin" | undefined {
  if (pathname === "/login") return undefined;
  if (pathname === "/platform" || pathname.startsWith("/platform/"))
    return "platform-admin";
  if (pathname === "/institution" || pathname.startsWith("/institution/"))
    return "institution-admin";
  return undefined;
}
