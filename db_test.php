<?php
require_once __DIR__ . '/config.php';
try { aura_db(); echo 'AURA MariaDB connection successful!'; }
catch (Throwable $e) { http_response_code(500); echo 'Database connection failed.'; }
