var
  table,
  EquipmentSVG = '<svg style="position: relative; top: 2px; margin: auto; text-align: center;" width="20px" height="20px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40"><path d="M 19.996546, 0.7644605 2.6462706, 5.9771679 C 3.0718364,18.8823157 6.3104161,34.625339 19.996546,39.229436 33.833327,34.757043 36.759467,18.7450662 37.346821, 5.9771679 Z" fill="#c1#" stroke="#c2#" stroke-width="1"></path><text y="20" x="20" style="-inkscape-font-specification:\'Cantarell, Bold\';font-family:Cantarell;font-feature-settings:normal;font-size:30px;font-variant-caps:normal;font-variant-ligatures:normal;font-variant-numeric:normal;font-weight:bold;letter-spacing:0px;line-height:1;text-align:center;text-anchor:middle;word-spacing:0px"><tspan y="30" x="20">#count#</tspan></text></svg>'
;

// O template de desinstalação
/**
{% set uninstallTemplate %}
  {% include 'erp/cadastre/vehicles/uninstalldialog.twig' with { deposits: deposits, groupid: authorization.user.groupid, defaultDepositID: defaultDepositID, technicians: technicians }  %}
{% endset %}
**/
var
  uninstallTemplate = "{{ uninstallTemplate|escape('js') }}".replace(/&lt;/g,'<').replace(/&gt;/g,'>')
;

// O template de mudança do estado do monitoramento
/**
{% set monitoredTemplate %}
  {% include 'erp/cadastre/vehicles/monitoreddialog.twig' %}
{% endset %}
**/
var
  monitoredTemplate = "{{ monitoredTemplate|escape('js') }}".replace(/&lt;/g,'<').replace(/&gt;/g,'>')
;

// Os formatadores de colunas

// Um formatador de colunas para exibir um ícone antes do nome de um
// associado de uma associação
var nameFormatter = function ( value, type, row ) {
  var
    icon  = 'fas fa-house-user',
    entityType  = 'Cliente',
    label = value,
    copyBtn = '&nbsp;'
      + '<span data-position="right center"'
      +       'data-blue data-inverted '
      +       'data-tooltip="Clique para copiar para a área de transferência">'
      +   '<i class="copy outline grey icon"></i>'
      + '</span>',
    buttons = ''
  ;

  switch (row.level) {
    case 0:
      if (row.cooperative) {
        icon = 'fas fa-clinic-medical blue';
        entityType = 'Associação';
      } else {
        icon = 'fas fa-house-user';
        entityType = 'Cliente';
      }
      buttons = copyBtn;

      break;
    case 1:
      if (row.juridicalperson) {
        icon = 'plus square grey icon';
        if (row.headoffice) {
          entityType = 'Matriz';
        } else {
          entityType = 'Unidade/filial';
        }
      } else {
        icon = 'fas fa-user';
        if (row.headoffice) {
          entityType = 'Titular';
        } else {
          entityType = 'Dependente';
        }
      }
      buttons = copyBtn;

      break;
    case 2:
      if (row.cooperative) {
        icon = 'fas fa-clinic-medical blue';
        entityType = 'Associação';
      } else {
        icon = 'fas fa-house-user';
        entityType = 'Cliente';
      }
      buttons = copyBtn;

      break;
    case 3:
    case 5:
      icon = 'fas fa-house-user';
      icon = 'check circle orange icon';
      entityType = 'Associado';
      buttons = copyBtn;

      break;
    case 4:
      if (row.juridicalperson) {
        icon = 'chevron right grey icon';
        if (row.headoffice) {
          entityType = 'Matriz';
        } else {
          entityType = 'Unidade/filial';
        }
      } else {
        icon = 'fas fa-user';
        if (row.headoffice) {
          entityType = 'Titular';
        } else {
          entityType = 'Dependente';
        }
      }
      buttons = copyBtn;

      break;
    case 6:
      icon = 'plus square outline silver icon';
      entityType = 'Veículo';
      buttons = copyBtn;

      break;
    default:
      icon = 'fas fa-info-circle';
      entityType = 'Desconhecido';
  }

  if (row.tradingname) {
    // Acrescenta o apelido ou nome fantasia do cliente
    label += ' '
      + '<i style="color: #0073cf; font-weight: bold;"> [ '
      + row.tradingname
      + ' ]</i>'
    ;
  }

  return ''
    + '<span data-position="right center"'
    +       'data-blue data-inverted '
    +       'data-tooltip="' + entityType + '">'
    +   '<i class="' + icon + '"></i>'
    + '</span>' + label
    + buttons
  ;
};

