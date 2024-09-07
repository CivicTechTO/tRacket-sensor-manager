<!DOCTYPE html>
<html lang="en">
<head>
<?php require_once('/var/www/webcomand/comand.php'); ?>
    <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>tRacket</title>
    <meta name="description" content="" />
    <meta name="keywords" content="" />
    <link rel="stylesheet" href="/leaflet/leaflet.css" />
    <link rel="stylesheet" href="/css/Control.Geocoder.css" />
    <link rel="stylesheet" href="/css/web.css" />
    <link rel="shortcut icon" href="/favicon.ico" />
</head>
<body class="view<?= $page_class ? ' ' . $page_class : '' ?>">
    <header>
        <h1><a href="https://tracket.info/">tRacket</a></h1>
    </header>
    <main<?= isset($main_class) && $main_class != '' ? 'class="' . $main_class . '"' : '' ?>>
<?= $content ?? '' ?>
    </main>
    <footer>
        <ul>
            <li><a href="/privacy-policy/index.html">Privacy Policy</a></li>
            <li><a href="/terms-of-use/index.html">Terms of Use</a></li>
        </ul>
    </footer>
    <script src="/js/jquery.min.js"></script>
    <script src="/leaflet/leaflet.js"></script>
    <script src="/js/Leaflet.Editable.js"></script>
    <script src="/js/Control.Geocoder.js"></script>
    <script src="/js/script.js"></script>
</body>
</html>
