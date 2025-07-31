/* ---------------------------------------------------------------------
 * masked.input.js
 * 
 * This file is part of Extension Library.
 *
 * (c) Emerson Cavalcanti
 *
 * LICENSE: The MIT License (MIT)
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the 
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject
 * to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
 * CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 * 
 * ---------------------------------------------------------------------
 * Descrição:
 * 
 * A classe que fornece uma maneira simples de mascaramento de campos
 * usando expressões regulares, permitindo o uso em celulares e em
 * desktops, bem como recortar e colar.
 * 
 * --------------------------------------------------------------------
 * Controle de Modificações
 * 
 * 2017-08-30 - Emerson Cavalcanti
 *   - Versão inicial
 * 
 * 2018-10-01 - Emerson Cavalcanti
 *   - Reescrita usando os novos padrões da internet
 * 
 * 2019-07-04 - Emerson Cavalcanti
 *   - Inclusão da máscara para IMEI
 *
 * 2020-01-27 - Emerson Cavalcanti
 *   - Adaptação para sintaxe semântica
 *   - Incluída as funções de validação, de forma a sinalizar no próprio
 * componente erros nos valores, além da função de máscaramento.
 *
 * 2020-04-22 - Emerson Cavalcanti
 *   - Adaptação do mascaramento de placas para aceitar o novo padrão
 * mercosul.
 *
 * 2020-06-10 - Emerson Cavalcanti
 *   - Incluído o mascaramento de caracteres alfanuméricos.
 *
 * 2022-07-21 - Emerson Cavalcanti
 *   - Incluída a validação de campos de hora curtos (hh:mm).
 *
 * 2022-07-25 - Emerson Cavalcanti
 *   - Incluído um campo à mais no telefone para permitir colar
 *     corretamente telefones digitados com um '.' à mais, tal como em
 *     (11) 9.8330-1234
 * ---------------------------------------------------------------------
 */