// Um formatador de colunas para exibir um ícone antes da placa para
// informar que o valor pode ser copiado
var plateFormatter = function ( value ) {
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

// Um formatador de colunas para exibir a existência de equipamentos
// associados e permitir o controle sobre sua exibição
var equipmentFormatter = function ( value ) {
  var
    blue1 = '#98ccfd',
    blue2 = '#2a5b8a',
    gray1 = '#cccccc',
    gray2 = '#4d4d4d',
    manageText = '',
    tooltip
  ;
  /**{% if authorization.user.groupid < 6 %}**/
  manageText = " Clique para gerenciar.";
  /**{% endif %}**/
  
  if (value > 0) {
    if (value > 1) {
      tooltip = "Temos " + value + " equipamentos associados à este veículo." + manageText;
    } else {
      tooltip = "Temos " + value + " equipamento associado à este veículo." + manageText;
    }

    return ''
      + '<span data-position="left center"'
      +       'data-blue data-inverted '
      +       'data-tooltip="' + tooltip + '">'
      +   EquipmentSVG
            .replace('#c1#', blue1)
            .replace('#c2#', blue2)
            .replace('#count#', value)
      + '</span>'
    ;
  }
  
  tooltip = "Não temos equipamentos associados." + manageText;
  return ''
    + '<span data-position="left center"'
    +       'data-blue data-inverted '
    +       'data-tooltip="' + tooltip + '">'
    +   EquipmentSVG
          .replace('#c1#', gray1)
          .replace('#c2#', gray2)
          .replace('#count#', '-')
    + '</span>'
  ;
};

// Um formatador (render) de coluna para exibir o texto indicativo
// de situação do cadastro
var activeFormatter = function ( value, type, row ) {
  // Verifica se foi solicitada a exibição do valor
  if ( type === 'display' ) {
    if (row.active === false) {
      // Retornamos um texto indicando que está inativo
      return '<span class="status" style="background-color: gray;">' +
               'Inativo' +
             '</span>';
    } else {
      if (row.blockedlevel > 0) {
        // Retornamos um texto indicando que está inativo
        return ''
          + '<span class="status" style="background-color: #c81120;">'
          +   'Bloqueado'
          + '</span>';
      } else {
        if (row.withoutmaintracker) {
          // Retornamos um texto indicando que está ativo, porém sem
          // o rastreador principal
          return ''
            + '<span class="status" style="background-color: #4a9c35;">'
            +   'Ativo'
            + '</span>';
        } else {
          // Retornamos um texto indicando que está ativo
          return ''
            + '<span class="status" style="background-color: #4a9c35;">'
            +   'Ativo'
            + '</span>';
        }
      }
    }
  }

  // Pesquisas, ordenamentos e tipos podem usar os dados originais
  return value;
};


// Um formatador (render) de coluna para exibir as informações de
// e-mails enviados
var sentByMailFormatter = function( title ) {
  return function ( value, type, row, meta ) {
    // Verifica se foi solicitada a exibição do valor
    if ( type === 'display' ) {
      var
        mailStatus = JSON.parse(value)
      ;

      return ''
        + '<div class="ui large label" data-inverted="" '
        +      'data-position="left center" data-blue '
        +      'data-tooltip="' + (mailStatus[0]>0?mailStatus[0]+(mailStatus[0]>1?' emails':' email')+' aguardando na fila de envio':'Nenhum e-mail para enviar') + '">'
        +   '<i class="mail ' + (mailStatus[0]>0?'orange':'silver') + ' icon"></i>' + (mailStatus[0]>0?mailStatus[0]:'&nbsp;&nbsp;&nbsp;')
        + '</div>'
        + '<div class="ui large label" data-inverted="" '
        +      'data-position="left center" data-blue '
        +      'data-tooltip="' + (mailStatus[1]>0?mailStatus[1]+(mailStatus[1]>1?' emails enviados ':' email enviado ')+' com sucesso':'Nenhum e-mail enviado') + '">'
        +   '<i class="mail ' + (mailStatus[1]>0?'green':'silver') + ' icon"></i>' + (mailStatus[1]>0?mailStatus[1]:'&nbsp;&nbsp;&nbsp;')
        + '</div>'
        + '<div class="ui large label" data-inverted="" '
        +      'data-position="left center" data-blue '
        +      'data-tooltip="' + (mailStatus[2]>0?mailStatus[2]+(mailStatus[2]>1?' emails que não foram entregues':' email que não foi entregue'):'Nenhum e-mail com falha no envio') + '">'
        +   '<i class="mail ' + (mailStatus[2]>0?'red':'silver') + ' icon"></i>' + (mailStatus[2]>0?mailStatus[2]:'&nbsp;&nbsp;&nbsp;')
        + '</div>'
      ;
    }

    // Pesquisas, ordenamentos e tipos podem usar os dados originais
    return value;
  }
};

// Um formatador (render) de coluna para exibir o ícone indicativo de
// gerar um PDF
var pdfIconFormatter = function ( value, type, row ) {
  // Verifica se foi solicitada a exibição do valor
  if ( type === 'display' ) {
    var
      tooltip,
      menu = ''
    ;

    switch (row.type) {
      case 1:
      case 2:
        tooltip = "Permite gerar um PDF para impressão da relação "
          + "de veículos deste cliente"
        ;
        menu = ''
          + '<div class="floating options inactive">'
          +   '<div class="ui compact horizontal labeled icon menu">'
          +     '<a class="item" onclick="vehicleList(this, ' + row.customerid + ', ' + row.subsidaryid + ', \'vehicleList\')">'
          +       '<i class="file pdf black icon"></i>'
          +       'Relação de veículos'
          +     '</a>'
          +     '<a class="item" onclick="vehicleList(this, ' + row.customerid + ', ' + row.subsidaryid + ', \'equipmentList\')">'
          +       '<i class="file pdf black icon"></i>'
          +       'Veículos rastreados'
          +     '</a>'
          +     '<p class="item">'
          +       '<i class="close icon"></i>'
          +     '</p>'
          +   '</div>'
          + '</div>'
        ;

        break;
      case 3:
        tooltip = "Permite gerar um PDF para impressão dos dados "
          + "cadastrais do veículo"
        ;

        break;
    }

    return ''
      + '<span data-position="left center"'
      +       'data-blue data-inverted '
      +       'data-tooltip="' + tooltip + '">'
      +   '<i class="file pdf black icon">'
      +   '</i>'
      + '</span>' + menu
    ;
  }

  // Pesquisas, ordenamentos e tipos podem usar os dados originais
  return value;
};

// Um formatador para exibir um ícone indicativo de que o veículo
// está ou não sendo monitorado
var monitoredFormatter = function ( value, type, row ) {
  var buildIcon = function (color, icon, title) {
    return ''
      + '<span data-position="left center"'
      +       'data-blue data-inverted '
      +       'data-tooltip="' + title + '">'
      +   '<i class="' + icon + ' ' + color + ' icon"></i>'
      + '</span>'
    ;
  };

  if (row.hasmonitoring === true) {
    switch (row.type) {
      case 1:
        const label = row.level == 1
          ? 'a unidade/filial do cliente'
          : 'e cliente'

        return buildIcon('low vision', 'blue', ''
          + 'Alterna o monitoramento dos veículos dest' + label
        );

        break;
      case 2:
        return buildIcon('low vision', 'teal', ''
          + 'Alterna o monitoramento dos veículos deste associado'
        );

        break;
      default:
        if (value) {
          // Está sendo monitorado
          return buildIcon('eye', 'darkorange', ''
            + 'Veículo sendo monitorado'
          );
        } else {
          // Não está sendo monitorado
          return buildIcon('eye slash', 'lightgrey', ''
            + 'Veículo não está sendo monitorado'
          );
        }
    }
  } else {
    // Não possui monitoramento
    return buildIcon('minus', 'lightgrey', ''
      + 'Cliente não possui monitoramento'
    );
  }
};

// Um formatador para exibir um ícone indicativo de que o veículo
// está ou não bloqueado
var blockedFormatter = function ( value, type, row ) {
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
      // O cliente está bloqueado
      return buildIcon('lock', 'darkred', ''
        + 'A conta deste cliente está bloqueada')
      ;

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
        + 'A conta d' + person + ' está bloqueada')
      ;

      break;
    case 3:
    case 4:
      // A conta do cliente ao qual o veículo está vinculada está
      // bloqueada
      return buildIcon('lock', 'darkorange', ''
        + 'A conta deste cliente está bloqueada'
      );

      break;
    case 5:
      // O veículo está bloqueado
      return buildIcon('lock', 'darkorange', ''
        + 'A conta deste veículo está bloqueado'
      );

      break;
    default:
      // Está livre
      return buildIcon('unlock', 'lightgrey', ''
        + 'Nenhum bloqueio nesta conta'
      );
  }
};

