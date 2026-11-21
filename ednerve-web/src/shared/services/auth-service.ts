import apiClient from "../lib/api-client";
import { authToken } from "../lib/auth-token";

import type {
  AuthUser,
  LoginPayload,
  LoginResult,
  LoginResponse,
  CurrentUserResponse,
  LogoutResponse,
} from "../types/auth";

/**
 * The web portal serves both workspaces.
 * - platform accounts must be platform-scoped (institution_id === null)
 * - institution accounts must carry an institution id
 * Anything else is not configured for this portal.
 */
function assertSupportedAccount(user: AuthUser): void {
  const supported =
    (user.account_type === "platform" && user.institution_id === null) ||
    (user.account_type === "institution" && user.institution_id !== null);

  if (!supported) {
    throw new Error(
      "Access denied. This account is not configured for the OolaEdNerve portal."
    );
  }
}

export const authService = {

  // Login — may return either the authenticated session or an MFA challenge.
  async login(payload: LoginPayload): Promise<LoginResult> {
    const response = await apiClient.post<LoginResponse>(
      "/auth/login",
      {
        ...payload,
        device_name: "ednerve-web",
      }
    );

    const data = response.data;

    if (data.mfa_required && data.mfa_token) {
      return { kind: "mfa_required", mfaToken: data.mfa_token };
    }

    if (!data.token || !data.user) {
      throw new Error("Unexpected sign-in response from the server.");
    }

    const { token, user } = data;

    assertSupportedAccount(user);

    authToken.set(token);

    return { kind: "authenticated", user };
  },

  // Complete an MFA challenge and create the session.
  async completeMfa(mfaToken: string, code: string): Promise<AuthUser> {
    const response = await apiClient.post<LoginResponse>(
      "/auth/mfa/challenge",
      { mfa_token: mfaToken, code }
    );

    const { token, user } = response.data;

    if (!token || !user) {
      throw new Error("Unexpected MFA response from the server.");
    }

    assertSupportedAccount(user);

    authToken.set(token);

    return user;
  },

  // Get authenticated user
  async getCurrentUser(): Promise<AuthUser> {
    const response = await apiClient.get<CurrentUserResponse>(
      "/auth/me"
    );

    const user = response.data.user;

    assertSupportedAccount(user);

    return user;
  },

  // Logout
  async logout(): Promise<string> {
    try {
      const response = await apiClient.post<LogoutResponse>(
        "/auth/logout"
      );

      return response.data.message;
    } finally {
      authToken.clear();
    }
  },

  // Check authentication
  isAuthenticated(): boolean {
    return authToken.exists();
  },

};