var
  installationsTable,
  $billingsTable = $('#billings'),
  $tbody = $('#billings > tbody'),
  selectedByPage = [],
  contents = new Map(),
  totalizer = []
;

class module{static enabled=!1;static enableDebug(){this.enabled=!0}static debug(...e){if(this.enabled)return console.log(...e)}static error(...e){if(this.enabled)return console.error(...e)}static info(...e){if(this.enabled)return console.info(...e)}static table(...e){if(this.enabled)return console.table(...e)}}
module.enableDebug();

/**{% if chosenPayment in ['unusual', 'closing'] %}**/
var
  contractualCharges = [],
  unboundContents = 0
;
/**{% endif %}**/
/**{% if chosenPayment == 'another' %}**/
var
  unboundContents = 1
;
/**{% endif %}**/
/**{% if chosenPayment == 'carnet' %}**/
var
  referenceMonths = [],
  sortReferences = function(a, b) {
    var
      aParts = a.split("/"),
      valueA = aParts[1] + aParts[0],
      bParts = b.split("/"),
      valueB = bParts[1] + bParts[0]
    ;
    return valueA > valueB;
  }
;
/**{% endif %}**/
/**{% if chosenPayment in ['carnet', 'prepayment'] %}**/
var
  currentStartDate = $('input[name="startdate"]').val()
;
/**{% endif %}**/
/**{% if chosenPayment is not same as 'carnet' %}**/
var
  $tableTotal = $('#billings > tfoot').find('th.tableTotal'),
  valueToPayDivider = 0
;
/**{% endif %}**/

/**{% if chosenPayment is not same as 'another' %}**/
// Um formatador para exibir um ícone indicativo da situação do
// contrato e/ou item de contrato
var blockedFormatter = function ( value, type, row, meta ) {
  var buildIcon = function (color, icon, title) {
    return ''
      + '<span data-position="left center"'
      +       'data-blue data-inverted '
      +       'data-tooltip="' + title + '">'
      +   '<i class="' + icon + ' ' + color + ' icon"></i>'
      + '</span>'
    ;
  };
  
  switch (value) {
    case 1:
      // Apenas o item de contrato está inativo
      return buildIcon('archive', 'darkorange', ''
        + 'O item de contrato está encerrado'
      );
    case 2:
      // Apenas o contrato está inativo ou encerrado
      if (row.contractenddate !== null) {
        return buildIcon('archive', 'grey', ''
          + 'O contrato ao qual este item pertence está encerrado'
        );
      } else {
        return buildIcon('lock', 'darkred', ''
          + 'O contrato ao qual este item pertence está inativo'
        );
      }
    case 3:
      // Tanto o contrato está encerrado ou inativo quanto o
      // respectivo item de contrato foi encerrado
      return buildIcon('archive', 'grey', ''
        + 'O item de contrato está encerrado'
      );
    case 4:
      // Apenas a unidade/filial do cliente está bloqueada
      return buildIcon('lock', 'darkorange', ''
        + 'O contrato ao qual este item pertence está inativo em '
        + 'função do bloqueio na unidade/filial do cliente'
      );
    case 5:
      // A unidade/filial do cliente está bloqueada, mas o item de
      // contrato já está encerrado também
      return buildIcon('archive', 'grey', ''
        + 'O item de contrato está encerrado'
      );
    case 6:
      // Tanto o contrato está inativo quanto a unidade/filial do
      // cliente está bloqueada
      if (row.contractenddate !== null) {
        return buildIcon('archive', 'grey', ''
          + 'O contrato ao qual este item pertence está encerrado'
        );
      } else {
        return buildIcon('lock', 'darkred', ''
          + 'A conta da unidade/filial do cliente está bloqueada e '
          + 'o contrato ao qual este item pertence está inativo'
        );
      }
    case 7:
      // Tanto o contrato está inativo quanto a unidade/filial do
      // cliente está bloqueada e o item de contrato foi encerrado
      return buildIcon('archive', 'grey', ''
        + 'O item de contrato está encerrado'
      );
    case 8:
      // Apenas o cliente está bloqueado
      return buildIcon('lock', 'darkorange', ''
        + 'O contrato ao qual este item pertence está inativo em '
        + 'função do bloqueio na conta do cliente'
      );
    case 9:
      // O cliente está bloqueado e o item de contrato foi encerrado
      return buildIcon('archive', 'grey', ''
        + 'O item de contrato está encerrado'
      );
    case 10:
      // Tanto a empresa/cliente está bloqueado quanto este contrato
      // está inativo
      if (row.contractenddate !== null) {
        return buildIcon('archive', 'grey', ''
          + 'O contrato ao qual este item pertence está encerrado'
        );
      } else {
        return buildIcon('lock', 'darkred', ''
          + 'A conta do cliente está bloqueada e o contrato ao '
          + 'qual este item pertence está inativo'
        );
      }
    case 11:
      // Tanto a empresa/cliente está bloqueado quanto este contrato
      // está inativo e o item de contrato foi encerrado
      return buildIcon('archive', 'grey', ''
        + 'O item de contrato está encerrado'
      );
    case 12:
      // Tanto a empresa/cliente quanto sua unidade/filial estão
      // bloqueados
      return buildIcon('lock', 'darkorange', ''
        + 'O contrato ao qual este item pertence está inativo em '
        + 'função do bloqueio tanto na conta do cliente quanto de '
        + 'sua unidade/filial'
      );
    case 13:
      // Tanto a empresa/cliente quanto sua unidade/filial estão
      // bloqueados e o item de contrato foi encerrado
      return buildIcon('archive', 'grey', ''
        + 'O item de contrato está encerrado'
      );
    case 14:
      // Tanto a empresa/cliente quanto sua unidade/filial estão
      // bloqueados e este contrato está inativo
      if (row.contractenddate !== null) {
        return buildIcon('archive', 'grey', ''
          + 'O contrato ao qual este item pertence está encerrado'
        );
      } else {
        return buildIcon('lock', 'darkred', ''
          + 'As contas do cliente e de sua unidade/filial estão '
          + 'bloqueadas e o contrato ao qual este item pertence '
          + 'está inativo'
        );
      }
    case 15:
      // Tanto a empresa/cliente quanto sua unidade/filial estão
      // bloqueados e este contrato está inativo e o item de
      // contrato foi encerrad0
      return buildIcon('archive', 'grey', ''
        + 'O item de contrato está encerrado'
      );
    case 16:
      // O contratante está bloqueado
      return buildIcon('lock', 'darkorange', ''
        + 'Este contrato está inativo em função do bloqueio na '
        + 'conta do contratante'
      );
    case 17:
      // O contratante está bloqueado e o item de contrato foi
      // encerrado
      return buildIcon('archive', 'grey', ''
        + 'O item de contrato está encerrado'
      );
    case 18:
      // Tanto o contratante está bloqueado quanto este contrato
      // está inativo
      if (row.contractenddate !== null) {
        return buildIcon('archive', 'grey', ''
          + 'O contrato ao qual este item pertence está encerrado'
        );
      } else {
        return buildIcon('lock', 'darkred', ''
          + 'A conta do contratante está bloqueada e o contrato ao '
          + 'qual este item pertence está inativo'
        );
      }
    case 19:
      // Tanto o contratante está bloqueado quanto este contrato
      // está inativo e o item de contrato foi encerrado
      return buildIcon('archive', 'grey', ''
        + 'O item de contrato está encerrado'
      );
    case 20:
      // Tanto o contratante quanto a unidade/filial do cliente
      // estão bloqueados
      return buildIcon('lock', 'darkorange', ''
        + 'Este contrato está inativo em função do bloqueio tanto '
        + 'na conta do contratante quanto da unidade/filial do '
        + 'cliente'
      );
    case 21:
      // Tanto o contratante quanto a unidade/filial do cliente
      // estão bloqueados e o item de contrato foi encerrado
      return buildIcon('archive', 'grey', ''
        + 'O item de contrato está encerrado'
      );
    case 22:
      // Tanto o contratante quanto a unidade/filial do cliente e
      // o contrato estão bloqueados
      if (row.contractenddate !== null) {
        return buildIcon('archive', 'grey', ''
          + 'O contrato ao qual este item pertence está encerrado'
        );
      } else {
        return buildIcon('lock', 'darkred', ''
          + 'As contas do contratante e da unidade/filial do '
          + 'cliente estão bloqueadas e o contrato ao qual este '
          + 'item pertence está inativo'
        );
      }
    case 23:
      // Tanto o contratante quanto a unidade/filial do cliente e
      // o contrato estão bloqueados e o item de contrato foi
      // encerrado
      return buildIcon('archive', 'grey', ''
        + 'O item de contrato está encerrado'
      );
    case 24:
      // Tanto o contratante quanto o cliente estão bloqueados
      return buildIcon('lock', 'darkorange', ''
        + 'Este contrato está inativo em função dos bloqueios '
        + 'tanto na conta do contratante quanto na do cliente'
      );
    case 25:
      // Tanto o contratante quanto o cliente estão bloqueados e o
      // item de contrato foi encerrado
      return buildIcon('archive', 'grey', ''
        + 'O item de contrato está encerrado'
      );
    case 26:
      // Tanto o contratante quanto a empresa/cliente estão
      // bloqueados e este contrato está inativo
      if (row.contractenddate !== null) {
        return buildIcon('archive', 'grey', ''
          + 'O contrato ao qual este item pertence está encerrado'
        );
      } else {
        return buildIcon('lock', 'darkred', ''
          + 'As contas do contratante e do cliente estão bloqueadas '
          + 'e o contrato ao qual este item pertence está inativo'
        );
      }
    case 27:
      // Tanto o contratante quanto a empresa/cliente estão
      // bloqueados e este contrato está inativo e o item de
      // contrato foi encerrado
      return buildIcon('archive', 'grey', ''
        + 'O item de contrato está encerrado'
      );
    case 28:
      // Tanto o contratante, quanto a empresa/cliente e a sua
      // unidade/filial estão bloqueados
      return buildIcon('lock', 'darkorange', ''
        + 'O contrato ao qual este item pertence está inativo em '
        + 'função dos bloqueios tanto na conta do contratante '
        + 'quanto na do cliente e também no da sua unidade/filial'
      );
    case 29:
      // Tanto o contratante, quanto a empresa/cliente e a sua
      // unidade/filial estão bloqueados e o item de contrato foi
      // encerrado
      return buildIcon('archive', 'grey', ''
        + 'O item de contrato está encerrado'
      );
    case 30:
      // Tanto o contratante, quanto a empresa/cliente e a sua
      // unidade/filial estão bloqueados e este contrato está
      // inativo
      if (row.contractenddate !== null) {
        return buildIcon('archive', 'grey', ''
          + 'O contrato ao qual este item pertence está encerrado'
        );
      } else {
        return buildIcon('lock', 'darkred', ''
          + 'O contrato ao qual este item pertence está inativo em '
          + 'função dos bloqueios tanto na conta do contratante '
          + 'quanto na do cliente e também no da sua unidade/filial '
          + 'e o contrato está inativo'
        );
      }
    case 31:
      // Todos estão bloqueados (contratante, empresa/cliente,
      // unidade/filial da empresa/cliente) o contrato está inativo
      // e o item de contrato foi encerrado
      return buildIcon('archive', 'grey', ''
        + 'O item de contrato está encerrado'
      );
    default:
      // Não temos bloqueios
      if (row.signaturedate === null) {
        return buildIcon('exclamation triangle', 'darkorange', ''
          + 'Este contrato não está assinado')
        ;
      } else {
        if (row.startdate === null) {
          return buildIcon('exclamation triangle', 'darkorange', ''
            + 'A instalação do equipamento ainda não foi registrada'
          );
        } else {
          if (row.notracker) {
            return buildIcon('exclamation triangle', 'darkorange', ''
              + 'O item de contrato está ativo mas sem um '
              + 'equipamento instalado'
            );
          } else {
            if (row.vehicleblocked) {
              return buildIcon('exclamation triangle', 'darkorange', ''
                + 'O item de contrato está ativo mas o veículo '
                + 'está bloqueado'
              );
            } else {
              return buildIcon('check', 'darkgreen', ''
                + 'Este item de contrato está ativo'
              );
            }
          }
        }
      }
  }
};
/**{% endif %}**/