// Um formatador (render) de coluna para exibir o icone permitindo
// a remoção de veículo
var deleteFormatter = function ( value, type, row ) {
  var
    tooltip = 'O veículo placa ' + row.name + ' não pode ser '
      + 'removido',
    color = 'lightgrey'
  ;

  // Verifica se foi solicitada a exibição do valor
  if ( type === 'display' ) {
    if (row.type === 3) {
      if (row.active === false) {
        // Permitimos a remoção do veículo
        color = 'darkred';
        tooltip = 'Clique para remover o veículo placa ' + row.name;
      }

      return ''
        + '<span data-position="left center"'
        +       'data-blue data-inverted '
        +       'data-tooltip="' + tooltip + '">'
        +   '<i class="remove ' + color + ' icon">'
        +   '</i>'
        + '</span>'
      ;
    } else {
      return '';
    }
  }

  // Pesquisas, ordenamentos e tipos podem usar os dados originais
  return value;
};

// Um formatador para a linha de detalhamento de equipamentos de
// rastreamento
function formatEquipmentList(data, row) {
  var
    detail = '<div class="slider">',
    newEquipment = function () {
      // Cria uma representação vazia, permitindo ao usuário associar
      // um equipamento ao veículo
      return ''
        + '<div class="three wide column">'
        /**{% if authorization.getAuthorizationFor(attachEquipment.URL, attachEquipment.method) %}**/
        +   '<table cellspacing="5px" cellpadding="5px" class="compact child table">'
        +     '<tr>'
        +       '<td class="clean">'
        +         '<button class="ui compact icon primary button"' 
        +                 'data-blue data-inverted '
        +                 'data-tooltip="Clique para associar um novo equipamento"'
        +                 'data-position="top left"'
        +                 'onclick="attach(' + data.id + ',' + row +')">'
        +           '<i class="plus icon"></i>'
        +         '</button>'
        +       '</td>'
        +     '</tr>'
        +   '</table>'
        /**{% endif %}**/
        + '</div>'
      ;
    }
  ;

  if (Array.isArray(data.equipmentdata)) {
    if (data.equipmentdata.length > 0) {
      for (var count=0; count < data.equipmentdata.length; count++) {
        // Criamos uma representação dos dados do SIM Card neste
        // slot do equipamento, permitindo ao usuário visualizar
        // suas informações básicas
        var
          equipmentData = data.equipmentdata[count],
          installationSite = equipmentData.installationsite === null
            ? '-'
            : equipmentData.installationsite,
          blockingSite = equipmentData.blockingsite === null
            ? '-'
            : equipmentData.blockingsite,
          sirenSite = equipmentData.sirensite === null
            ? '-'
            : equipmentData.sirensite,
          panicButtonSite = equipmentData.panicbuttonsite === null
            ? '-'
            : equipmentData.panicbuttonsite,
          payer = '',
          color = (equipmentData.main)
            ? 'lightcyan'
            : 'lightorange'
        ;

        if ((data.customerid !== equipmentData.customerpayerid) ||
            (data.subsidiaryid !== equipmentData.subsidiarypayerid)) {
          // Incluimos os dados do pagante
          payer = ''
            + '<td class="clean content divided">'
            +   '<span class="title">'
            +     'Informações do pagante'
            +   '</span>'
            +   '<div class="description">'
            +     '<p>'
            +       '<span class="model">'
            +         equipmentData.customerpayername
            +       '</span>'
            +       '<br>'
            +       '<span class="model">'
            +         equipmentData.subsidiarypayername
            +       '</span>'
            +       '<br>'
            +       '<span class="model">'
            +         equipmentData.nationalregister
            +       '</span>'
            +       '<br>'
            +     '</p>'
            +   '</div>'
            + '</td>'
          ;
        }
        
        detail += ''
          + '<div class="three wide column">'
          +   '<table cellspacing="5px" cellpadding="5px" class="compact child table">'
          +     '<tr>'
          +       '<td class="clean ' + color + '">'
          +         (count + 1)
          +       '</td>'
          +       '<td class="clean content divided">'
          +         '<a class="header">N&ordm; de série: '
          +           equipmentData.serialnumber
          +         '</a>'
          +         '<div class="description">'
          +           '<p>'
          +             '<span class="model">'
          +               equipmentData.brandname + ' / '
          +               equipmentData.modelname
          +             '</span>'
          +             '<br>'
          + ( (equipmentData.imei !== null)
                ? 'IMEI: ' + equipmentData.imei
                : '&nbsp;')
          +             '<br>'
          +             ((equipmentData.main)
                          ? '<span class="model role main">'
                            + 'Rastreador principal'
                            + '</span>'
                          : '<span class="model role contingency">'
                            + 'Rastreador de contingência'
                            + '</span>')
          +             '<br>'
          +             '<span class="model">'
          +               'Instalado em ' + equipmentData.installedat
          +             '</span>'
          +             '<br>'
          +             '<span class="model">'
          +               '<i class="tag blue icon"></i> ' + equipmentData.installationnumber
          +             '</span>'
          +           '</p>'
          +         '</div>'
          +       '</td>'
          +       '<td class="clean content divided">'
          +         '<span class="title">'
          +           'Local de instalação'
          +         '</span>'
          +         '<div class="description">'
          +           '<p>'
          +             '<span class="field">'
          +               'Rastreador:&nbsp;'
          +             '</span>'
          +             '<span class="site">'
          +               installationSite
          +             '</span>'
          +             '<br>'
          +             '<span class="field">'
          +               'Bloqueio:&nbsp;'
          +             '</span>'
          +             '<span class="site">'
          +               blockingSite
          +             '</span>'
          + ( (equipmentData.hasibutton)
                ? '<br>'
                + '<span class="field">'
                +   'Leitora de iButton:&nbsp;'
                + '</span>'
                + '<span class="site">'
                +   equipmentData.ibuttonsite
                + '</span>'
                : '')
          +             '<br>'
          +             '<span class="field">'
          +               'Sirene:&nbsp;'
          +             '</span>'
          +             '<span class="site">'
          +               sirenSite
          +             '</span>'
          +             '<br>'
          +             '<span class="field">'
          +               'Botão de pânico:&nbsp;'
          +             '</span>'
          +             '<span class="site">'
          +               panicButtonSite
          +             '</span>'
          +             '<br>'
          +           '</p>'
          +         '</div>'
          +       '</td>'
          +       payer
          +       '<td class="buttons">'
          +         '<button class="ui compact icon primary button"'
          +                 'data-blue data-inverted '
          +                 'data-tooltip="Clique para editar as informações da associação deste equipamento"'
          +                 'data-position="top right"'
          +                 'onclick="reattach(event, ' + equipmentData.vehicleid + ', ' + equipmentData.equipmentid + ', ' + row +')">'
          +           '<i class="pen alternate icon"></i>'
          +         '</button>'
          +         '<button class="ui compact icon youtube button"'
          +                 'data-blue data-inverted '
          +                 'data-tooltip="Clique para desassociar este equipamento"'
          +                 'data-position="top right"'
          +                 'onclick="detach(event, ' + equipmentData.equipmentid + ', ' + row +')">'
          +           '<i class="close icon"></i>'
          +         '</button>'
          +       '</td>'
          +     '</tr>'
          +   '</table>'
          + '</div>'
        ;
      }

      // Por último, acrescentamos a possíbilidade de associarmos
      // novos equipamentos
      detail += newEquipment();
    } else {
      // Criamos uma representação vazia, permitindo ao usuário
      // associar um equipamento ao veículo
      detail += ''
        + '<div class="three wide column">'
        +   '<table cellspacing="5px" '
        +          'cellpadding="5px" '
        +          'class="compact child table">'
        +     '<tr>'
        +       '<td class="clean lightgrey">-</td>'
        +       '<td class="clean content">'
        +         '<a class="header">< Não rastreado ></a>'
        +         '<div class="description">'
        +           '<p>Nenhum equipamento associado</p>'
        +         '</div>'
        +       '</td>'
        +     '</tr>'
        +   '</table>'
        + '</div>'
      ;
      
      detail += newEquipment();
    }
  } else {
    // Criamos uma representação vazia
    detail += '<p>Sem informações</p>';
  }
  
  detail += '</div>';
  
  return detail;
}

