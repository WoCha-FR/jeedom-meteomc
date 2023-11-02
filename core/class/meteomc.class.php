<?php
/* This file is part of Plugin zwavejs for jeedom.
*
* Plugin zwavejs for jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Plugin zwavejs for jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Plugin zwavejs for jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

class meteomc extends eqLogic {
  /*     * ***********************Methode static*************************** */
  public static function cron($_eqLogic_id = null) {
    // eqLogic fournis ?
    if ($_eqLogic_id == null) {
      $eqLogics = self::byType(__CLASS__, true);
    } else {
      $eqLogics = array(self::byId($_eqLogic_id));
    }
    // Parcours
    foreach ($eqLogics as $meteo) {
      // Actif ?
      if ($meteo->getIsEnable()) {
        // Mise à jour toutes les heures et 1 minute
        if (date('i') == '01') {
          log::add(__CLASS__, 'debug', 'Update data for : '.$meteo->getName());
          $meteo->updateFromMC();
        }
      }
    }
  }

  public static function request($_path, $_data = null, $_type = 'GET') {
    $url = 'https://api.meteo-concept.com/api/';
    $url.= $_path;
    if ($_data !== null && $_type == 'GET') {
      $url .='?'.http_build_query($_data);
    }
    $request_http = new com_http($url);
    $request_http->setHeader(array(
      'Content-Type: application/json',
      'Authorization: Bearer '.config::byKey('meteomcapikey', __CLASS__, '')
    ));
    $return = json_decode($request_http->exec(30,1), true);
    $return = is_json($return, $return);
    // Erreur
    if (isset($return['code'])) {
      log::add(__CLASS__, 'error', 'Code: ' . $return['code'] . ' ' . $return['message'] . ' ' . $url);
    }
    return $return;
  }

  public function updateFromMC() {
    // Vérifications
    if ( config::byKey('meteomcapikey', __CLASS__, '') == '') {
      throw new Exception(__('API Meteo Concept non renseigné dans la configuration', __FILE__));
    }
    if ($this->getConfiguration('meteomcinsee') == '') {
      throw new Exception(__('L\'identifiant de la ville ne peut être vide', __FILE__));
    }
    log::add(__CLASS__, 'debug', __FUNCTION__ . ' ' . $this->getName());
    // Variables
    $fileMC = __DIR__ . '/../../data/'.$this->getId().'_meteomc.json';
    $update = false;
    $newData = array();
    // Paramètres
    $_insee = $this->getConfiguration('meteomcinsee');
    // Ephemerides
    for ($i=0; $i <= 4; $i++) {
      $result = self::request('ephemeride/' . $i, array('insee'=>$_insee));
      $newData['j'.$i] = $result['ephemeride'];
    }
    // Jours
    $result = self::request('forecast/daily', array('insee'=>$_insee));
    foreach ($result['forecast'] as &$_day) {
      if ($_day['day'] > 5) {
        continue;
      }
      $_day['update'] = $result['update'];
      $newData['j'.$_day['day']] = array_merge($newData['j'.$_day['day']], $_day);
    }
    // Périodes
    $result = self::request('forecast/daily/periods', array('insee'=>$_insee));
    foreach ($result['forecast'] as &$_periods) {
      // Parcours des jours
      foreach ($_periods as $_period) {
        if ($_period['day'] > 5) {
          continue;
        }
        $_period['update'] = $result['update'];
        $newData['j'.$_period['day']]['p'.$_period['period']] = $_period;
      }
    }
    // Données mises à jour ?
    if (file_exists($fileMC)) {
      $oldData = json_decode(file_get_contents($fileMC), true);
      if ($newData !== $oldData) {
        $update = true;
        file_put_contents($fileMC, json_encode($newData));
        log::add(__CLASS__, 'debug', __FUNCTION__ . ' Updated data received.');
      }
      unset($oldData);
      log::add(__CLASS__, 'debug', __FUNCTION__ . ' Data up to date. No update.');
    } else {
      $update = true;
      file_put_contents($fileMC, json_encode($newData));
      log::add(__CLASS__, 'debug', __FUNCTION__ . ' New data received.');
    }
    // Mise à jour si besoin
    if ($update) {
      $this->updateCmds();
    }
  }

  public function updateCmds() {
    log::add(__CLASS__, 'debug', __FUNCTION__ . ' ' . $this->getName());
    $fileMC = __DIR__ . '/../../data/'.$this->getId().'_meteomc.json';
    if (file_exists($fileMC)) {
      $datas = json_decode(file_get_contents($fileMC), true);
      if ($datas === null) {
        log::add(__CLASS__, 'warning', __FUNCTION__ . ': JSON error for ' . $this->getName() . ' => '.json_last_error_msg());
        @unlink($fileMC);
        return false;
      }
    } else {
      log::add(__CLASS__, 'warning', __FUNCTION__ . ': No JSON file for ' . $this->getName());
      return false;
    }
    // Parcours des jours
    for ($i=0; $i <=4; $i++) {
      $jour = $datas['j'.$i];
      // Traitement initial des données
      $collectDate = (new DateTime($jour['update']))->format('Y-m-d H:i:s');
      unset($jour['latitude'],$jour['longitude'],$jour['insee'],$jour['update'],$jour['cp'],$jour['etp'],$jour['gustx'],$jour['day'],$jour['moon_age']);
      // Parcours des elements
      foreach($jour as $_key => $_value) {
        // On traite la période
        if ($_key == 'p0' || $_key == 'p1' || $_key == 'p2' || $_key == 'p3' || $_key == 'p4') {
          // Génération fin du logicalId
          $endId = '_j'.$_value['day'].'p'.$_value['period'];
          $pcollectDate = (new DateTime($_value['update']))->format('Y-m-d H:i:s');
          unset($_value['update']);
          // Parcours des données de la période en cours
          foreach($_value as $_pkey => $_pvalue) {
            // Mise en forme
            if ( $_pkey == 'datetime') {
              $_pvalue = (new DateTime($_pvalue . ' midnight'))->getTimestamp();
            }
            if ( $_pkey == 'weather') {
              $_txtValue = self::getConditionText($_pvalue);
              $this->checkAndUpdateCmd('weathertxt' . $endId, $_txtValue, $pcollectDate);
            }
            if ( $_pkey == 'period') {
              $_pvalue = self::getPeriodName($_pvalue);
            }
            $this->checkAndUpdateCmd($_pkey . $endId, $_pvalue, $pcollectDate);
          }
          continue;
        }
        // Mise en forme
        if ( $_key == 'datetime') {
          $_value = (new DateTime($_value . ' midnight'))->getTimestamp();
        }
        if ( $_key == 'weather') {
          $_txtValue = self::getConditionText($_value);
          $this->checkAndUpdateCmd('weathertxt_j' . $i, $_txtValue, $collectDate);
        }
        $this->checkAndUpdateCmd($_key.'_j'.$i, $_value, $collectDate);
      }
    }
    // Mise a jour Widget
    log::add(__CLASS__, 'debug',  __FUNCTION__ . ': refresh widget requested for ' . $this->getName());
    $this->refreshWidget();
  }

  public static function getConditionText($_condition) {
    $textes = array (
      0 => 'Soleil',
      1 => 'Peu nuageux',
      2 => 'Ciel voilé',
      3 => 'Nuageux',
      4 => 'Très nuageux',
      5 => 'Couvert',
      6 => 'Brouillard',
      7 => 'Brouillard givrant',
      10 => 'Pluie faible',
      11 => 'Pluie modérée',
      12 => 'Pluie forte',
      13 => 'Pluie faible verglaçante',
      14 => 'Pluie modérée verglaçante',
      15 => 'Pluie forte verglaçante',
      16 => 'Bruine',
      20 => 'Neige faible',
      21 => 'Neige modérée',
      22 => 'Neige forte',
      30 => 'Pluie et neige mêlées faibles',
      31 => 'Pluie et neige mêlées modérées',
      32 => 'Pluie et neige mêlées fortes',
      40 => 'Averses de pluie locales et faibles',
      41 => 'Averses de pluie locales',
      42 => 'Averses locales et fortes',
      43 => 'Averses de pluie faibles',
      44 => 'Averses de pluie',
      45 => 'Averses de pluie fortes',
      46 => 'Averses de pluie faibles et fréquentes',
      47 => 'Averses de pluie fréquentes',
      48 => 'Averses de pluie fortes et fréquentes',
      60 => 'Averses de neige localisées et faibles',
      61 => 'Averses de neige localisées',
      62 => 'Averses de neige localisées et fortes',
      63 => 'Averses de neige faibles',
      64 => 'Averses de neige',
      65 => 'Averses de neige fortes',
      66 => 'Averses de neige faibles et fréquentes',
      67 => 'Averses de neige fréquentes',
      68 => 'Averses de neige fortes et fréquentes',
      70 => 'Averses de pluie et neige mêlées localisées et faibles',
      71 => 'Averses de pluie et neige mêlées localisées',
      72 => 'Averses de pluie et neige mêlées localisées et fortes',
      73 => 'Averses de pluie et neige mêlées faibles',
      74 => 'Averses de pluie et neige mêlées',
      75 => 'Averses de pluie et neige mêlées fortes',
      76 => 'Averses de pluie et neige mêlées faibles et nombreuses',
      77 => 'Averses de pluie et neige mêlées fréquentes',
      78 => 'Averses de pluie et neige mêlées fortes et fréquentes',
      100 => 'Orages faibles et locaux',
      101 => 'Orages locaux',
      102 => 'Orages fort et locaux',
      103 => 'Orages faibles',
      104 => 'Orages',
      105 => 'Orages forts',
      106 => 'Orages faibles et fréquents',
      107 => 'Orages fréquents',
      108 => 'Orages forts et fréquents',
      120 => 'Orages faibles et locaux de neige ou grésil',
      121 => 'Orages locaux de neige ou grésil',
      122 => 'Orages locaux de neige ou grésil',
      123 => 'Orages faibles de neige ou grésil',
      124 => 'Orages de neige ou grésil',
      125 => 'Orages de neige ou grésil',
      126 => 'Orages faibles et fréquents de neige ou grésil',
      127 => 'Orages fréquents de neige ou grésil',
      128 => 'Orages fréquents de neige ou grésil',
      130 => 'Orages faibles et locaux de pluie et neige mêlées ou grésil',
      131 => 'Orages locaux de pluie et neige mêlées ou grésil',
      132 => 'Orages fort et locaux de pluie et neige mêlées ou grésil',
      133 => 'Orages faibles de pluie et neige mêlées ou grésil',
      134 => 'Orages de pluie et neige mêlées ou grésil',
      135 => 'Orages forts de pluie et neige mêlées ou grésil',
      136 => 'Orages faibles et fréquents de pluie et neige mêlées ou grésil',
      137 => 'Orages fréquents de pluie et neige mêlées ou grésil',
      138 => 'Orages forts et fréquents de pluie et neige mêlées ou grésil',
      140 => 'Pluies orageuses',
      141 => 'Pluie et neige mêlées à caractère orageux',
      142 => 'Neige à caractère orageux',
      210 => 'Pluie faible intermittente',
      211 => 'Pluie modérée intermittente',
      212 => 'Pluie forte intermittente',
      220 => 'Neige faible intermittente',
      221 => 'Neige modérée intermittente',
      222 => 'Neige forte intermittente',
      230 => 'Pluie et neige mêlées',
      231 => 'Pluie et neige mêlées',
      232 => 'Pluie et neige mêlées',
      235 => 'Averses de grêle'
    );
    return $textes[$_condition];
  }

  public static function getConditionImg($_condition, $_nuit = false) {
    switch($_condition) {
      case "43":
      case "46":
      case "210":
        $img = "40";
        break;
      case "44":
      case "47":
      case "211":
        $img = "41";
        break;
      case "45":
      case "48":
      case "212":
        $img = "42";
        break;
      case "63":
      case "66":
      case "220":
        $img = "60";
        break;
      case "64":
      case "67":
      case "221":
        $img = "61";
        break;
      case "65":
      case "68":
      case "222":
        $img = "62";
        break;
      case "73":
      case "76":
        $img = "70";
        break;
      case "74":
      case "77":
        $img = "71";
        break;
      case "75":
      case "78":
        $img = "72";
        break;
      case "106":
        $img = "103";
        break;
      case "107":
        $img = "104";
        break;
      case "108":
        $img = "105";
        break;
      case "122":
        $img = "121";
        break;
      case "126":
        $img = "123";
        break;
      case "125":
      case "127":
      case "128":
        $img = "124";
        break;
      case "136":
        $img = "133";
        break;
      case "137":
        $img = "134";
        break;
      case "138":
        $img = "135";
        break;
      case "230":
        $img = "30";
        break;
      case "231":
        $img = "31";
        break;
      case "232":
        $img = "32";
        break;
      default :
        $img = $_condition;
    }
    // Mode nuit ?
    if ($_nuit) {
      $imgdir = __DIR__ . '/../template/images/';
      if (@file_exists($imgdir . $img . 'n.svg')) {
        return $img . 'n.svg';
      }
    }
    return $img . '.svg';
  }

  public static function getPeriodName($_periodId) {
    $values = array (
      0 => __('Nuit', __FILE__),
      1 => __('Matin', __FILE__),
      2 => __('Après-midi', __FILE__),
      3 => __('Soir', __FILE__)
    );
    return $values[$_periodId];
  }

  public static function getJourName($_jourId) {
    $values = array(
      1 => __('Lundi', __FILE__),
      2 => __('Mardi', __FILE__),
      3 => __('Mercredi', __FILE__),
      4 => __('Jeudi', __FILE__),
      5 => __('Vendredi', __FILE__),
      6 => __('Samedi', __FILE__),
      7 => __('Dimanche', __FILE__)
    );
    return $values[$_jourId];
  }

  /*     * *********************Methode d'instance************************* */
  public function preUpdate() {
    // Ville ne peut etre vide
    if ($this->getConfiguration('meteomcinsee') == '') {
      throw new Exception(__('L\'identifiant de la ville ne peut être vide', __FILE__));
    }
    // eqLogicId
    $_newEqLogicID = $this->getConfiguration('meteomcinsee');
    if ($this->getLogicalId() == '') {
      if (is_object(self::byLogicalId($_newEqLogicID, __CLASS__))) {
        throw new Exception(__('Un équipement identique existe déjà.', __FILE__));
      }
      $this->setLogicalId($_newEqLogicID);
    }
  }

  public function postSave() {
    log::add(__CLASS__, 'debug', __FUNCTION__ . ': ' . $this->getName());
    // Commandes des Jours
    $cmds = array(
      'datetime'=>array('label'=>__("Timestamp", __FILE__),'subtype'=>'numeric','unit'=>''),
      'sunrise'=>array('label'=>__("Lever soleil", __FILE__),'subtype'=>'string','unit'=>'','generic'=>'WEATHER_SUNSET'),
      'sunset'=>array('label'=>__("Coucher soleil", __FILE__),'subtype'=>'string','unit'=>'','generic'=>'WEATHER_SUNRISE'),
      'weather'=>array('label'=>__("Code Temps sensible", __FILE__),'subtype'=>'numeric','unit'=>'','generic'=>'WEATHER_CONDITION_ID'),
      'weathertxt'=>array('label'=>__("Temps sensible", __FILE__),'subtype'=>'string','unit'=>'','generic'=>'WEATHER_CONDITION'),
      'tmin'=>array('label'=>__("Température mini", __FILE__),'subtype'=>'numeric','unit'=>'°C','generic'=>'WEATHER_TEMPERATURE_MIN'),
      'tmax'=>array('label'=>__("Température maxi", __FILE__),'subtype'=>'numeric','unit'=>'°C','generic'=>'WEATHER_TEMPERATURE_MAX'),
      'wind10m'=>array('label'=>__("Vent moyen", __FILE__),'subtype'=>'numeric','unit'=>'km/h','generic'=>'WEATHER_WIND_SPEED'),
      'gust10m'=>array('label'=>__("Rafales", __FILE__),'subtype'=>'numeric','unit'=>'km/h','generic'=>'WEATHER_WIND_SPEED'),
      'dirwind10m'=>array('label'=>__("Direction du vent", __FILE__),'subtype'=>'numeric','unit'=>'°','generic'=>'WEATHER_WIND_DIRECTION'),
      'rr10'=>array('label'=>__("Cumul de pluie", __FILE__),'subtype'=>'numeric','unit'=>'mm','generic'=>'RAIN_TOTAL'),
      'rr1'=>array('label'=>__("Cumul de pluie max", __FILE__),'subtype'=>'numeric','unit'=>'mm','generic'=>'RAIN_TOTAL'),
      'duration_day'=>array('label'=>__("Durée jour", __FILE__),'subtype'=>'string','unit'=>''),
      'diff_duration_day'=>array('label'=>__("Variation de durée", __FILE__),'subtype'=>'numeric','unit'=>'m'),
      'sun_hours'=>array('label'=>__("Ensoleillement", __FILE__),'subtype'=>'numeric','unit'=>'h'),
      'moon_phase'=>array('label'=>__("Phase de lune", __FILE__),'subtype'=>'string','unit'=>''),
      'probarain'=>array('label'=>__("Risque de pluie", __FILE__),'subtype'=>'numeric','unit'=>'%'),
      'probafrost'=>array('label'=>__("Risque de gel", __FILE__),'subtype'=>'numeric','unit'=>'%'),
      'probafog'=>array('label'=>__("Risque de brouillard", __FILE__),'subtype'=>'numeric','unit'=>'%'),
      'probawind70'=>array('label'=>__("Risque de vent 70 kmh", __FILE__),'subtype'=>'numeric','unit'=>'%'),
      'probawind100'=>array('label'=>__("Risque de vent 100 kmh", __FILE__),'subtype'=>'numeric','unit'=>'%')
    );
    // Commandes des Périodes
    $cmdsp = array(
      'datetime'=>array('label'=>__("Timestamp", __FILE__),'subtype'=>'numeric','unit'=>''),
      'period'=>array('label'=>__("Période", __FILE__),'subtype'=>'string','unit'=>''),
      'weather'=>array('label'=>__("Code Temps sensible", __FILE__),'subtype'=>'numeric','unit'=>'','generic'=>'WEATHER_CONDITION_ID'),
      'weathertxt'=>array('label'=>__("Temps sensible", __FILE__),'subtype'=>'string','unit'=>'','generic'=>'WEATHER_CONDITION'),
      'temp2m'=>array('label'=>__("Température", __FILE__),'subtype'=>'numeric','unit'=>'°C','generic'=>'WEATHER_TEMPERATURE'),
      'wind10m'=>array('label'=>__("Vent moyen", __FILE__),'subtype'=>'numeric','unit'=>'km/h','generic'=>'WEATHER_WIND_SPEED'),
      'gust10m'=>array('label'=>__("Rafales", __FILE__),'subtype'=>'numeric','unit'=>'km/h','generic'=>'WEATHER_WIND_SPEED'),
      'dirwind10m'=>array('label'=>__("Direction du vent", __FILE__),'subtype'=>'numeric','unit'=>'°','generic'=>'WEATHER_WIND_DIRECTION'),
      'rr10'=>array('label'=>__("Cumul de pluie", __FILE__),'subtype'=>'numeric','unit'=>'mm','generic'=>'RAIN_TOTAL'),
      'rr1'=>array('label'=>__("Cumul de pluie max", __FILE__),'subtype'=>'numeric','unit'=>'mm','generic'=>'RAIN_TOTAL'),
      'probarain'=>array('label'=>__("Risque de pluie", __FILE__),'subtype'=>'numeric','unit'=>'%'),
      'probafrost'=>array('label'=>__("Risque de gel", __FILE__),'subtype'=>'numeric','unit'=>'%'),
      'probafog'=>array('label'=>__("Risque de brouillard", __FILE__),'subtype'=>'numeric','unit'=>'%'),
      'probawind70'=>array('label'=>__("Risque de vent 70 kmh", __FILE__),'subtype'=>'numeric','unit'=>'%'),
      'probawind100'=>array('label'=>__("Risque de vent 100 kmh", __FILE__),'subtype'=>'numeric','unit'=>'%')
    );
    // Création commandes J0 à J4
    $ordre = 1;
    for ($i=0; $i <=4; $i++) {
      // Les jours
      $_addIdj = '_j'.$i;
      foreach ($cmds as $_LogicalId => $_config) {
        $newcmd = $this->getCmd(null, $_LogicalId . $_addIdj);
        // Création si besoin
        if (!is_object($newcmd)) {
          $newcmd = new meteomcCmd();
          $newcmd->setLogicalId($_LogicalId . $_addIdj);
          $newcmd->setEqLogic_id($this->getId());
        }
        // Perso du nom
        if ( $i == 0) {
          $newcmd->setName($_config['label']);
        } else {
          $newcmd->setName($_config['label'] . ' J+' . $i);
        }
        $newcmd->setType('info');
        $newcmd->setSubType($_config['subtype']);
        $newcmd->setUnite($_config['unit']);
        $newcmd->setIsHistorized(0);
        $newcmd->setIsVisible(0);
        $newcmd->setTemplate('dashboard', 'core::line');
        $newcmd->setTemplate('mobile', 'core::line');
        // Generic Type
        if (isset($_config['generic'])) {
          // Condition & Temperature
          if ( $i != 0 ) {
            switch ($_config['generic']) {
              case 'WEATHER_TEMPERATURE_MIN':
              case 'WEATHER_TEMPERATURE_MAX':
              case 'WEATHER_CONDITION':
              case 'WEATHER_CONDITION_ID':
                $newcmd->setDisplay('generic_type', $_config['generic'] . '_' . $i);
                break;
              default:
                $newcmd->setDisplay('generic_type', $_config['generic']);
            }
          } else {
            $newcmd->setDisplay('generic_type', $_config['generic']);
          }
        } else {
          $newcmd->setDisplay('generic_type', 'GENERIC_INFO');
        }
        $newcmd->setConfiguration('groupe','j'.$i);
        $newcmd->setOrder($ordre);
        $newcmd->save();
        $ordre++;
      }
      // Les périodes
      for ($p=0; $p<=3; $p++) {
        $_addIdp = '_j'.$i.'p'.$p;
        foreach ($cmdsp as $_pLogicalId => $_pconfig) {
          $newcmd = $this->getCmd(null, $_pLogicalId . $_addIdp);
          // Création si besoin
          if (!is_object($newcmd)) {
            $newcmd = new meteomcCmd();
            $newcmd->setLogicalId($_pLogicalId . $_addIdp);
            $newcmd->setEqLogic_id($this->getId());
          }
          // Perso du nom
          if ( $i == 0) {
            $newcmd->setName($_pconfig['label'] . ' P' . $p);
          } else {
            $newcmd->setName($_pconfig['label'] . ' J+' . $i . ' P' . $p);
          }
          $newcmd->setType('info');
          $newcmd->setSubType($_pconfig['subtype']);
          $newcmd->setUnite($_pconfig['unit']);
          $newcmd->setIsHistorized(0);
          $newcmd->setIsVisible(0);
          $newcmd->setTemplate('dashboard', 'core::line');
          $newcmd->setTemplate('mobile', 'core::line');
          // Generic Type
          if (isset($_pconfig['generic'])) {
            $newcmd->setDisplay('generic_type', $_pconfig['generic']);
          } else {
            $newcmd->setDisplay('generic_type', 'GENERIC_INFO');
          }
          $newcmd->setConfiguration('groupe','j'.$i.'_periods');
          $newcmd->setOrder($ordre);
          $newcmd->save();
          $ordre++;
        }
      }
    }
    // Cmd Action Refresh
    $ref = $this->getCmd(null, 'refresh');
    if (!is_object($ref)) {
      $ref = new meteomcCmd();
      $ref->setName(__('Rafraichir', __FILE__));
    }
    $ref->setEqLogic_id($this->getId());
    $ref->setLogicalId('refresh');
    $ref->setType('action');
    $ref->setSubType('other');
    $ref->setConfiguration('groupe','j0');
    $ref->setOrder($ordre);
    $ref->save();
  }
  
  private function makeMainWidget($version = 'dashboard') {
    // Variables de configuration
    $imgdir = 'plugins/' . __CLASS__ . '/core/template/images';
    $affPeriodN = $this->getConfiguration('affencours', 1);
    $affNbJours = $this->getConfiguration('affnbjours', 5);
    $affInfoSun = $this->getConfiguration('affinfosun', 1);
    $affDureeJo = $this->getConfiguration('affdureejo', 1);
    $affDureeSo = $this->getConfiguration('affdureeso', 1);
    $affInfoLun = $this->getConfiguration('affinfolun', 1);
    $affVentMoy = $this->getConfiguration('affventmoy', 1);
    $affVentRaf = $this->getConfiguration('affventraf', 1);
    $affPluiePr = $this->getConfiguration('affpluiepr', 1);
    $affPluieMx = $this->getConfiguration('affpluiemx', 1);
    $affVenProb = $this->getConfiguration('affvenprob', 1);
    $affGelProb = $this->getConfiguration('affgelprob', 1);
    $affBrouill = $this->getConfiguration('affbrouill', 1);
    // Génération HTML
    $_html = '<div id="meteoMC#uid#" class="meteoMC">';
    $_html.= '<div class="meteoPrevJours" data-equipement="#uid#">';
    $_html.= '<table>';
    // Ligne 1: Entete
    $_html.= '<tr>';
    if ($affPeriodN == 1) {
      $_html.= '<td class="tableCmdcss meteoMCTitre">';
      $_html.= '<div class="meteoMCTitreDivJour">#PNOW#</div></td>';
    }
    for ($i=0; $i<$affNbJours; $i++) {
      $_html.= '<td class="tableCmdcss meteoMCTitre">';
      $_html.= '<div class="meteoMCTitreDivJour">#JOUR'.$i.'# #DATE'.$i.'#</div></td>';
    }
    // Ligne 2: Image de prévision
    $_html.= '</tr><tr>';
    if ($affPeriodN == 1) {
      $_html.= '<td class="tableCmdcss meteoMCImgPrev">';
      $_html.= '<img src="/'.$imgdir.'/#weatherimg_p#" title="#weathertxt_p#" alt="#weathertxt_p#" /></td>';
    }
    for ($i=0; $i<$affNbJours; $i++) {
      $_html.= '<td class="tableCmdcss meteoMCImgPrev">';
      $_html.= '<img src="/'.$imgdir.'/#weatherimg_j'.$i.'#" title="#weathertxt_j'.$i.'#" alt="#weathertxt_j'.$i.'#" /></td>';
    }
    // Ligne 3: Texte prévision
    $_html.= '</tr><tr>';
    if ($affPeriodN == 1) {
      $_html.= '<td class="tableCmdcss meteoMCCondition">#weathertxt_p#</td>';
    }
    for ($i=0; $i<$affNbJours; $i++) {
      $_html.= '<td class="tableCmdcss meteoMCCondition">#weathertxt_j'.$i.'#</td>';
    }
    // Ligne 4: Température MIN & MAX
    $_html.= '</tr><tr>';
    if ($affPeriodN == 1) {
      $_html.= '<td class="tableCmdcss meteoMCData">';
      $_html.= '<div class="meteoMCDataf" title="'.__("Température", __FILE__).'"><i class="fas fa-thermometer-half"></i> #tact#<span class="meteoMCUnite"> °C</span></div></td>';
    }
    for ($i=0; $i<$affNbJours; $i++) {
      $_html.= '<td class="tableCmdcss meteoMCData">';
      $_html.= '<div class="meteoMCDatag"><i class="fas fa-temperature-low icon_blue" title="'.__("Température mini", __FILE__).'"></i> #tmin_j'.$i.'#<span class="meteoMCUnite"> °C</span></div>';
      $_html.= '<div class="meteoMCDatad"><i class="fas fa-temperature-high icon_red" title="'.__("Température maxi", __FILE__).'"></i> #tmax_j'.$i.'#<span class="meteoMCUnite"> °C</span></div></td>';
    }
    // OPT Ligne 5: Lever - Coucher soleil
    if ($affInfoSun == 1) {
      $_html.= '</tr><tr>';
      if ($affPeriodN == 1) {
        $_html.= '<td></td>';
      }
      for ($i=0; $i<$affNbJours; $i++) {
        $_html.= '<td class="tableCmdcss meteoMCData">';
        $_html.= '<div class="meteoMCDatag"><i class="fas fa-sun" title="'.__("Lever soleil", __FILE__).'"></i> #sunrise_j'.$i.'#</div>';
        $_html.= '<div class="meteoMCDatad"><i class="far fa-sun" title="'.__("Coucher soleil", __FILE__).'"></i> #sunset_j'.$i.'#</div></td>';
      }
    }
    // OPT Ligne 6: Durée jour & différence
    if ($affDureeJo == 1) {
      $_html.= '</tr><tr>';
      if ($affPeriodN == 1) {
        $_html.= '<td></td>';
      }
      for ($i=0; $i<$affNbJours; $i++) {
        $_html.= '<td class="tableCmdcss meteoMCData">';
        $_html.= '<div class="meteoMCDatag"><i class="fas fa-clock icon_yellow" title="'.__("Durée jour", __FILE__).'"></i> #duration_day_j'.$i.'#</div>';
        $_html.= '<div class="meteoMCDatad"><i class="fas fa-history" title="'.__("Variation de durée", __FILE__).'"></i> #diff_duration_day_j'.$i.'#<span class="meteoMCUnite"> min</span></div></td>';
      }
    }
    // OPT Ligne 7: Temps d'ensoleillement
    if ($affDureeSo == 1) {
      $_html.= '</tr><tr>';
      if ($affPeriodN == 1) {
        $_html.= '<td></td>';
      }
      for ($i=0; $i<$affNbJours; $i++) {
        $_html.= '<td class="tableCmdcss meteoMCData">';
        $_html.= '<div class="meteoMCDataf" title="'.__("Ensoleillement", __FILE__).'"><i class="fas fa-sun icon_yellow"></i> #sun_hours_j'.$i.'#<span class="meteoMCUnite"> h</span></div></td>';
      }
    }
    // LUNE
    if ($affInfoLun == 1) {
      $_html.= '</tr><tr>';
      if ($affPeriodN == 1) {
        $_html.= '<td></td>';
      }
      for ($i=0; $i<$affNbJours; $i++) {
        $_html.= '<td class="tableCmdcss meteoMCCondition">#moon_phase_j'.$i.'#</td>';
      }
    }
    // OPT Ligne 8: Vent moyen & direction
    if ($affVentMoy == 1) {
      $_html.= '</tr><tr>';
      if ($affPeriodN == 1) {
        $_html.= '<td class="tableCmdcss meteoMCData"><div class="meteoMCDataf">';
        $_html.= '<i class="icon jeedomapp-wind" title="'.__("Vent moyen", __FILE__).'"></i> #wind10m_p#<span class="meteoMCUnite"> km/h</span>';
        $_html.= '<img src="/'.$imgdir.'/vent.png" style="transform:rotate(#dirwind10m_p#deg)" title="#dirwind10m_p#°" alt="#dirwind10m_p#°"></div></td>';
      }
      for ($i=0; $i<$affNbJours; $i++) {
        $_html.= '<td class="tableCmdcss meteoMCData"><div class="meteoMCDataf">';
        $_html.= '<i class="icon jeedomapp-wind" title="'.__("Vent moyen", __FILE__).'"></i> #wind10m_j'.$i.'#<span class="meteoMCUnite"> km/h</span>';
        $_html.= '<img src="/'.$imgdir.'/vent.png" style="transform:rotate(#dirwind10m_j'.$i.'#deg)" title="#dirwind10m_j'.$i.'#°" alt="#dirwind10m_j'.$i.'#°"></div></td>';
      }
    }
    // OPT Ligne 9: Rafales de vent
    if ($affVentRaf == 1) {
      $_html.= '</tr><tr>';
      if ($affPeriodN == 1) {
        $_html.= '<td class="tableCmdcss meteoMCData">';
        $_html.= '<div class="meteoMCDataf" title="'.__("Rafales", __FILE__).'"><i class="fas fa-wind"></i> #gust10m_p#<span class="meteoMCUnite"> km/h</span></div></td>';
      }
      for ($i=0; $i<$affNbJours; $i++) {
        $_html.= '<td class="tableCmdcss meteoMCData">';
        $_html.= '<div class="meteoMCDataf" title="'.__("Rafales", __FILE__).'"><i class="fas fa-wind"></i> #gust10m_j'.$i.'#<span class="meteoMCUnite"> km/h</span></div></td>';
      }
    }
    // OPT Ligne 10: Pluie & probabilité
    if ($affPluiePr == 1) {
      $_html.= '</tr><tr>';
      if ($affPeriodN == 1) {
        $_html.= '<td class="tableCmdcss meteoMCData">';
        $_html.= '<div class="meteoMCDatag" title="'.__("Cumul de pluie", __FILE__).'"><i class="fas fa-cloud-rain"></i> #rr10_p#<span class="meteoMCUnite"> mm</span></div>';
        $_html.= '<div class="meteoMCDatad" title="'.__("Risque de pluie", __FILE__).'"><i class="far fa-clock"></i> #probarain_p#<span class="meteoMCUnite"> %</span></div>';
        $_html.= '</td>';
      }
      for ($i=0; $i<$affNbJours; $i++) {
        $_html.= '<td class="tableCmdcss meteoMCData">';
        $_html.= '<div class="meteoMCDatag" title="'.__("Cumul de pluie", __FILE__).'"><i class="fas fa-cloud-rain"></i> #rr10_j'.$i.'#<span class="meteoMCUnite"> mm</span></div>';
        $_html.= '<div class="meteoMCDatad" title="'.__("Risque de pluie", __FILE__).'"><i class="far fa-clock"></i> #probarain_j'.$i.'#<span class="meteoMCUnite"> %</span></div>';
        $_html.= '</td>';
      }
    }
    // OPT Ligne 11: Pluie Max
    if ($affPluieMx == 1) {
      $_html.= '</tr><tr>';
      if ($affPeriodN == 1) {
        $_html.= '<td class="tableCmdcss meteoMCData"><div class="meteoMCDataf" title="'.__("Cumul de pluie max", __FILE__).'">';
        $_html.= '<i class="fas fa-cloud-rain icon_red"></i> #rr1_p#<span class="meteoMCUnite"> mm</span></div></td>';
      }
      for ($i=0; $i<$affNbJours; $i++) {
        $_html.= '<td class="tableCmdcss meteoMCData"><div class="meteoMCDataf" title="'.__("Cumul de pluie max", __FILE__).'">';
        $_html.= '<i class="fas fa-cloud-rain icon_red"></i> #rr1_j'.$i.'#<span class="meteoMCUnite"> mm</span></div></td>';
      }
    }
    // OPT Ligne 12: Probalité vent 70 et 100
    if ($affVenProb == 1) {
      $_html.= '</tr><tr>';
      if ($affPeriodN == 1) {
        $_html.= '<td class="tableCmdcss meteoMCData">';
        $_html.= '<div class="meteoMCDatag" title="'.__("Risque de vent 70 kmh", __FILE__).'"><i class="fas fa-wind icon_orange"></i> #probawind70_p#<span class="meteoMCUnite"> %</span></div>';
        $_html.= '<div class="meteoMCDatad" title="'.__("Risque de vent 100 kmh", __FILE__).'"><i class="fas fa-wind icon_red"></i> #probawind100_p#<span class="meteoMCUnite"> %</span></div></td>';
      }
      for ($i=0; $i<$affNbJours; $i++) {
        $_html.= '<td class="tableCmdcss meteoMCData">';
        $_html.= '<div class="meteoMCDatag" title="'.__("Risque de vent 70 kmh", __FILE__).'"><i class="fas fa-wind icon_orange"></i> #probawind70_j'.$i.'#<span class="meteoMCUnite"> %</span></div>';
        $_html.= '<div class="meteoMCDatad" title="'.__("Risque de vent 100 kmh", __FILE__).'"><i class="fas fa-wind icon_red"></i> #probawind100_j'.$i.'#<span class="meteoMCUnite"> %</span></div></td>';
      }
    }
    // OPT Ligne 13: Probalité GEL
    if ($affGelProb == 1) {
      $_html.= '</tr><tr>';
      if ($affPeriodN == 1) {
        $_html.= '<td class="tableCmdcss meteoMCData">';
        $_html.= '<div class="meteoMCDataf" title="'.__("Risque de gel", __FILE__).'"><i class="far fa-snowflake"></i> #probfrost_p#<span class="meteoMCUnite"> %</span></div></td>';
      }
      for ($i=0; $i<$affNbJours; $i++) {
        $_html.= '<td class="tableCmdcss meteoMCData">';
        $_html.= '<div class="meteoMCDataf" title="'.__("Risque de gel", __FILE__).'"><i class="far fa-snowflake"></i> #probfrost_j'.$i.'#<span class="meteoMCUnite"> %</span></div></td>';
      }
    }
    // OPT Ligne 14: Probalité Brouillard
    if ($affBrouill == 1) {
      $_html.= '</tr><tr>';
      if ($affPeriodN == 1) {
        $_html.= '<td class="tableCmdcss meteoMCData">';
        $_html.= '<div class="meteoMCDataf" title="'.__("Risque de brouillard", __FILE__).'"><i class="fas fa-water"></i> #probfog_p#<span class="meteoMCUnite"> %</span></div></td>';
      }
      for ($i=0; $i<$affNbJours; $i++) {
        $_html.= '<td class="tableCmdcss meteoMCData">';
        $_html.= '<div class="meteoMCDataf" title="'.__("Risque de brouillard", __FILE__).'"><i class="fas fa-water"></i> #probfog_j'.$i.'#<span class="meteoMCUnite"> %</span></div></td>';
      }
    }
    $_html.= '</tr></table>';
    $_html.= '</div>';
    $_html.= '</div>';
    // Renvoie
    return $_html;
  }

  public function toHtml($_version = 'dashboard') {
    log::add(__CLASS__, 'debug', 'To html: ' . $this->getName());
    // Depuis cache ?
    $replace = $this->preToHtml($_version);
    if (!is_array($replace)) {
      log::add(__CLASS__, 'debug', 'To html en cache: ' . $this->getName());
      return $replace;
    }
    $version = jeedom::versionAlias($_version);
    log::add(__CLASS__, 'debug', 'To html version: '.$version);
    // Panneau principal
    $html_j = $this->makeMainWidget($version);
    // Remplacement tableau Principal
    $tst_j = strtotime('today');
    $tst_d = strtotime('tomorrow');
    for ($i=0; $i<$this->getConfiguration('affnbjours', 5); $i++) {
      // JOUR
      $daystamp = $this->getCmd('info', 'datetime_j' . $i);
      $tst_m = is_object($daystamp) ? $daystamp->execCmd() : '';
      if ($tst_m == $tst_j) {
        $replace['#JOUR' . $i . '#'] = __("Aujourd'hui", __FILE__);
        $replace['#DATE' . $i . '#'] = '';
      } else if ($tst_m == $tst_d) {
        $replace['#JOUR' . $i . '#'] = __("Demain", __FILE__);
        $replace['#DATE' . $i . '#'] = '';
      } else {
        $replace['#JOUR' . $i . '#'] = is_object($daystamp) ? self::getJourName(date('N', $daystamp->execCmd())) : '';
        $replace['#DATE' . $i . '#'] = is_object($daystamp) ? date('d-m', $daystamp->execCmd()) : '';
      }
      // Image Weather
      $weather = $this->getCmd('info', 'weather_j' . $i);
      $replace['#weatherimg_j' . $i . '#'] = is_object($weather) ? self::getConditionImg($weather->execCmd()) : '';
      // Texte Weather
      $weathertxt = $this->getCmd('info', 'weathertxt_j' . $i);
      $replace['#weathertxt_j' . $i . '#'] = is_object($weathertxt) ? $weathertxt->execCmd() : '';
      // Température min & max
      $tmin = $this->getCmd('info', 'tmin_j' . $i);
      $tmax = $this->getCmd('info', 'tmax_j' . $i);
      $replace['#tmin_j' . $i . '#'] = is_object($tmin) ? $tmin->execCmd() : '';
      $replace['#tmax_j' . $i . '#'] = is_object($tmax) ? $tmax->execCmd() : '';
      // Lever & Coucher soleil
      $sunrise = $this->getCmd('info', 'sunrise_j' . $i);
      $sunset = $this->getCmd('info', 'sunset_j' . $i);
      $replace['#sunset_j' . $i . '#'] = is_object($sunset) ? $sunset->execCmd() : '';
      $replace['#sunrise_j' . $i . '#'] = is_object($sunrise) ? $sunrise->execCmd() : '';
      // DureeJour & Différence
      $duration = $this->getCmd('info', 'duration_day_j' . $i);
      $diffduration = $this->getCmd('info', 'diff_duration_day_j' . $i);
      $replace['#duration_day_j' . $i . '#'] = is_object($duration) ? $duration->execCmd() : '';
      $replace['#diff_duration_day_j' . $i . '#'] = is_object($diffduration) ? $diffduration->execCmd() : '';
      // Temps d'ensoleillement
      $sunhours = $this->getCmd('info', 'sun_hours_j' . $i);
      $replace['#sun_hours_j' . $i . '#'] = is_object($sunhours) ? $sunhours->execCmd() : '';
      // Vent & rafales
      $wind = $this->getCmd('info', 'wind10m_j' . $i);
      $dirw = $this->getCmd('info', 'dirwind10m_j' . $i);
      $gust = $this->getCmd('info', 'gust10m_j' . $i);
      $replace['#wind10m_j' . $i . '#'] = is_object($wind) ? $wind->execCmd() : '';
      $replace['#dirwind10m_j' . $i . '#'] = is_object($dirw) ? $dirw->execCmd() : '';
      $replace['#gust10m_j' . $i . '#'] = is_object($gust) ? $gust->execCmd() : '';
      // Pluie & probabilité
      $pluiemin = $this->getCmd('info', 'rr10_j' . $i);
      $pluiepro = $this->getCmd('info', 'probarain_j' . $i);
      $pluiemax = $this->getCmd('info', 'rr1_j' . $i);
      $replace['#rr10_j' . $i . '#'] = is_object($pluiemin) ? $pluiemin->execCmd() : '';
      $replace['#probarain_j' . $i . '#'] = is_object($pluiepro) ? $pluiepro->execCmd() : '';
      $replace['#rr1_j' . $i . '#'] = is_object($pluiemax) ? $pluiemax->execCmd() : '';
      // Probabilité Vent70 & Vent100
      $probven70 = $this->getCmd('info', 'probawind70_j' . $i);
      $probven100 = $this->getCmd('info', 'probawind100_j' . $i);
      $replace['#probawind70_j' . $i . '#'] = is_object($probven70) ? $probven70->execCmd() : '';
      $replace['#probawind100_j' . $i . '#'] = is_object($probven100) ? $probven100->execCmd() : '';
      // Probabilité Brouillard
      $probfog = $this->getCmd('info', 'probafog_j' . $i);
      $replace['#probfog_j' . $i . '#'] = is_object($probfog) ? $probfog->execCmd() : '';
      // Probabilité Gel
      $probfrost = $this->getCmd('info', 'probafrost_j' . $i);
      $replace['#probfrost_j' . $i . '#'] = is_object($probfrost) ? $probfrost->execCmd() : '';
      // Phase de Lune
      $phaselune = $this->getCmd('info', 'moon_phase_j' . $i);
      $replace['#moon_phase_j' . $i . '#'] = is_object($phaselune) ? $phaselune->execCmd() : '';
    }
    // Remplacement tableau Principal - Période en cours si demandée
    if ($this->getConfiguration('affpencours', 1) == 1) {
      // A quelle période commencer ?
      $_now = (new DateTime('now'))->format('Gi');
      if ($_now < 530) {
        $_p = 0;
      } else if ($_now < 1130) {
        $_p = 1;
      } else if ($_now < 1530) {
        $_p = 2;
      } else {
        $_p = 3;
      }
      // Recupérations des données
      $i = 'j0p'.$_p;
      // Periode
      $period = $this->getCmd('info', 'period_' . $i);
      $replace['#PNOW#'] = is_object($period) ? $period->execCmd() : '';
      // Image Weather
      if (is_object($period) && $period->execCmd() == __('Nuit', __FILE__)) {
        $weather = $this->getCmd('info', 'weather_' . $i);
        $replace['#weatherimg_p#'] = is_object($weather) ? self::getConditionImg($weather->execCmd(), true) : '';
      } else {
        $weather = $this->getCmd('info', 'weather_' . $i);
        $replace['#weatherimg_p#'] = is_object($weather) ? self::getConditionImg($weather->execCmd()) : '';
      }
      // Texte Weather
      $weathertxt = $this->getCmd('info', 'weathertxt_' . $i);
      $replace['#weathertxt_p#'] = is_object($weathertxt) ? $weathertxt->execCmd() : '';
      // Temperature
      $tact = $this->getCmd('info', 'temp2m_' . $i);
      $replace['#tact#'] = is_object($tact) ? $tact->execCmd() : '';
      // Vent & rafales
      $wind = $this->getCmd('info', 'wind10m_' . $i);
      $dirw = $this->getCmd('info', 'dirwind10m_' . $i);
      $gust = $this->getCmd('info', 'gust10m_' . $i);
      $replace['#wind10m_p#'] = is_object($wind) ? $wind->execCmd() : '';
      $replace['#dirwind10m_p#'] = is_object($dirw) ? $dirw->execCmd() : '';
      $replace['#gust10m_p#'] = is_object($gust) ? $gust->execCmd() : '';
      // Pluie & probabilité
      $pluiemin = $this->getCmd('info', 'rr10_' . $i);
      $pluiepro = $this->getCmd('info', 'probarain_' . $i);
      $pluiemax = $this->getCmd('info', 'rr1_' . $i);
      $replace['#rr10_p#'] = is_object($pluiemin) ? $pluiemin->execCmd() : '';
      $replace['#probarain_p#'] = is_object($pluiepro) ? $pluiepro->execCmd() : '';
      $replace['#rr1_p#'] = is_object($pluiemax) ? $pluiemax->execCmd() : '';
      // Probabilité Vent70 & Vent100
      $probven70 = $this->getCmd('info', 'probawind70_' . $i);
      $probven100 = $this->getCmd('info', 'probawind100_' . $i);
      $replace['#probawind70_p#'] = is_object($probven70) ? $probven70->execCmd() : '';
      $replace['#probawind100_p#'] = is_object($probven100) ? $probven100->execCmd() : '';
      // Probabilité Brouillard
      $probfog = $this->getCmd('info', 'probafog_' . $i);
      $replace['#probfog_p#'] = is_object($probfog) ? $probfog->execCmd() : '';
      // Probabilité Gel
      $probfrost = $this->getCmd('info', 'probafrost_' . $i);
      $replace['#probfrost_p#'] = is_object($probfrost) ? $probfrost->execCmd() : '';
    }
    // Remplacement dans le template
    $replace['#tabHtmlJours#'] = template_replace($replace, $html_j);
    // Config utilisateur Jeedom
    $parameters = $this->getDisplay('parameters');
    if (is_array($parameters)) {
      foreach ($parameters as $key => $value) {
        $replace['#' . $key . '#'] = $value;
      }
    }
    $html = template_replace($replace, getTemplate('core', $version, 'widget', __CLASS__));
    // Mise en cache si configuré et restitution
    return $this->postToHtml($_version, $html);
  }
}

class meteomcCmd extends cmd {
  public function execute($_options = array()) {
    if ($this->getLogicalId() == 'refresh' && $this->getEqLogic()->getIsEnable()) {
      log::add('meteomc', 'debug', 'Refresh requested for : '.$this->getEqLogic()->getName());
      $this->getEqLogic()->updateFromMC();
    }
    return false;
  }
}
