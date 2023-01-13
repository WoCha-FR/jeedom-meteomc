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

/* * ***************************Includes********************************* */

class meteomc extends eqLogic {
	/*     * ***********************Methode static*************************** */
	public static function cronDaily($_eqLogic_id = null) {
		// eqLogic fournis ?
		if ($_eqLogic_id == null) {
			$eqLogics = self::byType(__CLASS__, true);
		} else {
			$eqLogics = array(self::byId($_eqLogic_id));
		}
		// Action selon configuration
		foreach ($eqLogics as $meteo) {
			if ($meteo->getConfiguration('meteomcmode') == 'daily' && $meteo->getIsEnable()) {
				$meteo->updateEphemerides();
				$meteo->refreshWidget();
			}
		}
	}

	public static function cronHourly($_eqLogic_id = null) {
		// eqLogic fournis ?
		if ($_eqLogic_id == null) {
			$eqLogics = self::byType(__CLASS__, true);
		} else {
			$eqLogics = array(self::byId($_eqLogic_id));
		}
		// Action selon configuration
		foreach ($eqLogics as $meteo) {
			// Journalières ou Périodes
			if ($meteo->getConfiguration('meteomcmode') == 'daily' && $meteo->getIsEnable()) {
				$meteo->updateJournalier();
				$meteo->refreshWidget();
			} else if ($meteo->getConfiguration('meteomcmode') == 'periods' && $meteo->getIsEnable()) {
				$meteo->updatePeriodes();
				$meteo->refreshWidget();
			}
		}
	}

