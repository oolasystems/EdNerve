import { ChevronLeft, ChevronRight } from "lucide-react";

export interface PaginationControlsProps {
  page: number;
  lastPage: number;
  total: number;
  from: number | null;
  to: number | null;
  loading?: boolean;
  onPageChange: (page: number) => void;
  itemLabel?: string;
}

export function PaginationControls({
  page,
  lastPage,
  total,
  from,
  to,
  loading = false,
  onPageChange,
  itemLabel = "records",
}: PaginationControlsProps) {
  const handleChange = (next: number) => {
    if (next < 1 || next > lastPage) return;
    onPageChange(next);
  };

  return (
    <div className="pagination-bar">
      <span>
        Showing {from ?? 0}–{to ?? 0} of {total} {itemLabel}
      </span>

      <div className="pagination-controls">
        <button
          type="button"
          className="pagination-button"
          onClick={() => handleChange(page - 1)}
          disabled={page <= 1 || loading}
          aria-label="Previous page"
        >
          <ChevronLeft size={17} />
        </button>

        <span>
          Page {page} of {lastPage}
        </span>

        <button
          type="button"
          className="pagination-button"
          onClick={() => handleChange(page + 1)}
          disabled={page >= lastPage || loading}
          aria-label="Next page"
        >
          <ChevronRight size={17} />
        </button>
      </div>
    </div>
  );
}

export default PaginationControls;
