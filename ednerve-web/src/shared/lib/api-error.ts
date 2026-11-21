/** Shared API error extraction.
 * Backend validation failures come as { message, errors: { field: string[] } }.
 */

export interface ApiErrorShape {
  response?: {
    data?: {
      message?: string;
      errors?: Record<string, string[]>;
    };
  };
}

export function getApiErrorMessage(
  error: unknown,
  fallback = "Something went wrong. Please try again."
): string {
  const err = error as ApiErrorShape | undefined;
  const data = err?.response?.data;

  if (data?.errors) {
    return Object.values(data.errors).flat().join(" ");
  }

  return data?.message || fallback;
}
