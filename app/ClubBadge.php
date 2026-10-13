<?php
declare(strict_types=1);
final class ClubBadge {
    public static function render(string $club): void {
        static $logos;$logos??=is_file(dirname(__DIR__).'/database/club-badges.json')?json_decode(file_get_contents(dirname(__DIR__).'/database/club-badges.json'),true,512,JSON_THROW_ON_ERROR):[];
        if(isset($logos[$club]) && is_file(dirname(__DIR__).'/public'.$logos[$club]['path']))echo '<img class="club-badge" src="'.e($logos[$club]['path']).'" alt="Escudo de '.e($club).'" width="48" height="48" loading="lazy">';
        else echo '<span class="club-badge badge-pending" aria-hidden="true">'.e(mb_substr($club,0,2)).'</span>';
    }
}
