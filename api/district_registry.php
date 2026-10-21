<?php
function vs_district_registry(): array {
    static $map = null;
    if ($map !== null) return $map;
    $data = json_decode(file_get_contents(__DIR__.'/../database/districts.json'), true);
    $map = [];
    foreach (($data['districts'] ?? []) as $row) $map[$row['id']] = $row;
    return $map;
}
function vs_valid_district($id): bool { return is_string($id) && isset(vs_district_registry()[$id]); }

function vs_language_registry(): array {
    static $map = null;
    if ($map !== null) return $map;
    $map=[];
    $fh=fopen(__DIR__.'/../database/language_registry.csv','r');
    $header=fgetcsv($fh);
    $header[0]=preg_replace('/^\xEF\xBB\xBF/','',$header[0]);
    while (($r=fgetcsv($fh))!==false) {
        if (count($r)!==count($header)) continue;
        $row=array_combine($header,$r);
        $map[$row['language_id']]=['id'=>$row['language_id'],'name'=>$row['display_name'],'type'=>$row['type'],'status'=>$row['app_status']];
    }
    fclose($fh);
    return $map;
}
function vs_valid_language($id): bool { return is_string($id) && isset(vs_language_registry()[$id]); }
