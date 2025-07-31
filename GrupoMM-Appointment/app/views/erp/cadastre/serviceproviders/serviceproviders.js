var
  table
;

// Os formatadores de colunas

// Um formatador de colunas para exibir um ícone antes do nome de um
// prestador de serviços
var nameFormatter = function ( value, type, row ) {
  var
    icon  = 'ambulance',
    type  = 'Prestador de serviços',
    button = ''
      + '<button class="ui primary inline icon button"'
      + '        data-tooltip="Adiciona um técnico"'
      + '        data-position="right center" type="button"'
      + '        data-blue data-inverted'
      + '        name="addTechnicianButton"'
      + '        onclick="addTechnician(event, ' + row.id + ')">'
      + '  <i class="add icon" name="addTechnicianIcon"></i>'
      + '</button>',
    label = value
  ;

  if (row.tradingname) {
    // Acrescenta o apelido ou nome fantasia do prestador de
    // serviços
    label += ' '
      + '<i style="color: #0073cf; font-weight: bold;"> [ '
      + row.tradingname
      + ' ]</i>'
    ;
  }

  if (row.level == 1) {
    icon = 'fas fa-user';
    type = 'Técnico';
  } else {
    label += '<br>'
      + '<i style="color: SlateGray; font-size: .9em;">Nosso '
      +   type.toLowerCase() + ' há '
      +   getAge(row.createdat.substring(0, 10))
      + '</i>'
    ;
  }

  return ''
    + ((row.level == 0)?button:'')
    + '<span data-position="right center"'
    +       'data-blue data-inverted '
    +       'data-tooltip="' + type + '">'
    +   '<i class="fas fa-' + icon + '"></i>'
    + '</span>'
    + label
  ;
};

// Um formatador de colunas para exibir um ícone antes dos telefones
// para informar que podem ser copiados
var phoneFormatter = function ( value ) {
  var
    icon  = 'copy outline grey icon',
    label = 'Clique para copiar para a área de transferência'
  ;

  return ''
    + '<span data-position="left center"'
    +       'data-blue data-inverted '
    +       'data-tooltip="' + label + '">'
    +   '<i class="' + icon + '"></i>'
    + '</span>' + value
  ;
};

// Um formatador (render) de coluna para exibir o texto indicativo da
// situação do cadastro
var activeFormatter = function ( value, type, row ) {
  // Verifica se foi solicitada a exibição do valor
  if ( type === 'display' ) {
    if (row.active === false) {
      // Retornamos um texto indicando que está inativo
      return '<span class="status" style="background-color: #528aae;">' +
               'Inativo' +
             '</span>';
    } else {
      if (row.blockedlevel > 0) {
        // Retornamos um texto indicando que está inativo
        return '<span class="status" style="background-color: #c81120;">' +
                 'Bloqueado' +
               '</span>';
      } else {
        // Retornamos um texto indicando que está ativo
        return '<span class="status" style="background-color: #4a9c35;">' +
                 'Ativo' +
               '</span>';
      }
    }
  }

  // Pesquisas, ordenamentos e tipos podem usar os dados originais
  return value;
};

// Um formatador para exibir um ícone indicativo de que o prestador
// de serviços está ou não bloqueado
var blockedFormatter = function ( value, type, row ) {
  var
    buildIcon = function (color, icon, title) {
      return '<span data-position="left center"' +
                   'data-blue data-inverted ' +
                   'data-tooltip="' + title + '">' +
               '<i class="' + icon + ' ' + color + ' icon">' +
               '</i>' +
             '</span>';
    }
  ;

  switch (value) {
    case 1:
      // O prestador de serviços está bloqueado
      return buildIcon('lock', 'darkred', ''
        + 'A conta deste prestador de serviços está bloqueada')
      ;
    case 2:
      // Apenas o técnico está bloqueado
      return buildIcon('lock', 'darkorange', ''
        + 'A conta do técnico está bloqueada'
      );
    default:
      // Está livre
      return buildIcon('unlock', 'lightgrey', ''
        + 'Nenhum bloqueio nesta conta')
      ;
  }
};

// Sobrescreve o tratamento de erros
$.fn.dataTable.ext.errMode = function ( settings, helpPage, message ) {
  var
    errorMessage = ''
  ;

  if (typeof(message) != 'undefined' && message != null) {
    if (message.length > 38) {
      // Exibe a mensagem de erro
      errorMessage = message.substring(38);
    }
  }

  if (errorMessage.startsWith('Não temos prestadores de serviços cadastrados')) {
    // Não faz nada
  } else {
    // Exibe a mensagem de erro
    if (errorMessage.length > 0) {
      infoDialog('Desculpe-nos', errorMessage);
    }
  }
};

