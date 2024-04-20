#!/usr/bin/env php
<?php

/**
 * Utilisation de l'api RadioDNS
 *
 * @param string -c --ecc country code
 * @param string -m --mode FM|DAB
 * @param string -f --frequency fréquence en MHz (FM)
 * @param string -p --pi code Pi (FM)
 * @param string -e --ensemble-id identifiant du multiplex (DAB)
 * @param string -s --service-id identifiant du service (DAB)
 *
 * @author Guillaume Seznec <guillaume@seznec.fr>
 */

declare(strict_types=1);

require_once __DIR__ . '/RadioDNS.php';

// parsing des paramètres
$short_options = "c:m:f:p:e:s:";
$long_options = ["ecc:", "mode:", "frequency:", "pi:", "ensemble-id:", "service-id:"];
$options = getopt($short_options, $long_options);

$ecc = $mode = $frequency = $pi = $ensemble_id = $service_id = null;
if (isset($options["c"]) || isset($options["ecc"])) {
    $ecc = isset($options["c"]) ? $options["c"] : $options["ecc"];
} else {
    die('no ecc');
}
if (isset($options["m"]) || isset($options["mode"])) {
    $mode = isset($options["m"]) ? $options["m"] : $options["mode"];
} else {
    die('no mode');
}
if (isset($options["f"]) || isset($options["frequency"])) {
    $frequency = isset($options["f"]) ? floatval($options["f"]) : floatval($options["frequency"]);
}
if (isset($options["p"]) || isset($options["pi"])) {
    $pi = isset($options["p"]) ? $options["p"] : $options["pi"];
}
if (isset($options["e"]) || isset($options["ensemble-id"])) {
    $ensemble_id = isset($options["e"]) ? $options["e"] : $options["ensemble-id"];
}
if (isset($options["s"]) || isset($options["service-id"])) {
    $service_id = isset($options["s"]) ? $options["s"] : $options["service-id"];
}

$rdns = new RadioDNS();
$rdns->ecc = $ecc;
$rdns->mode = $mode;
if (!is_null($frequency)) {
    $rdns->frequency = $frequency;
}
if (!is_null($pi)) {
    $rdns->pi = $pi;
}
if (!is_null($ensemble_id)) {
    $rdns->ensemble_id = $ensemble_id;
}
if (!is_null($service_id)) {
    $rdns->service_id = $service_id;
}

try {
    echo "xmlUrl     : " . $rdns->getXmlUrl() . "\n";
    echo "shortName  : " . $rdns->getShortName() . "\n";
    echo "mediumName : " . $rdns->getMediumName() . "\n";
    echo "logoUrls   :\n";
    foreach ($rdns->getLogos() as $logo) {
        echo " - " . $logo['url'] . " (" . $logo['mime'] . ") " . $logo['width'] . "x" . $logo['height'] . "\n";
    }
    echo "bearers    :\n";
    foreach ($rdns->getBearers() as $bearer) {
        echo " - " . $bearer['id'] . " cost=" . $bearer['cost'] . "\n";
    }
} catch (\Exception $e) {
    echo "ERROR:" . $e->getMessage() . "\n";
}
