var
  table,
  restriction = {
    Protested: 1,
    CreditBlocked: 2,
    SentToDunningAgency: 4
  }
;

// Um formatador para exibir a situação da cobrança
var situationFormatter = function ( value, type, row, meta ) {
  switch (row.paymentmethodid) {
    case 5:
      // Boleto bancário
      return value
        + '<br>'
        + '<span data-position="left center"'
        +       'data-blue data-inverted '
        +       'data-tooltip="Situação do boleto"'
        +       'class="highlight" style="font-style: italic;">'
        + 'Boleto ' + row.droppedtypename.toLowerCase()
        + '</span>'
      ;

      break;
    default:
      // Todos os demais meios de pagamento
      return value;
  }
};

// Um formatador para exibir a data de pagamento e a data de crédito se
// a mesma for diferente
var paidDateFormatter = function ( value, type, row, meta ) {
  var
    content = ''
  ;

  // Modificamos os valores para exibí-los corretamente
  if ( value ) {
    content = value;

    if (value != row.creditdate) {
      // Acrescentemos a data de crédito
      content = content
        + '<br>'
        + '<span data-position="left center"'
        +       'data-blue data-inverted '
        +       'data-tooltip="Data do crédito"'
        +       'class="highlight" style="font-style: italic;font-size: .8em;">'
        + row.creditdate
        + '</span>'
      ;
    }
  }

  // Pesquisas, ordenamentos e tipos podem usar os dados originais
  return content;
};

// Um formatador para exibir o número do documento
var documentFormatter = function ( value, type, row, meta ) {
  switch (row.paymentmethodid) {
    case 5:
      // Boleto bancário, acrescentamos a informação do número de
      // identificação do boleto junto à instituição financeira
      return value
        + '<br>'
        + '<span data-position="left center"'
        +       'data-blue data-inverted '
        +       'data-tooltip="Número do boleto no banco"'
        +       'class="highlight" style="font-size: .8em; font-style: italic;">'
        +   row.billingcounter
        + '</span>'
      ;

      break;
    default:
      // Todos os demais meios de pagamento
      return value;
  }
};

// Um formatador (render) de coluna para exibir um valor de quantidade
// monetária em que valores negativos são exibidos de maneira diferente
// e que um ícone de detalhamento é incluído
var monetaryWithDetailFormatter = function ( value, type, row, meta ) {
  // Verifica se foi solicitada a exibição do valor
  if ( type === 'display' ) {
    var
      formatedValue = '0.00'
    ;

    if (value === null) {
      return '';
    }

    // Modificamos os valores para exibí-los corretamente
    if ( typeof value == 'number' ) {
      formatedValue = value.toFixed(2);
    }
    if ( typeof value == 'string' ) {
      formatedValue = value;
    }

    // Se o valor for negativo, exibidos de maneira diferenciada
    if ( parseFloat(value) >= 0.00 ) {
      // Retornamos o valor formatado
      formatedValue = '<span class="bold">' + formatedValue.replace('.', ',') + '</span>';
    } else {
      formatedValue = '<span class="darkred bold">' + formatedValue.replace('.', ',') + '</span>';
    }

    // Acrescentamos o símbolo monetário mais o valor seguido de um
    // ícone para indicar a existência de detalhamento do valor
    value = '<span class="symbol with detail">R$</span> ' + formatedValue
      + '<button class="ui detail button" type="button">'
      +   '<i class="detail stream icon"></i>'
      + '</button>'
    ;
  }

  // Pesquisas, ordenamentos e tipos podem usar os dados originais
  return value;
};