$(document).ready(function()
{
  // -------------------------------[ Componentes do formulário ]---
  $("input[name='customername']")
    .autocomplete(customerNameCompletionOptions)
  ;
  $("input[name='subsidiaryname']")
    .autocomplete(subsidiaryNameCompletionOptions)
  ;
  /**{% if chosenPayment == 'unusual' %}**/
  $("input[name='chargeFromAssociate']")
    .closest('.ui.checkbox')
    .checkbox(checkboxOptions)
  ;
  $("input[name='_chargeFromAssociate']")
    .change(chargeFromAssociateHandler)
  ;
  $('input[name="associateid"]')
    .closest('div.ui.dropdown')
    .dropdown(associateOptions)
  ;
  /**{% endif %}**/

  $("#duedate")
    .calendar(calendarOptions)
  ;
  $("input[name='duedate")
    .mask({
      type: 'date',
      validate: true
    })
  ;
  /**{% if chosenPayment is not same as 'carnet' %}**/
  $("input[name='valuetopay']")
    .mask({
      type: 'monetary',
      trim: true,
      allowNegativeValues: false,
      decimalsPlaces: 2,
      maxLength: 15
    })
    .blur(monetaryHandler)
  ;
  /**{% endif %}**/
  /**{% if chosenPayment in ['carnet', 'prepayment'] %}**/
  $("#startdate")
    .calendar(calendarOptions)
  ;
  $("input[name='startdate")
    .mask({
      type: 'date',
      validate: true
    })
    .change(function(event) {
      if (currentStartDate !== $(event.target).val()) {
        currentStartDate = $(event.target).val();

        // Força o carregamento da tabela
        tableReload();
      }
    });
  ;
  $("input[name='numberofparcels']")
    .mask({
      type: 'number',
      trim: true,
      allowNegativeValues: false,
      maxLength: 2
    })
    .change(function(event) {
      // Força o carregamento da tabela
      tableReload();
    });
  ;
  /**{% endif %}**/
  /**{% if chosenPayment is not same as 'carnet' %}**/
  $('input[name="paymentconditionid"]')
    .closest('div.ui.dropdown')
    .dropdown({
      onChange: paymentConditionHandler
    })
  ;
  /**{% endif %}**/
  /**{% if chosenPayment == 'another' %}**/
  // Mascarámos os campos da tabela
  $tbody
    .find('tr')
    .last()
    .find('input[name="billings[][value]"')
    .mask({
      type: 'monetary',
      trim: true,
      allowNegativeValues: false,
      decimalsPlaces: 2,
      maxLength: 15
    })
    .blur(monetaryHandler)
    .change(recalculateTotals)
  ;
  $tbody
    .find('tr:last')
    .find('td:eq(3)')
    .click(removeThis)
  ;

  // Atualiza a sequência dos campos
  module.debug('Disparando atualização tabela');
  tableRefresh();
  /**{% endif %}**/

  /**{% if chosenPayment is not same as 'another' %}**/
  // Atualiza a tabela para reajustar as colunas em caso de
  // alternância da barra lateral
  $('#ResizeSidebarMenu').click(function() {
    setTimeout(function() {
      installationsTable
        .columns
        .adjust()
      ;
    }, 500);
  });

  // Atualiza a tabela para reajustar as colunas em caso de
  // redimensionamento da janela
  $(window).resize(function() {
    setTimeout(function() {
      installationsTable
        .columns
        .adjust()
      ;
    }, 500);
  });

  installationsTable = $('#installations').DataTable({
    pagingType: "first_last_numbers",
    lengthChange: false,
    pageLength: 10,
    searching: false,
    scrollX: true,
    language: {
      url: "{{ i18n('datatables/plugins/i18n/Portuguese-Brasil.json') }}",
    },
    columnDefs: [
      { "targets": 0,
        "name": "selected",
        "data": "selected",
        "orderable": false,
        "className": "select-checkbox",
        "width": "20px" },
      { "targets": 1,
        "name": "id",
        "data": "id",
        "visible": false,
        "orderable": false },
      { "targets": 2,
        "name": "plate",
        "data": "plate",
        "className": "dt-center bold",
        "visible": true,
        "width": "80px",
        "orderable": false },
      { "targets": 3,
        "name": "model",
        "data": "model",
        "visible": true,
        "orderable": true,
        "orderable": false },
      { "targets": 4,
        "name": "installations.installationnumber",
        "data": "installationnumber",
        "visible": true,
        "orderable": false,
        "width": "120px" },
      { "targets": 5,
        "name": "blockedlevel",
        "data": "blockedlevel",
        "className": "dt-center",
        "visible": true,
        "orderable": false,
        "width": "10px",
        "render": blockedFormatter },
      { "targets": 6,
        "name": "installations.monthprice",
        "data": "monthprice",
        "className": "dt-right",
        "visible": true,
        "orderable": false,
        "width": "80px",
        "render": monetaryFormatter },
      { "targets": 7,
        "name": "installations.startdate",
        "data": "startdate",
        "className": "dt-center",
        "visible": true,
        "width": "80px",
        "orderable": false },
      { "targets": 8,
        "name": "installations.enddate",
        "data": "enddate",
        "visible": false,
        "orderable": false },
      { "targets": 9,
        "name": "installations.dueday",
        "data": "dueday",
        "visible": false,
        "orderable": false },
      { "targets": 10,
        "name": "paymentconditionid",
        "data": "paymentconditionid",
        "visible": false,
        "orderable": false },
      { "targets": 11,
        "name": "numberofparcels",
        "data": "numberofparcels",
        "className": "dt-center",
        "visible": true,
        "orderable": false },
      { "targets": 12,
        "name": "installations.lastdayofbillingperiod",
        "data": "lastdayofbillingperiod",
        "className": "dt-center",
        "visible": true,
        "width": "80px",
        "orderable": false },
      { "targets": 13,
        "name": "firstduedate",
        "data": "firstduedate",
        "className": "dt-center",
        "visible": true,
        "orderable": false },
      { "targets": 14,
        "name": "contracts.signaturedate",
        "data": "signaturedate",
        "visible": false,
        "orderable": false },
      { "targets": 15,
        "name": "contracts.enddate",
        "data": "contractenddate",
        "visible": false,
        "orderable": false },
      { "targets": 16,
        "name": "vehicleblocked",
        "data": "vehicleblocked",
        "visible": false,
        "orderable": false },
      { "targets": 17,
        "name": "notracker",
        "data": "notracker",
        "visible": false,
        "orderable": false },
      { "targets": 18,
        "name": "containstrackingdata",
        "data": "containstrackingdata",
        "visible": false,
        "orderable": false }
    ],
    order: [[ 2, 'asc' ]],
    select: {
      style: 'multi',
      items: 'row'
    },
    dom:
      "<'ui grid'"+
        "<'row'"+
          "<'sixteen wide column'"+
            "<'toolbar'>"+
          ">"+
        ">"+
        "<'row dt-table'"+
          "<'sixteen wide column'tr>"+
        ">"+
        "<'row'"+
          "<'seven wide column'i>"+
          "<'right aligned nine wide column'p>"+
        ">"+
      ">",
    processing: true,
    serverSide: true,
    ajax: {
      url: "{{ path_for(getInstallations.URL) }}",
      type: "{{ getInstallations.method }}",
      data: function ( params ) {
        params.customerID      = $('input[name="customerid"]').val();
        params.customerName    = $('input[name="customername"]').val();
        params.subsidiaryID    = $('input[name="subsidiaryid"]').val();
        params.subsidiaryName  = $('input[name="subsidiaryname"]').val();
        /**{% if chosenPayment == 'carnet' %}**/
        params.toCarnet        = true;
        params.withoutInactive = true;
        /**{% endif %}**/
        /**{% if chosenPayment == 'prepayment' %}**/
        params.withoutInactive = true;
        /**{% endif %}**/
        /**{% if chosenPayment == 'unusual' %}**/
        params.fromAssociate   = $("input[name='chargeFromAssociate']").val();
        params.associateID     = $('input[name="associateid"]').val();
        /**{% endif %}**/
      },
      error: handleAjaxError
    },
    responsive: true,
    initComplete: function(settings, json) {
      // Unstack Pagination
      $('div.ui.pagination.menu')
        .removeClass('stackable')
        .addClass('unstackable')
      ;
      module.debug('settings', settings);
      module.debug('json', json);
    },
    rowCallback: function(row, data) {
      // Força a seleção dos ítens em caso de mudança de página
      var
        info = installationsTable.page.info(),
        $headerCheck = $("div.dataTables_scrollHead table thead th.select-checkbox")
      ;

      // Deixamos o botão em branco, inicialmente
      $headerCheck
        .removeClass("selected")
        .removeClass("indeterminate")
      ;

      if ( $.inArray(data.id, selectedByPage[info.page]) !== -1 ) {
        $(row)
          .addClass('selected')
        ;
        installationsTable
          .row(row)
          .select()
        ;

        // Atualizamos o estado do botão marcar/desmarcar tudo
        if (selectedByPage[info.page].length == (info.end - info.start)) {
          $headerCheck
            .removeClass("indeterminate")
            .addClass("selected")
          ;
        } else {
          $headerCheck
            .removeClass("selected")
            .addClass("indeterminate")
          ;
        }
      }
    },
    createdRow: function(row, data, dataIndex) {
      // Deixa as linhas com cores diferentes para indicar os
      // contratos que não estão ativos
      if (data.enddate !== null) {
        $(row)
          .addClass('damaged')
        ;
      } else {
        if (data.notracker) {
          if (data.containstrackingdata) {
            // Está sem rastreador
            $(row)
              .addClass('maintenance')
            ;
          } else {
            // Nunca foi rastreado
            $(row)
              .addClass('broked')
            ;
          }
        } else {
          if (data.blockedlevel > 0) {
            if (data.enddate !== null) {
              $(row)
                .addClass('damaged')
              ;
            } else {
              $(row)
                .addClass('blocked')
              ;
            }
          } else {
            if (data.vehicleblocked === true) {
              $(row)
                .addClass('blocked')
              ;
            }
          }
        }
      }
    }
  });

  // Manipulação do botão de selecionar/descelecionar tudo  
  $('#installations')
    .find("th.select-checkbox")
    .on("click", function() {
      if (selectedByPage.length > 0) {
        // Analisamos os critétios de seleção
        if ($(this).hasClass("selected")) {
          // Descelecionamos tudo
          installationsTable
            .rows()
            .deselect()
          ;
          $(this)
            .removeClass("indeterminate")
            .removeClass("selected")
          ;
        } else {
          // Selecionamos tudo
          installationsTable
            .rows()
            .select()
          ;
          $(this)
            .removeClass("indeterminate")
            .addClass("selected")
          ;
        }
      }
    })
  ;

  // Manipula os eventos do datatables para permitir a correta análise
  // do que está selecionado, fazendo a atualização dos demais valores
  installationsTable
    .on('processing.dt', function (element, settings, processing) {
      module.debug('Processing', processing ? 'block' : 'none');
      if (!processing) {
        // Concluímos o processamento dos dados, então precisamos
        // analisar as seleções de itens de contrato
        if (contents.size == 0) {
          var
            // Obtemos as configurações do conteúdo
            info = installationsTable.page.info()
          ;
          selectedByPage = [];

          // Criamos páginas para armazenar os itens selecionados
          module.debug('info', info);
          module.debug('Criando as páginas nos itens selecionados...');
          for (var i = 0; i < info.pages; i++) {
            selectedByPage
              .push([])
            ;
          }
          module.info('Itens selecionados por página');
          module.table(selectedByPage);
        }
      }
    })
    .on('select', function (element, dt, type, indexes) {
      if ( type === 'row' ) {
        var
          info = dt.page.info(),
          $headerCheck = $("div.dataTables_scrollHead table thead th.select-checkbox")
        ;
        module.debug('info', info);

        // Selecionada uma ou mais linhas, então indexes contém o que
        // foi devidamente selecionado
        indexes.forEach(function(row) {
          var
            installation = dt.rows( row ).data().toArray()[0]
          ;

          if (!contents.has(installation.id)) {
            // Selecionamos o novo item
            module.debug(
              'Selecionado item de contrato ID',
              installation.id,
              'na página',
              info.page
            );

            // Armazenamos as informações do item selecionado na sua
            // respectiva página
            selectedByPage[info.page]
              .push(installation.id)
            ;

            // Adicionamos a instalação selecionada
            addSelectedInstallation(installation);
            module.info('Itens selecionados por página');
            module.table(selectedByPage);

            // Atualizamos os itens selecionados
            selectionRefresh();

            // Atualizamos o botão de seleciona todos na página
            if (selectedByPage[info.page].length == (info.end - info.start)) {
              $headerCheck
                .removeClass("indeterminate")
                .addClass("selected")
              ;
            } else {
              $headerCheck
                .removeClass("selected")
                .addClass("indeterminate")
              ;
            }
          }
        });
      }
    })
    .on('deselect', function (element, dt, type, indexes) {
      if ( type === 'row' ) {
        var
          info = dt.page.info(),
          $headerCheck = $("div.dataTables_scrollHead table thead th.select-checkbox")
        ;
        module.debug('info', info);

        // Desselecionada uma ou mais linhas, então indexes contém o que
        // foi devidamente desselecionado
        indexes.forEach(function(row) {
          var
            installation = dt.rows( row ).data().toArray()[0],
            pos = $.inArray(installation.id, selectedByPage[info.page])
          ;

          // Selecionamos o novo item
          module.debug(
            'Desselecionado item de contrato ID',
            installation.id,
            'na página',
            info.page
          );

          // Removemos as informações do item selecionado
          contents.delete(installation.id);
          selectedByPage[info.page]
            .splice(pos, 1)
          ;

          // Retiramos do formulário
          deleteSelectedInstallation(installation);
          module.info('Itens selecionados por página');
          module.table(selectedByPage);

          // Corrigimos o botão de selecionar tudo
          if (selectedByPage[info.page].length == 0) {
            $headerCheck
              .removeClass("indeterminate")
              .removeClass("selected")
            ;
          } else {
            $headerCheck
              .removeClass("selected")
              .addClass("indeterminate")
            ;
          }

          // Atualizamos os itens selecionados
          selectionRefresh();
        });
      }
    })
  ;
  /**{% endif %}**/

  // Coloca o foco no primeiro campo
  if ($("input[name='customerid']").val() > 0) {
    $("input[name='name']")
      .focus()
    ;
  } else {
    $("input[name='customername']")
      .focus()
    ;
  }
});


