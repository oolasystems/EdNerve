import { useEffect, useState } from "react";
import {
  Building2,
  Pencil,
  Save,
  X,
  RefreshCw,
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
  });

  // Populate the form when the institution loads (and after saving).
  useEffect(() => {
    if (!institution) return;
    setForm({
      name: institution.name ?? "",
      email: institution.email ?? "",
      phone: institution.phone ?? "",
      website: institution.website ?? "",
      address: institution.address ?? "",
      city: institution.city ?? "",
      state: institution.state ?? "",
      country: institution.country ?? "",
    });
  }, [institution]);

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
            <Input
              id="profile-city"
              value={form.city}
              onChange={field("city")}
              placeholder="Aligarh"
            />
          </FormField>

          <FormField label="State" htmlFor="profile-state">
            <Input
              id="profile-state"
              value={form.state}
              onChange={field("state")}
              placeholder="Uttar Pradesh"
            />
          </FormField>

          <FormField label="Country" htmlFor="profile-country">
            <Input
              id="profile-country"
              value={form.country}
              onChange={field("country")}
              placeholder="India"
            />
          </FormField>
        </div>

        <div className="institution-form-actions">
          <Button
            type="button"
            variant="secondary"
            onClick={() => setEditing(false)}
          >
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

  // ----- View mode -----
  return (
    <div className="institution-details" style={{ maxWidth: 1060 }}>
      <section className="institution-details-profile">
        <div className="institution-details-profile-icon">
          <Building2 size={30} color="var(--text-accent)" strokeWidth={1.6} />
        </div>

        <div className="institution-details-profile-info">
          <h2>{institution.name}</h2>
          <span className="institution-mono">{institution.code} · {institution.slug}</span>
          <div className="institution-details-profile-meta">
            <StatusBadge status={institution.status} />
            <span>{institution.institution_type}</span>
          </div>
        </div>

        {canEdit && (
          <div style={{ marginLeft: "auto", alignSelf: "center" }}>
            <Button type="button" onClick={() => setEditing(true)}>
              <Pencil size={15} />
              Edit profile
            </Button>
          </div>
        )}
      </section>

      <section className="institution-details-section">
        <div className="institution-details-section-heading">
          <Building2 size={19} />
          <h3>Institution Profile</h3>
        </div>

        <div className="institution-details-grid">
          <div className="institution-details-field">
            <label>Institution Name</label>
            <strong>{institution.name}</strong>
          </div>
          <div className="institution-details-field">
            <label>Institution Code</label>
            <strong>{institution.code}</strong>
          </div>
          <div className="institution-details-field">
            <label>Type</label>
            <strong>{institution.institution_type}</strong>
          </div>
          <div className="institution-details-field">
            <label>Status</label>
            <strong><StatusBadge status={institution.status} /></strong>
          </div>
          <div className="institution-details-field">
            <label>Official Email</label>
            <strong>{institution.email ?? "Not specified"}</strong>
          </div>
          <div className="institution-details-field">
            <label>Contact Phone</label>
            <strong>{institution.phone ?? "Not specified"}</strong>
          </div>
          <div className="institution-details-field">
            <label>Website</label>
            <strong>
              {institution.website ? (
                <a href={institution.website} target="_blank" rel="noreferrer">
                  {institution.website}
                </a>
              ) : (
                "Not specified"
              )}
            </strong>
          </div>
          <div className="institution-details-field">
            <label>Address</label>
            <strong>
              {institution.address ? (
                <>
                  {institution.address}
                  {(institution.city || institution.state || institution.country) && (
                    <>
                      <br />
                      {[institution.city, institution.state, institution.country]
                        .filter(Boolean)
                        .join(", ")}
                    </>
                  )}
                </>
              ) : (
                "Not specified"
              )}
            </strong>
          </div>
        </div>
      </section>
    </div>
  );
};

export default InstitutionProfilePage;