// Um formatador (render) de coluna para exibir um valor de quantidade
// monetária em que valores negativos são exibidos de maneira diferente
// e que um segundo valor é exibido numa linha adicional
var monetaryWithAbatementFormatter = function ( value, type, row, meta ) {
  // Verifica se foi solicitada a exibição do valor
  if ( type === 'display' ) {
    var
      formatedValue = '0.00',
      abatementValue = '0.00'
    ;

    if (value === null) {
      return '';
    }

    // Modificamos os valores para exibí-los corretamente
    if ( typeof value == 'number' ) {
      formatedValue = value.toFixed(2);
    }
    if ( typeof value == 'string' ) {
      formatedValue = value;
    }

    // Se o valor for negativo, exibidos de maneira diferenciada
    if ( parseFloat(value) >= 0.00 ) {
      // Retornamos o valor formatado
      formatedValue = '<span class="bold">' + formatedValue.replace('.', ',') + '</span>';
    } else {
      formatedValue = '<span class="darkred bold">' + formatedValue.replace('.', ',') + '</span>';
    }

    // Acrescentamos o símbolo monetário mais o valor seguido de um
    // ícone para indicar a existência de detalhamento do valor
    value = '<span class="symbol">R$</span> '
      + formatedValue
    ;

    if ( parseFloat(row.abatementvalue) > 0.00 ) {
      // Modificamos os valores para exibí-los corretamente
      if ( typeof row.abatementvalue == 'number' ) {
        abatementValue = row.abatementvalue.toFixed(2);
      }
      if ( typeof abatementValue == 'string' ) {
        abatementValue = row.abatementvalue;
      }

      // Retornamos o valor formatado
      formatedValue = '<span class="bold">' + abatementValue.replace('.', ',') + '</span>';

      // Acrescentamos o símbolo monetário mais o valor
      value = value + '<br>'
        + '<span data-position="left center"'
        +       'data-blue data-inverted '
        +       'data-tooltip="Valor do desconto/abatimento"'
        +       'class="highlight" style="font-size: .8em; font-style: italic;">'
        + formatedValue
        + '</span>'
      ;
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

// Um formatador (render) de coluna para exibir os botões conforme
// o tipo de cobrança
var actionsFormatter = function (value, type, row, meta) {
  // Verifica se foi solicitada a exibição do valor
  if ( type === 'display' ) {
    var
      dropButton = '',
      pdfButton = ''
        + '<button class="ui button" type="button" '
        +         'data-position="left center" data-blue="" '
        +         'data-tooltip="Gera um PDF com o conteúdo desta cobrança" '
        +         'onclick="getPDF(this)">'
        +   '<i class="file pdf icon"></i>'
        + '</button>',
      sendmailButton = ''
        + '<button class="ui button" type="button" '
        +         'data-position="left center" data-blue="" '
        +         'data-tooltip="Envia a cobrança por e-mail" '
        +         'onclick="sendByMail(this)">'
        +   '<i class="envelope icon"></i>'
        + '</button>',
      copyDigitableLineToClipboardButton = ''
        + '<button class="ui button" type="button" '
        +         'data-position="left center" data-blue="" '
        +         'data-tooltip="Copia a linha digitável para a área de transferência" '
        +         'onclick="copyDigitableLineToClipboard(this)">'
        +   '<i class="barcode icon"></i>'
        + '</button>',
      copyDownloadableLinkToClipboardButton = ''
        + '<button class="ui button" type="button" '
        +         'data-position="left center" data-blue="" '
        +         'data-tooltip="Copia um link para download do boleto para a área de transferência" '
        +         'onclick="copyDownloadableLink(this)">'
        +   '<i class="linkify icon"></i>'
        + '</button>',
      // Não implementado, então ocultamos o botão
      //sendSMSButton = ''
      //  + '<button class="ui button" type="button" '
      //  +         'data-position="left center" data-blue="" '
      //  +         'data-tooltip="Envia a cobrança por SMS" '
      //  +         'onclick="sendBySMS(this)">'
      //  +   '<svg height="16" viewBox="0 0 16 16" width="16" '
      //  +         'xmlns="http://www.w3.org/2000/svg">'
      //  +     '<path d="m14.254392.70320971h-12.508784c-.9579642 0-1.73733085.77936659-1.73733085 1.73733089v8.3391884c0 .957966.77936665 1.737332 1.73733085 1.737332h1.4385104c.1177904 1.005915-.1789452 1.688338-.9221754 2.134485-.1337745.08028-.1977084.240098-.1560125.390552.041347.15046.1782502.254692.3346099.254692.8895137 0 3.0257359-.276235 4.0597951-2.779729h7.7540565c.957964 0 1.737331-.779366 1.737331-1.737332v-8.3391884c0-.9579643-.779367-1.73733089-1.737331-1.73733089zm-8.6741466 6.21478049c.1577491.1091039.1970132.3259233.08791.483673-.067409.097291-.175818.1497586-.2859649.1497586-.068103 0-.1372495-.020153-.1973608-.061849l-.3120257-.2161247v.3790855c0 .192149-.1556646.3474663-.3474663.3474663-.1918013 0-.3474663-.1553165-.3474663-.3474663v-.3790855l-.3116771.2157766c-.06046.041697-.1292573.061848-.1973607.061848-.1101462 0-.2185563-.05212-.2859648-.1497586-.109104-.1577487-.069841-.3742207.08791-.4836725l.4440608-.3075064-.4444092-.3078551c-.1577499-.1091043-.1970132-.3259234-.0879088-.4836731.1094519-.1577496.3259231-.1966658.4836727-.0879089l.3116771.216124v-.3790855c0-.1921489.1556647-.3474663.3474663-.3474663.1918012 0 .3474663.1553166.3474663.3474663v.3790855l.3116772-.2157765c.1577493-.1094519.3742211-.069841.483673.08791.1091039.1577493.069841.3742213-.08791.483673l-.4444092.3075074zm3.4746618 0c.1577492.1091039.1970133.3259233.08791.483673-.067409.097291-.1758179.1497586-.2859646.1497586-.068104 0-.1372494-.020153-.1973609-.061849l-.3120256-.2161247v.3790855c0 .192149-.1556646.3474663-.3474663.3474663-.1918014 0-.3474663-.1553165-.3474663-.3474663v-.3790855l-.3116771.2157766c-.06046.041697-.1292573.061848-.1973609.061848-.1101462 0-.2185563-.05212-.2859646-.1497586-.1091039-.1577484-.0698409-.3742204.0879099-.4836722l.4440608-.3075067-.4444093-.3078551c-.157749-.1091043-.1970132-.3259234-.0879093-.4836731.1094513-.1577496.3259232-.1966658.4836729-.0879089l.3116771.216124v-.3790855c0-.1921489.1556646-.3474663.3474663-.3474663.1918014 0 .3474663.1553166.3474663.3474663v.3790855l.3116769-.2157765c.1577493-.1094519.3742212-.069841.4836733.08791.1091039.1577493.069841.3742213-.08791.483673l-.4444091.3075074zm3.4746618 0c.157757.1091039.197014.3259233.0879.483673-.06741.097291-.17582.1497586-.285966.1497586-.0681 0-.137248-.020153-.19736-.061849l-.312023-.2161247v.3790855c.000008.192149-.155663.3474663-.347457.3474663-.191802 0-.347467-.1553165-.347467-.3474663v-.3790855l-.311678.2157766c-.06046.041697-.129257.061848-.19736.061848-.110147 0-.218556-.05212-.285965-.1497586-.109104-.1577492-.06984-.3742208.08791-.4836725l.444061-.3075064-.44441-.3078551c-.157749-.1091043-.197012-.3259234-.087913-.4836731.109452-.1577496.325923-.1966658.483673-.0879087l.311677.216124v-.3790857c0-.1921489.155665-.3474663.347466-.3474663.191802 0 .347467.1553166.347467.3474663v.3790855l.311677-.2157765c.157741-.1094519.374222-.069841.483674.08791.109105.1577493.06985.3742213-.0879.483673l-.444407.3075069z" '
      //  +            'opacity=".6" stroke-width=".347465"/>'
      //  +   '</svg>'
      //  + '</button>',
      historyButton = ''
        + '<button class="ui button" type="button" '
        +         'data-position="left center" data-blue="" '
        +         'data-tooltip="Permite acompanhar o histórico de eventos no título" '
        +         'onclick="history(this)">'
        +   '<i class="history icon"></i>'
        + '</button>',
      buttons = '<div class="ui icon buttons">'
    ;

    if (row.paymentsituationid == 1) {
      // Carregamos o menu de baixa conforme o tipo de pagamento e sua
      // situação
      dropButton = ''
        + '<button class="ui button" type="button" '
        +         'data-position="left center" data-blue="" '
        +         'data-tooltip="Permite realizar operações na cobrança" '
        +         'onclick="dropMenu(this)">'
        +   '<i class="arrow down icon"></i>'
        + '</button>'
      ;
    }

    // Conforme o tipo de cobrança, habilitamos os botões
    switch (row.paymentmethodid) {
      case 1:
        // Dinheiro
        if (row.paymentsituationid == 1) {
          buttons += dropButton;
        }
        buttons += pdfButton;

        break;
      case 2:
        // Cheque
        if (row.paymentsituationid == 1) {
          buttons += dropButton;
        }
        buttons += pdfButton;

        break;
      case 3:
        // Cartão de débito
        if (row.paymentsituationid == 1) {
          buttons += dropButton;
        }
        buttons += pdfButton;

        break;
      case 4:
        // Cartão de crédito
        if (row.paymentsituationid == 1) {
          buttons += dropButton;
        }
        buttons += pdfButton;

        break;
      case 5:
        // Boleto bancário
        if (row.paymentsituationid == 1) {
          buttons += dropButton;
        }
        buttons += pdfButton;
        if (row.paymentsituationid == 1) {
          buttons += sendmailButton;
          buttons += copyDigitableLineToClipboardButton;
          buttons += copyDownloadableLinkToClipboardButton;
          //buttons += sendSMSButton;
        }

        break;
      case 6:
        // Transferência bancária
        if (row.paymentsituationid == 1) {
          buttons += dropButton;
        }
        buttons += pdfButton;

        break;
      default:
        // Meio de pagamento inválido ou desconhecido
    }

    // Sempre acrescenta o histórico
    buttons += historyButton;

    buttons += '</div>';

    return buttons;
  }

  // Pesquisas, ordenamentos e tipos podem usar os dados originais
  return '';
};

$(document).ready(function() {
  // --------------------------------[ Componentes da Searchbar ]---
  $('.searchbar .ui.dropdown')
    .dropdown({
      onChange: function(value, text, $selectedItem) {
        searchLoad();
      }
    })
  ;
  $("input[name='customerName']")
    .autocomplete(customerNameCompletionOptions)
  ;
  $('#searchValue')
    .keypress(forceSearch)
  ;

  // Lida com o pressionamento de teclas
  $(document).on('keydown',function(event) {
    if (event.keyCode == 27) {
      // Limpa os dados do formulário
      $('#searchValue').val('');
      $("input[name='customerID']").val(0);
      $("input[name='customerName']").val('');
      $("input[name='subsidiaryID']").val(0);

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
    autoWidth: true,
    //fixedColumns: {
    //  left: 3
    //  },
    language: {
      url: "{{ i18n('datatables/plugins/i18n/Portuguese-Brasil.json') }}",
    },
    columnDefs: [
      { "targets": 0,
        "name": "payments.paymentid",
        "data": "id",
        "visible": false,
        "orderable": false },
      { "targets": 1,
        "name": "contracts.customerid",
        "data": "customerid",
        "visible": false,
        "orderable": false },
      { "targets": 2,
        "name": "customers.name, payments.duedate",
        "data": "customername",
        "visible": true,
        "orderable": true,
        "width": "350px" },
      { "targets": 3,
        "name": "contracts.subsidiaryid",
        "data": "subsidiaryid",
        "visible": false,
        "orderable": false },
      { "targets": 4,
        "name": "subsidiaries.name",
        "data": "subsidiaryname",
        "visible": false,
        "orderable": false },
      { "targets": 5,
        "name": "erp.getInvoiceNumber(payments.invoiceID, payments.invoiceNumber)",
        "data": "invoiceid",
        "className": "dt-center",
        "visible": true,
        "render": documentFormatter,
        "orderable": true,
        "width": "80px" },
      { "targets": 6,
        "name": "invoices.referencemonthyear, customers.name, payments.duedate",
        "data": "referencemonthyear",
        "className": "dt-center",
        "visible": true,
        "orderable": true,
        "width": "60px" },
      { "targets": 7,
        "name": "payments.paymentmethodid",
        "data": "paymentmethodid",
        "visible": false,
        "orderable": false },
      { "targets": 8,
        "name": "paymentmethods.name, customers.name, payments.duedate",
        "data": "paymentmethodname",
        "className": "dt-center",
        "visible": true,
        "orderable": true,
        "width": "140px" },
      { "targets": 9,
        "name": "payments.valuetopay, customers.name, payments.duedate",
        "data": "valuetopay",
        "className": "dt-right",
        "visible": true,
        "orderable": true,
        "render": monetaryWithAbatementFormatter,
        "width": "100px" },
      { "targets": 10,
        "name": "payments.duedate, customers.name",
        "data": "duedate",
        "className": "dt-center",
        "visible": true,
        "orderable": true,
        "width": "80px" },
      { "targets": 11,
        "name": "overdue",
        "data": "overdue",
        "visible": false,
        "orderable": false },
      { "targets": 12,
        "name": "payments.paymentsituationid",
        "data": "paymentsituationid",
        "visible": false,
        "orderable": false },
      { "targets": 13,
        "name": "payments.restrictionid",
        "data": "restrictionid",
        "visible": false,
        "orderable": false },
      { "targets": 14,
        "name": "payments.restrictionid, paymentsituations.name, payments.duedate, customers.name",
        "data": "paymentsituationname",
        "className": "dt-center",
        "visible": true,
        "orderable": true,
        "render": situationFormatter,
        "width": "140px" },
      { "targets": 15,
        "name": "payments.droppedtypeid",
        "data": "droppedtypeid",
        "className": "dt-center",
        "visible": false,
        "orderable": false },
      { "targets": 16,
        "name": "droppedtypes.name, customers.name, payments.duedate",
        "data": "droppedtypename",
        "className": "dt-center",
        "visible": false,
        "orderable": false,
        "width": "140px" },
      { "targets": 17,
        "name": "payments.paiddate, customers.name, payments.duedate",
        "data": "paiddate",
        "className": "dt-center",
        "visible": true,
        "orderable": true,
        "width": "80px",
        "render": paidDateFormatter },
      { "targets": 18,
        "name": "payments.paidvalue, customers.name, payments.duedate",
        "data": "paidvalue",
        "className": "dt-right",
        "visible": true,
        "orderable": true,
        "width": "120px",
        "render": monetaryWithDetailFormatter },
      { "targets": 19,
        "name": "payments.latepaymentinterest, customers.name, payments.duedate",
        "data": "latepaymentinterest",
        "className": "dt-right",
        "visible": false,
        "orderable": true,
        "width": "150px",
        "render": monetaryFormatter },
      { "targets": 20,
        "name": "payments.finevalue, customers.name, payments.duedate",
        "data": "finevalue",
        "className": "dt-right",
        "visible": false,
        "orderable": true,
        "width": "100px",
        "render": monetaryFormatter },
      { "targets": 21,
        "name": "payments.abatementvalue, customers.name, payments.duedate",
        "data": "abatementvalue",
        "className": "dt-right",
        "visible": false,
        "orderable": true,
        "width": "100px",
        "render": monetaryFormatter },
      { "targets": 22,
        "name": "payments.tariffvalue, customers.name, payments.duedate",
        "data": "tariffvalue",
        "className": "dt-right",
        "visible": true,
        "orderable": true,
        "width": "120px",
        "render": monetaryWithDetailFormatter },
      { "targets": 23,
        "name": "payments.creditdate, customers.name, payments.duedate",
        "data": "creditdate",
        "className": "dt-center",
        "visible": false,
        "orderable": true,
        "width": "80px" },
      { "targets": 24,
        "name": "haserror",
        "data": "haserror",
        "visible": false,
        "orderable": false },
      { "targets": 25,
        "name": "reasonforerror",
        "data": "reasonforerror",
        "visible": false,
        "orderable": false },
      { "targets": 26,
        "name": "drop",
        "data": null,
        "visible": true,
        "orderable": false,
        "width": "193px",
        "render": actionsFormatter },
      { "targets": 27,
        "name": "sentmailstatus",
        "data": "sentmailstatus",
        "className": "dt-center",
        "visible": true,
        "orderable": false,
        "width": "10px",
        "render": sentByMailFormatter("Cobrança enviada por e-mail") },
      { "targets": 28,
        "name": "delete",
        "data": "id",
        "className": "dt-center",
        "visible": false,
        "orderable": false,
        "width": "10px",
        "render": deleteFormatter("Remover esta cobrança") },
      { "targets": 29,
        "name": "billingcounter",
        "data": "billingcounter",
        "visible": false,
        "orderable": false }
    ],
    order: [[ 10, 'asc' ]],
    select: {
      style: 'single',
      items: 'cell'
    },
    processing: true,
    serverSide: true,
    displayStart: parseInt('{{ payment.displayStart|default("0") }}'),
    ajax: {
      url: "{{ path_for(getData.URL) }}",
      type: "{{ getData.method }}",
      data: function ( params ) {
        params.searchValue  = $('#searchValue').val();
        params.searchField  = $('#searchField').val();
        params.methodID     = $('#methodID').val();
        params.situationID  = $('#situationID').val();
        params.customerID   = $("input[name='customerID']").val();
        params.customerName = $("input[name='customerName']").val();
        params.subsidiaryID = $("input[name='subsidiaryID']").val();
      },
      error: handleAjaxError
    },
    responsive: true,
    drawCallback: function( oSettings ) {
      // Unstack Pagination
      $('div.ui.pagination.menu')
        .removeClass('stackable')
        .addClass('unstackable')
      ;
    },
    initComplete: function(settings, json) {
      // Unstack Pagination
      $('div.ui.pagination.menu')
        .removeClass('stackable')
        .addClass('unstackable')
      ;

    },
    createdRow: function(row, data, dataIndex) {
      // Deixa as linhas com cores diferentes para indicar a situação
      // de cada cobrança
      // unregistered para não registrado ainda
      // beforeExpiration para aguardando pagamento
      // overdue para vencido
      // paid para pago
      // cancel para cancelado
      // maintenance para quando estiver em cobrança
      // error para quando estiver com erro
      if (data.haserror) {
        $(row)
          .addClass('error')
        ;
      } else {
        if (data.paymentsituationid == 1) {
          if (data.droppedtypeid === 1) {
            // Não registrado
            $(row)
              .addClass('unregistered')
            ;
          } else {
            if (data.restrictionid > 0) {
              // Com restrições (protestado, negativado, etc)
              $(row)
                .addClass('billing')
              ;
            } else {
              // A receber
              if (data.overdue) {
                $(row)
                  .addClass('overdue')
                ;
              } else {
                $(row)
                  .addClass('beforeExpiration')
                ;
              }
            }
          }
        } else if (data.paymentsituationid == 2) {
          // Pago
          $(row)
            .addClass('paid')
          ;
        } else if (data.paymentsituationid == 3) {
          // Cancelado
          $(row)
            .addClass('cancel')
          ;
        } else if (data.paymentsituationid == 4) {
          // Em cobrança
          $(row)
            .addClass('billing')
          ;
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

          // Recupera o cobrança de serviço selecionado
          payment = dt.rows( index.row ).data().toArray()[0]
        ;

        // Em função da coluna onde ocorreu o clique, executa a ação
        // correspondente
        switch (index.column) {
          case 18:
            if (payment.paidvalue > 0) {
              showPaidValueDetails(payment);
            }

            break;
          case 22:
            if (payment.paymentmethodid == 5) {
              showTariffs(payment.id, payment.invoiceid, payment.customername, payment.tariffvalue);
            } else {
              infoDialog(
                'Relação de tarifas cobradas',
                'Desculpe, não foi possível obter as tarifas cobradas '
                + 'relativos ao documento nº <b>' + payment.invoiceid
                + '</b> do cliente <b>' + payment.customername
                + '</b>. Esta função está disponível apenas para '
                + 'boletos.'
              );
            }

            break;
          case 27:
            // Situação do envio do e-mail
            showMailStatus(payment.id, payment.invoiceid, payment.customername, payment.sentmailstatus);

            break;
          default:
            // Coloca o cobrança em edição
        }

        e.preventDefault();
      }
    }
  );
});


// =================================================[ Options ]=====

// As opções para o componente de autocompletar o nome do cliente
var customerNameCompletionOptions = {
  autoSelectFirst: true,
  searchOnFocus: false,
  showClearValue: true,
  ajax: {
    url: "{{ path_for(getCustomerNameCompletion.URL) }}",
    type: "{{ getCustomerNameCompletion.method }}",
    data: function(params, options) {
      params.type = 'customer';
      params.detailed = true;
      //params.onlyCustomers = true;
    }
  },
  onFormatResult: function(searchTerm, suggestion, index) {
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
        day   = date.getDate().toString().padStart(2, '0'),
        month = (date.getMonth()+1).toString().padStart(2, '0'),
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


// ================================================[ Handlers ]=====

/**
 * Permite adicionar uma cobrança em função da escolha do usuário.
 *
 * @param string chosenPayment
 *   O tipo de pagamento a ser gerado
 */
function add(chosenPayment) {
  var
    data = {
      'chosenPayment' : chosenPayment
    }
  ;

  const
    url         = "{{ buildURL(add.URL, {}) }}",
    queryString = encodeQueryData(data)
  ;
  
  window.location.href = url + '?' + queryString;
}

/**
 * Permite obter uma lista de telefones para cobrança.
 *
 * @param bool overdue
 *   Se devemos obtér telefones de clientes com valores
 *   vencidos. Falso obtém de clientes ativos.
 * @param bool sentToDunningBureau
 *   Se devemos obtér dos clientes cujos valores foram enviados para
 *   cobrança
 * @param int amount
 *   A quantidade de veículos ativos usado para filtrar
 */
function getPhoneList(type, overdue, sentToDunningBureau, amount) {
  var
    data = {
      'type' : type,
      'overdue' : overdue,
      'sentToDunningBureau' : sentToDunningBureau,
      'amount' : amount
    }
  ;

  const
    url         = "{{ buildURL(getBillingPhoneList.URL, {}) }}",
    queryString = encodeQueryData(data)
  ;
  
  window.location.href = url + '?' + queryString;
}

/**
 * Codifica elementos para envio na requisição.
 *
 * @param array data
 *   A matriz com os dados a serem codificados
 *
 * @return string
 */
function encodeQueryData(data) {
  const
    ret = []
  ;

  for (let d in data) {
    ret
      .push(encodeURIComponent(d) + '=' + encodeURIComponent(data[d]))
    ;
  }

  return ret.join('&');
}

/**
 * Executa a pesquisa.
 *
 * @return void
 */
function searchLoad() {
  table
    .ajax
    .reload()
  ;
  setTimeout(function() {
    //table
    //  .columns
    //  .adjust()
    //  .draw()
    //;
  }, 500);
}

/**
 * Força a atualização da tabela.
 *
 * @param Object event
 *   O evento
 *
 * @return void
 */
function forceSearch(event) {
  if (event.which == 13) {
    searchLoad();
  }
}

/**
 * Permite realizar a baixa do título.
 *
 * @param Object element
 *   O nó do elemento HTML de onde partiu a requisição.
 *
 * @return void
 */
function drop(element)
{
  var
    payment = table.row( $(element).parents('tr') ).data()
  ;

  console.log("Baixar o valor " + payment.valuetopay + " de " + payment.customername);
}

/**
 * Obtém um arquivo PDF com o conteúdo desta cobrança.
 *
 * @param Object element
 *   O nó do elemento HTML de onde partiu a requisição.
 *
 * @return void
 */
function getPDF(element)
{
  var
    payment = table.row( $(element).parents('tr') ).data(),
    paymentID = payment.id,
    url = "{{ buildURL(getPDF.URL, { 'paymentID': 'paymentID' }) }}"
  ;

  window.open(url, '_blank');
}

/**
 * Copia a linha digitável de um boleto para a área de transferência.
 *
 * @param Object element
 *   O nó do elemento HTML de onde partiu a requisição.
 *
 * @return {[type]} [description]
 */
function copyDigitableLineToClipboard(element)
{
  var
    payment = table.row( $(element).parents('tr') ).data(),
    paymentID = payment.id,
    url = "{{ buildURL(getDigitableLine.URL) }}"
  ;

  setWait();
  requestJSONData(url, { paymentID: paymentID },
    function (digitableLine, params, message)
    {
      // Conseguiu recuperar a linha digitável com base nos valores
      // informados, então copia para o clipboard
      unsetWait();

      copyTextToClipboard(digitableLine);
    },
    function (message) {
      // Não conseguiu recuperar a linha digitável, então alerta
      unsetWait();
      $('body')
        .toast({
            title: 'Linha digitável',
            message: message,
            displayTime: 5000,
            showProgress: 'bottom',
            class: 'red',
            classProgress: 'yellow'
        })
      ;
    }
  );
}

/**
 * Copia o link para download de um boleto para a área de transferência.
 *
 * @param Object element
 *   O nó do elemento HTML de onde partiu a requisição.
 *
 * @return {[type]} [description]
 */
function copyDownloadableLink(element)
{
  var
    payment = table.row( $(element).parents('tr') ).data(),
    paymentID = payment.id,
    url = "{{ buildURL(getDownloadableLink.URL) }}"
  ;

  setWait();
  requestJSONData(url, { paymentID: paymentID },
    function (downloadableLink, params, message)
    {
      // Conseguiu recuperar a linha digitável com base nos valores
      // informados, então copia para o clipboard
      unsetWait();

      copyTextToClipboard(downloadableLink);
    },
    function (message) {
      // Não conseguiu recuperar a linha digitável, então alerta
      unsetWait();
      $('body')
        .toast({
            title: 'Link para download',
            message: message,
            displayTime: 5000,
            showProgress: 'bottom',
            class: 'red',
            classProgress: 'yellow'
        })
      ;
    }
  );
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

/**
 * Envia a cobrança por e-mail. Se a cobrança está sendo feita por
 * boleto, então anexa-o ao corpo do e-mail.
 *
 * @param Object element
 *   O nó do elemento HTML de onde partiu a requisição.
 *
 * @return void
 */
function sendByMail(element)
{
  var
    payment = table.row( $(element).parents('tr') ).data(),
    url = "{{ buildURL(sendByMail.URL) }}"
  ;

  setWait();
  putJSONData(url, {
      paymentID: payment.id,
      customerID: payment.customerid,
      customerName: payment.customername,
      paymentMethodID: payment.paymentmethodid,
      paymentMethodName: payment.paymentmethodname
    },
    function (digitableLine, params, message)
    {
      // Conseguiu enviar o e-mail, enão exibe o sucesso
      unsetWait();
      $('body')
        .toast({
            title: 'Envio de e-mail',
            message: message,
            showProgress: 'bottom',
            class: 'inverted yellow',
            classProgress: 'red'
        })
      ;
      
      // Atualiza a tabela
      table
        .ajax
        .reload( null, false )
      ;
    },
    function (message) {
      // Não conseguiu enviar o e-mail, então alerta
      unsetWait();
      $('body')
        .toast({
            title: 'Envio de e-mail',
            message: message,
            displayTime: 5000,
            showProgress: 'bottom',
            class: 'inverted red',
            classProgress: 'red'
        })
      ;
    }
  );
}

/**
 * Envia uma cobrança por SMS.
 *
 * @param Object element
 *   O nó do elemento HTML de onde partiu a requisição.
 *
 * @return void
 */
function sendBySMS(element)
{
  var
    payment = table.row( $(element).parents('tr') ).data()
  ;

  // Atualiza a tabela
  table
    .ajax
    .reload( null, false )
  ;
}

function showMailStatus(paymentID, invoiceID, customer, sentMailStatus)
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
      + customer + '</b>.'
    );
  } else {
    // Requisitamos os dados da situação dos e-mails
    var url = "{{ path_for(getMailData.URL) }}";

    requestJSONData(url, { paymentID: paymentID },
    function (mailData, params, message)
    {
      // Conseguiu localizar os dados dos e-mails, então exibe o
      // diálogo ao cliente
      
      // Atualizamos os dados do formulário
      $('.ui.emails.modal')
        .find('.sub.header')
        .html('Aqui estão relacionados todos e-mails relativos '
          + 'ao documento nº <b>' + invoiceID + '</b> do '
          + 'cliente <b>' + customer + '</b>.'
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
        + 'relativos ao documento nº <b>' + invoiceID
        + '</b> do cliente <b>' + customer + '</b>.'
      );
    });
  }
}

function showPaidValueDetails(payment)
{
  // Atualizamos os dados do formulário
  $('.ui.paid.modal')
    .find('.sub.header')
    .html('Aqui estão informados os detalhes dos valores pagos '
      + 'relativos ao documento nº <b>' + payment.invoiceid + '</b> do '
      + 'cliente <b>' + payment.customername + '</b>.'
    )
  ;

  $("#paidTable")
    .find('tbody')
    .empty()
  ;
  $("#paidTable")
    .find('tbody')
    .append(
      $('<tr>')
        .append(
          $('<td>')
            .addClass('right aligned')
            .text('Valor do documento')
        )
        .append(
          $('<td>')
            .addClass('right aligned')
            .append(
              $('<span>')
                .addClass('symbol')
                .text('R$')
            )
            .append(
              $('<span>')
                .addClass('bold')
                .text(payment.valuetopay)
            )
        )
    )
  ;
  $("#paidTable")
    .find('tbody')
    .append(
      $('<tr>')
        .append(
          $('<td>')
            .addClass('right aligned')
            .text('Desconto/abatimento')
        )
        .append(
          $('<td>')
            .addClass('right aligned')
            .append(
              $('<span>')
                .addClass('symbol')
                .text('R$')
            )
            .append(
              $('<span>')
                .addClass('bold')
                .text(payment.abatementvalue)
            )
        )
    )
  ;
  $("#paidTable")
    .find('tbody')
    .append(
      $('<tr>')
        .append(
          $('<td>')
            .addClass('right aligned')
            .text('Multa')
        )
        .append(
          $('<td>')
            .addClass('right aligned')
            .append(
              $('<span>')
                .addClass('symbol')
                .text('R$')
            )
            .append(
              $('<span>')
                .addClass('bold')
                .text(payment.finevalue)
            )
        )
    )
  ;
  $("#paidTable")
    .find('tbody')
    .append(
      $('<tr>')
        .append(
          $('<td>')
            .addClass('right aligned')
            .text('Juros de mora')
        )
        .append(
          $('<td>')
            .addClass('right aligned')
            .append(
              $('<span>')
                .addClass('symbol')
                .text('R$')
            )
            .append(
              $('<span>')
                .addClass('bold')
                .text(payment.latepaymentinterest)
            )
        )
    )
  ;

  $("#total")
    .text(
      payment.paidvalue
        .replace('.', ',')
    )
  ;

  $('.ui.paid.modal').modal(
    {
      closable  : true,
      autofocus: true,
      restoreFocus: true
    })
    .modal('show')
  ;
}

function showTariffs(paymentID, invoiceID, customer, tariffValue)
{
  if (tariffValue == 0.00) {
    // Nenhum e-mail enviado, então exibe a informação
    infoDialog(
      'Relação de tarifas cobradas',
      'Não existem tarifas cobradas relativo ao documento nº <b>'
      + invoiceID + '</b> do cliente <b>'
      + customer + '</b>.'
    );
  } else {
    // Requisitamos os dados das tarifas cobradas
    var url = "{{ path_for(getTariffData.URL) }}";

    requestJSONData(url, { paymentID: paymentID },
    function (tariffData, params, message)
    {
      // Conseguiu localizar os dados das tarifas, então exibe o
      // diálogo ao cliente
      
      // Atualizamos os dados do formulário
      $('.ui.tariffs.modal')
        .find('.sub.header')
        .html('Aqui estão relacionados todas tarifas cobradas '
          + 'relativas ao documento nº <b>' + invoiceID + '</b> do '
          + 'cliente <b>' + customer + '</b>.'
        )
      ;

      $("#tariffsTable")
        .find('tbody')
        .empty()
      ;

      tariffData.forEach(function(row) {
        $("#tariffsTable")
          .find('tbody')
          .append(
            $('<tr>')
              .append(
                $('<td>')
                  .addClass('center aligned')
                  .text(row.occurrencedate)
              )
              .append(
                $('<td>')
                  .text(row.reasons)
              )
              .append(
                $('<td>')
                  .addClass('right aligned')
                  .append(
                    $('<span>')
                      .addClass('symbol')
                      .text('R$')
                  )
                  .append(
                    $('<span>')
                      .addClass('bold')
                      .text(row.tariffvalue)
                  )
              )
          )
        ;
      });

      $("#total")
        .text(
          tariffValue
            .replace('.', ',')
        )
      ;

      $('.ui.tariffs.modal').modal(
        {
          closable  : true,
          autofocus: true,
          restoreFocus: true
        })
        .modal('show')
      ;
    },
    function (data, params, message) {
      // Não conseguiu localizar os dados das tarifas, então exibe a
      // informação
      errorDialog(
        'Relação de tarifas cobradas',
        'Desculpe, não foi possível obter as tarifas cobradas '
        + 'relativos ao documento nº <b>' + invoiceID
        + '</b> do cliente <b>' + customer + '</b>.'
      );
    });
  }
}

function history(element)
{
  var
    payment = table.row( $(element).parents('tr') ).data()
  ;

  // Requisitamos os dados das tarifas cobradas
  var url = "{{ path_for(getHistoryData.URL) }}";

  requestJSONData(url, { paymentID: payment.id },
  function (historyData, params, message)
  {
    // Conseguiu localizar os dados das tarifas, então exibe o
    // diálogo ao cliente
    
    // Atualizamos os dados do formulário
    $('.ui.history.modal')
      .find('.sub.header')
      .html('Aqui estão relacionados todas as movimentações ocorridas '
        + 'relativas ao documento nº <b>' + payment.invoiceid + '</b> '
        + 'no valor de <b>R$ ' + payment.valuetopay.replace('.', ',')
        + '</b> com vencimento em <b>' + payment.duedate + '</b> e '
        + 'que percence ao cliente <b>' + payment.customername + '</b>.'
      )
    ;

    $("#historyTable")
      .find('tbody')
      .empty()
    ;

    historyData.forEach(function(row) {
      var
        classComplement = (row.performed)
          ? 'class="positive"'
          : 'class="warning"'
      ;
      $("#historyTable")
        .find('tbody')
        .append(
          $('<tr ' + classComplement + '>')
            .append(
              $('<td>')
                .addClass('center aligned')
                .text(row.eventdate)
            )
            .append(
              $('<td>')
                .text(row.eventtypename)
            )
            .append(
              $('<td>')
                .text(row.description)
            )
            .append(
              $('<td>')
                .text(row.reasons)
            )
        )
      ;
    });

    $('.ui.history.modal').modal(
      {
        closable  : true,
        autofocus: true,
        restoreFocus: true
      })
      .modal('show')
    ;
  },
  function (data, params, message) {
    // Não conseguiu localizar os dados de histórico, então exibe a
    // informação
    errorDialog(
      'Histórico de movimentações',
      'Desculpe, não temos histórico de movimentações relativas ao '
      + 'documento nº <b>' + payment.invoiceid + '</b> do cliente <b>'
      + payment.customername + '</b>.'
    );
  });
}

function dropMenu(element)
{
  var
    payment = table.row( $(element).parents('tr') ).data(),
    $menu = $('.ui.slim.modal').find('.menu'),
    menu = '',
    today = new Date(),
    dueDate = new Date(),
    dateParts = payment.duedate.split('/'),
    isProtested = (payment.restrictionid & restriction.Protested),
    isCreditBlocked = (payment.restrictionid & restriction.CreditBlocked),
    wasSentToDunningAgency = (payment.restrictionid & restriction.SentToDunningAgency),
    restrictionItems = ''
  ;

  // Setamos a data de vencimento
  dueDate.setFullYear(dateParts[2], (dateParts[1]-1), dateParts[0], 0, 0, 0, 0);
  today.setHours(0,0,0,0);

  // Determina os itens de restrição
  if (dueDate < today) {
    restrictionItems = ''
      + '<div class="divider"></div>'
      + ( isProtested
          ? '<a class="orange item" data-value="excludeProtestAndKeepingPending" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Sustar protesto</a>'
          : '<a class="orange item" data-value="includeProtest" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Protestar</a>')
      + ( isCreditBlocked
          ? '<a class="orange item" data-value="excludeCreditBlockedAndKeepingPending" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Sustar negativação</a>'
          : '<a class="orange item" data-value="includeCreditBlocked" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Negativar</a>')
      + ( wasSentToDunningAgency
          ? '<a class="orange item" data-value="retireFromDunningAgency" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Retirar da cobrança</a>'
          : '<a class="orange item" data-value="sentToDunningAgency" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Enviar para cobrança</a>')
    ;
  }

  switch (payment.paymentmethodid) {
    case 1:
      // Dinheiro
      menu = menu
        + '<a class="silver item" data-value="pay" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Informar pagamento</a>'
        + (
            (payment.abatementvalue > 0.00)
              ? '<a class="silver item" data-value="ungrantDiscount" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Cancelar abatimento concedido</a>'
              : '<a class="silver item" data-value="grantDiscount" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Conceder abatimento</a>'
          )
        + '<a class="silver item" data-value="changeDueDate" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Alterar vencimento</a>'
        + '<a class="silver item" data-value="cancelPayment" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Cancelar cobrança</a>'
        + restrictionItems
      ;

      break;
    case 2:
      // Cheque
      menu = menu
        + '<a class="silver item" data-value="pay" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Informar pagamento</a>'
        + '<a class="silver item" data-value="devolution" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Informar devolução</a>'
        + '<a class="silver item" data-value="reintroduce" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Informar reapresentação</a>'
        + (
            (payment.abatementvalue > 0.00)
              ? '<a class="silver item" data-value="ungrantDiscount" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Cancelar abatimento concedido</a>'
              : '<a class="silver item" data-value="grantDiscount" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Conceder abatimento</a>'
          )
        + '<a class="silver item" data-value="changeDueDate" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Alterar vencimento</a>'
        + '<a class="silver item" data-value="cancelPayment" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Cancelar cobrança</a>'
        + restrictionItems
      ;

      break;
    case 3:
      // Cartão de débito
    case 4:
      // Cartão de crédito
      menu = menu
        + '<a class="silver item" data-value="pay" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Informar pagamento</a>'
        + '<a class="silver item" data-value="unauthorized" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Informar pagamento não autorizado</a>'
        + (
            (payment.abatementvalue > 0.00)
              ? '<a class="silver item" data-value="ungrantDiscount" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Cancelar abatimento concedido</a>'
              : '<a class="silver item" data-value="grantDiscount" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Conceder abatimento</a>'
          )
        + '<a class="silver item" data-value="changeDueDate" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Alterar vencimento</a>'
        + '<a class="silver item" data-value="cancelPayment" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Cancelar cobrança</a>'
        + restrictionItems
      ;

      break;
    case 5:
      // Boleto bancário
      
      // Analisa a situação do boleto
      switch (payment.droppedtypeid) {
        case 1:
          // Aguardando registro instituição financeira
        case 2:
          // Aguardando o pagamento
          menu = menu
            + '<a class="silver item" data-value="pay" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Informar pagamento</a>'
            + (
                (payment.abatementvalue > 0.00)
                  ? '<a class="silver item" data-value="ungrantDiscount" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Cancelar abatimento concedido</a>'
                  : '<a class="silver item" data-value="grantDiscount" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Conceder abatimento</a>'
              )
            + '<a class="silver item" data-value="changeDueDate" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Alterar vencimento</a>'
            + '<a class="silver item" data-value="cancelPayment" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Cancelar cobrança</a>'
            + restrictionItems
          ;

          break;
        case 3:
          // Compensado
          break;
        case 4:
          // Baixa decurso prazo
          menu = menu
            + '<a class="silver item" data-value="pay" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Informar pagamento</a>'
            + '<a class="silver item" data-value="cancelPayment" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Cancelar cobrança</a>'
            + '</div>'
            + '</div>'
            + restrictionItems
          ;
          break;
        case 5:
          // Baixa manual
          break;
      }

      break;
    case 6:
      // Transferência bancária
      menu = menu
        + '<a class="silver item" data-value="pay" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Informar pagamento</a>'
        + (
            (payment.abatementvalue > 0.00)
              ? '<a class="silver item" data-value="ungrantDiscount" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Cancelar abatimento concedido</a>'
              : '<a class="silver item" data-value="grantDiscount" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Conceder abatimento</a>'
          )
        + '<a class="silver item" data-value="changeDueDate" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Alterar vencimento</a>'
        + '<a class="silver item" data-value="cancelPayment" data-id="' + payment.id + '" onclick="dropMenuClick(this);">Cancelar cobrança</a>'
        + restrictionItems
      ;

      break;
  }

  // Limpamos o menu anterior
  $menu
    .empty()
  ;

  // Incluímos o conteúdo do menu
  $menu
    .html(menu)
  ;

  // Armazenamos os dados da cobrança
  $menu
    .data(payment)
  ;

  $('.ui.slim.modal').modal(
      {
        closable  : true,
        autofocus: false
      }
    )
    .modal('show')
  ;
}

var dropMenuClick = function(element)
{
  var
    $choice = $(element),
    paymentID = $choice.data("id"),
    functionName = $choice.data("value"),
    payment = $(element).closest('.menu').data(),
    $menu = $(element).closest('.ui.slim.modal')
  ;
  
  console.log('Click', element);
  eval(functionName)(paymentID, payment);
};

var setWait = function()
{
  // switch to cursor wait for the current element over
  var
    elements = $(':hover')
  ;
  
  if (elements.length) {
    // get the last element which is the one on top
    elements.last().addClass('cursor-wait');
  }

  // use .off() and a unique event name to avoid duplicates
  $('html')
    .off('mouseover.cursorwait')
    .on('mouseover.cursorwait', function(e)
    {
      // switch to cursor wait for all elements you'll be over
      $(e.target).addClass('cursor-wait');
    })
  ;
};

var unsetWait = function()
{
  // remove event handler
  $('html')
    .off('mouseover.cursorwait')
  ;
  // get back to default
  $('.cursor-wait')
    .removeClass('cursor-wait')
  ;
};

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


// =============================================[ Action Handlers ]=====

/**
 * Realiza o pagamento do título informado.
 *
 * @param int paymentID
 *   O ID do pagamento
 * @param object row
 *   Os dados do pagamento
 *
 * @return void
 */
function pay(paymentID, row) {
  var
    today = new Date(),
    day = String(today.getDate()).padStart(2, '0'),
    month = String(today.getMonth() + 1).padStart(2, '0'),
    year = today.getFullYear(),
    currentDay = day + '/' + month + '/' + year
  ;

  // Atualizamos os dados do formulário
  $('.ui.pay.modal')
    .find('.sub.header')
    .html('Informe os detalhes do pagamento do documento nº <b>'
          + row.invoiceid + '</b> do cliente <b>'
          + row.customername + '</b>:'
    )
  ;
  $("input[name='payID']")
    .val(row.id)
  ;
  $("input[name='payMethodID']")
    .val(row.paymentmethodid)
  ;
  $("input[name='duedate']")
    .val(row.duedate)
  ;
  $("input[name='paiddate']")
    .val(currentDay)
  ;
  $("input[name='paiddate']")
    .mask({
      type: 'date',
      validate: true
    })
  ;
  $("input[name='creditdate']")
    .val(currentDay)
  ;
  $("input[name='creditdate']")
    .mask({
      type: 'date',
      validate: true
    })
  ;
  $("input[name='valuetopay']")
    .val(row.valuetopay.replace('.', ','))
  ;
  $("input[name='abatementvalue']")
    .val(row.abatementvalue.replace('.', ','))
  ;
  $("input[name='paidvalue']")
    .val('0.00')
  ;
  $("input[name='paidvalue']")
    .mask({
      type: 'monetary',
      trim: true,
      allowNegativeValues: false,
      maxLength: 10,
      decimalsPlaces: 2
    })
  ;
  $("textarea[name='paidreasons']")
    .val('')
  ;

  // Remove quaisquer erros anteriores
  $('div.pay.form')
    .find('div.field.error')
    .removeClass('error')
  ;
  $('div.pay.form')
    .find('small.helper')
    .remove()
  ;
  $('div.pay.form')
    .find('span.text.error')
    .text('')
  ;

  $('.ui.pay.modal').modal(
    {
      closable  : true,
      autofocus: false,
      onShow    : function() {
        $("#paiddate")
          .calendar(calendarOptions)
        ;
        $("#creditdate")
          .calendar(calendarOptions)
        ;
        setTimeout(function () {
          $("input[name='paidvalue']")
            .focus()
          ;
        }, 10);
      },
      onApprove : function(dialogRef) {
        // Recupera os valores informados
        var
          paymentID = $("input[name='payID']").val(),
          paymentMethodID = $("input[name='payMethodID']").val(),
          paidDate  = $("input[name='paiddate']").val(),
          creditDate  = $("input[name='creditdate']").val(),
          paidValue = $("input[name='paidvalue']").val().replace(/[.]+/g,"").replace(/[,]+/g,"."),
          reasons = $("textarea[name='paidreasons']").val(),
          url = "{{ buildURL(drop.URL, {'paymentID': 'paymentID'}) }}",
          params = {
            'action': 'pay',
            'paymentID': paymentID,
            'paymentMethodID': paymentMethodID,
            'paiddate': paidDate,
            'creditdate': creditDate,
            'paidvalue': paidValue,
            'paidreasons': reasons
          }
        ;

        // Remove quaisquer erros anteriores
        $('div.pay.form')
          .find('div.field.error')
          .removeClass('error')
        ;
        $('div.pay.form')
          .find('small.helper')
          .remove()
        ;
        $('div.pay.form')
          .find('span.text.error')
          .text('')
        ;

        // Informa o pagamento
        putJSONData(url, params,
        function (data, params, message) {
          // Esconde o diálogo
          $('.ui.pay.modal').modal('hide');

          // Atualiza a tabela
          table
            .ajax
            .reload()
          ;
        },
        function (message, errors) {
          // Sinaliza em qual campo ocorreu o erro
          $.each(errors, function(field, error) {
            $("input[name='" + field + "']")
              .closest('div.field')
              .addClass('error')
              .append('<small class="helper">' + error + '</small>')
            ;
          });

          // Informa a mensagem de erro
          $('div.pay.form')
            .find('span.text.error')
            .text(message)
          ;
        });

        return false;
      }
    })
    .modal('show')
  ;
}

/**
 * Informa a devolução de um cheque referente ao pagamento do título
 * informado.
 *
 * @param int paymentID
 *   O ID do pagamento
 * @param object row
 *   Os dados do pagamento
 *
 * @return void
 */
function devolution(paymentID, row) {
  console.log('Informando a devolução do cheque referente ao pagamento do documento nº ' + row.invoiceid);
}

/**
 * Informa que uma cobrança através de cartão não foi autorizada
 * referente ao pagamento do título informado.
 *
 * @param int paymentID
 *   O ID do pagamento
 * @param object row
 *   Os dados do pagamento
 *
 * @return void
 */
function unauthorized(paymentID, row) {
  console.log('Informando pagamento não autorizado referente ao pagamento do documento nº ' + row.invoiceid);
}

/**
 * Informa a reapresentação de um cheque referente ao pagamento do
 * título informado.
 *
 * @param int paymentID
 *   O ID do pagamento
 * @param object row
 *   Os dados do pagamento
 *
 * @return void
 */
function reintroduce(paymentID, row) {
  console.log('Informando a reapresentação do cheque referente ao pagamento do documento nº ' + row.invoiceid);
}

/**
 * Concede um desconto no título informado.
 *
 * @param int paymentID
 *   O ID do pagamento
 * @param object row
 *   Os dados do pagamento
 *
 * @return void
 */
function grantDiscount(paymentID, row) {
  // Atualizamos os dados do formulário
  $('.ui.discount.modal')
    .find('.sub.header')
    .html('Informe o abatimento a ser concedido ao cliente para o '
          + 'pagamento do documento nº <b>' + row.invoiceid + '</b> do '
          + 'cliente <b>'+ row.customername + '</b>:'
    )
  ;
  $("input[name='discountID']")
    .val(row.id)
  ;
  $("input[name='discountMethodID']")
    .val(row.paymentmethodid)
  ;
  $("input[name='discountduedate']")
    .val(row.duedate)
  ;
  $("input[name='originalvalue']")
    .val(row.valuetopay.replace('.', ','))
  ;
  $("input[name='discountvalue']")
    .val('0.00')
  ;
  $("input[name='discountvalue']")
    .mask({
      type: 'monetary',
      trim: true,
      allowNegativeValues: false,
      maxLength: 10,
      decimalsPlaces: 2
    })
  ;
  $("textarea[name='discountreasons']")
    .val('')
  ;

  // Remove quaisquer erros anteriores
  $('div.discount.form')
    .find('div.field.error')
    .removeClass('error')
  ;
  $('div.discount.form')
    .find('small.helper')
    .remove()
  ;
  $('div.discount.form')
    .find('span.text.error')
    .text('')
  ;

  $('.ui.discount.modal').modal(
    {
      closable  : true,
      autofocus: false,
      onShow    : function() {
        setTimeout(function () {
          $("input[name='discountvalue']")
            .focus()
          ;
        }, 10);
      },
      onApprove : function(dialogRef) {
        // Recupera os valores informados
        var
          paymentID = $("input[name='discountID']").val(),
          paymentMethodID = $("input[name='discountMethodID']").val(),
          originalValue = $("input[name='originalvalue']").val().replace(/[.]+/g,"").replace(/[,]+/g,"."),
          discountValue = $("input[name='discountvalue']").val().replace(/[.]+/g,"").replace(/[,]+/g,"."),
          reasons = $("textarea[name='discountreasons']").val(),
          url = "{{ buildURL(drop.URL, {'paymentID': 'paymentID'}) }}",
          params = {
            'action': 'abatement',
            'paymentID': paymentID,
            'paymentMethodID': paymentMethodID,
            'originalvalue': originalValue,
            'discountvalue': discountValue,
            'discountreasons': reasons
          }
        ;

        // Remove quaisquer erros anteriores
        $('div.discount.form')
          .find('div.field.error')
          .removeClass('error')
        ;
        $('div.discount.form')
          .find('small.helper')
          .remove()
        ;
        $('div.discount.form')
          .find('span.text.error')
          .text('')
        ;

        // Informa o desconto
        putJSONData(url, params,
        function (data, params, message) {
          // Esconde o diálogo
          $('.ui.discount.modal').modal('hide');

          // Atualiza a tabela
          table
            .ajax
            .reload()
          ;
        },
        function (message, errors) {
          // Sinaliza em qual campo ocorreu o erro
          $.each(errors, function(field, error) {
            $("input[name='" + field + "']")
              .closest('div.field')
              .addClass('error')
              .append('<small class="helper">' + error + '</small>')
            ;
          });

          // Informa a mensagem de erro
          $('div.discount.form')
            .find('span.text.error')
            .text(message)
          ;
        });

        return false;
      }
    })
    .modal('show')
  ;
}

/**
 * Retira um desconto oferecido no título informado.
 *
 * @param int paymentID
 *   O ID do pagamento
 * @param object row
 *   Os dados do pagamento
 *
 * @return void
 */
function ungrantDiscount(paymentID, row) {
  // Atualizamos os dados do formulário
  $('.ui.reason.modal')
    .find('.header.main')
    .html('<i class="exclamation triangle icon"></i>'
          + 'Cancelamento do abatimento concedido'
    )
  ;
  $('.ui.reason.modal')
    .find('.sub.header')
    .html('Informe o motivo para exclusão do abatimento concedido ao '
          + 'cliente para o pagamento do documento nº <b>'
          + row.invoiceid + '</b> do cliente <b>'+ row.customername
          + '</b>:'
    )
  ;
  $("input[name='reasonID']")
    .val(row.id)
  ;
  $("input[name='reasonMethodID']")
    .val(row.paymentmethodid)
  ;
  $("textarea[name='reasonsdescription']")
    .val('')
  ;

  // Remove quaisquer erros anteriores
  $('div.reason.form')
    .find('div.field.error')
    .removeClass('error')
  ;
  $('div.reason.form')
    .find('small.helper')
    .remove()
  ;
  $('div.reason.form')
    .find('span.text.error')
    .text('')
  ;

  $('.ui.reason.modal').modal(
    {
      closable  : true,
      autofocus: false,
      onShow    : function() {
        setTimeout(function () {
          $("input[name='reasonsdescription']")
            .focus()
          ;
          $("input[name='reasonsdescription']")
            .select()
          ;
        }, 10);
      },
      onApprove : function(dialogRef) {
        // Recupera os valores informados
        var
          paymentID = $("input[name='reasonID']").val(),
          paymentMethodID = $("input[name='reasonMethodID']").val(),
          reasons = $("textarea[name='reasonsdescription']").val(),
          url = "{{ buildURL(drop.URL, {'paymentID': 'paymentID'}) }}",
          params = {
            'action': 'ungrantAbatement',
            'paymentID': paymentID,
            'paymentMethodID': paymentMethodID,
            'reasonsdescription': reasons
          }
        ;

        // Remove quaisquer erros anteriores
        $('div.reason.form')
          .find('div.field.error')
          .removeClass('error')
        ;
        $('div.reason.form')
          .find('small.helper')
          .remove()
        ;
        $('div.reason.form')
          .find('span.text.error')
          .text('')
        ;

        // Realiza a modificação
        putJSONData(url, params,
        function (data, params, message) {
          // Esconde o diálogo
          $('.ui.reason.modal').modal('hide');

          // Atualiza a tabela
          table
            .ajax
            .reload()
          ;
        },
        function (message, errors) {
          // Sinaliza em qual campo ocorreu o erro
          $.each(errors, function(field, error) {
            $("input[name='" + field + "']")
              .closest('div.field')
              .addClass('error')
              .append('<small class="helper">' + error + '</small>')
            ;
          });

          // Informa a mensagem de erro
          $('div.reason.form')
            .find('span.text.error')
            .text(message)
          ;
        });

        return false;
      }
    })
    .modal('show')
  ;
}

/**
 * Altera a data do vencimento do título informado.
 *
 * @param int paymentID
 *   O ID do pagamento
 * @param object row
 *   Os dados do pagamento
 *
 * @return void
 */
function changeDueDate(paymentID, row) {
  // Atualizamos os dados do formulário
  $('.ui.duedate.modal')
    .find('.sub.header')
    .html('Informe a nova data de vencimento para pagamento do '
          + 'documento nº <b>' + row.invoiceid + '</b> do cliente <b>'
          + row.customername + '</b>:'
    )
  ;
  $("input[name='duedateID']")
    .val(row.id)
  ;
  $("input[name='duedateMethodID']")
    .val(row.paymentmethodid)
  ;
  $("input[name='currentduedate']")
    .val(row.duedate)
  ;
  $("input[name='valuetopayindate']")
    .val(row.valuetopay.replace('.', ','))
  ;
  $("input[name='newduedate']")
    .val('')
  ;
  $("input[name='newduedate']")
    .mask({
      type: 'date',
      validate: true
    })
  ;
  $("textarea[name='duedatereasons']")
    .val('')
  ;

  // Remove quaisquer erros anteriores
  $('div.duedate.form')
    .find('div.field.error')
    .removeClass('error')
  ;
  $('div.duedate.form')
    .find('small.helper')
    .remove()
  ;
  $('div.duedate.form')
    .find('span.text.error')
    .text('')
  ;

  $('.ui.duedate.modal').modal(
    {
      closable  : true,
      autofocus: false,
      onShow    : function() {
        $("#newduedate")
          .calendar(calendarOptions)
        ;
        setTimeout(function () {
          $("input[name='newduedate']")
            .focus()
          ;
        }, 10);
      },
      onApprove : function(dialogRef) {
        // Recupera os valores informados
        var
          paymentID = $("input[name='duedateID']").val(),
          paymentMethodID = $("input[name='duedateMethodID']").val(),
          currentDuedate = $("input[name='currentduedate']").val(),
          newDuedate = $("input[name='newduedate']").val(),
          reasons = $("textarea[name='duedatereasons']").val(),
          url = "{{ buildURL(drop.URL, {'paymentID': 'paymentID'}) }}",
          params = {
            'action': 'changeDuedate',
            'paymentID': paymentID,
            'paymentMethodID': paymentMethodID,
            'currentduedate': currentDuedate,
            'newduedate': newDuedate,
            'duedatereasons': reasons
          }
        ;

        // Remove quaisquer erros anteriores
        $('div.duedate.form')
          .find('div.field.error')
          .removeClass('error')
        ;
        $('div.duedate.form')
          .find('small.helper')
          .remove()
        ;
        $('div.duedate.form')
          .find('span.text.error')
          .text('')
        ;

        // Atualiza a data de vencimento
        putJSONData(url, params,
        function (data, params, message) {
          // Esconde o diálogo
          $('.ui.duedate.modal').modal('hide');

          // Atualiza a tabela
          table
            .ajax
            .reload()
          ;
        },
        function (message, errors) {
          // Sinaliza em qual campo ocorreu o erro
          $.each(errors, function(field, error) {
            $("input[name='" + field + "']")
              .closest('div.field')
              .addClass('error')
              .append('<small class="helper">' + error + '</small>')
            ;
          });

          // Informa a mensagem de erro
          $('div.duedate.form')
            .find('span.text.error')
            .text(message)
          ;
        });

        return false;
      }
    })
    .modal('show')
  ;
}

/**
 * Solicita negativação do título informado.
 *
 * @param int paymentID
 *   O ID do pagamento
 * @param object row
 *   Os dados do pagamento
 *
 * @return void
 */
function includeCreditBlocked(paymentID, row) {
  // Atualizamos os dados do formulário
  $('.ui.reason.modal')
    .find('.header.main')
    .html('<i class="exclamation triangle icon"></i>'
          + 'Solicitação de negativação'
    )
  ;
  $('.ui.reason.modal')
    .find('.sub.header')
    .html('Informe o motivo para inclusão da cobrança referente ao '
          + 'do documento nº <b>' + row.invoiceid + '</b> do cliente <b>'
          + row.customername + '</b> junto ao banco de dados da '
          + 'entidade de proteção de crédito:'
    )
  ;
  $("input[name='reasonID']")
    .val(row.id)
  ;
  $("input[name='reasonMethodID']")
    .val(row.paymentmethodid)
  ;
  $("textarea[name='reasonsdescription']")
    .val('')
  ;

  // Remove quaisquer erros anteriores
  $('div.reason.form')
    .find('div.field.error')
    .removeClass('error')
  ;
  $('div.reason.form')
    .find('small.helper')
    .remove()
  ;
  $('div.reason.form')
    .find('span.text.error')
    .text('')
  ;

  $('.ui.reason.modal').modal(
    {
      closable  : true,
      autofocus: false,
      onShow    : function() {
        setTimeout(function () {
          $("input[name='reasonsdescription']")
            .focus()
          ;
          $("input[name='reasonsdescription']")
            .select()
          ;
        }, 10);
      },
      onApprove : function(dialogRef) {
        // Recupera os valores informados
        var
          paymentID = $("input[name='reasonID']").val(),
          paymentMethodID = $("input[name='reasonMethodID']").val(),
          reasons = $("textarea[name='reasonsdescription']").val(),
          url = "{{ buildURL(drop.URL, {'paymentID': 'paymentID'}) }}",
          params = {
            'action': 'creditBlocked',
            'paymentID': paymentID,
            'paymentMethodID': paymentMethodID,
            'reasonsdescription': reasons
          }
        ;

        // Remove quaisquer erros anteriores
        $('div.reason.form')
          .find('div.field.error')
          .removeClass('error')
        ;
        $('div.reason.form')
          .find('small.helper')
          .remove()
        ;
        $('div.reason.form')
          .find('span.text.error')
          .text('')
        ;

        // Realiza a modificação
        putJSONData(url, params,
        function (data, params, message) {
          // Esconde o diálogo
          $('.ui.reason.modal').modal('hide');

          // Atualiza a tabela
          table
            .ajax
            .reload()
          ;
        },
        function (message, errors) {
          // Sinaliza em qual campo ocorreu o erro
          $.each(errors, function(field, error) {
            $("input[name='" + field + "']")
              .closest('div.field')
              .addClass('error')
              .append('<small class="helper">' + error + '</small>')
            ;
          });

          // Informa a mensagem de erro
          $('div.reason.form')
            .find('span.text.error')
            .text(message)
          ;
        });

        return false;
      }
    })
    .modal('show')
  ;
}

/**
 * Solicita baixa da negativação do título informado, porém mantém o
 * título pendente.
 *
 * @param int paymentID
 *   O ID do pagamento
 * @param object row
 *   Os dados do pagamento
 *
 * @return void
 */
function excludeCreditBlockedAndKeepingPending(paymentID, row) {
  // Atualizamos os dados do formulário
  $('.ui.reason.modal')
    .find('.header.main')
    .html('<i class="exclamation triangle icon"></i>'
          + 'Solicitação de baixa da negativação'
    )
  ;
  $('.ui.reason.modal')
    .find('.sub.header')
    .html('Informe o motivo para retirada da cobrança referente ao '
          + 'do documento nº <b>' + row.invoiceid + '</b> do cliente <b>'
          + row.customername + '</b> do banco de dados da entidade de '
          + 'proteção de crédito:'
    )
  ;
  $("input[name='reasonID']")
    .val(row.id)
  ;
  $("input[name='reasonMethodID']")
    .val(row.paymentmethodid)
  ;
  $("textarea[name='reasonsdescription']")
    .val('')
  ;

  // Remove quaisquer erros anteriores
  $('div.reason.form')
    .find('div.field.error')
    .removeClass('error')
  ;
  $('div.reason.form')
    .find('small.helper')
    .remove()
  ;
  $('div.reason.form')
    .find('span.text.error')
    .text('')
  ;

  $('.ui.reason.modal').modal(
    {
      closable  : true,
      autofocus: false,
      onShow    : function() {
        setTimeout(function () {
          $("input[name='reasonsdescription']")
            .focus()
          ;
          $("input[name='reasonsdescription']")
            .select()
          ;
        }, 10);
      },
      onApprove : function(dialogRef) {
        // Recupera os valores informados
        var
          paymentID = $("input[name='reasonID']").val(),
          paymentMethodID = $("input[name='reasonMethodID']").val(),
          reasons = $("textarea[name='reasonsdescription']").val(),
          url = "{{ buildURL(drop.URL, {'paymentID': 'paymentID'}) }}",
          params = {
            'action': 'creditUnblocked',
            'paymentID': paymentID,
            'paymentMethodID': paymentMethodID,
            'reasonsdescription': reasons
          }
        ;

        // Remove quaisquer erros anteriores
        $('div.reason.form')
          .find('div.field.error')
          .removeClass('error')
        ;
        $('div.reason.form')
          .find('small.helper')
          .remove()
        ;
        $('div.reason.form')
          .find('span.text.error')
          .text('')
        ;

        // Realiza a modificação
        putJSONData(url, params,
        function (data, params, message) {
          // Esconde o diálogo
          $('.ui.reason.modal').modal('hide');

          // Atualiza a tabela
          table
            .ajax
            .reload()
          ;
        },
        function (message, errors) {
          // Sinaliza em qual campo ocorreu o erro
          $.each(errors, function(field, error) {
            $("input[name='" + field + "']")
              .closest('div.field')
              .addClass('error')
              .append('<small class="helper">' + error + '</small>')
            ;
          });

          // Informa a mensagem de erro
          $('div.reason.form')
            .find('span.text.error')
            .text(message)
          ;
        });

        return false;
      }
    })
    .modal('show')
  ;
}

/**
 * Solicita protesto do título informado.
 *
 * @param int paymentID
 *   O ID do pagamento
 * @param object row
 *   Os dados do pagamento
 *
 * @return void
 */
function includeProtest(paymentID, row) {
  // Atualizamos os dados do formulário
  $('.ui.reason.modal')
    .find('.header.main')
    .html('<i class="exclamation triangle icon"></i>'
          + 'Solicitação de protesto'
    )
  ;
  $('.ui.reason.modal')
    .find('.sub.header')
    .html('Informe o motivo para enviar para protesto os valores '
          + 'referentes ao documento nº <b>' + row.invoiceid
          + '</b> do cliente <b>' + row.customername + '</b>:'
    )
  ;
  $("input[name='reasonID']")
    .val(row.id)
  ;
  $("input[name='reasonMethodID']")
    .val(row.paymentmethodid)
  ;
  $("textarea[name='reasonsdescription']")
    .val('')
  ;

  // Remove quaisquer erros anteriores
  $('div.reason.form')
    .find('div.field.error')
    .removeClass('error')
  ;
  $('div.reason.form')
    .find('small.helper')
    .remove()
  ;
  $('div.reason.form')
    .find('span.text.error')
    .text('')
  ;

  $('.ui.reason.modal').modal(
    {
      closable  : true,
      autofocus: false,
      onShow    : function() {
        setTimeout(function () {
          $("input[name='reasonsdescription']")
            .focus()
          ;
          $("input[name='reasonsdescription']")
            .select()
          ;
        }, 10);
      },
      onApprove : function(dialogRef) {
        // Recupera os valores informados
        var
          paymentID = $("input[name='reasonID']").val(),
          paymentMethodID = $("input[name='reasonMethodID']").val(),
          reasons = $("textarea[name='reasonsdescription']").val(),
          url = "{{ buildURL(drop.URL, {'paymentID': 'paymentID'}) }}",
          params = {
            'action': 'protest',
            'paymentID': paymentID,
            'paymentMethodID': paymentMethodID,
            'reasonsdescription': reasons
          }
        ;

        // Remove quaisquer erros anteriores
        $('div.reason.form')
          .find('div.field.error')
          .removeClass('error')
        ;
        $('div.reason.form')
          .find('small.helper')
          .remove()
        ;
        $('div.reason.form')
          .find('span.text.error')
          .text('')
        ;

        // Realiza a modificação
        putJSONData(url, params,
        function (data, params, message) {
          // Esconde o diálogo
          $('.ui.reason.modal').modal('hide');

          // Atualiza a tabela
          table
            .ajax
            .reload()
          ;
        },
        function (message, errors) {
          // Sinaliza em qual campo ocorreu o erro
          $.each(errors, function(field, error) {
            $("input[name='" + field + "']")
              .closest('div.field')
              .addClass('error')
              .append('<small class="helper">' + error + '</small>')
            ;
          });

          // Informa a mensagem de erro
          $('div.reason.form')
            .find('span.text.error')
            .text(message)
          ;
        });

        return false;
      }
    })
    .modal('show')
  ;
}

/**
 * Solicita baixa do protesto do título informado, porém mantém o título
 * pendente.
 *
 * @param int paymentID
 *   O ID do pagamento
 * @param object row
 *   Os dados do pagamento
 *
 * @return void
 */
function excludeProtestAndKeepingPending(paymentID, row) {
  // Atualizamos os dados do formulário
  $('.ui.reason.modal')
    .find('.header.main')
    .html('<i class="exclamation triangle icon"></i>'
          + 'Solicitação de baixa do protesto'
    )
  ;
  $('.ui.reason.modal')
    .find('.sub.header')
    .html('Informe o motivo para retirada do protesto da cobrança '
          + 'referente ao do documento nº <b>' + row.invoiceid
          + '</b> do cliente <b>' + row.customername + '</b>:'
    )
  ;
  $("input[name='reasonID']")
    .val(row.id)
  ;
  $("input[name='reasonMethodID']")
    .val(row.paymentmethodid)
  ;
  $("textarea[name='reasonsdescription']")
    .val('')
  ;

  // Remove quaisquer erros anteriores
  $('div.reason.form')
    .find('div.field.error')
    .removeClass('error')
  ;
  $('div.reason.form')
    .find('small.helper')
    .remove()
  ;
  $('div.reason.form')
    .find('span.text.error')
    .text('')
  ;

  $('.ui.reason.modal').modal(
    {
      closable  : true,
      autofocus: false,
      onShow    : function() {
        setTimeout(function () {
          $("input[name='reasonsdescription']")
            .focus()
          ;
          $("input[name='reasonsdescription']")
            .select()
          ;
        }, 10);
      },
      onApprove : function(dialogRef) {
        // Recupera os valores informados
        var
          paymentID = $("input[name='reasonID']").val(),
          paymentMethodID = $("input[name='reasonMethodID']").val(),
          reasons = $("textarea[name='reasonsdescription']").val(),
          url = "{{ buildURL(drop.URL, {'paymentID': 'paymentID'}) }}",
          params = {
            'action': 'unprotest',
            'paymentID': paymentID,
            'paymentMethodID': paymentMethodID,
            'reasonsdescription': reasons
          }
        ;

        // Remove quaisquer erros anteriores
        $('div.reason.form')
          .find('div.field.error')
          .removeClass('error')
        ;
        $('div.reason.form')
          .find('small.helper')
          .remove()
        ;
        $('div.reason.form')
          .find('span.text.error')
          .text('')
        ;

        // Realiza a modificação
        putJSONData(url, params,
        function (data, params, message) {
          // Esconde o diálogo
          $('.ui.reason.modal').modal('hide');

          // Atualiza a tabela
          table
            .ajax
            .reload()
          ;
        },
        function (message, errors) {
          // Sinaliza em qual campo ocorreu o erro
          $.each(errors, function(field, error) {
            $("input[name='" + field + "']")
              .closest('div.field')
              .addClass('error')
              .append('<small class="helper">' + error + '</small>')
            ;
          });

          // Informa a mensagem de erro
          $('div.reason.form')
            .find('span.text.error')
            .text(message)
          ;
        });

        return false;
      }
    })
    .modal('show')
  ;
}

/**
 * Envia o título informado para cobrança externa.
 *
 * @param int paymentID
 *   O ID do pagamento
 * @param object row
 *   Os dados do pagamento
 *
 * @return void
 */
function sentToDunningAgency(paymentID, row) {
  // Atualizamos os dados do formulário
  $('.ui.reason.modal')
    .find('.header.main')
    .html('<i class="exclamation triangle icon"></i>'
          + 'Envio para cobrança'
    )
  ;
  $('.ui.reason.modal')
    .find('.sub.header')
    .html('Informe o motivo para envio dos valores referente ao '
          + 'documento nº <b>' + row.invoiceid + '</b> do cliente <b>'
          + row.customername + '</b> para uma empresa de cobrança:'
    )
  ;
  $("input[name='reasonID']")
    .val(row.id)
  ;
  $("input[name='reasonMethodID']")
    .val(row.paymentmethodid)
  ;
  $("textarea[name='reasonsdescription']")
    .val('')
  ;

  // Remove quaisquer erros anteriores
  $('div.reason.form')
    .find('div.field.error')
    .removeClass('error')
  ;
  $('div.reason.form')
    .find('small.helper')
    .remove()
  ;
  $('div.reason.form')
    .find('span.text.error')
    .text('')
  ;

  $('.ui.reason.modal').modal(
    {
      closable  : true,
      autofocus: false,
      onShow    : function() {
        setTimeout(function () {
          $("input[name='reasonsdescription']")
            .focus()
          ;
          $("input[name='reasonsdescription']")
            .select()
          ;
        }, 10);
      },
      onApprove : function(dialogRef) {
        // Recupera os valores informados
        var
          paymentID = $("input[name='reasonID']").val(),
          paymentMethodID = $("input[name='reasonMethodID']").val(),
          reasons = $("textarea[name='reasonsdescription']").val(),
          url = "{{ buildURL(drop.URL, {'paymentID': 'paymentID'}) }}",
          params = {
            'action': 'sentToDunningAgency',
            'paymentID': paymentID,
            'paymentMethodID': paymentMethodID,
            'reasonsdescription': reasons
          }
        ;

        // Remove quaisquer erros anteriores
        $('div.reason.form')
          .find('div.field.error')
          .removeClass('error')
        ;
        $('div.reason.form')
          .find('small.helper')
          .remove()
        ;
        $('div.reason.form')
          .find('span.text.error')
          .text('')
        ;

        // Realiza a modificação
        putJSONData(url, params,
        function (data, params, message) {
          // Esconde o diálogo
          $('.ui.reason.modal').modal('hide');

          // Atualiza a tabela
          table
            .ajax
            .reload()
          ;
        },
        function (message, errors) {
          // Sinaliza em qual campo ocorreu o erro
          $.each(errors, function(field, error) {
            $("input[name='" + field + "']")
              .closest('div.field')
              .addClass('error')
              .append('<small class="helper">' + error + '</small>')
            ;
          });

          // Informa a mensagem de erro
          $('div.reason.form')
            .find('span.text.error')
            .text(message)
          ;
        });

        return false;
      }
    })
    .modal('show')
  ;
}

/**
 * Retira o título informado da cobrança externa, porém mantém o título
 * pendente.
 *
 * @param int paymentID
 *   O ID do pagamento
 * @param object row
 *   Os dados do pagamento
 *
 * @return void
 */
function retireFromDunningAgency(paymentID, row) {
  // Atualizamos os dados do formulário
  $('.ui.reason.modal')
    .find('.header.main')
    .html('<i class="exclamation triangle icon"></i>'
          + 'Solicitação de retirada da cobrança'
    )
  ;
  $('.ui.reason.modal')
    .find('.sub.header')
    .html('Informe o motivo para retirada do documento nº <b>'
          + row.invoiceid + '</b> do cliente <b>' + row.customername
          + '</b> da empresa de cobrança:'
    )
  ;
  $("input[name='reasonID']")
    .val(row.id)
  ;
  $("input[name='reasonMethodID']")
    .val(row.paymentmethodid)
  ;
  $("textarea[name='reasonsdescription']")
    .val('')
  ;

  // Remove quaisquer erros anteriores
  $('div.reason.form')
    .find('div.field.error')
    .removeClass('error')
  ;
  $('div.reason.form')
    .find('small.helper')
    .remove()
  ;
  $('div.reason.form')
    .find('span.text.error')
    .text('')
  ;

  $('.ui.reason.modal').modal(
    {
      closable  : true,
      autofocus: false,
      onShow    : function() {
        setTimeout(function () {
          $("input[name='reasonsdescription']")
            .focus()
          ;
          $("input[name='reasonsdescription']")
            .select()
          ;
        }, 10);
      },
      onApprove : function(dialogRef) {
        // Recupera os valores informados
        var
          paymentID = $("input[name='reasonID']").val(),
          paymentMethodID = $("input[name='reasonMethodID']").val(),
          reasons = $("textarea[name='reasonsdescription']").val(),
          url = "{{ buildURL(drop.URL, {'paymentID': 'paymentID'}) }}",
          params = {
            'action': 'retireFromDunningAgency',
            'paymentID': paymentID,
            'paymentMethodID': paymentMethodID,
            'reasonsdescription': reasons
          }
        ;

        // Remove quaisquer erros anteriores
        $('div.reason.form')
          .find('div.field.error')
          .removeClass('error')
        ;
        $('div.reason.form')
          .find('small.helper')
          .remove()
        ;
        $('div.reason.form')
          .find('span.text.error')
          .text('')
        ;

        // Realiza a modificação
        putJSONData(url, params,
        function (data, params, message) {
          // Esconde o diálogo
          $('.ui.reason.modal').modal('hide');

          // Atualiza a tabela
          table
            .ajax
            .reload()
          ;
        },
        function (message, errors) {
          // Sinaliza em qual campo ocorreu o erro
          $.each(errors, function(field, error) {
            $("input[name='" + field + "']")
              .closest('div.field')
              .addClass('error')
              .append('<small class="helper">' + error + '</small>')
            ;
          });

          // Informa a mensagem de erro
          $('div.reason.form')
            .find('span.text.error')
            .text(message)
          ;
        });

        return false;
      }
    })
    .modal('show')
  ;
}

/**
 * Cancela a cobrança do título informado.
 *
 * @param int paymentID
 *   O ID do pagamento
 * @param object row
 *   Os dados do pagamento
 *
 * @return void
 */
function cancelPayment(paymentID, row) {
  // Atualizamos os dados do formulário
  $('.ui.reason.modal')
    .find('.header.main')
    .html('<i class="exclamation triangle icon"></i>'
          + 'Cancelamento da cobrança'
    )
  ;
  $('.ui.reason.modal')
    .find('.sub.header')
    .html('Informe o motivo para cancelamento da cobrança referente ao '
          + 'do documento nº <b>' + row.invoiceid + '</b> do cliente <b>'
          + row.customername + '</b>:'
    )
  ;
  $("input[name='reasonID']")
    .val(row.id)
  ;
  $("input[name='reasonMethodID']")
    .val(row.paymentmethodid)
  ;
  $("textarea[name='reasonsdescription']")
    .val('')
  ;

  // Remove quaisquer erros anteriores
  $('div.reason.form')
    .find('div.field.error')
    .removeClass('error')
  ;
  $('div.reason.form')
    .find('small.helper')
    .remove()
  ;
  $('div.reason.form')
    .find('span.text.error')
    .text('')
  ;

  $('.ui.reason.modal').modal(
    {
      closable  : true,
      autofocus: false,
      onShow    : function() {
        setTimeout(function () {
          $("input[name='reasonsdescription']")
            .focus()
          ;
          $("input[name='reasonsdescription']")
            .select()
          ;
        }, 10);
      },
      onApprove : function(dialogRef) {
        // Recupera os valores informados
        var
          paymentID = $("input[name='reasonID']").val(),
          paymentMethodID = $("input[name='reasonMethodID']").val(),
          reasons = $("textarea[name='reasonsdescription']").val(),
          url = "{{ buildURL(drop.URL, {'paymentID': 'paymentID'}) }}",
          params = {
            'action': 'cancel',
            'paymentID': paymentID,
            'paymentMethodID': paymentMethodID,
            'reasonsdescription': reasons
          }
        ;

        // Remove quaisquer erros anteriores
        $('div.reason.form')
          .find('div.field.error')
          .removeClass('error')
        ;
        $('div.reason.form')
          .find('small.helper')
          .remove()
        ;
        $('div.reason.form')
          .find('span.text.error')
          .text('')
        ;

        // Realiza a modificação
        putJSONData(url, params,
        function (data, params, message) {
          // Esconde o diálogo
          $('.ui.reason.modal').modal('hide');

          // Atualiza a tabela
          table
            .ajax
            .reload()
          ;
        },
        function (message, errors) {
          // Sinaliza em qual campo ocorreu o erro
          $.each(errors, function(field, error) {
            $("input[name='" + field + "']")
              .closest('div.field')
              .addClass('error')
              .append('<small class="helper">' + error + '</small>')
            ;
          });

          // Informa a mensagem de erro
          $('div.reason.form')
            .find('span.text.error')
            .text(message)
          ;
        });

        return false;
      }
    })
    .modal('show')
  ;
}

/**
 * Obtém um arquivo de remessa com as instruções a serem enviadas ao
 * banco.
 *
 * @param int fileID
 *   O ID do arquivo (0 para gerar um novo com as instruções pendentes)
 *
 * @return void
 */
function downloadShippingFile(fileID)
{
  var
    url = "{{ buildURL(getShippingFile.URL, { 'fileID': 'fileID' }) }}",
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
                'Arquivo de remessa',
                json.message
              );
            });

            break;
          default:
            response.text().then(function(text) {
              infoDialog(
                'Arquivo de remessa',
                text
              );
            });
        }
      }
    })
  ;
}