$(document).ready(function() {
  // --------------------------------[ Componentes da Searchbar ]---
  $('.searchbar .ui.dropdown')
    .dropdown()
  ;
  $('#searchValue').keypress(function(e) {
    if (e.which == 13) {
      searchLoad();
    }
  });

  // Lida com o pressionamento de teclas
  $(document).on('keydown',function(event) {
    if (event.keyCode == 27) {
      // Limpa os dados do formulário
      $('#filterType').val('');

      searchLoad();
    }
  });

  // ----------------------------------------------[ Datatables ]---

  // Atualiza a tabela para reajustar as colunas em caso de
  // alternância da barra lateral
  $('#ResizeSidebarMenu').click(function() {
    setTimeout(function() {
      table
        .columns
        .adjust()
      ;
    }, 500);
  });

  // Atualiza a tabela para reajustar as colunas em caso de
  // redimensionamento da janela
  $(window).resize(function() {
    setTimeout(function() {
      table
        .columns
        .adjust()
      ;
    }, 500);
  });

  table = $('#result').DataTable({
    pagingType: "first_last_numbers",
    lengthChange: false,
    searching: false,
    scrollX: true,
    language: {
      url: "{{ i18n('datatables/plugins/i18n/Portuguese-Brasil.json') }}"
    },
    columnDefs: [
      { "targets": 0,
        "name": "id",
        "data": "id",
        "visible": false,
        "orderable": false },
      { "targets": 1,
        "name": "subsidiaryid",
        "data": "subsidiaryid",
        "visible": false,
        "orderable": false },
      { "targets": 2,
        "name": "technicianid",
        "data": "technicianid",
        "visible": false,
        "orderable": false },
      { "targets": 3,
        "name": "juridicalperson",
        "data": "juridicalperson",
        "visible": false,
        "orderable": false },
      { "targets": 4,
        "name": "technicianistheprovider",
        "data": "technicianistheprovider",
        "visible": false,
        "orderable": false },
      { "targets": 5,
        "name": "level",
        "data": "level",
        "visible": false,
        "orderable": false },
      { "targets": 6,
        "name": "NULL",
        "data": "name",
        "visible": true,
        "orderable": false,
        "render": nameFormatter },
      { "targets": 7,
        "name": "tradingname",
        "data": "tradingname",
        "visible": false,
        "orderable": false },
      { "targets": 8,
        "name": "blocked",
        "data": "blocked",
        "visible": false,
        "orderable": false },
      { "targets": 9,
        "name": "cityname",
        "data": "cityname",
        "visible": true,
        "orderable": false },
      { "targets": 10,
        "name": "occupationarea",
        "data": "occupationarea",
        "visible": true,
        "orderable": false },
      { "targets": 11,
        "name": "nationalregister",
        "data": "nationalregister",
        "visible": true,
        "orderable": false,
        "width": "100px" },
      { "targets": 12,
        "name": "phones",
        "data": "phones",
        "visible": true,
        "orderable": false,
        "render": phoneFormatter },
      { "targets": 13,
        "name": "active",
        "data": "active",
        "className": "dt-center",
        "visible": true,
        "width": "30px",
        "orderable": false,
        "render": activeFormatter },
      { "targets": 14,
        "name": "pdf",
        "data": "id",
        "className": "dt-center",
        "visible": "{% if authorization.getAuthorizationFor(getPDF.URL, getPDF.method) %}true{% else %}false{% endif %}"=="true",
        "orderable": false,
        "width": "10px",
        "render": iconFormatter("file pdf", "black", "Permite gerar um PDF para impressão dos dados cadastrais desta unidade/filial") },
      { "targets": 15,
        "name": "blockedlevel",
        "data": "blockedlevel",
        "className": "dt-center",
        "visible": true,
        "orderable": false,
        "width": "10px",
        "render": blockedFormatter },
      { "targets": 16,
        "name": "createdat",
        "data": "createdat",
        "visible": false,
        "orderable": false }
    ],
    order: [[ 6, 'asc' ]],
    select: {
      style: 'single',
      items: 'cell'
    },
    processing: true,
    serverSide: true,
    displayStart: parseInt("{{ serviceProvider.displayStart|default('0') }}"),
    ajax: {
      url: "{{ path_for(getData.URL) }}",
      type: "{{ getData.method }}",
      data: function ( params ) {
        params.searchValue = $('#searchValue').val();
        params.searchField = $('#searchField').val();
        params.filterType  = $('#filterType').val();
      },
      error: handleAjaxError
    },
    responsive: true,
    initComplete: function() {
      // Unstack Pagination
      $('div.ui.pagination.menu')
        .removeClass('stackable')
        .addClass('unstackable')
      ;
    },
    drawCallback: function() {
      // Unstack Pagination
      $('div.ui.pagination.menu')
        .removeClass('stackable')
        .addClass('unstackable')
      ;
    },
    createdRow: function( row, data ) {
      if (data.level == 0) {
        // Expande a coluna
        //$('td:eq(0)', row).attr('colspan', 3);
        //$('td:eq(1)', row).css('display', 'none');
        //$('td:eq(2)', row).css('display', 'none');
      }

      // Deixa as linhas com cores diferentes para indicar bloqueio
      if ( data.active == false ) {
        // Adiciona o estado de inativo
        if (data.blockedlevel > 0) {
          $(row).addClass('blocked');
        }

        // Adiciona o estado de negativado
        $(row).addClass('inactived');
      } else {
        if (data.blockedlevel > 0) {
          $(row).addClass('blocked');
        } else {
          switch (data.level) {
            case 0:
              $(row).addClass('grouped');

              break;
            case 1:
              $(row).addClass('technician');

              break;
            default:
              // Não faz nada
          }
        }
      }
    }
  });

  // Manipula os eventos de click
  table
    .on('user-select', function (e, dt, type, cell) {
      if (type === 'cell') {
        var
          // Recupera os dados da célula selecionada
          index   = cell[0][0],

          // Recupera o prestador de serviços selecionado
          serviceProvider = dt.rows( index.row ).data().toArray()[0]
        ;

        // Em função da coluna onde ocorreu o clique, executa a ação
        // correspondente
        switch (index.column) {
          case 12:
            if (serviceProvider.phones !== undefined) {
              copyTextToClipboard(serviceProvider.phones);
            }

            break;
          /**{% if authorization.getAuthorizationFor(getPDF.URL, getPDF.method) %}**/
          case 14:
            var url;

            // Gera um PDF com as informações cadastrais
            if (serviceProvider.level == 1) {
              // Da unidade/filial do prestador de serviços
              url = "{{ buildURL(getPDF.URL, {'serviceProviderID': 'serviceProvider.id', 'subsidiaryID': 'serviceProvider.subsidiaryid'}) }}";
            } else {
              // Do prestador de serviços
              url = "{{ buildURL(getPDF.URL, {'serviceProviderID': 'serviceProvider.id'}) }}";
            }

            window.open(url, '_blank');

            break;
          /**{% endif %}**/
          /**{% if authorization.getAuthorizationFor(toggleBlocked.URL, toggleBlocked.method) %}**/
          case 15:
            // Alterna o bloqueio do técnico e/ou prestador de serviços
            var
              action = serviceProvider.blocked
                ? "desbloquear"
                : "bloquear",
              person,
              complement = ''
            ;

            if (serviceProvider.level == 0) {
              person = (serviceProvider.juridicalperson === true)
                ? 'o prestador de serviços'
                : 'o técnico'
              ;
              complement = ' deste prestador de serviços';
            } else {
              person = 'o técnico';
            }

            questionDialog(
              action.charAt(0).toUpperCase() + action.slice(1), ''
                + 'Você deseja realmente ' + action + ' ' + person
                + ' <b>&ldquo;' + serviceProvider.name
                + '&rdquo;</b>' + complement + '?',
              function() {
                // Alternar o bloqueio da entidade selecionada
                var url;

                if (serviceProvider.level == 1) {
                  url  = "{{ buildURL(toggleTechnician.URL, {'serviceProviderID': 'serviceProvider.id', 'technicianID': 'serviceProvider.technicianid'}) }}";
                } else {
                  url  = "{{ buildURL(toggleBlocked.URL, {'serviceProviderID': 'serviceProvider.id'}) }}";
                }

                putJSONData(url, [],
                function () {
                  // Atualiza a tabela
                  searchLoad();
                });
              },
              function() {
                table
                  .rows()
                  .deselect()
                ;
                table
                  .draw('page')
                ;
              }
            );

            break;
          /**{% endif %}**/
          default:
            if (serviceProvider.level == 1) {
              /**{% if authorization.getAuthorizationFor(editTechnician.URL, editTechnician.method) %}**/
              // Coloca o técnico em edição
              window.location.href = "{{ buildURL(editTechnician.URL, {'serviceProviderID': 'serviceProvider.id', 'technicianID': 'serviceProvider.technicianid'}) }}";
              /**{% endif %}**/
            } else {
              /**{% if authorization.getAuthorizationFor(edit.URL, edit.method) %}**/
              // Coloca o prestador de serviços em edição
              window.location.href = "{{ buildURL(edit.URL, {'serviceProviderID': 'serviceProvider.id'}) }}";
              /**{% endif %}**/
            }
        }

        e.preventDefault();
      }
    }
  );

  // Força a atualização da tabela para reajustar as colunas após
  // a carga
  setTimeout(function() {
    table
      .columns
      .adjust()
    ;
  }, 1500);
});


