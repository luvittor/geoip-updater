<?php
if ($_SERVER['REQUEST_URI'] === '/many') { header('Location: /once', true, 302); exit; }
if ($_SERVER['REQUEST_URI'] === '/once') { header('Location: /final', true, 302); exit; }
if ($_SERVER['REQUEST_URI'] === '/cross') { header('Location: '.getenv('CROSS_TARGET'), true, 302); exit; }
if ($_SERVER['REQUEST_URI'] === '/loop') { header('Location: /loop', true, 302); exit; }
if ($_SERVER['REQUEST_URI'] === '/capture') { echo $_SERVER['HTTP_AUTHORIZATION'] ?? 'none'; exit; }
header('ETag: final'); echo 'done';
