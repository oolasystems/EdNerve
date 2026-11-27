import { useState } from "react";
import { UserPlus } from "lucide-react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { z } from "zod";

import UsersExplorer from "../components/UsersExplorer";
import { platformUserService } from "../api";
import "../users.css";

import {
  Alert,
  Button,
  Modal,
  FormField,
  Input,
} from "../../../shared/ui";
import { getApiErrorMessage } from "../../../shared/lib/api-error";

const schema = z.object({
  name: z.string().min(1, "Full name is required.").max(150),
  email: z.email("Enter a valid email address.").max(150),
  phone: z.string().max(30),
  password: z.string().min(8, "Password must be at least 8 characters."),
});

type CreatePlatformUserValues = z.input<typeof schema>;

const defaultValues: CreatePlatformUserValues = {
  name: "",
  email: "",
  phone: "",
  password: "",
};

const PlatformUsersPage = () => {
  const queryClient = useQueryClient();
  const [modalOpen, setModalOpen] = useState(false);
  const [submitError, setSubmitError] = useState("");

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<CreatePlatformUserValues>({
    resolver: zodResolver(schema),
    defaultValues,
  });

  const createMutation = useMutation({
    mutationFn: (values: CreatePlatformUserValues) =>
      platformUserService.createPlatformUser({
        name: values.name.trim(),
        email: values.email.trim().toLowerCase(),
        phone: values.phone.trim() || null,
        password: values.password,
      }),
    onSuccess: () => {
      toast.success("Platform administrator created successfully.");
      reset(defaultValues);
      setModalOpen(false);
      setSubmitError("");
      void queryClient.invalidateQueries({ queryKey: ["platform", "users"] });
    },
    onError: (error) => {
      setSubmitError(
        getApiErrorMessage(error, "Unable to create the platform administrator.")
      );
    },
  });

  return (
    <div className="platform-users-page">
      <div className="institution-directory-toolbar">
        <p className="dashboard-updated-label platform-users-description">
          Oola Systems accounts with full platform access.
        </p>
        <Button icon={UserPlus} onClick={() => setModalOpen(true)}>
          Add Platform Admin
        </Button>
      </div>

      <UsersExplorer accountType="platform" lockAccountType />

      <Modal
        open={modalOpen}
        title="Add Platform Admin"
        onClose={() => {
          setModalOpen(false);
          setSubmitError("");
        }}
        footer={
          <>
            <Button variant="secondary" onClick={() => setModalOpen(false)}>
              Cancel
            </Button>
            <Button
              variant="primary"
              loading={createMutation.isPending}
              onClick={handleSubmit((values) => {
                setSubmitError("");
                createMutation.mutate(values);
              })}
            >
              Create Admin
            </Button>
          </>
        }
      >
        {submitError && <Alert tone="error">{submitError}</Alert>}

        <form className="auth-form platform-user-create-form">
          <FormField
            label="Full Name"
            htmlFor="platform-user-name"
            required
            error={errors.name?.message}
          >
            <Input
              id="platform-user-name"
              placeholder="Administrator's full name"
              {...register("name")}
            />
          </FormField>

          <FormField
            label="Email Address"
            htmlFor="platform-user-email"
            required
            error={errors.email?.message}
          >
            <Input
              id="platform-user-email"
              type="email"
              placeholder="admin@oolasystems.com"
              {...register("email")}
            />
          </FormField>

          <FormField
            label="Phone"
            htmlFor="platform-user-phone"
            error={errors.phone?.message}
          >
            <Input
              id="platform-user-phone"
              type="tel"
              placeholder="Optional contact number"
              {...register("phone")}
            />
          </FormField>

          <FormField
            label="Password"
            htmlFor="platform-user-password"
            required
            error={errors.password?.message}
          >
            <Input
              id="platform-user-password"
              type="password"
              autoComplete="new-password"
              {...register("password")}
            />
          </FormField>
        </form>
      </Modal>
    </div>
  );
};

export default PlatformUsersPage;
