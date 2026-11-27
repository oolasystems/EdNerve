import type { HTMLAttributes, ReactNode } from "react";
import { RefreshCw, AlertCircle } from "lucide-react";

export interface DataTableColumn<T> {
  key: string;
  header: ReactNode;
  /** Render the cell for a row. */
  render: (row: T) => ReactNode;
  /** Optional classes applied to the <th> and <td>. */
  className?: string;
  align?: "left" | "center" | "right";
}

export interface DataTableProps<T>
  extends Omit<HTMLAttributes<HTMLDivElement>, "children"> {
  columns: DataTableColumn<T>[];
  rows: T[];
  rowKey: (row: T) => string | number;
  loading?: boolean;
  error?: string | null;
  emptyMessage?: string;
  tableClassName?: string;
}

/** Shared, presentation-focused table for CRUD and listing screens. */
export function DataTable<T>({
  columns,
  rows,
  rowKey,
  loading = false,
  error = null,
  emptyMessage = "No records found.",
  className = "",
  tableClassName = "",
  ...containerProps
}: DataTableProps<T>) {
  const columnCount = Math.max(columns.length, 1);
  const containerClassName = ["data-table-container", className]
    .filter(Boolean)
    .join(" ");
  const resolvedTableClassName = ["data-table", tableClassName]
    .filter(Boolean)
    .join(" ");

  return (
    <div className={containerClassName} {...containerProps}>
      <table className={resolvedTableClassName} aria-busy={loading}>
        <thead>
          <tr>
            {columns.map((column) => (
              <th
                key={column.key}
                className={[column.className, column.align ? `data-table-align-${column.align}` : ""]
                  .filter(Boolean)
                  .join(" ")}
                scope="col"
              >
                {column.header}
              </th>
            ))}
          </tr>
        </thead>

        <tbody>
          {loading ? (
            <tr>
              <td colSpan={columnCount} className="data-table-message" role="status" aria-live="polite">
                <RefreshCw size={22} className="spinner" aria-hidden="true" />
                <span>Loading records...</span>
              </td>
            </tr>
          ) : error ? (
            <tr>
              <td colSpan={columnCount} className="data-table-message" role="alert">
                <AlertCircle size={22} aria-hidden="true" />
                <span>{error}</span>
              </td>
            </tr>
          ) : rows.length === 0 ? (
            <tr>
              <td colSpan={columnCount} className="data-table-message" role="status">
                <span>{emptyMessage}</span>
              </td>
            </tr>
          ) : (
            rows.map((row) => (
              <tr key={rowKey(row)}>
                {columns.map((column) => (
                  <td
                    key={column.key}
                    className={[column.className, column.align ? `data-table-align-${column.align}` : ""]
                      .filter(Boolean)
                      .join(" ")}
                  >
                    {column.render(row)}
                  </td>
                ))}
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  );
}

export default DataTable;
