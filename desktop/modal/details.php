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

if (!isConnect('admin')) {
  throw new Exception('{{401 - Accès non autorisé}}');
}
// Variables
$eqLogic_id = filter_var($_GET['eqLogic'], FILTER_VALIDATE_INT);
$jperiod = filter_var($_GET['jour'], FILTER_VALIDATE_REGEXP, array("options"=>array("regexp"=>"/^j[0-4]$/")));
// Validation
if (!$eqLogic_id || !$jperiod) {
  throw new Exception('{{401 - Accès non autorisé}}');
}
// Récupération
$eqLogic = eqLogic::byId($eqLogic_id);
if ($eqLogic->getEqType_name() !== 'meteomc') {
  throw new Exception('{{401 - Accès non autorisé}}');
}
// Variables
$tst_j = strtotime('today');
$tst_d = strtotime('tomorrow');
$imgdir = 'plugins/' . $eqLogic->getEqType_name() . '/core/template/images';
$affVentMoy = $eqLogic->getConfiguration('affventmoy', 1);
$affVentRaf = $eqLogic->getConfiguration('affventraf', 1);
$affPluiePr = $eqLogic->getConfiguration('affpluiepr', 1);
$affPluieMx = $eqLogic->getConfiguration('affpluiemx', 1);
$affVenProb = $eqLogic->getConfiguration('affvenprob', 1);
$affGelProb = $eqLogic->getConfiguration('affgelprob', 1);
$affBrouill = $eqLogic->getConfiguration('affbrouill', 1);
// HTML
$_html = '<div class="meteoMC">';
$_html.= '<div class="meteoPrevJours">';
$_html.= '<table class="tableCmd"><tr>';
// Jour en cours d'affichage
$tst_m = cmd::byEqLogicIdAndLogicalId($eqLogic_id, 'datetime_'.$jperiod, false, 'info', $eqLogic)->execCmd();
if ($tst_m == $tst_j) {
  $txtdate = __("Aujourd'hui", __FILE__);
} else if ($tst_m == $tst_d) {
  $txtdate = __("Demain", __FILE__);
} else {
  $txtdate = $eqLogic->getJourName(date('N', $tst_m)).' '.date('d-m', $tst_m);
}
$_html.= '<td class="meteoMCTitre" colspan="4">'.$txtdate.'</td>';
$_html.= '</tr><tr>';
// Ligne 1: Entete
for ($i=0; $i<4; $i++) {
  $period = cmd::byEqLogicIdAndLogicalId($eqLogic_id, 'period_'.$jperiod.'p'.$i, false, 'info', $eqLogic)->execCmd();
  $_html.= '<td class="meteoMCTitre"><div class="meteoMCTitreDivJour">'.$period.'</div></td>';
}
// Ligne 2: Image de prévision
$_html.= '</tr><tr>';
for ($i=0; $i<4; $i++) {
  if ($i==0 || $i==3 ) {
    $imgfile = $eqLogic->getConditionImg(cmd::byEqLogicIdAndLogicalId($eqLogic_id, 'weather_'.$jperiod.'p'.$i, false, 'info', $eqLogic)->execCmd(), true);
  } else {
    $imgfile = $eqLogic->getConditionImg(cmd::byEqLogicIdAndLogicalId($eqLogic_id, 'weather_'.$jperiod.'p'.$i, false, 'info', $eqLogic)->execCmd());
  }
  $imgtxt = cmd::byEqLogicIdAndLogicalId($eqLogic_id, 'weathertxt_'.$jperiod.'p'.$i, false, 'info', $eqLogic)->execCmd();
  $_html.= '<td class="meteoMCImgPrev">';
  $_html.= '<img src="/'.$imgdir.'/'.$imgfile.'" title="'.$imgtxt.'" alt="'.$imgtxt.'" /></td>';
}
// Ligne 3: Texte prévision
$_html.= '</tr><tr>';
for ($i=0; $i<4; $i++) {
  $imgtxt = cmd::byEqLogicIdAndLogicalId($eqLogic_id, 'weathertxt_'.$jperiod.'p'.$i, false, 'info', $eqLogic)->execCmd();
  $_html.= '<td class="meteoMCCondition">'.$imgtxt.'</td>';
}
// Ligne 4: Température
$_html.= '</tr><tr>';
for ($i=0; $i<4; $i++) {
  $temperature = cmd::byEqLogicIdAndLogicalId($eqLogic_id, 'temp2m_'.$jperiod.'p'.$i, false, 'info', $eqLogic)->execCmd();
  $_html.= '<td class="meteoMCData"><div class="meteoMCDataf" title="'.__("Température", __FILE__).'">';
  $_html.= '<i class="fas fa-thermometer-half"></i> '.$temperature.'<span class="meteoMCUnite"> °C</span></div></td>';
}
// OPT Ligne 8: Vent moyen & direction
if ($affVentMoy == 1) {
  $_html.= '</tr><tr>';
  for ($i=0; $i<4; $i++) {
    $vitwind = cmd::byEqLogicIdAndLogicalId($eqLogic_id, 'wind10m_'.$jperiod.'p'.$i, false, 'info', $eqLogic)->execCmd();
    $dirwind = cmd::byEqLogicIdAndLogicalId($eqLogic_id, 'dirwind10m_'.$jperiod.'p'.$i, false, 'info', $eqLogic)->execCmd();
    $_html.= '<td class="meteoMCData"><div class="meteoMCDataf">';
    $_html.= '<i class="icon jeedomapp-wind" title="'.__("Vent moyen", __FILE__).'"></i> '.$vitwind.'<span class="meteoMCUnite"> km/h</span>'; 
    $_html.= '<img src="/'.$imgdir.'/vent.png" style="transform:rotate('.$dirwind.'deg)" title="'.$dirwind.'°" alt="'.$dirwind.'°"></div></td>';
  }
}
// OPT Ligne 9: Rafales de vent
if ($affVentRaf == 1) {
  $_html.= '</tr><tr>';
  for ($i=0; $i<4; $i++) {
    $vitwind = cmd::byEqLogicIdAndLogicalId($eqLogic_id, 'gust10m_'.$jperiod.'p'.$i, false, 'info', $eqLogic)->execCmd();
    $_html.= '<td class="meteoMCData">';
    $_html.= '<div class="meteoMCDataf" title="'.__("Rafales", __FILE__).'"><i class="fas fa-wind"></i> '.$vitwind.'<span class="meteoMCUnite"> km/h</span></div></td>';
  }
}
// OPT Ligne 10: Pluie & probabilité
if ($affPluiePr == 1) {
  $_html.= '</tr><tr>';
  for ($i=0; $i<4; $i++) {
    $pluie = cmd::byEqLogicIdAndLogicalId($eqLogic_id, 'rr10_'.$jperiod.'p'.$i, false, 'info', $eqLogic)->execCmd();
    $probapluie = cmd::byEqLogicIdAndLogicalId($eqLogic_id, 'probarain_'.$jperiod.'p'.$i, false, 'info', $eqLogic)->execCmd();
    $_html.= '<td class="meteoMCData">';
    $_html.= '<div class="meteoMCDatag" title="'.__("Cumul de pluie", __FILE__).'"><i class="fas fa-cloud-rain"></i> '.$pluie.'<span class="meteoMCUnite"> mm</span></div>';
    $_html.= '<div class="meteoMCDatad" title="'.__("Risque de pluie", __FILE__).'"><i class="far fa-clock"></i> '.$probapluie.'<span class="meteoMCUnite"> %</span></div>';
    $_html.= '</td>';
  }
}
// OPT Ligne 11: Pluie Max
if ($affPluieMx == 1) {
  $_html.= '</tr><tr>';
  for ($i=0; $i<4; $i++) {
    $pluiemax = cmd::byEqLogicIdAndLogicalId($eqLogic_id, 'rr1_'.$jperiod.'p'.$i, false, 'info', $eqLogic)->execCmd();
    $_html.= '<td class="meteoMCData"><div class="meteoMCDataf" title="'.__("Cumul de pluie max", __FILE__).'">';
    $_html.= '<i class="fas fa-cloud-rain icon_red"></i> '.$pluiemax.'<span class="meteoMCUnite"> mm</span></div></td>';
  }
}
// OPT Ligne 12: Probalité vent 70 et 100
if ($affVenProb == 1) {
  $_html.= '</tr><tr>';
  for ($i=0; $i<4; $i++) {
    $p70 = cmd::byEqLogicIdAndLogicalId($eqLogic_id, 'probawind70_'.$jperiod.'p'.$i, false, 'info', $eqLogic)->execCmd();
    $p100 = cmd::byEqLogicIdAndLogicalId($eqLogic_id, 'probawind100_'.$jperiod.'p'.$i, false, 'info', $eqLogic)->execCmd();
    $_html.= '<td class="meteoMCData">';
    $_html.= '<div class="meteoMCDatag" title="'.__("Risque de vent 70 kmh", __FILE__).'"><i class="fas fa-wind icon_orange"></i> '.$p70.'<span class="meteoMCUnite"> %</span></div>';
    $_html.= '<div class="meteoMCDatad" title="'.__("Risque de vent 100 kmh", __FILE__).'"><i class="fas fa-wind icon_red"></i> '.$p100.'<span class="meteoMCUnite"> %</span></div></td>';
  }
}
// OPT Ligne 13: Probalité GEL
if ($affGelProb == 1) {
  $_html.= '</tr><tr>';
  for ($i=0; $i<4; $i++) {
    $pgel = cmd::byEqLogicIdAndLogicalId($eqLogic_id, 'probafrost_'.$jperiod.'p'.$i, false, 'info', $eqLogic)->execCmd();
    $_html.= '<td class="meteoMCData">';
    $_html.= '<div class="meteoMCDataf" title="'.__("Risque de gel", __FILE__).'"><i class="far fa-snowflake"></i> '.$pgel.'<span class="meteoMCUnite"> %</span></div></td>';
  }
}
// OPT Ligne 14: Probalité Brouillard
if ($affBrouill == 1) {
  $_html.= '</tr><tr>';
  for ($i=0; $i<4; $i++) {
    $pfog = cmd::byEqLogicIdAndLogicalId($eqLogic_id, 'probafog_'.$jperiod.'p'.$i, false, 'info', $eqLogic)->execCmd();
    $_html.= '<td class="meteoMCData">';
    $_html.= '<div class="meteoMCDataf" title="'.__("Risque de brouillard", __FILE__).'"><i class="fas fa-water"></i> '.$pfog.'<span class="meteoMCUnite"> %</span></div></td>';
  }  
}
$_html.= '</tr></table>';
$_html.= '</div>';
$_html.= '</div>';
// Renvoie
echo $_html;
?>