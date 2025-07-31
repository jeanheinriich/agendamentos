/**********************************************************************
 * extension.js
 * 
 * (c) 2016 by Emerson Cavalcanti (emersoncavalcanti@gmail.com)
 * 
 * Licensed under GNU General Public License 3.0 or later. 
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 * 
 * Some open source application is distributed in the hope that it will 
 * be useful, but WITHOUT ANY WARRANTY; without even the implied warranty 
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with Foobar. If not, see <http://www.gnu.org/licenses/>.
 *
 * @license GPL-3.0+ <http://spdx.org/licenses/GPL-3.0+>
 * --------------------------------------------------------------------
 * Controle de Modificações
 * 
 * 2019-07-05 - Emerson Cavalcanti
 *   - Versão inicial
 * --------------------------------------------------------------------
 * Descrição:
 * 
 * A biblioteca JavaScript com extensões para os tipos base do
 * Javascript e funções para verificação e formatação de valores em
 * formulários.
 **********************************************************************/

// ---------------------------------------------------------------------
// Extensões dos objetos padrões
// ---------------------------------------------------------------------

// ==========================================================[ Strings ]

// Estende o objeto String padrão com um método isEmpty() que determina
// se a string está em branco
String.prototype.isEmpty = function() {
  return (this.length === 0 || !this.replace(/[`~!@#$%^&*()_|+\-=?;:'",.<>\{\}\[\]\\\/]/gi, '').trim());
};

// Estende o objeto String padrão com um método isNotEmpty() que
// determina se a string não está em branco
String.prototype.isNotEmpty = function() {
  return !(this.isEmpty());
};

// Estende o objeto String padrão com um método getOnlyNumbers() que
// recupera apenas os números desta
String.prototype.getOnlyNumbers = function() {
  return this.replace(/[^\d]+/g,'').trim();
};

// Estende o objeto String padrão com um método toUpperCaseFirst() que
// converte uma string com o primeiro caractere em maiúsculo e os demais
// em minúsculo
String.prototype.toUpperCaseFirst = function() {
  return this.charAt(0).toUpperCase() + this.slice(1).toLowerCase();
}

// Estende o objeto String padrão com um método toDate(format) que
// converte uma string para um objeto date
String.prototype.toDate = function (format) {
  var
    value = this,
    valueArray,
    date
  ;

  if (format == 'DD/MM/YYYY') {
    // Retorna a data obtida do formato DD/MM/YYYY
    valueArray = value.split("/");
    date = new Date(valueArray[2], valueArray[1] - 1, valueArray[0]);
  } else {
    // Retorna a data obtida do formato YYYY-MM-DD
    valueArray = value.split("-");
    date = new Date(valueArray[0], valueArray[1] - 1, valueArray[2]);
  }
  
  return date;
}

// Estende o objeto String padrão com um método padLeftWithZeros() que
// converte uma string acrescentando zeros a esquerda até preencher o
// tamanho indicado
String.prototype.padLeftWithZeros = function(size) {
  var
    value = this
  ;

  // Enquanto o valor contiver menos caracteres que o tamanho informado,
  // acrescentamos zeros
  while (value.length < size)
    value = "0" + value;
  
  return value;
}

// Estende o objeto String padrão com um método padLeftWithZeros() que
// converte uma string acrescentando espaços a esquerda até preencher o
// tamanho indicado
String.prototype.padLeftWithSpaces = function(size) {
  var
    value = this
  ;
  
  // Enquanto o valor contiver menos caracteres que o tamanho informado,
  // acrescentamos espaços em branco
  while (value.length < size)
    value = "\u00A0" + value;
  
  return value;
}

// Estende o objeto String padrão com um método fromMoney() que converte
// o valor formatado em monetário numa string em um valor no padrão
// numérico
String.prototype.fromMoney = function () {
  var
    value = this,
    numberReg = new RegExp("[^0-9-.]", ["g"])
  ;

  // Se não tivermos conteúdo, retorna
  if (value.isEmpty()) {
    return 0;
  }

  // 1. Convertemos valores entre parenteses para valores negativos
  value = value.replace(/\((?=\d+)(.*)\)/, "-$1")

  // 2. Removemos os separadores de milhares
  value = value.replace(/\./g,'');

  // 3. Convertemos o separador de decimal
  value = value.replace(",",".");

  // 4. Removemos quaisquer caracteres estranhos, como os símbolos
  // monetários
  value = value.replace(numberReg, '');

  // 5. Testamos para verificar se temos um valor passível de ser
  //    convertido e retornamos
  if (isNaN(value)) {
    return 0;
  }

  return parseFloat(value);
};

// ==========================================================[ Numbers ]

// Estende o objeto Number padrão com um método toMoney() que converte o
// valor número para a notação monetária num string. Uso:
//   someVar.formatMoney(decimalPlaces, symbol, thousandsSeparator, decimalSeparator)
// Defaults: (2, "", ".", ",")
Number.prototype.toMoney = function(decimalsPlaces, monetarySymbol,
  thousandSeparator, decimalsSeparator) {
  // Verifica se foi informada a quantidade de casas decimais (Default: 2)
  decimalsPlaces = !isNaN(decimalsPlaces = Math.abs(decimalsPlaces)) ? decimalsPlaces : 2;
  
  // Verifica a definição de símbolo monetário (Default: nenhum)
  monetarySymbol = (typeof monetarySymbol === 'undefined') ? '' : monetarySymbol;
  
  // Verifica a definição de separador de milhares (Default: '.')
  thousandSeparator = (typeof thousandSeparator === 'undefined') ? '.' : thousandSeparator;
  
  // Verifica a definição de separador de decimais (Default: ',')
  decimalsSeparator = (typeof decimalsSeparator === 'undefined') ? ',' : decimalsSeparator;

  var
    value    = this

    // Separa o valor de sua parte decimal e converte para caractere
    negative = value < 0 ? "-" : "",
    base     = Math.floor(value).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".")
    decimals = value.toString().split('.')[1]
  ;

  // Verifica se temos casas decimais suficientes
  if (decimals.length <= decimalsPlaces) {
    // Enquanto o valor decimal contiver menos casas decimais do que o
    // necessário, acrescentamos zeros à direita
    while (decimals.length < size)
      decimals = decimals + "0";
  } else {
    // Retornamos apenas as casas decimais necessárias
    decimals = decimals.substr(0, decimalsPlaces);
  }

  return monetarySymbol + negative + base + "," + decimals;
};

// ==========================================================[ Numbers ]

// Estende o objeto Date padrão com um método addDays() que permite
// adicionar uma quantidade de dias à data. Uso:
//   someVar.addDays(days)
Date.prototype.addDays = function(days)
{
  var
    changeDate = new Date(this.valueOf() + days * 24 * 60 * 60 * 1000)
  ;
  
  return changeDate;
}

// Estende o objeto Date padrão com um método formatTo() que permite
// converter uma data em string, formatando-a no padrão DD/MM/YYYY ou
// YYYY-MM-DD (Padrão SQL). Uso:
//  someVar.format()
Date.prototype.formatTo = function(format) {
  var
    day   = this.getDate().toString().padLeftWithZeros(2),
    month = (this.getMonth() + 1).toString().padLeftWithZeros(2),
    year  = this.getFullYear().toString().padLeftWithZeros(4)
  ;

  if (format == 'DD/MM/YYYY') {
    // Retorna a data no formato DD/MM/YYYY
    return day + '/' + month + '/' + year;
  } else {
    // Retorna a data no formato SQL
    return year + '-' + month + '-' + day;
  }
}


// ---------------------------------------------------------------------
// Funções auxiliares de verificação
// ---------------------------------------------------------------------

// Verifica se um valor está vazio
function isEmpty(value) {
  switch(value) {
    case "":
    case 0:
    case "0":
    case null:
    case false:
    case typeof this == "undefined":
      return true;
    default:
      return false;
  }
}

// Verifica se um valor é nulo
function isNull (value) {
  return value === null;
}

// Verifica se um valor é uma string
function isString(value) {
  return typeof value === 'string'
    || value instanceof String;
}

// Verifica se um valor não é uma string
function isNotString(value) {
  return !isString(value);
}

// Verifica se um valor é um número
function isNumber(value) {
  return typeof value === 'number' && isFinite(value);
}

// Verifica se um valor é uma matriz
function isArray(value) {
  return value
    && typeof value === 'object'
    && value.constructor === Array;
}

// Verifica se um valor é um Email válido
function isMailAddress(mailAddress) {
  // Se o valor estiver em branco, retorna
  if (isEmpty(mailAddress)) {
    return false;
  }
  
  // Se o valor não for uma string, retorna
  if (isNotString(mailAddress)) {
    return false;
  }

  // Criamos uma expressão regular para testar se o e-mail é válido
  var
    validMailAddressReg = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
  
  return validMailAddressReg.test(mailAddress);
}

// Verifica se um CPF é válido
function isValidCPF(cpfCode) {
  // Se o valor estiver em branco, retorna
  if (isEmpty(cpfCode))
    return false;
  
  // Se o valor não for uma string, retorna
  if (isNotString(cpfCode))
    return false;

  // Recupera apenas os números, dispensando os sinais de pontuação
  var
    cpf = cpfCode.getOnlyNumbers()
  ;
  
  // Se, após recuperar apenas os números, o cpf estiver em branco,
  // retorna
  if (cpf.isEmpty()) {
    return false;
  }
  
  // Se o cpf não tiver a quantidade de dígitos corretos, retorna
  if (cpf.length < 11) {
    return false;
  }
  
  // Elimina CPFs inválidos conhecidos
  if (cpf == "00000000000" || 
      cpf == "11111111111" || 
      cpf == "22222222222" || 
      cpf == "33333333333" || 
      cpf == "44444444444" || 
      cpf == "55555555555" || 
      cpf == "66666666666" || 
      cpf == "77777777777" || 
      cpf == "88888888888" || 
      cpf == "99999999999") {
    return false;
  }
  
  // Valida os dígitos verificadores do CPF
  var
    numbers,
    digits,
    sum,
    result
  ;
  
  numbers = cpf.substring(0,9);
  digits  = cpf.substring(9);
  sum     = 0;
  
  for (var i = 10; i > 1; i--) {
    sum += numbers.charAt(10 - i) * i;
  }
  
  result = sum % 11 < 2 ? 0 : 11 - sum % 11;
  
  if (result != digits.charAt(0)) {
    return false;
  }
  
  numbers = cpf.substring(0,10);
  sum     = 0;
  
  for (var i = 11; i > 1; i--) {
    sum += numbers.charAt(11 - i) * i;
  }
  
  result = sum % 11 < 2 ? 0 : 11 - sum % 11;
  
  if (result != digits.charAt(1)) {
    return false;
  }
  
  return true;
}

// Verifica se um CNPJ é válido
function isValidCNPJ(cnpjCode)
{
  // Se o valor estiver em branco, retorna
  if (isEmpty(cnpjCode)) {
    return false;
  }
  
  // Se o valor não for uma string, retorna
  if (isNotString(cnpjCode)) {
    return false;
  }

  // Recupera apenas os números, dispensando os sinais de pontuação
  var
    cnpj = cnpjCode.getOnlyNumbers()
  ;
  
  // Se, após recuperar apenas os números, o cnpj estiver em branco,
  // retorna
  if (cnpj.isEmpty()) {
    return false;
  }
   
  // Se o cnpj não tiver a quantidade de dígitos corretos, retorna
  if (cnpj.length < 14) {
    return false;
  }
  
  // Elimina CNPJs inválidos conhecidos
  if (cnpj == "00000000000000" || 
      cnpj == "11111111111111" || 
      cnpj == "22222222222222" || 
      cnpj == "33333333333333" || 
      cnpj == "44444444444444" || 
      cnpj == "55555555555555" || 
      cnpj == "66666666666666" || 
      cnpj == "77777777777777" || 
      cnpj == "88888888888888" || 
      cnpj == "99999999999999") {
    return false;
  }
  
  // Valida os dígitos verificadores do CNPJ
  var
    numbers,
    digits,
    sum,
    pos,
    result
  ;

  numbers = cnpj.substring(0, 12);
  digits  = cnpj.substring(12);
  sum     = 0;
  pos     = 12 - 7;

  for (var i = 12; i >= 1; i--) {
    sum += numbers.charAt(12 - i) * pos--;

    if (pos < 2) {
      pos = 9;
    }
  }

  result = sum % 11 < 2 ? 0 : 11 - sum % 11;

  if (result != digits.charAt(0)) {
    return false;
  }
  
  numbers = cnpj.substring(0,13);
  sum     = 0;
  pos     = 13 - 7;

  for (var i = 13; i >= 1; i--) {
    sum += numbers.charAt(13 - i) * pos--;
    if (pos < 2) {
      pos = 9;
    }
  }

  result = sum % 11 < 2 ? 0 : 11 - sum % 11;

  if (result != digits.charAt(1)) {
    return false;
  }
  
  return true;
}

// Verifica se um RENAVAM é válido
function isValidRENAVAM(renavamCode) {
  // Se o valor estiver em branco, retorna
  if (isEmpty(renavamCode)) {
    return false;
  }
  
  // Se o valor não for uma string, retorna
  if (isNotString(renavamCode)) {
    return false;
  }

  // Recupera apenas os números, dispensando os sinais de pontuação
  var
    renavam = renavamCode.getOnlyNumbers()
  ;
  
  // Se, após recuperar apenas os números, o renavam estiver em branco,
  // retorna
  if (renavam.isEmpty()) {
    return false;
  }

  // Completa com zeros a esquerda se for no padrão antigo de 9 dígitos
  if (renavam.match("^([0-9]{9})$")) {
    renavam = "00" + renavam;
  }
  
  // Se o renavam não tiver a quantidade de dígitos corretos, retorna
  if(!renavam.match("[0-9]{11}")) {
    return false;
  }
  
  var
    // Remove o digito verificador (11 posição)
    renavamWithoutCS = renavam.substring(0, 10).split(""),

    // Inverte os caracteres (reverso)
    renavamReverseWithoutCS = renavamWithoutCS.reverse(),

    sum = 0
  ;
  
  
  // Multiplica o valor reverso do renavam pelos números multiplicadores
  // para apenas os primeiros 8 dígitos de um total de 10
  for (var i = 0; i < 8; i++) {
    sum += renavamReverseWithoutCS[i] * (i + 2);
  }
  
  // Multiplica os dois últimos dígitos e soma
  sum += renavamReverseWithoutCS[8] * 2;
  sum += renavamReverseWithoutCS[9] * 3;
  
  var
    // Calcula o resto da divisão por 11
    mod11 = sum % 11

    // Faz-se a conta 11 (valor fixo) - mod11
    verifyingDigit = 11 - mod11
  ;
  
  
  // Caso o valor calculado anteriormente seja 10 ou 11, transformo ele
  // em 0, senão é o próprio número
  verifyingDigit = (verifyingDigit > 9) ? 0:verifyingDigit;
  
  // Por último, comparo com o valor informado
  if (parseInt(renavam.substring(10)) == verifyingDigit) {
    return true;
  }
  
  return false;
}

// Verifica se um VIN é válido
function isValidVIN(vinCode) {
  // Criamos uma expressão regular para testar se o VIN é válido
  var
    validVIN = new RegExp("^[A-HJ-NPR-Z\\d]{9}[A-HJ-NPR-Z\\d]{2}\\d{6}$")
  ;
  
  return vinCode.match(validVIN);
}

// Verifica se um IMSI é válido
function isValidIMSI(imsiCode) {
  // Se o valor estiver em branco, retorna
  if (isEmpty(imsiCode)) {
    return false;
  }
  
  // Se o valor não for uma string, retorna
  if (isNotString(imsiCode)) {
    return false;
  }

  // Recupera apenas os números, dispensando os sinais de pontuação
  var
    imsi = imsiCode.getOnlyNumbers()
  ;
  
  // Se, após recuperar apenas os números, o IMSI estiver em branco,
  // retorna
  if (imsi.isEmpty()) {
    return false;
  }

  // Se o IMSI não tiver a quantidade de dígitos corretos, retorna
  if (imsi.length < 15) {
    return false;
  }
  
  return true;
}

// Verifica se um ICCID é válido
function isValidICCID(iccidCode) {
  // Se o valor estiver em branco, retorna
  if (isEmpty(iccidCode)) {
    return false;
  }
  
  // Se o valor não for uma string, retorna
  if (isNotString(iccidCode)) {
    return false;
  }

  // Recupera apenas os números, dispensando os sinais de pontuação
  var
    iccid = iccidCode.getOnlyNumbers()
  ;
  
  // Se, após recuperar apenas os números, o ICCID estiver em branco,
  // retorna
  if (iccid.isEmpty()) {
    return false;
  }
  
  // Se o ICCID não tiver a quantidade de dígitos corretos, retorna
  if ((iccid.length < 19) || (iccid.length > 20)) {
    return false;
  }
  
  // Valida o dígito verificador pelo algorítimo Luhn
  var
    length  = iccid.length - 1,
    numbers = iccid.substring(0, length),
    digit   = iccid.substring(length),
    sum     = 0,
    delta   = [ 0, 1, 2, 3, 4, -4, -3, -2, -1, 0],
    luhnDigit
  ;
  
  for (var i=0; i<numbers.length; i++ ) {
    sum += parseInt(numbers.substring(i,i+1));
  }
  
  for (var i=numbers.length-1; i>=0; i-=2) {
    sum += delta[parseInt(numbers.substring(i,i+1))];
  }
  
  if (10-(sum%10)===10) {
    luhnDigit = 0
  } else {
    luhnDigit = 10-(sum%10);
  }
  
  if (luhnDigit != parseInt(digit)) {
    return false;
  }
  
  return true;
}

// Verifica se um IMEI é válido
function isValidIMEI(imeiCode) {
  // Se o valor estiver em branco, retorna
  if (isEmpty(imeiCode)) {
    return false;
  }
  
  // Se o valor não for uma string, retorna
  if (isNotString(imeiCode)) {
    return false;
  }

  // Recupera apenas os números, dispensando os sinais de pontuação
  var
    imei = imeiCode.getOnlyNumbers()
  ;
  
  // Se, após recuperar apenas os números, o IMEI estiver em branco,
  // retorna
  if (imei.isEmpty()) {
    return false;
  }
  
  // Se o IMEI não tiver a quantidade de dígitos corretos, retorna
  if ((imei.length < 15) || (imei.length > 15)) {
    return false;
  }

  // Calcula o dígito verificador
  var
    sum        = 0,
    multiplier = 2,
    tp,
    digit
  ;

  for (i = 0; i < 14; i++) {
    digit = imei.substring(14 - i - 1, 14 - i);
    tp = parseInt(digit,10) * multiplier;
    if (tp >= 10)
      sum += (tp % 10) +1;
    else
      sum += tp;

    if (multiplier == 1) {
      multiplier++;
    } else {
      multiplier--;
    }
  }

  var
    verifyingDigit = ((10 - (sum % 10)) % 10)
  ;

  if (verifyingDigit != parseInt(imei.substring(14,15),10)) {
    return false;
  }

  return true;
}

// Verifica se um ano é bissexto
function isLeapYear(year) {
  return ((year % 4 == 0) && (year % 100 != 0)) || (year % 400 == 0);
}

// Verifica se uma data é válida
function isValidDate(date, format) {
  // Se o valor estiver em branco, retorna
  if (isEmpty(date)) {
    return false;
  }
  
  // Se o valor não for uma string, retorna
  if (isNotString(date)) {
    return false;
  }

  var
    year,
    month,
    day
  ;

  if (format == 'DD/MM/YYYY') {
    // Criamos uma expressão regular para testar se a data é válida
    var
      dateReg = /^(0[1-9]|[12][0-9]|3[01])[- /.](0[1-9]|1[012])[- /.]\d{4}$/;

    if (!dateReg.test(date)) {
      return false;
    }

    // Recupera as partes da data obtida do formato DD/MM/YYYY
    var
      dateArray = date.split("/")
    ;

    day   = parseInt(dateArray[0]);
    month = parseInt(dateArray[1]);
    year  = parseInt(dateArray[2]);
  } else {
    // Criamos uma expressão regular para testar se a data é válida
    var
      dateReg = /^\d{4}[- /.](0[1-9]|1[012])[- /.](0[1-9]|[12][0-9]|3[01])$/;
    
    if (!dateReg.test(date)) {
      return false;
    }

    // Recupera as partes da data obtida do formato YYYY-MM-DD
    var
      dateArray = date.split("-")
    ;

    day   = parseInt(dateArray[2]);
    month = parseInt(dateArray[1]);
    year  = parseInt(dateArray[0]);
  }

  switch (month) {
    case 2:
      // Verificamos se o ano é bissexto
      if (isLeapYear(year)) {
        // Em anos bissextos, fevereiro não pode ter mais do que 29 dias
        if (day > 29) {
          return false;
        }
      } else {
        // Em anos que não sejam bissextos, fevereiro não pode ter mais
        // do que 28 dias
        if (day > 28) {
          return false;
        }
      }

      break;
    case  4:
    case  6:
    case  9:
    case 11:
      // Os dias não podem ter mais do que 30 dias, senão retorna
      if (day > 30) {
        return false;
      }

      break;
  }

  return true;
}
