<?php


use Phinx\Migration\AbstractMigration;

class DbTriggers extends AbstractMigration
{
    public function up()
    {
        $this->execute("CREATE TRIGGER move_to_deleted_members AFTER
                        DELETE ON members FOR EACH ROW
                        BEGIN
                          DELETE FROM deleted_members
                            WHERE deleted_members.id = OLD.id;
                          INSERT INTO deleted_members (id, username, password, email, verified)
                          VALUES ( OLD.id, OLD.username, OLD.password, OLD.email, OLD.verified );
                        END;");

        $this->execute("CREATE TRIGGER assign_default_role AFTER
                          INSERT ON members FOR EACH ROW
                          BEGIN
                            SET @default_role = (SELECT id FROM roles WHERE default_role = 1 LIMIT 1);
                            INSERT INTO member_roles (member_id, role_id) VALUES (NEW.id, @default_role);
                          END;");


        $this->execute("CREATE TRIGGER prevent_deletion_of_required_perms
                          BEFORE DELETE
                          ON permissions
                          FOR EACH ROW
                          BEGIN
                            IF OLD.required = 1 THEN
                              SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot delete required permissions';
                            END IF;
                        END;");

        $this->execute("CREATE TRIGGER prevent_deletion_of_required_roles
                          BEFORE DELETE
                          ON roles
                          FOR EACH ROW
                          BEGIN
                            IF OLD.required = 1 THEN
                              SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot delete required roles';
                            END IF;
                        END;");

        $this->execute("CREATE TRIGGER prevent_deletion_of_superadmin
                          BEFORE DELETE
                          ON members
                          FOR EACH ROW
                          BEGIN
                          	IF
                            (SELECT count(m.id)
                          	FROM members m
                          	INNER JOIN member_roles mr on mr.member_id = m.id
                          	INNER JOIN roles r on mr.role_id = r.id
                          	WHERE
                          	 m.verified = 1
                          	AND m.banned = 0
                          	AND r.name = 'Superadmin'
                              AND m.id = OLD.id) > 0
                            THEN
                              SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot delete superadmin user';
                            END IF;
                          END");

        $this->execute("CREATE TRIGGER at_least_one_superadmin_assigned
                      	BEFORE DELETE
                      		ON member_roles
                      		FOR EACH ROW
                      		BEGIN
                      			IF ((old.role_id = 1)) && (SELECT COUNT(id) from member_roles where role_id = 1) <= 1 THEN
                      			SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'There must be at least one Superadmin assigned';
                      		END IF;
                      	END;");
    }

    public function down()
    {
        $this->execute("DROP TRIGGER IF EXISTS move_to_deleted_members;");
        $this->execute("DROP TRIGGER IF EXISTS assign_default_role;");
        $this->execute("DROP TRIGGER IF EXISTS prevent_deletion_of_required_perms;");
        $this->execute("DROP TRIGGER IF EXISTS prevent_deletion_of_required_roles;");
        $this->execute("DROP TRIGGER IF EXISTS prevent_deletion_of_superadmin;");
        $this->execute("DROP TRIGGER IF EXISTS at_least_one_superadmin_assigned;");
    }
}