// =====================================================[ Options ]=====

// As opções para o componente de autocompletar o nome do pagante
var customerNameCompletionOptions = {
  autoSelectFirst: true,
  searchOnFocus: false,
  ajax: {
    url: "{{ path_for(getEntityDataCompletion.URL) }}",
    type: "{{ getEntityDataCompletion.method }}",
    data: function(params) {
      params.type = 'customer';
      params.detailed = true;
      params.notIncludeAnyRegister = true;
      /**{% if chosenPayment is not same as 'another' %}**/
      params.onlyCustomers = true;
      /**{% else %}**/
      params.onlyCustomers = false;
      /**{% endif %}**/
    }
  },
  onFormatResult: function(searchTerm, suggestion) {
    if (suggestion.juridicalperson) {
      return ''
        + '<div class="content">'
        +   '<div class="title">' + suggestion.name + '<br>'
        +     (
                (suggestion.tradingname)
                  ? '[<span style="color: #0256C4; font-style: italic;">' + suggestion.tradingname + '</span>]<br>'
                  : ''
              )
        +     (
                (suggestion.items > 1)
                  ? (' (<span style="color: CornflowerBlue; font-style: italic;">' + suggestion.subsidiaryname + '</span>)')
                  : ''
              )
        +   '</div>'
        + '</div>'
      ;
    } else {
      return ''
        + '<div class="content">'
        +   '<div class="title">' + suggestion.name + '<br>'
        +     (
                (suggestion.tradingname)
                  ? '[<span style="color: #0256C4; font-style: italic;">' + suggestion.tradingname + '</span>]<br>'
                  : ''
              )
        +     (
                (suggestion.items > 1)
                  ? ( ''
                      + '<span style="color: CornflowerBlue; font-style: italic;">'
                      +     (
                              (suggestion.headoffice == true)
                                ? 'Titular'
                                : ('Dependente: ' + suggestion.subsidiaryname)
                            )
                      +     '</span>'
                    )
                  : ''
              )
        +   '</div>'
        + '</div>'
      ;
    }
  },
  onSelect: function (element, suggestion) {
    // Armazena o ID do item selecionado
    $("input[name='customerid']")
      .val(suggestion.id)
    ;
    $("input[name='subsidiaryid']")
      .val(suggestion.subsidiaryid)
    ;
    $("input[name='subsidiaryname']")
      .val(suggestion.subsidiaryname)
    ;

    /**{% if chosenPayment == 'unusual' %}**/
    if (suggestion.cooperative) {
      // Habilitamos a escolha do associado, exibindo os respectivos
      // campos para permitir que se decida se a cobrança será feita do
      // associado e não da associação
      $("div.fields.associate.toggle")
        .show()
      ;
    } else {
      // Desabilitamos a escolha do associado
      $("div.fields.associate.toggle")
        .hide()
      ;
    }
    $("div.fields.associate.select")
      .hide()
    ;
    $("input[name='chargeFromAssociate']")
      .closest('.ui.checkbox')
      .checkbox('uncheck')
    ;

    // Limpamos os dados dos associados para permitir a correta seleção
    // do mesmo, se necessário
    $("input[name='associateid']")
      .closest('div.ui.dropdown')
      .dropdown('clear')
    ;
    $("input[name='associateid']")
      .closest('div.ui.dropdown')
      .dropdown('change values', [])
    ;
    $("input[name='associateid']")
      .closest('div.ui.dropdown')
      .dropdown('refresh')
    ;
    $("input[name='associateid']")
      .val(0)
    ;
    /**{% endif %}**/

    /**{% if chosenPayment is not same as 'another' %}**/
    // Limpamos quaisquer seleções anteriores
    formClear();

    // Carregamos as informações de instalações
    installationsTable
      .ajax
      .reload()
    ;
    /**{% endif %}**/

    // Modifica o rótulo do campo unidade/filial/titular
    if (suggestion.juridicalperson) {
      $("label[for='subsidiarytitle']")
        .text("Unidade/filial")
      ;
    } else {
      $("label[for='subsidiarytitle']")
        .text("Titular ou dependente")
      ;
    }
  },
  onInvalidateSelection: function () {
    // Não conseguiu localizar um cliente com base nos valores
    // informados, então limpa os dados atuais
    $("input[name='customerid']")
      .val(0)
    ;
    $("input[name='subsidiaryid']")
      .val(0)
    ;
    $("input[name='subsidiaryname']")
      .val("")
    ;
  }
};

// As opções para o componente de autocompletar a unidade/filial do
// cliente
var subsidiaryNameCompletionOptions = {
  autoSelectFirst: true,
  searchOnFocus: false,
  ajax: {
    url: "{{ path_for(getEntityDataCompletion.URL) }}",
    type: "{{ getEntityDataCompletion.method }}",
    data: function(params) {
      params.type = 'subsidiary';
      params.onlyCustomers = true;
      params.includeBlocked = 'true';
      params.entityID = $("input[name='customerid']").val();
    }
  },
  onFormatResult: function(searchTerm, suggestion) {
    return ''
      + '<div class="content">'
      +   '<div class="title">'
      +     suggestion.name + '<br>'
      +     '<span style="color: CornflowerBlue; font-style: italic;">'
      +       (suggestion.nationalregister.length > 14?'CNPJ: ':'CPF: ') + suggestion.nationalregister
      +     '</span><br>'
      +     '<span style="color: CornflowerBlue; font-style: italic;">'
      +       suggestion.city + '/' + suggestion.state
      +     '</span>'
      +   '</div>'
      + '</div>'
    ;
  },
  onSelect: function (element, suggestion) {
    // Armazena o ID do item selecionado
    $("input[name='subsidiaryid']")
      .val(suggestion.id)
    ;
  },
  onInvalidateSelection: function () {
    // Não conseguiu localizar uma unidade/filial com base nos
    // valores informados, então limpa os dados atuais
    $("input[name='subsidiaryid']")
      .val(0)
    ;
  }
};

// As opções para o componente de seleção do associado
var associateOptions = {
  apiSettings: {
    cache: true,
    url: "{{ path_for(getEntityDataCompletion.URL) }}",
    method: "{{ getEntityDataCompletion.method }}",
    data: {
      'type': 'associate',
      'searchTerm': '',
      'entityID': 0
    },
    beforeSend: function(settings) {
      var
        $customerID = $("input[name='customerid']").val(),
        $customerName = $('input[name="customername"]').val()
      ;

      // Cancela se não temos um ID de cliente
      if (!$customerID) {
        return false;
      }
      settings.data.entityID   = $customerID;
      settings.data.searchTerm = $customerName;

      return settings;
    }
  },
  message: {
    noResults : 'Nenhum associado disponível.'
  },
  filterRemoteData: true,
  saveRemoteData: false,
  onChange: function(value, text, $choice) {
    // Atualizamos as informações de veículos deste associado
    installationsTable
      .ajax
      .reload()
    ;
  }
};

// As opções para o componente de exibição de calendário no campo de
// entrada de data
var calendarOptions = {
  type: 'date',
  formatInput: true,
  monthFirst: false,
  today: true,
  text: {
    days: ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'],
    months: [
      'Janeiro', 'Fevereiro', 'Março', 'Abril',
      'Maio', 'Junho', 'Julho', 'Agosto',
      'Setembro', 'Outubro', 'Novembro', 'Dezembro'
    ],
    monthsShort: [
      'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun',
      'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'
    ],
    today: 'Hoje',
    now: 'Agora',
    am: 'AM',
    pm: 'PM'
  },
  formatter: {
    date: function (date, settings) {
      if (!date) {
        return '';
      }

      var
        day   = date.getDate().toString().padLeftWithZeros(2),
        month = (date.getMonth()+1).toString().padLeftWithZeros(2),
        year  = date.getFullYear()
      ;

      return (settings.type === 'year')
        ? year
        : (
            (settings.type === 'month')
              ? month + '/' + year
              : ( settings.monthFirst
                  ? month + '/' + day
                  : day + '/' + month
                ) + '/' + year
          )
      ;
    }
  },
  onChange: function(event) {
    /**{% if chosenPayment in ['carnet', 'prepayment'] %}**/
    if (currentStartDate !== $('input[name="startdate"]').val()) {
      currentStartDate = $('input[name="startdate"]').val();

      // Força o carregamento da tabela
      tableReload();
    }
    /**{% endif %}**/
  }
};

/**{% if chosenPayment == 'unusual' %}**/
// As opções para os checkbox
var checkboxOptions = {
  // Dispara ao iniciar para definir o valor, se necessário
  onChange   : function() {
    var
      checkbox = $(this).prop('name'),
      // Invertemos o valor atual
      checked = !($(this).attr('checked') !== undefined && $(this).attr('checked')),
      boolValue = (checked
        ? 'true'
        : 'false'),
      toggleField = checkbox.replace('_','')
    ;

    // Invertemos o checkbox fake
    $(this)
      .attr('checked', checked)
    ;

    // Alteramos o valor do verdadeiro input
    $("input[name='" + toggleField + "']")
      .val(boolValue)
    ;
  }
};
/**{% endif %}**/

// ====================================================[ Handlers ]=====

/**{% if chosenPayment == 'unusual' %}**/
// Trata a alteração do campo de seleção de se a cobrança deve ser feita
// diratamente do associado ou da associação
function chargeFromAssociateHandler() {
  toggleSelectAssociate($(this).is(":checked"));
}

// Lida com a exibição ou não da seleção do associado
function toggleSelectAssociate(selectAssociate) {
  if (selectAssociate) {
    // A cobrança será feita do associado, então devemos selecioná-lo

    // Exibe o campo de seleção do associado
    $("div.fields.associate.select")
      .show()
    ;
  } else {
    // A cobrança será feita diretamente da associação

    // Esconde o campo de seleção do associado
    $("div.fields.associate.select")
      .hide()
    ;
  }

  setTimeout(function() {
    // Carregamos as informações de instalações para atualizar o seu
    // conteúdo
    installationsTable
      .ajax
      .reload()
    ;
  }, 500);
}

