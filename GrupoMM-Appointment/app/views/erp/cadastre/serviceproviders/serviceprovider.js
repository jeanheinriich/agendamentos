/* ----------------------------------------------[ Filiais ]----- */

/**{% set subsidiaries = getValue('subsidiaries') %}**/
var
  // O índice de contagem das unidades/filiais
  subsidiariesCount = parseInt("{{ subsidiaries|length }}"),

  // Os índices de contagem dos telefones e contatos adicionais de cada
  // unidade/filial
  subsidiariesDetails = [],

  // O tipo de entidade selecionada
  entityTypeID = parseInt("{{ getValue('entitytypeid') }}")
;

// Determina a quantidade de telefones, e-mails e contatos em cada
// unidade/filial existente
/**{% for subsidiaryNumber, subsidiary in subsidiaries %}**/
subsidiariesDetails[parseInt("{{ subsidiaryNumber }}")] = {
  phonesCount: parseInt("{{ subsidiary.phones|length }}"),
  emailsCount: parseInt("{{ subsidiary.emails|length }}"),
  contactsCount: parseInt("{{ subsidiary.contacts|length }}")
};
/**{% endfor %}**/

// Carrega o template de uma nova unidade/filial, definindo os valores
// padrões
/**
{% if juridicalperson %}
  {% set defaultDocumentTypeID = 4 %}
{% else %}
  {% set defaultDocumentTypeID = 1 %}
{% endif %}
{% set subsidiaryTemplate %}
  {% include 'erp/cadastre/serviceproviders/subsidiary.twig' with { documentTypes: documentTypes, mailingProfiles: mailingProfiles, phoneTypes: phoneTypes, subsidiaryIndicator: "<%=subsidiaryIndicator%>", subsidiaryNumber: "<%=subsidiaryNumber%>", subsidiary: { subsidiaryid: 0, headoffice: false, regionaldocumenttype: defaultDocumentTypeID, maritalstatusid: 1, genderid: 1, cityid: 0, phones: { 0: { phonetypeid: 1 } }, emails: { 0: { } }, contacts: { } }, editMode: true, enableToggleBlocked: enableToggleBlocked, juridicalperson: juridicalperson, formMethod: formMethod }  %}
{% endset %}
**/
var subsidiaryTemplate = "{{ subsidiaryTemplate|escape('js') }}".replace(/&lt;/g,'<').replace(/&gt;/g,'>');

/* --------------------------------------------[ Telefones ]----- */

// Carrega o template de um novo telefone, definindo os valores padrões
/**
{% set phoningTemplate %}
  {% include 'erp/cadastre/serviceproviders/phone.twig' with { phoneTypes: phoneTypes, subsidiaryNumber: "<%=subsidiaryNumber%>", phoneNumber: '<%=phoneNumber%>', phone: { phoneid: 0, phonetypeid: 1 }, editMode: true }  %}
{% endset %}
**/
var phoningTemplate = "{{ phoningTemplate|escape('js') }}".replace(/&lt;/g,'<').replace(/&gt;/g,'>');

/* ----------------------------------------------[ E-mails ]----- */

// Carrega o template de um novo e-mail, definindo os valores padrões
/**
{% set emailTemplate %}
  {% include 'erp/cadastre/serviceproviders/mailing.twig' with { subsidiaryNumber: "<%=subsidiaryNumber%>", emailNumber: '<%=emailNumber%>', mailing: { mailingid: 0 }, editMode: true }  %}
{% endset %}
**/
var emailTemplate = "{{ emailTemplate|escape('js') }}".replace(/&lt;/g,'<').replace(/&gt;/g,'>');

/* ----------------------------------[ Contatos adicionais ]----- */

// Carrega o template de um novo contato adicional, definindo os valores
// padrões
/**
{% set contactTemplate %}
  {% include 'erp/cadastre/serviceproviders/mailingaddress.twig' with { mailingProfiles: mailingProfiles, phoneTypes: phoneTypes, subsidiaryNumber: "<%=subsidiaryNumber%>", contactNumber: '<%=contactNumber%>', contact: { mailingaddressid: 0, mailingprofileid: defaultMailingProfileID, phonetypeid: 1 }, editMode: true }  %}
{% endset %}
**/
var contactTemplate = "{{ contactTemplate|escape('js') }}".replace(/&lt;/g,'<').replace(/&gt;/g,'>');

/* --------------------------------[ Taxas de deslocamento ]----- */

/**{% set displacements = getValue('displacements') %}**/
// O índice de contagem das taxas de deslocamento
var displacementsCount = parseInt("{{ displacements|length }}");

// O template de um novo valor de deslocamento
/**
{% set displacementTemplate %}
  {% include 'erp/cadastre/serviceproviders/displacement.twig' with { number: '<%=number%>', displacement: { displacementpaidid: 0, distance: 0, value: '0,00' }, editMode: true, readonly: '' }  %}
{% endset %}
**/
var displacementTemplate = "{{ displacementTemplate|escape('js') }}".replace(/&lt;/g,'<').replace(/&gt;/g,'>');

/* -------------------------------------------------------------- */

