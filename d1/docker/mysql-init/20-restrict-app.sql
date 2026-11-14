-- MYSQL_USER is fixed to aanshu_app in compose.yaml. Runtime traffic has no DDL grants.
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'aanshu_app'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON aanshu.* TO 'aanshu_app'@'%';
