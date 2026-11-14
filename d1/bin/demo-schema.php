<?php
// Called by install/upgrade with their migration-capable PDO connection.
foreach(['projects','posts','reviews','enquiries'] as $table) {
    $check=$db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name='demo_key'");
    $check->execute([$table]);
    if(!(int)$check->fetchColumn()) $db->exec("ALTER TABLE $table ADD COLUMN demo_key VARCHAR(80) NULL DEFAULT NULL, ADD UNIQUE KEY demo_key (demo_key)");
}