// Lida com a seleção do associado
function associateSelectionHandler(value, text, $selectedItem) {
}
/**{% endif %}**/

/**
 * Faz o tratamento de campos que informam um valor monetário para
 * permitir corrigir valores inválidos.
 *
 * @return void
 */
function monetaryHandler()
{
  var
    monetary = $(this).val()
  ;

  if (monetary.trim().isEmpty()) {
    $(this)
      .val('0,00')
    ;
  }
}

/**{% if chosenPayment is not same as 'carnet' %}**/
function paymentConditionHandler(value, text, $selectedItem) {
  // Atualizamos as informações complementares do meio de
  // pagamento e definição de configuração
  $('input[name="paymentmethodid"]')
    .val($selectedItem[0].dataset['paymentmethodid'])
  ;
  $('input[name="definedmethodid"]')
    .val($selectedItem[0].dataset['definedmethodid'])
  ;

  // Analisamos se precisamos modificar a data de vencimento em
  // função dos prazos
  var
    timeUnit = $selectedItem[0].dataset['timeunit'],
    paymentInterval = toArray($selectedItem[0].dataset['paymentinterval']),
    minimumPaymentDate = new Date()
  ;

  // Zeramos a hora para efeito de análise
  minimumPaymentDate.setHours(0,0,0,0);
  module.debug('Data mínima para pagamento inicial é de ', formatDate(minimumPaymentDate));

  // Analisamos a questão do intervalo de pagamento
  if (paymentInterval.length > 1) {
    // Estamos lidando com a possibilidade de parcelamento, então
    // acrescentamos os campos para permitir a seleção da forma de
    // parcelamento
    if ($('div.subdivision').hasClass('hidden')) {
      $('div.subdivision')
        .removeClass('hidden')
      ;
    }
    $('#numberofinstallments')
      .empty()
    ;

    // Montamos as opções do combobox
    var
      singular = 0,
      plural = 1,
      installmentDescription = null,
      installmentAmount = null,
      complement = (timeUnit === 'DAY')
        ? [' dia', ' dias']
        : [' vez', ' vezes'],
      term = 'Parcelado em ',
      flexion
    ;
    paymentInterval.forEach(function(element, index) {
      flexion = (element > 1)
        ? plural
        : singular
      ;
      if (index > 0) {
        if (timeUnit === 'DAY') {
          installmentAmount += ' / ' + element;
        } else {
          installmentAmount = element;
        }
        installmentDescription = term
          + installmentAmount + complement[flexion]
        ;
      } else {
        installmentDescription = 'Parcela única';
        if (timeUnit === 'DAY') {
          installmentAmount = element;
          installmentDescription += ' em ' + installmentAmount
            + complement[flexion]
          ;
        } else {
          if (element == 0) {
            term = 'Entrada mais ';
          }
        }
      }

      // Acrescentamos a opção
      $('#numberofinstallments')
        .append('<option value="' + (index + 1) + '">'
          + installmentDescription
          + '</option>'
        )
      ;
    });

    // Analisamos a data mínima para o primeiro pagamento
    module.debug('Ajustando a data de vencimento em função da seleção');
    if (timeUnit === 'DAY') {
      // Estamos lindando com um valor de dias
      module.debug('Acrescentando', paymentInterval[0], 'dia(s)');
      minimumPaymentDate.setDate(
        minimumPaymentDate.getDate() + paymentInterval[0]
      );
    } else {
      // Estamos lidando com um valor de meses
      module.debug('Acrescentando', paymentInterval[0], 'mês(es)');
      minimumPaymentDate.setMonth(
        minimumPaymentDate.getMonth() + paymentInterval[0]
      );
    }
    if ($selectedItem[0].dataset['paymentmethodid'] == 5) {
      if (paymentInterval[0] == 0) {
        // Acrescentamos ao menos 2 dias para pagamento de
        // boletos
        module.debug('Acrescentando 2 dia(s) por ser boleto');
        minimumPaymentDate.setDate(
          minimumPaymentDate.getDate() + 2
        );
      }
    }

    $('#numberofinstallments')
      .dropdown({
        onChange: function(value) {
          // Precisamos atualizar o valor da parcela
          module.debug('Definindo parcelado em', value);
          valueToPayDivider = value;
          installmentRefresh(value);
        }
      })
    ;

    // Selecionamos o primeiro valor
    module.debug('Ajustando o número de parcelas para 1');
    $('#numberofinstallments')
      .dropdown('set selected', '1')
    ;
    valueToPayDivider = 1;
    installmentRefresh(1);
  } else {
    // Desabilitamos a informação de parcelamento
    if (!$('div.subdivision').hasClass('hidden')) {
      $('div.subdivision')
        .addClass('hidden')
      ;
    }

    // Limpamos as opções de parcelamento
    installmentClear();

    if (timeUnit === 'DAY') {
      // Estamos lindando com um valor de dias
      module.debug('Acrescentando', paymentInterval[0], 'dia(s)');
      minimumPaymentDate.setDate(
        minimumPaymentDate.getDate() + paymentInterval[0]
      );
    } else {
      // Estamos lidando com um valor de meses
      module.debug('Acrescentando', paymentInterval[0], 'mês(es)');
      minimumPaymentDate.setMonth(
        minimumPaymentDate.getMonth() + paymentInterval[0]
      );
    }
    if ($selectedItem[0].dataset['paymentmethodid'] == 5) {
      if (paymentInterval[0] == 0) {
        // Acrescentamos ao menos 2 dias para pagamento de
        // boletos
        module.debug('Acrescentando 2 dia(s) por ser boleto');
        minimumPaymentDate.setDate(
          minimumPaymentDate.getDate() + 2
        );
      }
    }
  }

  module.debug('Ajustando data de vencimento para', formatDate(minimumPaymentDate));
  // Ajustamos a data para uma que permita o processo deste
  // meio de pagamento
  $('input[name="duedate"]')
    .val(formatDate(minimumPaymentDate))
  ;
}
/**{% endif %}**/

/**{% if chosenPayment is not same as 'another' %}**/
/**
 * Limpa os controles do formulário, restaurando para a situação inicial
 *
 * @return void
 */
function formClear()
{
  // Indicamos que não temos nenhum item selecionado
  selected = [];

  // Limpamos a tabela e retornamos a condição inicial
  tableClear();

  /**{% if chosenPayment == 'carnet' %}**/
  // Limpa a data do primeiro vencimento
  $("input[name='duedate']")
    .val('')
  ;
  /**{% endif %}**/
}
/**{% endif %}**/

/**{% if chosenPayment is not same as 'another' %}**/
// ===========================[ Manipulação dos itens de contrato ]=====

/**
 * Adiciona um novo item de contrato ao valores que compõe o valor a ser
 * cobrado.
 *
 * @param object installation
 *   As informações do item de contrato
 *
 * @return void
 */
function addSelectedInstallation(installation)
{
  contents.set(installation.id, installation);

  if (contents.size == 1) {
    module.debug('Este é o primeiro item de contrato');
    /**{% if chosenPayment in ['unusual', 'closing'] %}**/
    if (unboundContents == 0) {
      // Limpamos o conteúdo anterior
      $tbody
        .empty()
      ;
    }
    /**{% else %}**/
    // Limpamos o conteúdo anterior
    $tbody
      .empty()
    ;
    /**{% endif %}**/
    /**{% if chosenPayment == 'carnet' %}**/
    // Atualiza a data do início do período cobrado
    $("input[name='startdate']")
      .val(getNextDay(installation.lastdayofbillingperiod))
    ;
    // Atualiza a data do primeiro vencimento
    $("input[name='duedate']")
      .val(installation.firstduedate)
    ;
    /**{% endif %}**/

    /**{% if chosenPayment in ['carnet', 'prepayment'] %}**/
    // Atualiza o valor total quantidade de parcelas
    if ($("input[name='numberofparcels']").val() == 0) {
      $("input[name='numberofparcels']")
        .val(installation.numberofparcels)
      ;
    }
    /**{% endif %}**/
  }

  module.error('Adicionando item de contrato', installation.id);
  // Obtém os dados a serem cobrados deste item de contrato e
  // acrescenta o seu conteúdo na tabela de resumo
  getBillingData(
    installation.contractid,
    installation.id,
    installation.installationnumber,
    installation.plate
  );

  /**{% if chosenPayment == 'prepayment' %}**/

  // Verificamos se a condição de pagamento está indicada
  if ($('input[name="paymentconditionid"]').val() == 0) {
    // Selecionamos o mesmo meio de pagamento do contrato
    var
      $dropdown = $('input[name="paymentconditionid"]')
        .closest('div.ui.dropdown')
    ;

    $dropdown
      .dropdown('set selected', installation.paymentconditionid)
    ;
  }
  /**{% endif %}**/
}

/**
 * Remove um item de contrato ao valores que compõe o valor a ser
 * cobrado.
 *
 * @param object installation
 *   As informações do item de contrato
 *
 * @return void
 */
function deleteSelectedInstallation(installation)
{
  /**{% if chosenPayment in ['unusual', 'closing'] %}**/
  // Removemos valores relacionados com este item de contrato
  module.debug('Removemos valores ligados à instalação', installation.id);
  deleteBilling(
    installation.contractid,
    installation.id,
    installation.installationnumber,
    installation.plate
  );

  // Analisamos se não temos mais nada selecionado
  if (contents.size == 0) {
    if (unboundContents == 0) {
      // Limpamos a tabela
      module.debug('Limpando a tabela por não conter mais itens');
      tableClear();
    }
  }
  /**{% else %}**/
  if (contents.size == 0) {
    // Limpamos a tabela
    module.debug('Limpando a tabela por não conter mais itens');
    tableClear();
  } else {
    // Removemos valores relacionados com este item de contrato
    module.debug('Removemos valores ligados à instalação', installation.id);
    deleteBilling(
      installation.contractid,
      installation.id,
      installation.installationnumber,
      installation.plate
    );
  }
  /**{% endif %}**/

  // Atualizamos a tabela e calculamos o valor total
  module.debug('Disparando atualização tabela');
  tableRefresh();
}

/**
 * Obtém os dados a serem cobrados, acrescenta-os na tabela de resumo.
 *
 * @param int contractID
 *   O ID do contrato
 * @param int installationID
 *   O ID do item de contrato
 * @param string installationNumber
 *   O número de identificação do item de contrato
 * @param int plate
 *   A placa do veículo
 *
 * @return void
 */
function getBillingData(
  contractID,
  installationID,
  installationNumber,
  plate
)
{
  /**{% if chosenPayment in ['carnet', 'prepayment'] %}**/
  var
    startDate = $('input[name="startdate"]').val()
  ;
  if (!dateIsValid(startDate)) {
    return;
  }
  /**{% endif %}**/

  // Consulta o web service para obter os dados a serem cobrados para
  // esta instalação
  var
    /**{% if chosenPayment in ['carnet', 'prepayment'] %}**/
    toBePerformedService = 'true',
    startDate = startDate,
    numberOfParcels = $("input[name='numberofparcels']").val(),
    /**{% else %}**/
      /**{% if chosenPayment == 'closing' %}**/
      withAppuredValues = 'true',
      withTariffs = 'true',
      /**{% else %}**/
      withAppuredValues = 'false',
      withTariffs = 'false',
      /**{% endif %}**/
    onlyTariffs = 'false',
    /**{% endif %}**/
    url = "{{ path_for(getInstallationBillingsData.URL) }}"
  ;
  requestJSONData(url, {
    contractID: contractID,
    installationID: installationID,
    /**{% if chosenPayment in ['carnet', 'prepayment'] %}**/
    toBePerformedService: toBePerformedService,
    startDate: startDate,
    numberOfParcels: numberOfParcels,
    /**{% else %}**/
    withAppuredValues: withAppuredValues,
    withTariffs: withTariffs,
    onlyTariffs: onlyTariffs,
    /**{% endif %}**/
    request: true
  },
  function (billingsData)
  {
    module.debug('Recuperado '+ billingsData.length + ' lançamentos');
    // Percorre os valores, adicionando-os na tabela
    billingsData.forEach(function(billingData) {
      addBilling(
        contractID,
        installationID,
        installationNumber,
        plate,
        billingData
      );
    });

    // Atualiza a sequência dos campos
    module.debug('Disparando atualização tabela');
    tableRefresh();
  },
  function () {
    module.debug('Nenhum lançamento recuperado');
    // Nenhum lançamento retornado para esta instalação, então
    // adiciona apenas uma linha indicando que não têm valores
    addBilling(
      contractID,
      installationID,
      installationNumber,
      plate,
      null
    );

    // Atualiza a sequência dos campos
    module.debug('Disparando atualização tabela');
    tableRefresh();
  });
}

