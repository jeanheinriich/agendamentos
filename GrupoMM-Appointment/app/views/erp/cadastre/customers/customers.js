var
  table
;

// Os formatadores de colunas

// Um formatador de colunas para exibir um ícone antes do nome de um
// associado de uma associação
var nameFormatter = function ( value, type, row, meta ) {
  var
    icon  = 'fas fa-house-user',
    type  = 'Cliente',
    label = value
  ;

  switch (row.level) {
    case 0:
      if (row.cooperative) {
        icon = 'fas fa-clinic-medical blue';
        type = 'Associação';
      } else {
        icon = 'fas fa-house-user';
        type = 'Cliente';
      }

      break;
    case 1:
      if (row.juridicalperson) {
        icon = 'plus square grey icon';
        if (row.headoffice) {
          type = 'Matriz';
        } else {
          type = 'Unidade/filial';
        }
      } else {
        icon = 'fas fa-user';
        if (row.headoffice) {
          type = 'Titular';
        } else {
          type = 'Dependente';
        }
      }

      break;
    case 2:
      if (row.cooperative) {
        icon = 'fas fa-clinic-medical blue';
        type = 'Associação';
      } else {
        icon = 'fas fa-house-user';
        type = 'Cliente';
      }

      break;
    case 3:
      icon = 'check circle orange icon';
      type = 'Associado';

      break;
    default:
      icon = 'fas fa-info-circle';
      type = 'Desconhecido';
  }

  if (row.tradingname) {
    // Acrescenta o apelido ou nome fantasia do cliente
    label += ' '
      + '<i style="color: #0073cf; font-weight: bold;"> [ '
      + row.tradingname
      + ' ]</i>'
    ;
  }
  label += '&nbsp;'
    + '<span data-position="right center"'
    +       'data-blue data-inverted '
    +       'data-tooltip="Clique para copiar para a área de transferência">'
    +   '<i class="copy outline grey icon"></i>'
    + '</span>'
  ;

  if (row.active) {
    if (row.level !== 1) {
      if (row.type == 1) {
        label += '<br>'
          + '<i style="color: SlateGray; font-size: .9em; padding-left: 1.6em;">Nosso cliente há '
          +   getAge(row.createdat.substring(0, 10))
          + '</i>'
        ;
      } else {
        label += '<br>'
          + '<i style="color: SlateGray; font-size: .9em; padding-left: 1.6em;">Associado há '
          +   getAge(row.createdat.substring(0, 10))
          + '</i>'
        ;
      }
    }
  }

  return ''
    + '<span data-position="right center"'
    +       'data-blue data-inverted '
    +       'data-tooltip="' + type + '">'
    +   '<i class="' + icon + '"></i>'
    + '</span>' + label
  ;
};

// Um formatador de colunas para exibir um ícone antes do nome de um
// associado de uma associação
var nationalRegisterFormatter = function ( value, type, row, meta ) {
  return value + '&nbsp;'
    + '<span data-position="right center"'
    +       'data-blue data-inverted '
    +       'data-tooltip="Clique para copiar para a área de transferência">'
    +   '<i class="copy outline grey icon"></i>'
    + '</span>'
  ;
};

