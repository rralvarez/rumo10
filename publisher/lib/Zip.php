<?php
declare(strict_types=1);
namespace Rumo\Publisher;
require_once __DIR__.'/Core.php';
function inspectArtifactZip(string $file): void {
    $f=fopen($file,'rb'); need($f!==false,'Cannot read ZIP');
    try {
        $size=filesize($file);need($size>=22,'Truncated ZIP');
        fseek($f,max(0,$size-65557));$tail=stream_get_contents($f);$at=strrpos($tail,"PK\x05\x06");
        need($at!==false && strlen($tail)-$at>=22,'Missing ZIP end directory');
        $e=unpack('vdisk/vstart/ventriesDisk/ventries/Vbytes/Voffset/vcomment',substr($tail,$at+4,18));
        need($e['disk']===0 && $e['start']===0 && $e['entries']===2 && $e['entriesDisk']===2 &&
             $at+22+$e['comment']===strlen($tail),'Unsupported ZIP layout');
        need($e['bytes']<65536 && $e['offset']+$e['bytes']===$size-strlen($tail)+$at,'ZIP directory bounds');
        fseek($f,$e['offset']);$seen=[];$used=0;
        for($i=0;$i<2;$i++) {
            $h=fread($f,46); need(strlen($h)===46 && substr($h,0,4)==="PK\x01\x02",'Invalid ZIP directory entry');
            $flags=unpack('v',substr($h,8,2))[1];$method=unpack('v',substr($h,10,2))[1];
            $len=unpack('vname/vextra/vcomment',substr($h,28,6));$expanded=unpack('V',substr($h,24,4))[1];
            need(($flags&1)===0 && in_array($method,[0,8],true),'Encrypted/unsupported ZIP compression');
            $name=fread($f,$len['name']);
            need(in_array($name,['candidate.json','release.tar.gz'],true) && !isset($seen[$name]),'Unexpected/duplicate ZIP member');
            need($expanded<=($name==='candidate.json'?65536:268435456),'ZIP expansion bound');
            $mode=unpack('V',substr($h,38,4))[1]>>16;
            need(($mode&0170000)!==0120000,'ZIP symlink refused');$seen[$name]=true;
            fseek($f,$len['extra']+$len['comment'],SEEK_CUR);$used+=46+$len['name']+$len['extra']+$len['comment'];
        }
        need($used===$e['bytes'],'Unexpected ZIP directory tail');
    } finally {fclose($f);}
}