/**
 * Adiciona um valor a ser cobrado.
 *
 * @param int contractID
 *   O ID do contrato ao qual está vinculado
 * @param int installationID
 *   O ID do item de contrato ao qual está vinculado
 * @param string installationNumber
 *   O número de registro do item de contrato ao qual está vinculado
 * @param string plate
 *   A placa do veículo
 * @param object billingData
 *   Os dados de cobrança
 *
 * @return void
 */
function addBilling(
  contractID,
  installationID,
  installationNumber,
  plate,
  billingData)
{
  if (billingData == null) {
    /**{% if chosenPayment == 'carnet' %}**/
    if (totalizer.length == 0) {
      // Mantemos a tabela em branco
      $tbody
        .empty()
        .append(''
          + '<tr>'
          +   '<td class="collapsing right aligned">1.</td>'
          +   '<td colspan="3" class="center aligned">Nenhum valor</td>'
          + '</tr>'
        )
      ;
    }
    /**{% endif %}**/
    /**{% if chosenPayment == 'prepayment' %}**/
    // PRÉ-PAGAMENTO
    // -------------
    // Os valores são somados por placa e a soma de todos os valores é o
    // valor final a ser cobrado
    var
      start = toDate($('input[name="startdate"]').val()),
      numberOfParcels = parseInt($('input[name="numberofparcels"]').val()),
      end = toDate($('input[name="startdate"]').val()),
      labelPlate = (plate)
        ? plate
        : 'Sem rastreador'
    ;
    end.setMonth(
      end.getMonth() + numberOfParcels
    );
    module.debug(start);
    module.debug(end);

    // Inicializamos os valores do totalizador
    totalizer[installationID] = {
      id: installationID,
      start: start,
      end: end,
      total: 0.00
    };
    module.debug('Adicionando item de contrato', installationNumber, plate);
    $tbody
      .append(''
        + '<tr data-installation="' + installationID + '">'
        +   '<td class="collapsing right aligned">0.</td>'
        +   '<td class="left aligned">'
        +     '<span class="headline">' + labelPlate + '</span><br>'
        +     '<span class="subheadline">'
        +       '<i class="file signature grey icon"></i>'
        +       installationNumber
        +     '</span>'
        +   '</td>'
        +   '<td class="left aligned">'
        +     'Valores do período de '
        +     formatDate(totalizer[installationID].start)
        +     ' à '
        +     formatDate(totalizer[installationID].end)
        +   '</td>'
        +   '<td class="right aligned">'
        +     '<span class="symbol">R$</span>0,00'
        +   '</td>'
        + '</tr>'
      )
    ;
    /**{% endif %}**/
    /**{% if chosenPayment in ['unusual', 'closing'] %}**/
    // Adicionamos uma linha para agrupamento
    addGroupingRow(
      contractID,
      installationID,
      installationNumber,
      plate
    );

    // Adiciona uma linha em branco para informar que esta placa não
    // contém valores a serem cobrados
    module.debug('Adicionando linha indicando item sem valores');
    $tbody
      .find('[data-installation="' + installationID + '"]')
      .last()
      .after(''
        + '<tr data-installation="' + installationID + '" data-empty="empty">'
        +   '<td class="collapsing right aligned">'
        +     '<div class="ui fitted checkbox">'
        +       '<input type="checkbox" disabled="disabled"><label></label>'
        +     '</div>'
        +   '</td>'
        +   '<td colspan="2" class="center aligned">'
        +     'Nenhum valor a ser cobrado'
        +   '</td>'
        +   '<td class="collapsing"></td>'
        + '</tr>'
      )
    ;
    /**{% endif %}**/
  } else {
    // Adiciona o valor a ser cobrado
    /**{% if chosenPayment == 'carnet' %}**/
    // CARNÊ DE PAGAMENTOS
    // -------------------
    // Os valores são distribuídos de acordo com o mês de referência,
    // então garante que o mês de referência já foi lançado
    var
      $reference = $tbody
        .find('[data-reference="' + billingData.referencemonthyear + '"]')
    ;

    if ($reference.length === 0) {
      // Inicializamos os valores do totalizador
      totalizer[billingData.referencemonthyear] = [];
      // Adicionamos o mês de referência
      referenceMonths
        .push(billingData.referencemonthyear)
      ;
      referenceMonths
        .sort(sortReferences)
      ;
      module.debug(
        'Adicionando mês de referência',
        billingData.referencemonthyear
      );
      // Determinamos a posição em que o mês deve ser inserido, de forma
      // que os valores permaneçam em ordem cronológica
      var
        pos = referenceMonths.indexOf(billingData.referencemonthyear),
        $newGroup = ''
          + '<tr data-reference="' + billingData.referencemonthyear + '" class="grouped">'
          +   '<td class="collapsing right aligned"></td>'
          +   '<td colspan="3" class="left aligned">'
          +     'Parcela referente ao mês <span class="bold">' + billingData.referencemonthyear + '</span><br>'
          +   '</td>'
          + '</tr>',
        $newTotal = ''
          + '<tr data-reference="' + billingData.referencemonthyear + '" class="total">'
          +   '<td class="collapsing right aligned">⇢</td>'
          +   '<td colspan="2" class="right aligned">'
          +     'Total da parcela referente ao mês ' + billingData.referencemonthyear
          +   '</td>'
          +   '<td class="right aligned">'
          +     '<span class="symbol">R$</span> 0,00'
          +   '</td>'
          + '</tr>'
      ;
      if (pos == (referenceMonths.length - 1)) {
        // Adicionamos no final
        $tbody
          .append($newGroup)
        ;
        $tbody
          .append($newTotal)
        ;
      } else {
        if (pos == 0) {
          // Adicionamos no começo
          $tbody
            .find('tr:first')
            .before($newGroup)
          ;
        } else {
          // Precisamos localizar a posição para adicionar
          var
            lastReference = referenceMonths[ pos - 1 ],
            $lastRow = $tbody
              .find('[data-reference="' + lastReference + '"]')
              .last()
          ;
          // Adicionamos após o item anterior
          $lastRow
            .after($newGroup)
          ;
        }
        $tbody
          .find('[data-reference="' + billingData.referencemonthyear + '"]')
          .last()
          .after($newTotal)
        ;
      }
      // Adicionamos o mês de referência
      $tbody
        .append(
        )
      ;
    }

    // Verifica se o item já foi adicionado
    var
      $row = $tbody
        .find('[data-reference="' + billingData.referencemonthyear + '"][data-installation="' + installationID + '"]')
    ;

    if ($row.length === 0) {
      module.debug('Adicionando item de contrato', installationNumber, plate);
      var
        $base = $tbody
          .find('[data-reference="' + billingData.referencemonthyear + '"]')
          .last()
      ;
      totalizer[billingData.referencemonthyear][installationID] = parseFloat(billingData.value);
      $base
        .before(''
          + '<tr data-reference="' + billingData.referencemonthyear + '" data-installation="' + installationID + '">'
          +   '<td class="collapsing right aligned">✔</td>'
          +   '<td class="left aligned">'
          +     '<span class="headline">' + plate + '</span><br>'
          +     '<span class="subheadline">'
          +       '<i class="file signature grey icon"></i>'
          +       installationNumber
          +     '</span>'
          +   '</td>'
          +   '<td class="left aligned">'
          +     billingData.name
          +   '</td>'
          +   '<td class="right aligned">'
          +     '<span class="symbol">R$</span>'
          +     toMoney(billingData.value)
          +   '</td>'
          + '</tr>'
        )
      ;
    } else {
      module.debug('Atualizando item de contrato', installationNumber, plate);
      // Apenas atualiza a linha informada
      totalizer[billingData.referencemonthyear][installationID] += parseFloat(billingData.value);
      $row
        .find('td:eq(2)')
        .text(
          'Valores do período de '
          + formatDate(toDate(billingData.startdateofperiod))
          + ' à '
          + formatDate(toDate(billingData.enddateofperiod))
        )
      ;
      $row
        .find('td:eq(3)')
        .html(
          '<span class="symbol">R$</span>'
          + toMoney(
              totalizer[billingData.referencemonthyear][installationID]
          )
        )
      ;
    }

    // Atualiza o total do mês
    var
      $total = $tbody
        .find('[data-reference="' + billingData.referencemonthyear + '"]')
        .last(),
      sum = 0
    ;
    totalizer[billingData.referencemonthyear].forEach(function(value) {
      sum += value;
    });
    $total
      .find('td:eq(2)')
      .html(
        '<span class="symbol">R$</span>'
        + toMoney(
            sum
        )
      )
    ;
    /**{% endif %}**/
    /**{% if chosenPayment == 'prepayment' %}**/
    // PRÉ-PAGAMENTO
    // -------------
    // Os valores são somados por placa e a soma de todos os valores é o
    // valor final a ser cobrado
    var
      $row = $tbody
        .find('[data-installation="' + installationID + '"]'),
      labelPlate = (plate)
        ? plate
        : 'Sem rastreador'
    ;

    if ($row.length === 0) {
      // Inicializamos os valores do totalizador
      totalizer[installationID] = {
        id: installationID,
        start: toDate(billingData.startdateofperiod),
        end: toDate(billingData.enddateofperiod),
        total: parseFloat(billingData.value)
      };

      // Adicionamos uma nova linha para conter os dados deste item de
      // contrato
      module.debug('Adicionando item de contrato', installationNumber, plate);
      $tbody
        .append(''
          + '<tr data-installation="' + installationID + '">'
          +   '<td class="collapsing right aligned">0.</td>'
          +   '<td class="left aligned">'
          +     '<span class="headline">' + labelPlate + '</span><br>'
          +     '<span class="subheadline">'
          +       '<i class="file signature grey icon"></i>'
          +       installationNumber
          +     '</span>'
          +   '</td>'
          +   '<td class="left aligned">'
          +     billingData.name
          +   '</td>'
          +   '<td class="right aligned">'
          +     '<span class="symbol">R$</span>' + toMoney(billingData.value)
          +   '</td>'
          + '</tr>'
        )
      ;
    } else {
      // Incrementamos os valores do totalizador
      if (totalizer[installationID].start > toDate(billingData.startdateofperiod)) {
        totalizer[installationID].start = toDate(billingData.startdateofperiod);
      }
      if (totalizer[installationID].end < toDate(billingData.enddateofperiod)) {
        totalizer[installationID].end = toDate(billingData.enddateofperiod);
      }
      totalizer[installationID].total += parseFloat(billingData.value);

      // Atualizamos a linha com os valores
      $row
        .find('td:eq(2)')
        .text(
          'Valores do período de '
          + formatDate(totalizer[installationID].start)
          + ' à '
          + formatDate(totalizer[installationID].end)
        )
      ;
      $row
        .find('td:eq(3)')
        .html(
          '<span class="symbol">R$</span>'
          + toMoney(totalizer[installationID].total)
        )
      ;
    }
    /**{% endif %}**/
    /**{% if chosenPayment in ['unusual', 'closing'] %}**/
    // FECHAMENTO OU COBRANÇA ADICIONAL
    // --------------------------------
    // Os valores são agrupados por placa, com cada item cobrado sendo
    // discriminado separadamente e a soma de todos os valores é o valor
    // final a ser cobrado
    if (billingData.rateperequipment) {
      module.debug('Cobrança por equipamento');
      var
        marked = lessThanOrEqualToCurrentMonth(billingData.billingdate),
        $base = $tbody
          .find('[data-installation="' + installationID + '"]')
          .last()
      ;
      if ($base.length === 0) {
        // Adicionamos uma linha para agrupamento
        addGroupingRow(
          contractID,
          installationID,
          installationNumber,
          plate
        );
      } else {
        if (typeof $base.data("empty") !== 'undefined') {
          // Precisamos remover a linha atual primeiramente
          $base
            .remove()
          ;
        }
      }

      addRow(
        installationID,
        contractID,
        billingData.id,
        billingData.name,
        billingData.billingvalue,
        marked,
        (billingData.id > 0)?true:false,
        billingData.isascertained
      );
    } else {
      module.debug('Cobrança adicional');
      if (billingData.contractcharge) {
        module.debug('Cobrança contractual');
        // Estamos lidando com uma cobrança contratual que não está
        // relacionada a nenhuma instalação, então ela é exibida à parte
        if (contractualCharges[billingData.id]) {
          // Verifica se esta instalação já está informada
          if (!contractualCharges[billingData.id].includes(installationID)) {
            // Adicionamos esta instalação neste item contractual
            module.debug('Adicionando instalação', installationID, 'ao item contractual', billingData.id);
            contractualCharges[billingData.id]
              .push(installationID)
            ;
          }
        } else {
          // Adicionamos este item contractual
          contractualCharges[billingData.id] = [
            installationID
          ];
          module.debug('Adicionando instalação', installationID, 'ao item contractual', billingData.id);

          // Adicionamos uma nova linha no conjunto de valores
          // adicionais
          unboundContents++;
          module.debug('Incrementando itens adicionais', unboundContents);

          if (unboundContents == 1) {
            // Adicionamos um grupo para outros itens cobrados
            module.debug('Adicionando outros itens cobrados');
            $tbody
              .append(''
                + '<tr data-installation="0" class="grouped">'
                +   '<td class="collapsing right aligned"></td>'
                +   '<td colspan="3" class="left aligned">'
                +     '<span>'
                +       '<i class="file code grey icon"></i>'
                +     '</span>'
                +     'Outros itens'
                +   '</td>'
                + '</tr>'
              )
            ;
          }

          addRow(
            0,
            0,
            billingData.id,
            billingData.name,
            billingData.billingvalue,
            false,
            true,
            false
          );
        }
      } else {
        // Estamos lidando com um lançamento independente dos demais
        // itens de contrato. Adicionamos uma nova linha no conjunto de
        // valores adicionais
        unboundContents++;
        module.debug('Incrementando itens adicionais', unboundContents);

        if (unboundContents == 1) {
          // Adicionamos um grupo para outros itens cobrados
          module.debug('Adicionando outros itens cobrados');
          $tbody
            .append(''
              + '<tr data-installation="0" class="grouped">'
              +   '<td class="collapsing right aligned"></td>'
              +   '<td colspan="3" class="left aligned">'
              +     '<span>'
              +       '<i class="file code grey icon"></i>'
              +     '</span>'
              +     'Outros itens'
              +   '</td>'
              + '</tr>'
            )
          ;
        }

        addRow(
          0,
          0,
          0,
          billingData.name,
          billingData.billingvalue,
          true,
          false,
          false
        );
      }
    }
    /**{% endif %}**/
  }
}