$(document).ready(function() {
  // -------------------------------[ Componentes do formulário ]---
  $('.form .ui.dropdown')
    .dropdown()
  ;
  $('.ui.checkbox')
    .checkbox(checkboxOptions)
  ;

  // Realiza o tratamento de mascaramento dos campos e autopreenchimento
  // em função dos valores iniciais
  $("input[name='name']")
    .blur(nameHandler)
    .trigger("blur")
  ;

  /**{% if formMethod == 'POST' %}**/
  // Faz o tratamento da seleção do tipo de prestador de serviços
  $("input[name='entitytypeid']")
    .change(entityTypeHandler)
    .trigger("change")
  ;
  /**{% endif %}**/
  
  // Mascara os campos e habilita os eventos para tratamento da
  // atualização na tela de cada unidade/filial
  for (var subsidiaryNumber = 0; subsidiaryNumber < subsidiariesCount; subsidiaryNumber++) {
    // Realiza o tratamento de mascaramento dos campos e
    // autopreenchimento em função dos valores
    $("input[name='subsidiaries[" + subsidiaryNumber + "][regionaldocumentstate]']")
      .mask({
        type: 'state',
        validate: true
      })
    ;
    $("input[name='subsidiaries[" + subsidiaryNumber + "][nationalregister]']")
      .mask({
        type: 'cpforcnpj',
        validate: true
      })
    ;
    $("input[name='subsidiaries[" + subsidiaryNumber + "][birthday]']")
      .mask({
        type: 'date',
        validate: true
      })
      .blur(birthdayHandler)
      .trigger("blur")
    ;
    $("input[name='subsidiaries[" + subsidiaryNumber + "][cityname]']")
      .autocomplete(cityNameCompletionOptions)
    ;
    $("input[name='subsidiaries[" + subsidiaryNumber + "][postalcode]']")
      .mask({
        type: 'postalCode'
      })
      .blur(postalCodeHandler)
    ;
    
    // Define o CEP corrente para o tratamento correto do preenchimento
    // do formulário
    $("input[name='subsidiaries[" + subsidiaryNumber + "][postalcode]']")
      .prop('currentPostalCode', $("input[name='subsidiaries[" + subsidiaryNumber + "][postalcode]']").val())
    ;
    
    // Força o mascaramento dos campos de telefones
    for (var phoneNumber = 0; phoneNumber < subsidiariesDetails[ subsidiaryNumber ].phonesCount; phoneNumber++) {
      $("input[name='subsidiaries[" + subsidiaryNumber + "][phones][" + phoneNumber + "][phonenumber]']")
        .mask({
          type: 'phoneNumber'
        })
      ;
    }
    
    // Força o mascaramento dos campos de telefones nos contatos
    // adicionais
    for (var contactNumber = 0; contactNumber < subsidiariesDetails[ subsidiaryNumber ].contactsCount; contactNumber++) {
      $("input[name='subsidiaries[" + subsidiaryNumber + "][contacts][" + contactNumber + "][phonenumber]']")
        .mask({
          type: 'phoneNumber'
        })
      ;
    }

    var
      count = $(".contactList[subsidiary='" + subsidiaryNumber + "']")
        .children('.mailing.grid').length
    ;

    if (count > 0) {
      // Se temos um ou mais contatos adicionais, garantimos que a
      // respectiva informação de aviso de que não temos contatos esteja
      // oculta
      $(".contactList[subsidiary='" + subsidiaryNumber + "']")
        .children('.nocontent')
        .hide()
      ;
    }
  }

  // Lida com os campos de veículo em caso de técnico pessoa física
  $("input[name='plate']")
    .mask({
      type: 'plate'
    })
  ;
  $("input[name='vehiclemodelname']")
    .blur(vehicleModelHandler)
    .autocomplete(vehicleModelNameCompletionOptions)
  ;
  $("input[name='vehicletypeid']")
    .change(vehicleTypeHandler)
  ;
  $("input[name='vehiclebrandname']")
    .autocomplete(brandNameCompletionOptions)
  ;

  // Mascara o campo do nome do banco
  $("input[name='accounts[0][bankid]']")
    .closest('div.ui.dropdown')
    .dropdown({
      fullTextSearch: 'exact',
      ignoreDiacritics: true
    })
  ;
  // Mascara o campo do tipo da chave PIX
  $("input[name='accounts[0][pixkeytypeid]']")
    .closest('div.ui.dropdown')
    .dropdown({
      fullTextSearch: 'exact',
      ignoreDiacritics: true,
      onChange: updatePixKeyMask
    })
  ;
  updatePixKeyMask(
    $("input[name='accounts[0][pixkeytypeid]']").val()
  );

  // Realiza o tratamento do clique na tabela de serviços permitidos e
  // valores cobrados para permitir marcar/desmarcar quais os serviços
  // serão permitidas
  $("table.services tbody tr td.toggle").click(toggleCell);

  // Força o mascaramento dos campos dos valores cobrados para cada tipo
  // de cobrança
  $("input[name*='pricevalue']")
    .mask({
      type: 'monetary',
      trim: true,
      allowNegativeValues: false,
      decimalsPlaces: 2,
      maxLength: 15
    })
    .blur(monetaryHandler)
  ;

  $("input[name='unproductivevisit']")
    .mask({
      type: 'monetary',
      trim: true,
      allowNegativeValues: false,
      decimalsPlaces: 2,
      maxLength: 15
    })
    .blur(monetaryHandler)
  ;
  $("select[name='unproductivevisittype']")
    .change(updateMeasureLabel)
    .trigger("change")
  ;
  $("input[name='frustratedvisit']")
    .mask({
      type: 'monetary',
      trim: true,
      allowNegativeValues: false,
      decimalsPlaces: 2,
      maxLength: 15
    })
    .blur(monetaryHandler)
  ;
  $("select[name='frustratedvisittype']")
    .change(updateMeasureLabel)
    .trigger("change")
  ;
  $("input[name='unrealizedvisit']")
    .mask({
      type: 'monetary',
      trim: true,
      allowNegativeValues: false,
      decimalsPlaces: 2,
      maxLength: 15
    })
    .blur(monetaryHandler)
  ;
  $("select[name='unrealizedvisittype']")
    .change(updateMeasureLabel)
    .trigger("change")
  ;

  // Força o mascaramento dos campos das taxas de deslocamento
  for (var displacementNumber = 0; displacementNumber < displacementsCount; displacementNumber++) {
    if (displacementNumber > 0) {
      $("input[name='displacements[" + displacementNumber + "][distance]']")
        .mask({
          type: 'number',
          maxLength: 4
        })
      ;
    }

    $("input[name='displacements[" + displacementNumber + "][value]']")
      .mask({
        type: 'monetary',
        trim: true,
        allowNegativeValues: false,
        decimalsPlaces: 2,
        maxLength: 12
      })
      .blur(monetaryHandler)
    ;
  }

  $("input[name='geographiccoordinateid']")
    .change(toggleNewReference)
    .trigger("change")
  ;
  $("input[name='latitude']")
    .blur(geographicCoordinateHandler)
  ;
  $("input[name='longitude']")
    .blur(geographicCoordinateHandler)
  ;

  /**{% if formMethod == 'PUT' %}**/
  // Se estamos modificando, então analisamos a informação de modelo de
  // veículo para travar ou não a edição dos campos do tipo, subtipo e
  // da marca do veículo. Quando tivermos um modelo informado, não
  // permitimos a edição dos demais campos.
  vehicleModelHandler();
  /**{% endif %}**/

  // Coloca o foco no primeiro campo
  $("input[name='name']")
    .focus()
  ;
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
  onSelect: function(element, suggestion) {
    // Recupera o ID da filial para a qual a cidade foi completada
    var subsidiaryNumber = $(this).attr("subsidiary");
    
    // Armazena o ID do item selecionado
    $("input[name='subsidiaries[" + subsidiaryNumber + "][cityid]']")
      .val(suggestion.id)
    ;
    $("input[name='subsidiaries[" + subsidiaryNumber + "][state]']")
      .val(suggestion.state)
    ;
  },
  onInvalidateSelection: function() {
    // Recupera o ID da filial para a qual a cidade foi completada
    var subsidiaryNumber = $(this).attr("subsidiary");
    
    // Limpa as informações de cidade selecionada
    $("input[name='subsidiaries[" + subsidiaryNumber + "][cityid]']")
      .val(0)
    ;
    $("input[name='subsidiaries[" + subsidiaryNumber + "][state]']")
      .val('')
    ;
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

    // Limpa a marca do veículo
    $("input[name='vehiclebrandname']")
      .val("")
    ;
    $("input[name='vehiclebrandid']")
      .val(0)
    ;

    // Destravamos os campos relacionados com o modelo de veículo para
    // permitir modificações destes valores
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
    // Não conseguiu localizar uma marca de veículo com base nos valores
    // informados, então limpa os dados atuais
    $("input[name='vehiclebrandid']")
      .val(0)
    ;
  }
};

// ================================================[ Handlers ]=====

/**{% if formMethod == 'POST' %}**/
// Faz o tratamento da seleção do tipo de prestador de serviços
function entityTypeHandler() {
  var
    serviceProviderType = $(this).val(),
    $dropdown = $(this).closest('div.search.selection.dropdown'),
    juridicalperson =
      $dropdown
        .find('div.item[data-value=' + serviceProviderType + ']')
        .attr('juridicalperson')
  ;

  // Modifica o campo indicativo de tipo de pessoa (física ou jurídica)
  $("input[name='juridicalperson']").val(juridicalperson);

  // Faz o tratamento conforme o tipo de pessoa que estamos lidando
  if (juridicalperson === 'true') {
    // Pessoa jurídica
    
    // A primeira unidade/filial é a matriz
    $("input[name='subsidiaries[0][name]']")
      .val('Matriz')
    ;
    $("input[name='subsidiaries[0][name]']")
      .prev()
      .html('Matriz')
      .trigger("change")
    ;
    
    // Altera o rótulo do campo do nome titular para nome da
    // unidade/filial
    $("label[for*='subsidiarytitle']")
      .text("Nome da unidade/filial")
    ;
    
    // Altera o rótulo do campo CPF/CNPJ para CNPJ
    $("label[for*='nationalregister']")
      .text("CNPJ")
    ;

    // Altera o 'placeholder' do campo CPF/CNPJ
    $("input[name*='nationalregister']")
      .attr("placeholder", "Informe o CNPJ")
    ;

    // Esconde campos apenas de pessoa física e mostra os exclusivos de
    // pessoa jurídica
    $("div.physical.person")
      .hide()
    ;
    $("div.juridical.person")
      .show()
    ;
    $("div.vehicle.data")
      .hide()
    ;

    for (var subsidiaryNumber = subsidiariesCount - 1;
         subsidiaryNumber >= 0;
         subsidiaryNumber--) {
      // Altera o 'placeholder' do campo CPF/CNPJ
      $("input[name='subsidiaries[" + subsidiaryNumber + "][nationalregister]']")
        .attr("placeholder", "Informe o CNPJ")
      ;

      // Modifica todos os campos seletores de tipos de documentos, de
      // forma a conter apenas os tipos válidos para uma pessoa jurídica
      $("input[name='subsidiaries[" + subsidiaryNumber + "][regionaldocumenttype]']").each(function() {
        // Para cada dropdown, alterna os tipos de documentos conforme o
        // tipo de cliente selecionado
        var
          $dropdown = $(this).closest('div.ui.search.selection.dropdown')
        ;

        // Deixa apenas os tipos de documentos para pessoas jurídicas
        // habilitados
        $dropdown
          .find('[juridicalperson*=false]')
          .addClass('disabled')
        ;
        $dropdown
          .find('[juridicalperson*=true]')
          .removeClass('disabled')
        ;

        // Seleciona o primeiro item disponível
        var
          $first = $dropdown.find('[juridicalperson*=true]').first()
        ;
        $dropdown
          .dropdown('set selected', $first.data('value'))
        ;
      });
    }

    // Modifica o campo de nova subsidiaria para unidade/filial
    $("button[name='addSubsidiaryButton']")
      .html('<i class="plus icon"></i> Nova unidade/filial')
    ;
  } else {
    // Pessoa física
    
    // A primeira unidade/filial é o titular
    var
      owner = $("input[name='name']").val()
    ;

    // Atribui o nome do cliente ao nome do primeiro titular
    $("input[name='subsidiaries[0][name]']")
      .val(owner)
    ;

    if (owner === "") {
      $("input[name='subsidiaries[0][name]']")
        .prev()
        .html("&nbsp;")
        .trigger("change")
      ;
    } else {
      $("input[name='subsidiaries[0][name]']")
        .prev()
        .html(owner)
        .trigger("change")
      ;
    }

    // Altera o rótulo do campo do nome da unidade/filial para titular
    $("label[for*='subsidiarytitle']")
      .text("Nome do títular")
    ;
    
    // Altera o rótulo do campo CPF/CNPJ para CPF
    $("label[for*='nationalregister']")
      .text("CPF")
    ;

    // Altera o 'placeholder' do campo CPF/CNPJ
    $("input[name*='nationalregister']")
      .attr("placeholder", "Informe o CPF")
    ;

    // Esconde campos apenas de pessoa jurídica e mostra os exclusivos
    // de pessoa física
    $("div.physical.person")
      .show()
    ;
    $("div.juridical.person")
      .hide()
    ;
    $("div.vehicle.data")
      .show()
    ;

    for (var subsidiaryNumber = subsidiariesCount - 1; subsidiaryNumber >= 0; subsidiaryNumber--) {
      // Altera o 'placeholder' do campo CPF/CNPJ
      $("input[name='subsidiaries[" + subsidiaryNumber + "][nationalregister]']")
        .attr("placeholder", "Informe o CPF")
      ;

      // Modifica todos os campos seletores de tipos de documentos, de
      // forma a conter apenas os tipos válidos para uma pessoa física
      $("input[name='subsidiaries[" + subsidiaryNumber + "][regionaldocumenttype]']").each(function() {
        // Para cada dropdown, alterna os tipos de documentos conforme o
        // tipo de cliente selecionado
        var
          $dropdown = $(this).closest('div.ui.search.selection.dropdown')
        ;

        // Deixa apenas os tipos de documentos para pessoas físicas
        // habilitados
        $dropdown
          .find('[juridicalperson*=true]')
          .addClass('disabled')
        ;
        $dropdown
          .find('[juridicalperson*=false]')
          .removeClass('disabled')
        ;

        // Seleciona o primeiro item disponível
        var
          $first = $dropdown.find('[juridicalperson*=false]').first()
        ;
        $dropdown
          .dropdown('set selected', $first.data('value'))
        ;
      });
    }

    // Modifica o campo de nova subsidiaria para dependente
    $("button[name='addSubsidiaryButton']")
      .html('<i class="plus icon"></i> Novo dependente')
    ;
  }
}
/**{% endif %}**/

// Faz o tratamento do campo nome para permitir atualizar o nome do
// primeiro prestador de serviços quando o mesmo for pessoa física
function nameHandler()
{
  var
    name            = $(this).val(),
    juridicalperson = $("input[name='juridicalperson']").val()==="true"
      ? true
      : false,
    referenceName   = $("input[name='referencename']").val(),
    startPhrase     = 'Sede do prestador'
  ;

  // Conforme o tipo de pessoa que estamos lidando, realiza o devido
  // tratamento
  if (juridicalperson) {
    // Pessoa jurídica, retira o nome no local de referência, se
    // necessário
    if (referenceName.startsWith(startPhrase)) {
      $("input[name='referencename']")
        .val('')
      ;
    }
  } else {
    // Pessoa física
    
    // Atribui o nome do prestador de serviços ao nome do primeiro
    // titular
    $("input[name='subsidiaries[0][name]']")
      .val(name)
    ;

    if (name === "") {
      $("input[name='subsidiaries[0][name]']")
        .prev()
        .html("&nbsp;")
        .trigger("change")
      ;
    } else {
      $("input[name='subsidiaries[0][name]']")
        .prev()
        .text(name)
        .trigger("change")
      ;
    }

    // Acrescentamos o nome no local de referência, se necessário
    if (referenceName.startsWith(startPhrase) || referenceName.trim() === "") {
      // Atribui o nome do prestador de serviços ao local de referência da
      // coordenada geográfica
      $("input[name='referencename']")
        .val(getReferenceName(startPhrase, name, 100))
      ;
    }
  }
}

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
  }
}

