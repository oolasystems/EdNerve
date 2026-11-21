import SettingsPage from "../components/SettingsPage";

const SystemConfigurationPage = () => (
  <SettingsPage
    groups={["system", "features"]}
    heading="System Configuration"
    description="Global platform identity, support contacts, maintenance controls and feature flags. Changes apply to the whole platform."
  />
);

export default SystemConfigurationPage;