/**
 * Remove um ou mais valores a serem cobrados.
 *
 * @param int contractID
 *   O ID do contrato ao qual está vinculado
 * @param int installationID
 *   O ID do item de contrato ao qual está vinculado
 * @param string installationNumber
 *   O número de registro do item de contrato ao qual está vinculado
 * @param string plate
 *   A placa do veículo
 * @param object billingData
 *   Os dados de cobrança
 *
 * @return void
 */
function deleteBilling(
  contractID,
  installationID,
  installationNumber,
  plate)
{
  $tbody
    .find('[data-installation="' + installationID + '"]')
    .remove()
  ;

  /**{% if chosenPayment == 'carnet' %}**/
  // Atualizamos os valores totais de cada mensalidade
  for (var ref in totalizer) {
    var
      sum = 0.00,
      amount = 0
    ;
    
    // Retiramos o item de contrato do totalizador
    totalizer[ref]
      .splice(installationID, 1)
    ;

    // Calcula o valor da parcela
    totalizer[ref].forEach(function(value) {
      sum += value;
      amount++;
    });

    // Atualiza os valores da parcela
    if (amount == 0) {
      // Removemos a parcela inteira
      module.debug('Removendo parcela referência ' + ref);
      $tbody
        .find('[data-reference="' + ref + '"]')
        .remove()
      ;
      referenceMonths
        .splice(referenceMonths.indexOf(ref), 1)
      ;
      referenceMonths
        .sort(sortReferences)
      ;
    } else {
      module.debug('Atualizando total parcela referência ' + ref);
      var
        $total = $tbody
          .find('[data-reference="' + ref + '"]')
          .last()
      ;

      $total
        .find('td:eq(2)')
        .html(
          '<span class="symbol">R$</span>'
          + toMoney(sum)
        )
      ;
    }
  }
  /**{% endif %}**/
  /**{% if chosenPayment == 'prepayment' %}**/
  // Retiramos o item de contrato do totalizador
  for (var key in totalizer) {
    if (totalizer[key].id == installationID) {
      totalizer
        .splice(key, 1)
      ;
    }
  }
  /**{% endif %}**/
  /**{% if chosenPayment in ['unusual', 'closing'] %}**/
  // Analisamos itens adicionais presentes em contrato
  contractualCharges.forEach(function(row, key) {
    if (row.includes(installationID)) {
      contractualCharges[key]
        .splice(contractualCharges[key].indexOf(installationID), 1)
      ;
      module.debug('Removendo instalação', installationID, 'do item contractual', key);

      if (contractualCharges[key].length == 0) {
        // Removemos este item contractual
        contractualCharges.splice(key, 1);
        module.debug('O item contractual', key, 'está vazio');
        $tbody
          .find('[data-installation="0"][data-id="' + key + '"]')
          .remove()
        ;
        unboundContents--;
        module.debug('Decrementando itens adicionais', unboundContents);

        // Analisamos se ainda temos itens adicionais
        if (unboundContents == 0) {
          module.debug('Removendo grupo de itens adicionais');
          $rows = $tbody
            .find('[data-installation="0"]')
            .remove()
          ;
        }
      }
    }
  });
  /**{% endif %}**/

  // Atualiza a sequência dos campos
  delay(tableRefresh, 500);

}

/**
 * Atualiza a informação de itens de contrato selecionados.
 *
 * @return void
 */
function selectionRefresh()
{
  var
    total = 0
  ;

  // Calculamos a quantidade de itens selecionados
  selectedByPage.forEach(function(page) {
    total += page.length;
  });

  if (total > 0) {
    if (total > 1) {
      $("div.toolbar")
        .html('<b>Selecionados ' + total + ' itens de contrato</b>')
      ;
    } else {
      $("div.toolbar")
        .html('<b>Selecionado ' + total + ' item de contrato</b>')
      ;
    }
  } else {
    // Nada selecionado
    $("div.toolbar")
      .html('<b></b>')
    ;
  }
}
/**{% endif %}**/

/**{% if chosenPayment in ['unusual', 'closing', 'another'] %}**/
/**
 * Exibe um diálogo para adição de um novo lançamento.
 *
 * @return void
 */
function addNewBilling()
{
  /**{% if chosenPayment == 'another' %}**/
  var
    size = $tbody.find('tr').length
  ;
  $tbody
    .append(''
      + '<tr>'
      +   '<td class="collapsing right aligned">'
      +     '<div class="ui fitted checkbox">'
      +       (size + 1) + '.'
      +     '</div>'
      +   '</td>'
      +   '<td class="left aligned">'
      +     '<input type="text" name="billings[][name]" placeholder="Descreva o valor sendo cobrado" maxlength="30" value="">'
      +   '</td>'
      +   '<td class="right aligned">'
      +     '<input type="text" name="billings[][value]" value="0,00">'
      +   '</td>'
      +   '<td class="collapsing center aligned">'
      +     '<i class="large red close icon"></i>'
      +   '</td>'
      + '</tr>'
    )
  ;
  unboundContents++;

  // Fazemos o mascaramento do campo
  $tbody
    .find('tr:last')
    .find('input[name="billings[][value]"')
    .mask({
      type: 'monetary',
      trim: true,
      allowNegativeValues: false,
      decimalsPlaces: 2,
      maxLength: 15
    })
    .blur(monetaryHandler)
    .change(recalculateTotals)
  ;
  $tbody
    .find('tr:last')
    .find('td:eq(3)')
    .click(removeThis)
  ;

  // Coloca o foco no campo do nome
  $tbody
    .find('tr:last td:eq(1) input')
    .focus()
  ;
  /**{% else %}**/
  // Atualizamos os dados do formulário
  var
    $modal = $('.ui.billing.modal'),
    $menu  = $modal.find('div.menu')
  ;

  // Limpamos quaisquer itens anteriores
  $menu
    .empty()
  ;

  // Adicionamos um para valores independentes
  $menu
    .append(
      $('<div>')
        .addClass('item')
        .attr('data-value', 0)
        .attr('data-contract', 0)
        .text('Independente de instalação')
    )
  ;

  // Adicionamos as instalações
  for (const [key, installation] of contents) {
    var
      label = (installation.plate)
        ? installation.plate
        : 'Sem rastreador'
    ;
    label += ' / <span class="plate">'
      + installation.installationnumber + '</span>'
    ;
    $menu
      .append(
        $('<div>')
          .addClass('item')
          .attr('data-value', installation.id)
          .attr('data-contract', installation.contractid)
          .attr('data-number', installation.installationnumber)
          .attr('data-plate', installation.plate)
          .html(label)
      )
    ;
  }

  $modal
    .find('input[name="installationid"]')
    .closest('div.ui.dropdown')
    .dropdown()
  ;

  $modal
    .find('input[name="name"]')
    .val('')
  ;
  $modal
    .find('input[name="value"]')
    .val('0,00')
  ;
  $modal
    .find('input[name="value"]')
    .mask({
      type: 'monetary',
      trim: true,
      allowNegativeValues: false,
      decimalsPlaces: 2,
      maxLength: 15
    })
    .blur(monetaryHandler)
  ;

  $modal.modal(
    {
      closable  : true,
      autofocus: true,
      restoreFocus: true,
      onApprove : function() {
        // Adicionamos uma linha contendo os novos valores
        var
          installationID = $modal
            .find('input[name="installationid"]')
            .val(),
          contractID = $modal
            .find('input[name="installationid"]')
            .closest('div.ui.dropdown')
            .find("div.item[data-value='" + installationID + "']")
            .data('contract')
          ;
          installationNumber = $modal
            .find('input[name="installationid"]')
            .closest('div.ui.dropdown')
            .find("div.item[data-value='" + installationID + "']")
            .data('number')
          ;
          plate = $modal
            .find('input[name="installationid"]')
            .closest('div.ui.dropdown')
            .find("div.item[data-value='" + installationID + "']")
            .data('plate')
          ;
          name = $modal
            .find('input[name="name"]')
            .val(),
          billingValue = toFloat($modal
            .find('input[name="value"]')
            .val()),
          today = new Date()
        ;

        addBilling(
          contractID,
          installationID,
          installationNumber,
          plate,
          {
            id: 0,
            contractid: contractID,
            billingdate: today.toISOString().split('T')[0],
            name: name,
            billingvalue: billingValue,
            installmentnumber: 0,
            numberofinstallments: 0,
            contractcharge: false,
            rateperequipment: ((installationID > 0)?true:false),
            isascertained: false
          }
        );

        // Atualiza a sequência dos campos
        module.debug('Disparando atualização tabela');
        tableRefresh();
      }
    })
    .modal('show')
  ;
  /**{% endif %}**/
}

/**
 * Adiciona uma linha da tabela
 *
 * @param int installationID
 *   O ID da instalação
 * @param int contractID
 *   O ID do contrato
 * @param int billingID
 *   O ID do lançamento
 * @param string name
 *   O nome do lançamento
 * @param float value
 *   O valor do lançamento
 * @param bool marked
 *   Se deve ser marcado previamente
 * @param bool fixed
 *   Se não pode ser excluído
 * @param bool isAscertained
 *   Se é o valor apurado do período
 */