// Um formatador (render) de coluna para exibir o texto indicativo
// de situação do cadastro
var activeFormatter = function ( value, type, row, meta ) {
  // Verifica se foi solicitada a exibição do valor
  if ( type === 'display' ) {
    if (row.hasrelationship === false) {
      // Retornamos um texto indicando que está inativo
      return '<span class="status" style="background-color: #f78104;">' +
               'Sem relacionamento' +
             '</span>';
    } else {
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
  }

  // Pesquisas, ordenamentos e tipos podem usar os dados originais
  return value;
};

// Um formatador para exibir um ícone indicativo de que o cliente
// está ou não bloqueado
var blockedFormatter = function ( value, type, row, meta ) {
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
      // O cliente está bloqueado
      return buildIcon('lock', 'darkred', ''
        + 'A conta deste cliente está bloqueada'
      );

      break;
    case 2:
      // Apenas a unidade/filial está bloqueada
      if (row.juridicalperson === true) {
        if (row.headoffice === true) {
          person = 'a matriz';
        } else {
          person = 'a unidde/filial';
        }
      } else {
        if (row.headoffice === true) {
          person = 'o titular';
        } else {
          person = 'o dependente';
        }
      }

      return buildIcon('lock', 'darkorange', ''
        + 'A conta d' + person + ' está bloqueada'
      );

      break;
    case 3:
      // A conta do associado está bloqueada
      return buildIcon('lock', 'darkorange', ''
        + 'A conta deste associado está bloqueada'
      );

      break;
    default:
      // Está livre
      return buildIcon('unlock', 'lightgrey', ''
        + 'Nenhum bloqueio nesta conta'
      );
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

  if (errorMessage.startsWith('Não temos clientes cadastrados')) {
    // Não faz nada
  } else {
    // Exibe a mensagem de erro
    if (errorMessage.length > 0) {
      infoDialog('Desculpe-nos', errorMessage);
    }
  }
};

$(document).ready(function() {
  var groupColumn = 4;

  // --------------------------------[ Componentes da Searchbar ]---
  $('.searchbar .ui.dropdown')
    .dropdown()
  ;
  $('#filterType')
    .change(filterTypeHandler)
  ;
  $('#searchValue').keypress(function(e) {
    if (e.which == 13) {
      searchLoad();
    }
  });

  // Lida com o pressionamento de teclas
  $(document).on('keydown',function(event) {
    if (event.keyCode == 27) {
      var
        $dropdown = $('#filterType')
      ;

      // Limpa os dados do formulário
      $('#filterType').val('');

      // Limpamos quaisquer seleções anteriores
      $dropdown
        .dropdown('clear')
      ;

      // Definimos o primeiro tipo de filtro disponível
      $dropdown
        .dropdown('set selected', 0)
      ;
      $dropdown
        .dropdown('refresh')
      ;

      // Limpa o campo de pesquisa
      $('#searchValue')
        .val('')
      ;

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
        "name": "affiliatedid",
        "data": "affiliatedid",
        "visible": false,
        "orderable": false },
      { "targets": 3,
        "name": "juridicalperson",
        "data": "juridicalperson",
        "visible": false,
        "orderable": false },
      { "targets": 4,
        "name": "cooperative",
        "data": "cooperative",
        "visible": false,
        "orderable": false },
      { "targets": 5,
        "name": "headoffice",
        "data": "headoffice",
        "visible": false,
        "orderable": false },
      { "targets": 6,
        "name": "type",
        "data": "type",
        "visible": false,
        "orderable": false },
      { "targets": 7,
        "name": "level",
        "data": "level",
        "visible": false,
        "orderable": false },
      { "targets": 8,
        "name": "activeassociation",
        "data": "activeassociation",
        "visible": false,
        "orderable": false },
      { "targets": 9,
        "name": "NULL",
        "data": "name",
        "visible": true,
        "orderable": false,
        "render": nameFormatter },
      { "targets": 10,
        "name": "tradingname",
        "data": "tradingname",
        "visible": false,
        "orderable": false },
      { "targets": 11,
        "name": "blocked",
        "data": "blocked",
        "visible": false,
        "orderable": false },
      { "targets": 12,
        "name": "cityname",
        "data": "cityname",
        "visible": true,
        "orderable": false },
      { "targets": 13,
        "name": "nationalregister",
        "data": "nationalregister",
        "visible": true,
        "orderable": false,
        "render": nationalRegisterFormatter,
        "width": "100px" },
      { "targets": 14,
        "name": "active",
        "data": "active",
        "className": "dt-center",
        "visible": true,
        "width": "30px",
        "orderable": false,
        "render": activeFormatter },
      { "targets": 15,
        "name": "pdf",
        "data": "id",
        "className": "dt-center",
        "visible": "{% if authorization.getAuthorizationFor(getPDF.URL, getPDF.method) %}true{% else %}false{% endif %}"=="true",
        "orderable": false,
        "width": "10px",
        "render": iconFormatter("file pdf", "black", "Permite gerar um PDF para impressão dos dados cadastrais") },
      { "targets": 16,
        "name": "blockedlevel",
        "data": "blockedlevel",
        "className": "dt-center",
        "visible": true,
        "orderable": false,
        "width": "10px",
        "render": blockedFormatter },
      { "targets": 17,
        "name": "delete",
        "data": "delete",
        "className": "dt-center",
        "visible": "{% if authorization.getAuthorizationFor(remove.URL, remove.method) %}true{% else %}false{% endif %}"=="true",
        "orderable": false,
        "width": "10px",
        "visible": false,
        "render": iconFormatter("minus", "lightgrey", "") },
      { "targets": 18,
        "name": "createdat",
        "data": "createdat",
        "visible": false,
        "orderable": false },
      { "targets": 19,
        "name": "hasrelationship",
        "data": "hasrelationship",
        "visible": false,
        "orderable": false }
    ],
    order: [[ 9, 'asc' ]],
    select: {
      style: 'single',
      items: 'cell'
    },
    processing: true,
    serverSide: true,
    displayStart: parseInt('{{ customer.displayStart|default("0") }}'),
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
    initComplete: function(settings, json) {
      // Unstack Pagination
      $('div.ui.pagination.menu')
        .removeClass('stackable')
        .addClass('unstackable')
      ;
    },
    createdRow: function(row, data, dataIndex) {
      if (data.level == 0) {
        // Expande a coluna
        $('td:eq(0)', row).attr('colspan', 3);
        $('td:eq(1)', row).css('display', 'none');
        $('td:eq(2)', row).css('display', 'none');
      }

      // Deixa as linhas com cores diferentes para indicar bloqueio
      if ( data.cooperative && data.activeassociation == false ) {
        // Adiciona a informação de grupo, se necessário
        if (data.level = 0) {
          $(row).addClass('grouped');
        }

        // Adiciona o estado de inativo, se necessário
        if (data.blockedlevel > 0) {
          $(row).addClass('blocked');
        }

        // A associação inteira está inativa, então adiciona o
        // estado de negativado
        $(row).addClass('inactived');
      } else {
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
              case 3:
                $(row).addClass('affiliated');

                break;
              default:
                // Não faz nada
            }
          }
        }
      }
    }
  });

  // Manipula os eventos de click
  table
    .on('user-select', function (e, dt, type, cell, originalEvent) {
      if (type === 'cell') {
        var
          // Recupera os dados da célula selecionada
          index   = cell[0][0],

          // Recupera o cliente selecionado
          customer = dt.rows( index.row ).data().toArray()[0]
        ;

        // Em função da coluna onde ocorreu o clique, executa a ação
        // correspondente
        switch (index.column) {
          case 15:
            /**{% if authorization.getAuthorizationFor(getPDF.URL, getPDF.method) %}**/
            var
              url
            ;

            // Gera um PDF com as informações cadastrais
            switch (customer.level) {
              case 0, 2:
                // Do cliente
                url = "{{ buildURL(getPDF.URL, {'customerID': 'customer.id'}) }}";

                break;
              case 1:
                // Da unidade/filial do cliente
                url = "{{ buildURL(getPDF.URL, {'customerID': 'customer.id', 'subsidiaryID': 'customer.subsidiaryid'}) }}";

                break;
              case 3:
                // Do associado
                url = "{{ buildURL(getPDF.URL, {'customerID': 'customer.affiliatedid'}) }}";
            }
            window.open(url, '_blank');
            /**{% endif %}**/

            break;
          case 16:
            /**{% if authorization.getAuthorizationFor(toggleBlocked.URL, toggleBlocked.method) %}**/
            var
              action = customer.blocked
                ? "desbloquear"
                : "bloquear",
              person,
              complement = ''
            ;

            switch (customer.level) {
              case 0:
                // O cliente
                if (customer.cooperative) {
                  person = 'a associação';
                } else {
                  person = 'o cliente';
                }

                break;
              case 1:
                // A unidade/filial
                if (customer.juridicalperson) {
                  person = 'a unidade';
                } else {
                  if (customer.headoffice) {
                    person = 'o titular';
                  } else {
                    person = 'o dependente';
                  }
                }

                if (customer.cooperative) {
                  complement = ' desta associação';
                } else {
                  complement = ' deste cliente';
                }

                break;
              case 2:
                // O cliente
                if (customer.cooperative) {
                  person = 'a associação';
                } else {
                  person = 'o cliente';
                }

                break;
              case 3:
                // O associado
                person = 'o associado';

                break;
            }

            questionDialog(
              action.charAt(0).toUpperCase() + action.slice(1), ''
                + 'Você deseja realmente ' + action + ' ' + person
                + ' <b>&ldquo;' + customer.name
                + '&rdquo;</b>' + complement + '?',
              function() {
                // Alternar o bloqueio da unidade/filial do cliente
                // selecionado
                var url;

                switch (customer.level) {
                  case 0:
                  case 2:
                    // O cliente
                    url = "{{ buildURL(toggleBlocked.URL, {'customerID': 'customer.id'}) }}";

                    break;
                  case 1:
                    // A unidade/filial
                    url = "{{ buildURL(toggleBlocked.URL, {'customerID': 'customer.id', 'subsidiaryID': 'customer.subsidiaryid'}) }}";

                    break;
                  case 3:
                    // O associado
                    url = "{{ buildURL(toggleBlocked.URL, {'customerID': 'customer.affiliatedid'}) }}";

                    break;
                }

                putJSONData(url, [],
                  function (data, params, message) {
                    // Atualiza a tabela
                    searchLoad();
                  }
                );
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
            /**{% endif %}**/

            break;
          case 17:
            // Remover unidade/filial
            // Não faz nada
            break;
          default:
            if (originalEvent.target.classList.contains('copy')) {
              // Copia a informação para a área de transferência
              var
                label = (index.column == 13)
                  ? customer.nationalregister.replace(/\D/g, "")
                  : customer.name
              ;

              copyTextToClipboard(label);
              
            } else {
              /**{% if authorization.getAuthorizationFor(edit.URL, edit.method) %}**/
              var
                customerID = (customer.affiliatedid > 0)
                  ? customer.affiliatedid
                  : customer.id
              ;
              // Coloca o cliente em edição
              window.location.href = "{{ buildURL(edit.URL, { 'customerID': 'customerID' }) }}";
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
  }, 700);
});


// ================================================[ Handlers ]=====

// Faz o tratamento da seleção do filtro dos dados
function filterTypeHandler() {
  searchLoad();
}

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
  }, 600);
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
  event.preventDefault();

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