// Obtém o nome de referência com base no tamanho, nome do prestador
// e a frase de início, limitando o texto ao tamanho informado
function getReferenceName(startPhrase, name, size) {
  if ((startPhrase.length + name.length + 1) < size) {
    return startPhrase + ' ' + name;
  }

  var
    nameParts = name.split(" "),
    wordExceptions = [
      'o', 'a', 'os', 'as', 'e', 'ou', 'de', 'di', 'do', 'da',
      'dos', 'das', 'dello', 'della', 'dalla', 'dal', 'del', 'em',
      'na', 'no', 'nas', 'nos', 'van', 'von', 'y', 'por', 'para',
      'um', 'uma', 'que', '-'
    ],
    skip = false,
    currentWord,
    lastWord
  ;

  do {
    skip = false;
    currentWord = nameParts.pop();
    if (wordExceptions.includes(currentWord)) {
      skip = true;
      continue;
    }

    lastWord = nameParts[ nameParts.length - 1 ];
    if (wordExceptions.includes(lastWord)) {
      nameParts.pop();
    }

    name = nameParts.join(' ');
  } while (skip || ((startPhrase.length + name.length + 1) > size));

  return startPhrase + ' ' + name;
}

// O manipulador do campo de data de nascimento, em que atualiza a
// idade
function birthdayHandler() {
  var
    // Recupera o ID da filial para a qual a data de nascimento foi
    // preenchida
    subsidiaryNumber = $(this).attr("subsidiary"),
    birthdayDate     = $(this).val()
  ;

  // Ignora qualquer modificação se estivermos em um campo somente
  // leitura
  if ($(this).prop('readonly')) {
    return;
  }

  // Verifica se a idade é válida
  if (birthdayDate.length === 10) {
    var
      parts    = birthdayDate.split("/"),
      day      = parseInt(parts[0]),
      month    = parseInt(parts[1]),
      year     = parseInt(parts[2]),
      birthday = new Date(year, month-1, day),
      today    = new Date(),
      isLeap   = function (year) {
        return year % 4 == 0 && (year % 100 != 0 || year % 400 == 0);
      },
      // Calcula a quantidade de dias desde a data de nascimento
      days     = Math.floor((today.getTime() - birthday.getTime())/1000/60/60/24),
      age      = 0
    ;

    // Percorre os anos
    for (var y = birthday.getFullYear(); y <= today.getFullYear(); y++) {
      var
        daysInYear = isLeap(y)
          ? 366
          : 365
      ;

      if (days >= daysInYear) {
        // Incrementamos a idade somente se houver dias suficientes
        // para completar mais um ano
        days -= daysInYear;
        age++;
      }
    }

    // Coloca a data calculada no campo de idade
    $("input[name='subsidiaries[" + subsidiaryNumber + "][age]']")
      .val(age + ' anos')
      .trigger("change")
    ;
  } else {
    // Não foi fornecida uma data, então ignora
    $("input[name='subsidiaries[" + subsidiaryNumber + "][age]']")
      .val('')
      .trigger("change")
    ;
  }
}

