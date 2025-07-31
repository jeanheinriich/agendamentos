/* ---------------------------------------------------------------------
 * spinner.input.js
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
 * A classe que fornece uma maneira simples de incrementar ou decrementar
 * a entrada de um valor num campo.
 * 
 * --------------------------------------------------------------------
 * Controle de Modificações
 * 
 * 2018-11-15 - Emerson Cavalcanti
 *   - Versão inicial
 * 2019-07-15 - Emerson Cavalcanti
 *   - Modificado para permitir a correta visualização, eliminando a
 * renderização errônea da borda.
 *
 * 2020-02-01 - Emerson Cavalcanti
 *   - Adaptação para sintaxe semântica
 * ---------------------------------------------------------------------
 */

;(function ( $, window, document, undefined ) {
  'use strict';
  
  var
    // O nome do plugin. Esta variável é usada no construtor do plugin,
    // assim como o wrapper do plugin para construir a chave para o
    // método "$.data".
    pluginName = 'SpinnerInput',
    
    // O namespace para eventos e o módulo
    eventNamespace   = '.' + pluginName,
    moduleNamespace  = "plugin_" + pluginName,

    // Os nomes das classes
    className      = {
      disabled     : 'disabled',
    },

    // Os seletores
    selector       = {
      increase     : '.up.button',
      decrease     : '.down.button'
    }
  ;

  // O construtor do plugin cria uma nova instância do plugin para o
  // nó(s) DOM a partir do qual o plugin foi chamado.
  function SpinnerInput ( element, options ) {
    var
      settings  = ( $.isPlainObject(options) )
        ? $.extend(true, {}, $.fn.spinnerinput.defaults, options)
        : $.extend({}, $.fn.spinnerinput.defaults),

      $module,

      // O input de entrada
      $input            = $(element),

      // Os botões de incremento e decremento
      $increase,
      $decrease,

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
        $module.removeData();
      },

      // As funções de construção
      create: {
        // Faz a criação do nosso componente
        component: function() {
          var
            $wrap          = module.create.element.div("ui icon spinner input"),
            $buttons       = module.create.element.div("ui spinner vertical buttons"),
            $buttonUp      = module.create.element.button("ui up", "up chevron"),
            $buttonDown    = module.create.element.button("ui down", "down chevron")
          ;

          // Lidamos com campos somente-leitura
          if ($input.is('[readonly]')) {
            module.verbose('Definindo componente como somente leitura');
            $wrap
              .addClass('readonly')
            ;
          }

          // Primeiramente, criamos um wrap ao redor do input para
          // conter todo nosso componente
          $input
            .wrap($wrap)
          ;

          // Definimos os comandos de cada botão
          $buttonUp
            .prop("command", "Up")
          ;
          $buttonDown
            .prop("command", "Down")
          ;

          // Acrescentamos os demais itens do nosso componente
          $module = $input.closest('div.icon.input');
          $buttons
            .append($buttonUp)
            .append($buttonDown)
          ;
          $module
            .append($buttons)
          ;

          // Localizamos as partes de nosso componente para acelerar
          $increase  = $module.find(selector.increase);
          $decrease  = $module.find(selector.decrease);

          if (module.is.not.empty()) {
            // Inicializa o valor corretamente, descartando valores
            // inválidos
            var
              value = module.get.value()
            ;

            module.set.value(value);
          }
        },
        element: {
          button: function(classes, icon) {
            var
              $newButton = $("<button>")
                .addClass(classes)
                .addClass("icon")
                .addClass("button")
              ;

            $newButton
              .html('<i class="' + icon + ' icon"></i>')
            ;

            return $newButton;
          },
          div: function(classes) {
            return $("<div>")
                     .addClass(classes);
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
          module.bind.keyboardEvents();
          module.bind.inputEvents();
          module.bind.mouseEvents();
        },

        // Lida com os eventos de teclado
        keyboardEvents: function() {
          module.verbose('Vinculando eventos de teclado');

          $input
            .on('keydown'   + eventNamespace, module.event.keydown)
          ;
        },

        // Lida com os eventos de entrada
        inputEvents: function() {
          module.verbose('Vinculando eventos de entrada');

          $input
            .on('change'    + eventNamespace, module.event.change)
          ;
        },

        // Lida com os eventos de mouse
        mouseEvents: function() {
          module.verbose('Vinculando eventos do mouse');

          $input
            .on('blur'      + eventNamespace, module.event.blur)
          ;
          $increase
            .on('click'     + eventNamespace, module.event.increase)
          ;
          $decrease
            .on('click'     + eventNamespace, module.event.decrease)
          ;
        }
      },

      // As funções de desvinculação dos eventos
      unbind: {
        events: function() {
          // Desvincula todos os eventos no namespace do nosso plugin que
          // estão anexados
          $module
            .off(eventNamespace)
          ;
        }
      },

      // ==================================================[ Eventos ]==

      event: {
        blur: function(event) {
          module.debug('Componente perdeu foco');
          module.handleValue();
        },
        change: function(event) {
          module.debug('Valor modificado');

          if (typeof settings.onChange === "function") {
            settings.onChange.call($input.get(0));
          }
        },
        decrease: function(event) {
          module.debug('Pressionado botão de decremento');
          event
            .preventDefault()
          ;
          module.decrease();
        },
        increase: function(event) {
          module.debug('Pressionado botão de incremento');
          event
            .preventDefault()
          ;
          module.increase();
        },
        keydown: function(event) {
          var
            keyCode = event.which,
            keys    = {
              upArrow: 38,
              downArrow: 40
            }
          ;

          if (module.is.disabled()) {
            module.verbose('Desabilitado, pressionamento de tecla ignorado');

            return;
          } else {
            switch (keyCode) {
              case keys.upArrow:
                module.verbose('Pressionada tecla Seta Acima');

                setTimeout(function () {
                  module.increase();
                }, 1);
                
                // Cancela o evento se a função ainda não tiver retornado
                event.stopImmediatePropagation();
                event.preventDefault();

                break;
              case keys.downArrow:
                module.verbose('Pressionada tecla Seta Abaixo');

                setTimeout(function () {
                  module.decrease();
                }, 1);
                
                // Cancela o evento se a função ainda não tiver retornado
                event.stopImmediatePropagation();
                event.preventDefault();

                break;
              default:
                module.verbose('Evento de tecla pressionada');
                setTimeout(function () {
                  module.handleValue();
                }, 1);
            }
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

      increase: function() {
        var
          value = module.get.value()
        ;

        if (value === settings.max) {
          // Ignora se o valor máximo for atingido
          module.verbose('Atingiu valor máximo permitido', settings.max);

          return;
        }

        // Incrementa o valor
        module.verbose('Adicionando incremento', settings.step, 'ao valor');
        value += settings.step;

        // Limita o valor dentro dos limites
        if (value > settings.max) {
          value = settings.max;
        }

        if (typeof settings.onLimite === "function") {
          value = settings.onLimite.call($input.get(0), value);
        }

        // Atribui o novo valor
        module.set.value(value);
      },
      decrease: function() {
        var
          value = module.get.value()
        ;

        if (value === settings.min) {
          // Ignora se o valor mínimo for atingido
          module.verbose('Atingiu valor mínimo permitido', settings.min);

          return;
        }

        // Incrementa o valor
        module.verbose('Diminuindo incremento', settings.step, 'do valor');
        value -= settings.step;

        // Limita o valor dentro dos limites
        if (value < settings.min) {
          value = settings.min;
        }

        if (typeof settings.onLimite === "function") {
          value = settings.onLimite.call($input.get(0), value);
        }

        // Atribui o novo valor
        module.set.value(value);
      },
      // Lida com o valor entrado, descartando valores e caracteres
      // inválidos
      handleValue: function() {
        var
          value    = module.get.value(),
          strValue = value.toString(),
          strMax   = settings.max.toString()
        ;

        // Limita a quantidade de caracteres dentro do tamanho
        // do valor máximo permitido
        if (strValue.length > strMax.length) {
          value = parseInt(strValue.substring(0, strMax.length));
        }

        // Limita o valor dentro dos limites configurados
        if (value > settings.max) {
          value = settings.max;
        }
        if (value < settings.min) {
          value = settings.min;
        }
        if (typeof settings.onLimite === "function") {
          value = settings.onLimite.call($input.get(0), value);
        }

        module.set.value(value);
      },

      is: {
        empty: function() {
          var
            value = $input.val()
          ;

          return (value.length === 0);
        },
        disabled: function() {
          return $module.hasClass(className.disabled);
        },
        not: {
          empty: function() {
            return !module.is.empty();
          },
        }
      },

      get: {
        value: function() {
          var
            value = $.trim($input.val());
          ;

          // Se o valor for nulo, força o valor como sendo "0"
          if ((value === undefined) ||
              (value === null) ||
              (value === '')) {
            value = '0';
          }

          // Remove qualquer caractere, deixando apenas os dígitos
          value = value.replace(/\D/g,"");

          return (value)?parseInt(value):0;
        },
      },

      set: {
        value: function(value) {
          // Apenas seta o valor se modificado
          if (value !== $input.val()) {
            $input
              .val(value)
              .trigger('change')
            ;
          }
        }
      }
    };

    // Inicializa nosso plugin
    module.initialize();
  };
  
  // Criamos um wrapper de plug-in leve em torno do construtor "Plugin",
  // prevenindo contra instanciações múltiplas.
  $.fn.spinnerinput = function ( options ) {
    this.each(function() {
      if ( !$.data( this, "plugin_" + pluginName ) ) {
        /* Usamos "$.data" para salvar cada instância do plugin no caso
         * do usuário quer modificá-lo. Usando "$.data" desta maneira,
         * garantimos que os dados sejam removidos quando o(s) elemento(s)
         * DOM for removido através de métodos jQuery, assim como quando
         * o usuário deixar a página. É uma maneira inteligente de evitar
         * vazamentos de memória.
         */
        $.data( this, moduleNamespace, new SpinnerInput( this, options ) );
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
  $.fn.spinnerinput.defaults = {
    // O valor mínimo permitido
    min: 0,

    // O valor máximo permitido
    max: 99,

    // O valor de incremento/decremento
    step: 1,

    /* Funções de Callbacks */
    onChange              : function() { },
    onLimite              : function(value) { return value; },
  };
})( jQuery, window, document );
