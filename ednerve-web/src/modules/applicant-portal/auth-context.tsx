import {
  createContext, useCallback, useContext, useEffect, useMemo, useState,
  type ReactNode,
} from "react";
import { applicantApi, applicantToken, applicantInstitutionCode, type ApplicantProfile } from "./api";

interface ApplicantAuthContextValue {
  applicant: ApplicantProfile | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  login: (payload: { institution_code: string; email: string; password: string }) => Promise<void>;
  logout: () => Promise<void>;
  refresh: () => Promise<void>;
}

const ApplicantAuthContext = createContext<ApplicantAuthContextValue | null>(null);

export function ApplicantAuthProvider({ children }: { children: ReactNode }) {
  const [applicant, setApplicant] = useState<ApplicantProfile | null>(null);
  const [isLoading, setIsLoading] = useState(() => Boolean(applicantToken.get()));

  const refresh = useCallback(async () => {
    if (!applicantToken.get()) {
      setApplicant(null);
      setIsLoading(false);
      return;
    }
    try {
      setApplicant(await applicantApi.me());
    } catch {
      applicantToken.clear();
      setApplicant(null);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    if (!applicantToken.get()) return;
    let active = true;
    applicantApi.me().then(profile => { if (active) setApplicant(profile); })
      .catch(() => { if (active) { applicantToken.clear(); setApplicant(null); } })
      .finally(() => { if (active) setIsLoading(false); });
    return () => { active = false; };
  }, []);

  const login = useCallback(async (payload: {
    institution_code: string; email: string; password: string;
  }) => {
    const result = await applicantApi.login(payload);
    if (!result.access_token || !result.applicant) {
      throw new Error("Unexpected applicant sign-in response.");
    }
    applicantToken.set(result.access_token);
    applicantInstitutionCode.set(payload.institution_code);
    setApplicant(result.applicant);
  }, []);

  const logout = useCallback(async () => {
    try {
      await applicantApi.logout();
    } finally {
      applicantToken.clear();
      applicantInstitutionCode.clear();
      setApplicant(null);
    }
  }, []);

  const value = useMemo(() => ({
    applicant, isLoading, isAuthenticated: Boolean(applicant),
    login, logout, refresh,
  }), [applicant, isLoading, login, logout, refresh]);

  return <ApplicantAuthContext.Provider value={value}>{children}</ApplicantAuthContext.Provider>;
}

// This hook is intentionally exported beside its provider; it is not a component.
// eslint-disable-next-line react-refresh/only-export-components
export function useApplicantAuth() {
  const context = useContext(ApplicantAuthContext);
  if (!context) throw new Error("useApplicantAuth must be used within ApplicantAuthProvider.");
  return context;
}