// O manipulador do campo de autocompletar o CEP
function postalCodeHandler() {
  var
    // Recupera o ID da filial para a qual o CEP foi completado
    subsidiaryNumber  = $(this).attr("subsidiary"),
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
        $("input[name='subsidiaries[" + subsidiaryNumber + "][" + field + "]']")
          .val("...")
          .trigger("change")
        ;
      });
      $("input[name='subsidiaries[" + subsidiaryNumber + "][cityid]']")
        .val(0)
      ;
      
      // Consulta o Web Service para obter os dados pelo CEP
      var url = "{{ path_for(getPostalCodeData.URL) }}";
      requestJSONData(url, { postalCode: postalCode },
      function (addressData) {
        // Conseguiu localizar um endereço com base nos valores
        // informados, então atualiza o formulário
        textFields.forEach(function(field) {
          $("input[name='subsidiaries[" + subsidiaryNumber + "][" + field + "]']")
            .val(addressData[field])
          ;
        });
        $("input[name='subsidiaries[" + subsidiaryNumber + "][cityid]']")
          .val(addressData.cityid)
        ;
        
        // Coloca em foco o campo de número
        $("input[name='subsidiaries[" + subsidiaryNumber + "][streetnumber]']")
          .focus()
        ;
      },
      function () {
        // Não conseguiu localizar os dados do endereço com base
        // no CEP informado, então limpa os dados atuais
        textFields.forEach(function(field) {
          $("input[name='subsidiaries[" + subsidiaryNumber + "][" + field + "]']")
            .val('')
            .trigger("change")
          ;
        });
        $("input[name='subsidiaries[" + subsidiaryNumber + "][cityid]']")
          .val(0)
        ;
      });
    } else {
      // O CEP não foi fornecido
      textFields.forEach(function(field) {
        $("input[name='subsidiaries[" + subsidiaryNumber + "][" + field + "]']")
          .val('')
          .trigger("change")
        ;
      });
      $("input[name='subsidiaries[" + subsidiaryNumber + "][cityid]']")
        .val(0)
      ;
    }
  }
}

