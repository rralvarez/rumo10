<?php
declare(strict_types=1);
final class ClubBadge {
    public static function name(string $club):string{return Competition::clubs()[$club]['display_name']??$club;}
    public static function render(string $club): void {
        if(RumoHub::enabled() && !empty(RumoHub::catalog()['clubs'][$club]['badge_path'])){echo '<img class="club-badge" src="/escudo?club='.e(rawurlencode($club)).'&amp;v='.e(substr(RumoHub::catalog()['version'],0,12)).'" alt="Escudo de '.e($club).'" width="48" height="48" loading="lazy">';return;}
        static $logos;$logos??=is_file(dirname(__DIR__).'/database/club-badges.json')?json_decode(file_get_contents(dirname(__DIR__).'/database/club-badges.json'),true,512,JSON_THROW_ON_ERROR):[];
        if(isset($logos[$club]) && is_file(dirname(__DIR__).'/public'.$logos[$club]['path']))echo '<img class="club-badge" src="'.e($logos[$club]['path']).'" alt="Escudo de '.e($club).'" width="48" height="48" loading="lazy">';
        else echo '<span class="club-badge badge-pending" aria-hidden="true">'.e(mb_substr($club,0,2)).'</span>';
    }
}
