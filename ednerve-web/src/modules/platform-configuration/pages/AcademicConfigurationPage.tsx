import SettingsPage from "../components/SettingsPage";

const AcademicConfigurationPage = () => (
  <SettingsPage
    groups={["academic"]}
    heading="Academic Configuration"
    description="Academic year, currency and display formats used across the ERP."
  />
);

export default AcademicConfigurationPage;
