<?php
declare(strict_types=1);
final class FeatureSchema {
 public const ACHIEVEMENTS=['rumo10_conquistas','rumo10_conquistas_carreiras','rumo10_conquistas_eventos'];
 public const RECONCILIATION=['rumo10_conquistas_reconciliacoes','rumo10_conquistas_fontes'];
 public static function ready(Repository $r,array $tables):bool {
  $marks=implode(',',array_fill(0,count($tables),'?'));
  return (int)$r->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.$marks.')',$tables)->fetchColumn()===count($tables);
 }
 public static function hub(Repository $r,int $account):int {
  $id=(int)$r->query('SELECT hub_usuario_id FROM rumo10_identidades WHERE conta_id=?',[$account])->fetchColumn();
  if($id<1)throw new InvalidArgumentException('Sua identidade central não está vinculada. Entre novamente.');return $id;
 }
}
