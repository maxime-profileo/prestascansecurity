<?php
/**
 * Copyright 2023 Profileo Group <contact@profileo.com> (https://www.profileo.com/fr/)
 *
 * For questions or comments about this software, contact Maxime Morel-Bailly <security@prestascan.com>
 * List of required attribution notices and acknowledgements for third-party software can be found in the NOTICE file.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @author    Profileo Group - Complete list of authors and contributors to this software can be found in the AUTHORS file.
 * @copyright Since 2023 Profileo Group <contact@profileo.com> (https://www.profileo.com/fr/)
 * @license   https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 */

namespace PrestaScan\Reports;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CoreVulnerabilitiesReport extends Report
{
    public $reportName = 'core_vulnerabilities';

    public function generate($automatic = false, $automaticScanId = '')
    {
        $postBody = array(
            'ps_version' => \PrestaScan\Tools::getPrestashopVersion(),
            'automatic' => $automatic,
            'automatic_scan_id' => $automaticScanId,
        );
        $request = new \PrestaScan\Api\Request(
            'prestascan-api/v1/scan/core-vulnerabilities',
            'POST',
            $postBody
        );

        $jobData = $postBody;
        return parent::generateReport($request, $postBody, $automatic);
    }

    public function save($payload)
    {
        $countVulnerabilitiesCritical = 0;
        $countVulnerabilitiesHigh = 0;
        $countVulnerabilitiesMedium = 0;
        $countVulnerabilitiesLow = 0;
        $criticity = 'low';
        $instance = \Module::getInstanceByName('prestascansecurity');
        foreach ($payload['result'] as $k => $vulnerability) {
            $payload['result'][$k]['cve']['value'] = substr(
                $vulnerability['cve']['value'],
                strrpos($vulnerability['cve']['value'], '/CVE-') + 5
            );

            $payload['result'][$k]['link'] = $vulnerability['cve']['value'];

            $payload['result'][$k]['fo']['value'] = isset($payload['result'][$k]['fo']['value']) && (int) $payload['result'][$k]['fo']['value'] === 1
                ? 'Yes'
                : ((isset($payload['result'][$k]['fo']['value']) && (int) $payload['result'][$k]['fo']['value']) === 0
                    ? 'No'
                    : ''
                );

            $payload['result'][$k]['bo']['value'] = isset($payload['result'][$k]['bo']['value']) && (int) $payload['result'][$k]['bo']['value'] === 1
                ? 'Yes'
                : ((isset($payload['result'][$k]['bo']['value']) && (int) $payload['result'][$k]['bo']['value']) === 0
                    ? 'No'
                    : ''
                );

            $payload['result'][$k]['type']['value'] = isset($payload['result'][$k]['type']) && isset($payload['result'][$k]['type']['value'])
                ? $instance->getVulnerabilityExtendedNameTranslated($payload['result'][$k]['type']['value'])
                : '';

            if (isset($vulnerability['severity']['value'])) {
                $severity = $vulnerability['severity']['value'];
                switch (strtolower($severity)) {
                    case 'critical':
                        $countVulnerabilitiesCritical++;
                        $criticity = $severity;
                        break;
                    case 'high':
                        $countVulnerabilitiesHigh++;
                        $criticity = $criticity !== 'critical' ? $severity : $criticity;
                        break;
                    case 'medium':
                        $countVulnerabilitiesMedium++;
                        $criticity = $criticity !== 'critical' && $criticity !== 'medium' ? $severity : $criticity;
                        break;
                    case 'low':
                        $countVulnerabilitiesLow++;
                        break;
                    default:
                        break;
                }
            }
        }
        $reportSummary = [];

        $reportSummary['prestashop_version'] = \PrestaScan\Tools::getPrestashopVersion();
        $reportSummary['scan_result_total'] = count($payload['result']);
        $reportSummary['scan_result_criticity'] = $countVulnerabilitiesCritical > 0 ? 'high' : ($countVulnerabilitiesHigh > 0 ? 'medium' : ($countVulnerabilitiesMedium > 0 ? 'medium' : ''));
        $reportSummary['scan_result_ttotal'] = $countVulnerabilitiesCritical + $countVulnerabilitiesHigh + $countVulnerabilitiesMedium + $countVulnerabilitiesLow;
        $reportSummary['total_critical'] = $countVulnerabilitiesCritical;
        $reportSummary['total_high'] = $countVulnerabilitiesHigh;
        $reportSummary['total_medium'] = $countVulnerabilitiesMedium;
        $reportSummary['total_low'] = $countVulnerabilitiesLow;

        return parent::saveReport($payload['completion_date'], $reportSummary, $payload);
    }
}
