/**{% if formMethod == 'PUT' %}**/
/**{% set equipments = getValue('equipments') %}**/
var
  // O índice de contagem dos equipamentos de rastreamento
  equipmentsCount = parseInt("{{ equipments|length }}")
;
/**{% endif %}**/
/**{% set ownerPhones = getValue('ownerPhones') %}**/
/**{% set anotherPhones = getValue('anotherPhones') %}**/
var
  // O índice de contagem dos telefones
  ownerPhonesCount   = parseInt("{{ ownerPhones|length }}"),
  anotherPhonesCount = parseInt("{{ anotherPhones|length }}")
;

// Carrega o template de um novo telefone do proprietário, definindo os
// valores padrões
/**
{% set ownerPhoningTemplate %}
  {% include 'erp/cadastre/vehicles/ownerphone.twig' with { phoneTypes: phoneTypes, phoneNumber: '<%=phoneNumber%>', phone: { ownerphoneid: 0, phonetypeid: 1 }, editMode: true }  %}
{% endset %}
**/
var ownerPhoningTemplate = "{{ ownerPhoningTemplate|escape('js') }}".replace(/&lt;/g,'<').replace(/&gt;/g,'>');
    
// Carrega o template de um novo telefone do outro local, definindo os
// valores padrões
/**
{% set anotherPhoningTemplate %}
  {% include 'erp/cadastre/vehicles/anotherphone.twig' with { phoneTypes: phoneTypes, phoneNumber: '<%=phoneNumber%>', phone: { anotherphoneid: 0, phonetypeid: 1 }, editMode: true }  %}
{% endset %}
**/
var anotherPhoningTemplate = "{{ anotherPhoningTemplate|escape('js') }}".replace(/&lt;/g,'<').replace(/&gt;/g,'>');

$(document).ready(function()
{
  // -----------------------------------[ Componentes do formulário ]---
  $('.form .ui.dropdown')
    .dropdown()
  ;
  $('.ui.checkbox:not(.radio)')
    .checkbox(checkboxOptions)
  ;
  /**{% if authorization.user.groupid < 6 %}**/
  $("input[name='customername']")
    .autocomplete(customerNameCompletionOptions)
  ;
  /**{% endif %}**/
  $("input[name='subsidiaryname']")
    .autocomplete(subsidiaryNameCompletionOptions)
  ;
  $("input[name='plate']")
    .mask({
      type: 'plate'
    })
  ;
  $("input[name='vehiclemodelname']")
    .blur(vehicleModelHandler)
    .autocomplete(vehicleModelNameCompletionOptions)
  ;
  $("input[name='yearfabr']")
    .mask({
      type: 'year'
    })
  ;
  $("input[name='yearmodel']")
    .mask({
      type: 'year'
    })
  ;
  $("input[name='vehicletypeid']")
    .change(vehicleTypeHandler)
  ;
  $("input[name='vehiclesubtypeid']")
    .closest('div.search.selection.dropdown')
    .dropdown({
      message: {
        noResults : 'Nenhum subtipo disponível. '
          + 'Selecione um tipo de veículo primeiramente.'
      }
    })
  ;
  $("input[name='vehiclebrandname']")
    .autocomplete(brandNameCompletionOptions)
  ;
  $("input[name='renavam']")
    .mask({
      type: 'renavam',
      validate: true,
      trim: true
    })
  ;
  $("input[name='vin']")
    .mask({
      type: 'vin',
      validate: true,
      trim: true
    })
  ;
  $("input[name='_customeristheowner']")
    .change(customerIsTheOwnerHandler)
  ;
  toggleOwnerData($("input[name='_customeristheowner']").is(":checked"));
  $("input[name='regionaldocumentstate']")
    .mask({
      type: 'state',
      validate: true
    })
  ;
  $("input[name='nationalregister']")
    .mask({
      type: 'cpforcnpj',
      validate: true
    })
  ;
  $("input[name='cityname']")
    .autocomplete(cityNameCompletionOptions)
  ;
  $("input[name='postalcode']")
    .mask({
      type: 'postalCode'
    })
    .blur(postalCodeHandler)
  ;
  // Força o mascaramento dos campos de telefones do proprietário
  for (var ownerPhoneNumber = 0; ownerPhoneNumber < ownerPhonesCount; ownerPhoneNumber++) {
    $("input[name='ownerPhones[" + ownerPhoneNumber + "][phonenumber]']")
      .mask({
        type: 'phoneNumber'
      })
    ;
  }
  $("input[name='placeOfStay']")
    .change(placeOfStayHandler)
    .trigger('change')
  ;
  $("input[name='anothercityname']")
    .autocomplete(anotherCityNameCompletionOptions)
  ;
  $("input[name='anotherpostalcode']")
    .mask({
      type: 'postalCode'
    })
    .blur(anotherPostalCodeHandler)
  ;
  // Força o mascaramento dos campos de telefones do outro local
  for (var anotherPhoneNumber = 0; anotherPhoneNumber < anotherPhonesCount; anotherPhoneNumber++) {
    $("input[name='anotherPhones[" + anotherPhoneNumber + "][phonenumber]']")
      .mask({
        type: 'phoneNumber'
      })
    ;
  }
  $("input[name='addDocument']")
    .click(addDocumentHandler)
  ;
  // Força o mascaramento dos campos de telefones do outro local
  for (var anotherPhoneNumber = 0; anotherPhoneNumber < anotherPhonesCount; anotherPhoneNumber++) {
    $("input[name='anotherphones[" + anotherPhoneNumber + "][phonenumber]']")
      .mask({
        type: 'phoneNumber'
      })
    ;
  }
  $("input[name='attachments[]']")
    .fileinput({
      placeholder: 'Selecione os documentos para anexar...',
      preview: true,
      contentName: 'documentos',
      noContentLabel: 'Nenhum documento selecionado para visualizar'
    })
  ;

  $('.special.cards .image.dimmable').dimmer({
    on: 'hover'
  });

  /**{% if formMethod == 'PUT' %}**/
  // Lidamos com os campos com controles especiais
  for (var equipmentNumber = 0; equipmentNumber < equipmentsCount; equipmentNumber++) {
    // Autocompletar o pagador
    $("input[name='equipments[" + equipmentNumber + "][customerpayername]']")
      .autocomplete(customerPayerNameCompletionOptions)
    ;

    // Autocompletar a unidade/filial do pagador
    $("input[name='equipments[" + equipmentNumber + "][subsidiarypayername]']")
      .autocomplete(subsidiaryPayerNameCompletionOptions)
    ;

    // Dropdown do contrato
    var
      contractID = $("input[name='equipments[" + equipmentNumber + "][contractid]']")
        .val() ,
      $dropdown = $("input[name='equipments[" + equipmentNumber + "][contractid]']")
        .closest('div.ui.dropdown')
    ;
    $dropdown
      .dropdown(contractOptions)
    ;
    $dropdown
      .dropdown('set selected', contractID)
    ;
    $dropdown
      .dropdown('refresh')
    ;

    // Dropdown do item de contrato
    $dropdown = $("input[name='equipments[" + equipmentNumber + "][installationid]']")
      .closest('div.ui.dropdown');
    $dropdown
      .dropdown(installationOptions)
    ;
    $dropdown
      .dropdown('set selected',
        $("input[name='equipments[" + equipmentNumber + "][installationid]']").val()
      )
    ;
    $dropdown
      .dropdown('refresh')
    ;

    // Checkboxes
    var
      amountOfPayerContracts = $("input[name='equipments[" + equipmentNumber + "][amountOfPayerContracts]']").val(),
      amountOfItensInContract = $("input[name='equipments[" + equipmentNumber + "][amountOfItensInContract]']").val(),
      loyaltyperiod = $("input[name='equipments[" + equipmentNumber + "][loyaltyperiod]']").val(),
      $dropdown
    ;

    // Encerrar contrato
    if ((amountOfPayerContracts == 1) && (amountOfItensInContract == 1)) {
      $dropdown = $("input[name='equipments[" + equipmentNumber + "][terminate]']")
        .closest('div.ui.dropdown');
      $dropdown
        .checkbox(checkboxOptions)
      ;
      $("input[name='_equipments[" + equipmentNumber + "][terminate]']")
        .change(function() {
          // Realiza o tratamento da seleção de encerrar ou não o
          // contrato do cliente
          var
            equipmentNumber = parseInt($(this).attr("equipment")),
            $close = $("input[name='equipments[" + equipmentNumber + "][notclose]']")
              .closest('div.ui.checkbox')
          ;

          if(this.checked) {
            $close
              .checkbox('set unchecked')
            ;
            $close
              .checkbox('set disabled')
            ;
          } else {
            $close
              .checkbox('set enabled')
            ;
          }
        })
      ;
      $dropdown = $("input[name='equipments[" + equipmentNumber + "][close]']")
        .closest('div.ui.dropdown');
      $dropdown
        .checkbox(checkboxOptions)
      ;
      if (loyaltyperiod > 0) {
        $dropdown = $("input[name='equipments[" + equipmentNumber + "][close]']")
          .closest('div.ui.dropdown');
        $dropdown
          .checkbox(checkboxOptions)
        ;
      }
    }
  }
  $("input[name='transferat")
    .closest('.ui.calendar')
    .calendar(calendarOptions)
  ;
  $("input[name='transferat")
    .mask({
      type: 'date',
      validate: true
    })
  ;
  /**{% endif %}**/

  /**{% if formMethod == 'PUT' %}**/
  // Se estamos modificando, então analisamos a informação de modelo de
  // veículo para travar ou não a edição dos campos do tipo, subtipo e
  // da marca do veículo. Quando tivermos um modelo informado, não
  // permitimos a edição dos demais campos.
  vehicleModelHandler();

  $("input[name='_blocknotices']")
    .change(blockNoticesHandler)
  ;
  toggleNoticesData($("input[name='_blocknotices']").is(":checked"));
  $("input[name='blockeddays']")
    .mask({
      type: 'number'
    })
    .blur(amountOfDaysHandler)
    .trigger("blur")
  ;
  /**{% endif %}**/

  // Coloca o foco no primeiro campo
  /**{% if authorization.user.groupid < 6 %}**/
  $("input[name='customername']")
    .focus()
  ;
  /**{% else %}**/
  $("input[name='subsidiaryname']")
    .focus()
  ;
  /**{% endif %}**/
});


