var installationFormValues = {};

$(document).ready(function()
{
  // -------------------------------[ Componentes do formulário ]---
  $("input[name='main']")
    .closest('div.selection.dropdown')
    .dropdown()
  ;
  $('.ui.checkbox:not(.radio)')
    .checkbox(checkboxOptions)
  ;
  /**{% if not attached %}**/
  $("input[name='serialnumber']")
    .autocomplete(serialNumberCompletionOptions)
  ;
  $("input[name='customerpayername']")
    .autocomplete(customerPayerNameCompletionOptions)
  ;
  $("input[name='subsidiarypayername']")
    .autocomplete(subsidiaryPayerNameCompletionOptions)
  ;
  $("input[name='contractid']")
    .closest('div.ui.dropdown')
    .dropdown(contractOptions)
  ;
  $("input[name='installationid']")
    .closest('div.ui.dropdown')
    .dropdown(installationOptions)
  ;
  /**{% else %}**/
  $("input[name='newserialnumber']")
    .autocomplete(newSerialNumberCompletionOptions)
  ;
  $("#replacedat")
    .calendar(calendarOptions)
  ;
  $("input[name='replacedat")
    .mask({
      type: 'date'
    })
  ;
  $("input[name='newcustomerpayername']")
    .autocomplete(newCustomerPayerNameCompletionOptions)
  ;
  $("input[name='newsubsidiarypayername']")
    .autocomplete(newSubsidiaryPayerNameCompletionOptions)
  ;
  $("input[name='newcontractid']")
    .closest('div.ui.dropdown')
    .dropdown(newContractOptions)
  ;
  $("input[name='newinstallationid']")
    .closest('div.ui.dropdown')
    .dropdown(newInstallationOptions)
  ;
  $("#transferat")
    .calendar(calendarOptions)
  ;
  $("input[name='transferat")
    .mask({
      type: 'date'
    })
  ;
  $("input[name='_terminate']")
    .change(terminateHandler)
  ;
  /**{% endif %}**/

  $("#installedat")
    .calendar(calendarOptions)
  ;
  $("input[name='installedat")
    .mask({
      type: 'date'
    })
  ;

  // Coloca o foco no primeiro campo
  /**{% if attached %}**/
  $("input[name='installationsite']")
    .focus()
  ;
  /**{% else %}**/
  $("input[name='serialnumber']")
    .focus()
  ;
  /**{% endif %}**/
  /**{% if attached %}**/
  $('.ui.toggle.replace.button')
    .state({
      text: {
        inactive : 'Substituir',
        active   : 'Substituíndo'
      },
      onActivate     : enableReplace,
      onDeactivate   : disableReplace,
    })
  ;
  $('.ui.toggle.transfer.button')
    .state({
      text: {
        inactive : 'Transferir',
        active   : 'Transferido'
      },
      onActivate     : enableTransfer,
      onDeactivate   : disableTransfer,
    })
  ;
  /**{% endif %}**/
});

// =================================================[ Options ]=====