// Um formatador de colunas para exibir o tipo de veículo
var typeFormatter = function ( value, type, row ) {
  if (row.vehiclesubtypeid > 0) {
    // Exibe o subtipo de veículo
    return row.vehiclesubtypename;
  }

  return row.vehicletypename;
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

  if (errorMessage === 'Não temos veículos cadastrados.') {
    // Primeiro verifica se temos ao menos um cliente cadastrado e
    // ativo antes de prosseguir
    var
      url  = "{{ path_for(hasOneOrMoreCustomer.URL) }}"
    ;

    getJSONData(url, { },
    function (data)
    {
      if (parseInt(data) > 0) {
        // Informa de maneira didática que não temos veículos
        // cadastrados e pergunta ao usuário se o mesmo deseja
        // cadastrar um neste momento
        questionDialog('Gerenciamento de veículos', ''
          + 'Ainda não há veículos cadastrado no sistema. Deseja '
          + 'cadastrar um novo veículo agora?<br>'
          + '<span style="color: #89afd7;">Caso você não queira '
          + 'fazer isto agora, pode cadastrá-lo em outro momento.'
          + '<span>',
          function() {
            // Permite adicionar um novo veículo
            var
              url  = "{{ path_for(add.URL) }}"
            ;

            window.location.href = url;
          });
      } else {
        // Informa de maneira didática que não temos veículos nem
        // clientes cadastrados e pergunta ao usuário se o mesmo
        // deseja cadastrar um cliente neste momento
        questionDialog('Gerenciamento de veículos', ''
          + 'Ainda não há nem veículos ou clientes cadastrado no '
          + 'sistema. Porém, antes de cadastrar um novo veículo, '
          + 'você precisa ter clientes aos quais irá associá-lo. '
          + 'Deseja cadastrar um novo cliente agora?<br>'
          + '<span style="color: #89afd7;">Caso você não queira '
          + 'fazer isto agora, pode cadastrá-lo em outro momento.'
          + '<span>',
          function() {
            // Permite adicionar um novo cliente
            var
              url  = "{{ path_for(addCustomer.URL) }}"
            ;

            window.location.href = url;
          });
      }
    },
    function (data, params, message)
    {
      if (typeof(message) != 'undefined' && message != null) {
        if (message.length > 38) {
          // Exibe a mensagem de erro
          warningDialog('Desculpe', message.substring(38));
        }
      } else {
        // Exibe a mensagem de erro
        warningDialog('Desculpe', 'Erro desconhecido');
      }
    });
  } else {
    // Exibe a mensagem de erro
    if (errorMessage.length > 0) {
      warningDialog('Desculpe', errorMessage);
    }
  }
};

