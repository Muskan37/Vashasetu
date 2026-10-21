<?php
/** Districts are administrative choices, not asserted speaker languages. */
require_once __DIR__ . '/district_registry.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo json_encode(['success'=>true,'districts'=>array_values(vs_district_registry()), 'languages'=>array_values(vs_language_registry()), 'note'=>'Geographic candidates only. Users choose their own spoken language; districts do not identify ethnicity or dialect.'],JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
