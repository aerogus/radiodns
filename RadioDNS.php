<?php

declare(strict_types=1);

/**
 * RadioDNS - parsing des metadonnées via le protocole RadioDNS
 *
 * Usage:
 * // Pour Radio FM
 * $rdns = new RadioDNS();
 * $rdns->frequency = 102.3;
 * $rdns->ecc = 'E1';
 * $rdns->pi = 'F21D';
 * echo $rdns->getLogos();
 * echo $rdns->getShortName();
 * echo $rdns->getMediumName();
 *
 * // Pour Radio DAB
 * $rdns = new RadioDNS();
 * $rdns->ensemble_id = 'FFFF';
 * $rdns->servicd_id = 'FFFF';
 * $rdns->service_component_id = '0';
 * $rdns->ecc = 'E1';
 * echo $rdns->getLogos();
 * echo $rdns->getShortName();
 * echo $rdns->getMediumName();
 *
 * ex: http://nostalgiefrance.nrjaudio.fm/radiodns/spi/3.1/SI.xml
 * ex: http://10230.f21d.fe1.fm.radiodns.org/radiodns/spi/3.1/SI.xml
 *
 * couverture :
 * https://radiodns.org/coverage/#FRA
 *
 * dtd: https://www.worlddab.org/schemas/spi/spi_33.xsd
 * dtd: https://www.worlddab.org/schemas/spi/spi_31.xsd
 *
 * @author Guillaume Seznec <guillaume@seznec.fr>
 *
 * @see https://radiodns.org/campaigns/project-logo/walkthrough-finding-radio-station-logos/
 * @see https://www.etsi.org/deliver/etsi_ts/103200_103299/103270/01.04.01_60/ts_103270v010401p.pdf
 * @see https://www.etsi.org/deliver/etsi_ts/102800_102899/102818/03.05.01_60/ts_102818v030501p.pdf
 * @see https://www.etsi.org/deliver/etsi_ts/101400_101499/101499/03.02.01_60/ts_101499v030201p.pdf
 */
class RadioDNS
{
    /**
     * @var \SimpleXMLElement|false
     */
    protected \SimpleXMLElement|false $xml = false;

    /**
     * Fréquence en MHz
     *
     * @var ?float
     */
    public ?float $frequency = null;

    /**
     * Code PI, ex: F21D
     *
     * @var ?string
     * @see https://www.csa.fr/maradiofm/radiords_tableau
     */
    public ?string $pi = null;

    /**
     * ECC Country Code, ex: E1 (France)
     *
     * @var ?string
     * @see https://www.etsi.org/deliver/etsi_ts/101700_101799/101756/02.04.01_60/ts_101756v020401p.pdf
     */
    public ?string $ecc = null;

    /**
     * Mode de transmission (FM|DAB)
     *
     * @var ?string
     */
    public ?string $mode = null;

    /**
     * @var ?string
     * ex: FFFF
     */
    public ?string $ensemble_id = null;

    /**
     * @var ?string
     * ex: FFFF
     */
    public ?string $service_id = null;

    /**
     * @var ?string
     * ex: FFFF . tjrs mettre à '0' ?
     */
    public ?string $service_component_id = '0';

    /**
     * @return array<array<string,mixed>>
     */
    public function getLogos(): array
    {
        if ($this->xml === false) {
            $this->loadXml();
        }

        $logos = [];
        foreach ($this->xml->mediaDescription as $media) {
            if (!is_null($media->multimedia)) {
                $logos[] = [
                    'url' => strval($media->multimedia['url']),
                    'mime' => strval($media->multimedia['mimeValue']),
                    'width' => intval(strval($media->multimedia['width'])),
                    'height' => intval(strval($media->multimedia['height'])),
                ];
            }
        }

        return $logos;
    }

    /**
     * @return array<array<string,mixed>>
     */
    public function getBearers(): array
    {
        if ($this->xml === false) {
            $this->loadXml();
        }

        $bearers = [];
        if ($this->xml !== false) {
            foreach ($this->xml->bearer as $bearer) {
                $bearers[] = [
                    'id' => strval($bearer['id']),
                    'cost' => intval(strval($bearer['cost'])),
                ];
            }
        }

        return $bearers;
    }

    /**
     * @return string
     */
    public function getShortName(): string
    {
        if ($this->xml === false) {
            $this->loadXml();
        }

        if ($this->xml !== false) {
            return strval($this->xml->shortName);
        }
        return '';
    }

    /**
     * @return string
     */
    public function getMediumName(): string
    {
        if ($this->xml === false) {
            $this->loadXml();
        }

        if ($this->xml !== false) {
            return strval($this->xml->mediumName);
        }
        return '';
    }

