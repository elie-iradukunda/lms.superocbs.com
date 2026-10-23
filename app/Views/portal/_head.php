<?php
/** The customer pages: the company's name and logo, nothing of the office behind it. */
$baseUrl = config('app.base_url', '');
$company = company_name();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(($title ?? 'Customer') . ' | ' . $company) ?></title>
  <link rel="icon" href="<?= $baseUrl ?>/assets/img/logistics-logo.svg">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/feather.css">
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/app-light.css">
  <link rel="stylesheet" href="<?= asset('assets/css/logistics.css') ?>">
  <link rel="stylesheet" href="<?= asset('assets/css/logistics-modules.css') ?>">
  <style>
    body { background: #f4f6f9; }
    .portal-top { background: #1f4e8c; color: #fff; padding: 14px 0; }
    .portal-top a { color: #fff; }
    .portal-top img { height: 34px; }
    .portal-wrap { max-width: 860px; margin: 24px auto; padding: 0 16px; }
    .portal-card { background: #fff; border-radius: 10px; box-shadow: 0 2px 12px rgba(0,0,0,.06); padding: 24px; }
    .portal-nav a { margin-left: 16px; font-weight: 600; }
    .portal-steps { list-style: none; padding: 0; margin: 0; border-left: 3px solid #dfe4ea; }
    .portal-steps li { position: relative; padding: 0 0 14px 18px; }
    .portal-steps li::before { content: ''; position: absolute; left: -8px; top: 4px; width: 13px; height: 13px; border-radius: 50%; background: #dfe4ea; }
    .portal-steps li.is-done::before { background: #2f855a; }
  </style>
</head>
<body>
<header class="portal-top">
  <div class="portal-wrap d-flex align-items-center justify-content-between" style="margin-top:0;margin-bottom:0">
    <a href="<?= url('request-transport') ?>" class="d-flex align-items-center text-decoration-none"><img src="<?= url(['brand', 'logo']) ?>" alt="" onerror="this.remove()"><strong class="ml-2"><?= e($company) ?></strong></a>
    <nav class="portal-nav"><a href="<?= url('request-transport') ?>">Request transport</a><a href="<?= url('track') ?>">Track a shipment</a></nav>
  </div>
</header>
<main class="portal-wrap">