	public static function cron30($_eqLogic_id = null) {
		// eqLogic fournis ?
		if ($_eqLogic_id == null) {
			$eqLogics = self::byType(__CLASS__, true);
		} else {
			$eqLogics = array(self::byId($_eqLogic_id));
		}
		// Action selon configuration
		foreach ($eqLogics as $meteo) {
			if ($meteo->getConfiguration('meteomcmode') == 'periods' && $meteo->getIsEnable()) {
				$meteo->updatePeriodes();
				$meteo->refreshWidget();
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
		$return = json_decode($request_http->exec(30,1),true);
		$return = is_json($return, $return);
		// Erreur
		if (isset($return['code'])) {
			log::add(__CLASS__, 'error', 'Code: ' . $return['code'] . ' ' . $return['message'] . ' ' . $url);
		}
		unset($return['city']);
		return $return;
		// Forecast : $return['update'] : "2022-12-28T11:14:43+01:00" + $return['forecast']
	}

	public function updateEphemerides() {
		// Vérifications
		if ( config::byKey('meteomcapikey', __CLASS__, '') == '') {
			throw new Exception(__('API Meteo Concept non renseigné dans la configuration', __FILE__));
		}
		if ($this->getConfiguration('meteomcinsee') == '') {
			throw new Exception(__('L\'identifiant de la ville ne peut être vide', __FILE__));
		}
		if ($this->getConfiguration('meteomcmode') != 'daily') {
			throw new Exception(__('Mode incorrect.', __FILE__));
		}
		log::add(__CLASS__, 'debug', __FUNCTION__, $this->getLogicalId());
		// Ephemerides
		$_insee = $this->getConfiguration('meteomcinsee');
		for ($i = 0; $i < 7; $i++) {
			$result = self::request('ephemeride/' . $i, array('insee'=>$_insee));
			log::add(__CLASS__, 'debug', json_encode($result));
			$_addId = '';
			if ($i != 0) {
				$_addId = '_' . $i;
			}
			// Parcours des données du jour en cours
			foreach ($result['ephemeride'] as $_cmdId => $_value) {
				$this->checkAndUpdateCmd(strtolower($_cmdId . $_addId), $_value);
			}
			// Pause
			@sleep(1);
		}
	}

	public function updateJournalier() {
		// Vérifications
		if ( config::byKey('meteomcapikey', __CLASS__, '') == '') {
			throw new Exception(__('API Meteo Concept non renseigné dans la configuration', __FILE__));
		}
		if ($this->getConfiguration('meteomcinsee') == '') {
			throw new Exception(__('L\'identifiant de la ville ne peut être vide', __FILE__));
		}
		if ($this->getConfiguration('meteomcmode') != 'daily') {
			throw new Exception(__('Mode incorrect.', __FILE__));
		}
		log::add(__CLASS__, 'debug', __FUNCTION__, $this->getLogicalId());
		// Récupération des données
		$_insee = $this->getConfiguration('meteomcinsee');
		// Prévisions météos
		$result = self::request('forecast/daily', array('insee'=>$_insee));
		log::add(__CLASS__, 'debug', json_encode($result));
		// Mise A jour des données
		$collectDate = (new DateTime($result['update']))->format('Y-m-d H:i:s');
		// Parcours des jours
		foreach ($result['forecast'] as &$forecast) {
			if ($forecast['day'] > 6) {
				continue;
			}
			log::add(__CLASS__, 'debug', json_encode($forecast));
			$_addId = '';
			if ($forecast['day'] != 0) {
				$_addId = '_' . $forecast['day'];
			}
			// Parcours des données du jour en cours
			foreach ($forecast as $_cmdId => $_value) {
				if ( $_cmdId == 'weather') {
					$_txtValue = self::getConditionText($_value);
					$this->checkAndUpdateCmd(strtolower('weathertxt' . $_addId), $_txtValue, $collectDate);
				}
				// Date
				if ($_cmdId == 'datetime') {
					$_dateValue = (new DateTime($_value . ' midnight'))->getTimestamp();
					$this->checkAndUpdateCmd(strtolower('date' . $_addId), $_dateValue, $collectDate);
				}
				$this->checkAndUpdateCmd(strtolower($_cmdId . $_addId), $_value, $collectDate);
			}
		}
	}

	public function updatePeriodes() {
		// Vérifications
		if ( config::byKey('meteomcapikey', __CLASS__, '') == '') {
			throw new Exception(__('API Meteo Concept non renseigné dans la configuration', __FILE__));
		}
		if ($this->getConfiguration('meteomcinsee') == '') {
			throw new Exception(__('L\'identifiant de la ville ne peut être vide', __FILE__));
		}
		if ($this->getConfiguration('meteomcmode') != 'periods') {
			throw new Exception(__('Mode incorrect.', __FILE__));
		}
		log::add(__CLASS__, 'debug', __FUNCTION__, $this->getLogicalId());
		// Récupération des données
		$_insee = $this->getConfiguration('meteomcinsee');
		// Prévisions météos
		$result = self::request('forecast/daily/periods', array('insee'=>$_insee));
		// Mise A jour des données
		$collectDate = (new DateTime($result['update']))->format('Y-m-d H:i:s');
		// A quelle période commencer ?
		$_now = (new DateTime('now'))->format('G');
		if ($_now < 6) {
			$_start = 0;
		} else if ($_now < 12) {
			$_start = 1;
		} else if ($_now < 16) {
			$_start = 2;
		} else {
			$_start = 3;
		}
		// Parcours des jours
		$i = 0;
		foreach ($result['forecast'] as &$_days) {
			// Parcours des périodes
			foreach ($_days as $_periods) {
				// Pas une période passée
				if ($_periods['day'] == 0 && $_periods['period'] < $_start || $_periods['day'] > 2) {
					continue;
				}
				// Données de la période
				log::add(__CLASS__, 'debug', json_encode($_periods));
				$_addId = '';
				if ($i != 0) {
					$_addId = '_' . $i;
				}
				// Parcours des données de la période en cours
				foreach ($_periods as $_cmdId => $_value) {
					// Code vers texte
					if ( $_cmdId == 'weather') {
						$_txtValue = self::getConditionText($_value);
						$this->checkAndUpdateCmd(strtolower('weathertxt' . $_addId), $_txtValue, $collectDate);
					}
					// Date
					if ($_cmdId == 'datetime') {
						$_dateValue = (new DateTime($_value . ' midnight'))->getTimestamp();
						$this->checkAndUpdateCmd(strtolower('date' . $_addId), $_dateValue, $collectDate);
					}
					// Heures
					if ($_cmdId == 'period') {
						$_periodValue = self::getPeriodName($_value);
						$this->checkAndUpdateCmd(strtolower('period' . $_addId), $_periodValue, $collectDate);
						continue;
					}
					// Autres valeurs
					$this->checkAndUpdateCmd(strtolower($_cmdId . $_addId), $_value, $collectDate);
				}
				$i++;
			}
		}
	}

	public function updateHeures() {
		// Vérifications
		if ( config::byKey('meteomcapikey', __CLASS__, '') == '') {
			throw new Exception(__('API Meteo Concept non renseigné dans la configuration', __FILE__));
		}
		if ($this->getConfiguration('meteomcinsee') == '') {
			throw new Exception(__('L\'identifiant de la ville ne peut être vide', __FILE__));
		}
		if ($this->getConfiguration('meteomcmode') != 'hours') {
			throw new Exception(__('Mode incorrect.', __FILE__));
		}
		log::add(__CLASS__, 'debug', __FUNCTION__, $this->getLogicalId());
		// Récupération des données
		$_insee = $this->getConfiguration('meteomcinsee');
		$result = self::request('forecast/nextHours', array('insee'=>$_insee, 'hourly'=>'true'));
		// Mise A jour des données
		$collectDate = (new DateTime($result['update']))->format('Y-m-d H:i:s');
		// Parcours des heures
		$i = 0;
		foreach ($result['forecast'] as &$forecast) {
			log::add(__CLASS__, 'debug', json_encode($forecast));
			$_addId = '';
			if ($i != 0) {
				$_addId = '_' . $i;
			}
			// Parcours des données de l'heure en cours
			foreach ($forecast as $_cmdId => $_value) {
				// Code vers texte
				if ( $_cmdId == 'weather') {
					$_txtValue = self::getConditionText($_value);
					$this->checkAndUpdateCmd(strtolower('weathertxt' . $_addId), $_txtValue, $collectDate);
				}
				// Date & Heures
				if ($_cmdId == 'datetime') {
					$_dateValue = (new DateTime($_value . ' midnight'))->getTimestamp();
					$this->checkAndUpdateCmd(strtolower('date' . $_addId), $_dateValue, $collectDate);
					$_hourValue = (new DateTime($_value))->format('H:i');
					$this->checkAndUpdateCmd(strtolower('period' . $_addId), $_hourValue, $collectDate);
				}
				// Autres valeurs
				$this->checkAndUpdateCmd(strtolower($_cmdId . $_addId), $_value, $collectDate);
			}
			$i++;
		}
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
		$_newEqLogicID = $this->getConfiguration('meteomcinsee').'_'.$this->getConfiguration('meteomcmode');
		if ($this->getLogicalId() == '') {
			if (is_object(self::byLogicalId($_newEqLogicID, __CLASS__))) {
				throw new Exception(__('Un équipement identique existe déjà.', __FILE__));
			}
			$this->setLogicalId($_newEqLogicID);
		}
	}

	public function postUpdate() {
		// Commandes selon configuration
		log::add(__CLASS__, 'debug', 'PostUpdate', $this->getLogicalId());
		switch( $this->getConfiguration('meteomcmode') ) {
			case 'daily':
				$commands = array(
					'date'=>array('label'=>__("Timestamp du jour", __FILE__),'subtype'=>'numeric','unit'=>''),
					'sunrise'=>array('label'=>__("Lever du soleil", __FILE__),'subtype'=>'string','unit'=>''),
					'sunset'=>array('label'=>__("Coucher du soleil", __FILE__),'subtype'=>'string','unit'=>''),
					'duration_day'=>array('label'=>__("Durée du jour", __FILE__),'subtype'=>'string','unit'=>''),
					'diff_duration_day'=>array('label'=>__("Gain ou perte de durée", __FILE__),'subtype'=>'numeric','unit'=>'m'),
					'moon_phase'=>array('label'=>__("Phase de lune", __FILE__),'subtype'=>'string','unit'=>''),
					'weather'=>array('label'=>__("Code Temps sensible", __FILE__),'subtype'=>'numeric','unit'=>''),
					'weathertxt'=>array('label'=>__("Temps sensible", __FILE__),'subtype'=>'string','unit'=>''),
					'tmin'=>array('label'=>__("Température minimale", __FILE__),'subtype'=>'numeric','unit'=>'°C'),
					'tmax'=>array('label'=>__("Température maximale", __FILE__),'subtype'=>'numeric','unit'=>'°C'),
					'wind10m'=>array('label'=>__("Vent moyen", __FILE__),'subtype'=>'numeric','unit'=>'km/h'),
					'gust10m'=>array('label'=>__("Rafales", __FILE__),'subtype'=>'numeric','unit'=>'km/h'),
					'dirwind10m'=>array('label'=>__("Direction du vent", __FILE__),'subtype'=>'numeric','unit'=>'°'),
					'rr10'=>array('label'=>__("Cumul de pluie", __FILE__),'subtype'=>'numeric','unit'=>'mm'),
					'rr1'=>array('label'=>__("Cumul de pluie maximal", __FILE__),'subtype'=>'numeric','unit'=>'mm'),
					'probarain'=>array('label'=>__("Probabilité de pluie", __FILE__),'subtype'=>'numeric','unit'=>'%'),
					'sun_hours'=>array('label'=>__("Ensoleillement", __FILE__),'subtype'=>'numeric','unit'=>'h'),
					'probafrost'=>array('label'=>__("Probabilité de gel", __FILE__),'subtype'=>'numeric','unit'=>'%'),
					'probafog'=>array('label'=>__("Probabilité de brouillard", __FILE__),'subtype'=>'numeric','unit'=>'%'),
					'probawind70'=>array('label'=>__("Probabilité de vent >70 km/h", __FILE__),'subtype'=>'numeric','unit'=>'%'),
					'probawind100'=>array('label'=>__("Probabilité de vent >100 km/h", __FILE__),'subtype'=>'numeric','unit'=>'%')
				);
				$count = 7;
				break;
			case 'periods':
				$commands = array(
					'date'=>array('label'=>__("Timestamp du jour", __FILE__),'subtype'=>'numeric','unit'=>''),
					'period'=>array('label'=>__("Heure locale", __FILE__),'subtype'=>'string','unit'=>''),
					'weather'=>array('label'=>__("Code Temps sensible", __FILE__),'subtype'=>'numeric','unit'=>''),
					'weathertxt'=>array('label'=>__("Temps sensible", __FILE__),'subtype'=>'string','unit'=>''),
					'temp2m'=>array('label'=>__("Température", __FILE__),'subtype'=>'numeric','unit'=>'°C'),
					'wind10m'=>array('label'=>__("Vent moyen", __FILE__),'subtype'=>'numeric','unit'=>'km/h'),
					'gust10m'=>array('label'=>__("Rafales", __FILE__),'subtype'=>'numeric','unit'=>'km/h'),
					'dirwind10m'=>array('label'=>__("Direction du vent", __FILE__),'subtype'=>'numeric','unit'=>'°'),
					'rr10'=>array('label'=>__("Cumul de pluie", __FILE__),'subtype'=>'numeric','unit'=>'mm'),
					'rr1'=>array('label'=>__("Cumul de pluie maximal", __FILE__),'subtype'=>'numeric','unit'=>'mm'),
					'probarain'=>array('label'=>__("Probabilité de pluie", __FILE__),'subtype'=>'numeric','unit'=>'%'),
					'probafrost'=>array('label'=>__("Probabilité de gel", __FILE__),'subtype'=>'numeric','unit'=>'%'),
					'probafog'=>array('label'=>__("Probabilité de brouillard", __FILE__),'subtype'=>'numeric','unit'=>'%'),
					'probawind70'=>array('label'=>__("Probabilité de vent >70 km/h", __FILE__),'subtype'=>'numeric','unit'=>'%'),
					'probawind100'=>array('label'=>__("Probabilité de vent >100 km/h", __FILE__),'subtype'=>'numeric','unit'=>'%')
				);
				$count = 8;
				break;
			case 'hours':
				$commands = array(
					'date'=>array('label'=>__("Timestamp du jour", __FILE__),'subtype'=>'numeric','unit'=>''),
					'period'=>array('label'=>__("Période", __FILE__),'subtype'=>'string','unit'=>''),
					'weather'=>array('label'=>__("Code Temps sensible", __FILE__),'subtype'=>'numeric','unit'=>''),
					'weathertxt'=>array('label'=>__("Temps sensible", __FILE__),'subtype'=>'string','unit'=>''),
					'temp2m'=>array('label'=>__("Température", __FILE__),'subtype'=>'numeric','unit'=>'°C'),
					'rh2m'=>array('label'=>__("Humidité",__FILE__),'subtype'=>'numeric','unit'=>'%'),
					'wind10m'=>array('label'=>__("Vent moyen", __FILE__),'subtype'=>'numeric','unit'=>'km/h'),
					'gust10m'=>array('label'=>__("Rafales", __FILE__),'subtype'=>'numeric','unit'=>'km/h'),
					'dirwind10m'=>array('label'=>__("Direction du vent", __FILE__),'subtype'=>'numeric','unit'=>'°'),
					'rr10'=>array('label'=>__("Cumul de pluie", __FILE__),'subtype'=>'numeric','unit'=>'mm'),
					'rr1'=>array('label'=>__("Cumul de pluie maximal", __FILE__),'subtype'=>'numeric','unit'=>'mm'),
					'probarain'=>array('label'=>__("Probabilité de pluie", __FILE__),'subtype'=>'numeric','unit'=>'%'),
					'probafrost'=>array('label'=>__("Probabilité de gel", __FILE__),'subtype'=>'numeric','unit'=>'%'),
					'probafog'=>array('label'=>__("Probabilité de brouillard", __FILE__),'subtype'=>'numeric','unit'=>'%'),
					'probawind70'=>array('label'=>__("Probabilité de vent >70 km/h", __FILE__),'subtype'=>'numeric','unit'=>'%'),
					'probawind100'=>array('label'=>__("Probabilité de vent >100 km/h", __FILE__),'subtype'=>'numeric','unit'=>'%')
				);
				$count = 12;
				break;
			default:
				throw new Exception(__('Mode inconnu.', __FILE__));
		}
		// Création des commandes
		$start = 0;
		$order = 1;
		while( $start < $count ) {
			$_addId = '';
			$_addTxt = '';
			if ($start != 0) {
				$_addId = '_' . $start;
				$_addTxt = '+' . $start;
			}
			foreach ($commands as $_LogicalId => $_cmd) {
				$newcmd = $this->getCmd(null, $_LogicalId . $_addId);
				if (!is_object($newcmd)) {
					$newcmd = new meteomcCmd();
					$newcmd->setLogicalId($_LogicalId . $_addId);
					$newcmd->setEqLogic_id($this->getId());
				}
				$newcmd->setName($_cmd['label'] . $_addTxt);
				$newcmd->setType('info');
				$newcmd->setSubType($_cmd['subtype']);
				$newcmd->setUnite($_cmd['unit']);
				$newcmd->setIsHistorized(0);
				$newcmd->setIsVisible(0);
				$newcmd->setDisplay('generic_type', 'GENERIC_INFO');
				$newcmd->setTemplate('dashboard', 'core::line');
				$newcmd->setTemplate('mobile', 'core::line');
				$newcmd->setOrder($order);
				$newcmd->save();
				$order++;
			}
			$start++;
		}
		// Refresh
		$refresh = $this->getCmd(null, 'refresh');
		if (!is_object($refresh)) {
			$refresh = new meteomcCmd();
			$refresh->setEqLogic_id($this->getId());
			$refresh->setLogicalId('refresh');
		}
		$refresh->setName(__('Rafraichir', __FILE__));
		$refresh->setType('action');
		$refresh->setSubType('other');
		$refresh->setOrder($order);
		$refresh->save();
  }

	public function postSave() {
		log::add(__CLASS__, 'debug', 'PostSave', $this->getLogicalId());
		// Mise à jour des données
		$refresh = $this->getCmd(null, 'refresh');
		if (is_object($refresh)) {
			$refresh->execCmd();
		}
	}

	public function toHtml($_version = 'dashboard') {
		log::add(__CLASS__,'debug','To html' );
		$replace = $this->preToHtml($_version);
		// Renvoie direct si existe
		if (!is_array($replace)) {
			return $replace;
		}
		$version = jeedom::versionAlias($_version);
		// Timestamp utiles
		$tst_j = strtotime('today');
		$tst_d = strtotime('tomorrow');
		// Configurations
		$mode = $this->getConfiguration('meteomcmode');
		if ($mode == 'daily') {
			$max = 6;
		} else if ($mode == 'periods') {
			$max = 7;
		} else {
			$max = 11;
		}
		// Parcours des répétitions
		$i = 0;
		while ($i <= $max) {
			if ($i != 0) {
				$_addId = '_' . $i;
			}
			// Généralitées
			$replace['#imgdir#'] = '/plugins/' . __CLASS__ . '/core/template/images';
			// JOUR
			$daystamp = $this->getCmd('info', 'date' . $_addId);
			$tst_m = is_object($daystamp) ? $daystamp->execCmd() : '';
			if ($tst_m == $tst_j) {
				$replace['#JOUR' . $_addId . '#'] = __("Aujourd'hui", __FILE__);
				if ($mode == 'daily') {
					$replace['#DATE' . $_addId . '#'] = '';
				}
			} else if ($tst_m == $tst_d) {
				$replace['#JOUR' . $_addId . '#'] = __("Demain", __FILE__);
				if ($mode == 'daily') {
					$replace['#DATE' . $_addId . '#'] = '';
				}
			} else {
				$replace['#JOUR' . $_addId . '#'] = is_object($daystamp) ? self::getJourName(date('N', $daystamp->execCmd())) : '';
				if ($mode == 'daily') {
					$replace['#DATE' . $_addId . '#'] = is_object($daystamp) ? date('d-m', $daystamp->execCmd()) : '';
				}
			}
			// Selon le mode
			if ($mode == 'daily') {
				// Température min & max
				$tmin = $this->getCmd('info', 'tmin' . $_addId);
				$tmax = $this->getCmd('info', 'tmax' . $_addId);
				$replace['#tmin' . $_addId . '#'] = is_object($tmin) ? $tmin->execCmd() : '';
				$replace['#tmax' . $_addId . '#'] = is_object($tmax) ? $tmax->execCmd() : '';
				// Heures Soleil
				$sunrise = $this->getCmd('info', 'sunrise' . $_addId);
				$sunset = $this->getCmd('info', 'sunset' . $_addId);
				$replace['#sunset' . $_addId . '#'] = is_object($sunset) ? $sunset->execCmd() : '';
				$replace['#sunrise' . $_addId . '#'] = is_object($sunrise) ? $sunrise->execCmd() : '';
				// DureeJour & Différence
				$duration = $this->getCmd('info', 'duration_day' . $_addId);
				$diffduration = $this->getCmd('info', 'diff_duration_day' . $_addId);
				$replace['#duration' . $_addId . '#'] = is_object($duration) ? $duration->execCmd() : '';
				$replace['#diffduration' . $_addId . '#'] = is_object($diffduration) ? $diffduration->execCmd() : '';
				// Temps d'ensoleillement
				$sunhours = $this->getCmd('info', 'sun_hours' . $_addId);
				$replace['#sunhours' . $_addId . '#'] = is_object($sunhours) ? $sunhours->execCmd() : '';
				// Image Weather
				$weather = $this->getCmd('info', 'weather' . $_addId);
				$replace['#weatherimg' . $_addId . '#'] = is_object($weather) ? self::getConditionImg($weather->execCmd()) : '';
				// Phase de Lune
			} else {
				// Temperature
				$tact = $this->getCmd('info', 'temp2m' . $_addId);
				$replace['#tact' . $_addId . '#'] = is_object($tact) ? $tact->execCmd() : '';
			}
			if ($mode == 'periods') {
				// Periode
				$period = $this->getCmd('info', 'period' . $_addId);
				$replace['#PERIOD' . $_addId . '#'] = is_object($period) ? $period->execCmd() : '';
				// Image Weather
				if (is_object($period) && $period->execCmd() == __('Nuit', __FILE__)) {
					$weather = $this->getCmd('info', 'weather' . $_addId);
					$replace['#weatherimg' . $_addId . '#'] = is_object($weather) ? self::getConditionImg($weather->execCmd(), true) : '';
				} else {
					$weather = $this->getCmd('info', 'weather' . $_addId);
					$replace['#weatherimg' . $_addId . '#'] = is_object($weather) ? self::getConditionImg($weather->execCmd()) : '';
				}
			}
			if ($mode == 'hours') {
				// Heures
				$period = $this->getCmd('info', 'period' . $_addId);
				$replace['#PERIOD' . $_addId . '#'] = is_object($period) ? $period->execCmd() : '';
				// Humidité
				$humi = $this->getCmd('info', 'rh2m' . $_addId);
				$replace['#humi' . $_addId . '#'] = is_object($humi) ? $humi->execCmd() : '';
				// Image Weather
				$tstamph = strtotime($period->execCmd());
				$hour = date('G', $tstamph);
				$weather = $this->getCmd('info', 'weather' . $_addId);
				if ($hour <= "6" || $hour >= "21") {
					$replace['#weatherimg' . $_addId . '#'] = is_object($weather) ? self::getConditionImg($weather->execCmd(), true) : '';
				} else {
					$replace['#weatherimg' . $_addId . '#'] = is_object($weather) ? self::getConditionImg($weather->execCmd()) : '';
				}
			}
			// TOUS
			// Texte Weather
			$weathertxt = $this->getCmd('info', 'weathertxt' . $_addId);
			$replace['#weathertxt' . $_addId . '#'] = is_object($weathertxt) ? $weathertxt->execCmd() : '';
			// Vent & rafales
			$wind = $this->getCmd('info', 'wind10m' . $_addId);
			$dirw = $this->getCmd('info', 'dirwind10m' . $_addId);
			$gust = $this->getCmd('info', 'gust10m' . $_addId);
			$replace['#wind' . $_addId . '#'] = is_object($wind) ? $wind->execCmd() : '';
			$replace['#dirw' . $_addId . '#'] = is_object($dirw) ? $dirw->execCmd() : '';
			$replace['#gust' . $_addId . '#'] = is_object($gust) ? $gust->execCmd() : '';
			// Pluie & probabilité
			$pluiemin = $this->getCmd('info', 'rr10' . $_addId);
			$pluiepro = $this->getCmd('info', 'probarain' . $_addId);
			$replace['#pluiemin' . $_addId . '#'] = is_object($pluiemin) ? $pluiemin->execCmd() : '';
			$replace['#pluiepro' . $_addId . '#'] = is_object($pluiepro) ? $pluiepro->execCmd() : '';
			// Pluie Max
			$pluiemax = $this->getCmd('info', 'rr1' . $_addId);
			$replace['#pluiemax' . $_addId . '#'] = is_object($pluiemax) ? $pluiemax->execCmd() : '';
			// Probabilité Brouillard
			$probfog = $this->getCmd('info', 'probafog' . $_addId);
			$replace['#probfog' . $_addId . '#'] = is_object($probfog) ? $probfog->execCmd() : '';
			// Probabilité Gel
			$probfrost = $this->getCmd('info', 'probafrost' . $_addId);
			$replace['#probfrost' . $_addId . '#'] = is_object($probfrost) ? $probfrost->execCmd() : '';
			// Probabilité Vent70 & Vent100
			$probven70 = $this->getCmd('info', 'probawind70' . $_addId);
			$replace['#probven70' . $_addId . '#'] = is_object($probven70) ? $probven70->execCmd() : '';
			$probven100 = $this->getCmd('info', 'probawind100' . $_addId);
			$replace['#probven100' . $_addId . '#'] = is_object($probven100) ? $probven100->execCmd() : '';
			// Période suivante
			$i++;
		}
		// Remplacement des légendes
		
		// Remplacement dans le template
		$html = template_replace($replace, getTemplate('core', $version, $this->getConfiguration('meteomcmode'), __CLASS__));
		cache::set('widgetHtml' . $_version . $this->getId(),$html, 0);
		return $html;
	}
}

class meteomcCmd extends cmd {
  public function execute($_options = array()) {
		if ($this->getLogicalId() == 'refresh') {
			// Refresh Suivant Configuration
			switch ($this->getEqLogic()->getConfiguration('meteomcmode')) {
				case "daily":
					$this->getEqLogic()->updateEphemerides();
					$this->getEqLogic()->updateJournalier();
					$this->getEqLogic()->refreshWidget();
					break;
				case "periods":
					$this->getEqLogic()->updatePeriodes();
					$this->getEqLogic()->refreshWidget();
					break;
				case "hours":
					$this->getEqLogic()->updateHeures();
					$this->getEqLogic()->refreshWidget();
					break;
			}
		}
		return false;
  }
}
