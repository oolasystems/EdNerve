import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import {
  Building2,
  UserRound,
  Save,
  ArrowLeft,
} from "lucide-react";

import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation } from "@tanstack/react-query";
import { z } from "zod";
import { toast } from "sonner";

import { platformInstitutionsService } from "../api";
import "../../institution/institution.css";
import type { CreateInstitutionPayload } from "../types";

import {
  Alert,
  Button,
  FormField,
  Input,
  Select,
  Textarea,
} from "../../../shared/ui";
import { getApiErrorMessage } from "../../../shared/lib/api-error";

const institutionFormSchema = z
  .object({
    code: z
      .string()
      .min(1, "Institution code is required.")
      .max(50, "Institution code must be 50 characters or fewer."),
    name: z
      .string()
      .min(1, "Institution name is required.")
      .max(255, "Institution name must be 255 characters or fewer."),
    slug: z
      .string()
      .min(1, "URL slug is required.")
      .max(255, "URL slug must be 255 characters or fewer.")
      .regex(
        /^[a-z0-9]+(?:-[a-z0-9]+)*$/,
        "Use lowercase letters, numbers and hyphens (e.g. amu-aligarh)."
      ),
    institution_type: z.enum([
      "university",
      "college",
      "college_group",
      "autonomous_college",
      "other",
    ]),
    email: z.union([z.literal(""), z.email("Enter a valid email address.")]),
    phone: z.string().max(30, "Phone must be 30 characters or fewer."),
    website: z.union([z.literal(""), z.url("Enter a valid URL.")]),
    address: z.string(),
    city: z.string().max(100),
    state: z.string().max(100),
    country: z.string().min(1, "Country is required."),
    admin_name: z
      .string()
      .min(1, "Administrator's full name is required.")
      .max(150),
    admin_email: z.email("Enter a valid administrator email address."),
    admin_phone: z.string().max(30),
    password: z
      .string()
      .min(8, "Password must be at least 8 characters."),
    password_confirmation: z
      .string()
      .min(1, "Please confirm the password."),
  })
  .refine((values) => values.password === values.password_confirmation, {
    message: "Password and Confirm Password do not match.",
    path: ["password_confirmation"],
  });

type InstitutionFormValues = z.input<typeof institutionFormSchema>;

const defaultValues: InstitutionFormValues = {
  code: "",
  name: "",
  slug: "",
  institution_type: "university",
  email: "",
  phone: "",
  website: "",
  address: "",
  city: "",
  state: "",
  country: "India",
  admin_name: "",
  admin_email: "",
  admin_phone: "",
  password: "",
  password_confirmation: "",
};

function generateSlug(value: string): string {
  return value
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");
}

const trimOrNull = (value: string) => {
  const trimmed = value.trim();
  return trimmed === "" ? null : trimmed;
};

