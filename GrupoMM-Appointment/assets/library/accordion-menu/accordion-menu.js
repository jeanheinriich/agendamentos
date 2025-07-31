/* ---------------------------------------------------------------------
 * accordion-menu.js
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
 * Um componente de menu tipo sanfona.
 * 
 * --------------------------------------------------------------------
 * Controle de Modificações
 * 
 * 2019-12-03 - Emerson Cavalcanti
 *   - Versão inicial
 * ---------------------------------------------------------------------
 */

;(function ( $, window, document, undefined ) {
  // O nome do plugin. Esta variável é usada no construtor do plugin,
  // assim como o wrapper do plugin para construir a chave para o método
  // "$.data"
  var
    pluginName = "accordionMenu"
  ;

  // O construtor do nosso plugin, responsável por instânciar o componente
  // no nó(s) DOM a partir do qual o plugin foi chamado. Por exemplo,
  // "$('.ui.vertical.accordion.menu').accordionmenu();" cria uma nova
  // instância do menu sanfona para todos os elementos que possuam classe
  // "ui vertical accordion menu".
  function AccordionMenu ( element, options ) {
    // Fornece acesso local ao(s) nó(s) DOM a partir do qual o plugin
    // foi chamado, e também acesso local ao nome do plugin e as nossas
    // opções padrão
    this.element = element;
    this._name = pluginName;
    this._defaults = $.fn.accordionmenu.defaults;
    
    // Faz o merge das configurações passadas com as nossas configurações
    // padrão
    this.options = $.extend( {}, this._defaults, options );
    
    // Inicializa nosso plugin
    this.init();
  }

  // Evita conflitos de calendar.prototype
  $.extend(AccordionMenu.prototype, {
    // O inicializador do nosso plugin
    init: function () {
      this.buildCache();
      this.buildComponent();
      this.bindEvents();
    },
    
    // Remove completamente a instância do plugin
    destroy: function() {
      this
        .unbindEvents()
      ;
      this
        .$element
        .removeData()
      ;
    },
    
    // Armazenar em cache nós DOM para melhoria de desempenho
    buildCache: function () {
      this.$element = $(this.element);
    },
    
    // Faz a criação do nosso componente
    buildComponent: function () {
      var
        plugin = this
      ;
      
      // Utiliza um template SemanticUI para criar um calendário
      var container = plugin.$element;
      
      // Esconde todos os níveis
      container
        .find("ul > li.item > ul")
        .hide();

      if (plugin.options.openActive) {
        if ( container.has('li.item.active') ) {
          // Abre o menu até o primeiro item ativo
          container
            .find('li.item.active')
            .eq(0)
            .each(function(index) {
              $(this)
                .parentsUntil($("ul.level-1" ))
                .addClass("open")
                .show();
            })
          ;
        }
      }
    },

    // Vincula os eventos que acionam métodos
    bindEvents: function() {
      var plugin = this;
      
      // Vinculamos os eventos aos seus respectivos manipuladores que
      // acionam outras funções. Todos os eventos são namespaced, ou
      // seja: ".on('click'+'.'+this._name', function() {});". Isso nos
      // permite desvincular eventos específicos do plug-in usando o
      // método unbindEvents abaixo.
      
      // Evento das teclas de seta

      // Recupera o(s) nó(s) DOM que fazem parte de nosso plugin
      var $parent   = this.$element;
      var $menuItem = $parent.find( "ul > li.item > div" );

      // Evento de 'click'
      $menuItem.on('click'+'.'+plugin._name, function(event) {
        if ($(this).parent().has("ul")) {
          event.preventDefault();
        }

        // Recupera o item do menu que estamos lidando
        var $item = $(this).parent();

        if ($item.hasClass("open")) {
          // Fecha este item no menu e todos os que estejam em níveis
          // inferiores
          $item.closest('ul').find('li.item.open').each(function() {
            var $element = $(this);
            $element.removeClass("open");
            $element.children('ul').slideUp();
          });
        } else {
          // Fecha itens no menu deste nível e todos os que estejam em
          // níveis inferiores a partir dele
          $item.closest('ul').find('li.item.open').each(function() {
            var $element = $(this);

            $element
              .removeClass("open")
            ;
            $element
              .children('ul')
              .slideUp()
            ;
          });

          // Abre este item no menu
          $item
            .addClass("open")
          ;
          $item
            .children('ul')
            .slideDown()
          ;
        }
      });
    },
    
    // Desvincula eventos que acionam métodos
    unbindEvents: function() {
      // Desvincula todos os eventos no namespace do nosso plugin que
      // estão anexados para "this.$element".
      this.$element.off('.'+this._name);
    }
  });
  
  // Criamos um wrapper de plug-in leve em torno do construtor "Plugin",
  // prevenindo contra instanciações múltiplas.
  $.fn.accordionmenu = function ( options ) {
    this.each(function() {
      if ( !$.data( this, "plugin_" + pluginName ) ) {
        /* Usamos "$.data" para salvar cada instância do plugin no caso
         * do usuário quer modificá-lo. Usando "$.data" desta maneira,
         * garantimos que os dados sejam removidos quando o(s) elemento(s)
         * DOM for removido através de métodos jQuery, assim como quando
         * o usuário deixar a página. É uma maneira inteligente de evitar
         * vazamentos de memória.
         */
        $.data( this, "plugin_" + pluginName, new AccordionMenu( this, options ) );
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
  $.fn.accordionmenu.defaults = {
    openActive: true
  };
})( jQuery, window, document );
