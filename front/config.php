<?php

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

if (isset($_POST['update'])) {
    \GlpiPlugin\Matomo\Config::saveConfig($_POST);
    Html::back();
}

Html::header('Matomo Tag Manager', '', 'config', 'plugins');
\GlpiPlugin\Matomo\Config::showConfigForm();
Html::footer();
