var
  // O índice de contagem dos telefones
  phonesCount = parseInt("{{ phones|length }}"),
  // O índice de contagem dos e-mails
  emailsCount = parseInt("{{ emails|length }}")
;

/* ------------------------------------------------[ Telefones ]----- */

// Carrega o template de um novo telefone, definindo os valores padrões
/**
{% set phoningTemplate %}
  {% include 'erp/cadastre/serviceproviders/technicians/phone.twig' with { phoneTypes: phoneTypes, phoneNumber: '<%=phoneNumber%>', phone: { technicianphoneid: 0, phonetypeid: 1 }, editMode: true, readonly: false }  %}
{% endset %}
**/
var phoningTemplate = "{{ phoningTemplate|escape('js') }}".replace(/&lt;/g,'<').replace(/&gt;/g,'>');

/* --------------------------------------------------[ E-mails ]----- */

// Carrega o template de um novo e-mail, definindo os valores padrões
/**
{% set emailTemplate %}
  {% include 'erp/cadastre/serviceproviders/technicians/mailing.twig' with { emailNumber: '<%=emailNumber%>', mailing: { technicianmailingid: 0 }, editMode: true, readonly: false }  %}
{% endset %}
**/
var emailTemplate = "{{ emailTemplate|escape('js') }}".replace(/&lt;/g,'<').replace(/&gt;/g,'>');

/* ------------------------------------------------------------------ */

$(document).ready(function() {
  // -----------------------------------[ Componentes do formulário ]---
  $('.form .ui.dropdown')
    .dropdown()
  ;
  $('.ui.checkbox')
    .checkbox(checkboxOptions)
  ;

  // Realiza o tratamento de mascaramento dos campos e autopreenchimento
  // em função dos valores iniciais
  $("input[name='regionaldocumentstate']")
    .mask({
      type: 'state',
      validate: true
    })
  ;
  $("input[name='cpf']")
    .mask({
      type: 'cpf',
      validate: true
    })
  ;
  $("input[name='birthday']")
    .mask({
      type: 'date',
      validate: true
    })
    .blur(birthdayHandler)
    .trigger("blur")
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
  
  // Define o CEP corrente para o tratamento correto do preenchimento
  // do formulário
  $("input[name='postalcode']")
    .prop('currentPostalCode', $("input[name='postalcode']").val())
  ;
    
  // Força o mascaramento dos campos de telefones
  for (var phoneNumber = 0; phoneNumber < phonesCount; phoneNumber++) {
    $("input[name='phones[" + phoneNumber + "][phonenumber]']")
      .mask({
        type: 'phoneNumber'
      })
    ;
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
    $("input[name='cityid']")
      .val(suggestion.id)
    ;
    $("input[name='state']")
      .val(suggestion.state)
    ;
  },
  onInvalidateSelection: function() {
    // Recupera o ID da filial para a qual a cidade foi completada
    var subsidiaryNumber = $(this).attr("subsidiary");
    
    // Limpa as informações de cidade selecionada
    $("input[name='cityid']")
      .val(0)
    ;
    $("input[name='state']")
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
    $("input[name='age']")
      .val(age + ' anos')
      .trigger("change")
    ;
  } else {
    // Não foi fornecida uma data, então ignora
    $("input[name='age']")
      .val('')
      .trigger("change")
    ;
  }
}

// O manipulador do campo de autocompletar o CEP
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
      function (addressData) {
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
        
        // Coloca em foco o campo de número
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

/* ------------------------------------------------[ Telefones ]----- */

// Faz a adição de um telefone
var addPhone = function() {
  var
    // Determina a quantidade de telefones
    phoneNumber = phonesCount
  ;

  // Adiciona um conjunto de campos para o novo telefone
  phonesCount++;
  $(".phoneList")
    .append(TemplateEngine(phoningTemplate, {
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
  $("input[name='phones[" + phoneNumber + "][phonenumber]']")
    .mask({
      type: 'phoneNumber'
    })
  ;
  
  // Coloca em foco o campo inicial do novo telefone
  $("input[name='phones[" + phoneNumber + "][phonenumber]']")
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

/* --------------------------------------------------[ E-mails ]----- */

// Faz a adição de um e-mail
var addEmail = function() {
  var
    // Determina a quantidade de e-mails
    emailNumber = emailsCount
  ;

  // Adiciona um conjunto de campos para o novo e-mail
  emailsCount++;
  $(".emailList")
    .append(TemplateEngine(emailTemplate, {
      emailNumber: emailNumber
    }))
  ;
  
  // Coloca em foco o campo inicial do novo e-mail
  $("input[name='emails[" + emailNumber + "][email]']")
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
