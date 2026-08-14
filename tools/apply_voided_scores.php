<?php
/**
 * CLI — lot one-shot vidé. Utiliser Admin → Résultats & scores manuels.
 *
 *   php tools/apply_voided_scores.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/app/bootstrap.php';

$def = voidedScoreBatchDefinition();
echo "Lot de correction one-shot : vide.\n";
echo 'scores=' . count($def['scores']) . ' cancelled=' . count($def['cancelled']) . "\n";
echo "Saisir les scores dans Admin → Résultats & scores manuels.\n";
exit(0);
