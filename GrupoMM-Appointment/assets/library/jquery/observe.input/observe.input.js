/* ---------------------------------------------------------------------
 * observe.input.js
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
 * A classe que fornece uma maneira simples de alertar o usuário durante
 * a entrada de um valor num campo. Exibe um alerta caso o CapsLock
 * esteja ativo no momento da digitação, prevenindo erros. Também permite
 * adicionar um evento de verificação durante a digitação que compara o
 * texto digitado com outro campo, permitindo a análise de conferência
 * da senha. Por último, permite adicionar um evento ao sair do campo
 * (blur) que indica se o valor digitado é válido ou não.
 * 
 * --------------------------------------------------------------------
 * Controle de Modificações
 * 
 * 2018-09-27 - Emerson Cavalcanti
 *   - Versão inicial
 * 2020-02-02 - Emerson Cavalcanti
 *   - Adaptação à versão semântica.
 * 2021-04-06 - Emerson Cavalcanti
 *   - Correção de vazamento de memória.
 *   - Correção de erro em campos somente leitura.
 * ---------------------------------------------------------------------
 */

;(function ( $, window, document, undefined ) {
  'use strict';

  var
    // O nome do plugin. Esta variável é usada no construtor do plugin,
    // assim como o wrapper do plugin para construir a chave para o
    // método "$.data".
    pluginName = 'ObserveInput',
    
    // O namespace para eventos e o módulo
    eventNamespace   = '.' + pluginName,
    moduleNamespace  = "plugin_" + pluginName,

    // Os nomes das classes
    className      = {
      disabled     : 'disabled',
      wrap         : 'ui leftandright icon input'
    },

    // Os metadados
    metadata       = {
    },

    // Os seletores
    selector       = {
      input        : 'input.observed'
    }
  ;

  // O construtor do plugin constrói uma nova instância do plugin para o
  // nó(s) DOM a partir do qual o plugin foi chamado.
  var ObserveInput = function ( element, options ) {
    var
      settings          = ( $.isPlainObject(options) )
        ? $.extend(true, {}, $.fn.observeinput.defaults, options)
        : $.extend({}, $.fn.observeinput.defaults),

      $module,

      // O input à ser observado
      $input           = $(element),

      // Os ícones de sinalização
      $leftIcon,
      $rightIcon,
      $toggleIcon,

      // As flags indicativas do que temos de verificar
      verifyOnTyping   = (typeof settings.onTyping === "function")
        ? true
        : false,
      verifyOnComplete = (typeof settings.onComplete === "function")
        ? true
        : false,
      
      module
    ;

    module = {
      // =======================================[ Criação componente ]==
      // O inicializador do nosso plugin
      initialize: function() {
        module.verbose('Inicializando componente');
        module.create.component();
        module.bind.events();
      },
      
      // Remove completamente a instância do plugin
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
            $wrap         = module.create.element.div("ui leftandright icon input"),
            $leftSymbol   = (typeof $input.data('symbol') !== 'undefined')
              ? module.create.element.icon($input.data('symbol'))
              : module.create.element.icon(settings.icon.left),
            $rightSymbol  = module.create.element.icon(settings.icon.right),
            $toggleSymbol = module.create.element.icon(settings.icon.toggle)
          ;

          // Lidamos com campos somente-leitura
          if ($input.is('[readonly]')) {
            module.verbose('Definindo componente como somente leitura');
            $wrap
              .addClass('readonly')
            ;
            $leftSymbol
              .addClass('hidden')
            ;
            $rightSymbol
              .addClass('hidden')
            ;
            $toggleSymbol
              .addClass('hidden')
            ;
          }

          // Primeiramente, criamos um wrap ao redor do input para
          // conter todo nosso componente
          $input
            .wrap($wrap)
          ;

          // Modificamos os atributos de nosso input e ícones
          $input
            .addClass('observed')
            .attr('autocomplete', 'nope')
            .attr('tabindex', 0)
          ;
          $leftSymbol
            .css('color', '#888888')
            .css('opacity', '1')
          ;
          $rightSymbol
            .css('color', '#888888')
            .css('opacity', '1')
          ;
          if (settings.enableToggleView) {
            $rightSymbol
              .css('right', '1.6em')
            ;

            $toggleSymbol
              .css('color', '#888888')
              .css('opacity', '1')
            ;

            $input
              .addClass('with toggle')
            ;
          }

          // Acrescentamos os demais itens do nosso componente
          $module = $input.closest("div.leftandright.icon.input");
          $module
            .prepend($leftSymbol)
          ;
          $leftIcon = $module.find('i').first();
          if (verifyOnComplete || verifyOnTyping) {
            // Nosso componente terá ícones também à direita
            $module
              .append($rightSymbol)
            ;
            $rightIcon = $module.find('i').last();
          }

          if (settings.enableToggleView) {
            $module
              .append($toggleSymbol)
            ;
            $toggleIcon = $module.find('i').last();
          }
        },
        element: {
          icon: function(classes) {
            return $("<i>")
              .addClass(classes)
              .addClass("icon")
            ;
          },
          div: function(classes) {
            return $("<div>")
              .addClass(classes)
            ;
          }
        },
      },

      // ====================================[ Vinculação de eventos ]==

      // As funções de vinculação dos eventos
      bind: {
        // Vincula os eventos que acionam métodos
        events: function() {
          // Vinculamos os eventos aos seus respectivos manipuladores que
          // acionam outras funções. Todos os eventos são namespaced.
          module.bind.keyboardEvents();
          module.bind.inputEvents();
          module.bind.mouseEvents();
        },

        // Lida com os eventos de teclado
        keyboardEvents: function() {
          module.verbose('Vinculando eventos de teclado');

          $input
            .on('keyup'     + eventNamespace, module.event.keyup)
          ;
        },

        // Lida com os eventos de entrada
        inputEvents: function() {
          module.verbose('Vinculando eventos de entrada');

          $input
            .on('input'     + eventNamespace,
              module.event.input)
            .on('change'    + eventNamespace,
              module.event.change)
          ;
        },

        // Lida com os eventos de mouse
        mouseEvents: function() {
          module.verbose('Vinculando eventos do mouse');

          $input
            .on('blur'      + eventNamespace,
              module.event.blur)
          ;

          if (settings.enableToggleView) {
            $toggleIcon
              .on('click'   + eventNamespace,
                module.event.toggle)
            ;
          }
        }
      },

      is: {
        disabled: function() {
          return $module.hasClass(className.disabled);
        },
        focused: function() {
          return ($input.filter(':focus').length > 0);
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
        // Lida com os eventos de tecla liberada
        keyup: function(event) {
          var
            leftSymbol     = (typeof $input.data('symbol') !== 'undefined')
              ? $input.data('symbol')
              : settings.icon.left,
            rightSymbol    = settings.icon.right,
            capslockSymbol = settings.icon.capslock,
            successSymbol  = settings.icon.success,
            failSymbol     = settings.icon.fail
          ;

          // Verifica se o campo de entrada está em modo somente
          // leitura
          if ($input.is('[readonly]')) {
            module.verbose('Ignorando pressionamento de teclas em '
              + 'campo de entrada em modo somente leitura')
            ;

            return;
          }

          // Verifica se o campo de entrada está desabilitado
          if (module.is.disabled()) {
            module.verbose('Desabilitado, liberação de tecla ignorado');

            return;
          }

          // Determina se o CapsLock está ativo
          if (typeof event.originalEvent.getModifierState === "function") {
            if (event.originalEvent.getModifierState("CapsLock")) {
              // CapsLock está ligado
              $leftIcon
                .removeClass(leftSymbol)
                .addClass(capslockSymbol)
                .css('color', '#cc0000');
            } else {
              // CapsLock está desligado
              $leftIcon
                .removeClass(capslockSymbol)
                .addClass(leftSymbol)
                .css('color', '#888888');
            }
          }
          
          if (verifyOnTyping) {
            // Executa a verificação ao digitar
            if (settings.onTyping.call($input)) {
              // Sinaliza que o valor digitado está correto
              $rightIcon
                .removeClass(rightSymbol)
                .removeClass(failSymbol)
                .addClass(successSymbol)
                .css('color', '#006400')
              ;
            } else {
              // Sinaliza que o valor digitado está incorreto
              $rightIcon
                .removeClass(rightSymbol)
                .removeClass(successSymbol)
                .addClass(failSymbol)
                .css('color', '#cc0000')
              ;
            }
          }
        },
        // Lida com os eventos de perda de foco
        blur: function(event) {
          var
            leftSymbol     = (typeof $input.data('symbol') !== 'undefined')
              ? $input.data('symbol')
              : settings.icon.left,
            rightSymbol    = settings.icon.right,
            capslockSymbol = settings.icon.capslock,
            successSymbol  = settings.icon.success,
            failSymbol     = settings.icon.fail
          ;

          // Sempre limpa o estado do CapsLock
          $leftIcon
            .removeClass(capslockSymbol)
            .addClass(leftSymbol)
            .css('color', '#888888')
          ;

          // Verifica se o campo de entrada está em modo somente
          // leitura
          if ($input.is('[readonly]')) {
            module.verbose('Ignorando perda de foco em campo de '
              + 'entrada em modo somente leitura')
            ;

            return;
          }

          // Verifica se o campo de entrada está desabilitado
          if (module.is.disabled()) {
            module.verbose('Desabilitado, perda de foco ignorada');

            return;
          }

          if (verifyOnComplete) {
            if (settings.onComplete.call($input)) {
              // Coloca como sucesso
              $rightIcon
                .removeClass(rightSymbol)
                .removeClass(failSymbol)
                .addClass(successSymbol)
                .css('color', '#006400');
            } else {
              // Coloca como falha
              $rightIcon
                .removeClass(rightSymbol)
                .removeClass(successSymbol)
                .addClass(failSymbol)
                .css('color', '#cc0000');
            }
          }
        },
        toggle: function(event) {
          // Lida com o evento de alternância da visibilidade da senha
          module.debug('Alternando visibilidade da senha');
          $toggleIcon
            .toggleClass("slash")
          ;
          if ( $input.is("[type='password']") ) {
            module.verbose('Exibindo senha');
            $input
              .attr("type", "text")
            ;
          } else {
            module.verbose('Ocultando senha');
            $input
              .attr("type", "password")
            ;
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
      }
    };

    // Inicializa nosso plugin
    module.initialize();
  };
  
  // Criamos um wrapper de plug-in leve em torno do construtor "Plugin",
  // prevenindo contra instanciações múltiplas.
  $.fn.observeinput = function ( options ) {
    var
      instance
    ;

    this.each(function() {
      if ( !$.data( this, moduleNamespace ) ) {
        /* Usamos "$.data" para salvar cada instância do plugin no caso
         * do usuário quer modificá-lo. Usando "$.data" desta maneira,
         * garantimos que os dados sejam removidos quando o(s)
         * elemento(s) DOM for removido através de métodos jQuery, assim
         * como quando o usuário deixar a página. É uma maneira
         * inteligente de evitar vazamentos de memória.
         */
        $.data( this, moduleNamespace, new ObserveInput( this, options ) );
      }
    });
    
    /* "return this;" retorna o objeto jQuery original. Isso permite que
     * métodos adicionais do jQuery sejam encadeados
     */
    return instance !== undefined ? instance:this;
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
  $.fn.observeinput.defaults = {
    debug: false,
    verbose: false,

    enableToggleView: false,

    icon: {
      left: 'keyboard outline',
      right: 'question',
      toggle: 'eye slash link',
      capslock: 'text height',
      success: 'check',
      fail: 'close'
    },
    onTyping: null,
    onComplete: null
  };
})( jQuery, window, document );