$(document).ready(function() {
  // --------------------------------[ Componentes da Searchbar ]---
  $('.searchbar .ui.dropdown')
    .dropdown()
  ;
  $('#filterType')
    .change(filterTypeHandler)
  ;
  /**{% if authorization.user.groupid < 6 %}**/
  $("input[name='customerName']")
    .autocomplete(customerNameCompletionOptions)
  ;
  /**{% endif %}**/
  $('.toggle .ui.checkbox')
    .checkbox()
  ;
  $('#searchValue')
    .keypress(forceSearch)
  ;

  // Lida com o pressionamento de teclas
  $(document).on('keydown',function(event) {
    if (event.key === 'Escape') {
      // Limpa os dados do formulário
      $('#searchValue').val('');
      $("input[name='customerID']").val(0);
      $("input[name='customerName']").val('');
      $("input[name='subsidiaryID']").val(0);
      $('input[name="filters[]"]:checked').each(function() {
        $(this).prop('checked', false);
      });

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
      url: "{{ i18n('datatables/plugins/i18n/Portuguese-Brasil.json') }}",
    },
    columnDefs: [
      { "targets": 0,
        "name": "id",
        "data": "id",
        "visible": false,
        "orderable": false },
      { "targets": 1,
        "name": "customerid",
        "data": "customerid",
        "visible": false,
        "orderable": false },
      { "targets": 2,
        "name": "subsidiaryid",
        "data": "subsidiaryid",
        "visible": false,
        "orderable": false },
      { "targets": 3,
        "name": "associationid",
        "data": "associationid",
        "visible": false,
        "orderable": false },
      { "targets": 4,
        "name": "associationunityid",
        "data": "associationunityid",
        "visible": false,
        "orderable": false },
      { "targets": 5,
        "name": "hasmonitoring",
        "data": "hasmonitoring",
        "visible": false,
        "orderable": false },
      { "targets": 6,
        "name": "juridicalperson",
        "data": "juridicalperson",
        "visible": false,
        "orderable": false },
      { "targets": 7,
        "name": "cooperative",
        "data": "cooperative",
        "visible": false,
        "orderable": false },
      { "targets": 8,
        "name": "headoffice",
        "data": "headoffice",
        "visible": false,
        "orderable": false },
      { "targets": 9,
        "name": "type",
        "data": "type",
        "visible": false,
        "orderable": false },
      { "targets": 10,
        "name": "level",
        "data": "level",
        "visible": false,
        "orderable": false },
      { "targets": 11,
        "name": "activeassociation",
        "data": "activeassociation",
        "visible": false,
        "orderable": false },
      { "targets": 12,
        "name": "NULL",
        "data": "name",
        "visible": true,
        "orderable": false,
        "render": nameFormatter },
      { "targets": 13,
        "name": "tradingname",
        "data": "tradingname",
        "visible": false,
        "orderable": false },
      { "targets": 14,
        "name": "vehiclebrandname",
        "data": "vehiclebrandname",
        "visible": true,
        "orderable": false,
        "width": "30%" },
      { "targets": 15,
        "name": "vehiclemodelname",
        "data": "vehiclemodelname",
        "visible": true,
        "width": "50%",
        "orderable": false },
      { "targets": 16,
        "name": "vehicletypename",
        "data": "vehicletypename",
        "visible": false,
        "orderable": false },
      { "targets": 17,
        "name": "vehiclesubtypename",
        "data": "vehiclesubtypename",
        "visible": false,
        "orderable": false },
      { "targets": 18,
        "name": "vehiclesubtypeid",
        "data": "vehiclesubtypeid",
        "visible": true,
        "orderable": false,
        "render": typeFormatter },
      { "targets": 19,
        "name": "vehiclecolor",
        "data": "vehiclecolor",
        "className": "dt-center",
        "visible": true,
        "orderable": false,
        "width": "10px",
        "render": colorFormatter },
      { "targets": 20,
        "name": "active",
        "data": "active",
        "className": "dt-center",
        "visible": true,
        "orderable": false,
        "render": activeFormatter },
      { "targets": 21,
        "name": "amountofequipments",
        "data": "amountofequipments",
        "visible": true,
        "className": "dt-center details-control",
        "width": "10px",
        "orderable": false,
        "render": equipmentFormatter },
      { "targets": 22,
        "name": "equipmentdata",
        "data": "equipmentdata",
        "visible": false,
        "orderable": false },
      { "targets": 23,
        "name": "sentmailstatus",
        "data": "sentmailstatus",
        "className": "dt-center",
        "visible": true,
        "orderable": false,
        "width": "10px",
        "render": sentByMailFormatter("Cobrança enviada por e-mail") },
      { "targets": 24,
        "name": "monitored",
        "data": "monitored",
        "className": "dt-center",
        "visible": true,
        "orderable": false,
        "width": "10px",
        "render": monitoredFormatter },
      { "targets": 25,
        "name": "pdf",
        "data": "id",
        "className": "dt-center",
        "visible": true,
        "orderable": false,
        "width": "10px",
        "render": pdfIconFormatter },
      { "targets": 26,
        "name": "blockedlevel",
        "data": "blockedlevel",
        "className": "dt-center",
        "visible": true,
        "orderable": false,
        "width": "10px",
        "render": blockedFormatter },
      { "targets": 27,
        "name": "delete",
        "data": "id",
        "visible": "{% if authorization.getAuthorizationFor(remove.URL, remove.method) %}true{% else %}false{% endif %}"=="true",
        "orderable": false,
        "width": "10px",
        "render": deleteFormatter },
      { "targets": 28,
        "name": "withoutmaintracker",
        "data": "withoutmaintracker",
        "visible": false,
        "orderable": false },
      { "targets": 29,
        "name": "ownername",
        "data": "ownername",
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
    displayStart: parseInt('{{ vehicle.displayStart|default("0") }}'),
    ajax: {
      url: "{{ path_for(getData.URL) }}",
      type: "{{ getData.method }}",
      data: function ( params ) {
        params.searchValue  = $('#searchValue').val();
        params.searchField  = $('#searchField').val();
        params.customerID   = $("input[name='customerID']").val();
        params.customerName = $("input[name='customerName']").val();
        params.subsidiaryID = $("input[name='subsidiaryID']").val();
        params.filterType   = $('#filterType').val();
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
    createdRow: function(row, data) {
      if (data.level < 6) {
        // Expande a coluna
        $('td:eq(0)', row)
          .attr('colspan', 7)
          .attr('width', '95%')
        ;
        $('td:eq(1)', row).css('display', 'none');
        $('td:eq(2)', row).css('display', 'none');
        $('td:eq(3)', row).css('display', 'none');
        $('td:eq(4)', row).css('display', 'none');
        $('td:eq(5)', row).css('display', 'none');
        $('td:eq(6)', row).css('display', 'none');
        //$('td:eq(7)', row).css('display', 'none');
      }

      // Deixa as linhas com cores diferentes para indicar bloqueio
      if ( (data.level == 6) && (data.active == false) ) {
        // Adiciona o estado de inativo
        if (data.blockedlevel > 0) {
          $(row).addClass('blocked');
        }

        $(row).addClass('inactived');
      } else {
        if (data.blockedlevel > 0) {
          $(row).addClass('blocked');
        } else {
          switch (data.level) {
            case 0:
            case 3:
              $(row).addClass('grouped');

              break;
            case 3:
            case 4:
            case 5:
              $(row).addClass('affiliated');

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
    .on('user-select', function (e, dt, type, cell, originalEvent) {
      if (type === 'cell') {
        var
          // Recupera os dados da célula selecionada
          index   = cell[0][0],

          // Recupera os dados do veículo selecionado
          vehicle = dt.rows( index.row ).data().toArray()[0]
        ;

        // Em função da coluna onde ocorreu o clique, executa a ação
        // correspondente
        switch (index.column) {
          case 21:
            /**{% if authorization.user.groupid < 6 %}**/
            // Recuperamos a informação de detalhamento
            var
              child = dt.row( index.row ).child
            ;
            
            // Estamos lidando com o detalhamento de equipamentos
            // de rastreamento
            if (child.isShown()) {
              // O detalhamento desta linha já está sendo exibido,
              // então apenas ocultamos
              child.hide();
            } else {
              // Verifica se já temos a linha de detalhamento
              // definida e exibe as informações de rastreadores
              if (child() && child().length) {
                child
                  .show()
                ;
              } else {
                child(formatEquipmentList(vehicle, index.row), 'no-padding')
                  .show()
                ;
              }
            }
            /**{% endif %}**/

            break;
          case 23:
            // Situação do envio do e-mail
            showMailStatus(
              vehicle.id,
              vehicle.name,
              vehicle.customerid,
              vehicle.ownername,
              vehicle.sentmailstatus
            );

            break;
          case 24:
            /**{% if authorization.getAuthorizationFor(toggleMonitored.URL, toggleMonitored.method) %}**/
            if (vehicle.hasmonitoring === true) {
              if (vehicle.type == 3) {
                // Alterna o monitoramento do veículo
                var
                  action = vehicle.monitored
                    ? "desativar"
                    : "ativar"
                ;

                questionDialog(
                  action.charAt(0).toUpperCase() + action.slice(1) + ' o monitoramento',
                  'Você deseja realmente ' + action + ' o monitoramento do veículo placa ' +
                  '<b>&ldquo;' + vehicle.name + '&rdquo;</b>?',
                  function() {
                    // Alternar o monitoramento do veículo
                    var
                      url  = "{{ buildURL(toggleMonitored.URL, {'vehicleID': 'vehicle.id'}) }}"
                    ;

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
              } else {
                // Lida com a mudança da situação do monitoramento dos
                // veículos do cliente/associado
                interactionDialog(
                  'Alterar situação do monitoramento',
                  monitoredTemplate,
                  function() {
                    $("input[name='monitored']")
                      .focus()
                    ;
                  },
                  function() {
                    // Confirmou a desinstalação, então repassa as informações
                    var
                      monitoredState = $('.monitored')
                        .find('[name="monitoredState"]:checked')
                        .val(),
                      action = monitoredState == 'enabled' ? 1 : 0,
                      customerid = (vehicle.type = 1 && vehicle.cooperative == true)
                        ? 0
                        : vehicle.customerid,
                      unityid = (vehicle.type = 1 && vehicle.cooperative == true)
                        ? 0
                        : (vehicle.subsidiaryid ?? 0),
                      associationid = (vehicle.type = 1 && vehicle.cooperative == true)
                        ? vehicle.customerid
                        : (vehicle.associationid ?? 0),
                      associationunityid = (vehicle.type = 1 && vehicle.cooperative == true)
                        ? (vehicle.subsidaryid ?? 0)
                        : (vehicle.associationunityid ?? 0)
                    ;
              
                    var
                      url = "{{ buildURL(toggleMonitored.URL, {'vehicleID': 'action', 'customerID':'customerid', 'subsidiaryID': 'unityid', 'associationID': 'associationid', 'associationUnityID': 'associationunityid' }) }}"
                    ;
                    console.log('url', url);
                    putJSONData(url, [],
                      function () {
                        // Atualiza a tabela
                        searchLoad();
                      }
                    );
                  },
                  function() {
                    // Cancelou, então ignora
                  }
                );
              }
            }
            /**{% endif %}**/

            break;
          case 25:
            /**{% if authorization.getAuthorizationFor(getPDF.URL, getPDF.method) %}**/
            if (vehicle.type == 3) {
              // Gera um PDF com as informações do veículo deste
              // cliente
              var
                customerID   = vehicle.customerid,
                subsidiaryID = vehicle.subsidiaryid,
                vehicleID    = vehicle.id,
                url = "{{ buildURL(getPDF.URL, {'customerID': 'customerID', 'subsidiaryID': 'subsidiaryID', 'vehicleID': 'vehicleID'}) }}",
                myHeaders = new Headers()
              ;

              // Incluímos o cabeçalho para indicar que não desejamos que o conteúdo
              // seja cacheável
              myHeaders.append('pragma', 'no-cache');
              myHeaders.append('cache-control', 'no-cache');

              fetch(url, { method: 'GET', headers: myHeaders })
                .then(function(response) {
                  if (response.ok) {
                    var
                      disposition = response.headers.get('Content-Disposition'),
                      parts = disposition.split(';'),
                      filename = parts[0].split('=')[1].replaceAll("'", "")
                    ;
                    response.blob().then(function(myBlob) {
                      download(myBlob, filename, 'application/octet-stream');
                    });
                  } else {
                    switch (response.status) {
                      case 404:
                      case 406:
                        response.json().then(function(json) {
                          infoDialog(
                            'Arquivo PDF',
                            json.message
                          );
                        });

                        break;
                      default:
                        response.text().then(function(text) {
                          infoDialog(
                            'Arquivo PDF',
                            text
                          );
                        });
                    }
                  }
                })
              ;
            } else {
              var
                element = $(originalEvent.target)
              ;

              if ( element.hasClass('file pdf') ||
                   element.is('td') ) {
                var
                  cell = $(originalEvent.currentTarget),
                  menu = cell.children('div.floating.options')
                ;

                if (menu.hasClass('inactive')) {
                  menu
                    .removeClass('inactive')
                  ;
                  menu
                    .find('i.close')
                    .on('click', closeMenu)
                  ;
                }
              }
            }
            /**{% endif %}**/

            break;
          case 26:
            if (vehicle.type == 3) {
              /**{% if authorization.getAuthorizationFor(toggleBlocked.URL, toggleBlocked.method) %}**/
              // Alterna o bloqueio da conta do veículo
              var
                action = vehicle.blocked
                  ? "ativar"
                  : "inativar"
              ;

              questionDialog(
                action.charAt(0).toUpperCase() + action.slice(1),
                'Você deseja realmente ' + action + ' o veículo placa ' +
                '<b>&ldquo;' + vehicle.name + '&rdquo;</b>?',
                function() {
                  // Alternar o bloqueio da conta do veículo
                  var
                    url  = "{{ buildURL(toggleBlocked.URL, {'vehicleID': 'vehicle.id'}) }}"
                  ;

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
              /**{% endif %}**/
            }

            break;
          case 27:
            if (vehicle.type == 3) {
              /**{% if authorization.getAuthorizationFor(remove.URL, remove.method) %}**/
              if (vehicle.active == false) {
                // Permite apagar o veículo selecionado
                questionDialog(
                  'Remover veículo',
                  'Você deseja realmente remover o veículo placa <b>&ldquo;' +
                  vehicle.name + '&rdquo;</b>?<br> Esta ação não poderá ser desfeita.',
                  function() {
                    // Remove o veículo selecionado
                    var
                      url  = "{{ buildURL(remove.URL, {'vehicleID': 'vehicle.id'}) }}"
                    ;

                    deleteJSONData(url, [],
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
              }
              /**{% endif %}**/
            }

            break;
          default:
            if (originalEvent.target.classList.contains('copy')) {
              // Copia a placa para a área de transferência
              copyTextToClipboard(vehicle.name);
            } else {
              if (vehicle.type == 3) {
                /**{% if authorization.getAuthorizationFor(edit.URL, edit.method) %}**/
                // Coloca o veículo em edição
                window.location.href = "{{ buildURL(edit.URL, {'vehicleID': 'vehicle.id'}) }}";
                /**{% endif %}**/
              }
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


// =================================================[ Options ]=====

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

/**{% if authorization.user.groupid < 6 %}**/
// As opções para o componente de autocompletar o nome do cliente
var customerNameCompletionOptions = {
  autoSelectFirst: true,
  searchOnFocus: false,
  showClearValue: true,
  ajax: {
    url: "{{ path_for(getCustomerNameCompletion.URL) }}",
    type: "{{ getCustomerNameCompletion.method }}",
    data: function(params) {
      params.type = 'customer';
      params.detailed = true;
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
  onSelect: function(element, suggestion) {
    // Armazena o ID do item selecionado
    $("input[name='customerID']")
      .val(suggestion.id)
    ;
    $("input[name='subsidiaryID']")
      .val(suggestion.subsidiaryid)
    ;

    searchLoad();
  },
  onInvalidateSelection: function() {
    // Limpa os dados
    $("input[name='customerID']")
      .val(0)
    ;
    $("input[name='subsidiaryID']")
      .val(0)
    ;

    searchLoad();
  }
};
/**{% endif %}**/

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
        : (settings.type === 'month')
          ? month + '/' + year
          : ( settings.monthFirst
              ? month + '/' + day
              : day + '/' + month
            ) + '/' + year
      ;
    }
  }
};


// ==============================================[ Float Menu ]=====

var closeMenu = function(event) {
  var
    element = $(event.target)
  ;

  if ( element.hasClass('close') &&
       element.is('i') ) {
    var
      menu = $(this).closest('div.floating.options')
    ;

    event.preventDefault();

    $(menu)
      .addClass('inactive')
    ;
  }
};

// Um formatador de coluna para exibir um ícone na barra de título
var iconTitleWithMenuFormatter = function( icon, color, tooltip, customerID, subsidiaryID) {
  if (tooltip) {
    return ''
      + '<span>'
      +   '<i class="' + icon + ' ' + color + ' icon">'
      +   '</i>'
      +   '<div class="floating options inactive">'
      +     '<div class="ui compact horizontal labeled icon menu">'
      +       '<a class="item" onclick="vehicleList(this, ' + customerID + ', ' + subsidiaryID + ', \'vehicleList\')">'
      +         '<i class="' + icon + ' ' + color + ' icon"></i>'
      +         'Relação de veículos'
      +       '</a>'
      +       '<a class="item" onclick="vehicleList(this, ' + customerID + ', ' + subsidiaryID + ', \'equipmentList\')">'
      +         '<i class="' + icon + ' ' + color + ' icon"></i>'
      +         'Veículos rastreados'
      +       '</a>'
      +       '<p class="item">'
      +         '<i class="close icon"></i>'
      +       '</p>'
      +     '</div>'
      +   '</div>'
      + '</span>'
    ;
  } else {
    return '<i class="' + icon + ' ' + color + ' icon"></i>';
  }
};

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
  }, 500);
}

// Força a atualização da tabela
function forceSearch(event) {
  if (event.which == 13) {
    searchLoad();
  }
}

// Vincula (associa) o veículo selecionado com um equipamento
function attach(vehicleID, row) {
  /**{% if authorization.getAuthorizationFor(attachEquipment.URL, attachEquipment.method) %}**/
  var url = "{{ buildURL(attachEquipment.URL, { 'vehicleID': 'vehicleID' }) }}";

  window.location.href = url;
  /**{% endif %}**/
}

// Edita as informações de associação do veículo com o equipamento
function reattach(event, vehicleID, equipmentID, row) {
  /**{% if authorization.getAuthorizationFor(attachEquipment.URL, attachEquipment.method) %}**/
  var url = "{{ buildURL(attachEquipment.URL, { 'vehicleID': 'vehicleID','equipmentID': 'equipmentID' }) }}";

  window.location.href = url;
  /**{% endif %}**/
}

// Desvincula (desassocia) o equipamento do veículo 
function detach(event, equipmentID, row) {
  event.stopPropagation();
  
  /**{% if authorization.getAuthorizationFor(detachEquipment.URL, detachEquipment.method) %}**/
  // Solicitada a desassociação do equipamento do veículo
  // selecionado, precisa determinar o local onde o equipamento será
  // armazenado após desinstalação
  $('.toggle.checkbox')
    .checkbox()
  ;
  interactionDialog(
    'Desinstalação de equipamento',
    uninstallTemplate,
    function() {
      var
        today = new Date(),
        day   = String(today.getDate()).padStart(2, '0'),
        month = String(today.getMonth() + 1).padStart(2, '0'),
        year  = today.getFullYear()
      ;

      today = day + '/' + month + '/' + year;

      $('.uninstall.form')
        .find('.ui.selection.dropdown')
        .dropdown({
          useLabels: true,
          forceSelection: true
        })
      ;
      $("input[name='uninstalledat']")
        .val(today)
      ;
      $("#uninstalledat")
        .calendar(calendarOptions)
      ;
      $("input[name='uninstalledat']")
        .mask({
          type: 'date'
        })
      ;
      $('.ui.checkbox:not(.toggle)')
        .checkbox(checkboxOptions)
      ;

      var
        depositID = $('input[name="depositid"]').val()
      ;
      $("input[name='uninstalledat")
        .focus()
      ;
    },
    function() {
      // Confirmou a desinstalação, então repassa as informações
      var
        storageLocation = $('.uninstall')
          .find('[name="storageLocation"]:checked')
          .val(),
        uninstalledAt = $('.uninstall')
          .find('[name="uninstalledat"]')
          .val(),
        terminate = $('input[name="terminate"]').val(),
        params = {
          uninstalledAt: uninstalledAt,
          terminate: terminate,
          storageLocation: storageLocation,
          depositID: 0,
          technicianID: 0,
          serviceproviderID: 0
        }
      ;

      switch (storageLocation) {
        case 'StoredOnDeposit':
          params['depositID'] = $('input[name="depositid"]').val();
          
          break;
        case 'StoredWithTechnician':
          params['technicianID'] = $('input[name="technicianid"]').val();

          break;
        case 'StoredWithServiceProvider':
          params['serviceproviderID'] = $('input[name="serviceproviderid"]').val();

          break;
      }

      var
        url = "{{ buildURL(detachEquipment.URL, {'equipmentID': 'equipmentID'}) }}"
      ;
      deleteJSONData(url, params,
      function () {
        // Atualiza a linha
        table
          .row( row )
          .draw()
        ;
        
        // Reexibe o detalhamento dos equipamentos
        setTimeout(function() {
          // Reexibimos a informação de detalhamento
          var node = table.cell( row, 21).node();

          $(node)
            .trigger('click')
          ;
        }, 400);
      });
    },
    function() {
      // Cancelou, então ignora
    }
  );
  /**{% endif %}**/
}

function vehicleList(element, customerID, subsidiaryID = 0, type = 'vehicleList') {
  // Gera um PDF com a relação de veículos deste cliente 
  var
    url = "{{ buildURL(getPDF.URL, {'customerID': 'customerID', 'subsidiaryID': 'subsidiaryID', 'vehicleID': 'type'}) }}",
    myHeaders = new Headers()
  ;

  // Escondemos o menu
  $(element)
    .closest('.floating.options')
    .addClass('inactive')
  ;

  // Incluímos o cabeçalho para indicar que não desejamos que o conteúdo
  // seja cacheável
  myHeaders.append('pragma', 'no-cache');
  myHeaders.append('cache-control', 'no-cache');

  fetch(url, { method: 'GET', headers: myHeaders })
    .then(function(response) {
      if (response.ok) {
        var
          disposition = response.headers.get('Content-Disposition'),
          parts = disposition.split(';'),
          filename = parts[0].split('=')[1].replaceAll("'", "")
        ;
        response.blob().then(function(myBlob) {
          download(myBlob, filename, 'application/octet-stream');
        });
      } else {
        switch (response.status) {
          case 404:
          case 406:
            response.json().then(function(json) {
              infoDialog(
                'Arquivo PDF',
                json.message
              );
            });

            break;
          default:
            response.text().then(function(text) {
              infoDialog(
                'Arquivo PDF',
                text
              );
            });
        }
      }
    })
  ;
}

function showMailStatus(vehicleID, plate, customerID, customerName, sentMailStatus)
{
  var
    mailStatus = JSON.parse(sentMailStatus)
  ;

  if ((mailStatus[0] == 0) && (mailStatus[1] == 0) && (mailStatus[2] == 0)) {
    // Nenhum e-mail enviado, então exibe a informação
    infoDialog(
      'Situação do envio de e-mails',
      'Desculpe, ainda não foi enviado nenhum e-mail relativo ao '
      + 'documento nº <b>' + invoiceID + '</b> do cliente <b>'
      + customerName + '</b>.'
    );
  } else {
    // Requisitamos os dados da situação dos e-mails
    var url = "{{ path_for(getMailData.URL) }}";

    requestJSONData(url, { vehicleID: vehicleID, plate: plate, customerID: customerID },
    function (mailData, params, message)
    {
      // Conseguiu localizar os dados dos e-mails, então exibe o
      // diálogo ao cliente
      
      // Atualizamos os dados do formulário
      $('.ui.emails.modal')
        .find('.sub.header')
        .html('Aqui estão relacionados todos e-mails relativos '
          + 'ao veículo placa <b>' + plate + '</b> do '
          + 'cliente <b>' + customerName + '</b>.'
        )
      ;

      $("#emailsTable")
        .find('tbody')
        .empty()
      ;

      mailData.forEach(function(row) {
        var
          lineColor = (row.sentstatus == 0)
            ? 'orange'
            : (row.sentstatus == 1)?'green':'red',
          ratingStatus = [
            (row.attempts>0)?' active':'',
            (row.attempts>1)?' active':'',
            (row.attempts>2)?' active':''
          ],
          statusIcon = (row.sentstatus == 0)
            ? 'clock'
            : (row.sentstatus == 1)?'checkmark':'close',
          hint = (row.sentstatus == 0)
            ? 'Aguardando o envio...'
            : (row.sentstatus == 1)?'Enviado com sucesso':'Erro ao enviar',
          rowClass = (row.sentstatus == 0)
            ? 'warning'
            : (row.sentstatus == 1)?'positive':'negative',
          reasons = (row.sentstatus == 0)
            ? 'Aguardando o envio...'
            : (row.sentstatus == 1)?'Enviado com sucesso':row.reasons
        ;

        $("#emailsTable")
          .find('tbody')
          .append(
            $('<tr>')
              .addClass(rowClass)
              .append(
                $('<td>')
                  .text(row.maileventname)
              )
              .append(
                $('<td>')
                  .addClass('center aligned')
                  .text(row.statusat)
              )
              .append(
                $('<td>')
                  .text(row.sentto)
              )
              .append(
                $('<td>')
                  .append(
                    $('<div>')
                      .addClass('ui ' + lineColor + ' rating disabled')
                      .append(
                        $('<i>')
                          .addClass('circle icon' + ratingStatus[0])
                      )
                      .append(
                        $('<i>')
                          .addClass('circle icon' + ratingStatus[1])
                      )
                      .append(
                        $('<i>')
                          .addClass('circle icon' + ratingStatus[2])
                      )
                  )
              )
              .append(
                $('<td>')
                  .addClass('center aligned')
                  .append(
                    $('<span>')
                      .attr('data-tooltip', hint)
                      .attr('data-inverted', true)
                      .attr('data-position', 'left center')
                      .append(
                        $('<i>')
                          .addClass('large ' + lineColor + ' ' + statusIcon + ' icon')
                      )
                  )
              )
              .append(
                $('<td>')
                  .text(
                    reasons
                  )
              )
          )
        ;
      });

      $('.ui.emails.modal').modal(
        {
          closable  : true,
          autofocus: true,
          restoreFocus: true
        })
        .modal('show')
      ;
    },
    function (data, params, message) {
      // Não conseguiu localizar os dados dos emails, então exibe a
      // informação
      errorDialog(
        'Situação do envio de e-mails',
        'Desculpe, não foi possível obter os e-mails enviado '
        + 'relativos ao veículo placa <b>' + plate
        + '</b> do cliente <b>' + customerName + '</b>.'
      );
    });
  }
}

var tableToExcel = (function() {
  var
    uri = 'data:application/vnd.ms-excel;base64,',
    template = ''
      + '<html xmlns:o="urn:schemas-microsoft-com:office:office" '
      +       'xmlns:x="urn:schemas-microsoft-com:office:excel" '
      +       'xmlns="http://www.w3.org/TR/REC-html40">'
      +   '<head>'
      +     '<!--[if gte mso 9]>'
      +       '<xml>'
      +         '<x:ExcelWorkbook>'
      +           '<x:ExcelWorksheets>'
      +             '<x:ExcelWorksheet>'
      +               '<x:Name>{worksheet}</x:Name>'
      +               '<x:WorksheetOptions>'
      +                 '<x:DisplayGridlines/>'
      +               '</x:WorksheetOptions>'
      +             '</x:ExcelWorksheet>'
      +           '</x:ExcelWorksheets>'
      +         '</x:ExcelWorkbook>'
      +       '</xml>'
      +     '<![endif]-->'
      +     '<meta http-equiv="content-type" content="application/vnd.ms-excel;" charset="UTF-8">'
      +     '<meta charset="UTF-8">'
      +   '</head>'
      +   '<body>'
      +     '<table>{table}</table>'
      +   '</body>'
      + '</html>',
    base64 = function(s)
    {
      return window.btoa(unescape(encodeURIComponent(s)))
    },
    format = function(s, c) {
      return s.replace(/{(\w+)}/g, function(m, p) {
        return c[p];
      })
    }
  ;

  return function(table, name) {
    if (!table.nodeType) {
      table = document.getElementById(table);
    }

    var
      ctx = {worksheet: name || 'Worksheet', table: table.innerHTML}
    ;

    window.location.href = uri + base64(format(template, ctx));
  }
})();

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
