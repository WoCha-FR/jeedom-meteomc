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

/* Parametrage affichage */
$('.eqLogicAttr[data-l1key=id]').change(function () {
  if( $(this).value() != '' ) {
    jeedom.eqLogic.byId({ 
      id: $(this).value(),
      error: function (error) {
        $.fn.showAlert({message: error.message, level: 'danger'})
      },
      success: function (data) {
        /* Masquage */
        if ( data.logicalId.length != 0 ) {
          $(".eqLogicAttr[data-l1key=configuration][data-l2key=meteomcville]").prop( "disabled", true );
        } else {
          $(".eqLogicAttr[data-l1key=configuration][data-l2key=meteomcville]").prop( "disabled", false );
        }
      }
    });
  }
});

/* Autocomplete Choix Ville */
$(".eqLogicAttr[data-l1key=configuration][data-l2key=meteomcville]").autocomplete({
  source: function( request, response ) {
    $.ajax({
      url: "plugins/meteomc/core/ajax/meteomc.ajax.php",
      dataType: "json",
      data: {
        action: 'geoapi',
        term: request.term
      },
      error: function(request, status, error) {
        handleAjaxError(request, status, error);
      },
      success: function( data ) {
        if (data.state != 'ok') {
          $.fn.showAlert({message: data.result, level: 'danger'});
          return;
        }
        if (data.result.length === 0) {
          $.fn.showAlert({message: '{{Pas de resultat}}', level: 'danger'});
          return;
        }
        response( data.result ); 
      }
    });
  },
  select: function( event, ui ) {
    $(".eqLogicAttr[data-l1key=configuration][data-l2key=meteomcinsee]").val(ui.item.insee);
  }
});

/* Permet la réorganisation des commandes dans l'équipement */
$("#table_cmd").sortable({
  axis: "y",
  cursor: "move",
  items: ".cmd",
  placeholder: "ui-state-highlight",
  tolerance: "intersect",
  forcePlaceholderSize: true
})