// Faz o tratamento de campos que informam um valor monetário para
// permitir corrigir valores inválidos
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

// Atualiza a máscara do campo de chave PIX
function updatePixKeyMask(value)
{
  var
    $input = $("input[name='accounts[0][pixkey]'"),
    maskedInput = $input.data('plugin_MaskedInput')
  ;

  switch (parseInt(value)) {
    case 1:
      // Nenhuma chave PIX
      if (maskedInput) {
        // Remove o mascaramento
        maskedInput.destroy();
      }

      if (!$input.prop('readonly')) {
        // Desativa edição
        $input
          .prop('readonly', true)
          .closest('div')
          .addClass("readonly")
        ;
      }

      break;
    case 2:
      // CPF ou CNPJ
      
      if (maskedInput) {
        // Remove o mascaramento
        maskedInput.destroy();
      }

      if ($input.prop('readonly')) {
        // Ativa edição
        $input
          .prop('readonly', false)
          .closest('div')
          .removeClass("readonly")
        ;
      }

      // Habilita máscara para CPF/CNPJ
      $input
        .mask({
          type: 'cpforcnpj',
          validate: true
        })
      ;

      break;
    case 4:
      // Telefone celular
      
      if (maskedInput) {
        // Remove o mascaramento
        maskedInput.destroy();
      }

      if ($input.prop('readonly')) {
        // Ativa edição
        $input
          .prop('readonly', false)
          .closest('div')
          .removeClass("readonly")
        ;
      }

      // Habilita máscara para celular
      $input
        .mask({
          type: 'phoneNumber'
        })
      ;

      break;
    default:
      // E-mail ou chave aleatória
      
      if (maskedInput) {
        // Remove o mascaramento
        maskedInput.destroy();
      }

      if ($input.prop('readonly')) {
        // Ativa edição
        $input
          .prop('readonly', false)
          .closest('div')
          .removeClass("readonly")
        ;
      }

      // Define o tamanho do campo
      $input
        .prop('maxlength', 72)
      ;
  }
}