/**
 * Processa um arquivo de retorno com as ocorrências em cobranças por
 * boleto registradas na instituição financeira.
 *
 * @return void
 */
function processReturnFile()
{
  // Exibimos o modal para permitir ao usuário informar o arquivo de
  // retorno a ser processado
  $('.ui.return.modal').modal(
    {
      closable  : true,
      autofocus: false,
      onShow    : function() {
        setTimeout(function () {
          $("input[name='reasonsdescription']")
            .focus()
          ;
        }, 10);
      },
      onApprove : function(dialogRef) {
        var
          returnFile = $('#returnfile').prop('files'),
          url = "{{ buildURL(processReturnFile.URL) }}",
          myHeaders = new Headers(),
          formData = new FormData()
        ;

        myHeaders.append('pragma', 'no-cache');
        myHeaders.append('cache-control', 'no-cache');

        formData.append("returnfile", returnFile[0]);

        // Processa o arquivo de retorno
        fetch(url, { method: 'POST', headers: myHeaders, body: formData })
          .then(function(response) {
            if (response.ok) {
              response.json().then(function(json) {
                // Exibimos um diálogo com o resultado do processamento
                $('.ui.result.modal')
                  .find('.sub.header')
                  .html('Este é o resultado do processamento do arquivo '
                    + 'de retorno <b>' + json.data.filename + '</b>.'
                  )
                ;

                $("#resultTable")
                  .find('tbody')
                  .empty()
                ;

                json.data.content.forEach(function(row) {
                  var
                    classComplement = (row.hasError)
                      ? 'class="warning"'
                      : 'class="positive"'
                  ;
                  $("#resultTable")
                    .find('tbody')
                    .append(
                      $('<tr ' + classComplement + '>')
                        .append(
                          $('<td>')
                            .addClass('center aligned')
                            .text(row.documentNumber)
                        )
                        .append(
                          $('<td>')
                            .text(row.customername)
                        )
                        .append(
                          $('<td>')
                            .addClass('center aligned')
                            .text(row.dueDate)
                        )
                        .append(
                          $('<td>')
                            .addClass('right aligned')
                            .append(
                              $('<span>')
                                .addClass('symbol')
                                .text('R$')
                            )
                            .append(
                              $('<span>')
                                .addClass('bold')
                                .text(row.valueToPay)
                            )
                        )
                    )
                  ;
                  if (row.paidValue) {
                    $("#resultTable")
                      .find('tbody')
                      .append(
                        $('<tr ' + classComplement + '>')
                          .append(
                            $('<td>')
                              .attr('colspan', 2)
                              .html(row.occurrence)
                          )
                          .append(
                            $('<td>')
                              .addClass('right aligned')
                              .text('Valor pago:')
                          )
                          .append(
                            $('<td>')
                              .addClass('right aligned')
                              .append(
                                $('<span>')
                                  .addClass('symbol')
                                  .text('R$')
                              )
                              .append(
                                $('<span>')
                                  .addClass('bold')
                                  .text(row.paidValue)
                              )
                          )
                      )
                    ;
                  } else {
                    $("#resultTable")
                      .find('tbody')
                      .append(
                        $('<tr ' + classComplement + '>')
                          .append(
                            $('<td>')
                              .attr('colspan', 4)
                              .html(row.occurrence)
                          )
                      )
                    ;
                  }
                });

                $('.ui.result.modal').modal(
                  {
                    closable  : true,
                    autofocus: true,
                    restoreFocus: true
                  })
                  .modal('show')
                ;
              },
              function (data, params, message) {
                // Não conseguiu localizar os dados de histórico, então exibe a
                // informação
                errorDialog(
                  'Histórico de movimentações',
                  'Desculpe, não temos histórico de movimentações relativas ao '
                  + 'documento nº <b>' + payment.invoiceid + '</b> do cliente <b>'
                  + payment.customername + '</b>.'
                );
              });
            } else {
              switch (response.status) {
                case 404:
                case 406:
                  response.json().then(function(json) {
                    infoDialog(
                      'Arquivo de retorno',
                      json.message
                    );
                  });

                  break;
                default:
                  response.text().then(function(text) {
                    infoDialog(
                      'Arquivo de retorno',
                      text
                    );
                  });
              }
            }
          })
        ;

        return false;
      }
    })
    .modal('show')
  ;
}