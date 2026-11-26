import axios from "axios";

export function getApplicantApiError(error: unknown, fallback: string): string {
  if (axios.isAxiosError(error)) {
    const errors = error.response?.data?.errors as Record<string, string[]> | undefined;
    const first = errors ? Object.values(errors).flat()[0] : undefined;
    return first || error.response?.data?.message || fallback;
  }
  return error instanceof Error ? error.message : fallback;
}