// Trata o clique na tabela para seleção ou não do item
function toggleCell() {
  // Altera o símbolo no campo
  if ($(this).attr('added') == "true") {
    $(this)
      .attr('added', "false")
    ;
    $(this)
      .children('i')
      .removeClass('green')
      .removeClass('checkmark')
      .addClass('red')
      .addClass('times')
    ;
    $(this)
      .closest('tr')
      .removeClass('selected')
      .addClass('unselected')
    ;
    // Desabilita a entrada do valor a ser cobrado
    $(this)
      .closest('tr')
      .find("input[type='text']")
      .prop('readonly', true)
      .closest('div')
      .addClass("readonly")
    ;
    $(this)
      .children("input")
      .val("false")
    ;
  } else {
    $(this)
      .attr('added', "true")
    ;
    $(this)
      .children('i')
      .removeClass('red')
      .removeClass('times')
      .addClass('green')
      .addClass('checkmark')
    ;
    $(this)
      .closest('tr')
      .removeClass('unselected')
      .addClass('selected')
    ;
    // Habilita a entrada do valor a ser cobrado
    $(this)
      .closest('tr')
      .find("input[type='text']")
      .prop('readonly', false)
      .closest('div')
      .removeClass("readonly")
    ;
    $(this)
      .children("input")
      .val("true")
    ;
  }
}

// Atualiza o rótulo da unidade de medida
function updateMeasureLabel()
{
  var
    labelText  = $('option:selected', this).attr('label'),
    labelField = $(this).attr('label')
  ;
  
  $("div[name='" + labelField + "']")
    .text(labelText)
  ;
}

// Alterna os campos de nova referência para quando for selecionado
// outra referência na lista de locais de referência
function toggleNewReference()
{
  var
    actualID  = $(this).val()
  ;
  
  if (actualID > 0) {
    $('div.another')
      .hide()
    ;
  } else {
    $('div.another')
      .show()
    ;
  }
}

// Faz o tratamento de campos que informam um valor de coordenada
// geográfica para permitir corrigir valores inválidos
function geographicCoordinateHandler()
{
  var
    coordinate = $(this).val()
  ;

  if (coordinate.trim().isEmpty()) {
    $(this)
      .val('0,0000000')
    ;
  }
}

/* -------------------------------------[ Unidades/Filiais ]----- */