// ================================================[ Handlers ]=====

// Executa a pesquisa
function searchLoad() {
  table
    .ajax
    .reload()
  ;
  setTimeout(function() {
    table
      .columns
      .adjust()
    ;
  }, 500);
}

// Recupera a idade a partir de uma data
function getAge(date) {
  function plularize(name, value, separator) {
    var
      result = ''
    ;

    if (value > 0) {
      if (name === 'mês') {
        if (value > 1) {
          result = value + ' meses';
        } else {
          result = value + ' mês';
        }
      } else {
        result = value + ' ' + name;

        if (value > 1) result += 's';
      }

      result += separator;
    }

    return result;
  }

  var
    date_part   = date.split("-"),
    day         = date_part[2],
    month       = date_part[1],
    year        = date_part[0],

    // Recuperamos a data atual
    today       = new Date(),
    today_year  = today.getYear(),
    today_month = today.getMonth() + 1,
    today_day   = today.getDate()
  ;

  // Realizamos os cálculos
  var
    age = (today_year + 1900) - year
  ;

  if (today_month < month) {
    age--;
  }
  if ((month == today_month) && (today_day < day)) {
    age--;
  }
  if (age > 1900) {
    age -= 1900;
  }

  // Calculamos os meses
  var
    months = 0
  ;

  if (today_month > month && day > today_day) {
    months = today_month - month - 1;
  }
  else if (today_month > month) {
    months = today_month - month
  }

  if (today_month < month && day < today_day) {
    months = 12 - (month - today_month);
  }
  else if (today_month < month) {
    months = 12 - (month - today_month + 1);
  }

  if (today_month == month && day > today_day) {
    months = 11;
  }

  // Calculamos os dias
  var
    days = 0
  ;

  if (today_day > day) {
    days = today_day - day;
  }
  if (today_day < day) {
    var
      lastDayOfMonth = new Date(today_year, today_month - 1, 0)
    ;

    days = lastDayOfMonth.getDate() - (day - today_day);
  }

  if (age > 0) {
    if (months > 0) {
      return plularize('ano', age, ' e ') +
             plularize('mês', months, '');
    } else {
      return plularize('ano', age, '');
    }
  } else {
    if (months > 0) {
      if (days > 0) {
        return plularize('mês', months, ' e ') +
               plularize('dia', days, '');
      } else {
        return plularize('mês', months, '');
      }
    } else {
      if (days > 0) {
        return plularize('dia', days, '');
      } else {
        return 'menos de um dia';
      }
    }
  }

  return plularize('mês', months, '');
}

