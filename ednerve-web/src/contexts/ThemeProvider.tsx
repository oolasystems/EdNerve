import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from "react";
import type { ReactNode } from "react";
import { ACCENT_PALETTES } from "./theme-constants";

export type ThemePreference = "light" | "dark" | "system";
export type AccentPalette = "teal" | "blue" | "violet" | "emerald" | "rose" | "amber" | "custom";


export interface ThemeContextValue {
  preference: ThemePreference;
  resolvedTheme: "light" | "dark";
  accentPalette: AccentPalette;
  accentColor: string;
  setTheme: (value: ThemePreference) => void;
  setAccentPalette: (value: AccentPalette) => void;
  setCustomAccent: (value: string) => void;
  toggle: () => void;
}

const ThemeContext = createContext<ThemeContextValue | undefined>(undefined);
const THEME_KEY = "ednerve-theme";
const ACCENT_KEY = "ednerve-accent-palette";
const CUSTOM_KEY = "ednerve-custom-accent";
const ORDER: ThemePreference[] = ["light", "dark", "system"];
const DEFAULT_CUSTOM = "#0BC8B5";

function systemIsDark(): boolean {
  return typeof window !== "undefined" &&
    window.matchMedia?.("(prefers-color-scheme: dark)").matches;
}

function validHex(value: string): boolean {
  return /^#[0-9a-fA-F]{6}$/.test(value);
}

function readableForeground(hex: string): string {
  const value = hex.replace("#", "");
  const channels = [0, 2, 4].map((index) => parseInt(value.slice(index, index + 2), 16) / 255);
  const linear = channels.map((channel) => channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4);
  const luminance = 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2];
  return luminance > 0.42 ? "#102027" : "#FFFFFF";
}

function readPreference(): ThemePreference {
  if (typeof window === "undefined") return "system";
  const stored = window.localStorage.getItem(THEME_KEY);
  return stored === "light" || stored === "dark" || stored === "system" ? stored : "system";
}

function readAccent(): AccentPalette {
  if (typeof window === "undefined") return "teal";
  const stored = window.localStorage.getItem(ACCENT_KEY);
  return ["teal", "blue", "violet", "emerald", "rose", "amber", "custom"].includes(stored ?? "")
    ? stored as AccentPalette : "teal";
}

export function ThemeProvider({ children }: { children: ReactNode }) {
  const [preference, setPreference] = useState<ThemePreference>(readPreference);
  const [systemDark, setSystemDark] = useState(systemIsDark);
  const [accentPalette, setAccentPaletteState] = useState<AccentPalette>(readAccent);
  const [customAccent, setCustomAccentState] = useState(() => {
    if (typeof window === "undefined") return DEFAULT_CUSTOM;
    const value = window.localStorage.getItem(CUSTOM_KEY) ?? DEFAULT_CUSTOM;
    return validHex(value) ? value : DEFAULT_CUSTOM;
  });
  const resolvedTheme = preference === "system" ? (systemDark ? "dark" : "light") : preference;
  const preset = ACCENT_PALETTES.find((item) => item.id === accentPalette);
  const accentColor = accentPalette === "custom" ? customAccent : (preset?.color ?? ACCENT_PALETTES[0].color);

  useEffect(() => {
    const element = document.documentElement;
    element.classList.toggle("dark", resolvedTheme === "dark");
    element.dataset.theme = resolvedTheme;
    element.dataset.accent = accentPalette;
    element.style.colorScheme = resolvedTheme;
    element.style.setProperty("--brand-teal", accentColor);
    element.style.setProperty("--brand-teal-hover", `color-mix(in srgb, ${accentColor} 82%, white)`);
    element.style.setProperty("--brand-teal-light", `color-mix(in srgb, ${accentColor} 15%, transparent)`);
    element.style.setProperty("--primary", accentColor);
    element.style.setProperty("--primary-foreground", readableForeground(accentColor));
    element.style.setProperty("--text-accent", accentColor);
    element.style.setProperty("--ring", accentColor);
    element.style.setProperty("--accent", `color-mix(in srgb, ${accentColor} 12%, var(--background))`);
    element.style.setProperty("--accent-foreground", accentColor);
    element.style.setProperty("--surface-accent", `color-mix(in srgb, ${accentColor} 8%, var(--surface))`);
    element.style.setProperty("--border-accent", `color-mix(in srgb, ${accentColor} 48%, var(--border))`);
    element.style.setProperty("--gradient-accent", `linear-gradient(110deg, color-mix(in srgb, ${accentColor} 55%, var(--surface)), ${accentColor}, color-mix(in srgb, ${accentColor} 65%, white))`);
  }, [resolvedTheme, accentPalette, accentColor]);

  useEffect(() => {
    if (preference !== "system") return;
    const mediaQuery = window.matchMedia("(prefers-color-scheme: dark)");
    const listener = (event: MediaQueryListEvent) => {
      setSystemDark(event.matches);
    };
    mediaQuery.addEventListener("change", listener);
    return () => mediaQuery.removeEventListener("change", listener);
  }, [preference]);

  const setTheme = useCallback((value: ThemePreference) => {
    setPreference(value);
    window.localStorage.setItem(THEME_KEY, value);
  }, []);

  const setAccentPalette = useCallback((value: AccentPalette) => {
    setAccentPaletteState(value);
    window.localStorage.setItem(ACCENT_KEY, value);
  }, []);

  const setCustomAccent = useCallback((value: string) => {
    if (!validHex(value)) return;
    setCustomAccentState(value);
    setAccentPaletteState("custom");
    window.localStorage.setItem(CUSTOM_KEY, value);
    window.localStorage.setItem(ACCENT_KEY, "custom");
  }, []);

  const toggle = useCallback(() => {
    setPreference((current) => {
      const next = ORDER[(ORDER.indexOf(current) + 1) % ORDER.length];
      window.localStorage.setItem(THEME_KEY, next);
      return next;
    });
  }, []);

  const value = useMemo(() => ({
    preference, resolvedTheme, accentPalette, accentColor,
    setTheme, setAccentPalette, setCustomAccent, toggle,
  }), [preference, resolvedTheme, accentPalette, accentColor, setTheme, setAccentPalette, setCustomAccent, toggle]);

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

// eslint-disable-next-line react-refresh/only-export-components
export function useTheme(): ThemeContextValue {
  const context = useContext(ThemeContext);
  if (!context) throw new Error("useTheme must be used inside <ThemeProvider>");
  return context;
}
