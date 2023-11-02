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
// Déclaration des variables obligatoires
$plugin = plugin::byId('meteomc');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
?>

<div class="row row-overflow">
  <!-- Page d'accueil du plugin -->
  <div class="col-xs-12 eqLogicThumbnailDisplay">
    <legend><i class="fas fa-cog"></i>  {{Gestion}}</legend>
    <!-- Boutons de gestion du plugin -->
    <div class="eqLogicThumbnailContainer">
      <div class="cursor eqLogicAction logoPrimary" data-action="add">
        <i class="fas fa-plus-circle"></i>
        <br>
        <span>{{Ajouter}}</span>
      </div>
      <div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
        <i class="fas fa-wrench"></i>
        <br>
        <span>{{Configuration}}</span>
      </div>
    </div>
    <legend><i class="fas fa-table"></i> {{Mes Données météo}}</legend>
    <?php
    if (count($eqLogics) == 0) {
      echo '<br><div class="text-center" style="font-size:1.2em;font-weight:bold;">{{Pas de données, cliquer sur "Ajouter" pour commencer}}</div>';
    } else {
      // Champ de recherche
      echo '<div class="input-group" style="margin:5px;">';
      echo '<input class="form-control roundedLeft" placeholder="{{Rechercher}}" id="in_searchEqlogic">';
      echo '<div class="input-group-btn">';
      echo '<a id="bt_resetSearch" class="btn" style="width:30px"><i class="fas fa-times"></i></a>';
      echo '<a class="btn roundedRight hidden" id="bt_pluginDisplayAsTable" data-coreSupport="1" data-state="0"><i class="fas fa-grip-lines"></i></a>';
      echo '</div>';
      echo '</div>';
      // Liste des équipements du plugin
      echo '<div class="eqLogicThumbnailContainer">';
      foreach ($eqLogics as $eqLogic) {
        $opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
        echo '<div class="eqLogicDisplayCard cursor '.$opacity.'" data-eqLogic_id="' . $eqLogic->getId() . '">';
        echo '<img src="' . $plugin->getPathImgIcon() . '">';
        echo '<br>';
        echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
        echo '<span class="hiddenAsCard displayTableRight hidden">';
        echo ($eqLogic->getIsVisible() == 1) ? '<i class="fas fa-eye" title="{{Equipement visible}}"></i>' : '<i class="fas fa-eye-slash" title="{{Equipement non visible}}"></i>';
        echo '</span>';
        echo '</div>';
      }
      echo '</div>';
    }
    ?>
  </div> <!-- /.eqLogicThumbnailDisplay -->
  <!-- Page de présentation de l'équipement -->
  <div class="col-xs-12 eqLogic" style="display: none;">
    <!-- barre de gestion de l'équipement -->
    <div class="input-group pull-right" style="display:inline-flex;">
      <span class="input-group-btn">
        <!-- Les balises <a></a> sont volontairement fermées à la ligne suivante pour éviter les espaces entre les boutons. Ne pas modifier -->
        <a class="btn btn-sm btn-default eqLogicAction roundedLeft" data-action="configure"><i class="fas fa-cogs"></i><span class="hidden-xs"> {{Configuration avancée}}</span></a>
        <a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}</a>
        <a class="btn btn-sm btn-danger eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}</a>
      </span>
    </div>
    <!-- Onglets -->
    <ul class="nav nav-tabs" role="tablist">
      <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
      <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Equipement}}</a></li>
      <li role="presentation"><a href="#commandtab_j0" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list"></i> {{Météo du jour}}</a></li>
      <li role="presentation"><a href="#commandtab_j0p" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list"></i> {{Périodes J}}</a></li>
      <li role="presentation"><a href="#commandtab_j1" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list"></i> {{Météo J +1}}</a></li>
      <li role="presentation"><a href="#commandtab_j1p" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list"></i> {{Périodes J +1}}</a></li>
      <li role="presentation"><a href="#commandtab_j2" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list"></i> {{Météo J +2}}</a></li>
      <li role="presentation"><a href="#commandtab_j2p" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list"></i> {{Périodes J +2}}</a></li>
      <li role="presentation"><a href="#commandtab_j3" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list"></i> {{Météo J +3}}</a></li>
      <li role="presentation"><a href="#commandtab_j3p" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list"></i> {{Périodes J +3}}</a></li>
      <li role="presentation"><a href="#commandtab_j4" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list"></i> {{Météo J +4}}</a></li>
      <li role="presentation"><a href="#commandtab_j4p" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list"></i> {{Périodes J +4}}</a></li>
    </ul>
    <div class="tab-content">
      <!-- Onglet de configuration de l'équipement -->
      <div role="tabpanel" class="tab-pane active" id="eqlogictab">
        <!-- Partie gauche de l'onglet "Equipements" -->
        <!-- Paramètres généraux et spécifiques de l'équipement -->
        <form class="form-horizontal">
          <fieldset>
            <div class="col-lg-6">
              <legend><i class="fas fa-wrench"></i> {{Paramètres généraux}}</legend>
              <div class="form-group">
                <label class="col-sm-4 control-label">{{Nom de l'équipement}}</label>
                <div class="col-sm-6">
                  <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display:none;">
                  <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement}}">
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label" >{{Objet parent}}</label>
                <div class="col-sm-6">
                  <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                    <option value="">{{Aucun}}</option>
                    <?php
                    $options = '';
                    foreach ((jeeObject::buildTree(null, false)) as $object) {
                      $options .= '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
                    }
                    echo $options;
                    ?>
                  </select>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label">{{Catégorie}}</label>
                <div class="col-sm-6">
                  <?php
                  foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
                    echo '<label class="checkbox-inline">';
                    echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" >' . $value['name'];
                    echo '</label>';
                  }
                  ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label">{{Options}}</label>
                <div class="col-sm-6">
                  <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked>{{Activer}}</label>
                  <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked>{{Visible}}</label>
                </div>
              </div>

              <legend><i class="fas fa-cogs"></i> {{Paramètres spécifiques}}</legend>
              <div class="form-group">
                <label class="col-sm-4 control-label">{{Ville}}</label>
                <div class="col-sm-4">
                  <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="meteomcville" placeholder="{{Saisir la ville}}">
                  <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="meteomcinsee" style="display:none;">
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label" >{{Nombre de jours à afficher}}</label>
                <div class="col-sm-2">
                  <select class="form-control eqLogicAttr" data-l1key="configuration" data-l2key="affnbjours">
                    <option value="5">{{5}}</option>
                    <option value="4">{{4}}</option>
                    <option value="3">{{3}}</option>
                    <option value="2">{{2}}</option>
                    <option value="1">{{1}}</option>
                  </select>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label" >{{Affichage de la météo actuelle}}</label>
                <div class="col-sm-6">
                  <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="affencours">{{Activer}}</label>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label" >{{Heures de lever et coucher du soleil}}</label>
                <div class="col-sm-6">
                  <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="affinfosun">{{Activer}}</label>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label" >{{Durée du jour et différence}}</label>
                <div class="col-sm-6">
                  <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="affdureejo">{{Activer}}</label>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label" >{{Durée d'ensoleillement}}</label>
                <div class="col-sm-6">
                  <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="affdureeso">{{Activer}}</label>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label" >{{Phase de Lune}}</label>
                <div class="col-sm-6">
                  <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="affinfolun">{{Activer}}</label>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label" >{{Vent}}</label>
                <div class="col-sm-6">
                  <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="affventmoy">{{Activer}}</label>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label" >{{Rafales de vent}}</label>
                <div class="col-sm-6">
                  <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="affventraf">{{Activer}}</label>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label" >{{Précipitations}}</label>
                <div class="col-sm-6">
                  <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="affpluiepr">{{Activer}}</label>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label" >{{Précipitations maximales}}</label>
                <div class="col-sm-6">
                  <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="affpluiemx">{{Activer}}</label>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label" >{{Probabilité de grand vent}}</label>
                <div class="col-sm-6">
                  <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="affvenprob">{{Activer}}</label>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label" >{{Probabilité de gel}}</label>
                <div class="col-sm-6">
                  <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="affgelprob">{{Activer}}</label>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label" >{{Probabilité de brouillard}}</label>
                <div class="col-sm-6">
                  <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="affbrouill">{{Activer}}</label>
                </div>
              </div>
            </div>
            <!-- Partie droite de l'onglet "Équipement" -->
            <!-- Affiche un champ de commentaire par défaut mais vous pouvez y mettre ce que vous voulez -->
            <div class="col-lg-6">
              <legend><i class="fas fa-info"></i> {{Informations}}</legend>
              <div class="form-group">
                <label class="col-sm-4 control-label">{{Description}}</label>
                <div class="col-sm-6">
                  <textarea class="form-control eqLogicAttr autogrow" data-l1key="comment"></textarea>
                </div>
              </div>
            </div>
          </fieldset>
        </form>
      </div><!-- /.tabpanel #eqlogictab-->

      <!-- Onglet des commandes de l'équipement Jour0 -->
      <div role="tabpanel" class="tab-pane" id="commandtab_j0">
        <div class="table-responsive">
          <table id="table_cmd" class="table table-bordered table-condensed">
            <thead>
              <tr>
                <th class="hidden-xs" style="min-width:50px;width:70px;">ID</th>
                <th style="min-width:200px;width:350px;">{{Nom}}</th>
                <th>{{Etat}}</th>
                <th style="min-width:80px;width:200px;">{{Options}}</th>
                <th style="min-width:80px;width:200px;">{{Actions}}</th>
              </tr>
            </thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div><!-- /.tabpanel #commandtab_j0-->
      <!-- Onglet des commandes de l'équipement Jour0 Periodes -->
      <div role="tabpanel" class="tab-pane" id="commandtab_j0p">
        <div class="table-responsive">
          <table id="table_cmd" class="table table-bordered table-condensed">
            <thead>
              <tr>
                <th class="hidden-xs" style="min-width:50px;width:70px;">ID</th>
                <th style="min-width:200px;width:350px;">{{Nom}}</th>
                <th>{{Etat}}</th>
                <th style="min-width:80px;width:200px;">{{Options}}</th>
                <th style="min-width:80px;width:200px;">{{Actions}}</th>
              </tr>
            </thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div><!-- /.tabpanel #commandtab_j0p-->
      <!-- Onglet des commandes de l'équipement Jour1 -->
      <div role="tabpanel" class="tab-pane" id="commandtab_j1">
        <div class="table-responsive">
          <table id="table_cmd" class="table table-bordered table-condensed">
            <thead>
              <tr>
                <th class="hidden-xs" style="min-width:50px;width:70px;">ID</th>
                <th style="min-width:200px;width:350px;">{{Nom}}</th>
                <th>{{Etat}}</th>
                <th style="min-width:80px;width:200px;">{{Options}}</th>
                <th style="min-width:80px;width:200px;">{{Actions}}</th>
              </tr>
            </thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div><!-- /.tabpanel #commandtab_j1-->
      <!-- Onglet des commandes de l'équipement Jour1 Periodes -->
      <div role="tabpanel" class="tab-pane" id="commandtab_j1p">
        <div class="table-responsive">
          <table id="table_cmd" class="table table-bordered table-condensed">
            <thead>
              <tr>
                <th class="hidden-xs" style="min-width:50px;width:70px;">ID</th>
                <th style="min-width:200px;width:350px;">{{Nom}}</th>
                <th>{{Etat}}</th>
                <th style="min-width:80px;width:200px;">{{Options}}</th>
                <th style="min-width:80px;width:200px;">{{Actions}}</th>
              </tr>
            </thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div><!-- /.tabpanel #commandtab_j1p-->
      <!-- Onglet des commandes de l'équipement Jour2 -->
      <div role="tabpanel" class="tab-pane" id="commandtab_j2">
        <div class="table-responsive">
          <table id="table_cmd" class="table table-bordered table-condensed">
            <thead>
              <tr>
                <th class="hidden-xs" style="min-width:50px;width:70px;">ID</th>
                <th style="min-width:200px;width:350px;">{{Nom}}</th>
                <th>{{Etat}}</th>
                <th style="min-width:80px;width:200px;">{{Options}}</th>
                <th style="min-width:80px;width:200px;">{{Actions}}</th>
              </tr>
            </thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div><!-- /.tabpanel #commandtab_j2-->
      <!-- Onglet des commandes de l'équipement Jour2 Periodes -->
      <div role="tabpanel" class="tab-pane" id="commandtab_j2p">
        <div class="table-responsive">
          <table id="table_cmd" class="table table-bordered table-condensed">
            <thead>
              <tr>
                <th class="hidden-xs" style="min-width:50px;width:70px;">ID</th>
                <th style="min-width:200px;width:350px;">{{Nom}}</th>
                <th>{{Etat}}</th>
                <th style="min-width:80px;width:200px;">{{Options}}</th>
                <th style="min-width:80px;width:200px;">{{Actions}}</th>
              </tr>
            </thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div><!-- /.tabpanel #commandtab_j2p-->
      <!-- Onglet des commandes de l'équipement Jour3 -->
      <div role="tabpanel" class="tab-pane" id="commandtab_j3">
        <div class="table-responsive">
          <table id="table_cmd" class="table table-bordered table-condensed">
            <thead>
              <tr>
                <th class="hidden-xs" style="min-width:50px;width:70px;">ID</th>
                <th style="min-width:200px;width:350px;">{{Nom}}</th>
                <th>{{Etat}}</th>
                <th style="min-width:80px;width:200px;">{{Options}}</th>
                <th style="min-width:80px;width:200px;">{{Actions}}</th>
              </tr>
            </thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div><!-- /.tabpanel #commandtab_j3-->
      <!-- Onglet des commandes de l'équipement Jour3 Periodes -->
      <div role="tabpanel" class="tab-pane" id="commandtab_j3p">
        <div class="table-responsive">
          <table id="table_cmd" class="table table-bordered table-condensed">
            <thead>
              <tr>
                <th class="hidden-xs" style="min-width:50px;width:70px;">ID</th>
                <th style="min-width:200px;width:350px;">{{Nom}}</th>
                <th>{{Etat}}</th>
                <th style="min-width:80px;width:200px;">{{Options}}</th>
                <th style="min-width:80px;width:200px;">{{Actions}}</th>
              </tr>
            </thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div><!-- /.tabpanel #commandtab_j3p-->
      <!-- Onglet des commandes de l'équipement Jour4 -->
      <div role="tabpanel" class="tab-pane" id="commandtab_j4">
        <div class="table-responsive">
          <table id="table_cmd" class="table table-bordered table-condensed">
            <thead>
              <tr>
                <th class="hidden-xs" style="min-width:50px;width:70px;">ID</th>
                <th style="min-width:200px;width:350px;">{{Nom}}</th>
                <th>{{Etat}}</th>
                <th style="min-width:80px;width:200px;">{{Options}}</th>
                <th style="min-width:80px;width:200px;">{{Actions}}</th>
              </tr>
            </thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div><!-- /.tabpanel #commandtab_j4-->
      <!-- Onglet des commandes de l'équipement Jour4 Periodes -->
      <div role="tabpanel" class="tab-pane" id="commandtab_j4p">
        <div class="table-responsive">
          <table id="table_cmd" class="table table-bordered table-condensed">
            <thead>
              <tr>
                <th class="hidden-xs" style="min-width:50px;width:70px;">ID</th>
                <th style="min-width:200px;width:350px;">{{Nom}}</th>
                <th>{{Etat}}</th>
                <th style="min-width:80px;width:200px;">{{Options}}</th>
                <th style="min-width:80px;width:200px;">{{Actions}}</th>
              </tr>
            </thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div><!-- /.tabpanel #commandtab_j4p-->

    </div><!-- /.tab-content -->
  </div><!-- /.eqLogic -->
</div><!-- /.row row-overflow -->

<!-- Inclusion du fichier javascript du plugin (dossier, nom_du_fichier, extension_du_fichier, id_du_plugin) -->
<?php include_file('desktop', 'meteomc', 'js', 'meteomc');?>
<!-- Inclusion du fichier javascript du core - NE PAS MODIFIER NI SUPPRIMER -->
<?php include_file('core', 'plugin.template', 'js');?>
