<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

try {
  require_once __DIR__ . '/../../../../core/php/core.inc.php';
  include_file('core', 'authentification', 'php');
  if (!isConnect('admin')) {
    throw new \Exception(__('401 - Accès non autorisé', __FILE__));
  }

  if (init('action') == 'geoapi') {
    $terme = urlencode(init('term'));
    // Requete CURL
    $c = curl_init();
    curl_setopt($c, CURLOPT_URL, 'https://geo.api.gouv.fr/communes?fields=departement,code&boost=population&limit=10&nom='.$terme);
    curl_setopt($c, CURLOPT_HEADER, false);
    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($c, CURLOPT_HTTPHEADER, array(
    	'Cache-Control: no-cache',
    	'content-type:application/json;charset=utf-8'
    ));
    $response = curl_exec($c);
    curl_close($c);
    if (!is_json($response)) {
      throw new Exception(__('Erreur reponse autocomplete', __FILE__) . $response);
    }
    $result = json_decode($response, true);
    $values = array();
    foreach ($result as $key => $value) {
      $ville = $value["nom"];
      $deptn = $value["departement"]["code"];
      $insee = $value["code"];
      $values[] = array(
        "label" => $ville. " (".$deptn.")",
        "value" => $ville. " (".$deptn.")",
        "insee" => $insee
      );
    }
    ajax::success($values);
  }

  throw new Exception(__('Aucune methode correspondante à : ', __FILE__) . init('action'));
} catch (\Exception $e) {
  ajax::error(displayExeption($e), $e->getCode());
}
