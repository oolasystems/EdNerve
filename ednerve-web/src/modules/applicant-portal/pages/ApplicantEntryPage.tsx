import { Navigate, useParams } from "react-router-dom";

/**
 * The Oola Systems Admission Portal page owns public applicant navigation.
 * Direct visits to the legacy React entry route go straight to applicant login.
 */
export default function ApplicantEntryPage() {
  const { institutionCode = "" } = useParams();

  return (
    <Navigate
      to={`/apply/${institutionCode}/login`}
      replace
    />
  );
}
