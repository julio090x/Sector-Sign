<?php
/** Public non-sensitive build metadata. Does not query the database or external services. */
declare(strict_types=1);
function release_info():array {
    static $release=null;
    if($release!==null)return $release;
    $raw=@file_get_contents(__DIR__.'/release.json');
    $data=is_string($raw)?json_decode($raw,true):null;
    $release=is_array($data)?$data:['version'=>'unknown','released_at'=>'','channel'=>'unknown','changes'=>[]];
    return $release;
}