/**
 * Copia o conteúdo informado para a área de transferência.
 *
 * @param string content
 *   O conteúdo a ser copiado
 * @param string message (opcional)
 *   Uma mensagem para alertar o usuário
 *
 * @return void
 */
async function copyTextToClipboard(content, message = '') {
  if (message == '') {
    message = 'Conteúdo copiado com sucesso';
  }

  // Sempre acrescenta o ícone de cópia
  message = '<i class="copy outline large icon"></i> ' + message;

  try {
    await navigator.clipboard.writeText(content);

    $('body')
      .toast({
        message: message,
        class : 'blue',
        showProgress: 'bottom'
      })
    ;
  } catch (error) {
    message = '<i class="copy outline large icon"></i> Não foi '
     + 'possível copiar o conteúdo'
    ;

    $('body')
      .toast({
        message: message,
        class : 'red',
        showProgress: 'bottom'
      })
    ;
  }
}

function addTechnician(event, serviceProviderID) {
  event.preventDefault();
  event.stopPropagation();
  /**{% if authorization.getAuthorizationFor(addTechnician.URL, add.method) %}**/
  // Adicionamos um novo técnico
  window.location.href = "{{ buildURL(addTechnician.URL, { 'serviceProviderID': 'serviceProviderID' }) }}";
  /**{% endif %}**/
}
