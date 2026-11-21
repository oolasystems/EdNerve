-- Permission catalogue and role-permission mappings
SELECT COUNT(*) AS permission_count FROM permissions;
SELECT module, COUNT(*) AS permission_count FROM permissions GROUP BY module ORDER BY module;
SELECT COUNT(*) AS role_permission_count FROM role_permission;

-- Role assignment distribution
SELECT r.code AS role_code, COUNT(ura.id) AS assignment_count
FROM roles r
LEFT JOIN user_role_assignments ura ON ura.role_id = r.id
GROUP BY r.id, r.code
ORDER BY r.code;

-- Review assignments with their institution and organization scope
SELECT u.id AS user_id, u.name AS user_name, r.code AS role_code,
       t.code AS institution_code, ou.code AS organization_unit_code,
       ura.is_active, ura.starts_at, ura.ends_at
FROM user_role_assignments ura
JOIN users u ON u.id = ura.user_id
JOIN roles r ON r.id = ura.role_id
LEFT JOIN institutions t ON t.id = ura.institution_id
LEFT JOIN academic_units ou ON ou.id = ura.academic_unit_id
ORDER BY u.id, r.code;

-- Check that institution-scoped assignments reference a institution membership.
SELECT ura.id, ura.user_id, ura.role_id, ura.institution_id
FROM user_role_assignments ura
LEFT JOIN institution_user tu ON tu.user_id = ura.user_id AND tu.institution_id = ura.institution_id
WHERE ura.institution_id IS NOT NULL AND tu.id IS NULL;

-- Check that organization-scoped assignments stay within the same institution.
SELECT ura.id, ura.institution_id, ura.academic_unit_id, ou.institution_id AS organization_institution_id
FROM user_role_assignments ura
JOIN academic_units ou ON ou.id = ura.academic_unit_id
WHERE ura.academic_unit_id IS NOT NULL
  AND (ura.institution_id IS NULL OR ura.institution_id <> ou.institution_id);
