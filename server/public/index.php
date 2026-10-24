<?php
declare(strict_types=1);
require __DIR__.'/../private/bootstrap.php';
if(!installed()){header('Location: /install.php');exit;}
start_session();send_security_headers();
require_once __DIR__.'/../private/release.php';
$build=release_info();
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="theme-color" content="#0b5bd9"><meta name="description" content="Sector Sign — centro de mando de publicidad digital"><link rel="manifest" href="/manifest.webmanifest"><link rel="icon" href="/icons/icon.svg" type="image/svg+xml"><title>Sector Sign · Centro de mando</title><link rel="stylesheet" href="/assets/app.css?v=070"></head><body><div id="app"><div class="boot"><div class="brand-dot">S</div><p>Cargando Sector Sign…</p></div></div><div id="toast" role="status" aria-live="polite"></div><dialog id="modal"><div id="modal-inner"></div></dialog><script>window.SS_BOOT_RELEASE=<?=json_encode($build,JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_HEX_APOS)?>;window.SS_CSRF=<?=json_encode($_SESSION['csrf'],JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_HEX_APOS)?>;</script><script src="/assets/scheduler.js?v=070"></script><script src="/assets/programs-ui.js?v=070"></script><script src="/assets/app.js?v=070" defer></script></body></html>