;(function ( $, window, document, undefined ) {
  'use strict';

  var
    // O nome do plugin. Esta variável é usada no construtor do plugin,
    // assim como o wrapper do plugin para construir a chave para o
    // método "$.data".
    pluginName       = 'MaskedInput',
    
    // O namespace para eventos e o módulo
    eventNamespace   = '.' + pluginName,
    moduleNamespace  = "plugin_" + pluginName,

    // Os nomes das classes
    className        = {
      empty          : 'empty',
      invalid        : 'error',
      invalidIcon    : 'close',
      mask           : 'ui icon input',
      valid          : 'success',
      validIcon      : 'check'
    },

    // Os seletores
    selector       = {
      icon         : '.icon'
    }
  ;

  // O construtor do plugin cria uma nova instância do plugin para o
  // nó(s) DOM a partir do qual o plugin foi chamado.
  function MaskedInput ( element, options ) {
    var
      settings  = ( $.isPlainObject(options) )
        ? $.extend(true, {}, $.fn.mask.defaults, options)
        : $.extend({}, $.fn.mask.defaults),

      $module   = $(element),

      // O DIV parente
      $field,

      // O ícone que indica visualmente a situação da validação
      $icon,

      // O temporizador para atraso na execução
      timer     = null,

      // O ícone original (antes da criação deste componente)
      originalIconClass,

      // O tamanho máximo definido
      $maxLength,

      module
    ;

    module = {
      // =======================================[ Criação componente ]==
      initialize: function () {
        module.verbose('Inicializando componente');
        module.create.component();
        module.bind.events();
      },
      destroy: function() {
        module.verbose('Destruindo componente');
        module.unbind.events();
        module.remove.validate();
        $module.removeData();
      },

      // As funções de construção
      create: {
        // Faz a criação do nosso componente
        component: function() {
          // Inicializa os elementos do nosso componente
          var
            // O tamanho das máscaras
            maskLengthPerMask = {
              // Data/hora
              'date': 10,
              'time': 8,
              'shorttime': 5,
              'year': 4,
              
              // Documentos e códigos
              'cpf': 14,
              'cnpj': 18,
              'cpforcnpj': 18,
              'iccid': 20,
              'imei': 18,
              'imsi': 15,
              'phoneNumber': 16,
              'plate': 7,
              'postalCode': 9,
              'renavam': 11,
              'state': 2,
              'vin': 17,

              // Valores
              'monetary': 18,
              'numeric': 18,
              'percentage': 19
            }
          ;

          // Seta o comprimento máximo do campo, quando definido
          if (typeof maskLengthPerMask[settings.type] !== 'undefined') {
            // Recupera o tamanho máximo definido na máscara
            $maxLength = maskLengthPerMask[settings.type];

            // Verifica se foi informada uma limitação diferente
            if (settings.maxLength !== null) {
              if (['monetary', 'numeric', 'percentage'].includes(settings.type)) {
                // Sobrepõe o comprimento máximo estipulado na máscara
                $maxLength = settings.maxLength;
              }
            }

            // Define o tamanho máximo conforme estipulado
            $module
              .attr('maxlength', $maxLength)
            ;
            module.verbose('Tamanho máximo da entrada definida',
              $maxLength
            );
          } else {
            // Limita a quantidade de dígitos apenas se informado
            if (settings.maxLength !== null) {
              // Define o tamanho máximo
              $maxLength = settings.maxLength;
              $module
                .attr('maxlength', $maxLength)
              ;
              module.verbose('Tamanho máximo da entrada definida',
                $maxLength
              );
            }
          }

          // Localizamos o ícone e o DIV parente para poder sinalizar em
          // caso de validação
          if (settings.validate) {
            $field  = $module.closest("div.field");
            $icon   = $module.siblings("i.icon");
            
            // Recupera as classes originais do ícone
            if ($icon.length ) {
              originalIconClass = module.get.iconClass($icon);
            } else {
              originalIconClass = '';
            }

            if (settings.type == 'date') {
              className.validIcon = 'calendar check';
              className.invalidIcon = 'calendar times';
            }
          }
        }
      },

      // ====================================[ Vinculação de eventos ]==

      // As funções de vinculação dos eventos
      bind: {
        // Vincula os eventos que acionam métodos
        events: function() {
          // Vinculamos os eventos aos seus respectivos manipuladores
          // que acionam outras funções. Todos os eventos são
          // namespaced.
          module.verbose('Vinculando eventos para mascaramento');
          module.bind.inputEvents();
          
          // Verifica se o componente já possui conteúdo em sua
          // inicialização
          if ($module.val().length > 0) {
            // Força o mascaramento
            $module.trigger('change');
          }
        },

        // Lida com os eventos de entrada
        inputEvents: function() {
          module.verbose('Vinculando eventos de entrada');

          $module
            .on('input'     + eventNamespace, module.event.input)
            .on('change'    + eventNamespace, module.event.input)
          ;
        }
      },

      // As funções de desvinculação dos eventos
      unbind: {
        events: function() {
          // Desvincula todos os eventos no namespace do nosso plugin
          // que estão anexados
          $module
            .off(eventNamespace)
          ;
        }
      },

      // ==================================================[ Eventos ]==

      event: {
        input: function(event) {
          module.verbose('Evento de entrada');

          var
            value = module.get.value()
          ;

          if (module.is.empty(value)) {
            if (settings.validate) {
              // Elimina quaisquer sinalizações
              module.set.status.empty();
            }
            if (settings.trim) {
              module.set.value(value.trim());
            }

            return;
          }
          
          // Determina o tipo de máscara para aplicar
          switch (settings.type) {
            // Date e hora
            case         'date': value = module.set.mask.date( value ); break;
            case         'time': value = module.set.mask.time( value ); break;
            case    'shorttime': value = module.set.mask.time( value, 4 ); break;
            case         'year': value = module.set.mask.number( value ); break;
            
            // Documentos
            case          'cpf': value = module.set.mask.cpf( value ); break;
            case         'cnpj': value = module.set.mask.cnpj( value ); break;
            case    'cpforcnpj': value = module.set.mask.cpforcnpj( value ); break;
            case        'iccid': value = module.set.mask.iccid( value ); break;
            case         'imei': value = module.set.mask.imei( value ); break;
            case         'imsi': value = module.set.mask.imsi( value ); break;
            case  'phoneNumber': value = module.set.mask.phoneNumber( value ); break;
            case        'plate': value = module.set.mask.plate( value ); break;
            case   'postalCode': value = module.set.mask.postalCode( value ); break;
            case      'renavam': value = module.set.mask.renavam( value ); break;
            case        'state': value = module.set.mask.state( value ); break;
            case          'vin': value = module.set.mask.vin( value ); break;
            
            // Valores
            case 'alphanumeric': value = module.set.mask.alphanumeric( value ); break;
            case     'monetary': value = module.set.mask.numericValue( value ); break;
            case      'numeric': value = module.set.mask.numericValue( value ); break;
            case       'number': value = module.set.mask.number( value ); break;
            case   'percentage': value = module.set.mask.numericValue( value ); break;
            case     'interval': value = module.set.mask.intervalValue( value ); break;
            default            : value = module.set.mask.number( value ); break;
          }
          
          // Atribui o valor mascarado novamente ao componente
          if (settings.trim) {
            module.set.value(value.trim());
          } else {
            module.set.value(value);
          }
        }
      },

      // ================================================[ Depuração ]==

      debug: function() {
        if (settings.debug) {
          module.debug = Function.prototype.bind.call(console.info, console, pluginName + ':');
          module.debug.apply(console, arguments);
        }
      },
      verbose: function() {
        if (settings.debug && settings.verbose) {
          module.verbose = Function.prototype.bind.call(console.warn, console, pluginName + ':');
          module.verbose.apply(console, arguments);
        }
      },
      error: function() {
        module.error = Function.prototype.bind.call(console.error, console, pluginName + ':');
        module.error.apply(console, arguments);
      },

      // ====================================================[ Ações ]==

      get: {
        // Retorna as classes que definem o ícone
        iconClass: function($element) {
          var
            classList = $($element).attr("class").split(/\s+/),
            classes = []
          ;

          // Adicionamos todas as classes que não sejam 'icon'
          $.each(classList, function(index, className) {
            if (className !== 'icon') {
              if (classes.indexOf(className) === -1) {
                module.verbose('Armazenando a classe', className);
                classes.push(className);
              }
            }
          });

          module.verbose('Ícone original', classes.join(' '));

          return classes.join(' ');
        },

        // Retorna o valor de entrada
        value: function() {
          return $.trim($module.val());
        },
        // Retorna o sinal de um valor
        onlySignal: function(value) {
          if (settings.allowNegativeValues) {
            module.verbose('Recuperando o sinal do valor', value);

            return (value.charAt(0) === '-')
              ? '-'
              : ''
            ;
          }

          module.verbose('Ignorando o sinal do valor', value);
          return '';
        },
        // Retorna apenas os números de um valor
        onlyNumbers: function(value, forceZeroValue) {
          module.verbose('Recuperando apenas números do valor', value);

          // Força o valor padrão
          if (forceZeroValue === undefined) {
            forceZeroValue = false;
          }
          
          if (forceZeroValue) {
            module.verbose('Forçando valor zero');
            // Se o valor for nulo, força o valor como sendo "0"
            if ((value === undefined) ||
                (value === null) ||
                (value === '-') ||
                (value === '')) {
              value = '0';
            }
          }
          
          // Remove qualquer caractere, deixando apenas os dígitos
          return value.replace(/\D/g,"");
        },
        // Retorna apenas as letras de um valor
        onlyLetters: function(value) {
          module.verbose('Recuperando apenas letras do valor', value);
          // Remove qualquer caractere, deixando apenas as letras
          return value.replace(/[^a-z]/gi,"");
        },
        onlyAlphanumeric: function(value) {
          module.verbose('Recuperando apenas letras e números do valor', value);
          // Remove qualquer caractere, deixando apenas as letras e números
          return value.replace(/[^a-zA-Z0-9]/gi,"");
        },
        onlyValidCharacters: function(value, filter) {
          module.verbose('Recuperando apenas os caracteres válidos', filter, 'do valor', value);
          // Retorna apenas os caracteres válidos
          var invalidCharacters = new RegExp("[^" + filter + "]", "gi");
          
          // Remove qualquer caractere, deixando apenas os caracteres válidos
          return value.replace(invalidCharacters,"");
        }
      },
      is: {
        // Verifica se o valor está vazio
        empty: function(value) {
          return (value.length === 0);
        },
        // Verifica se o ano é bissexto
        leapYear: function(year) {
          return ((year % 4 == 0) && (year % 100 != 0))
          || (year % 400 == 0);
        },
        // Os validadores
        valid: {
          // Validador para CNPJ
          cnpj: function(value) {
            var
              knownInvalidValues = [
                '00000000000000',
                '11111111111111',
                '22222222222222',
                '33333333333333',
                '44444444444444',
                '55555555555555',
                '66666666666666',
                '77777777777777',
                '88888888888888',
                '99999999999999'
              ],
              numbers,
              digits,
              sum,
              pos,
              result
            ;

            // Elimina CNPJs inválidos conhecidos
            if (knownInvalidValues.includes(value)) {
              return false;
            }

            numbers = value.substring(0, 12);
            digits  = value.substring(12);
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
            
            numbers = value.substring(0,13);
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
          },
          // Validador para CPF
          cpf: function(value) {
            var
              knownInvalidValues = [
                '00000000000',
                '11111111111',
                '22222222222',
                '33333333333',
                '44444444444',
                '55555555555',
                '66666666666',
                '77777777777',
                '88888888888',
                '99999999999'
              ],
              numbers,
              digits,
              sum,
              result
            ;

            // Elimina CPFs inválidos conhecidos
            if (knownInvalidValues.includes(value)) {
              return false;
            }

            numbers = value.substring(0,9);
            digits  = value.substring(9);
            sum     = 0;
            
            for (var i = 10; i > 1; i--) {
              sum += numbers.charAt(10 - i) * i;
            }
            
            result = sum % 11 < 2 ? 0 : 11 - sum % 11;
            
            if (result != digits.charAt(0)) {
              return false;
            }
            
            numbers = value.substring(0,10);
            sum     = 0;
            
            for (var i = 11; i > 1; i--) {
              sum += numbers.charAt(11 - i) * i;
            }
            
            result = sum % 11 < 2 ? 0 : 11 - sum % 11;
            
            if (result != digits.charAt(1)) {
              return false;
            }
            
            return true;
          },
          // Validador para Data
          date: function(value) {
            var
              parts = value.split("/"),
              year  = parseInt(parts[2]),
              month = parseInt(parts[1]),
              day   = parseInt(parts[0])
            ;
            module.debug('Contém', year, month, day);

            // Analisa em função do mês do ano
            switch (month) {
              case 2:
                // Verificamos se o ano é bissexto
                if (module.is.leapYear(year)) {
                  // Em anos bissextos, fevereiro não pode ter mais do
                  // que 29 dias
                  if (day > 29) {
                    return false;
                  }
                } else {
                  // Em anos que não sejam bissextos, fevereiro não pode
                  // ter mais do que 28 dias
                  if (day > 28) {
                    return false;
                  }
                }

                break;
              case  4:
              case  6:
              case  9:
              case 11:
                // Os dias não podem ter mais do que 30 dias, senão
                // retorna
                if (day > 30) {
                  return false;
                }

                break;
              default:
                // Os dias não podem ter mais do que 31 dias, senão
                // retorna
                if (day > 31) {
                  return false;
                }

                break;
            }

            return true;
          },
          // Validador para ICCID
          iccid: function(value) {
            // Valida o dígito verificador pelo algorítimo Luhn
            var
              length  = value.length - 1,
              numbers = value.substring(0, length),
              digit   = value.substring(length),
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
          },
          // Validador para IMEI
          imei: function(value) {
            var
              sum        = 0,
              multiplier = 2,
              tp,
              digit
            ;

            for (i = 0; i < 14; i++) {
              digit = value.substring(14 - i - 1, 14 - i);
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

            if (verifyingDigit != parseInt(value.substring(14,15),10)) {
              return false;
            }

            return true;
          },
          // Validador para RENAVAM
          renavam: function(value) {
            // Completa com zeros a esquerda se o valor estiver no
            // padrão antigo de 9 dígitos
            if (value.match("^([0-9]{9})$")) {
              value = '00' + value;
            }

            var
              // Remove o digito verificador (11 posição)
              valueWithoutCS = value.substring(0, 10).split(""),

              // Inverte os caracteres (reverso)
              valueReverseWithoutCS = valueWithoutCS.reverse(),

              sum = 0
            ;

            // Multiplica o valor reverso do renavam pelos números
            // multiplicadores para apenas os primeiros 8 dígitos de um
            // total de 10
            for (var i = 0; i < 8; i++) {
              sum += valueReverseWithoutCS[i] * (i + 2);
            }
            
            // Multiplica os dois últimos dígitos e soma
            sum += valueReverseWithoutCS[8] * 2;
            sum += valueReverseWithoutCS[9] * 3;
            
            var
              // Calcula o resto da divisão por 11
              mod11 = sum % 11

              // Faz-se a conta 11 (valor fixo) - mod11
              verifyingDigit = 11 - mod11
            ;
            
            // Caso o valor calculado anteriormente seja 10 ou 11,
            // transformo ele em 0, senão é o próprio número
            verifyingDigit = (verifyingDigit > 9) ? 0:verifyingDigit;
            
            // Por último, comparo com o valor informado
            if (parseInt(value.substring(10)) == verifyingDigit) {
              return true;
            }
            
            return false;
          },
          state: function(value) {
            var
              states = [
                'AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO',
                'MA', 'MG', 'MS', 'MT', 'PA', 'PB', 'PE', 'PI', 'PR',
                'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO'
              ]
            ;

            return (states.indexOf(value) === -1)?false:true;
          },
          // Validador para Hora
          time: function(value) {
            var
              parts  = value.split(":"),
              hour   = parseInt(parts[0]),
              minute = parseInt(parts[1]),
              second = parseInt(parts[2])
            ;

            // As horas não podem ser superiores à 24
            if (hour > 24) {
              return false;
            }

            // Os minutos e segundos não podem ser superiores à 60
            if ((minute > 60) || (second > 60)) {
              return false;
            }

            return true;
          },
          // Validador para Hora reduzida (hora e minuto)
          shorttime: function(value) {
            var
              parts  = value.split(":"),
              hour   = parseInt(parts[0]),
              minute = parseInt(parts[1])
            ;

            // As horas não podem ser superiores à 24
            if (hour > 24) {
              return false;
            }

            // Os minutos e segundos não podem ser superiores à 60
            if (minute > 60) {
              return false;
            }

            return true;
          },
          // Validador para VIN - Vehicle Identification Number
          vin: function(value) {
            // Provisoriamente desativamos. Existe uma maneira melhor de
            // calcular se o código é valido, então vamos implementar
            // futuramente
            return true;
            //var
            //  validVIN = new RegExp("^[A-HJ-NPR-Z\\d]{9}[A-HJ-NPR-Z\\d]{2}\\d{6}$")
            //;
            //
            //return value.match(validVIN);
          }
        }
      },
      set: {
        // As máscaras a serem aplicadas
        mask: {
          // Máscara para CNPJ no formato 00.000.000/0000-00
          cnpj: function(value) {
            module.debug('Mascarando para CNPJ');
            var
              maskedValue = module.get.onlyNumbers(value)
            ;
            
            // Limita a quantidade máxima de números possíveis
            if (maskedValue.length > 14) {
              maskedValue = maskedValue.substring(0, 14);
            }

            if (settings.validate) {
              if (maskedValue.length > 0) {
                if ((maskedValue.length == 14) &&
                    (module.is.valid.cnpj(maskedValue))) {
                  module.set.status.valid();
                } else {
                  module.set.status.invalid();
                }
              } else {
                module.set.status.empty();
              }
            }
            
            // Coloca um ponto entre o segundo e o terceiro dígitos
            maskedValue = maskedValue.replace(/^(\d{2})(\d)/,"$1.$2");
            
            // Coloca um ponto entre o quinto e o sexto dígitos
            maskedValue = maskedValue.replace(/^(\d{2})\.(\d{3})(\d)/,"$1.$2.$3")
            
            // Coloca uma barra entre o oitavo e o nono dígitos
            maskedValue = maskedValue.replace(/\.(\d{3})(\d)/,".$1/$2");
            
            // Coloca um hífen depois do bloco de quatro dígitos
            maskedValue = maskedValue.replace(/(\d{4})(\d)/,"$1-$2");
            
            return maskedValue;
          },
          // Máscara para CPF no formato 999.999.999-99
          cpf: function(value) {
            module.debug('Mascarando para CPF');
            var
              maskedValue = module.get.onlyNumbers(value)
            ;
            
            // Limita a quantidade máxima de números possíveis
            if (maskedValue.length > 11) {
              maskedValue = maskedValue.substring(0, 11);
            }

            if (settings.validate) {
              if (maskedValue.length > 0) {
                if ((maskedValue.length == 11) &&
                    (module.is.valid.cpf(maskedValue))) {
                  module.set.status.valid();
                } else {
                  module.set.status.invalid();
                }
              } else {
                module.set.status.empty();
              }
            }
            
            // Coloca um ponto entre o terceiro e o quarto dígitos
            maskedValue = maskedValue.replace(/(\d{3})(\d)/,"$1.$2");
            
            // Coloca um ponto entre o terceiro e o quarto dígitos
            // de novo (para o segundo bloco de números)
            maskedValue = maskedValue.replace(/(\d{3})(\d)/,"$1.$2");
            
            // Coloca um hífen entre o terceiro e o quarto dígitos
            maskedValue = maskedValue.replace(/(\d{3})(\d{1,2})$/,"$1-$2");
            
            return maskedValue;
          },
          // Máscara para CPF ou CNPJ no mesmo campo (determinado pela
          // quantidade de dígitos
          cpforcnpj: function(value) {
            module.debug('Mascarando para CPF ou CNPJ');
            var
              maskedValue = module.get.onlyNumbers(value)
            ;
            
            module.verbose('Determinando máscara a ser utilizada');
            if (maskedValue.length > 11) {
              // Máscara para CNPJ
              return module.set.mask.cnpj(maskedValue);
            } else {
              // Máscara para CPF
              return module.set.mask.cpf(maskedValue);
            }
          },
          // Máscara para data no formato DD/MM/YYYY
          date: function(value) {
            module.debug('Mascarando para data');
            var
              maskedValue = module.get.onlyNumbers(value)
            ;
            
            // Limita a quantidade máxima de números possíveis
            if (maskedValue.length > 8) {
              maskedValue = maskedValue.substring(0, 8);
            }
            
            // Coloca uma barra entre o segundo e o terceiro dígitos
            maskedValue = maskedValue.replace(/(\d{2})(\d)/,"$1/$2");
            
            // Coloca uma barra entre o segundo e o terceiro dígitos de novo
            // (para o segundo bloco de números)
            maskedValue = maskedValue.replace(/(\d{2})(\d)/,"$1/$2");

            if (settings.validate) {
              module.debug('Validando data');
              if (maskedValue.length > 0) {
                module.debug('Contém algum valor');
                if ((maskedValue.length == 10) &&
                    (module.is.valid.date(maskedValue))) {
                  module.set.status.valid();
                } else {
                  module.set.status.invalid();
                }
              } else {
                module.set.status.empty();
              }
            }
            
            return maskedValue;
          },
          // Máscara para ICCID - Integrated Circuit Card Identifier
          iccid: function(value) {
            module.debug('Mascarando para ICCID');
            var
              maskedValue = module.get.onlyNumbers(value)
            ;

            // Limita a quantidade máxima de números possíveis
            if (maskedValue.length > 20) {
              maskedValue = maskedValue.substring(0, 19);
            }

            if (settings.validate) {
              if (maskedValue.length > 0) {
                if ((maskedValue.length > 18) &&
                    (module.is.valid.iccid(maskedValue))) {
                  module.set.status.valid();
                } else {
                  module.set.status.invalid();
                }
              } else {
                module.set.status.empty();
              }
            }
            
            return maskedValue;
          },
          // Máscara para IMEI - International Mobile Equipment Identity no
          // formato nnnnnn-nn-nnnnnn-n
          imei: function(value) {
            module.debug('Mascarando para IMEI');
            var
              maskedValue = module.get.onlyNumbers(value)
            ;
            
            // Limita a quantidade máxima de números possíveis
            if (maskedValue.length > 15) {
              maskedValue = maskedValue.substring(0, 14);
            }

            if (settings.validate) {
              if (maskedValue.length > 0) {
                if ((maskedValue.length > 14) &&
                    (module.is.valid.imei(maskedValue))) {
                  module.set.status.valid();
                } else {
                  module.set.status.invalid();
                }
              } else {
                module.set.status.empty();
              }
            }
            
            // Coloca um traço entre o sexto e o sétimo dígitos
            maskedValue = maskedValue.replace(/^(\d{6})(\d)/,"$1-$2");
            
            // Coloca um traço entre o oitavo e o nono dígitos
            maskedValue = maskedValue.replace(/^(\d{6})\-(\d{2})(\d)/,"$1-$2-$3")
            
            // Coloca um traço entre o décimo quarto e o décimo quinto dígitos
            maskedValue = maskedValue.replace(/^(\d{6})\-(\d{2})\-(\d{6})(\d)/,"$1-$2-$3-$4")
            
            return maskedValue;
          },
          // Máscara para IMSI - International Mobile Subscriber
          // Identity
          imsi: function(value) {
            module.debug('Mascarando para IMSI');
            var
              maskedValue = module.get.onlyNumbers(value)
            ;
            
            // Limita a quantidade máxima de números possíveis
            if (maskedValue.length > 15) {
              maskedValue = maskedValue.substring(0, 14);
            }

            if (settings.validate) {
              if (maskedValue.length > 0) {
                if (maskedValue.length == 15) {
                  module.set.status.valid();
                } else {
                  module.set.status.invalid();
                }
              } else {
                module.set.status.empty();
              }
            }
            
            return maskedValue;
          },
          // Máscara para valores alfanuméricos
          alphanumeric: function(value) {
            module.debug('Mascarando para valor alfanumérico');

            // Se estiver definido que devemos ter apenas caracteres
            // minúsculos, então garante isto
            if (settings.lowercase) {
              value = value.toLowerCase();
            }

            // Se estiver definido que devemos ter apenas caracteres
            // maiúsculos, então garante isto
            if (settings.uppercase) {
              value = value.toUpperCase();
            }

            var
              maskedValue = module.get.onlyAlphanumeric(value)
            ;

            return maskedValue;
          },
          // Máscara para valores de intervalo
          intervalValue: function(value) {
            module.debug('Mascarando para descrição de intervalos');
            var
              // Recupera os caracteres a serem analisados, excluindo
              // quaisquer caracteres não alfanuméricos
              maskedValue = value.replace(/(\/|\D)(?=\1)|^[\/\D]|[^\/\d]$/g, '')
            ;
            
            return maskedValue;
          },
          // Máscara para valores numéricos (valores com casas decimais)
          // tais como valores monetários e de porcentagem
          numericValue: function(value) {
            module.debug('Mascarando para um valor numérico');
            var
              signal = module.get.onlySignal(value),
              maskedValue = module.get.onlyNumbers(value, true),
              decimalsPlaces = settings.decimalsPlaces,
              minimumLength = decimalsPlaces + 1,
              amountOfPoints = Math.floor(($maxLength - minimumLength) / 4),
              maximumLength = $maxLength - amountOfPoints - 1
            ;

            if (settings.allowNegativeValues) {
              // Retiramos 1 caracter para permitir o sinal
              maximumLength--;
            }

            // Remove zeros a esquerda
            if (maskedValue.length > minimumLength) {
              maskedValue = maskedValue.replace(/^0+/, '');
            }
            
            // Completa com zeros a esquerda para garantir um tamanho
            // mínimo
            if (maskedValue.length < minimumLength) {
              do {
                maskedValue = '0' + maskedValue;
              } while (maskedValue.length < minimumLength)
            }
            
            // Limita a quantidade máxima de números possíveis
            if (maskedValue.length > maximumLength) {
              maskedValue = maskedValue.substring(0, maximumLength);
            }

            // Separamos os valores inteiros das casas decimais para
            // simplificar a formatação
            var
              decimalNumbers = maskedValue.substr(-decimalsPlaces),
              integerNumbers = maskedValue.substr(0, maskedValue.length - decimalsPlaces)
            ;

            // Colocamos os pontos entre milhares
            integerNumbers = integerNumbers.replace(/(\d)(?=(\d{3})+$)/g, '$1.');
            
            // Por último, montamos o resultado final, colocando a parte
            // inteira, seguido da virgula e dos valores decimais
            maskedValue = integerNumbers + ',' + decimalNumbers;
            
            return signal + maskedValue;
          },
          // Máscara para valores numéricos
          number: function(value) {
            module.debug('Mascarando para número');
            return module.get.onlyNumbers(value);
          },
          // Máscara para telefone com 9 dígitos ((99) 99999-9999)
          phoneNumber: function(value) {
            module.debug('Mascarando para telefone');
            var
              maskedValue = module.get.onlyNumbers(value)
            ;
            module.debug('Valor inicial', maskedValue);
            
            // Limita a quantidade máxima de números possíveis
            module.debug('Valor contém', maskedValue.length, 'dígitos');
            if (maskedValue.length > 11) {
              maskedValue = maskedValue.substring(0, 11);
            }
            
            // Coloca os parênteses em volta dos dois primeiros dígitos
            maskedValue = maskedValue.replace(/^(\d{2})(\d)/g,"($1) $2");
            module.debug('Colocou os parênteses em volta dos dois primeiros dígitos', maskedValue);
            
            // Coloca o hífen entre o quarto e o quinto dígitos
            maskedValue = maskedValue.replace(/(\d)(\d{4})$/,"$1-$2");
            module.debug('Colocou o hífen entre o quarto e o quinto dígitos', maskedValue);
            
            return maskedValue;
          },
          // Máscara para placas no formato AAA9999
          plate: function(value) {
            module.debug('Mascarando para placa');
            var
              mask = 'AAA9*99',
              patterns = {
                '9' : '0-9',
                'A' : 'a-zA-Z',
                '*' : 'a-zA-Z0-9',
              },

              // Recupera os caracteres a serem analisados, excluindo
              // quaisquer caracteres não alfanuméricos
              characters = value.replace(/[^a-zA-Z0-9]/gi,''),
              pattern,
              plate = '',
              nextChar,
              pos = 0
            ;

            module.verbose('Temos', characters.length, 'a serem analisados');

            if (characters.length > 0) {
              // Vamos retirando cada caractere e analisando conforme a
              // máscara a ser respeitada
              do {
                // Primeiramente extraímos da máscara o próximo padrão
                // de análise
                pattern = patterns[ mask.charAt(pos) ];
                module.verbose('Analisando o', (pos + 1), 'caractere da máscara:', mask.charAt(pos));
                module.verbose('Utilizando o padrão:', pattern);
                
                // Agora extraímos o próximo caractere e analisamos
                // segundo o nosso padrão de análise da máscara
                nextChar = module.get.onlyValidCharacters(characters.charAt(0), pattern);
                module.verbose('Valor recuperado usando o padrão:', nextChar);

                if (nextChar.length > 0) {
                  module.verbose('O caractere', nextChar, 'foi considerado válido');
                  // Conseguimos um caractere válido, então adicionamos
                  // ao resultado
                  plate += nextChar;

                  // Incrementamos a posição em nossa máscara
                  pos++;

                  // Se a máscara tiver terminado, terminamos
                  if (pos > mask.length) {
                    break;
                  }
                }

                module.verbose('Plate contém', plate);
                // Eliminamos o caractere analisado e prosseguimos
                characters = characters.slice(1);
              } while (characters.length > 0);
            }

            // Retorna a placa
            return plate.toUpperCase();
          },
          // Máscara para CEP no formato 99999-999
          postalCode: function(value) {
            module.debug('Mascarando para CEP');
            var
              maskedValue = module.get.onlyNumbers(value)
            ;
            
            // Limita a quantidade máxima de números possíveis
            if (maskedValue.length > 8) {
              maskedValue = maskedValue.substring(0, 8);
            }
            
            // Coloca o hífen entre o quinto e o sexto dígito
            maskedValue = maskedValue.replace(/^(\d{5})(\d)/,"$1-$2");
            
            return maskedValue;
          },
          renavam: function(value) {
            module.debug('Mascarando para RENAVAM');
            // Máscara para RENAVAM
            var
              maskedValue = module.get.onlyNumbers(value)
            ;
            
            // Limita a quantidade máxima de números possíveis
            if (maskedValue.length > 11) {
              maskedValue = maskedValue.substring(0, 11);
            }

            if (settings.validate) {
              if (maskedValue.length > 0) {
                if (((maskedValue.length == 8) || (maskedValue.length == 11)) &&
                    (module.is.valid.renavam(maskedValue))) {
                  module.set.status.valid();
                } else {
                  module.set.status.invalid();
                }
              } else {
                module.set.status.empty();
              }
            }

            return maskedValue;
          },
          // Máscara para UFs no formato AA
          state: function(value) {
            module.debug('Mascarando para UFs');
            var
              maskedValue = module.get.onlyLetters(value).toUpperCase()
            ;

            // Limita a quantidade máxima de números possíveis
            if (maskedValue.length > 2) {
              maskedValue = maskedValue.substring(0, 1);
            }

            if (settings.validate) {
              if (maskedValue.length > 0) {
                if ((maskedValue.length == 2) &&
                    (module.is.valid.state(maskedValue))) {
                  module.set.status.valid();
                } else {
                  module.set.status.invalid();
                }
              } else {
                module.set.status.empty();
              }
            }
            
            return maskedValue;
          },
          // Máscara para tempo no formato HH:MM:SS
          time: function(value, size) {
            module.debug('Mascarando para hora');
            var
              maskedValue = module.get.onlyNumbers(value)
            ;

            // Força o valor padrão
            if (size === undefined) {
              size = 6;
            }
            
            // Limita a quantidade máxima de números possíveis
            if (maskedValue.length > size) {
              maskedValue = maskedValue.substring(0, size);
            }
            
            // Coloca dois pontos entre o segundo e o terceiro dígitos
            maskedValue = maskedValue.replace(/(\d{2})(\d)/,"$1:$2");
            
            if (size > 4) {
              // Coloca dois pontos entre o segundo e o terceiro dígitos
              // de novo (para o segundo bloco de números)
              maskedValue = maskedValue.replace(/(\d{2})(\d)/,"$1:$2");
            }

            if (settings.validate) {
              if (maskedValue.length > 0) {
                if (size==4) {
                  // Formato de hora reduzido (Hora e minuto)
                  if ((maskedValue.length == 5) &&
                      (module.is.valid.shorttime(maskedValue))) {
                    module.set.status.valid();
                  } else {
                    module.set.status.invalid();
                  }
                } else {
                  // Formato de hora completo (Hora, minuto e segundo)
                  if ((maskedValue.length == 8) &&
                      (module.is.valid.time(maskedValue))) {
                    module.set.status.valid();
                  } else {
                    module.set.status.invalid();
                  }
                }
              } else {
                module.set.status.empty();
              }
            }
            
            return maskedValue;
          },
          // Máscara para VIN - Vehicle Identification Number
          vin: function(value) {
            module.debug('Mascarando para VIN');
            var
              maskedValue = module.get.onlyValidCharacters(value, '0123456789ABCDEFGHJKLMNPRSTUVWXYZ')
            ;
            
            // Limita a quantidade máxima de números possíveis
            if (maskedValue.length > 17) {
              maskedValue = maskedValue.substring(0, 17);
            }

            if (settings.validate) {
              if (maskedValue.length > 0) {
                if ((maskedValue.length == 17) &&
                    (module.is.valid.vin(maskedValue))) {
                  module.set.status.valid();
                } else {
                  module.set.status.invalid();
                }
              } else {
                module.set.status.empty();
              }
            }
            
            return maskedValue.toUpperCase();
          },
        },
        status: {
          empty: function() {
            module.verbose('Definindo estado inicial');

            clearTimeout(timer);
            
            if ($field.length) {
              $field
                .removeClass(className.valid)
                .removeClass(className.invalid)
              ;
            }
            if ($icon.length) {
              $icon
                .removeClass(className.validIcon)
                .removeClass(className.invalidIcon)
                .addClass(originalIconClass)
              ;
            }

            if (typeof settings.onEmptyValue === "function") {
              settings.onEmptyValue.call();
            }
          },
          invalid: function() {
            // Adia a informação de valor inválido, caso o valor mude
            // muito rapidamente
            var
              searchDelay = 800
            ;
            module.verbose('Atrasando informação de valor inválido', searchDelay);
            clearTimeout(timer);

            timer = setTimeout(function() {
              module.verbose('Definindo valor como inválido');

              if ($field.length) {
                $field
                  .removeClass(className.valid)
                  .addClass(className.invalid)
                ;
              }
              if ($icon.length) {
                $icon
                  .removeClass(originalIconClass)
                  .removeClass(className.validIcon)
                  .addClass(className.invalidIcon)
                ;
              }

              if (typeof settings.onInvalidValue === "function") {
                settings.onInvalidValue.call();
              }
            }, searchDelay);
          },
          valid: function() {
            module.verbose('Definindo valor como válido');

            clearTimeout(timer);

            if ($field.length) {
              $field
                .removeClass(className.invalid)
                .addClass(className.valid)
              ;
            }
            if ($icon.length) {
              $icon
                .removeClass(originalIconClass)
                .removeClass(className.invalidIcon)
                .addClass(className.validIcon)
              ;
            }
            
            if (typeof settings.onValidValue === "function") {
              settings.onValidValue.call(this, module.get.value());
            }
          }
        },
        value: function(value) {
          module.debug('Definindo valor modificado', value);
          $module
            .val(value)
          ;
          // TODO: entender o mecanismo por tráz de value
          //$module
          //  .attr('value', value)
          //;
          module.debug('Valor modificado é ', $module.val());
        }
      },
      remove: {
        validate: function() {
          // Remove os atributos de validação
          if ($field) {
            if ($field.length) {
              $field
                .removeClass(className.valid)
                .removeClass(className.invalid)
              ;
            }
          }
          if ($icon) {
            if ($icon.length) {
              $icon
                .removeClass(className.validIcon)
                .removeClass(className.invalidIcon)
                .addClass(originalIconClass)
              ;
            }
          }
        }
      }
    };

    this.destroy = function() {
      module.debug('Destruindo componente');
      module.destroy();
    };
    
    // Inicializa nosso plugin
    module.initialize();
  }
  
  // Criamos um wrapper de plug-in leve em torno do construtor "Plugin",
  // prevenindo contra instanciações múltiplas.
  $.fn.mask = function ( options ) {
    this.each(function() {
      if ( !$.data( this, "plugin_" + pluginName ) ) {
        /* Usamos "$.data" para salvar cada instância do plugin no caso
         * do usuário quer modificá-lo. Usando "$.data" desta maneira,
         * garantimos que os dados sejam removidos quando o(s) elemento(s)
         * DOM for removido através de métodos jQuery, assim como quando
         * o usuário deixar a página. É uma maneira inteligente de evitar
         * vazamentos de memória.
         */
        $.data( this, moduleNamespace, new MaskedInput( this, options ) );
      }
    });
    
    /* "return this;" retorna o objeto jQuery original. Isso permite que
     * métodos adicionais do jQuery sejam encadeados
     */
    return this;
  };
  
  /* Anexamos as opções do plugin padrão diretamente ao objeto do plugin.
   * Isto permite que os usuários substituam as opções de plug-in padrão
   * globalmente, em vez de passando a(s) mesma(s) opção(ões) toda vez
   * que o plugin é inicializado.
   * 
   * Por exemplo, o usuário pode definir o valor "property" uma vez para
   * todas instâncias do plugin com
   * "$.fn.pluginName.defaults.property = 'myValue';". Então, toda vez
   * que o plugin for inicializado, "property" será definido como
   * "myValue".
   */
  $.fn.mask.defaults = {
    debug: false,
    verbose: false,

    // O tipo de mascaramento
    type: 'number',

    // Se deve verificar o valor da entrada
    validate: false,

    // O tamanho máximo
    maxlength: null,

    // A flag que indica se devemos eliminar espaços desnecessários
    trim: false,

    // A flag que indica se devemos permitir valores negativos em
    // valores numéricos
    allowNegativeValues: false,

    // A quantidade de casas decimais (válido para valores monetários e
    // em porcentagens)
    decimalsPlaces: 2,

    /* Funções de Callbacks */
    onEmptyValue: function() { },
    onValidValue: function() { },
    onInvalidValue: function() { },
  };
})( jQuery, window, document );
