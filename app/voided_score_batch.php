<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/**
 * Ancien lot one-shot (août 2026) — vidé après application.
 * Les scores se saisissent désormais dans Admin → Résultats & scores manuels.
 *
 * @return array{scores: list, cancelled: array<int,string>}
 */
function voidedScoreBatchDefinition(): array
{
    return [
        'scores'    => [],
        'cancelled' => [],
    ];
}

/**
 * @return array{
 *   score_ok:int, score_err:int, cancel_ok:int, cancel_err:int, skip:int,
 *   mail_ok:int, mail_err:int, admin_mail:bool,
 *   errors:list<string>, mails:list<string>, log:list<string>
 * }
 */
function runVoidedScoreBatch(PDO $pdo, bool $sendMails = true): array
{
    unset($pdo, $sendMails);
    return [
        'score_ok'   => 0,
        'score_err'  => 0,
        'cancel_ok'  => 0,
        'cancel_err' => 0,
        'skip'       => 0,
        'mail_ok'    => 0,
        'mail_err'   => 0,
        'admin_mail' => false,
        'errors'     => ['Lot de correction vidé — utiliser Admin → Résultats & scores manuels.'],
        'mails'      => [],
        'log'        => ['Lot vide : rien à appliquer.'],
    ];
}

/**
 * @return array{mail_ok:int,mail_err:int,mails:list<string>,errors:list<string>,admin_mail:bool}
 */
function resendVoidedScoreBatchMails(PDO $pdo): array
{
    unset($pdo);
    return [
        'mail_ok'    => 0,
        'mail_err'   => 0,
        'mails'      => [],
        'errors'     => ['Lot vidé — plus de renvoi de mails de lot.'],
        'admin_mail' => false,
    ];
}
