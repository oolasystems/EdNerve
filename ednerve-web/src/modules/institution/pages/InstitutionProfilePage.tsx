import { useState } from "react";
import {
  Building2,
  Pencil,
  Save,
  X,
  RefreshCw,
  Mail,
  Phone,
  Globe,
  MapPin,
  Link2,
  Hash,
} from "lucide-react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { institutionService } from "../api";
import "../institution.css";

import { useAuth } from "../../../contexts/AuthContext";
import { isInstitutionAdmin } from "../../../shared/lib/rbac";

import {
  Alert,
  Button,
  FormField,
  Input,
  StatusBadge,
  Textarea,
} from "../../../shared/ui";
import { getApiErrorMessage } from "../../../shared/lib/api-error";

const InstitutionProfilePage = () => {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const canEdit = isInstitutionAdmin(user);

  const profileQuery = useQuery({
    queryKey: ["institution", "profile"],
    queryFn: () => institutionService.getProfile(),
  });

  const institution = profileQuery.data?.institution;

  const [editing, setEditing] = useState(false);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState("");
  const [form, setForm] = useState({
    name: "",
    email: "",
    phone: "",
    website: "",
    address: "",
    city: "",
    state: "",
    country: "",
    applicant_portal_origin: "",
    applicant_portal_entry_path: "",
  });

  if (profileQuery.isLoading) {
    return (
      <div className="dashboard-state">
        <p>Loading institution profile...</p>
      </div>
    );
  }

  if (profileQuery.isError || !institution) {
    return (
      <div className="dashboard-state">
        <Alert tone="error">
          Unable to load the institution profile. Please try again.
        </Alert>
        <button
          type="button"
          className="workspace-action-button"
          onClick={() => void profileQuery.refetch()}
        >
          <RefreshCw size={15} />
          Retry
        </button>
      </div>
    );
  }

  const handleEdit = () => {
    setForm({
      name: institution.name ?? "",
      email: institution.email ?? "",
      phone: institution.phone ?? "",
      website: institution.website ?? "",
      address: institution.address ?? "",
      city: institution.city ?? "",
      state: institution.state ?? "",
      country: institution.country ?? "",
      applicant_portal_origin: institution.applicant_portal_origin ?? "",
      applicant_portal_entry_path: institution.applicant_portal_entry_path ?? "",
    });
    setFormError("");
    setEditing(true);
  };

  const field = (key: keyof typeof form) => (
    event: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>
  ) => setForm((current) => ({ ...current, [key]: event.target.value }));

  const handleSave = async () => {
    setFormError("");

    if (!form.name.trim()) {
      setFormError("Institution name is required.");
      return;
    }
    if (form.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
      setFormError("Enter a valid email address.");
      return;
    }

    setSaving(true);
    try {
      await institutionService.updateProfile({
        name: form.name.trim(),
        email: form.email.trim() || null,
        phone: form.phone.trim() || null,
        website: form.website.trim() || null,
        address: form.address.trim() || null,
        city: form.city.trim() || null,
        state: form.state.trim() || null,
        country: form.country.trim() || null,
        applicant_portal_origin: form.applicant_portal_origin.trim() || null,
        applicant_portal_entry_path: form.applicant_portal_entry_path.trim() || null,
      });
      await queryClient.invalidateQueries({ queryKey: ["institution"] });
      toast.success("Institution profile updated.");
      setEditing(false);
    } catch (error) {
      setFormError(
        getApiErrorMessage(error, "Unable to update the institution profile.")
      );
    } finally {
      setSaving(false);
    }
  };

  if (editing) {
    return (
      <div className="institution-form">
        {formError && (
          <div className="institution-form-alert" role="alert">
            {formError}
          </div>
        )}

        <div className="institution-form-grid">
          <FormField label="Institution Name" htmlFor="profile-name" required>
            <Input
              id="profile-name"
              value={form.name}
              onChange={field("name")}
              placeholder="Aligarh Muslim University"
            />
          </FormField>

          <FormField label="Email" htmlFor="profile-email">
            <Input
              id="profile-email"
              type="email"
              value={form.email}
              onChange={field("email")}
              placeholder="contact@institution.edu.in"
            />
          </FormField>

          <FormField label="Phone" htmlFor="profile-phone">
            <Input
              id="profile-phone"
              value={form.phone}
              onChange={field("phone")}
              placeholder="+91 00000 00000"
            />
          </FormField>

          <FormField label="Website" htmlFor="profile-website">
            <Input
              id="profile-website"
              value={form.website}
              onChange={field("website")}
              placeholder="https://www.amu.ac.in"
            />
          </FormField>
        </div>

        <FormField label="Address" htmlFor="profile-address">
          <Textarea
            id="profile-address"
            rows={2}
            value={form.address}
            onChange={field("address")}
            placeholder="Street / campus address"
          />
        </FormField>

        <div className="institution-form-grid">
          <FormField label="City" htmlFor="profile-city">
            <Input id="profile-city" value={form.city} onChange={field("city")} placeholder="Aligarh" />
          </FormField>
          <FormField label="State" htmlFor="profile-state">
            <Input id="profile-state" value={form.state} onChange={field("state")} placeholder="Uttar Pradesh" />
          </FormField>
          <FormField label="Country" htmlFor="profile-country">
            <Input id="profile-country" value={form.country} onChange={field("country")} placeholder="India" />
          </FormField>
        </div>

        <section className="institution-details-section">
          <div className="institution-details-section-heading">
            <Building2 size={19} />
            <h3>Applicant Portal Configuration</h3>
          </div>
          <div className="institution-form-grid">
            <FormField label="Applicant Portal Origin" htmlFor="profile-applicant-portal-origin">
              <Input
                id="profile-applicant-portal-origin"
                type="url"
                value={form.applicant_portal_origin}
                onChange={field("applicant_portal_origin")}
                placeholder="https://admissions.example.edu"
              />
            </FormField>
            <FormField label="Applicant Portal Entry Path" htmlFor="profile-applicant-portal-entry-path">
              <Input
                id="profile-applicant-portal-entry-path"
                value={form.applicant_portal_entry_path}
                onChange={field("applicant_portal_entry_path")}
                placeholder="/apply/AMU"
              />
            </FormField>
          </div>
        </section>

        <div className="institution-form-actions">
          <Button type="button" variant="secondary" onClick={() => setEditing(false)}>
            <X size={16} />
            Cancel
          </Button>
          <Button type="button" onClick={handleSave} disabled={saving}>
            <Save size={16} />
            {saving ? "Saving..." : "Save changes"}
          </Button>
        </div>
      </div>
    );
  }

  const address = [institution.address, institution.city, institution.state, institution.country]
    .filter(Boolean)
    .join(", ");
  const portalEntry = institution.applicant_portal_entry_path;

  return (
    <div className="institution-profile-view">
      <section className="institution-profile-hero">
        <div className="institution-profile-hero-icon">
          <Building2 size={30} strokeWidth={1.7} />
        </div>
        <div className="institution-profile-hero-copy">
          <div className="institution-profile-eyebrow">INSTITUTION</div>
          <h2>{institution.name}</h2>
          <div className="institution-profile-identity">
            <span><Hash size={13} /> {institution.code}</span>
            {institution.slug && <span>{institution.slug}</span>}
            <StatusBadge status={institution.status} />
            {institution.institution_type && <span className="institution-profile-type">{institution.institution_type}</span>}
          </div>
        </div>
        {canEdit && (
          <div className="institution-profile-edit-action">
            <Button type="button" onClick={handleEdit}>
              <Pencil size={15} />
              Edit profile
            </Button>
          </div>
        )}
      </section>

      <section className="institution-profile-section" aria-labelledby="institution-profile-details-heading">
        <div className="institution-profile-section-heading">
          <div>
            <h3 id="institution-profile-details-heading">Institution details</h3>
            <p>Official identity and registration information</p>
          </div>
        </div>
        <dl className="institution-profile-data-grid">
          <div className="institution-profile-data-item">
            <dt>Institution name</dt>
            <dd>{institution.name}</dd>
          </div>
          <div className="institution-profile-data-item">
            <dt>Institution code</dt>
            <dd>{institution.code}</dd>
          </div>
          <div className="institution-profile-data-item">
            <dt>Institution type</dt>
            <dd>{institution.institution_type || "Not specified"}</dd>
          </div>
          <div className="institution-profile-data-item">
            <dt>Account status</dt>
            <dd><StatusBadge status={institution.status} /></dd>
          </div>
        </dl>
      </section>

      <section className="institution-profile-section" aria-labelledby="institution-profile-contact-heading">
        <div className="institution-profile-section-heading">
          <div>
            <h3 id="institution-profile-contact-heading">Contact information</h3>
            <p>Official communication channels and institutional website</p>
          </div>
        </div>
        <dl className="institution-profile-data-grid">
          <div className="institution-profile-data-item">
            <dt><Mail size={14} /> Official email</dt>
            <dd>{institution.email || "Not specified"}</dd>
          </div>
          <div className="institution-profile-data-item">
            <dt><Phone size={14} /> Contact phone</dt>
            <dd>{institution.phone || "Not specified"}</dd>
          </div>
          <div className="institution-profile-data-item institution-profile-data-item-wide">
            <dt><Globe size={14} /> Official website</dt>
            <dd>
              {institution.website ? (
                <a href={institution.website} target="_blank" rel="noreferrer">{institution.website}</a>
              ) : "Not specified"}
            </dd>
          </div>
          <div className="institution-profile-data-item institution-profile-data-item-wide">
            <dt><MapPin size={14} /> Institutional address</dt>
            <dd>{address || "Not specified"}</dd>
          </div>
        </dl>
      </section>

      <section className="institution-profile-section" aria-labelledby="institution-profile-portal-heading">
        <div className="institution-profile-section-heading">
          <div>
            <h3 id="institution-profile-portal-heading">Applicant portal configuration</h3>
            <p>Public portal address configured for this institution</p>
          </div>
        </div>
        <dl className="institution-profile-data-grid">
          <div className="institution-profile-data-item institution-profile-data-item-wide">
            <dt><Globe size={14} /> Portal origin</dt>
            <dd>{institution.applicant_portal_origin || "Not specified"}</dd>
          </div>
          <div className="institution-profile-data-item institution-profile-data-item-wide">
            <dt><Link2 size={14} /> Portal entry path</dt>
            <dd>{portalEntry || "Not specified"}</dd>
          </div>
        </dl>
      </section>
    </div>
  );
};

export default InstitutionProfilePage;
