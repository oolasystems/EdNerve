
import { useEffect } from "react";

/**
 * Enables the applicant portal to render inside a university website.
 * The parent owns the outer layout and validates iframe resize messages.
 */
export default function EmbeddedFrameBridge() {
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);

    if (params.get("embed") === "1") {
      window.sessionStorage.setItem("ednerve_embed_mode", "1");
    }

    const embedded =
      window.sessionStorage.getItem("ednerve_embed_mode") === "1";

    if (!embedded || window.parent === window) return;

    // Determine the trusted parent origin from the iframe referrer.
    let targetOrigin: string;

    try {
      if (!document.referrer) return;
      targetOrigin = new URL(document.referrer).origin;
    } catch {
      return;
    }

    document.documentElement.classList.add("ednerve-embedded");
    document.body.classList.add("ednerve-embedded");

    const reportHeight = () => {
      const height = Math.max(
        document.documentElement.scrollHeight,
        document.body.scrollHeight,
        document.documentElement.offsetHeight,
        document.body.offsetHeight
      );

      window.parent.postMessage(
        {
          type: "EDNERVE_FRAME_RESIZE",
          height: Math.ceil(height),
        },
        targetOrigin
      );
    };

    const observer = new ResizeObserver(reportHeight);

    observer.observe(document.documentElement);
    observer.observe(document.body);

    window.addEventListener("load", reportHeight);
    window.addEventListener("resize", reportHeight);

    reportHeight();

    return () => {
      observer.disconnect();

      window.removeEventListener("load", reportHeight);
      window.removeEventListener("resize", reportHeight);

      document.documentElement.classList.remove("ednerve-embedded");
      document.body.classList.remove("ednerve-embedded");
    };
  }, []);

  return null;
}