function addRow(
  installationID,
  contractID,
  billingID,
  name,
  value,
  marked = false,
  fixed = false,
  isAscertained = false
)
{
  var
    attributes = ((marked) ? ' checked="checked"' : ''),
    icon = ((fixed) ? 'grey minus' : 'red close'),
    $base = $tbody
      .find('[data-installation="' + installationID + '"]')
      .last(),
    ascertained = ((isAscertained)?'1':'0')
  ;
  module.debug('Adicionando uma nova linha na tabela para', name);
  $base
    .after(''
      + '<tr data-installation="' + installationID + '" data-contract="' + contractID + '" data-id="' + billingID + '">'
      +   '<td class="collapsing right aligned">'
      +     '<div class="ui fitted checkbox">'
      +       '<input type="checkbox" name="billings[][marked]" ' + attributes +'><label></label>'
      +     '</div>'
      +     '<input type="hidden" name="billings[][installationid]" value="' + installationID + '">'
      +   '</td>'
      +   '<td class="left aligned">'
      +     '<input type="text" name="billings[][name]" value="' + name + '">'
      +     '<input type="hidden" name="billings[][billingid]" value="' + billingID + '">'
      +   '</td>'
      +   '<td class="right aligned">'
      +     '<input type="text" name="billings[][value]" value="' + toMoney(value) + '">'
      +     '<input type="hidden" name="billings[][ascertained]" value="' + ascertained + '">'
      +   '</td>'
      +   '<td class="collapsing center aligned">'
      +     '<i class="large ' + icon + ' icon"></i>'
      +     '<input type="hidden" name="billings[][contractid]" value="' + contractID + '">'
      +   '</td>'
      + '</tr>'
    )
  ;

  // Fazemos o mascaramento do campo
  $base
    .next()
    .find('input[name="billings[][marked]"')
    .checkbox()
    .change(rowStatusHandler)
    .change(recalculateTotals)
  ;
  $base
    .next()
    .find('input[name="billings[][value]"')
    .mask({
      type: 'monetary',
      trim: true,
      allowNegativeValues: false,
      decimalsPlaces: 2,
      maxLength: 15
    })
    .blur(monetaryHandler)
    .change(recalculateTotals)
  ;

  if (fixed == false) {
    // Adicionamos a ação de excluir a linha
    module.debug("Adicionando manipulador de exclusão da linha");
    $base
      .next()
      .find('td:eq(3)')
      .click(removeThis)
    ;
  }

  if (marked) {
    // Acrescenta o valor desta linha no totalizador
    totalizer
      .push(value)
    ;

    // Indicamos que o mesmo está selecionado
    $base
      .next()
      .addClass('green')
    ;
  }
}

/**
 * Adiciona uma linha de agrupamento.
 *
 * @param int contractID
 *   O ID do contrato
 * @param int installationID
 *   O ID da instalação
 * @param string installationNumber
 *   O número da instalação
 * @param string plate
 *   A placa do carro
 *
 * @return void
 */
function addGroupingRow(
  contractID,
  installationID,
  installationNumber,
  plate
)
{
  var
    $newGroup = ''
      + '<tr data-installation="' + installationID + '" data-contract="' + contractID + '" class="grouped">'
      +   '<td class="collapsing right aligned"></td>'
      +   '<td class="left aligned bold">'
      +     'Placa'
      +       ((plate !== null
                 ? ' ativa: ' + plate
                 : ' não disponível'))
      +   '</td>'
      +   '<td colspan="2" class="left aligned">'
      +     '<span {{ tooltip.add("right", "O número da '
      +       'instalação do cliente") }}>'
      +       '<i class="file signature black icon"></i>'
      +     '</span>'
      +     installationNumber
      +   '</td>'
      + '</tr>'
  ;
  module.debug('Adicionando grupo para', plate);
  //debugger;  
  if (unboundContents > 0) {
    var
      $base = $tbody
        .find('[data-installation="0"]')
        .first()
    ;
    module.debug('Grupo adicionado antes dos itens adicionais');
    $base
      .before($newGroup)
    ;
  } else {
    module.debug('Grupo adicionado no final');
    $tbody
      .append($newGroup)
    ;
  }
}

/**
 * Removemos uma linha de agrupamento.
 *
 * @param int installationID
 *   O ID da instalação
 *
 * @return void
 */
function delGroupingRow(installationID)
{
  // Removemos quaiquer linhas relacionadas com esta instalação
  $tbody
    .find('[data-installation="' + installationID + '"]')
    .remove()
  ;
}

/**
 * Atualiza o estado da linha, colocando uma cor verde nas linhas
 * selecionadas.
 *
 * @return void
 */
function rowStatusHandler()
{
  if ($(this).is(":checked")) {
    $(this)
      .closest("tr")
      .addClass("green")
    ;
  } else {
    $(this)
      .closest("tr")
      .removeClass("green")
    ;
  }
}

/**
 * Remove a linha selecionada.
 *
 * @param Event event
 *   O evento de remoção
 *
 * @return void
 */
function removeThis(event)
{
  /**{% if chosenPayment == 'another' %}**/
  if (unboundContents > 1) {
    // Removemos a linha selecionada, apenas
    unboundContents--;
    $(event.target)
      .closest('tr')
      .remove()
    ;
  } else {
    // Apenas limpamos os valores desta linha
    var
      $row = $(event.target).closest('tr')
    ;
    module.debug('$row', $row);

    $row
      .find('td:eq(1) input')
      .val('')
    ;
    $row
      .find('td:eq(2) input')
      .val('0,00')
    ;
  }
  /**{% else %}**/
  var
    installationID = $(event.target)
      .closest('tr')
      .data('installation')
  ;

  if (installationID == 0) {
    // Decrementamos a quantidade de registros adicionais
    unboundContents--;
    module.debug('Decrementando itens adicionais', unboundContents);
    if (unboundContents > 0) {
      // Removemos apenas esta linha
      $(event.target)
        .closest('tr')
        .remove()
      ;
    } else {
      if (contents.size == 0) {
        // Não temos outros itens selecionados

        // Limpamos a tabela e retornamos a condição inicial
        tableClear();
      } else {
        // Removemos todas as linhas independentes
        delGroupingRow(0);
      }
    }
  } else {
    // Removemos esta linha
    $(event.target)
      .closest('tr')
      .remove()
    ;
    var
      $base = $tbody
        .find('[data-installation="' + installationID + '"]')
    ;

    if ($base.length == 1) {
      // Nenhum lançamento nesta instalação, então adiciona apenas
      // uma linha indicando que não têm valores
      $tbody
        .find('[data-installation="' + installationID + '"]')
        .last()
        .after(''
          + '<tr data-installation="' + installationID + '" data-empty="empty">'
          +   '<td class="collapsing right aligned">'
          +     '<div class="ui fitted checkbox">'
          +       '<input type="checkbox" disabled="disabled"><label></label>'
          +     '</div>'
          +   '</td>'
          +   '<td colspan="2" class="center aligned">'
          +     'Nenhum valor a ser cobrado'
          +   '</td>'
          +   '<td class="collapsing"></td>'
          + '</tr>'
        )
      ;
    }
  }
  /**{% endif %}**/

  // Atualiza a sequência dos campos
  module.debug('Disparando atualização tabela');
  tableRefresh();
}

/**
 * Recalcula os valores totais da tabela.
 *
 * @return void
 */
function recalculateTotals()
{
  totalizer = [];
  module.debug('Recauculando totais');
  $tbody
    .find('tr:has(input[type="text"])')
    .each(function() {
      // Analisamos as linhas marcadas
      var
        /**{% if chosenPayment is not same as 'another' %}**/
        marked = $(this)
          .find('td:nth-child(1) input[type="checkbox"]')
          .is(":checked"),
        /**{% endif %}**/
        value = $(this)
          .find('td:nth-child(3) input[type="text"]')
          .val()
      ;

      /**{% if chosenPayment == 'another' %}**/
      module.debug('Adicionando', value);
      totalizer
        .push(toFloat(value))
      ;
      /**{% else %}**/
      if (marked) {
        module.debug('Adicionando', value);
        totalizer
          .push(toFloat(value))
        ;
      }
      /**{% endif %}**/
    })
  ;
  module.debug('Total apurado');
  module.table(totalizer);

  totalRefresh();
}
/**{% endif %}**/

// =============================[ Manipulação da tabela de resumo ]=====

/**{% if chosenPayment is not same as 'another' %}**/
/**
 * Limpa o conteúdo da tabela, retornando à sua condição inicial.
 *
 * @return void
 */
function tableClear()
{
  // Limpa as variáveis
  /**{% if chosenPayment in ['unusual', 'closing'] %}**/
  independent = 0;
  /**{% endif %}**/
  contents = new Map();

  /**{% if chosenPayment is not same as 'carnet' %}**/
  totalizer = [];
  $tableTotal
    .text('0,00')
  ;
  $('input[name="valuetopay"]')
    .val('0.00')
  ;
  /**{% endif %}**/

  /**{% if chosenPayment == 'carnet' %}**/
  referenceMonths = [];
  /**{% endif %}**/
  /**{% if chosenPayment in ['carnet', 'prepayment'] %}**/
  $tbody
    .empty()
    .append(''
      + '<tr>'
      +   '<td class="collapsing right aligned">1.</td>'
      +   '<td colspan="3" class="center aligned">Nenhum valor</td>'
      + '</tr>'
    )
  ;
  /**{% else %}**/
    /**{% if chosenPayment == 'another' %}**/

    // Limpamos a tabela
    $tbody
      .empty()
    ;

    // Reincerimos ao menos uma cobrança
    addNewBilling();

    /**{% else %}**/
    $tbody
      .empty()
      .append(''
        + '<tr>'
        +   '<td class="collapsing right aligned">'
        +     '<input type="checkbox" disabled="disabled"><label></label>'
        +   '</td>'
        +   '<td colspan="2" class="center aligned">Nenhum valor</td>'
        +   '<td class="collapsing"></td>'
        + '</tr>'
      )
    ;
    /**{% endif %}**/
  /**{% endif %}**/
}
/**{% endif %}**/

/**{% if chosenPayment in ['carnet', 'prepayment'] %}**/
/**
 * Recarregamos os valores dos itens selecionados, recalculando os
 * períodos em função dos valores novos.
 *
 * @return void
 */
function tableReload()
{
  var
    startDate = $('input[name="startdate"]').val()
  ;
  if (dateIsValid(startDate)) {
    // Limpamos a tabela
    $tbody
      .empty()
    ;

    // Recarregamos todos os itens selecionados
    for (const [key, installation] of contents) {
      module.error('Recarregando item de contrato', installation.id);
      addSelectedInstallation(installation);
    }
  }
}
/**{% endif %}**/

/**
 * Atualiza o conteúdo da tabela
 *
 * @return void
 */
