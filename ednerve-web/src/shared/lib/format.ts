/** Shared formatting helpers (previously duplicated across pages). */

export function formatInstitutionType(type: string): string {
  if (!type) return "Unknown";
  return type
    .replaceAll("_", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export function formatDate(value: string | null | undefined): string {
  if (!value) return "—";
  return new Date(value).toLocaleDateString("en-IN", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
}

export function joinLocation(parts: Array<string | null | undefined>): string {
  return parts.filter(Boolean).join(", ");
}
