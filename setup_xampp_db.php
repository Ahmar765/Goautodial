<?php
require_once __DIR__ . '/php/RequestGuard.php';
// The former Windows helper used root without a password and reset tables.
// The guarded initializer now reads explicit DB_* settings and requires opt-in.
require __DIR__ . '/init_db.php';