/**{% if not attached %}**/
// As opções para o componente de autocompletar o número de série do
// equipamento
var serialNumberCompletionOptions = {
  autoSelectFirst: true,
  searchOnFocus: false,
  ajax: {
    url: "{{ path_for(getEquipmentCompletion.URL) }}",
    type: "{{ getEquipmentCompletion.method }}",
    data: function(params) {
      params.mode = 'toEquipment';
    }
  },
  onFormatResult: function(searchTerm, suggestion, index) {
    if (suggestion.imei === null) {
      suggestion.imei = '';
    }

    return ''
      + '<div class="content">'
      +   '<div class="title">Nº de série: ' + suggestion.name + (suggestion.attached?'<span style="color: darkred; font-style: italic;"> (instalado)</span>':'') + '<br>'
      +     (suggestion.imei.trim()===''?'':'<span style="color: #609f9f; font-style: italic;">IMEI: ' + suggestion.imei + '</span><br>')
      +     '<span style="color: #1858cd; font-style: italic;">' + suggestion.equipmentmodelname + '</span><br>'
      +     '<span style="color: CornflowerBlue; font-style: italic;">' + suggestion.equipmentbrandname + '</span><span style="color: #9cadcd; font-style: italic;"></span>'
      +   '</div>'
      + '</div>'
    ;
  },
  onSelect: function (element, suggestion) {
    // Armazena o ID do item selecionado e atualiza os demais campos
    $("input[name='equipmentid']")
      .val(suggestion.id)
    ;
    $("input[name='equipmentbrandid']")
      .val(suggestion.equipmentbrandid)
    ;
    $("input[name='equipmentbrandname']")
      .val(suggestion.equipmentbrandname)
    ;
    $("input[name='equipmentmodelid']")
      .val(suggestion.equipmentmodelid)
    ;
    $("input[name='equipmentmodelname']")
      .val(suggestion.equipmentmodelname)
    ;
    $("input[name='storedlocationname']")
      .val(suggestion.storedlocationname)
    ;

    var
      form = $('form[name="attach"]')
    ;

    if (suggestion.attached) {
      // O equipamento já está vinculado à um outro veículo, então
      // deixa os campos em vermelho e não permite vinculá-lo a este
      // veículo
      $("input[name='equipmentbrandname']")
        .closest('div.field')
        .addClass("error")
      ;
      $("input[name='equipmentmodelname']")
        .closest('div.field')
        .addClass("error")
      ;
      $("input[name='storedlocationname']")
        .closest('div.field')
        .addClass("error")
      ;
      $("button[type=submit]", form)
        .attr("disabled", true)
      ;
    } else {
      $("input[name='equipmentbrandname']")
        .closest('div.field')
        .removeClass("error")
      ;
      $("input[name='equipmentmodelname']")
        .closest('div.field')
        .removeClass("error")
      ;
      $("input[name='storedlocationname']")
        .closest('div.field')
        .removeClass("error")
      ;
      $("button[type=submit]", form)
        .attr("disabled", false)
      ;
    }
  },
  onInvalidateSelection: function() {
    // Não conseguiu localizar equipamentos com base nos valores
    // informados, então limpa os dados atuais
    $("input[name='equipmentid']")
      .val(0)
    ;
    $("input[name='equipmentbrandid']")
      .val(0)
    ;
    $("input[name='equipmentbrandname']")
      .val('')
    ;
    $("input[name='equipmentbrandid']")
      .val(0)
    ;
    $("input[name='equipmentmodelname']")
      .val('')
    ;
    $("input[name='storedlocationname']")
      .val('')
    ;

    // Deixa os campos normais
    $("input[name='equipmentbrandname']")
      .closest('div.field')
      .removeClass("error")
    ;
    $("input[name='equipmentmodelname']")
      .closest('div.field')
      .removeClass("error")
    ;
    $("input[name='storedlocationname']")
      .closest('div.field')
      .removeClass("error")
    ;
  }
};

