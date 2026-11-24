import { useCallback, useMemo, useState } from "react";
import {
  GraduationCap,
  Library,
  Network,
  Plus,
  Pencil,
  Trash2,
} from "lucide-react";
import { useQuery, useQueryClient, useMutation } from "@tanstack/react-query";
import { toast } from "sonner";

import { institutionService } from "../api";
import "../institution.css";
import { ACADEMIC_UNIT_TYPES } from "../types";
import type {
  AcademicUnit,
  AcademicUnitType,
  AcademicUnitStatus,
  Faculty,
  School,
} from "../types";

import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin } from "../../../shared/lib/rbac";
import { getApiErrorMessage } from "../../../shared/lib/api-error";
import { useDebouncedValue } from "../../../shared/lib/hooks/useDebouncedValue";

import {
  DataTable,
} from "../../../shared/crud";
import type { DataTableColumn } from "../../../shared/crud";
import {
  Alert,
  Badge,
  Button,
  FormField,
  Input,
  Modal,
  SearchInput,
  StatusBadge,
} from "../../../shared/ui";
import { Search } from "lucide-react";

type Tab = "faculties" | "schools" | "units";

const emptyFaculty = { code: "", name: "", status: "active" as AcademicUnitStatus };
const emptySchool = {
  code: "",
  name: "",
  faculty_id: "" as string | number,
  status: "active" as AcademicUnitStatus,
};
const EMPTY_LIST: never[] = [];

const emptyUnit = {
  code: "",
  name: "",
  unit_type: "department" as AcademicUnitType,
  faculty_id: "" as string | number,
  school_id: "" as string | number,
  status: "active" as AcademicUnitStatus,
};

const HIERARCHY_STYLE: React.CSSProperties = {
  color: "var(--text-secondary)",
  fontSize: "var(--font-size-xs)",
};