// =====================================================[ Options ]=====

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
  ajax: {
    url: "{{ path_for(getEntityDataCompletion.URL) }}",
    type: "{{ getEntityDataCompletion.method }}",
    data: function(params) {
      params.type = 'customer';
      params.detailed = true;
      params.notIncludeAnyRegister = true;
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

    // Modifica o rótulo do campo unidade/filial/associado
    if (suggestion.cooperative) {
      $("label[for='subsidiarytitle']")
        .text("Associado ou unidade ao qual o veículo está vinculado")
      ;
      $("input[name='entitytypeid']")
        .val(3)
      ;
    } else {
      if (suggestion.juridicalperson) {
        $("label[for='subsidiarytitle']")
          .text("Unidade/filial ao qual o veículo está vinculado")
        ;
        $("input[name='entitytypeid']")
          .val(1)
        ;
      } else {
        $("label[for='subsidiarytitle']")
          .text("Titular ou dependente ao qual o veículo está vinculado")
        ;
        $("input[name='entitytypeid']")
          .val(2)
        ;
      }
    }

    // Habilita o campo que permite indicar se o cliente é o
    // proprietário deste veículo
    $("input[name='customeristheowner']")
      .closest('div')
      .removeClass("disabled")
      .removeClass("blocked")
    ;

    // Força a atualização dos dados do proprietário, se necessário
    if ($("input[name='_customeristheowner']").is(":checked")) {
      // Forçamos a atualização dos dados do proprietário
      getCustomerDataHandler();
    }

    /**{% if formMethod == 'PUT' %}**/
    var
      originalCustomerID   = $("input[name='originalcustomerid']").val(),
      originalSubsidiaryID = $("input[name='originalsubsidiaryid']").val()
    ;

    if ( (originalCustomerID == suggestion.id) &&
         (originalSubsidiaryID == suggestion.subsidiaryid) ) {
      // Precisamos bloquear a mudança de titularidade dos
      // rastreadores vinculados, então escondemos os campos
      $("div.transfer")
        .slideUp()
      ;
    } else {
      // Precisamos habilitar a mudança de titularidade dos
      // rastreadores vinculados, então exibimos os campos
      $("div.transfer")
        .slideDown()
      ;

      // Analisamos as informações dos equipamentos instalados
      for (var equipmentNumber = 0; equipmentNumber < equipmentsCount; equipmentNumber++) {
        // Analisamos se o proprietário do veículo também era o
        // pagador deste equipamento
        var
          customerPayerID = $("input[name='equipments[" + equipmentNumber + "][customerpayerid]']").val(),
          subsidiaryPayerID = $("input[name='equipments[" + equipmentNumber + "][subsidiarypayerid]']").val()
        ;

        if ( (originalCustomerID === customerPayerID) &&
             (originalSubsidiaryID === subsidiaryPayerID) ) {
          // Modificamos o nome do cliente pagante também
          $("input[name='equipments[" + equipmentNumber + "][customerpayername]']")
            .val(suggestion.name)
          ;
          $("input[name='equipments[" + equipmentNumber + "][customerpayerid]']")
            .val(suggestion.id)
          ;
          $("input[name='equipments[" + equipmentNumber + "][subsidiarypayername]']")
            .val(suggestion.subsidiaryname)
          ;
          $("input[name='equipments[" + equipmentNumber + "][subsidiarypayerid]']")
            .val(suggestion.subsidiaryid)
          ;

          // Modifica o rótulo do campo unidade/filial/associado
          if (suggestion.juridicalperson) {
            $("label[for='equipments[" + equipmentNumber + "][subsidiarypayername]']")
              .text("Unidade/filial")
            ;
          } else {
            $("label[for='equipments[" + equipmentNumber + "][subsidiarypayername]']")
              .text("Titular ou dependente")
            ;
          }

          // Limpamos os dados de contrato
          emptyContractData(equipmentNumber);

          // Liberamos os campos para definir a situação do contrato
          // anterior
          $("input[name='_equipments[" + equipmentNumber + "][terminate]']")
            .prop('disabled', false)
            .closest('div')
            .removeClass("disabled")
          ;
          $("input[name='_equipments[" + equipmentNumber + "][notclose]']")
            .prop('disabled', false)
            .closest('div')
            .removeClass("disabled")
          ;
          $("input[name='_equipments[" + equipmentNumber + "][notchargeloyaltybreak]']")
            .prop('disabled', false)
            .closest('div')
            .removeClass("disabled")
          ;
        } else {
          // Bloqueamos os campos para definir a situação do contrato
          // anterior, já que não ouve alteração ainda
          $("input[name='_equipments[" + equipmentNumber + "][terminate]']")
            .prop('disabled', true)
            .closest('div')
            .addClass("disabled")
          ;
          $("input[name='_equipments[" + equipmentNumber + "][notclose]']")
            .prop('disabled', true)
            .closest('div')
            .addClass("disabled")
          ;
          $("input[name='_equipments[" + equipmentNumber + "][notchargeloyaltybreak]']")
            .prop('disabled', true)
            .closest('div')
            .addClass("disabled")
          ;
        }
      }
    }
    /**{% endif %}**/
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
    $("input[name='subsidiarypayerid']")
      .val(0)
    ;
    $("input[name='subsidiaryname']")
      .val("")
    ;

    // Desabilita o campo de indicação de que o cliente é o
    // proprietário do veículo
    $("input[name='customeristheowner']")
      .closest('div')
      .addClass("disabled")
      .addClass("blocked")
    ;

    // Verifica se o campo de indicação de que o cliente é o
    // proprietário do veículo estava marcado
    if ($("input[name='customeristheowner']").val() == "true") {
      // Limpa os dados de cliente das informações de proprietário
      // do veículo
      $("input[name='_customeristheowner']")
        .prop( "checked", false )
      ;
      $("input[name='customeristheowner']")
        .val("false")
      ;

      emptyOwnerData();

      // Habilita os campos de entrada do proprietário do veículo
      $("div.ownerdata")
        .find('div.field')
        .removeClass("disabled")
        .removeClass("blocked")
      ;
    }
  }
};
/**{% endif %}**/

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
      params.entityID = $("input[name='customerid']").val();
    }
  },
  onFormatResult: function(searchTerm, suggestion) {
    return ''
      + '<div class="content">'
      +   '<div class="title">'
      +   ((suggestion.affiliated)
             ? '<span style="color: DarkOrange; font-style: italic;">Associado: </span>'
             : '')
      + suggestion.name + '<br>'
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
    if (suggestion.affiliated) {
      // Os afiliados, no caso de associações, não possuem contrato
      // direto, então usamos o contrato da associação
      $("input[name='subsidiarypayerid']")
        .val(suggestion.subsidiarypayerid)
      ;
    } else {
      $("input[name='subsidiarypayerid']")
        .val(suggestion.subsidiaryid)
      ;
    }

    // Força a atualização dos dados do proprietário, se necessário
    if ($("input[name='_customeristheowner']").is(":checked")) {
      // Forçamos a atualização dos dados do proprietário
      getCustomerDataHandler();
    }


    /**{% if formMethod == 'PUT' %}**/
    var
      customerID           = $("input[name='customerid']").val(),
      customerName         = $("input[name='customername']").val(),
      originalCustomerID   = $("input[name='originalcustomerid']").val(),
      originalSubsidiaryID = $("input[name='originalsubsidiaryid']").val()
    ;

    if ( (originalCustomerID == customerID) &&
         (originalSubsidiaryID == suggestion.id) ) {
      // Precisamos bloquear a mudança de titularidade dos
      // rastreadores vinculados, então escondemos os campos
      $("div.transfer")
        .slideUp()
      ;
    } else {
      // Precisamos habilitar a mudança de titularidade dos
      // rastreadores vinculados, então exibimos os campos
      $("div.transfer")
        .slideDown()
      ;

      // Analisamos as informações dos equipamentos instalados
      for (var equipmentNumber = 0; equipmentNumber < equipmentsCount; equipmentNumber++) {
        // Analisamos se o proprietário do veículo também era o
        // pagador deste equipamento
        var
          customerPayerID = $("input[name='equipments[" + equipmentNumber + "][customerpayerid]']").val(),
          subsidiaryPayerID = $("input[name='equipments[" + equipmentNumber + "][subsidiarypayerid]']").val()
        ;

        if ( (originalCustomerID === customerPayerID) &&
             (originalSubsidiaryID === subsidiaryPayerID) ) {
          // Modificamos o nome do cliente pagante também
          $("input[name='equipments[" + equipmentNumber + "][customerpayername]']")
            .val(customerName)
          ;
          $("input[name='equipments[" + equipmentNumber + "][customerpayerid]']")
            .val(customerID)
          ;
          $("input[name='equipments[" + equipmentNumber + "][subsidiarypayername]']")
            .val(suggestion.name)
          ;
          $("input[name='equipments[" + equipmentNumber + "][subsidiarypayerid]']")
            .val(suggestion.id)
          ;

          // Modifica o rótulo do campo unidade/filial/associado
          if (suggestion.juridicalperson) {
            $("label[for='equipments[" + equipmentNumber + "][subsidiarypayername]']")
              .text("Unidade/filial")
            ;
          } else {
            $("label[for='equipments[" + equipmentNumber + "][subsidiarypayername]']")
              .text("Titular ou dependente")
            ;
          }

          // Limpamos os dados de contrato
          emptyContractData(equipmentNumber);

          // Liberamos os campos para definir a situação do contrato
          // anterior
          $("input[name='_equipments[" + equipmentNumber + "][terminate]']")
            .prop('disabled', false)
            .closest('div')
            .removeClass("disabled")
          ;
          $("input[name='_equipments[" + equipmentNumber + "][notclose]']")
            .prop('disabled', false)
            .closest('div')
            .removeClass("disabled")
          ;
          $("input[name='_equipments[" + equipmentNumber + "][notchargeloyaltybreak]']")
            .prop('disabled', false)
            .closest('div')
            .removeClass("disabled")
          ;
        }
      }
    }
    /**{% endif %}**/
  },
  onInvalidateSelection: function () {
    // Não conseguiu localizar uma unidade/filial com base nos
    // valores informados, então limpa os dados atuais
    $("input[name='subsidiaryid']")
      .val(0)
    ;
    $("input[name='subsidiarypayerid']")
      .val(0)
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
    data: function(params) {
      params.type = 'payer';
      params.detailed = true;
      params.onlyCustomers = true;
      params.notIncludeAnyRegister = true;
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
    var
      // Recupera a linha do equipamento
      equipmentNumber = parseInt($(element).attr("equipmentNumber"))
    ;

    // Armazena o ID do item selecionado
    $("input[name='equipments[" + equipmentNumber + "][customerpayerid]']")
      .val(suggestion.id)
    ;
    $("input[name='equipments[" + equipmentNumber + "][subsidiarypayername]']")
      .val(suggestion.subsidiaryname)
    ;
    $("input[name='equipments[" + equipmentNumber + "][subsidiarypayerid]']")
      .val(suggestion.subsidiaryid)
    ;
    $("input[name='equipments[" + equipmentNumber + "][payerentitytypeid]']")
      .val(suggestion.entitytypeid)
    ;

    var
      originalCustomerPayerID   = $("input[name='equipments[" + equipmentNumber + "][originalcustomerpayerid]']").val(),
      originalSubsidiaryPayerID = $("input[name='equipments[" + equipmentNumber + "][originalsubsidiarypayerid]']").val()
    ;

    if ( (originalCustomerPayerID == suggestion.id) &&
         (originalSubsidiaryPayerID == suggestion.subsidiaryid) ) {
      // Bloqueamos os campos para definir a situação do contrato
      // anterior
      $("input[name='_equipments[" + equipmentNumber + "][terminate]']")
        .prop('disabled', true)
        .closest('div')
        .addClass("disabled")
      ;
      $("input[name='_equipments[" + equipmentNumber + "][notclose]']")
        .prop('disabled', true)
        .closest('div')
        .addClass("disabled")
      ;
      $("input[name='_equipments[" + equipmentNumber + "][notchargeloyaltybreak]']")
        .prop('disabled', true)
        .closest('div')
        .addClass("disabled")
      ;
    } else {
      // Liberamos os campos para definir a situação do contrato
      // anterior
      $("input[name='_equipments[" + equipmentNumber + "][terminate]']")
        .prop('disabled', false)
        .closest('div')
        .removeClass("disabled")
      ;
      $("input[name='_equipments[" + equipmentNumber + "][notclose]']")
        .prop('disabled', false)
        .closest('div')
        .removeClass("disabled")
      ;
      $("input[name='_equipments[" + equipmentNumber + "][notchargeloyaltybreak]']")
        .prop('disabled', false)
        .closest('div')
        .removeClass("disabled")
      ;
    }

    // Modifica o rótulo do campo unidade/filial/associado
    if (suggestion.juridicalperson) {
      $("label[for='equipments[" + equipmentNumber + "][subsidiarypayername]']")
        .text("Unidade/filial")
      ;
    } else {
      $("label[for='equipments[" + equipmentNumber + "][subsidiarypayername]']")
        .text("Titular ou dependente")
      ;
    }

    // Limpamos os dados de contrato
    emptyContractData(equipmentNumber);
  },
  onInvalidateSelection: function () {
    // Não conseguiu localizar um cliente com base nos valores
    // informados, então limpa os dados atuais
    var
      // Recupera a linha do equipamento
      equipmentNumber = parseInt($(element).attr("equipmentNumber"))
    ;

    $("input[name='equipments[" + equipmentNumber + "][customerpayerid]']")
      .val(0)
    ;
    $("input[name='equipments[" + equipmentNumber + "][subsidiarypayername]']")
      .val('')
    ;
    $("input[name='equipments[" + equipmentNumber + "][subsidiarypayerid]']")
      .val(0)
    ;

    // Limpamos os dados de contrato
    emptyContractData(equipmentNumber);
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
    data: function(params) {
      params.type = 'subsidiary';
      params.onlyCustomers = true;
      params.entityID = $("input[name='customerpayerid']").val();
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
    var
      // Recupera a linha do equipamento
      equipmentNumber = parseInt($(element).attr("equipmentNumber"))
    ;

    // Armazena o ID do item selecionado
    $("input[name='equipments[" + equipmentNumber + "][subsidiarypayerid]']")
      .val(suggestion.id)
    ;

    // Limpamos os dados de contrato
    emptyContractData(equipmentNumber);
  },
  onInvalidateSelection: function () {
    // Não conseguiu localizar uma unidade/filial com base nos
    // valores informados, então limpa os dados atuais
    var
      // Recupera a linha do equipamento
      equipmentNumber = parseInt($(element).attr("equipmentNumber"))
    ;

    $("input[name='equipments[" + equipmentNumber + "][subsidiarypayerid]']")
      .val(0)
    ;

    // Limpamos os dados de contrato
    emptyContractData(equipmentNumber);
  }
};

// As opções para o componente de autocompletar o nome do modelo do
// veículo
var vehicleModelNameCompletionOptions = {
  autoSelectFirst: true,
  searchOnFocus: false,
  showClearValue: true,
  cache: false,
  maxResults: 20,
  preventBadQueries : false,
  ajax: {
    url: "{{ path_for(getVehicleModelCompletion.URL) }}",
    type: "{{ getVehicleModelCompletion.method }}",
    data: function(params) {
      params.vehicleTypeID = 0;
    }
  },
  onFormatResult: function(searchTerm, suggestion) {
    return ''
      + '<div class="content">'
      +   '<div class="title">'
      +     '<span style="color: #3c4c5c; font-weight: bold;font-style: italic;">'
      +       suggestion.vehiclebrandname
      +     '</span> '
      +     suggestion.name + '<br>'
      +     '<span style="color: CornflowerBlue; font-style: italic;">'
      +     ( (suggestion.vehiclesubtypeid > 0)
                ? suggestion.vehiclesubtypename
                : suggestion.vehicletypename )
      +     '</span>'
      +   '</div>'
      + '</div>'
    ;
  },
  onSelect: function (element, suggestion) {
    // Armazena o ID do item selecionado
    $("input[name='vehiclemodelid']")
      .val(suggestion.id)
    ;

    // Seta o tipo de veículo
    $("input[name='vehicletypeid']")
      .closest('div')
      .dropdown('set selected', suggestion.vehicletypeid)
    ;

    // Seta o subtipo de veículo
    var
      $dropdown = $("input[name='vehiclesubtypeid']")
        .closest('div.ui.search.selection.dropdown')
    ;

    // Ajustamos os subtipos de veículos ofertados
    $dropdown
      .dropdown('change values', vehicleSubtypes[suggestion.vehicletypeid])
    ;

    // Definimos o subtipo informado
    $dropdown
      .dropdown('set selected', suggestion.vehiclesubtypeid)
    ;
    $dropdown
      .dropdown('refresh')
    ;

    // Seta a marca do veículo
    $("input[name='vehiclebrandname']")
      .val(suggestion.vehiclebrandname)
    ;
    $("input[name='vehiclebrandid']")
      .val(suggestion.vehiclebrandid)
    ;

    // Travamos os campos relacionados com o modelo de veículo para não
    // permitir modificações destes valores, já que eles já foram
    // obtidos através da informação de modelo
    vehicleModelHandler();
  },
  onInvalidateSelection: function () {
    // Não conseguiu localizar um modelo de veículo com base nos valores
    // informados, então limpa os dados atuais
    $("input[name='vehiclemodelid']")
      .val(0)
    ;

    // Limpa o tipo de veículo
    $("input[name='vehicletypeid']")
      .closest('div')
      .dropdown('clear')
    ;

    // Limpa o subtipo de veículo
    var
      $dropdown = $("input[name='vehiclesubtypeid']")
        .closest('div.ui.search.selection.dropdown')
    ;

    // Ajustamos os subtipos de veículos ofertados
    $dropdown
      .dropdown('change values', [{ id: 0, name: 'Não informado' }])
    ;
    $dropdown
      .dropdown('clear')
    ;
    $dropdown
      .dropdown('refresh')
    ;

    // Limpa a marca do veículo
    $("input[name='vehiclebrandname']")
      .val("")
    ;
    $("input[name='vehiclebrandid']")
      .val(0)
    ;

    // Destravamos os campos relacionados com o modelo de veículo
    // para permitir modificações destes valores
    vehicleModelHandler();
  }
};

// As opções para o componente de autocompletar o nome da marca do
// veículo
var brandNameCompletionOptions = {
  autoSelectFirst: true,
  searchOnFocus: false,
  cache: false,
  maxResults: 20,
  preventBadQueries : false,
  ajax: {
    url: "{{ path_for(getVehicleBrandCompletion.URL) }}",
    type: "{{ getVehicleBrandCompletion.method }}",
    data: function(params) {
      params.vehicleTypeID = $("input[name='vehicletypeid']").val();
    }
  },
  onSelect: function (element, suggestion) {
    // Armazena o ID do item selecionado
    $("input[name='vehiclebrandid']")
      .val(suggestion.id)
    ;
  },
  onInvalidateSelection: function () {
    // Não conseguiu localizar uma marca de veículo com base nos
    // valores informados, então limpa os dados atuais
    $("input[name='vehiclebrandid']")
      .val(0)
    ;
  }
};

// As opções para o componente de autocompletar o nome da cidade
var cityNameCompletionOptions = {
  autoSelectFirst: true,
  searchOnFocus: false,
  ajax: {
    url: "{{ path_for(getCityNameCompletion.URL) }}",
    type: "{{ getCityNameCompletion.method }}",
    data: function(params) {
      params.state = '';
    }
  },
  onFormatResult: function(searchTerm, suggestion) {
    return ''
      + '<div class="content">'
      +   '<div class="title">' + suggestion.name + ' / '
      +     '<span style="color: CornflowerBlue; font-style: italic;">'
      +       suggestion.state
      +     '</span>'
      +   '</div>'
      + '</div>'
    ;
  },
  onSelect: function (element, suggestion) {
    // Armazena o ID do item selecionado
    $("input[name='cityid']")
      .val(suggestion.id)
    ;
    $("input[name='state']")
      .val(suggestion.state)
    ;
  },
  onInvalidateSelection: function ()
  {
    // Limpa os dados
    $("input[name='cityid']")
      .val(0)
    ;
    $("input[name='state']")
      .val("")
    ;
  }
};

// As opções para o componente de autocompletar o nome da cidade do
// local de permanência do veículo
var anotherCityNameCompletionOptions = {
  autoSelectFirst: true,
  searchOnFocus: false,
  ajax: {
    url: "{{ path_for(getCityNameCompletion.URL) }}",
    type: "{{ getCityNameCompletion.method }}",
    data: function(params) {
      params.state = '';
    }
  },
  onFormatResult: function(searchTerm, suggestion) {
    return ''
      + '<div class="content">'
      +   '<div class="title">' + suggestion.name + ' / '
      +     '<span style="color: CornflowerBlue; font-style: italic;">'
      +       suggestion.state
      +     '</span>'
      +   '</div>'
      + '</div>'
    ;
  },
  onSelect: function (element, suggestion) {
    // Armazena o ID do item selecionado
    $("input[name='anothercityid']")
      .val(suggestion.id)
    ;
    $("input[name='anotherstate']")
      .val(suggestion.state)
    ;
  },
  onInvalidateSelection: function ()
  {
    // Limpa os dados
    $("input[name='anothercityid']")
      .val(0)
    ;
    $("input[name='anotherstate']")
      .val("")
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
        // Recupera a linha do equipamento
        equipmentNumber = parseInt($(this).children('input').attr("equipmentNumber")),
        $customerID = $("input[name='equipments[" + equipmentNumber + "][customerpayerid]']").val(),
        $subsidiaryID = $("input[name='equipments[" + equipmentNumber + "][subsidiarypayerid]']").val()
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
  onChange: function() {
    var
      // Recupera a linha do equipamento
      equipmentNumber = parseInt($(this).children('input').attr("equipmentNumber"))
    ;

    // Limpamos os dados do item de contrato para permitir a correta
    // seleção dos itens deste contrato
    $("input[name='equipments[" + equipmentNumber + "][installatioid]']")
      .closest('div.ui.dropdown')
      .dropdown('clear')
    ;
    $("input[name='equipments[" + equipmentNumber + "][installatioid]']")
      .closest('div.ui.dropdown')
      .dropdown('change values', [])
    ;
    $("input[name='equipments[" + equipmentNumber + "][installatioid]']")
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
      'includeSuspended': 'false',
      'includeFinish': 'false',
      'contractID': 0
    },
    beforeSend: function(settings) {
      var
        // Recupera a linha do equipamento
        equipmentNumber = parseInt($(this).children('input').attr("equipmentNumber")),
        // Recupera o número do contrato
        $contractID = $("input[name='equipments[" + equipmentNumber + "][contractid]']").val()
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


// ==========================================[ Field Cleaners ]=====

/**{% if formMethod == 'PUT' %}**/
// Limpa os campos relacionados com o contrato
var emptyContractData = function (equipmentNumber) {
  // Limpamos os contratos e os respectivos itens
  $("input[name='equipments[" + equipmentNumber + "][contractid]']")
    .closest('div.ui.dropdown')
    .dropdown('clear')
  ;
  $("input[name='equipments[" + equipmentNumber + "][contractid]']")
    .closest('div.ui.dropdown')
    .dropdown('change values', [])
  ;
  $("input[name='equipments[" + equipmentNumber + "][contractid]']")
    .closest('div.ui.dropdown')
    .dropdown('refresh')
  ;
  $("input[name='equipments[" + equipmentNumber + "][installationid]']")
    .closest('div.ui.dropdown')
    .dropdown('clear')
  ;
  $("input[name='equipments[" + equipmentNumber + "][installationid]']")
    .closest('div.ui.dropdown')
    .dropdown('change values', [])
  ;
  $("input[name='equipments[" + equipmentNumber + "][installationid]']")
    .closest('div.ui.dropdown')
    .dropdown('refresh')
  ;
};
/**{% endif %}**/

// Limpa os campos relacionados com o proprietário do veículo
var emptyOwnerData = function () {
  if ($("#ownerClear").hasClass('disabled')) {
    return false;
  }

  $("div.ownerdata")
    .find('input')
    .val("")
  ;
  $("input[name='regionaldocumenttype']")
    .closest('div')
    .dropdown('clear')
  ;
  $("input[name='cityid']")
    .val(0)
  ;
  $("input[name='state']")
    .val("")
  ;
  $("input[name='nationalregister']")
    .trigger("blur")
  ;
};


// ====================================================[ Handlers ]=====

/**
 * Faz o tratamento dos campos ligados ao modelo do veículo, permitindo
 * ou não a modificação do tipo, subtipo e marca do veículo quando o
 * usuário indicar um modelo válido.
 *
 * @return void
 */
function vehicleModelHandler() {
  var
    vehicleModelID   = $("input[name='vehiclemodelid']").val()
  ;

  if (parseInt(vehicleModelID) > 0) {
    // Travamos a edição dos campos de tipo de veículo e marca para não
    // permitir modificações destes valores pelo usuário, já que eles
    // foram obtidos através da informação do modelo de veículo
    $("input[name='vehicletypeid']")
      .closest('div')
      .addClass("disabled")
      .addClass("readonly")
      .find('input.search')
      .prop('readonly', true)
    ;
    $("input[name='vehiclebrandname']")
      .prop('readonly', true)
      .closest('div')
      .addClass("readonly")
    ;
    $("input[name='vehiclebrandname']")
      .closest('div')
      .find('i.search.icon')
      .addClass("hidden")
    ;
  } else {
    // Destravamos a edição dos campos de tipo de veículo e marca para
    // permitir modificações destes valores pelo usuário
    $("input[name='vehicletypeid']")
      .closest('div')
      .removeClass("disabled")
      .removeClass("readonly")
      .find('input.search')
      .prop('readonly', false)
    ;
    $("input[name='vehiclebrandname']")
      .prop('readonly', false)
      .closest('div')
      .removeClass("readonly")
    ;
    $("input[name='vehiclebrandname']")
      .closest('div')
      .find('i.search.icon')
      .removeClass("hidden")
    ;
  }

  var
    vehicleSubtypeID = parseInt($("input[name='vehiclesubtypeid']").val())
  ;
  if (vehicleSubtypeID > 0) {
    // Bloqueamos o subtipo do tipo de veículo
    $("input[name='vehiclesubtypeid']")
      .closest('div')
      .addClass("disabled")
      .addClass("readonly")
      .find('input.search')
      .prop('readonly', true)
    ;
  } else {
    // Mantemos o subtipo do tipo de veículo editável
    $("input[name='vehiclesubtypeid']")
      .closest('div')
      .removeClass("disabled")
      .removeClass("readonly")
      .find('input.search')
      .prop('readonly', false)
    ;
  }
}

/**
 * Trata as modificações do tipo de veículo selecionado.
 *
 * @return void
 */
function vehicleTypeHandler() {
  var
    typeID = parseInt($(this).val())
  ;

  if (typeID > 0) {
    // Limpa o campo de seleção da marca do veículo
    $("input[name='vehiclebrandname']")
      .val('')
    ;
    $("input[name='vehiclebrandname']")
      .autocomplete('clear')
    ;
    $("input[name='vehiclebrandid']")
      .val(0)
    ;

    var
      $dropdown = $("input[name='vehiclesubtypeid']")
        .closest('div.ui.search.selection.dropdown'),
      firstSubtypeID = vehicleSubtypes[typeID][0]['value']
    ;

    // Ajustamos os subtipos de veículos ofertados
    $dropdown
      .dropdown('change values', vehicleSubtypes[typeID])
    ;

    // Definimos o primeiro subtipo disponível
    $dropdown
      .dropdown('set selected', firstSubtypeID)
    ;
    $dropdown
      .dropdown('refresh')
    ;
  }
}


// ----------------------------------------------[ Owner Handlers ]-----

/**
 * Faz o tratamento do campo que indica se o cliente é o dono do veículo
 * ou é um terceiro.
 *
 * @return void
 */
function customerIsTheOwnerHandler() {
  toggleOwnerData($(this).is(":checked"));
}

/**
 * Lida com a exibição dos campos do dono do veículo.
 *
 * @param bool customerIsTheOwner
 *   O indicativo se o cliente é o proprietário do veículo
 *
 * @return void
 */
function toggleOwnerData(customerIsTheOwner) {
  if (customerIsTheOwner) {
    // Recupera os dados do cliente
    getCustomerDataHandler();

    // Esconde os campos com os dados do proprietário
    //$("div.ownerdata")
    //  .hide()
    //;

    // Esconde os campos com os números de telefone
    $("div.owner.block")
      .hide()
    ;

    // Desabilita a entrada dos dados do proprietário do veículo
    $("div.ownerdata")
      .find('div.field')
      .addClass("disabled")
      .addClass("blocked")
    ;

    $("div.ownerdata")
      .find("input[type='text']")
      .prop('readonly', true)
      .closest('div')
      .addClass("readonly")
    ;

    // Desabilita a limpeza dos dados do proprietário
    $("#ownerClear")
      .addClass("disabled")
    ;
    $("#ownerRestore")
      .addClass("disabled")
    ;

    // Desabilita a seleção do tipo de documento
    $("input[name='regionaldocumenttype']")
      .closest('div')
      .addClass("disabled")
      .addClass("readonly")
      .find('input.search')
      .prop('readonly', true)
    ;
    $("input[name='regionaldocumenttype']")
      .closest('div')
      .find('i.search.icon')
      .addClass("hidden")
    ;

    // Desabilita a seleção da cidade
    $("input[name='cityname']")
      .closest('div')
      .addClass("disabled")
      .addClass("readonly")
      .find('input.search')
      .prop('readonly', true)
    ;
    $("input[name='cityname']")
      .closest('div')
      .find('i.search.icon')
      .addClass("hidden")
    ;

    // Verifica se o radiobox para a opção para 'nas dependências do
    // proprietário do veículo' está selecionado
    var
      placeOfStay = $('input[name="placeOfStay"]:checked').val()
    ;
    if (placeOfStay === "atsameowneraddress") {
      $('input[name="placeOfStay"][value="atsamecustomeraddress"]')
        .prop("checked", true)
      ;
    }

    // Desabilita o radiobox para a opção para 'nas dependências do
    // proprietário do veículo', já que não temos esta informação
    $('input[name="placeOfStay"][value="atsameowneraddress"]')
      .attr("disabled", true)
    ;
  } else {
    // Restaura os dados originais do proprietário anteriormente
    // armazenados
    getOwnerDataHandler();

    //Exibe os campos com os dados do proprietário
    //$("div.ownerdata")
    //  .show()
    //;

    // Exibe os campos com os números de telefone
    $("div.owner.block")
      .show()
    ;

    // Habilita a entrada do proprietário
    $("div.ownerdata")
      .find('div.field')
      .removeClass("disabled")
      .removeClass("blocked")
    ;

    $("div.ownerdata")
      .find("input[type='text']")
      .prop('readonly', false)
      .closest('div')
      .removeClass("readonly")
    ;

    // Habilita a limpeza dos dados do proprietário
    $("#ownerClear")
      .removeClass("disabled")
    ;
    $("#ownerRestore")
      .removeClass("disabled")
    ;

    // Habilita a seleção do tipo de documento
    $("input[name='regionaldocumenttype']")
      .closest('div')
      .removeClass("disabled")
      .removeClass("readonly")
      .find('input.search')
      .prop('readonly', false)
    ;
    $("input[name='regionaldocumenttype']")
      .closest('div')
      .find('i.search.icon')
      .removeClass("hidden")
    ;

    // Habilita a seleção da cidade
    $("input[name='cityname']")
      .closest('div')
      .removeClass("disabled")
      .removeClass("readonly")
      .find('input.search')
      .prop('readonly', false)
    ;
    $("input[name='cityname']")
      .closest('div')
      .find('i.search.icon')
      .removeClass("hidden")
    ;
    
    // Habilita o radiobox para a opção para 'nas dependências do
    // proprietário do veículo', já que agora temos esta informação
    $('input[name="placeOfStay"][value="atsameowneraddress"]')
      .attr("disabled", false)
    ;
  }
}

/**
 * Faz o tratamento do campo que indica o local de permanência do
 * veículo.
 *
 * @return void
 */
function placeOfStayHandler() {
  var
    // Obtemos qual o local onde o veículo permanece
    placeOfStay = $('input[name="placeOfStay"]:checked').val()
  ;

  if (placeOfStay === "atanotheraddress") {
    // Exibe os campos com os dados do local de permanência
    $("div.anotheraddressdata")
      .show()
    ;

    // Habilita a entrada dos dados
    $("div.anotheraddressdata")
      .find('div.field')
      .removeClass("disabled")
      .removeClass("blocked")
    ;

    $("div.anotheraddressdata")
      .find("input[type='text']")
      .prop('readonly', false)
      .closest('div')
      .removeClass("readonly")
    ;

    // Habilita a seleção da cidade
    $("input[name='anothercityname']")
      .closest('div')
      .removeClass("disabled")
      .removeClass("readonly")
      .find('input.search')
      .prop('readonly', false)
    ;
    $("input[name='anothercityname']")
      .closest('div')
      .find('i.search.icon')
      .removeClass("hidden")
    ;
  } else {
    // Esconde os campos com os dados do local de permanência
    $("div.anotheraddressdata")
      .hide()
    ;

    // Desabilita a entrada dos dados
    $("div.anotheraddressdata")
      .find('div.field')
      .addClass("disabled")
      .addClass("blocked")
    ;

    $("div.anotheraddressdata")
      .find("input[type='text']")
      .prop('readonly', true)
      .closest('div')
      .addClass("readonly")
    ;

    // Desabilita a seleção da cidade
    $("input[name='anothercityname']")
      .closest('div')
      .addClass("disabled")
      .addClass("readonly")
      .find('input.search')
      .prop('readonly', true)
    ;
    $("input[name='anothercityname']")
      .closest('div')
      .find('i.search.icon')
      .addClass("hidden")
    ;
  }
}

/**
 * O restaurador dos dados do cliente quando o usuário desmarca a caixa
 * que indica que o veículo pertence ao próprio cliente, trazendo os
 * dados anteriormente armazenados.
 *
 * @return void
 */
function getOwnerDataHandler() {
  if ($("#ownerRestore").hasClass('disabled')) {
    return false;
  }
  
  $("input[name='ownername']")
    .val("{{ getValue('ownername') }}")
  ;
  $("input[name='regionaldocumenttype']")
    .closest('div')
    .dropdown('set selected', parseInt("{{ getValue('regionaldocumenttype') }}"))
  ;
  $("input[name='regionaldocumenttype']")
    .closest('div')
    .find('input.search')
    .val("")
    .trigger("change")
  ;
  $("input[name='regionaldocumenttype']")
    .closest('div')
    .removeClass("disabled")
    .removeClass("readonly")
    .find('input.search')
    .prop('readonly', false)
  ;
  $("input[name='regionaldocumentnumber']")
    .val("{{ getValue('regionaldocumentnumber') }}")
  ;
  $("input[name='regionaldocumentstate']")
    .val("{{ getValue('regionaldocumentstate') }}")
  ;
  $("input[name='nationalregister']")
    .val("{{ getValue('nationalregister') }}")
    .trigger("blur")
  ;
  $("input[name='address']")
    .val("{{ getValue('address') }}")
  ;
  $("input[name='streetnumber']")
    .val("{{ getValue('streetnumber') }}")
  ;
  $("input[name='complement']")
    .val("{{ getValue('complement') }}")
  ;
  $("input[name='district']")
    .val("{{ getValue('district') }}")
  ;
  $("input[name='postalcode']")
    .val("{{ getValue('postalcode') }}")
  ;
  $("input[name='cityname']")
    .val("{{ getValue('cityname') }}")
  ;
  $("input[name='cityname']")
    .prop('readonly', false)
    .removeClass("disabled")
    .closest('div')
    .removeClass("readonly")
  ;
  $("input[name='cityid']")
    .val(parseInt("{{ getValue('cityid') }}"))
  ;
  $("input[name='ibgecode']")
    .val("{{ getValue('ibgecode') }}")
  ;
  $("input[name='state']")
    .val("{{ getValue('state') }}")
  ;
}

/**
 * O recuperador dos dados do cliente quando o usuário marca a caixa que
 * indica que o veículo pertence ao próprio cliente.
 *
 * @return void
 */
function getCustomerDataHandler() {
  // Recupera o ID do cliente e filial
  var
    customerID   = $("input[name='customerid']").val(),
    subsidiaryID = $("input[name='subsidiaryid']").val()
  ;

  // Verifica se foi selecionado um cliente
  if ((customerID > 0) && (subsidiaryID > 0)) {
    // Preenche os campos com "..." enquanto consulta o Web Service.
    $("div.ownerdata")
      .find('input')
      .val("...")
      .trigger("change")
    ;
    $("input[name='regionaldocumenttype']")
      .closest('div')
      .dropdown('clear')
    ;
    $("input[name='cityid']")
      .val(0)
    ;
    $("input[name='state']")
      .val("")
    ;

    // Consulta o Web Service para obter os dados do proprietário
    // Recupera os nomes das entidades
    var
      url  = "{{ path_for(getEntityDataCompletion.URL) }}"
    ;
    requestJSONData(url, { searchTerm: null, type: 'ownerdata',
      customerID: customerID, subsidiaryID: subsidiaryID },
    function (ownerdata) {
      var
        otherFields = [
          'regionaldocumentnumber',
          'regionaldocumentstate',
          'nationalregister',
          'address',
          'district',
          'streetnumber',
          'complement',
          'postalcode',
          'cityid',
          'cityname',
          'state',
          'email',
          'phonenumber'
        ]
      ;
      // Conseguiu recuperar os dados do proprietário, então
      // atualiza o formulário
      $ownerName = '';
      if (ownerdata.cooperative && ownerdata.affiliated) {
        $ownerName = ownerdata.subsidiaryname;
      } else {
        if (ownerdata.juridicalperson) {
          $ownerName = ownerdata.entityname + ' / '
            + ownerdata.subsidiaryname
          ;
        } else {
          $ownerName = ownerdata.subsidiaryname;
        }
      }
      $("input[name='ownername']")
        .val($ownerName)
      ;
      $("input[name='regionaldocumenttype']")
        .closest('div')
        .dropdown('set selected', ownerdata.regionaldocumenttype)
      ;
      $("input[name='regionaldocumenttype']")
        .closest('div')
        .find('input.search')
        .val("")
        .trigger("change")
      ;
      $("input[name='regionaldocumenttype']")
        .closest('div')
        .addClass("disabled")
        .addClass("readonly")
        .find('input.search')
        .prop('readonly', true)
      ;
      otherFields.forEach(function(field) {
        $("input[name='" + field + "']")
          .val(ownerdata[field])
        ;
      });
      $("input[name='cityname']")
        .addClass("disabled")
        .prop('readonly', true)
        .closest('div')
        .addClass("readonly")
      ;
    },
    function ()
    {
      // Não conseguiu localizar os dados do proprietário com base
      // nos valores informados, então limpa os dados atuais
      emptyOwnerData();
    });
  } else {
    // Não conseguimos recuperar os dados do proprietário, então
    // deixa em branco
    emptyOwnerData();

    // Desmarca o campo "O cliente é o proprietário deste veículo"
    $("input[name='_customeristheowner']")
      .click()
    ;

    // Exibe a mensagem de erro
    errorDialog('Erro', 'Selecione o cliente do qual este ' +
      'veículo pertence primeiro.')
    ;
  }
}

/**
 * O manipulador do campo de autocompletar o CEP do proprietário do
 * veículo.
 *
 * @return void
 */
function postalCodeHandler() {
  var
    currentPostalCode = $(this).val()
  ;

  // Ignora qualquer modificação se estivermos em um campo somente
  // leitura
  if ($(this).prop('readonly')) {
    return;
  }

  // Verifica se o CEP é válido
  if (currentPostalCode.length > 0) {
    // Remove espaços
    currentPostalCode = currentPostalCode.replace(/^s+|s+$/g, '');

    // Verifica se o CEP está no formato válido
    var cepER = /^[0-9]{5}-[0-9]{3}$/;
    if (!cepER.test(currentPostalCode)) {
      // Não foi fornecido um CEP válido, então alerta o usuário
      warningDialog('Desculpe', 'O CEP informado é inválido.');

      return;
    }
  } else {
    // Não foi fornecido um CEP, então ignora
    return;
  }

  // Verifica se o valor foi alterado...
  if ($(this).prop('currentPostalCode') !== $(this).val()) {
    // Atualiza o valor armazenado
    $(this)
      .prop('currentPostalCode', $(this).val())
    ;

    // Recupera o CEP preenchido sem pontuação
    var
      postalCode = $(this).val().replace(/-/g, ''),
      textFields = [
        'address',
        'district',
        'cityname',
        'state'
      ]
    ;

    // Verifica se campo possui um CEP preenchido
    if (postalCode != "") {
      // Preenche os campos com "..." enquanto consulta o Web Service
      textFields.forEach(function(field) {
        $("input[name='" + field + "']")
          .val("...")
          .trigger("change")
        ;
      });
      $("input[name='cityid']")
        .val(0)
      ;

      // Consulta o Web Service para obter os dados pelo CEP
      var url = "{{ path_for(getPostalCodeData.URL) }}";
      requestJSONData(url, { postalCode: postalCode },
      function (addressData)
      {
        // Conseguiu localizar um endereço com base nos valores
        // informados, então atualiza o formulário
        textFields.forEach(function(field) {
          $("input[name='" + field + "']")
            .val(addressData[field])
          ;
        });
        $("input[name='cityid']")
          .val(addressData.cityid)
        ;

        // Coloca em foco o campo do número da casa
        $("input[name='streetnumber']")
          .focus()
        ;
      },
      function () {
        // Não conseguiu localizar os dados do endereço com base
        // no CEP informado, então limpa os dados atuais
        textFields.forEach(function(field) {
          $("input[name='" + field + "']")
            .val('')
            .trigger("change")
          ;
        });
        $("input[name='cityid']")
          .val(0)
        ;
      });
    } else {
      // O CEP não foi fornecido
      textFields.forEach(function(field) {
        $("input[name='" + field + "']")
          .val('')
          .trigger("change")
        ;
      });
      $("input[name='cityid']")
        .val(0)
      ;
    }
  }
}

// ---------------------------------------[ Owner Phones Handlers ]-----

// Faz a adição de um telefone
var addOwnerPhone = function() {
  var
    // Determina a quantidade de telefones do proprietário deste veículo
    phoneNumber = ownerPhonesCount
  ;

  // Adiciona um conjunto de campos para o novo telefone
  ownerPhonesCount++;
  $(".ownerPhoneList")
    .append(TemplateEngine(ownerPhoningTemplate, {
      phoneNumber: phoneNumber
    }))
  ;
  
  $('.ui.selection.dropdown')
    .dropdown()
  ;
  $('.ui.menu .ui.dropdown')
    .dropdown({
      on: 'hover'
    })
  ;
  
  // Mascara o campo de telefone
  $("input[name='ownerPhones[" + phoneNumber + "][phonenumber]']")
    .mask({
      type: 'phoneNumber'
    })
  ;
  
  // Coloca em foco o campo inicial do novo telefone
  $("input[name='ownerPhones[" + phoneNumber + "][phonenumber]']")
    .focus()
  ;
};

// Faz a remoção de um telefone
var delOwnerPhone = function(element) {
  var
    // Recupera o ID do telefone
    phoneNumber = parseInt($(element).attr("phoneNumber"))
  ;

  if (phoneNumber > 0) {
    $(element)
      .closest('.owner.phoning.grid')
      .remove()
    ;
  }
};


// ------------------------------------[ Another Address Handlers ]-----

/**
 * O manipulador do campo de autocompletar o CEP do local de permanência
 * do veículo.
 *
 * @return void
 */
function anotherPostalCodeHandler() {
  var
    currentPostalCode = $(this).val()
  ;

  // Ignora qualquer modificação se estivermos em um campo somente
  // leitura
  if ($(this).prop('readonly')) {
    return;
  }

  // Verifica se o CEP é válido
  if (currentPostalCode.length > 0) {
    // Remove espaços
    currentPostalCode = currentPostalCode.replace(/^s+|s+$/g, '');

    // Verifica se o CEP está no formato válido
    var cepER = /^[0-9]{5}-[0-9]{3}$/;
    if (!cepER.test(currentPostalCode)) {
      // Não foi fornecido um CEP válido, então alerta o usuário
      warningDialog('Desculpe', 'O CEP informado é inválido.');

      return;
    }
  } else {
    // Não foi fornecido um CEP, então ignora
    return;
  }

  // Verifica se o valor foi alterado...
  if ($(this).prop('currentPostalCode') !== $(this).val()) {
    // Atualiza o valor armazenado
    $(this)
      .prop('currentPostalCode', $(this).val())
    ;

    // Recupera o CEP preenchido sem pontuação
    var
      postalCode = $(this).val().replace(/-/g, ''),
      textFields = [
        'address',
        'district',
        'cityname',
        'state'
      ]
    ;

    // Verifica se campo possui um CEP preenchido
    if (postalCode != "") {
      // Preenche os campos com "..." enquanto consulta o Web Service
      textFields.forEach(function(field) {
        $("input[name='another" + field + "']")
          .val("...")
          .trigger("change")
        ;
      });
      $("input[name='anothercityid']")
        .val(0)
      ;

      // Consulta o Web Service para obter os dados pelo CEP
      var url = "{{ path_for(getPostalCodeData.URL) }}";
      requestJSONData(url, { postalCode: postalCode },
      function (addressData)
      {
        // Conseguiu localizar um endereço com base nos valores
        // informados, então atualiza o formulário
        textFields.forEach(function(field) {
          $("input[name='another" + field + "']")
            .val(addressData[field])
          ;
        });
        $("input[name='anothercityid']")
          .val(addressData.cityid)
        ;

        // Coloca em foco o campo do número da casa
        $("input[name='anotherstreetnumber']")
          .focus()
        ;
      },
      function () {
        // Não conseguiu localizar os dados do endereço com base
        // no CEP informado, então limpa os dados atuais
        textFields.forEach(function(field) {
          $("input[name='another" + field + "']")
            .val('')
            .trigger("change")
          ;
        });
        $("input[name='anothercityid']")
          .val(0)
          .trigger("change")
        ;
      });
    } else {
      // O CEP não foi fornecido
      textFields.forEach(function(field) {
        $("input[name='another" + field + "']")
          .val('')
          .trigger("change")
        ;
      });
      $("input[name='anothercityid']")
        .val(0)
      ;
    }
  }
}

// -------------------------------------[ Another Phones Handlers ]-----

// Faz a adição de um telefone
var addAnotherPhone = function() {
  var
    // Determina a quantidade de telefones para o outro local
    phoneNumber = anotherPhonesCount
  ;

  // Adiciona um conjunto de campos para o novo telefone
  anotherPhonesCount++;
  $(".anotherPhoneList")
    .append(TemplateEngine(anotherPhoningTemplate, {
      phoneNumber: phoneNumber
    }))
  ;
  
  $('.ui.selection.dropdown')
    .dropdown()
  ;
  $('.ui.menu .ui.dropdown')
    .dropdown({
      on: 'hover'
    })
  ;
  
  // Mascara o campo de telefone
  $("input[name='anotherPhones[" + phoneNumber + "][phonenumber]']")
    .mask({
      type: 'phoneNumber'
    })
  ;
  
  // Coloca em foco o campo inicial do novo telefone
  $("input[name='anotherPhones[" + phoneNumber + "][phonenumber]']")
    .focus()
  ;
};

// Faz a remoção de um telefone
var delAnotherPhone = function(element) {
  var
    // Recupera o ID do telefone
    phoneNumber = parseInt($(element).attr("phoneNumber"))
  ;

  if (phoneNumber > 0) {
    $(element)
      .closest('.another.phoning.grid')
      .remove()
    ;
  }
};


// --------------------------------------[ Block Notices Handlers ]-----

/**
 * Faz o tratamento do campo que indica se devemos bloquear o envio de
 * mensagens de aviso ao proprietário do veículo.
 *
 * @return void
 */
function blockNoticesHandler() {
  toggleNoticesData($(this).is(":checked"));
}

/**
 * Lida com a exibição dos campos de bloqueio de envio de avisos.
 *
 * @param bool blockNotices
 *   O indicativo se devemos bloquear o envio de avisos ao proprietário
 *   deste veículo
 *
 * @return void
 */
function toggleNoticesData(blockNotices) {
  if (blockNotices) {
    // Exibe os campos com os dados do bloqueio
    $("div.noticesdata")
      .show()
    ;
  } else {
    // Esconde os campos com os dados do bloqueio
    $("div.noticesdata")
      .hide()
    ;
  }
}

/**
 * Lida com a quantidade de dias do bloqueio. Faz o tratamento do campo
 * duração para atualizar a data do encerramento do período de bloqueio
 * do envio de avisos.
 *
 * @return void
 */
function amountOfDaysHandler()
{
  var
    duration = $(this).val(),
    days = parseInt(duration)
  ;

  if (duration.trim().isEmpty()) {
    $('input[name="endPeriod"]')
      .val('')
    ;
  } else {
    var
      startAt = $('input[name="blockedstartat"]').val(),
      startDate = (startAt.trim() === '')
        ? new Date(
            new Date().toDateString()
          )
        : new Date(
            startAt.replace(/(\d{2})\/(\d{2})\/(\d{4})/, "$2/$1/$3")
          ),
      endDate = new Date(
        startDate
      ),
      period
    ;

    if (days > 0) {
      // Temos uma quantidade definida de dias para terminar, então a
      // data final é a data inicial acrescida da quantidade de dias
      // informada
      endDate.setDate(
        endDate.getDate() + days
      );

      period = 'Iniciando-se em '
        + startDate.toLocaleDateString('en-GB')
        + ' até '
        + endDate.toLocaleDateString('en-GB')
      ;
    } else {
      period = 'Iniciando-se em '
        + startDate.toLocaleDateString('en-GB')
        + ' sem data para terminar'
      ;
    }

    $('input[name="period"]')
      .val(period)
    ;
  }
}


// ----------------------------------[ Attached Document Handlers ]-----

/**
 * Faz o tratamento do botão de adicionar um novo documento.
 *
 * @return void
 */
function addDocumentHandler() {
  // Faz o acionamento da adição de imagens
  $(".placeholder.segment")
    .hide()
  ;
  $(".fields.hidden")
    .removeClass("hidden")
  ;
  $('input[name="attachments[]"]')
    .click()
  ;
}

/**
 * Exibe uma imagem ampliada na tela.
 *
 * @param object element
 *   O elemento a ser exibido
 *
 * @return void
 */
function showModal(element) {
  // Exclui todos os modais pendentes
  deleteModal();

  var
    attachmentID = $(element).attr("data-src"),
    url          = "{{ buildURL('ERP\\Cadastre\\Vehicles\\Attachments\\Get', { 'operation': 'operation', 'attachmentID': 'attachmentID'}) }}"
  ;
              ;

  $('body')
    .append('<div class="ui basic modal">' +
            '  <div class="content">' +
            '    <img src="'+url+'" width="100%" />' +
            '  </div>' +
            '</div>')
  ;
  $('.ui.basic.modal')
    .modal('show')
  ;
}

/**
 * Remove a janela de modal.
 * 
 * @return void
 */
function deleteModal() {
  $('.ui.basic.modal').each(function() {
    $(this)
      .remove()
    ;
  });
}

/**
 * Recupera o PDF de um anexo.
 *
 * @param int id
 *   O ID do anexo
 *
 * @return window
 */
function getPDFAttachment(id) {
  // Gera um PDF com as informações do documento anexo do veículo
  var
    attachmentID = id,
    url  = "{{ buildURL('ERP\\Cadastre\\Vehicles\\Attachments\\Get\\PDF', {'attachmentID': 'attachmentID'}) }}",
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
  window.open(url, '_blank');
  deleteAttachment(1, 1, 'rtwer');
}

/**
 * Apaga um anexo.
 *
 * @param int index
 *   O índice do anexo
 * @param int id
 *   O ID do anexo
 * @param string filename
 *   O nome do arquivo com o conteúdo do anexo
 *
 * @return void
 */
function deleteAttachment(index, id, filename) {
  var attachmentID = id;

  // Apaga o anexo selecionado
  questionDialog(
    'Remover anexo',
    'Você deseja realmente remover o anexo <b>&ldquo;' + filename +
    '&rdquo;</b> ' + attachmentID + '? Esta ação não poderá ser ' +
    'desfeita.',
    function() {
      // Remove o anexo selecionado
      var url  = "{{ buildURL('ERP\\Cadastre\\Vehicles\\Attachments\\Delete', {'attachmentID': 'attachmentID'}) }}";

      deleteJSONData(url, [],
      function () {
        // Remove a informação do anexo
        $("#attachment" + index)
          .remove()
        ;
      });
    },
    null);
}
