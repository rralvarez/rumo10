<?php
declare(strict_types=1);
namespace Rumo\Publisher;
require_once __DIR__.'/Core.php';
final class PrivateBackup {
    public static function capture(array $sources,string $dest): array {
        need(count($sources)>0,'Explicit config/storage backup roots required');
        privateDir($dest); $files=[]; $bytes=0;
        foreach($sources as $label=>$source) {
            relative($label);need(!str_contains($label,'/'),'Backup label must be one component');
            need(file_exists($source) && !is_link($source),'Backup source must be explicitly resolved physical path');
            $paths=is_dir($source)?new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source,\FilesystemIterator::SKIP_DOTS)):[$source];
            foreach($paths as $item) {
                $path=is_string($item)?$item:$item->getPathname();
                need(!is_link($path),'Backup symlink needs explicit resolved-source approval');
                if(is_dir($path))continue;
                $before=sameFileState($path);$bytes+=$before['bytes'];
                need($bytes<1024*1024*1024*10,'Backup safety size bound exceeded');
                $rel=$label.(is_dir($source)?'/'.str_replace('\\','/',substr($path,strlen($source)+1)):'');
                $target=pathAt($dest,$rel);parents($target);
                need(copy($path,$target),'Private backup copy failed');
                need(digest($target)===$before['hash'] && sameFileState($path)===$before,'Shared data changed during backup');
                chmod($target,0600);$files[$rel]=$before['hash'];
            }
        }
        record($dest.'/inventory.json',['files'=>$files,'bytes'=>$bytes]);
        // Real restoration rehearsal into a separate private directory. Never an operational restore.
        privateDir($dest.'/rehearsal');
        foreach($files as $rel=>$hash) {
            $target=pathAt($dest.'/rehearsal',$rel);parents($target);
            need(copy(pathAt($dest,$rel),$target) && digest($target)===$hash,'Private backup restore rehearsal failed');
        }
        return ['files'=>$files,'bytes'=>$bytes,'restore_tested'=>true,'inventory_sha256'=>digest($dest.'/inventory.json')];
    }
}
