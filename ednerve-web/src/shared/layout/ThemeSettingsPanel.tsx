import { Check, Monitor, Moon, Palette, Sun } from "lucide-react";
import type { CSSProperties } from "react";
import { useTheme } from "../../contexts/ThemeProvider";
import { ACCENT_PALETTES } from "../../contexts/theme-constants";
import type { ThemePreference } from "../../contexts/ThemeProvider";

const MODES: { value: ThemePreference; label: string; Icon: typeof Sun }[] = [
  { value: "light", label: "Light", Icon: Sun },
  { value: "dark", label: "Dark", Icon: Moon },
  { value: "system", label: "System", Icon: Monitor },
];

export default function ThemeSettingsPanel() {
  const { preference, setTheme, accentPalette, setAccentPalette, accentColor, setCustomAccent } = useTheme();

  return (
    <section className="theme-settings-panel" aria-label="Appearance settings">
      <div className="theme-settings-heading">
        <span className="theme-settings-icon"><Palette size={17} /></span>
        <div><strong>Appearance</strong><small>Personalise your workspace</small></div>
      </div>

      <div className="theme-settings-section">
        <span className="theme-settings-label">Theme mode</span>
        <div className="theme-mode-options" role="group" aria-label="Theme mode">
          {MODES.map(({ value, label, Icon }) => (
            <button key={value} type="button" className={preference === value ? "selected" : ""}
              aria-pressed={preference === value} onClick={() => setTheme(value)}>
              <Icon size={15} /><span>{label}</span>
            </button>
          ))}
        </div>
      </div>

      <div className="theme-settings-section">
        <span className="theme-settings-label">Accent colour</span>
        <div className="theme-palette-grid" role="group" aria-label="Accent colour presets">
          {ACCENT_PALETTES.map((palette) => (
            <button key={palette.id} type="button" className={`theme-palette-swatch ${accentPalette === palette.id ? "selected" : ""}`}
              style={{ "--swatch-color": palette.color } as CSSProperties}
              aria-label={`${palette.label} accent`} aria-pressed={accentPalette === palette.id}
              onClick={() => setAccentPalette(palette.id)}>
              <span className="theme-palette-dot">{accentPalette === palette.id && <Check size={13} />}</span>
              <small>{palette.label}</small>
            </button>
          ))}
        </div>
      </div>

      <div className="theme-settings-section theme-custom-colour">
        <label htmlFor="theme-custom-accent">Custom colour</label>
        <div className="theme-custom-colour-control">
          <input id="theme-custom-accent" type="color" value={accentColor}
            onChange={(event) => setCustomAccent(event.target.value)} aria-label="Choose a custom accent colour" />
          <span>{accentPalette === "custom" ? accentColor.toUpperCase() : "Choose any colour"}</span>
        </div>
      </div>
      <p className="theme-settings-note">Your appearance preferences are saved on this device.</p>
    </section>
  );
}
