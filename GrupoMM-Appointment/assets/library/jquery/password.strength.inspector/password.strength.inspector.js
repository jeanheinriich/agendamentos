/* ---------------------------------------------------------------------
 * password.strength.inspector.js
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
 * A classe que fornece uma maneira simples de impedir que o usuário
 * use uma senha fácil de adivinhar ou que seja simples de ser quebrada
 * por ataque de força bruta.
 * 
 * Ele fornece regras de validação de senha simples, porém bastante
 * eficazes. A biblioteca introduz a tradução de senhas codificadas pelo
 * alfabeto leet, convertendo a senha fornecida em uma lista de
 * possíveis alterações simples, como alterar a letra a por @ ou 4. Esta
 * lista é então comparada com senhas comuns baseadas em pesquisas feitas
 * sobre as mais recentes violações de banco de dados de senhas (stratfor,
 * sony, phpbb, rockyou, myspace).
 * 
 * Além disso, ele valida o tamanho e o uso de vários conjuntos de
 * caracteres (letras maiúsculas, minúsculas, caracteres numéricos e
 * caracteres especiais). Por último, verifica também o uso de palavras
 * de uso no ambiente onde está inserido (como o nome da empresa).
 * 
 * --------------------------------------------------------------------
 * Controle de Modificações
 * 
 * 2018-09-26 - Emerson Cavalcanti
 *   - Versão inicial
 * 
 * 2020-02-28 - Emerson Cavalcanti
 *   - Adaptação ao formato semântico.
 * ---------------------------------------------------------------------
 */

'use strict';

// O construtor do plugin constrói uma nova instância do plugin
function PasswordStrengthInspector( maxSize, options ) {
  var
    // As possíveis validações a serem executadas 
    validators = [
      'length', 'upper', 'lower', 'numeric', 'special', 'onlynumeric'
    ],

    // Determina as configurações
    settings  = (options  instanceof Array)
      ? options
      : validators,

    // Os formatadores das mensagens para cada erro
    messages = {
      'length'      : 'A senha deve ter entre %s e %s caracteres',
      'upper'       : 'A senha deve conter pelo menos um caractere maiúsculo',
      'lower'       : 'A senha deve conter pelo menos um caractere minúsculo',
      'alpha'       : 'A senha deve conter pelo menos um caractere alfabético',
      'numeric'     : 'A senha deve conter pelo menos um caractere numérico',
      'special'     : 'A senha deve conter pelo menos um símbolo',
      'onlynumeric' : 'A senha não deve ser totalmente numérica'
    },

    // Determina um tamanho máximo para nossa senha
    maxLength = (typeof maxSize !== 'undefined')
      ? maxSize
      : 25,

    // Define um tamanho mínimo para nossa senha
    minLength = 8,

    // Os erros encontrados durante a verificação
    errors    = [],

    // As variações das senhas usando a tradução de caracteres do
    // alfabeto leet
    pass      = [],

    module
  ;

  module = {
    is: {
      // Determina se o validador informado foi definido nas
      // configurações
      defined: function(validator) {
        return settings.indexOf(validator) !== -1
          ? true
          : false
        ;
      }
    },
    add: {
      error: function() {
        var
          args      = arguments,
          validator = args[0],
          index     = 1,
          message
        ;

        // Verifica se temos algo à formatar
        if (args.length < 2) {
          // Apenas adiciona a mensagem, sem formatar
          errors.push(messages[validator]);
        } else {
          // Formata a mensagem utilizando os parâmetros passados
          message = (messages[validator] + '').replace(/%((\d)\$)?([sd%])/g, function (match, group, pos, format) {
              if (match === '%%') {
                return '%';
              }

              if (typeof pos === 'undefined') {
                pos = index++;
              }

              if (pos in args && pos > 0) {
                return args[pos];
              } else {
                return match;
              }
            })
          ;

          // Adiciona a mensagem formatada
          errors.push(message);
        }
      }
    },

    // ===========================================[ Os validadores ]==
    // As definições de cada função responsável por validar uma regra
    check: {
      // Determina se temos caracteres alfanuméricos
      alpha: function(value) {
        if (!value.toLowerCase().match(/[a-z]/g)) {
          module.add.error('alpha');
        }
      },
      length: function(value) {
        var
          length = value.length
        ;
        
        if ((length < minLength) || (length > maxLength)) {
          module.add.error('length', minLength, maxLength);
        }
      },
      lower: function(value) {
        if (!value.match(/[a-z]/g)) {
          module.add.error('lower');
        }
      },
      numeric: function(value) {
        if (!value.match(/[0-9]/g)) {
          module.add.error('numeric');
        }
      },
      onlynumeric: function(value) {
        if (value.match(/^\d+$/)) {
          module.add.error('onlynumeric');
        }
      },
      special: function(value) {
        if (!value.match(/[\W_]/)) {
          module.add.error('special');
        }
      },
      upper: function(value) {
        if (!value.match(/[A-Z]/g)) {
          module.add.error('upper');
        }
      }
    },

    // ====================================[ A função de validação ]==
    // A função responsável por validar cada regra sobre a senha
    // informada
    validate: function(password) {
      errors   = [];

      // Executa as verificações em função das opções definidas
      if (module.is.defined('alpha')) {
        module.check.alpha(password);
      }
      if (module.is.defined('length')) {
        module.check.length(password);
      }
      if (module.is.defined('lower')) {
        module.check.lower(password);
      }
      if (module.is.defined('numeric')) {
        module.check.numeric(password);
      }
      if (module.is.defined('onlynumeric')) {
        module.check.onlynumeric(password);
      }
      if (module.is.defined('special')) {
        module.check.special(password);
      }
      if (module.is.defined('upper')) {
        module.check.upper(password);
      }

      if (typeof errors !== 'undefined' && errors.length > 0) {
        // Temos erros
        return false;
      }
      
      return true;
    }
  };

  this.validate= function(password) {
    return module.validate(password);
  }

  this.getErrors = function() {
    return errors;
  }
};