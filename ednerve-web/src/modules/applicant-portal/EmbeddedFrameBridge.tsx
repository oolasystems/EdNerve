import { useEffect } from "react";

/**
 * Enables the hosted React app to render cleanly inside a university portal iframe.
 * The parent page validates event.origin and event.source before applying height.
 */
export default function EmbeddedFrameBridge() {
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get("embed") === "1") {
      window.sessionStorage.setItem("ednerve_embed_mode", "1");
    }

    const embedded = window.sessionStorage.getItem("ednerve_embed_mode") === "1";
    if (!embedded || window.parent === window) return;

    document.documentElement.classList.add("ednerve-embedded");
    document.body.classList.add("ednerve-embedded");

    const reportHeight = () => {
      const height = Math.max(
        document.documentElement.scrollHeight,
        document.body.scrollHeight,
        document.documentElement.offsetHeight,
        document.body.offsetHeight,
      );

      window.parent.postMessage(
        { type: "EDNERVE_FRAME_RESIZE", height: Math.ceil(height) },
        "*",
      );
    };

    reportHeight();

    const observer = new ResizeObserver(reportHeight);
    observer.observe(document.documentElement);
    observer.observe(document.body);

    window.addEventListener("load", reportHeight);
    window.addEventListener("resize", reportHeight);

    return () => {
      observer.disconnect();
      window.removeEventListener("load", reportHeight);
      window.removeEventListener("resize", reportHeight);
    };
  }, []);

  return null;
}
