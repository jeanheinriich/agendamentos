/* ---------------------------------------------------------------------
 * simple-calendar.js
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
 * Um componente de calendário.
 * 
 * --------------------------------------------------------------------
 * Controle de Modificações
 * 
 * 2019-12-02 - Emerson Cavalcanti
 *   - Versão inicial
 * ---------------------------------------------------------------------
 */

Number.prototype.pad = function(size) {
  var sign = Math.sign(this) === -1 ? '-' : '';
  return sign + new Array(size).concat([Math.abs(this)]).join('0').slice(-size);
}

;(function ( $, window, document, undefined ) {
  // O nome do plugin. Esta variável é usada no construtor do plugin,
  // assim como o wrapper do plugin para construir a chave para o método
  // "$.data"
  var pluginName = "simpleCalendar";

  // O construtor do nosso plugin, responsável por instânciar o componente
  // no nó(s) DOM a partir do qual o plugin foi chamado. Por exemplo,
  // "$('div.calendar').simplecalendar();" cria uma nova instância do
  // calendário para todos os divs que possuam classe "calendar".
  function SimpleCalendar ( element, options ) {
    // Fornece acesso local ao(s) nó(s) DOM a partir do qual o plugin foi
    // chamado, e também acesso local ao nome do plugin e as nossas
    // opções padrão
    this.element = element;
    this._name = pluginName;
    this._defaults = $.fn.simplecalendar.defaults;
    this.currentDate = new Date();
    this.currentDate.setHours(0,0,0,0);
    
    // Faz o merge das configurações passadas com as nossas configurações
    // padrão
    this.options = $.extend( {}, this._defaults, options );
    
    // Inicializa nosso plugin
    this.init();
  }

  // Evita conflitos de calendar.prototype
  $.extend(SimpleCalendar.prototype, {
    // O inicializador do nosso plugin
    init: function () {
      this.buildCache();
      this.buildComponent();
      this.updateHeader();
      this.updateBody();
      this.updateVerticalDate();
      this.bindEvents();
    },
    
    // Remove completamente a instância do plugin
    destroy: function() {
      this.unbindEvents();
      this.$element.removeData();
    },
    
    // Armazenar em cache nós DOM para melhoria de desempenho
    buildCache: function () {
      this.$element = $(this.element);
    },
    
    // Faz a criação do nosso componente
    buildComponent: function () {
      var plugin = this;
      
      // Utiliza um template SemanticUI para criar um calendário
      
      // Limpa o nosso container
      var container = plugin.$element;
      container.empty();

      var verticalDate = $('<div/>')
        .addClass("vertical")
        .addClass("date")
        .appendTo(container);
      var month = $('<div/>')
        .addClass("month")
        .appendTo(verticalDate);
      var dayofweek = $('<div/>')
        .addClass("dayofweek")
        .appendTo(verticalDate);
      var day = $('<div/>')
        .addClass("day")
        .appendTo(verticalDate);
      var year = $('<div/>')
        .addClass("year")
        .appendTo(verticalDate);

      // Acrescenta uma tabela
      var table = $('<table/>')
        .addClass('calendar')
        .appendTo(container);

      // Acrescenta o cabeçalho de nossa tabela
      var thead = $('<thead/>')
        .appendTo(table);

      var row =  $('<tr/>')
        .appendTo(thead);
      var cell = $('<th/>')
        .attr('colspan', '7')
        .appendTo(row);

      // O cabeçalho
      var header = $('<div/>')
        .addClass("header")
        .appendTo(cell);
      // Botão à esquerda
      // Botão de mês anterior
      var prev = $('<a/>')
        .addClass("btn")
        .addClass("prev")
        .prop("command", "Previous")
        .data('increment', -1)
        .appendTo(header);
      $('<i/>')
        .addClass("left")
        .addClass("chevron")
        .addClass("icon")
        .appendTo(prev);
      // Rótulos
      var label = $('<div/>')
        .addClass("title")
        .appendTo(header);
      // Mês corrente
      var headerMonthText = $('<span/>')
        .addClass('month')
        .appendTo(label);
      // Ano corrente
      var headerYearText = $('<span/>')
        .addClass('year')
        .appendTo(label);
      // Botões à direita
      // Botão para data corrente
      var today = $('<a/>')
        .addClass("btn")
        .addClass("today")
        .prop("command", "Today")
        .data('increment', 0)
        .appendTo(header);
      $('<i/>')
        .addClass("calendar")
        .addClass("check")
        .addClass("icon")
        .appendTo(today);
      // Botão de mês posterior
      var next = $('<a/>')
        .addClass("btn")
        .addClass("next")
        .prop("command", "Next")
        .data('increment', 1)
        .appendTo(header);
      $('<i/>')
        .addClass("right")
        .addClass("chevron")
        .addClass("icon")
        .appendTo(next);

      // Rótulos dos dias da semana
      row = $('<tr/>')
        .addClass("days")
        .appendTo(thead);
      for (var i = 0; i < 7; i++) {
        cell = $('<th/>')
          .addClass("day")
          .appendTo(row);
        cell.text(plugin.options.text.days[(i + plugin.options.firstDayOfWeek) % 7].substring(0,1));

        // Indica se for sábado
        if (((i + plugin.options.firstDayOfWeek) % 7) == 6) {
          cell.addClass("saturday");
        }

        // Indica se for domingo
        if (((i + plugin.options.firstDayOfWeek) % 7) == 0) {
          cell.addClass("sunday");
        }
      }

      // Conteúdo do calendário
      var tbody = $('<tbody/>')
        .appendTo(table);
    },

    // Atualiza o cabeçalho
    updateHeader: function () {
      var plugin = this;

      // Recupera o(s) nó(s) DOM que fazem parte de nosso plugin
      var $parent = this.$element;
      var $header = $parent.find("table.calendar thead");

      $header.find(".month").text(plugin.options.text.months[plugin.currentDate.getMonth()]);
      $header.find(".year").text(plugin.currentDate.getFullYear());
    },

    // Atualiza o calendário vertical
    updateVerticalDate: function () {
      var plugin = this;

      // Recupera o(s) nó(s) DOM que fazem parte de nosso plugin
      var $parent   = this.$element;
      var $vertical = $parent.find("div.vertical.date");
      var today = new Date();
      today.setHours(0,0,0,0);

      $vertical.find(".year").text(today.getFullYear());
      $vertical.find(".day").text(today.getDate().pad(2));
      $vertical.find(".dayofweek").text(plugin.options.text.days[today.getDay()].substring(0,3));
      $vertical.find(".month").text(plugin.options.text.months[today.getMonth()].substring(0,3));
    },

    // Atualiza o corpo do calendário
    updateBody: function () {
      var plugin = this;

      // Recupera o(s) nó(s) DOM que fazem parte de nosso plugin
      var $parent     = this.$element;

      var table = $parent.find('table');
      var tbody = table.children('tbody');
      tbody.empty();

      // Recupera as informações para montar o calendário
      var year  = plugin.currentDate.getFullYear();
      var month = plugin.currentDate.getMonth();
      
      // Determina o primeiro dia do calendário
      var firstDayOfCalendar = new Date(year, month, 1);
      var firstDayOfWeek = (0 + plugin.options.firstDayOfWeek) % 7;
      if (firstDayOfCalendar.getDay() == firstDayOfWeek) {
        // Voltamos uma semana
        firstDayOfCalendar.setDate(firstDayOfCalendar.getDate() - 7);
      } else {
        // Decrementa o calendário até que estejamos no primeiro dia deste
        // calendário (primeiro dia da semana que pode estar no mês anterior)
        while(firstDayOfCalendar.getDay() != firstDayOfWeek) {
          firstDayOfCalendar.setDate(firstDayOfCalendar.getDate() - 1);
        }
      }

      var rows = 6;
      var row;
      var cellDate = new Date();
      cellDate.setTime(firstDayOfCalendar.getTime());

      for (var r = 0; r < rows; r++) {
        row = $('<tr/>').appendTo(tbody);

        for (c = 0; c < 7; c++) {
          cell = $('<td/>')
            .addClass('day')
            .appendTo(row);
          cell.text(cellDate.getDate());
          cell.data('date', cellDate);

          // Se for o dia de hoje
          if (cellDate.toDateString() === (new Date).toDateString()) {
            cell.addClass("today");
          }

          // Se o dia for um sábado
          if (cellDate.getDay() === 6) {
            cell.addClass("saturday");
          }

          // Se o dia for um domingo
          if (cellDate.getDay() === 0) {
            cell.addClass("sunday");
          }

          // Se o dia estiver em outro mês
          if (cellDate.getMonth() != month) {
             cell.addClass("another-month"); 
          }

          // Incrementa a data
          cellDate.setDate(cellDate.getDate() + 1);
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
      var $parent       = this.$element;
      var $actionButton = $parent.find( ".btn" );

      // Evento de 'click'
      $actionButton.on('click'+'.'+plugin._name, function(event) {

        event.preventDefault();

        // Recupera o comando que estamos lidando
        var command   = $(this).prop('command');
        var increment = $(this).data('increment');

        // Usamos o método 'call' para que dentro do método possamos
        // acessar nosso componente. Neste caso, a palavra-chave "this"
        // se refere à instância do plug-in, e não ao manipulador do
        // evento.
        plugin.handleAction.call(plugin, command, increment);
      });
    },
    
    // Desvincula eventos que acionam métodos
    unbindEvents: function() {
      // Desvincula todos os eventos no namespace do nosso plugin que
      // estão anexados para "this.$element".
      this.$element.off('.'+this._name);
    },
    
    // O método que lida com o pressionamento dos botões, tratando os
    // incrementos e decrementos dos valores
    handleAction: function(command, increment) {
      var plugin = this;

      // Como estamos tratando os comandos, então incrementa ou
      // decrementa as datas conforme o caso
      switch (command) {
        case 'Previous':
        case 'Next':
          plugin.currentDate.setMonth(plugin.currentDate.getMonth() + increment);
          plugin.updateBody();
          plugin.updateHeader();

          break;
        default:
          // Retorna ao mês corrente
          var today = new Date();
          today.setHours(0,0,0,0);
          plugin.currentDate.setTime(today.getTime());
          plugin.updateBody();
          plugin.updateHeader();
      }

      // Sinaliza que ocorreram modificações no componente
      plugin.$element.trigger("change");
    },
  });
  
  // Criamos um wrapper de plug-in leve em torno do construtor "Plugin",
  // prevenindo contra instanciações múltiplas.
  $.fn.simplecalendar = function ( options ) {
    this.each(function() {
      if ( !$.data( this, "plugin_" + pluginName ) ) {
        /* Usamos "$.data" para salvar cada instância do plugin no caso
         * do usuário quer modificá-lo. Usando "$.data" desta maneira,
         * garantimos que os dados sejam removidos quando o(s) elemento(s)
         * DOM for removido através de métodos jQuery, assim como quando
         * o usuário deixar a página. É uma maneira inteligente de evitar
         * vazamentos de memória.
         */
        $.data( this, "plugin_" + pluginName, new SimpleCalendar( this, options ) );
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
  $.fn.simplecalendar.defaults = {
    firstDayOfWeek: 0,    // primeiro dia da semana (0 = Domingo)
    constantHeight: true, // adicione linhas a meses mais curtos para
                          // manter a altura do calendário consistente
    text: {
      days: [
        'Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'
      ],
      months: [
        'Janeiro', 'Fevereiro', 'Março', 'Abril',
        'Maio', 'Junho', 'Julho', 'Agosto',
        'Setembro', 'Outubro', 'Novembro', 'Dezembro'
      ]
    }
  };
})( jQuery, window, document );
