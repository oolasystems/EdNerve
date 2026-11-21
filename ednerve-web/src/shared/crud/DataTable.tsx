import type { ReactNode } from "react";
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

export interface DataTableProps<T> {
  columns: DataTableColumn<T>[];
  rows: T[];
  rowKey: (row: T) => string | number;
  loading?: boolean;
  error?: string | null;
  emptyMessage?: string;
}

export function DataTable<T>({
  columns,
  rows,
  rowKey,
  loading = false,
  error = null,
  emptyMessage = "No records found.",
}: DataTableProps<T>) {
  const columnCount = columns.length;

  return (
    <div className="data-table-container">
      <table className="data-table">
        <thead>
          <tr>
            {columns.map((column) => (
              <th
                key={column.key}
                className={column.className}
                style={{ textAlign: column.align }}
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
              <td colSpan={columnCount} className="data-table-message">
                <RefreshCw size={22} className="spinner" aria-hidden="true" />
                <span>Loading records...</span>
              </td>
            </tr>
          ) : error ? (
            <tr>
              <td colSpan={columnCount} className="data-table-message">
                <AlertCircle size={22} aria-hidden="true" />
                <span>{error}</span>
              </td>
            </tr>
          ) : rows.length === 0 ? (
            <tr>
              <td colSpan={columnCount} className="data-table-message">
                <span>{emptyMessage}</span>
              </td>
            </tr>
          ) : (
            rows.map((row) => (
              <tr key={rowKey(row)}>
                {columns.map((column) => (
                  <td
                    key={column.key}
                    className={column.className}
                    style={{ textAlign: column.align }}
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