    /**
     * @throws \Exception
     */
    protected function checkProps()
    {
        // check params obligatoires
        if (is_null($this->ecc)) {
            throw new \Exception('ecc not set');
        } elseif (is_null($this->mode)) {
            throw new \Exception('mode not set');
        } elseif ($this->mode === 'FM') {
            if (is_null($this->frequency)) {
                throw new \Exception('frequency not set');
            } elseif (is_null($this->pi)) {
                throw new \Exception('pi not set');
            }
        } elseif ($this->mode === 'DAB') {
            if (is_null($this->service_component_id)) {
                throw new \Exception('service_component_id not set');
            } elseif (is_null($this->service_id)) {
                throw new \Exception('service_id not set');
            } elseif (is_null($this->ensemble_id)) {
                throw new \Exception('ensemble_id not set');
            }
        } else {
            throw new \Exception('invalid mode');
        }

    }

    /**
     * @return void
     *
     * @throws \Exception
     */
    protected function loadXml(): void
    {
        $this->checkProps();

        $url = $this->getXmlUrl();
        $data = file_get_contents($url, true);

        if ($data !== false) {
            $data = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $data); // HACK !!
            $sxe = new \SimpleXMLElement($data);
            foreach ($sxe->service as $service) {
                foreach ($service->bearer as $bearer) {
                    // on filtre juste la station qui nous intéresse via la prop id de la balise bearer
                    if (in_array(strval($bearer['id']), [$this->getBearerId(), $this->getBearerIdJoker()], true)) {
                        $this->xml = $service;
                        return;
                    }
                }
            }
            foreach ($sxe->services->service as $service) {
                foreach ($service->bearer as $bearer) {
                    // on filtre juste la station qui nous intéresse via la prop id de la balise bearer
                    if (in_array(strval($bearer['id']), [$this->getBearerId(), $this->getBearerIdJoker()], true)) {
                        $this->xml = $service;
                        return;
                    }
                }
            }
        }
    }

    /**
     * @return string
     */
    protected function getGcc(): string
    {
        $this->checkProps();

        if ($this->mode === 'FM') {
            return strtolower(substr($this->pi, 0, 1)) . strtolower($this->ecc);
        } elseif ($this->mode === 'DAB') {
            return strtolower(substr($this->service_id, 0, 1)) . strtolower($this->ecc);
        }
        return '';
    }

    /**
     * @return string
     */
    protected function getBearerId(): string
    {
        $this->checkProps();

        $id  = strtolower($this->mode);
        $id .= ':';
        $id .= $this->getGcc();
        $id .= '.';
        if ($this->mode === 'FM') {
            $id .= strtolower($this->pi);
            $id .= '.';
            $id .= str_pad(strval(intval($this->frequency * 100)), 5, '0', STR_PAD_LEFT);
        } elseif ($this->mode === 'DAB') {
            $id .= strtolower($this->ensemble_id);
            $id .= '.';
            $id .= strtolower($this->service_id);
            $id .= '.';
            $id .= strtolower($this->service_component_id);
        }

        return $id;
    }

    /**
     * @return string
     */
    protected function getBearerIdJoker(): string
    {
        $this->checkProps();

        $id  = strtolower($this->mode);
        $id .= ':';
        $id .= $this->getGcc();
        $id .= '.';
        if ($this->mode === 'FM') {
            $id .= strtolower($this->pi);
            $id .= '.';
            $id .= '*';
        } elseif ($this->mode === 'DAB') {
            $id .= strtolower($this->ensemble_id);
            $id .= '.';
            $id .= strtolower($this->service_id);
            $id .= '.';
            $id .= strtolower($this->service_component_id);
        }

        return $id;
    }

    /**
     * @return string
     */
    public function getXmlUrl(): string
    {
        $fqdn_records = dns_get_record($this->getFqdn(), DNS_CNAME);
        if (count($fqdn_records) === 0) {
            throw new \Exception('no fqdn dns records');
        } else {
            $fqdn_last_record = array_pop($fqdn_records);
            $metadata_nslookup = '_radioepg._tcp.' . $fqdn_last_record['target'];
            $srv_records = dns_get_record($metadata_nslookup, DNS_SRV);
            if (count($srv_records) === 0) {
                throw new \Exception('no srv dns records for ' . $metadata_nslookup);
            } else {
                $host = $srv_records[0]['target'];
                $url = 'http://' . $host . '/radiodns/spi/3.1/SI.xml';
                return $url;
            }
            throw new \Exception('xml not found');
        }
    }

    /**
     * @return string
     */
    public function getFqdn(): string
    {
        $this->checkProps();

        $args = [];

        if ($this->mode === 'FM') {
            $args[] = strval(intval($this->frequency * 100));
            $args[] = strtolower($this->pi);
            $args[] = substr(strtolower($this->pi), 0, 1) . strtolower($this->ecc);
        } elseif ($this->mode === 'DAB') {
            $args[] = strtolower($this->service_component_id);
            $args[] = strtolower($this->service_id);
            $args[] = strtolower($this->ensemble_id);
            $args[] = substr(strtolower($this->service_id), 0, 1) . strtolower($this->ecc);
        }

        $args[] = strtolower($this->mode);
        $args[] = 'radiodns';
        $args[] = 'org';

        return implode('.', $args);
    }
}