function tableRefresh()
{
  module.debug('Atualizando tabela');
  /**{% if chosenPayment in ['carnet', 'prepayment'] %}**/
    /**{% if chosenPayment == 'carnet' %}**/
    var
      parcel = 1
    ;
    $tbody
      .find('tr')
      .each(function(count, element) {
        if ($(element).hasClass('grouped')) {
          $(element)
            .find('td:eq(0)')
            .text(parcel + '.')
          ;
          parcel++;
        }
      })
    ;
    /**{% else %}**/
    $tbody
      .find('tr')
      .each(function(row) {
        // Atualizamos a numeração das linhas
        $tbody
          .find('tr:eq(' + row + ') td:eq(0)')
          .text((row + 1))
        ;
      })
    ;

    totalRefresh();
    /**{% endif %}**/
  /**{% else %}**/
    /**{% if chosenPayment in ['unusual', 'closing'] %}**/
    $tbody
      .find('tr:has(input[type="text"])')
      .each(function(row) {
        // Renomeamos os inputs para que o formulário possa ser lido
        // corretamente após o post
        $(this)
          .find('td:nth-child(1) input[type="checkbox"]')
          .attr('name', 'billings[' + row + '][marked]')
        ;
        $(this)
          .find('td:nth-child(1) input[type="hidden"]')
          .attr('name', 'billings[' + row + '][installationid]')
        ;
        $(this)
          .find('td:nth-child(2) input[type="text"]')
          .attr('name', 'billings[' + row + '][name]')
        ;
        $(this)
          .find('td:nth-child(2) input[type="hidden"]')
          .attr('name', 'billings[' + row + '][billingid]')
        ;
        $(this)
          .find('td:nth-child(3) input[type="text"]')
          .attr('name', 'billings[' + row + '][value]')
        ;
        $(this)
          .find('td:nth-child(3) input[type="hidden"]')
          .attr('name', 'billings[' + row + '][ascertained]')
        ;
        $(this)
          .find('td:nth-child(4) input[type="hidden"]')
          .attr('name', 'billings[' + row + '][contractid]')
        ;

        var
          marked = $(this)
            .find('td:nth-child(1) input[type="checkbox"]')
            .is(":checked"),
          value = $(this)
            .find('td:nth-child(3) input[type="text"]')
            .val()
        ;

        if (marked) {
          totalizer
            .push(toFloat(value))
          ;
        }
      })
    ;
    /**{% endif %}**/
    /**{% if chosenPayment == 'another' %}**/
    $tbody
      .find('tr:has(input)')
      .each(function(row) {
        // Renomeamos os inputs para que o formulário possa ser lido
        // corretamente após o post
        $tbody
          .find('tr:eq(' + row + ') td:eq(0)')
          .text((row + 1))
        ;
        $(this)
          .find('td:nth-child(2) input[type="text"]')
          .attr('name', 'billings[' + row + '][name]')
        ;
        $(this)
          .find('td:nth-child(3) input[type="text"]')
          .attr('name', 'billings[' + row + '][value]')
        ;

        var
          value = $(this)
            .find('td:nth-child(3) input[type="text"]')
            .val()
        ;

        totalizer
          .push(toFloat(value))
        ;
      })
    ;
    /**{% endif %}**/
  module.debug('Trigger recalculateTotals');
  recalculateTotals();
  module.debug('Trigger totalRefresh');
  totalRefresh();
  /**{% endif %}**/
}

/**{% if chosenPayment is not same as 'carnet' %}**/
/**
 * Atualizamos as informações do valor total da cobrança. Se necessário,
 * atualiza também o valor quando a forma de pagamento for parcelada.
 *
 * @return void
 */
function totalRefresh()
{
  var
    valueToPay = 0.00
  ;
  
  totalizer.forEach(function(item) {
    /**{% if chosenPayment == 'prepayment' %}**/
    valueToPay += item.total;
    /**{% else %}**/
    valueToPay += item;
    /**{% endif %}**/
  });

  // Atualizamos o valor total da cobrança
  $tableTotal
    .text(toMoney(valueToPay))
  ;
  $('input[name="valuetopay"]')
    .val(toMoney(valueToPay))
  ;

  /**{% if chosenPayment is not same as 'carnet' %}**/
  if (valueToPayDivider > 0) {
    // Precisamos atualizar também o valor da parcela do meio de
    // pagamento informado
    installmentRefresh(valueToPayDivider);
  }
  /**{% endif %}**/
}
/**{% endif %}**/

/**{% if chosenPayment is not same as 'carnet' %}**/
// ======================[ Manipulação das condições de pagamento ]=====
/**
 * Atualiza o valor da parcela para o meio de pagamento informado.
 *
 * @param int numberOfInstallments
 *   A quantidade de parcelas utilizada
 *
 * @return void
 */
function installmentRefresh(numberOfInstallments)
{
  var
    valueToPay = toFloat(
      $('input[name="valuetopay"]').val()
    ),
    installmentvalue = preciseRounding(
      valueToPay/numberOfInstallments, 2
    )
  ;
  module.debug('valueToPay', valueToPay);
  module.debug('installmentvalue', installmentvalue);

  $('input[name="installmentvalue"]')
    .val(
      toMoney(installmentvalue)
    )
  ;
}

/**
 * Limpa as opções de parcelamento para o meio de pagamento informado.
 *
 * @return void
 */
function installmentClear()
{
  valueToPayDivider = 0;
  
  $('#numberofinstallments')
    .empty()
  ;

  // Acrescentamos uma opção
  $('#numberofinstallments')
    .append('<option value="1">Sem parcelamento</option>')
  ;

  // Selecionamos o primeiro valor
  $('#numberofinstallments')
    .dropdown('set selected', '1')
  ;
}
/**{% endif %}**/

// ========================================[ Funções de validação ]=====

/**
 * Valida o formulário antes do envio.
 *
 * @param Object form
 *   O formulário a ser validado
 *
 * @return bool
 *   Se o formulário é válido
 */
function validateForm(form)
{
  var
    customerid   = $('input[name="customerid"]').val(),
    subsidiaryid = $('input[name="subsidiaryid"]').val()
  ;

  if ((parseInt(customerid) == 0) || (parseInt(subsidiaryid) == 0)) {
    warningDialog(
      'Atenção',
      'Selecione o cliente para o qual deseja emitir esta cobrança.'
    );

    return false;
  }

  /**{% if chosenPayment == 'unusual' %}**/
  if ( $("input[name='_chargeFromAssociate']").is(":checked") ) {
    // Verifica se foi informado o associado
    if ($('input[name="associateid"]').val() == 0) {
      warningDialog(
        'Atenção',
        'Informe o associado do qual será realizada a cobrança.'
      );

      return false;
    }
  }
  /**{% endif %}**/

  /**{% if chosenPayment in ['carnet', 'prepayment'] %}**/
  var
    numberofparcels = $('input[name="numberofparcels"]').val(),
    startDate = $('input[name="startdate"]').val()
  ;
  if (parseInt(numberofparcels) < 1) {
    warningDialog(
      'Atenção',
      'Selecione a quantidade de parcelas.'
    );

    return false;
  }
  if (!dateIsValid(startDate)) {
    warningDialog(
      'Atenção',
      'Selecione a data de início do período a ser cobrado.'
    );

    return false;
  }
  /**{% endif %}**/

  /**{% if chosenPayment is not same as 'another' %}**/
  // Verifica se foram informados valores a serem cobrados
  if (contents.size == 0) {
    warningDialog(
      'Atenção',
      'Selecione ao menos um item de contrato para emitir a cobrança.'
    );

    return false;
  }
  /**{% endif %}**/

  /**{% if chosenPayment is not same as 'carnet' %}**/
  if ($("input[name='paymentconditionid']").val() == 0) {
    warningDialog(
      'Atenção',
      'Informe a condição de pagamento.'
    );

    return false;
  }

  if ($("input[name='valuetopay']").val() == '0,00') {
    warningDialog(
      'Atenção',
      'Informe algum valor a ser cobrado.'
    );

    return false;
  }
  /**{% else %}**/
  if (Object.keys(totalizer).length == 0) {
    warningDialog(
      'Atenção',
      'Informe algum valor a ser cobrado.'
    );

    return false;
  }
  /**{% endif %}**/

  /**{% if chosenPayment in ['carnet', 'prepayment'] %}**/
  // Incluímos as informações dos itens de contrato selecionados
  for (const [key, installation] of contents) {
    var
      newInput = $("<input>")
        .attr({"type":"hidden","name":"installations[]"})
        .val(installation.id)
    ;

    $(form)
      .append(newInput)
    ;
  }
  /**{% endif %}**/

  return true;
}

// =============================[ Funções de conversão/formatação ]=====

/**
 * Obtém um objeto Date a partir de uma data.
 *
 * @param string value
 *   A data a ser convertida
 *
 * @return Data
 *   O objeto do tipo data
 */
function toDate(value)
{
  if (value.trim() == "") {
    // Retornamos a data atual
    var
      today = new Date()
    ;

    return today.setHours(0,0,0,0);
  }

  var
    formated
  ;
  if (value.includes("/")) {
    var
      dateParts = value.split("/")
    ;
    formated = dateParts[2] + '-' + dateParts[1] + '-' + dateParts[0]
      + ' 00:00:00.0000'
    ;
  } else {
    var
      dateParts = value.split("-")
    ;
    formated = dateParts[0] + '-' + dateParts[1] + '-' + dateParts[2]
      + ' 00:00:00.0000'
    ;
  }

  return new Date(formated);
}

/**
 * Formata datas no padrão Brasileiro.
 *
 * @param DateTime date
 *   A data a ser formatada
 * 
 * @return string
 */
function formatDate(date)
{
  var
    day = String(date.getDate()).padStart(2, '0'),
    month = String(date.getMonth() + 1).padStart(2, '0'),
    year = date.getFullYear()
  ;

  return day + '/' + month + '/' + year;
}

/**
 * Verifica se a data informada é uma data válida no formato DD/MM/YYYY.
 *
 * @param string dateString
 *   A data a ser analisada
 *
 * @return bool
 *   Se a data é válida
 */
function dateIsValid(dateString)
{
  // First check for the pattern
  if(!/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(dateString)) {
    return false;
  }

  // Parse the date parts to integers
  var
    parts = dateString.split("/"),
    day = parseInt(parts[0], 10),
    month = parseInt(parts[1], 10),
    year = parseInt(parts[2], 10)
  ;

  // Check the ranges of month and year
  if(year < 1000 || year > 3000 || month == 0 || month > 12) {
    return false;
  }

  var
    monthLength = [ 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ]
  ;

  // Adjust for leap years
  if(year % 400 == 0 || (year % 100 != 0 && year % 4 == 0)) {
    monthLength[1] = 29;
  }

  // Check the range of the day
  return day > 0 && day <= monthLength[month - 1];
}

/**{% if chosenPayment in ['unusual', 'closing'] %}**/
/**
 * Obtém se a data informada é menor ou igual ao mês atual.
 *
 * @param Date dateValue
 *   A data a ser analizada
 *
 * @return bool
 *   Se a data informada é menor ou igual ao mês atual
 */
function lessThanOrEqualToCurrentMonth(dateValue)
{
  var
    // Obtemos as datas para analisar
    dateOfValue    = new Date(dateValue),
    today          = new Date(),
    lastDayOfMonth = new Date(today.getFullYear(), today.getMonth()+1, 0)
  ;

  return (dateOfValue <= lastDayOfMonth);
}
/**{% endif %}**/

/**
 * Converte valores formatados no padrão monetário Brasileiro em um
 * valor de ponto flutuante.
 *
 * @param string value
 *   O valor a ser convertido
 *
 * @return float
 */
function toFloat(value)
{
  return parseFloat(
    value
      .replace(".", "")
      .replace(",", ".")
  );
}

/**
 * Formata valores em float no padrão monetário Brasileiro.
 * 
 * @param float value
 *   O valor a ser formatado
 *
 * @return string
 */
function toMoney(value)
{
  if (typeof value !== 'number') {
    value = parseFloat(value);
  }

  return value
    .toFixed(2)
    .replace('.', ',')
  ;
}

/**
 * Converte o valor de um intervalo em uma matriz.
 *
 * @param string value
 *   O valor com o intervalo
 *
 * @return array
 */
function toArray(value)
{
  var
    data = value.replace(/[{}]+/g,'')
  ;

  return data.split(',').map(Number);
}

/**
 * Realiza o arredondamento preciso do valor
 *
 * @param float number
 *   O número a ser arredondado
 * @param int precision
 *   O número de casas decimais a ser considerado
 *
 * @return float
 *   O valora arredondado
 */
function preciseRounding(number, precision) {
  var
    factor = Math.pow(10, precision),
    tempNumber = number * factor,
    roundedTempNumber = Math.round(tempNumber)
  ;

  return roundedTempNumber / factor;
};

/**
 * Atrasa a execução da uma função.
 *
 * @param Object callback
 *   A função a ser executada
 * @param int delayTime
 *   O tempo (em millisegundos) de atraso
 *
 * @return void
 */
function delay(callback, delayTime) {
  var
    timer = 0
  ;

  clearTimeout(timer);

  timer = setTimeout(function () {
    callback();
  }, delayTime || 0);
}

/**
 * Incrementa em um dia a data informada.
 *
 * @param string lastDay
 *   O último dia
 *
 * @return string
 */
function getNextDay(lastDay) {
  var
    nextDay = toDate(lastDay)
  ;

  nextDay = nextDay.addDays(1);

  return formatDate(nextDay);
}