const AddInstitutionPage = () => {
  const navigate = useNavigate();

  const [submitError, setSubmitError] = useState("");
  const [slugManuallyEdited, setSlugManuallyEdited] = useState(false);

  const {
    register,
    handleSubmit,
    watch,
    setValue,
    setError,
    clearErrors,
    formState: { errors, isSubmitting },
  } = useForm<InstitutionFormValues>({
    resolver: zodResolver(institutionFormSchema),
    defaultValues,
  });

  const watchedName = watch("name");

  // Auto-suggest the slug from the institution name until the user edits it.
  useEffect(() => {
    if (slugManuallyEdited) return;
    setValue("slug", generateSlug(watchedName ?? ""));
  }, [watchedName, slugManuallyEdited, setValue]);

  const createMutation = useMutation({
    mutationFn: (payload: CreateInstitutionPayload) =>
      platformInstitutionsService.createInstitution(payload),
    onSuccess: () => {
      toast.success(
        "Institution and its initial administrator have been created successfully."
      );
      navigate("/platform/institutions", { replace: true });
    },
    onError: (error) => {
      console.error("Unable to create institution:", error);
      setSubmitError(
        getApiErrorMessage(
          error,
          "Unable to create the institution. Please try again."
        )
      );
    },
  });

  const handleCancel = () => {
    navigate("/platform/institutions");
  };

  const onSubmit = (values: InstitutionFormValues) => {
    setSubmitError("");
    clearErrors();

    const trimmedCountry = values.country.trim();

    if (!trimmedCountry) {
      setError("country", {
        type: "manual",
        message: "Country is required.",
      });
      return;
    }

    const payload: CreateInstitutionPayload = {
      institution: {
        code: values.code.trim().toUpperCase(),
        name: values.name.trim(),
        slug: values.slug.trim(),
        institution_type: values.institution_type,
        email: trimOrNull(values.email),
        phone: trimOrNull(values.phone),
        website: trimOrNull(values.website),
        address: trimOrNull(values.address),
        city: trimOrNull(values.city),
        state: trimOrNull(values.state),
        country: trimmedCountry,
      },
      initial_admin: {
        name: values.admin_name.trim(),
        email: values.admin_email.trim(),
        phone: trimOrNull(values.admin_phone),
        password: values.password,
      },
    };

    createMutation.mutate(payload);
  };

  const submitting = isSubmitting || createMutation.isPending;

  return (
    <div className="institution-form-container">
      <form
        onSubmit={handleSubmit(onSubmit)}
        className="institution-form"
        noValidate
      >
        {submitError && (
          <Alert tone="error" className="institution-form-alert">
            {submitError}
          </Alert>
        )}

        {/* Institution Details */}
        <section className="institution-form-section">
          <div className="institution-form-section-heading">
            <div className="institution-form-section-icon">
              <Building2 size={20} />
            </div>
            <div>
              <h2>Institution Details</h2>
              <p>Enter the basic information about the institution.</p>
            </div>
          </div>

          <div className="institution-form-grid">
            <FormField
              label="Institution Code"
              htmlFor="institution-code"
              required
              error={errors.code?.message}
            >
              <Input
                id="institution-code"
                placeholder="e.g. AMU"
                maxLength={50}
                {...register("code", {
                  setValueAs: (value: string) => value.toUpperCase(),
                })}
              />
            </FormField>

            <FormField
              label="Institution Name"
              htmlFor="institution-name"
              required
              error={errors.name?.message}
            >
              <Input
                id="institution-name"
                placeholder="Enter institution name"
                maxLength={255}
                {...register("name")}
              />
            </FormField>

            <FormField
              label="URL Slug"
              htmlFor="institution-slug"
              required
              hint="Suggested from the institution name. You can edit it."
              error={errors.slug?.message}
            >
              <Input
                id="institution-slug"
                placeholder="institution-url-slug"
                maxLength={255}
                {...register("slug", {
                  onChange: () => setSlugManuallyEdited(true),
                })}
              />
            </FormField>

            <FormField
              label="Institution Type"
              htmlFor="institution-type"
              required
              error={errors.institution_type?.message}
            >
              <Select id="institution-type" {...register("institution_type")}>
                <option value="university">University</option>
                <option value="college">College</option>
                <option value="college_group">College Group</option>
                <option value="autonomous_college">Autonomous College</option>
                <option value="other">Other</option>
              </Select>
            </FormField>

            <FormField
              label="Official Email"
              htmlFor="institution-email"
              error={errors.email?.message}
            >
              <Input
                id="institution-email"
                type="email"
                placeholder="info@institution.edu"
                {...register("email")}
              />
            </FormField>

            <FormField
              label="Contact Phone"
              htmlFor="institution-phone"
              error={errors.phone?.message}
            >
              <Input
                id="institution-phone"
                type="tel"
                placeholder="Institution contact number"
                {...register("phone")}
              />
            </FormField>

            <FormField
              label="Website"
              htmlFor="institution-website"
              className="institution-form-full"
              error={errors.website?.message}
            >
              <Input
                id="institution-website"
                type="url"
                placeholder="https://www.institution.edu"
                {...register("website")}
              />
            </FormField>

            <FormField
              label="Address"
              htmlFor="institution-address"
              className="institution-form-full"
              error={errors.address?.message}
            >
              <Textarea
                id="institution-address"
                placeholder="Institution address"
                rows={2}
                className="institution-form-textarea"
                {...register("address")}
              />
            </FormField>

            <FormField
              label="City"
              htmlFor="institution-city"
              error={errors.city?.message}
            >
              <Input
                id="institution-city"
                placeholder="City"
                {...register("city")}
              />
            </FormField>

            <FormField
              label="State"
              htmlFor="institution-state"
              error={errors.state?.message}
            >
              <Input
                id="institution-state"
                placeholder="State"
                {...register("state")}
              />
            </FormField>

            <FormField
              label="Country"
              htmlFor="institution-country"
              required
              error={errors.country?.message}
            >
              <Input id="institution-country" {...register("country")} />
            </FormField>
          </div>
        </section>

        {/* Initial Administrator */}
        <section className="institution-form-section">
          <div className="institution-form-section-heading">
            <div className="institution-form-section-icon admin-icon">
              <UserRound size={20} />
            </div>
            <div>
              <h2>Initial Institution Administrator</h2>
              <p>
                Create the first administrator account for this institution.
              </p>
            </div>
          </div>

          <div className="institution-form-grid">
            <FormField
              label="Full Name"
              htmlFor="admin-name"
              required
              error={errors.admin_name?.message}
            >
              <Input
                id="admin-name"
                placeholder="Administrator's full name"
                {...register("admin_name")}
              />
            </FormField>

            <FormField
              label="Email Address"
              htmlFor="admin-email"
              required
              error={errors.admin_email?.message}
            >
              <Input
                id="admin-email"
                type="email"
                placeholder="admin@institution.edu"
                {...register("admin_email")}
              />
            </FormField>

            <FormField
              label="Phone"
              htmlFor="admin-phone"
              error={errors.admin_phone?.message}
            >
              <Input
                id="admin-phone"
                type="tel"
                placeholder="Administrator's phone number"
                {...register("admin_phone")}
              />
            </FormField>

            <FormField
              label="Password"
              htmlFor="admin-password"
              required
              error={errors.password?.message}
            >
              <Input
                id="admin-password"
                type="password"
                autoComplete="new-password"
                {...register("password")}
              />
            </FormField>

            <FormField
              label="Confirm Password"
              htmlFor="admin-password-confirm"
              required
              error={errors.password_confirmation?.message}
            >
              <Input
                id="admin-password-confirm"
                type="password"
                autoComplete="new-password"
                {...register("password_confirmation")}
              />
            </FormField>
          </div>
        </section>

        {/* Form Actions */}
        <div className="institution-form-actions">
          <button
            type="button"
            className="btn btn-secondary"
            onClick={handleCancel}
            disabled={submitting}
          >
            <ArrowLeft size={16} />
            Cancel
          </button>

          <Button
            type="submit"
            variant="primary"
            icon={Save}
            loading={submitting}
          >
            {submitting ? "Creating Institution..." : "Create Institution"}
          </Button>
        </div>
      </form>
    </div>
  );
};

export default AddInstitutionPage;