const AcademicUnitsPage = () => {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const canManage = isInstitutionAdmin(user);

  const [tab, setTab] = useState<Tab>("faculties");
  const [searchInput, setSearchInput] = useState("");
  const debouncedSearch = useDebouncedValue(searchInput.trim(), 300);

  const facultiesQuery = useQuery({
    queryKey: ["institution", "faculties"],
    queryFn: () => institutionService.getFaculties(),
  });
  const schoolsQuery = useQuery({
    queryKey: ["institution", "schools"],
    queryFn: () => institutionService.getSchools(),
  });
  const unitsQuery = useQuery({
    queryKey: ["institution", "academic-units"],
    queryFn: () => institutionService.getAcademicUnits(),
  });

  const faculties = facultiesQuery.data?.faculties ?? EMPTY_LIST;
  const schools = schoolsQuery.data?.schools ?? EMPTY_LIST;
  const units = unitsQuery.data?.academic_units ?? EMPTY_LIST;

  const unitSchoolById = useMemo(
    () => new Map(schools.map((s) => [s.id, s])),
    [schools]
  );
  void unitSchoolById;

  const filterList = useCallback(
    <T extends { name: string; code: string }>(rows: T[]) => {
      const term = debouncedSearch.toLowerCase();
      return rows.filter(
        (row) =>
          !term ||
          row.name.toLowerCase().includes(term) ||
          row.code.toLowerCase().includes(term)
      );
    },
    [debouncedSearch]
  );

  const filteredFaculties = useMemo(() => filterList(faculties), [faculties, filterList]);
  const filteredSchools = useMemo(() => filterList(schools), [schools, filterList]);
  const filteredUnits = useMemo(() => filterList(units), [units, filterList]);

  // ---------- Modal state for the three entity types ----------
  const [modalOpen, setModalOpen] = useState(false);
  const [modalMode, setModalMode] = useState<Tab>("faculties");

  const [editingFaculty, setEditingFaculty] = useState<Faculty | null>(null);
  const [facultyForm, setFacultyForm] = useState(emptyFaculty);
  const [facultyError, setFacultyError] = useState("");

  const [editingSchool, setEditingSchool] = useState<School | null>(null);
  const [schoolForm, setSchoolForm] = useState(emptySchool);
  const [schoolError, setSchoolError] = useState("");

  const [editingUnit, setEditingUnit] = useState<AcademicUnit | null>(null);
  const [unitForm, setUnitForm] = useState(emptyUnit);
  const [unitError, setUnitError] = useState("");

  const setFormError = (value: string) => {
    if (modalMode === "faculties") setFacultyError(value);
    else if (modalMode === "schools") setSchoolError(value);
    else setUnitError(value);
  };

  // ----- Save mutations -----
  const facultySave = useMutation({
    mutationFn: async () => {
      const payload = {
        code: facultyForm.code.trim(),
        name: facultyForm.name.trim(),
        status: facultyForm.status,
      };
      if (editingFaculty) {
        return institutionService.updateFaculty(editingFaculty.id, payload);
      }
      return institutionService.createFaculty(payload);
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["institution", "faculties"] });
      toast.success(editingFaculty ? "Faculty updated." : "Faculty created.");
      setModalOpen(false);
    },
    onError: (error) =>
      setFacultyError(getApiErrorMessage(error, "Unable to save faculty.")),
  });

  const schoolSave = useMutation({
    mutationFn: async () => {
      const payload = {
        code: schoolForm.code.trim(),
        name: schoolForm.name.trim(),
        faculty_id:
          schoolForm.faculty_id === "" ? null : Number(schoolForm.faculty_id),
        status: schoolForm.status,
      };
      if (editingSchool) {
        return institutionService.updateSchool(editingSchool.id, payload);
      }
      return institutionService.createSchool(payload);
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["institution", "schools"] });
      toast.success(editingSchool ? "School updated." : "School created.");
      setModalOpen(false);
    },
    onError: (error) =>
      setSchoolError(getApiErrorMessage(error, "Unable to save school.")),
  });

  const unitSave = useMutation({
    mutationFn: async () => {
      const payload = {
        code: unitForm.code.trim(),
        name: unitForm.name.trim(),
        unit_type: unitForm.unit_type,
        faculty_id:
          unitForm.faculty_id === "" ? null : Number(unitForm.faculty_id),
        school_id:
          unitForm.school_id === "" ? null : Number(unitForm.school_id),
        status: unitForm.status,
      };
      if (editingUnit) {
        return institutionService.updateAcademicUnit(editingUnit.id, payload);
      }
      return institutionService.createAcademicUnit(payload);
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["institution", "academic-units"] });
      toast.success(editingUnit ? "Academic unit updated." : "Academic unit created.");
      setModalOpen(false);
    },
    onError: (error) =>
      setUnitError(getApiErrorMessage(error, "Unable to save academic unit.")),
  });

  const deactivateMutation = useMutation({
    mutationFn: async ({ kind, id }: { kind: Tab; id: number }) => {
      if (kind === "faculties") return institutionService.deactivateFaculty(id);
      if (kind === "schools") return institutionService.deactivateSchool(id);
      return institutionService.deactivateAcademicUnit(id);
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["institution"] });
      toast.success("Deactivated.");
    },
    onError: (error) =>
      toast.error(getApiErrorMessage(error, "Unable to deactivate.")),
  });

  const openCreateModal = (mode: Tab) => {
    setModalMode(mode);
    setModalOpen(true);
    setFormError("");
    if (mode === "faculties") { setEditingFaculty(null); setFacultyForm(emptyFaculty); }
    if (mode === "schools") { setEditingSchool(null); setSchoolForm(emptySchool); }
    if (mode === "units") { setEditingUnit(null); setUnitForm(emptyUnit); }
  };

  const openEditModal = (mode: Tab, entity: Faculty | School | AcademicUnit) => {
    setModalMode(mode);
    setModalOpen(true);
    setFormError("");
    if (mode === "faculties") {
      const f = entity as Faculty;
      setEditingFaculty(f);
      setFacultyForm({ code: f.code, name: f.name, status: f.status });
    }
    if (mode === "schools") {
      const s = entity as School;
      setEditingSchool(s);
      setSchoolForm({
        code: s.code,
        name: s.name,
        faculty_id: s.faculty_id ?? "",
        status: s.status,
      });
    }
    if (mode === "units") {
      const u = entity as AcademicUnit;
      setEditingUnit(u);
      setUnitForm({
        code: u.code,
        name: u.name,
        unit_type: u.unit_type,
        faculty_id: u.faculty_id ?? "",
        school_id: u.school_id ?? "",
        status: u.status,
      });
    }
  };

  const handleSave = () => {
    if (modalMode === "faculties") {
      if (!facultyForm.code.trim()) { setFacultyError("Code is required."); return; }
      if (!facultyForm.name.trim()) { setFacultyError("Name is required."); return; }
      facultySave.mutate();
    } else if (modalMode === "schools") {
      if (!schoolForm.code.trim()) { setSchoolError("Code is required."); return; }
      if (!schoolForm.name.trim()) { setSchoolError("Name is required."); return; }
      schoolSave.mutate();
    } else {
      if (!unitForm.code.trim()) { setUnitError("Code is required."); return; }
      if (!unitForm.name.trim()) { setUnitError("Name is required."); return; }
      unitSave.mutate();
    }
  };

  const allQueries = [facultiesQuery, schoolsQuery, unitsQuery];
  const isLoading = allQueries.some((q) => q.isLoading);
  const isError = allQueries.some((q) => q.isError);

  if (isError) {
    return (
      <Alert tone="error" className="institution-directory-error">
        Unable to load academic hierarchy. Please try again.
        <button
          type="button"
          onClick={() => allQueries.forEach((q) => void q.refetch())}
        >
          Retry
        </button>
      </Alert>
    );
  }

  // ----- Columns -----
  const facultyColumns: DataTableColumn<Faculty>[] = [
    {
      key: "faculty",
      header: "Faculty",
      render: (f) => (
        <div className="institution-name-cell">
          <div className="institution-avatar">
            <Library size={16} />
          </div>
          <div>
            <strong>{f.name}</strong>
            <span className="institution-mono">{f.code}</span>
          </div>
        </div>
      ),
    },
    {
      key: "type",
      header: "Level",
      render: () => <Badge tone="info">Faculty</Badge>,
    },
    {
      key: "status",
      header: "Status",
      render: (f) => <StatusBadge status={f.status} />,
    },
    ...(canManage
      ? [
          {
            key: "actions",
            header: "",
            render: (f: Faculty) => (
              <span className="institution-row-actions">
                <button type="button" className="institution-icon-button" title="Edit faculty" onClick={() => openEditModal("faculties", f)}>
                  <Pencil size={16} />
                </button>
                <button type="button" className="institution-icon-button danger" title="Deactivate faculty" disabled={f.status === "inactive"} onClick={() => deactivateMutation.mutate({ kind: "faculties", id: f.id })}>
                  <Trash2 size={16} />
                </button>
              </span>
            ),
          } as DataTableColumn<Faculty>,
        ]
      : []),
  ];

  const schoolColumns: DataTableColumn<School>[] = [
    {
      key: "school",
      header: "School",
      render: (s) => (
        <div className="institution-name-cell">
          <div className="institution-avatar">
            <GraduationCap size={16} />
          </div>
          <div>
            <strong>{s.name}</strong>
            <span className="institution-mono">{s.code}</span>
          </div>
        </div>
      ),
    },
    {
      key: "parent",
      header: "Faculty",
      render: (s) =>
        s.faculty ? <span className="institution-mono">{s.faculty.name}</span> : "—",
    },
    {
      key: "status",
      header: "Status",
      render: (s) => <StatusBadge status={s.status} />,
    },
    ...(canManage
      ? [
          {
            key: "actions",
            header: "",
            render: (s: School) => (
              <span className="institution-row-actions">
                <button type="button" className="institution-icon-button" title="Edit school" onClick={() => openEditModal("schools", s)}>
                  <Pencil size={16} />
                </button>
                <button type="button" className="institution-icon-button danger" title="Deactivate school" disabled={s.status === "inactive"} onClick={() => deactivateMutation.mutate({ kind: "schools", id: s.id })}>
                  <Trash2 size={16} />
                </button>
              </span>
            ),
          } as DataTableColumn<School>,
        ]
      : []),
  ];

  const unitColumns: DataTableColumn<AcademicUnit>[] = [
    {
      key: "unit",
      header: "Unit",
      render: (u) => (
        <div className="institution-name-cell">
          <div className="institution-avatar">
            <Network size={16} />
          </div>
          <div>
            <strong>{u.name}</strong>
            <span className="institution-mono">{u.code}</span>
          </div>
        </div>
      ),
    },
    {
      key: "type",
      header: "Type",
      render: (u) => <Badge tone="info">{u.unit_type}</Badge>,
    },
    {
      key: "parent",
      header: "Parent",
      render: (u) => {
        if (u.school) return <span className="institution-mono">{u.school.name}</span>;
        if (u.faculty) return <span className="institution-mono">{u.faculty.name}</span>;
        return "—";
      },
    },
    {
      key: "status",
      header: "Status",
      render: (u) => <StatusBadge status={u.status} />,
    },
    ...(canManage
      ? [
          {
            key: "actions",
            header: "",
            render: (u: AcademicUnit) => (
              <span className="institution-row-actions">
                <button type="button" className="institution-icon-button" title="Edit unit" onClick={() => openEditModal("units", u)}>
                  <Pencil size={16} />
                </button>
                <button type="button" className="institution-icon-button danger" title="Deactivate unit" disabled={u.status === "inactive"} onClick={() => deactivateMutation.mutate({ kind: "units", id: u.id })}>
                  <Trash2 size={16} />
                </button>
              </span>
            ),
          } as DataTableColumn<AcademicUnit>,
        ]
      : []),
  ];

  const tabButton = (id: Tab, label: string, count: number) => (
    <button
      type="button"
      className={`institution-toggle-row ${tab === id ? "active" : ""}`}
      style={{
        background: tab === id ? "var(--accent-teal-bg)" : "transparent",
        border: "1px solid var(--border)",
        borderRadius: "var(--radius-md)",
        padding: "10px 14px",
        cursor: "pointer",
        color: tab === id ? "var(--text-accent)" : "var(--text-secondary)",
        fontWeight: tab === id ? 600 : 500,
      }}
      onClick={() => setTab(id)}
    >
      {label} ({count})
    </button>
  );

  return (
    <>
      <p style={HIERARCHY_STYLE}>
        Hierarchy (JSPM diagram): <strong>Institution → Faculty → School → Academic Unit</strong>.
        SPPU/AMU skip schools; JNU skips faculties. All three shapes are supported.
      </p>

      <div className="institution-directory-toolbar" style={{ gap: 8 }}>
        {tabButton("faculties", "Faculties", filteredFaculties.length)}
        {tabButton("schools", "Schools", filteredSchools.length)}
        {tabButton("units", "Academic Units", filteredUnits.length)}

        <SearchInput
          icon={Search}
          placeholder="Search by code or name..."
          value={searchInput}
          onChange={(event) => setSearchInput(event.target.value)}
          aria-label="Search academic hierarchy"
          style={{ marginLeft: "auto" }}
        />

        {canManage && (
          <Button type="button" onClick={() => openCreateModal(tab)}>
            <Plus size={16} />
            Add {tab === "faculties" ? "Faculty" : tab === "schools" ? "School" : "Unit"}
          </Button>
        )}
      </div>

      {tab === "faculties" && (
        <DataTable<Faculty>
          columns={facultyColumns}
          rows={filteredFaculties}
          rowKey={(row) => row.id}
          loading={isLoading}
          error={null}
          emptyMessage="No faculties yet."
        />
      )}

      {tab === "schools" && (
        <DataTable<School>
          columns={schoolColumns}
          rows={filteredSchools}
          rowKey={(row) => row.id}
          loading={isLoading}
          error={null}
          emptyMessage="No schools yet. JSPM-style chains create schools; AMU/SPPU/JNU use faculties or units directly."
        />
      )}

      {tab === "units" && (
        <DataTable<AcademicUnit>
          columns={unitColumns}
          rows={filteredUnits}
          rowKey={(row) => row.id}
          loading={isLoading}
          error={null}
          emptyMessage="No academic units yet."
        />
      )}

      <Modal
        open={modalOpen}
        title={
          modalMode === "faculties"
            ? editingFaculty ? "Edit Faculty" : "Add Faculty"
            : modalMode === "schools"
              ? editingSchool ? "Edit School" : "Add School"
              : editingUnit ? "Edit Academic Unit" : "Add Academic Unit"
        }
        onClose={() => setModalOpen(false)}
        footer={
          <>
            <Button type="button" variant="secondary" onClick={() => setModalOpen(false)}>
              Cancel
            </Button>
            <Button
              type="button"
              onClick={handleSave}
              disabled={facultySave.isPending || schoolSave.isPending || unitSave.isPending}
            >
              Save
            </Button>
          </>
        }
      >
        {modalMode === "faculties" && (
          <>
            {facultyError && <div className="institution-form-alert" role="alert">{facultyError}</div>}
            <FormField label="Code" htmlFor="fac-code" required>
              <Input id="fac-code" value={facultyForm.code} onChange={(e) => setFacultyForm((c) => ({ ...c, code: e.target.value }))} placeholder="ENG" />
            </FormField>
            <FormField label="Name" htmlFor="fac-name" required>
              <Input id="fac-name" value={facultyForm.name} onChange={(e) => setFacultyForm((c) => ({ ...c, name: e.target.value }))} placeholder="Engineering" />
            </FormField>
            <FormField label="Status" htmlFor="fac-status">
              <select id="fac-status" className="filter-select" value={facultyForm.status} onChange={(e) => setFacultyForm((c) => ({ ...c, status: e.target.value as AcademicUnitStatus }))}>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </FormField>
          </>
        )}

        {modalMode === "schools" && (
          <>
            {schoolError && <div className="institution-form-alert" role="alert">{schoolError}</div>}
            <FormField label="Faculty" htmlFor="sch-fac">
              <select id="sch-fac" className="filter-select" value={schoolForm.faculty_id} onChange={(e) => setSchoolForm((c) => ({ ...c, faculty_id: e.target.value }))}>
                <option value="">None / Directly under institution</option>
                {faculties.map((f) => (
                  <option key={f.id} value={f.id}>{f.code} — {f.name}</option>
                ))}
              </select>
            </FormField>
            <FormField label="Code" htmlFor="sch-code" required>
              <Input id="sch-code" value={schoolForm.code} onChange={(e) => setSchoolForm((c) => ({ ...c, code: e.target.value }))} placeholder="SOT" />
            </FormField>
            <FormField label="Name" htmlFor="sch-name" required>
              <Input id="sch-name" value={schoolForm.name} onChange={(e) => setSchoolForm((c) => ({ ...c, name: e.target.value }))} placeholder="School of Technology" />
            </FormField>
            <FormField label="Status" htmlFor="sch-status">
              <select id="sch-status" className="filter-select" value={schoolForm.status} onChange={(e) => setSchoolForm((c) => ({ ...c, status: e.target.value as AcademicUnitStatus }))}>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </FormField>
          </>
        )}

        {modalMode === "units" && (
          <>
            {unitError && <div className="institution-form-alert" role="alert">{unitError}</div>}
            <div className="institution-form-grid">
              <FormField label="School" htmlFor="unit-school">
                <select id="unit-school" className="filter-select" value={unitForm.school_id} onChange={(e) => setUnitForm((c) => ({ ...c, school_id: e.target.value }))}>
                  <option value="">None / no school tier</option>
                  {schools.map((s) => (
                    <option key={s.id} value={s.id}>{s.code} — {s.name}</option>
                  ))}
                </select>
              </FormField>
              <FormField label="Faculty" htmlFor="unit-fac">
                <select id="unit-fac" className="filter-select" value={unitForm.faculty_id} onChange={(e) => setUnitForm((c) => ({ ...c, faculty_id: e.target.value }))}>
                  <option value="">None / direct under institution</option>
                  {faculties.map((f) => (
                    <option key={f.id} value={f.id}>{f.code} — {f.name}</option>
                  ))}
                </select>
              </FormField>
            </div>
            <FormField label="Type" htmlFor="unit-type">
              <select id="unit-type" className="filter-select" value={unitForm.unit_type} onChange={(e) => setUnitForm((c) => ({ ...c, unit_type: e.target.value as AcademicUnitType }))}>
                {ACADEMIC_UNIT_TYPES.map((t) => (
                  <option key={t.value} value={t.value}>{t.label}</option>
                ))}
              </select>
            </FormField>
            <div className="institution-form-grid">
              <FormField label="Code" htmlFor="unit-code" required>
                <Input id="unit-code" value={unitForm.code} onChange={(e) => setUnitForm((c) => ({ ...c, code: e.target.value }))} placeholder="CSE" />
              </FormField>
              <div />
            </div>
            <FormField label="Name" htmlFor="unit-name" required>
              <Input id="unit-name" value={unitForm.name} onChange={(e) => setUnitForm((c) => ({ ...c, name: e.target.value }))} placeholder="Department of Computer Science" />
            </FormField>
            <FormField label="Status" htmlFor="unit-status">
              <select id="unit-status" className="filter-select" value={unitForm.status} onChange={(e) => setUnitForm((c) => ({ ...c, status: e.target.value as AcademicUnitStatus }))}>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </FormField>
          </>
        )}
      </Modal>
    </>
  );
};

export default AcademicUnitsPage;
