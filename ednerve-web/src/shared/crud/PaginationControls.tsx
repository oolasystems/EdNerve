import type { HTMLAttributes } from "react";
import { ChevronLeft, ChevronRight } from "lucide-react";

export interface PaginationControlsProps
  extends Omit<HTMLAttributes<HTMLDivElement>, "children"> {
  page: number;
  lastPage: number;
  total: number;
  from: number | null;
  to: number | null;
  loading?: boolean;
  onPageChange: (page: number) => void;
  itemLabel?: string;
}

/** Shared pagination summary and previous/next controls. */
export function PaginationControls({
  page,
  lastPage,
  total,
  from,
  to,
  loading = false,
  onPageChange,
  itemLabel = "records",
  className = "",
  ...containerProps
}: PaginationControlsProps) {
  const handleChange = (next: number) => {
    if (loading || next < 1 || next > lastPage) return;
    onPageChange(next);
  };

  const containerClassName = ["pagination-bar", className]
    .filter(Boolean)
    .join(" ");

  return (
    <div className={containerClassName} {...containerProps}>
      <span aria-live="polite">
        Showing {from ?? 0}–{to ?? 0} of {total} {itemLabel}
      </span>

      <div className="pagination-controls" role="group" aria-label="Pagination">
        <button
          type="button"
          className="pagination-button"
          onClick={() => handleChange(page - 1)}
          disabled={page <= 1 || loading}
          aria-label="Previous page"
        >
          <ChevronLeft size={17} aria-hidden="true" />
        </button>

        <span aria-current="page">
          Page {page} of {lastPage}
        </span>

        <button
          type="button"
          className="pagination-button"
          onClick={() => handleChange(page + 1)}
          disabled={page >= lastPage || loading}
          aria-label="Next page"
        >
          <ChevronRight size={17} aria-hidden="true" />
        </button>
      </div>
    </div>
  );
}

export default PaginationControls;