// Faz a adição de uma unidade/filial
var addSubsidiary = function() {
  var
    subsidiaryNumber = subsidiariesCount,
    phoneNumber      = 1,
    emailNumber      = 1,
    contactNumber    = 0,
    juridicalperson  = $("input[name='juridicalperson']").val()==="true"
      ? true
      : false
  ;

  // Determina o número da unidade/filial
  subsidiariesCount++;

  // Inicializa os contadores de telefones, e-mails e contatos
  // adicionais nesta unidade/filial
  subsidiariesDetails[ subsidiaryNumber ] = {
    phonesCount: 1,
    emailsCount: 1,
    contactsCount: 0
  };

  // Adiciona um conjunto de campos para a nova unidade/filial
  $("#subsidiaryList")
    .append(TemplateEngine(subsidiaryTemplate, {
      subsidiaryIndicator: (subsidiaryNumber).toString().padLeftWithZeros(2),
      subsidiaryNumber: subsidiaryNumber,
      phoneNumber: phoneNumber,
      emailNumber: emailNumber,
      contactNumber: contactNumber
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
  
  // Faz o tratamento conforme o tipo de pessoa que estamos lidando
  if (juridicalperson) {
    // Pessoa jurídica
    
    // Altera o rótulo do campo para nome da unidade/filial
    $("label[for*='subsidiarytitle']")
      .text("Nome da unidade/filial")
    ;

    // Altera o rótulo do campo CPF/CNPJ para CNPJ
    $("label[for='nationalregister']")
      .text("CNPJ")
    ;

    // Altera o 'placeholder' do campo CPF/CNPJ
    $("input[name='subsidiaries[" + subsidiaryNumber + "][nationalregister]']")
      .attr("placeholder", "Informe o CNPJ")
    ;

    // Modifica todos os campos seletores de tipos de documentos, de
    // forma a conter apenas os tipos válidos
    $("input[name='subsidiaries[" + subsidiaryNumber + "][regionaldocumenttype]']").each(function() {
      // Para cada dropdown, alterna os tipos de documentos conforme
      // o tipo de cliente selecionado
      var
        $dropdown = $(this).closest('div.ui.search.selection.dropdown')
      ;

      // Deixa apenas os tipos de documentos para pessoas jurídicas
      // habilitados
      $dropdown
        .find('[juridicalperson*=false]')
        .addClass('disabled')
      ;
      $dropdown
        .find('[juridicalperson*=true]')
        .removeClass('disabled')
      ;

      // Seleciona o primeiro item disponível
      var
        $first = $dropdown.find('[juridicalperson*=true]').first()
      ;
      $dropdown
        .dropdown('set selected', $first.data('value'))
      ;
    });

    // Esconde campos apenas de pessoa física
    $("div.physical.person")
      .hide()
    ;

    // Exibe os campos apenas de pessoa jurídica
    $("div.juridical.person")
      .show()
    ;
  } else {
    // Pessoa física

    // Altera o rótulo do campo para nome do titular
    $("label[for*='subsidiarytitle']")
      .text("Nome do títular")
    ;

    // Altera o rótulo do campo CPF/CNPJ para CPF
    $("label[for='nationalregister']")
      .text("CPF")
    ;

    // Altera o 'placeholder' do campo CPF/CNPJ
    $("input[name='subsidiaries[" + subsidiaryNumber + "][nationalregister]']")
      .attr("placeholder", "Informe o CPF")
    ;

    // Modifica todos os campos seletores de tipos de documentos, de
    // forma a conter apenas os tipos válidos
    $("input[name='subsidiaries[" + subsidiaryNumber + "][regionaldocumenttype]']").each(function() {
      // Para cada dropdown, alterna os tipos de documentos conforme
      // o tipo de cliente selecionado
      var
        $dropdown = $(this).closest('div.ui.search.selection.dropdown')
      ;
      
      // Deixa apenas os tipos de documentos para pessoas físicas
      // habilitados
      $dropdown
        .find('[juridicalperson*=true]')
        .addClass('disabled')
      ;
      $dropdown
        .find('[juridicalperson*=false]')
        .removeClass('disabled')
      ;

      // Seleciona o primeiro item disponível
      var
        $first = $dropdown.find('[juridicalperson*=false]').first()
      ;
      $dropdown
        .dropdown('set selected', $first.data('value'))
      ;
    });

    // Exibe os campos apenas de pessoa física
    $("div.physical.person")
      .show()
    ;

    // Esconde campos apenas de pessoa jurídica
    $("div.juridical.person")
      .hide()
    ;
  }

  // Realiza o tratamento de mascaramento dos campos e autopreenchimento
  // em função dos valores
  $("input[name='subsidiaries[" + subsidiaryNumber + "][regionaldocumentstate]']")
    .mask({
      type: 'state',
      validate: true
    })
  ;
  $("input[name='subsidiaries[" + subsidiaryNumber + "][nationalregister]']")
    .mask({
      type: 'cpforcnpj',
      validate: true
    })
  ;
  $("input[name='subsidiaries[" + subsidiaryNumber + "][birthday]']")
    .mask({
      type: 'date',
      validate: true
    })
    .blur(birthdayHandler)
  ;
  $("input[name='subsidiaries[" + subsidiaryNumber + "][cityname]']")
    .autocomplete(cityNameCompletionOptions)
  ;
  $("input[name='subsidiaries[" + subsidiaryNumber + "][postalcode]']")
    .mask({
      type: 'postalCode'
    })
    .blur(postalCodeHandler)
  ;
  
  // Define o CEP corrente para o tratamento correto do preenchimento
  // do formulário
  $("input[name='subsidiaries[" + subsidiaryNumber + "][postalcode]']")
    .prop('currentPostalCode', $("input[name='subsidiaries[" + subsidiaryNumber + "][postalcode]']").val())
  ;
    
  // Força o mascaramento dos campos de telefones
  for (var phoneNumber = 0; phoneNumber < subsidiariesDetails[ subsidiaryNumber ].phonesCount; phoneNumber++) {
    $("input[name='subsidiaries[" + subsidiaryNumber + "][phones][" + phoneNumber + "][phonenumber]']")
      .mask({
        type: 'phoneNumber'
      })
    ;
  }
  
  // Força o mascaramento dos campos de telefones nos contatos
  // adicionais
  for (var contactNumber = 0; contactNumber < subsidiariesDetails[ subsidiaryNumber ].contactsCount; contactNumber++) {
    $("input[name='subsidiaries[" + subsidiaryNumber + "][contacts][" + contactNumber + "][phonenumber]']")
      .mask({
        type: 'phoneNumber'
      })
    ;
  }
  
  // Trata o checkbox corretamente
  $('.ui.checkbox')
    .checkbox(checkboxOptions)
  ;
  
  // Coloca em foco o campo inicial da nova unidade/filial
  $("input[name='subsidiaries[" + subsidiaryNumber + "][name]']")
    .focus()
  ;
};

// Faz a remoção de uma unidade/filial
var delSubsidiary = function(element) {
  $(element)
    .closest('.subsidiary.entry')
    .remove()
  ;
};

// Faz a adição de um telefone
var addPhone = function(subsidiaryNumber) {
  var
    // Determina a quantidade de telefones nesta unidade/filial
    phoneNumber = subsidiariesDetails[ subsidiaryNumber ].phonesCount
  ;

  // Adiciona um conjunto de campos para o novo telefone
  subsidiariesDetails[ subsidiaryNumber ].phonesCount++;
  $(".phoneList[subsidiary='" + subsidiaryNumber + "']")
    .append(TemplateEngine(phoningTemplate, {
      subsidiaryNumber: subsidiaryNumber,
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
  $("input[name='subsidiaries[" + subsidiaryNumber + "][phones][" + phoneNumber + "][phonenumber]']")
    .mask({
      type: 'phoneNumber'
    })
  ;
  
  // Coloca em foco o campo inicial do novo telefone
  $("input[name='subsidiaries[" + subsidiaryNumber + "][phones][" + phoneNumber + "][phonenumber]']")
    .focus()
  ;
};

// Faz a remoção de um telefone
var delPhone = function(element) {
  var
    // Recupera o ID do telefone
    phoneNumber = parseInt($(element).attr("phoneNumber"))
  ;

  if (phoneNumber > 0) {
    $(element)
      .closest('.phoning.grid')
      .remove()
    ;
  }
};

// Faz a adição de um e-mail
var addEmail = function(subsidiaryNumber) {
  var
    // Determina a quantidade de e-mails nesta unidade/filial
    emailNumber = subsidiariesDetails[ subsidiaryNumber ].emailsCount
  ;

  // Adiciona um conjunto de campos para o novo e-mail
  subsidiariesDetails[ subsidiaryNumber ].emailsCount++;
  $(".emailList[subsidiary='" + subsidiaryNumber + "']")
    .append(TemplateEngine(emailTemplate, {
      subsidiaryNumber: subsidiaryNumber,
      emailNumber: emailNumber
    }))
  ;
  
  // Coloca em foco o campo inicial do novo e-mail
  $("input[name='subsidiaries[" + subsidiaryNumber + "][emails][" + emailNumber + "][email]']")
    .focus()
  ;
};

// Faz a remoção de um e-mail
var delEmail = function(element) {
  var
    // Recupera o ID do e-mail
    emailNumber = parseInt($(element).attr("emailNumber"))
  ;

  if (emailNumber > 0) {
    $(element)
      .closest('.mailing.grid')
      .remove()
    ;
  }
};

// Faz a adição de um contato adicional
var addContact = function(subsidiaryNumber) {
  var
    // Determina a quantidade de contatos nesta unidade/filial
    contactNumber = subsidiariesDetails[ subsidiaryNumber ].contactsCount
  ;

  // Como temos um contato adicional, garantimos que a respectiva
  // informação de aviso de que não temos contatos esteja oculta
  $(".contactList[subsidiary='" + subsidiaryNumber + "']")
    .children('.nocontent')
    .hide()
  ;

  // Adiciona um conjunto de campos para o novo contato
  subsidiariesDetails[ subsidiaryNumber ].contactsCount++;
  $(".contactList[subsidiary='" + subsidiaryNumber + "']")
    .append(TemplateEngine(contactTemplate, {
      subsidiaryNumber: subsidiaryNumber,
      contactNumber: contactNumber
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
  $("input[name='subsidiaries[" + subsidiaryNumber + "][contacts][" + contactNumber + "][phonenumber]']")
    .mask({
      type: 'phoneNumber'
    })
  ;
  
  // Coloca em foco o campo inicial do novo contato
  $("input[name='subsidiaries[" + subsidiaryNumber + "][contacts][" + contactNumber + "][name]']")
    .focus()
  ;
};

// Faz a remoção de um contato
var delContact = function(element) {
  var
    // Recupera o ID do contato
    subsidiaryNumber = parseInt($(element).attr("subsidiaryNumber"))
  ;

  $(element)
    .closest('.mailing.address.grid')
    .remove()
  ;

  var
    count = $(".contactList[subsidiary='" + subsidiaryNumber + "']")
      .children('.mailing.address.grid').length
  ;

  if (count == 0) {
    // Se não tivermos nenhum contato adicional, precisamos exibir a
    // respectiva informação de aviso de que não temos contatos
    // adicionais
    $(".contactList[subsidiary='" + subsidiaryNumber + "']")
      .children('.nocontent')
      .show()
    ;
  }
};

/* --------------------------------[ Taxas de deslocamento ]----- */

// Faz a adição de uma nova faixa de cobrança para taxas de
// deslocamento
var addDisplacement = function()
{
  // Incrementa o contador das faixas de taxas de deslocamento
  var
    displacementNumber = displacementsCount,
    count = $("#displacementList tbody")
      .children('tr').length
  ;

  if (count == 0) {
    // Se não tivermos nenhuma faixa de deslocamento, precisamos
    // alterar o rótulo do último valor
    $("#displacementList tfoot tr:first td:first")
      .text('Acima disto, pagar:')
    ;
  }

  // Incrementa a quantidade de faixas
  displacementsCount++;
  
  // Adiciona um conjunto de campos para a nova faixa
  $("#displacementList tbody")
    .append(TemplateEngine(displacementTemplate,
      { number: displacementNumber }))
  ;

  // Mascara os campos
  $("input[name='displacements[" + displacementNumber + "][distance]']")
    .mask({
      type: 'number',
      trim: true,
      maxLength: 4
    })
  ;
  $("input[name='displacements[" + displacementNumber + "][value]']")
    .mask({
      type: 'monetary',
      trim: true,
      allowNegativeValues: false,
      decimalsPlaces: 2,
      maxLength: 12
    })
  ;

  // Coloca em foco o campo inicial da nova distância
  $("input[name='displacements[" + displacementNumber + "][distance]']")
    .focus()
  ;
};

// Faz a remoção de uma faixa de cobrança de taxa de deslocamento
var delDisplacement = function(element) {
  $(element)
    .closest('tr')
    .remove()
  ;

  var
    count = $("#displacementList tbody")
      .children('tr').length
  ;

  if (count == 0) {
    // Se não tivermos nenhuma faixa de deslocamento, precisamos
    // alterar o rótulo do último valor
    $("#displacementList tfoot tr:first td:first")
      .text('Para qualquer distância, cobrar:')
    ;
  }
};