function addCmdToTable(_cmd) {
  if (!isset(_cmd)) {
      var _cmd = {configuration: {}};
  }
  if (!isset(_cmd.configuration)) {
      _cmd.configuration = {};
  }
  // Onglet J0
  if (init(_cmd.configuration.groupe) == 'j0') {
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td class="hidden-xs"><span class="cmdAttr" data-l1key="id"></span>'
    tr += '<span class="cmdAttr" data-l1key="type" style="display: none;"></span>';
    tr += '<span class="cmdAttr" data-l1key="subType" style="display: none;"></span></td>';
    tr += '<td class="hidden-xs"><span class="cmdAttr" data-l1key="name"></span></td>';
    tr += '<td><span class="cmdAttr" data-l1key="htmlstate"></span></td>';
    tr += '<td>';
    if(!isset(_cmd.type) || _cmd.type == 'info' ){
      tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label></span> ';
    }
    tr += '</td><td>';
    if (is_numeric(_cmd.id)) {
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
      if (init(_cmd.type) == 'action') {
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
      }
    }
    tr += '</tr>';
    $('#commandtab_j0 tbody').append(tr);
    $('#commandtab_j0 tbody tr').last().setValues(_cmd, '.cmdAttr');
    if (isset(_cmd.type)) {
      $('#commandtab_j0 tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type));
    }
    jeedom.cmd.changeType($('commandtab_j0 tbody tr').last(), init(_cmd.subType));
  }
  // Onglet J0 Periods
  if (init(_cmd.configuration.groupe) == 'j0_periods') {
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td class="hidden-xs"><span class="cmdAttr" data-l1key="id"></span>'
    tr += '<span class="cmdAttr" data-l1key="type" style="display: none;"></span>';
    tr += '<span class="cmdAttr" data-l1key="subType" style="display: none;"></span></td>';
    tr += '<td class="hidden-xs"><span class="cmdAttr" data-l1key="name"></span></td>';
    tr += '<td><span class="cmdAttr" data-l1key="htmlstate"></span></td>';
    tr += '<td>';
    if(!isset(_cmd.type) || _cmd.type == 'info' ){
      tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label></span> ';
    }
    tr += '</td><td>';
    if (is_numeric(_cmd.id)) {
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
    }
    tr += '</tr>';
    $('#commandtab_j0p tbody').append(tr);
    $('#commandtab_j0p tbody tr').last().setValues(_cmd, '.cmdAttr');
    if (isset(_cmd.type)) {
      $('#commandtab_j0p tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type));
    }
    jeedom.cmd.changeType($('commandtab_j0p tbody tr').last(), init(_cmd.subType));
  }
  // Onglet J1
  if (init(_cmd.configuration.groupe) == 'j1') {
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td class="hidden-xs"><span class="cmdAttr" data-l1key="id"></span>'
    tr += '<span class="cmdAttr" data-l1key="type" style="display: none;"></span>';
    tr += '<span class="cmdAttr" data-l1key="subType" style="display: none;"></span></td>';
    tr += '<td class="hidden-xs"><span class="cmdAttr" data-l1key="name"></span></td>';
    tr += '<td><span class="cmdAttr" data-l1key="htmlstate"></span></td>';
    tr += '<td>';
    if(!isset(_cmd.type) || _cmd.type == 'info' ){
      tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label></span> ';
    }
    tr += '</td><td>';
    if (is_numeric(_cmd.id)) {
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
    }
    tr += '</tr>';
    $('#commandtab_j1 tbody').append(tr);
    $('#commandtab_j1 tbody tr').last().setValues(_cmd, '.cmdAttr');
    if (isset(_cmd.type)) {
      $('#commandtab_j1 tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type));
    }
    jeedom.cmd.changeType($('commandtab_j1 tbody tr').last(), init(_cmd.subType));
  }
  // Onglet J1 Periods
  if (init(_cmd.configuration.groupe) == 'j1_periods') {
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td class="hidden-xs"><span class="cmdAttr" data-l1key="id"></span>'
    tr += '<span class="cmdAttr" data-l1key="type" style="display: none;"></span>';
    tr += '<span class="cmdAttr" data-l1key="subType" style="display: none;"></span></td>';
    tr += '<td class="hidden-xs"><span class="cmdAttr" data-l1key="name"></span></td>';
    tr += '<td><span class="cmdAttr" data-l1key="htmlstate"></span></td>';
    tr += '<td>';
    if(!isset(_cmd.type) || _cmd.type == 'info' ){
      tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label></span> ';
    }
    tr += '</td><td>';
    if (is_numeric(_cmd.id)) {
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
    }
    tr += '</tr>';
    $('#commandtab_j1p tbody').append(tr);
    $('#commandtab_j1p tbody tr').last().setValues(_cmd, '.cmdAttr');
    if (isset(_cmd.type)) {
      $('#commandtab_j1p tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type));
    }
    jeedom.cmd.changeType($('commandtab_j1p tbody tr').last(), init(_cmd.subType));
  }
  // Onglet J2
  if (init(_cmd.configuration.groupe) == 'j2') {
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td class="hidden-xs"><span class="cmdAttr" data-l1key="id"></span>'
    tr += '<span class="cmdAttr" data-l1key="type" style="display: none;"></span>';
    tr += '<span class="cmdAttr" data-l1key="subType" style="display: none;"></span></td>';
    tr += '<td class="hidden-xs"><span class="cmdAttr" data-l1key="name"></span></td>';
    tr += '<td><span class="cmdAttr" data-l1key="htmlstate"></span></td>';
    tr += '<td>';
    if(!isset(_cmd.type) || _cmd.type == 'info' ){
      tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label></span> ';
    }
    tr += '</td><td>';
    if (is_numeric(_cmd.id)) {
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
    }
    tr += '</tr>';
    $('#commandtab_j2 tbody').append(tr);
    $('#commandtab_j2 tbody tr').last().setValues(_cmd, '.cmdAttr');
    if (isset(_cmd.type)) {
      $('#commandtab_j2 tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type));
    }
    jeedom.cmd.changeType($('commandtab_j2 tbody tr').last(), init(_cmd.subType));
  }
  // Onglet J2 Periods
  if (init(_cmd.configuration.groupe) == 'j2_periods') {
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td class="hidden-xs"><span class="cmdAttr" data-l1key="id"></span>'
    tr += '<span class="cmdAttr" data-l1key="type" style="display: none;"></span>';
    tr += '<span class="cmdAttr" data-l1key="subType" style="display: none;"></span></td>';
    tr += '<td class="hidden-xs"><span class="cmdAttr" data-l1key="name"></span></td>';
    tr += '<td><span class="cmdAttr" data-l1key="htmlstate"></span></td>';
    tr += '<td>';
    if(!isset(_cmd.type) || _cmd.type == 'info' ){
      tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label></span> ';
    }
    tr += '</td><td>';
    if (is_numeric(_cmd.id)) {
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
    }
    tr += '</tr>';
    $('#commandtab_j2p tbody').append(tr);
    $('#commandtab_j2p tbody tr').last().setValues(_cmd, '.cmdAttr');
    if (isset(_cmd.type)) {
      $('#commandtab_j2p tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type));
    }
    jeedom.cmd.changeType($('commandtab_j2p tbody tr').last(), init(_cmd.subType));
  }
  // Onglet J3
  if (init(_cmd.configuration.groupe) == 'j3') {
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td class="hidden-xs"><span class="cmdAttr" data-l1key="id"></span>'
    tr += '<span class="cmdAttr" data-l1key="type" style="display: none;"></span>';
    tr += '<span class="cmdAttr" data-l1key="subType" style="display: none;"></span></td>';
    tr += '<td class="hidden-xs"><span class="cmdAttr" data-l1key="name"></span></td>';
    tr += '<td><span class="cmdAttr" data-l1key="htmlstate"></span></td>';
    tr += '<td>';
    if(!isset(_cmd.type) || _cmd.type == 'info' ){
      tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label></span> ';
    }
    tr += '</td><td>';
    if (is_numeric(_cmd.id)) {
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
    }
    tr += '</tr>';
    $('#commandtab_j3 tbody').append(tr);
    $('#commandtab_j3 tbody tr').last().setValues(_cmd, '.cmdAttr');
    if (isset(_cmd.type)) {
      $('#commandtab_j3 tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type));
    }
    jeedom.cmd.changeType($('commandtab_j3 tbody tr').last(), init(_cmd.subType));
  }
  // Onglet J3 Periods
  if (init(_cmd.configuration.groupe) == 'j3_periods') {
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td class="hidden-xs"><span class="cmdAttr" data-l1key="id"></span>'
    tr += '<span class="cmdAttr" data-l1key="type" style="display: none;"></span>';
    tr += '<span class="cmdAttr" data-l1key="subType" style="display: none;"></span></td>';
    tr += '<td class="hidden-xs"><span class="cmdAttr" data-l1key="name"></span></td>';
    tr += '<td><span class="cmdAttr" data-l1key="htmlstate"></span></td>';
    tr += '<td>';
    if(!isset(_cmd.type) || _cmd.type == 'info' ){
      tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label></span> ';
    }
    tr += '</td><td>';
    if (is_numeric(_cmd.id)) {
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
    }
    tr += '</tr>';
    $('#commandtab_j3p tbody').append(tr);
    $('#commandtab_j3p tbody tr').last().setValues(_cmd, '.cmdAttr');
    if (isset(_cmd.type)) {
      $('#commandtab_j3p tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type));
    }
    jeedom.cmd.changeType($('commandtab_j3p tbody tr').last(), init(_cmd.subType));
  }
  // Onglet J4
  if (init(_cmd.configuration.groupe) == 'j4') {
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td class="hidden-xs"><span class="cmdAttr" data-l1key="id"></span>'
    tr += '<span class="cmdAttr" data-l1key="type" style="display: none;"></span>';
    tr += '<span class="cmdAttr" data-l1key="subType" style="display: none;"></span></td>';
    tr += '<td class="hidden-xs"><span class="cmdAttr" data-l1key="name"></span></td>';
    tr += '<td><span class="cmdAttr" data-l1key="htmlstate"></span></td>';
    tr += '<td>';
    if(!isset(_cmd.type) || _cmd.type == 'info' ){
      tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label></span> ';
    }
    tr += '</td><td>';
    if (is_numeric(_cmd.id)) {
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
    }
    tr += '</tr>';
    $('#commandtab_j4 tbody').append(tr);
    $('#commandtab_j4 tbody tr').last().setValues(_cmd, '.cmdAttr');
    if (isset(_cmd.type)) {
      $('#commandtab_j4 tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type));
    }
    jeedom.cmd.changeType($('commandtab_j4 tbody tr').last(), init(_cmd.subType));
  }
  // Onglet J4 Periods
  if (init(_cmd.configuration.groupe) == 'j4_periods') {
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td class="hidden-xs"><span class="cmdAttr" data-l1key="id"></span>'
    tr += '<span class="cmdAttr" data-l1key="type" style="display: none;"></span>';
    tr += '<span class="cmdAttr" data-l1key="subType" style="display: none;"></span></td>';
    tr += '<td class="hidden-xs"><span class="cmdAttr" data-l1key="name"></span></td>';
    tr += '<td><span class="cmdAttr" data-l1key="htmlstate"></span></td>';
    tr += '<td>';
    if(!isset(_cmd.type) || _cmd.type == 'info' ){
      tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label></span> ';
    }
    tr += '</td><td>';
    if (is_numeric(_cmd.id)) {
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
    }
    tr += '</tr>';
    $('#commandtab_j4p tbody').append(tr);
    $('#commandtab_j4p tbody tr').last().setValues(_cmd, '.cmdAttr');
    if (isset(_cmd.type)) {
      $('#commandtab_j4p tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type));
    }
    jeedom.cmd.changeType($('commandtab_j4p tbody tr').last(), init(_cmd.subType));
  }
}
