import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from "react";
import type { ReactNode } from "react";

export type ThemePreference = "light" | "dark" | "system";

export interface ThemeContextValue {
  /** What the user chose (what drives the toggle icon). */
  preference: ThemePreference;
  /** What is actually applied ('system' resolved to concrete mode). */
  resolvedTheme: "light" | "dark";
  setTheme: (value: ThemePreference) => void;
  /** Cycle: light → dark → system → light … */
  toggle: () => void;
}

const ThemeContext = createContext<ThemeContextValue | undefined>(undefined);

const STORAGE_KEY = "ednerve-theme";
const ORDER: ThemePreference[] = ["light", "dark", "system"];

function systemIsDark(): boolean {
  return typeof window !== "undefined" &&
    window.matchMedia?.("(prefers-color-scheme: dark)").matches;
}

function resolveTheme(preference: ThemePreference): "light" | "dark" {
  return preference === "system"
    ? systemIsDark()
      ? "dark"
      : "light"
    : preference;
}

export function ThemeProvider({ children }: { children: ReactNode }) {
  const [preference, setPreference] = useState<ThemePreference>(() => {
    if (typeof window === "undefined") return "system";
    const stored = window.localStorage.getItem(STORAGE_KEY);
    return stored === "light" || stored === "dark" || stored === "system"
      ? stored
      : "system";
  });

  const resolvedTheme = resolveTheme(preference);

  useEffect(() => {
    document.documentElement.classList.toggle(
      "dark",
      resolvedTheme === "dark"
    );
    document.documentElement.style.colorScheme = resolvedTheme;
  }, [resolvedTheme]);

  // Live-follow OS changes when in system mode.
  useEffect(() => {
    if (preference !== "system") return;

    const mediaQuery = window.matchMedia("(prefers-color-scheme: dark)");
    const listener = (event: MediaQueryListEvent) => {
      document.documentElement.classList.toggle("dark", event.matches);
      document.documentElement.style.colorScheme = event.matches ? "dark" : "light";
    };

    mediaQuery.addEventListener("change", listener);
    return () => mediaQuery.removeEventListener("change", listener);
  }, [preference]);

  const setTheme = useCallback((value: ThemePreference) => {
    setPreference(value);
    window.localStorage.setItem(STORAGE_KEY, value);
  }, []);

  const toggle = useCallback(() => {
    setPreference((current) => {
      const index = ORDER.indexOf(current);
      const next = ORDER[(index + 1) % ORDER.length];
      window.localStorage.setItem(STORAGE_KEY, next);
      return next;
    });
  }, []);

  const value = useMemo(
    () => ({ preference, resolvedTheme, setTheme, toggle }),
    [preference, resolvedTheme, setTheme, toggle]
  );

  return (
    <ThemeContext.Provider value={value}>
      {children}
    </ThemeContext.Provider>
  );
}

// This hook belongs to the context module; it is not a component export.
// eslint-disable-next-line react-refresh/only-export-components
export function useTheme(): ThemeContextValue {
  const context = useContext(ThemeContext);

  if (!context) {
    throw new Error("useTheme must be used inside <ThemeProvider>");
  }

  return context;
}