// As opções para o componente de autocompletar o nome do pagante
var customerPayerNameCompletionOptions = {
  autoSelectFirst: true,
  searchOnFocus: false,
  ajax: {
    url: "{{ path_for(getEntityDataCompletion.URL) }}",
    type: "{{ getEntityDataCompletion.method }}",
    data: function(params, options) {
      params.type = 'payer';
      params.detailed = true;
      params.notIncludeAnyRegister = true;
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
  onSelect: function (element, suggestion) {
    // Armazena o ID do item selecionado
    $("input[name='customerpayerid']")
      .val(suggestion.id)
    ;
    $("input[name='subsidiarypayerid']")
      .val(suggestion.subsidiaryid)
    ;
    $("input[name='subsidiarypayername']")
      .val(suggestion.subsidiaryname)
    ;
    $("input[name='payerentitytypeid']")
      .val(suggestion.entitytypeid)
    ;

    // Limpamos os contratos e os respectivos itens
    $("input[name='contractid']")
      .closest('div.ui.dropdown')
      .dropdown('clear')
    ;
    $("input[name='contractid']")
      .closest('div.ui.dropdown')
      .dropdown('change values', [])
    ;
    $("input[name='contractid']")
      .closest('div.ui.dropdown')
      .dropdown('refresh')
    ;
    $("input[name='installationid']")
      .closest('div.ui.dropdown')
      .dropdown('clear')
    ;
    $("input[name='installationid']")
      .closest('div.ui.dropdown')
      .dropdown('change values', [])
    ;
    $("input[name='installationid']")
      .closest('div.ui.dropdown')
      .dropdown('refresh')
    ;

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
    $("input[name='customerpayerid']")
      .val(0)
    ;
    $("input[name='subsidiarypayerid']")
      .val(0)
    ;
    $("input[name='subsidiarypayername']")
      .val("")
    ;
  }
};

// As opções para o componente de autocompletar a unidade/filial do
// cliente
var subsidiaryPayerNameCompletionOptions = {
  autoSelectFirst: true,
  searchOnFocus: false,
  ajax: {
    url: "{{ path_for(getEntityDataCompletion.URL) }}",
    type: "{{ getEntityDataCompletion.method }}",
    data: function(params, options) {
      params.type = 'subsidiary';
      params.onlyCustomers = true;
      params.entityID = $("input[name='customerpayerid']").val();
    }
  },
  onFormatResult: function(searchTerm, suggestion, index) {
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
    $("input[name='subsidiarypayerid']")
      .val(suggestion.id)
    ;
  },
  onInvalidateSelection: function () {
    // Não conseguiu localizar uma unidade/filial com base nos
    // valores informados, então limpa os dados atuais
    $("input[name='subsidiarypayerid']")
      .val(0)
    ;
  }
};

// As opções para o componente de seleção do contrato
var contractOptions = {
  apiSettings: {
    cache: true,
    url: "{{ path_for(getContracts.URL) }}",
    method: "{{ getContracts.method }}",
    data: {
      'request': 'DropDownData',
      'customerID': 0,
      'subsidiaryID': 0,
      'includeSuspended': 'false',
      'includeFinish': 'false'
    },
    beforeSend: function(settings) {
      var
        $customerID = $("input[name='customerpayerid']").val(),
        $subsidiaryID = $("input[name='subsidiarypayerid']").val()
      ;

      // Cancela se não temos um ID de cliente
      if (!$customerID) {
        return false;
      }
      settings.data.customerID   = $customerID;
      settings.data.subsidiaryID = $subsidiaryID;

      return settings;
    }
  },
  message: {
    noResults : 'Nenhum contrato disponível.'
  },
  // Acrescentamos outros metadatas
  metadata : {
    signature : 'signature'
  },
  filterRemoteData: false,
  saveRemoteData: false,
  onChange: function(value, text, $choice) {
    // Limpamos os dados do item de contrato para permitir a correta
    // seleção dos itens deste contrato
    $("input[name='installationid']")
      .closest('div.ui.dropdown')
      .dropdown('clear')
    ;
    $("input[name='installationid']")
      .closest('div.ui.dropdown')
      .dropdown('change values', [])
    ;
    $("input[name='installationid']")
      .closest('div.ui.dropdown')
      .dropdown('refresh')
    ;
  }
};

// As opções para o componente de seleção do item de contrato
var installationOptions = {
  apiSettings: {
    cache: true,
    url: "{{ path_for(getInstallations.URL) }}",
    method: "{{ getInstallations.method }}",
    data: {
      'request': 'DropDownData',
      'includeInstalled': 'true',
      'includeNew': 'true',
      'includeSuspended': 'false',
      'includeFinish': 'false',
      'contractID': 0
    },
    beforeSend: function(settings) {
      var
        $contractID = $("input[name='contractid']").val()
      ;

      // Cancela se não temos um ID de contrato
      if (!$contractID) {
        return false;
      }
      settings.data.contractID = $contractID;

      return settings;
    }
  },
  message: {
    noResults : 'Nenhum item de contrato disponível.'
  },
  filterRemoteData: false,
  saveRemoteData: false
};
/**{% else %}**/
// As opções para o componente de autocompletar o número de série do
// equipamento que irá substituir o equipamento atual
var newSerialNumberCompletionOptions = {
  autoSelectFirst: true,
  searchOnFocus: false,
  ajax: {
    url: "{{ path_for(getEquipmentCompletion.URL) }}",
    type: "{{ getEquipmentCompletion.method }}",
    data: function(params) {
      params.mode = 'toEquipment';
    }
  },
  onFormatResult: function(searchTerm, suggestion, index) {
    if (suggestion.imei === null) {
      suggestion.imei = '';
    }

    return ''
      + '<div class="content">'
      +   '<div class="title">Nº de série: ' + suggestion.name + (suggestion.attached?'<span style="color: darkred; font-style: italic;"> (instalado)</span>':'') + '<br>'
      +     (suggestion.imei.trim()===''?'':'<span style="color: #609f9f; font-style: italic;">IMEI: ' + suggestion.imei + '</span><br>')
      +     '<span style="color: #1858cd; font-style: italic;">' + suggestion.equipmentmodelname + '</span><br>'
      +     '<span style="color: CornflowerBlue; font-style: italic;">' + suggestion.equipmentbrandname + '</span><span style="color: #9cadcd; font-style: italic;"></span>'
      +   '</div>'
      + '</div>'
    ;
  },
  onSelect: function (element, suggestion) {
    // Armazena o ID do item selecionado e atualiza os demais campos
    $("input[name='newequipmentid']")
      .val(suggestion.id)
    ;
    $("input[name='newequipmentbrandid']")
      .val(suggestion.equipmentbrandid)
    ;
    $("input[name='newequipmentbrandname']")
      .val(suggestion.equipmentbrandname)
    ;
    $("input[name='newequipmentmodelid']")
      .val(suggestion.equipmentmodelid)
    ;
    $("input[name='newequipmentmodelname']")
      .val(suggestion.equipmentmodelname)
    ;
    $("input[name='newstoredlocationname']")
      .val(suggestion.storedlocationname)
    ;

    var
      form = $('form[name="attach"]')
    ;

    if (suggestion.attached) {
      // O equipamento já está vinculado à um outro veículo, então
      // deixa os campos em vermelho e não permite vinculá-lo a este
      // veículo
      $("input[name='newequipmentbrandname']")
        .closest('div.field')
        .addClass("error")
      ;
      $("input[name='newequipmentmodelname']")
        .closest('div.field')
        .addClass("error")
      ;
      $("input[name='newstoredlocationname']")
        .closest('div.field')
        .addClass("error")
      ;
      $("button[type=submit]", form)
        .attr("disabled", true)
      ;
    } else {
      $("input[name='newequipmentbrandname']")
        .closest('div.field')
        .removeClass("error")
      ;
      $("input[name='newequipmentmodelname']")
        .closest('div.field')
        .removeClass("error")
      ;
      $("input[name='newstoredlocationname']")
        .closest('div.field')
        .removeClass("error")
      ;
      $("button[type=submit]", form)
        .attr("disabled", false)
      ;
    }
  },
  onInvalidateSelection: function() {
    // Não conseguiu localizar equipamentos com base nos valores
    // informados, então limpa os dados atuais
    $("input[name='newequipmentid']")
      .val(0)
    ;
    $("input[name='newequipmentbrandid']")
      .val(0)
    ;
    $("input[name='newequipmentbrandname']")
      .val('')
    ;
    $("input[name='newequipmentbrandid']")
      .val(0)
    ;
    $("input[name='newequipmentmodelname']")
      .val('')
    ;
    $("input[name='newstoredlocationname']")
      .val('')
    ;

    // Deixa os campos normais
    $("input[name='newequipmentbrandname']")
      .closest('div.field')
      .removeClass("error")
    ;
    $("input[name='newequipmentmodelname']")
      .closest('div.field')
      .removeClass("error")
    ;
    $("input[name='newstoredlocationname']")
      .closest('div.field')
      .removeClass("error")
    ;
  }
};

// As opções para o componente de autocompletar o nome do pagante
var newCustomerPayerNameCompletionOptions = {
  autoSelectFirst: true,
  searchOnFocus: false,
  ajax: {
    url: "{{ path_for(getEntityDataCompletion.URL) }}",
    type: "{{ getEntityDataCompletion.method }}",
    data: function(params, options) {
      params.type = 'payer';
      params.detailed = true;
      params.notIncludeAnyRegister = true;
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
  onSelect: function (element, suggestion) {
    // Armazena o ID do item selecionado
    $("input[name='newcustomerpayerid']")
      .val(suggestion.id)
    ;
    $("input[name='newsubsidiarypayerid']")
      .val(suggestion.subsidiaryid)
    ;
    $("input[name='newsubsidiarypayername']")
      .val(suggestion.subsidiaryname)
    ;
    $("input[name='newpayerentitytypeid']")
      .val(suggestion.entitytypeid)
    ;

    // Limpamos os contratos e os respectivos itens
    $("input[name='newcontractid']")
      .closest('div.ui.dropdown')
      .dropdown('clear')
    ;
    $("input[name='newcontractid']")
      .closest('div.ui.dropdown')
      .dropdown('change values', [])
    ;
    $("input[name='newcontractid']")
      .closest('div.ui.dropdown')
      .dropdown('refresh')
    ;
    $("input[name='newinstallationid']")
      .closest('div.ui.dropdown')
      .dropdown('clear')
    ;
    $("input[name='newinstallationid']")
      .closest('div.ui.dropdown')
      .dropdown('change values', [])
    ;
    $("input[name='newinstallationid']")
      .closest('div.ui.dropdown')
      .dropdown('refresh')
    ;

    // Modifica o rótulo do campo unidade/filial/titular
    if (suggestion.juridicalperson) {
      $("label[for='newsubsidiarytitle']")
        .text("Unidade/filial")
      ;
    } else {
      $("label[for='newsubsidiarytitle']")
        .text("Titular ou dependente")
      ;
    }
  },
  onInvalidateSelection: function () {
    // Não conseguiu localizar um cliente com base nos valores
    // informados, então limpa os dados atuais
    $("input[name='newcustomerpayerid']")
      .val(0)
    ;
    $("input[name='newsubsidiarypayerid']")
      .val(0)
    ;
    $("input[name='newsubsidiarypayername']")
      .val("")
    ;
  }
};

// As opções para o componente de autocompletar a unidade/filial do
// cliente
var newSubsidiaryPayerNameCompletionOptions = {
  autoSelectFirst: true,
  searchOnFocus: false,
  ajax: {
    url: "{{ path_for(getEntityDataCompletion.URL) }}",
    type: "{{ getEntityDataCompletion.method }}",
    data: function(params, options) {
      params.type = 'subsidiary';
      params.onlyCustomers = true;
      params.entityID = $("input[name='newcustomerpayerid']").val();
    }
  },
  onFormatResult: function(searchTerm, suggestion, index) {
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
    $("input[name='newsubsidiarypayerid']")
      .val(suggestion.id)
    ;
  },
  onInvalidateSelection: function () {
    // Não conseguiu localizar uma unidade/filial com base nos
    // valores informados, então limpa os dados atuais
    $("input[name='newsubsidiarypayerid']")
      .val(0)
    ;
  }
};

// As opções para o componente de seleção do contrato
var newContractOptions = {
  apiSettings: {
    cache: true,
    url: "{{ path_for(getContracts.URL) }}",
    method: "{{ getContracts.method }}",
    data: {
      'request': 'DropDownData',
      'customerID': 0,
      'subsidiaryID': 0
    },
    beforeSend: function(settings) {
      var
        $customerID = $("input[name='newcustomerpayerid']").val(),
        $subsidiaryID = $("input[name='newsubsidiarypayerid']").val()
      ;

      // Cancela se não temos um ID de cliente
      if (!$customerID) {
        return false;
      }
      settings.data.customerID   = $customerID;
      settings.data.subsidiaryID = $subsidiaryID;

      return settings;
    }
  },
  message: {
    noResults : 'Nenhum contrato disponível.'
  },
  // Acrescentamos outros metadatas
  metadata : {
    signature : 'signature'
  },
  filterRemoteData: false,
  saveRemoteData: false,
  onChange: function(value, text, $choice) {
    // Limpamos os dados do item de contrato para permitir a correta
    // seleção dos itens deste contrato
    $("input[name='newinstallationid']")
      .closest('div.ui.dropdown')
      .dropdown('clear')
    ;
    $("input[name='newinstallationid']")
      .closest('div.ui.dropdown')
      .dropdown('change values', [])
    ;
    $("input[name='newinstallationid']")
      .closest('div.ui.dropdown')
      .dropdown('refresh')
    ;
  }
};

// As opções para o componente de seleção do item de contrato
var newInstallationOptions = {
  apiSettings: {
    cache: true,
    url: "{{ path_for(getInstallations.URL) }}",
    method: "{{ getInstallations.method }}",
    data: {
      'request': 'DropDownData',
      'includeInstalled': 'true',
      'includeNew': 'true',
      'contractID': 0
    },
    beforeSend: function(settings) {
      var
        $contractID = $("input[name='newcontractid']").val()
      ;

      // Cancela se não temos um ID de contrato
      if (!$contractID) {
        return false;
      }
      settings.data.contractID = $contractID;

      return settings;
    }
  },
  message: {
    noResults : 'Nenhum item de contrato disponível.'
  },
  filterRemoteData: false,
  saveRemoteData: false
};
/**{% endif %}**/

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
  }
};


// ================================================[ Handlers ]=====

/**
 * Habilita os campos de substituíção de um rastreador.
 *
 * @return void
 */
function enableReplace()
{
  $("div.replace")
    .show()
  ;
  $("input[name='newserialnumber']")
    .focus()
  ;
  $("input[name='replace']")
    .val('true')
  ;

  // Sempre bloqueia a transferência de propriedade quando a
  // substituição está ativa
  $("button.transfer")
    .addClass('disabled')
  ;

  // Copia as informações de instalação e limpa os campos para que o
  // usuário seja obrigado a preenchê-los
  installationFormValues['installationsite'] = $("input[name='installationsite']").val();
  $("input[name='installationsite']").val('');
  installationFormValues['hasblocking'] = $("input[name='hasblocking']").val();
  $("input[name='hasblocking']").val('false');
  $("input[name='hasblocking']")
    .closest('div.ui.checkbox')
    .checkbox('uncheck')
  ;
  installationFormValues['blockingsite'] = $("input[name='blockingsite']").val();
  $("input[name='blockingsite']").val('');
  installationFormValues['hasibutton'] = $("input[name='hasibutton']").val();
  $("input[name='hasibutton']").val('');
  $("input[name='hasibutton']")
    .closest('div.ui.checkbox')
    .checkbox('uncheck')
  ;
  installationFormValues['ibuttonsite'] = $("input[name='ibuttonsite']").val();
  $("input[name='ibuttonsite']").val('');
  installationFormValues['panicbuttonsite'] = $("input[name='panicbuttonsite']").val();
  $("input[name='panicbuttonsite']").val('');
  installationFormValues['hassiren'] = $("input[name='hassiren']").val();
  $("input[name='hassiren']").val('');
  $("input[name='hassiren']")
    .closest('div.ui.checkbox')
    .checkbox('uncheck')
  ;
  installationFormValues['sirensite'] = $("input[name='sirensite']").val();
  $("input[name='sirensite']").val('');
}

/**
 * Desabilita os campos de substituição de um rastreador.
 *
 * @return void
 */
function disableReplace()
{
  $("div.replace")
    .hide()
  ;
  $("input[name='replace']")
    .val('false')
  ;

  // Sempre desbloqueia a transferência de propriedade quando a
  // substituição está inativa
  $("button.transfer")
    .removeClass('disabled')
  ;  

  if (Object.keys(installationFormValues).length > 0) {
    // Restaura as informações de instalação para os campos
    $("input[name='installationsite']").val(installationFormValues['installationsite']);
    $("input[name='hasblocking']").val(installationFormValues['hasblocking']);
    $("input[name='hasblocking']")
      .closest('div.ui.checkbox')
      .checkbox(installationFormValues['hasblocking'] === 'true'?'check':'uncheck')
    ;
    $("input[name='blockingsite']").val(installationFormValues['blockingsite']);
    $("input[name='hasibutton']").val(installationFormValues['hasibutton']);
    $("input[name='hasibutton']")
      .closest('div.ui.checkbox')
      .checkbox(installationFormValues['hasibutton'] === 'true'?'check':'uncheck')
    ;
    $("input[name='ibuttonsite']").val(installationFormValues['ibuttonsite']);
    $("input[name='panicbuttonsite']").val(installationFormValues['panicbuttonsite']);
    $("input[name='hassiren']").val(installationFormValues['hassiren']);
    $("input[name='hassiren']")
      .closest('div.ui.checkbox')
      .checkbox(installationFormValues['hassiren'] === 'true'?'check':'uncheck')
    ;
    $("input[name='sirensite']").val(installationFormValues['sirensite']);
  }
}

/**
 * Habilita os campos de transferência da propriedade de um
 * rastreador.
 *
 * @return void
 */
function enableTransfer()
{
  $("div.transfer")
    .show()
  ;
  $("input[name='newcustomerpayername']")
    .focus()
  ;
  $("input[name='transfer']")
    .val('true')
  ;

  // Sempre bloqueia a substituição de rastreador quando a transferência
  // está ativa
  $("button.replace")
    .addClass('disabled')
  ;
}

/**
 * Desabilita os campos de transferência da propriedade de um
 * rastreador.
 *
 * @return void
 */
function disableTransfer()
{
  $("div.transfer")
    .hide()
  ;
  $("input[name='transfer']")
    .val('false')
  ;

  // Sempre desbloqueia a substituição de rastreador quando a
  // transferência está inativa
  $("button.replace")
    .removeClass('disabled')
  ;
}

/**
 * Faz o tratamento do campo que indica se o cliente é o dono do veículo
 * ou é um terceiro.
 *
 * @return void
 */
function terminateHandler() {
  var
    terminate = $(this).is(":checked")
  ;
  
  if (terminate) {
    // Desabilita a entrada do campo manter item de contrato ativo, pois
    // não têm razão de existir um contrato inativo com item de contrato
    // ativo
    $("input[name='_notclose']")
      .closest('.ui.checkbox')
      .checkbox('set disabled')
    ;
  } else {
    // Desabilita a entrada do campo
    $("input[name='_notclose']")
      .closest('.ui.checkbox')
      .checkbox('set enabled')
    ;
  }
}