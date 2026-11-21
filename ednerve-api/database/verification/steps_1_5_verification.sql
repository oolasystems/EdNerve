-- OolaEdNerve: verification queries for implementation Steps 1-5.
-- Run these in the ednerve database after `php artisan migrate` and `php artisan db:seed`.

-- 1. Confirm that all five foundation tables exist.
SHOW TABLES LIKE 'institutions';
SHOW TABLES LIKE 'academic_units';
SHOW TABLES LIKE 'roles';
SHOW TABLES LIKE 'users';
SHOW TABLES LIKE 'institution_user';

-- 2. Verify the five demo institution records seeded by InstitutionSeeder.
SELECT id, code, name, institution_type, city, state, country, timezone, is_active
FROM institutions
ORDER BY id;

-- Expected count after InstitutionSeeder: 5.
SELECT COUNT(*) AS institution_count FROM institutions;

-- 3. Verify the system role catalogue seeded by RoleSeeder.
SELECT id, code, name, scope_type, institution_id, is_system, is_active
FROM roles
ORDER BY id;

-- Expected count after RoleSeeder: 12.
SELECT COUNT(*) AS role_count FROM roles;

-- 4. Verify the new user-account columns.
SHOW COLUMNS FROM users;

-- 5. Verify institution membership table structure and current membership count.
SHOW COLUMNS FROM institution_user;
SELECT COUNT(*) AS institution_membership_count FROM institution_user;

-- 6. Check for duplicate institution membership pairs (expected: zero rows).
SELECT institution_id, user_id, COUNT(*) AS duplicate_count
FROM institution_user
GROUP BY institution_id, user_id
HAVING COUNT(*) > 1;

-- 7. Check organization-unit institution ownership and parent references.
SELECT id, institution_id, parent_id, code, name, unit_type, is_active
FROM academic_units
ORDER BY institution_id, id;
