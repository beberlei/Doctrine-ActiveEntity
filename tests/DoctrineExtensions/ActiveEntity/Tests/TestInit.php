<?php

require_once $GLOBALS['DOCTRINE2_PATH'] . "/vendor/doctrine-common/lib/Doctrine/Common/ClassLoader.php";

$loader = new Doctrine\Common\ClassLoader('Doctrine\Common', $GLOBALS['DOCTRINE2_PATH'] . "/vendor/doctrine-common/lib");
$loader->register();

$loader = new Doctrine\Common\ClassLoader('Doctrine\DBAL', $GLOBALS['DOCTRINE2_PATH'] . "/vendor/doctrine-dbal/lib");
$loader->register();

$loader = new Doctrine\Common\ClassLoader('Doctrine\ORM', $GLOBALS['DOCTRINE2_PATH'] . "/");
$loader->register();

$loader = new Doctrine\Common\ClassLoader('DoctrineExtensions\ActiveEntity\Models', __DIR__ . "/../../../");
$loader->register();

$loader = new Doctrine\Common\ClassLoader('DoctrineExtensions\ActiveEntity', __DIR__ . "/../../../../lib/");
$loader->register();