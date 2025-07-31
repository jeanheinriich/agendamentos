/* ---------------------------------------------------------------------
 * autocomplete.js
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
 * A classe que fornece o recurso de autocompletar para um campo.
 * Baseado no jQuery e no Semantic UI.
 * 
 * --------------------------------------------------------------------
 * Controle de Modificações
 * 
 * 2020-01-09 - Emerson Cavalcanti
 *   - Versão inicial
 * 
 * 2021-04-23 - Emerson Cavalcanti
 *   - Incluído botão para limpeza do componente
 *   - Incluído recurso para transformar em maiúsculas as letras
 *     durante a digitação
 * ---------------------------------------------------------------------
 */

;(function ($, window, document) {
  'use strict';

  var
    // O nome do plugin. Esta variável é usada no construtor do plugin,
    // assim como o wrapper do plugin para construir a chave para o
    // método "$.data".
    pluginName = 'Autocomplete',
    
    // O namespace para eventos e o módulo
    eventNamespace   = '.' + pluginName,
    moduleNamespace  = "plugin_" + pluginName,

    // Os nomes das classes
    className      = {
      active       : 'active',
      autocomplete : 'ui autocomplete',
      disabled     : 'disabled',
      hidden       : 'hidden',
      selected     : 'selected',
      suggestion   : 'item',
      suggestions  : 'suggestions',
      visible      : 'visible'
    },

    // Os metadados
    metadata       = {
      cache        : 'cache',
      results      : 'results',
      result       : 'result'
    },

    // Os seletores
    selector       = {
      clear        : '.times.icon',
      hint         : '.hint.text',
      icon         : '.search.icon',
      search       : 'input.search',
      selected     : '.item.selected',
      spinner      : '.spinner.icon',
      suggestion   : '.item',
      suggestions  : '.suggestions'
    }
  ;

  // O construtor do plugin constrói uma nova instância do plugin para o
  // nó(s) DOM a partir do qual o plugin foi chamado.
  var Autocomplete = function (element, options) {
    var
      settings          = ( $.isPlainObject(options) )
        ? $.extend(true, {}, $.fn.autocomplete.defaults, options)
        : $.extend({}, $.fn.autocomplete.defaults),

      $module,

      // O input de pesquisa
      $search           = $(element),

      // O botão de limpeza
      $clear            = null,

      // O valor corrente
      currentValue      = $search.val(),

      // A lista de sugestões
      suggestions       = [],

      // A DIV que contém as sugestões a serem exibidas
      $suggestions,
      
      // As informações de seleção
      selection         = null,
      selectedIndex     = -1,

      // As configurações da conexão AJAX para obtenção dos dados remotos
      ajaxSettings      = {},
      currentRequest    = null,

      // Os temporizadores para atraso na execução
      timer             = null,
      blurTimeout       = null,

      // Durante o evento onBlur, o navegador acionará o evento "change"
      // porque o valor foi alterado. Para que a sugestão correta possa
      // ser selecionada ao clicar-se na sugestão com o mouse, e ignorar
      // o efeito colateral de exibir uma outra sugestão, é criada a
      // flag abaixo
      ignoreValueChange = false,

      // A relação de consultas que não retornaram valores
      badQueries        = [],

      // O label que exibe a dica abaixo do texto digitado
      $hint,

      // As configurações da dica de preenchimento em função do valor
      // digitado
      hintValue         = '',
      hint              = null,

      module,
      enable,
      disable,
      clear
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
            $wrap        = module.create.element.div("ui search autocomplete"),
            $hintText    = module.create.element.div("hint text"),
            $spinnerIcon = module.create.element.icon("spinner spin hidden"),
            $searchIcon  = module.create.element.icon("search"),
            $container   = module.create.element.div("suggestions hidden transition"),
            $clearIcon   = module.create.element.icon("times")
          ;

          // Lidamos com campos somente-leitura
          if ($search.is('[readonly]')) {
            module.verbose('Definindo componente como somente leitura');
            $wrap
              .addClass('readonly')
            ;
            $searchIcon
              .addClass('hidden')
            ;
          }

          // Primeiramente, criamos um wrap ao redor do search para
          // conter todo nosso componente
          $search
            .wrap($wrap)
          ;

          // Modificamos os atributos de nosso search
          $search
            .addClass('search')
            .attr('autocomplete', 'nope')
            .attr('tabindex', 0)
          ;

          // Acrescentamos os demais itens do nosso componente
          $module = $search.closest('div.autocomplete');
          $module
            .append($hintText)
            .append($spinnerIcon)
            .append($searchIcon)
            .append($clearIcon)
            .append($container)
          ;

          // Localizamos as partes de nosso componente para acelerar
          $suggestions = $module.find(selector.suggestions);
          $hint        = $module.find(selector.hint);
          $clear       = $module.find(selector.clear);

          if (settings.showClearValue) {
            $search
              .addClass('with')
              .addClass('clear')
            ;
          } else {
            $clear
              .hide()
            ;
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
        message: function (message, type, header) {
          return module.templates.message(message, type, header);
        },
        results: function(suggestionsData) {
          var
            html = '',
            formatResult = (typeof settings.onFormatResult === "function")
              ? settings.onFormatResult
              : function(value, suggestion, i) {
                return suggestion[settings.fieldName];
              }
          ;

          if ($.isEmptyObject(suggestionsData)) {
            if(settings.showNoResults) {
              html = module.templates.message('Não temos sugestões '
                + 'para o termo informado', 'empty', 'Nenhum resultado')
              ;
            } 
          } else {
            // Cria o HTML interno com as sugestões
            $.each(suggestionsData, function (index, suggestion) {
              html += ''
                + '<div class="' + className.suggestion + '" '
                + '     data-index="' + index + '"'
                + '     data-value="' + suggestion[settings.fieldID]
                + '">'
                + formatResult(currentValue, suggestion, index)
                + '</div>'
              ;
            });
          }

          return html;
        }
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

          $search
            .on('keydown'   + eventNamespace, module.event.keydown)
            .on('keyup'     + eventNamespace, module.event.keyup)
          ;
        },

        // Lida com os eventos de entrada
        inputEvents: function() {
          module.verbose('Vinculando eventos de entrada');

          $search
            .on('input'     + eventNamespace, selector.search,
              module.event.search.input)
            .on('change'    + eventNamespace, selector.search,
              module.event.search.change)
          ;
        },

        // Lida com os eventos de mouse
        mouseEvents: function() {
          module.verbose('Vinculando eventos do mouse');

          $suggestions
            .on('click'     + eventNamespace, selector.suggestion,
              module.event.suggestion.click)
          ;
          $module
            .on('blur'      + eventNamespace, selector.search,
              module.event.search.blur)
            .on('click'     + eventNamespace, selector.search,
              module.event.search.click)
            .on('focus'     + eventNamespace, selector.search,
              module.event.search.focus)
          ;

          $module
            .on('click'     + eventNamespace, selector.clear,
              module.event.clear.click)
          ;
          $module
            .on('mouseleave'+ eventNamespace,
              module.event.leave)
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
        keydown: function(event) {
          var
            keyCode = event.which,
            keys    = {
              enter: 13,
              escape: 27,
              leftArrow: 37,
              rightArrow: 39,
              upArrow: 38,
              downArrow: 40,
              tab: 9
            }
          ;

          // Verifica se o campo de entrada está em modo somente
          // leitura
          if ($search.is('[readonly]')) {
            module.verbose('Ignorando pressionamento de teclas em '
              + 'campo de entrada em modo somente leitura')
            ;

            return;
          }

          if (module.is.disabled()) {
            module.verbose('Entrada desabilitada, pressionamento de '
              + 'tecla ignorado')
            ;

            return;
          } else {
            switch (keyCode) {
              case keys.escape:
                // Cancela a operação
                module.debug('Pressionada tecla ESC, cancelando '
                  + 'sugestões')
                ;
                $search
                  .val(currentValue)
                ;
                module.unset.hint();
                module.unset.visible();

                break;
              case keys.tab:
                module.debug('Pressionada tecla TAB');

                // Verificamos se temos uma dica ativa
                if (hint && settings.onHint) {
                  // Selecionamos à partir da dica
                  module.verbose('Selecionando à partir da dica atual',
                    hint)
                  ;
                  module.set.select.hint();

                  return;
                }

                // Verifica se temos algo selecionado
                if (selectedIndex === -1) {
                  // Não deixamos nada selecionado
                  module.verbose('Não temos nada selecionado, '
                    + 'ignorando.')
                  ;
                  module.unset.hint();
                  module.unset.visible();

                  return;
                }

                // Forçamos a seleção da sugestão atual e retornamos
                // para que o TAB possa ser processado normalmente
                module.set.select.suggestion(selectedIndex);
                
                return;

                break;
              case keys.enter:
                module.debug('Pressionada tecla ENTER');

                // Verifica se temos algo selecionado
                if (selectedIndex === -1) {
                  // Não deixamos nada selecionado
                  module.verbose('Não temos nada selecionado, '
                    + 'ignorando.')
                  ;
                  module.unset.hint();
                  module.unset.visible();

                  if (settings.alertInvalidSelection) {
                    module.verbose('Sinalizando erro');
                    $module
                      .addClass('error')
                    ;
                  }

                  return;
                }

                // Forçamos a seleção da sugestão atual e retornamos
                module.set.select.suggestion(selectedIndex);

                break;
              case keys.rightArrow:
                module.debug('Pressionada tecla Seta Direita');

                // Verificamos se temos uma dica ativa
                if (hint && settings.onHint && module.is.cursorAtEnd()) {
                  // Selecionamos à partir da dica
                  module.verbose('Selecionando à partir da dica atual');
                  module.set.select.hint();
                }
                
                return;

                break;
              case keys.upArrow:
                module.debug('Pressionada tecla Seta Acima');

                // Verifica se temos algo selecionado
                if (selectedIndex == -1) {
                  module.verbose('Não temos nada selecionado, '
                    + 'ignorando.')
                  ;

                  return;
                }

                // Se estivermos no primeiro item selecionado então
                // precisamos desmarcar todos
                if (module.is.on.firstSuggestion()) {
                  module.verbose('Alcançado primeiro elemento, '
                    + 'desmarcando quaisquer sugestões')
                  ;
                  module.unset.active.suggestion();
                  ignoreValueChange = false;

                  // Atribuímos o valor corrente
                  module.verbose('Atribuímos o valor corrente ao search',
                    currentValue)
                  ;
                  $search
                    .val(currentValue)
                  ;
                  $search
                    .focus()
                    .val($search.val())
                  ;

                  // Atualiza a dica de sugestão de preenchimento
                  module.set.hint();

                  // Cancela o evento
                  event.stopImmediatePropagation();
                  event.preventDefault();

                  return;
                }

                module.adjustScroll(selectedIndex - 1);

                break;
              case keys.downArrow:
                module.debug('Pressionada tecla Seta Abaixo');

                // Se as sugestões estiverem ocultas e for pressionada a
                // seta para baixo, devemos exibir as sugestões
                if (module.is.not.visible() && currentValue) {
                  if (!suggestions.length && !module.is.value.changed()) {
                    module.debug('Recarregando lista de sugestões para '
                      + 'o termo', currentValue)
                    ;
                    module.set.hint();
                    module.set.value.changed();
                  } else {
                    module.verbose('Exibindo lista de sugestões');
                    module.suggest();
                  }

                  return;
                }

                // Se estivermos no último item selecionado, então ignora
                if (module.is.on.lastSuggestion()) {
                  module.verbose('Alcançado último elemento, '
                    + 'ignorando')
                  ;

                  return;
                }

                module.adjustScroll(selectedIndex + 1);

                break;
              default:
                return;
            }

            // Cancela o evento se a função ainda não tiver retornado
            event.stopImmediatePropagation();
            event.preventDefault();
          }
        },
        keyup: function(event) {
          var
            keyCode = event.which,
            keys    = {
              UP: 38,
              DOWN: 40
            }
          ;

          // Verifica se o campo de entrada está em modo somente
          // leitura
          if ($search.is('[readonly]')) {
            module.verbose('Ignorando pressionamento de teclas em '
              + 'campo de entrada em modo somente leitura')
            ;

            return;
          }

          if (module.is.disabled()) {
            module.verbose('Entrada desabilitada, liberação de tecla '
              + 'ignorada')
            ;

            return;
          } else {
            module.debug('Evento de tecla liberada');

            // Primeiramente tratamos as teclas direcionais
            switch (keyCode) {
              case keys.UP:
              case keys.DOWN:
                module.verbose('Liberada tecla de seta acima/abaixo');

                return;
            }

            // Lidamos com a capitalização das letras, se necessário
            if (settings.convertToUppercase) {
              module.debug("Analisando capitalização");
              if (keyCode >= 65 && keyCode <= 90) {
                module.debug("Forçando conteúdo para maiúscula");
                var
                  value = $search.val()
                ;

                $search.val(value.toUpperCase());
              }
            }

            clearTimeout(timer);

            // Verifica se o valor foi modificado
            if (module.is.value.changed()) {
              if ($search.val() === '...') {
                module.debug('Ignorando a ação de autocompletar');

                return;
              }
              
              module.debug('O valor corrente', currentValue, 'difere '
                + 'da entrada', $search.val())
              ;
              module.set.hint();
              module.set.value.changed();
            }
          }

          // Cancela a propagação do evento
          event.stopImmediatePropagation();
        },
        leave: function() {
          module.debug('Mouse saiu do componente');
          selectedIndex = -1;
        },
        search: {
          blur: function(event) {
            module.debug('Componente perdeu foco');
            module.set.blur(event);
          },
          change: function(event) {
            if (module.is.disabled()) {
              module.verbose('Entrada desabilitada, modificação do '
                + 'campo de pesquisa ignorada')
              ;

              return;
            } else {
              module.debug('Entrada modificada, atualizando seleção');

              clearTimeout(timer);

              // Verifica se o valor foi modificado
              if (module.is.value.changed()) {
                module.debug('O valor corrente', currentValue, 'difere '
                  + 'da entrada', $search.val())
                ;
                module.set.hint();
                module.set.value.changed();
              }
            }
          },
          click: function() {
            module.debug('Evento de click no search');
            module.unset.blur();
          },
          focus: function(event) {
            module.debug('Componente recebeu foco');

            // Verifica se o campo de entrada está em modo somente
            // leitura
            if ($search.is('[readonly]')) {
              module.verbose('Ignorando campo somente leitura');

              return;
            }

            ignoreValueChange = false;

            if(settings.searchOnFocus && module.has.minimumCharacters() ) {
              module.event.value.changed();
            }
          },
          input: function(event) {
            module.debug('Evento', event);

            if (module.is.disabled()) {
              module.verbose('Entrada desabilitada, entrada no campo '
                + 'de pesquisa ignorada')
              ;

              return;
            } else {
              module.debug('Entrada modificada, atualizando seleção');

              clearTimeout(timer);

              // Verifica se o valor foi modificado
              if (module.is.value.changed()) {
                module.debug('O valor corrente', currentValue, 'difere '
                  + 'da entrada', $search.val())
                ;
                module.set.hint();
                module.set.value.changed();
              }
            }
          }
        },
        suggestion: {
          click: function(event) {
            event.stopPropagation();
            var
              index = $(this).data('index')
            ;

            module.debug('Selecionada sugestão', index);
            module.set.select.suggestion(index);

          }
        },
        clear: {
          click: function(event) {
            event.stopPropagation();

            module.debug('Limpado o valor');
            module.unset.hint();
            module.unset.visible();
            selection = null;
            currentValue = '';
            selectedIndex = -1;
            ignoreValueChange = false;
            $search.val(currentValue);

            if (typeof settings.onInvalidateSelection === "function") {
              settings.onInvalidateSelection.call($search.get(0));
            }
          }
        },
        value: {
          changed: function() {
            var
              searchTerm = module.get.query()
            ;

            module.debug('Evento de modificação do valor de pesquisa');

            if (ignoreValueChange) {
              module.debug('Ignorando a modificação de valor');
              ignoreValueChange = false;

              return;
            }

            // Verifica se temos algo selecionado e o valor digitado difere
            // do valor corrente
            if (selection && currentValue !== searchTerm) {
              module.debug('Seleção invalidada');
              selection = null;

              if (settings.alertInvalidSelection) {
                module.verbose('Sinalizando erro');
                $module
                  .addClass('error')
                ;
              }

              if (typeof settings.onInvalidateSelection === "function") {
                settings.onInvalidateSelection.call($search.get(0));
              }
            }

            clearTimeout(timer);

            // Atribuímos o valor corrente
            module.debug('Modificado o valor corrente de', currentValue,
              'para', searchTerm)
            ;
            currentValue = searchTerm;
            selectedIndex = -1;

            if (typeof settings.onChange === "function") {
              settings.onChange.call($search.get(0), searchTerm);
            }

            // Se o que foi digitado corresponder exatamente à única opção
            // retornada, então selecionamos este item
            if (module.is.exactMatch(searchTerm)) {
              module.debug('Valor digitado corresponde à única '
                + 'sugestão, então seleciona-a')
              ;
              module.set.select.suggestion(0);

              return;
            }

            if (module.has.minimumCharacters(searchTerm)) {
              // Recuperamos as sugestões de completar para o termo digitado
              module.debug('Localizando sugestões para o termo',
                searchTerm)
              ;
              module.get.suggestions(searchTerm);
            } else {
              // Escondemos as sugestões
              module.unset.hint();
              module.unset.visible();
            }
          }
        },
        xhr: {
          always: function() {
            // Nada especial
            module.unset.loading();
          },
          done: function(response, status, xhr) {
            var
              searchTerm = ajaxSettings.searchTerm,
              result,
              json
            ;

            try {
              // Conforme o tipo de resposta, trata          
              switch (typeof response) {
                case 'object':
                  module.verbose('Recebido um JSON');
                  json = response;

                  break;
                default:
                  module.verbose('Convertendo response para JSON');

                  try {
                    json = JSON.parse(response);
                  } catch (error) {
                    module.set.error('A resposta do servidor não '
                      + 'contém um JSON válido', 'Desculpe, ocorreu um '
                      + 'erro', remote.searchTerm, xhr, status, '')
                    ;
                  }
              }

              // Verifica se nossa resposta contém as chaves 'data' e
              // 'result', e se 'result' contém o valor 'OK'
              if (('data' in json) &&
                  ('result' in json)) {
                // Verifica se o resultado é positivo
                if (json.result.toUpperCase() === 'OK') {
                  // Recebemos uma resposta válida, então tratamos
                  module.verbose(json.message);

                  // Verificamos se os dados recebidos são válidos
                  if ($.isPlainObject(json.data) ||
                      Array.isArray(json.data)) {
                    if (!json.data.length) {
                      // Adicionamos o termo pesquisado caso a consulta
                      // não tenha retornado valores
                      module.set.badQuery(searchTerm);
                    } else {
                      // Armazena a consulta em cache
                      module.write.cache(searchTerm, json.data);

                      // Sempre seta que não temos mais erro
                      module.unset.error();
                    }

                    // Importamos os dados recebidos
                    suggestions = json.data;
                    module.suggest();

                    if (typeof settings.onSearchComplete === "function") {
                      settings.onSearchComplete.call(
                        $search.get(0), searchTerm, json.data
                      );
                    }
                  } else {
                    // Não recebemos dados válidos, então notifica
                    module.set.error(
                      'A resposta do servidor não possui dados válidos',
                      'Desculpe, ocorreu um erro',
                      searchTerm, xhr, status, ''
                    );
                  }
                } else {
                  // A resposta não foi positiva, então alerta
                  module.set.error(
                    json.message,
                    'Desculpe, ocorreu um erro',
                    searchTerm, xhr, status, json.message
                  );
                }
              } else {
                // O formato dos dados JSON é inválido
                module.set.error(
                  'A resposta do servidor não possui os campos '
                   + 'necessários', 'Desculpe, ocorreu um erro',
                  searchTerm, xhr, status, ''
                );
              }
            } finally {
              // Destrói a requisição ajax
              currentRequest = null;
            }
          },
          fail: function(xhr, status, httpMessage) {
            var
              errorMsg
            ;

            // Ignora erros de abortamento de conexão
            if (httpMessage == 'abort' || httpMessage == 'undefined') {
              return;
            }

            switch (xhr.status) {
              case 403:
                // Erro de permissão
                errorMsg = 'Acesso ao recurso em ' + ajaxSettings.url
                  + ' não foi permitido'
                ;

                break;
              case 404:
                // Erro de recurso inexistente
                errorMsg = 'Acesso ao recurso em ' + ajaxSettings.url
                  + ' inexistente'
                ;

                break;
              case 405:
                // Erro de método
                errorMsg = 'O método HTTP ' + ajaxSettings.method
                  + ' não é permitido'
                ;

                break;
              case 500:
                // Erro interno da aplicação remota
                errorMsg = 'Acesso ao recurso em ' + ajaxSettings.url
                  + ' retornou erro no servidor'
                ;

                break;
              default:
                errorMsg = 'Falha na requisição ao recurso em '
                  + ajaxSettings.url
                ;
            }

            if (xhr.status > 0) {
              module.error('Erro HTTP ' + xhr.status + ':', errorMsg);
            } else {
              module.error('Erro na requisição AJAX:', errorMsg);
            }

            // Setamos a mensagem de erro
            module.set.error(errorMsg, 'Desculpe, ocorreu um erro',
              ajaxSettings.searchTerm, xhr, status, httpMessage)
            ;
          }
        }
      },

      // ================================================[ Depuração ]==

      debug: function() {
        if (settings.debug) {
          module.debug = Function.prototype.bind.call(
            console.info, console, pluginName + ':'
          );
          module.debug.apply(console, arguments);
        }
      },
      verbose: function() {
        if (settings.debug && settings.verbose) {
          module.verbose = Function.prototype.bind.call(
            console.warn, console, pluginName + ':'
          );
          module.verbose.apply(console, arguments);
        }
      },
      error: function() {
        module.error = Function.prototype.bind.call(
          console.error, console, pluginName + ':'
        );
        module.error.apply(console, arguments);
      },

      // ====================================================[ Ações ]==

      adjustScroll: function(index) {
        var
          activeItem = module.set.active.suggestion(index);

        module.debug('Ajustando scroll');

        if (!activeItem) {
          module.verbose(
            'Não temos nenhum item ativo para o índice', index
          );

          return;
        }

        var
          offsetTop,
          upperBound,
          lowerBound,
          heightDelta = $(activeItem).outerHeight()
        ;

        offsetTop = activeItem.offsetTop;
        upperBound = $suggestions.scrollTop();
        lowerBound = upperBound + settings.maxHeight - heightDelta;

        if (offsetTop < upperBound) {
          $suggestions
            .scrollTop(offsetTop)
          ;
        } else if (offsetTop > lowerBound) {
          $suggestions
            .scrollTop(offsetTop - settings.maxHeight + heightDelta)
          ;
        }

        module.verbose(
          'Setando para ignorar próxima modificação de valor'
        );
        ignoreValueChange = true;

        // Seta o valor correto
        $search
          .val(module.get.suggestion(index).name)
        ;

        // Esconde a dica
        module.unset.hint();
      },

      abortAjax: function () {
        if (currentRequest) {
          currentRequest.abort();
          currentRequest = null;
        }
      },

      has: {
        minimumCharacters: function(searchTerm) {
          searchTerm = (searchTerm !== undefined)
            ? String(searchTerm)
            : String(module.get.query())
          ;

          return (searchTerm.length >= settings.minCharacters);
        }
      },

      is: {
        badQuery: function(searchTerm) {
          if (settings.preventBadQueries) {
            module.verbose('Verificando consultas ruins para o termo', searchTerm);
            if (badQueries.indexOf(searchTerm) != -1) {
              module.debug(
                'O termo', searchTerm, 
                'já foi consultado e não retornou sugestões'
              );
              return true;
            }
          }

          return false;
        },
        cursorAtEnd: function () {
          var
            length         = $search.val().length,
            selectionStart = $search.selectionStart,
            range
          ;

          // Verificamos se temos alguma seleção
          if (typeof selectionStart === 'number') {
            return selectionStart === valLength;
          }

          if (document.selection) {
            range = document.selection.createRange();
            range.moveStart('character', -valLength);

            return valLength === range.text.length;
          }

          return true;
        },
        disabled: function() {
          return $module.hasClass(className.disabled);
        },
        exactMatch: function(searchTerm) {
          return (suggestions.length === 1 &&
                  suggestions[0][settings.fieldName].toLowerCase() === searchTerm.toLowerCase());
        },
        focused: function() {
          return ($search.filter(':focus').length > 0);
        },
        hidden: function() {
          return $suggestions.hasClass(className.hidden);
        },
        not: {
          visible: function() {
            return !module.is.visible();
          }
        },
        on: {
          firstSuggestion: function() {
            return selectedIndex == 0;
          },
          lastSuggestion: function() {
            return selectedIndex == (suggestions.length - 1);
          }
        },
        value: {
          changed: function() {
            return currentValue !== $search.val();
          }
        },
        visible: function() {
          return $module.hasClass(className.active);
        }
      },

      // Torna um string normalizado, permitindo a busca sem se
      // preocupar com acentuações e/ou a capitalização
      normalize: function(value) {
        value = (value !== undefined)
          ? String(value)
          : ''
        ;

        return value
          .normalize('NFD')
          .replace(/[\u0300-\u036f]/g, "")
          .toLowerCase()
        ;
      },

      get: {
        hint: function(searchTerm) {
          var
            bestMatch = null,
            index = -1
          ;

          searchTerm = module.normalize(searchTerm);

          if (!searchTerm) {
            module.verbose('Valor para pesquisa', searchTerm);

            return { 'index': index, 'value': bestMatch };
          }

          $.each(suggestions, function (i, suggestion) {
            var
              match = module.normalize(suggestion[settings.fieldName]),
              foundMatch = match.indexOf(searchTerm) === 0
            ;

            if (foundMatch) {
              bestMatch = suggestion[settings.fieldName];
              index     = i;

              return false;
            }
          });

          return {
            'index': index,
            'value': bestMatch
          };
        },
        query: function() {
          return $.trim($search.val());
        },
        // Recupera uma sugestão pelo seu índice
        suggestion: function(index) {
          module.verbose(
            'Tentando recuperar sugestão pelo índice', index
          );

          if (typeof index == "undefined") {
            module.verbose('Índice indefinido', suggestions.length);

            return null;
          }

          if (index !== -1 &&
              index > suggestions.length) {
            module.verbose(
              'Índice', index, 'ultrapassa limites de '
                + 'sugestões disponíveis', suggestions.length
            );

            return null;
          }

          module.verbose(suggestions[index]);

          var
            suggestion = suggestions[index],
            Name       = suggestion[settings.fieldName]
          ;

          return {
            'data' : suggestion,
            'name' : Name
          };
        },
        suggestions: function(searchTerm) {
          searchTerm = (searchTerm !== undefined)
            ? String(searchTerm)
            : String(module.get.query())
          ;

          // Primeiramente, verificamos se foi informada uma função externa
          // para obtenção dos dados
          if (typeof settings.onLookup === "function") {
            // Requisita os dados usando à função externa
            module.debug('Requisitando sugestões pela função de lookup',
              searchTerm)
            ;
            settings.onLookup.call(
              $search.get(0),
              searchTerm,
              function (json) {
                suggestions = json.data;
                module.suggest();

                if (typeof settings.onSearchComplete === "function") {
                  settings.onSearchComplete.call($search.get(0),
                    searchTerm, suggestions
                  );
                }
              }
            );

            return;
          } else {
            // Verifica se o termo está na relação de consultas que não
            // retornaram valor
            if (module.is.badQuery(searchTerm)) {
              suggestions = [];
              module.suggest();

              if (typeof settings.onSearchComplete === "function") {
                settings.onSearchComplete.call(
                  $search.get(0), searchTerm, suggestions
                );
              }
            } else {
              // Verifica se temos um objeto com os dados
              if ($.isPlainObject(settings.source) ||
                  Array.isArray(settings.source)) {
                // Realiza uma consulta local às sugestões
                module.debug('Localizando dados localmente baseado no '
                  + 'termo', searchTerm
                );
                module.search.local(searchTerm);
              } else {
                // Realiza uma consulta remota às sugestões
                module.debug('Localizando dados remotamente baseado no '
                  + 'termo', searchTerm
                );
                module.search.remote(searchTerm);
              }
            }
          }
        }
      },

      set: {
        active: {
          // Ativa a sugestão pelo índice informado
          suggestion: function(index) {
            var
              content = $suggestions.find(selector.suggestion);

            module.debug('Ativada sugestão', index);

            // Remove quaisquer sugestões selecionadas anteriormente e
            // seta a sugestão indicada como selecionada
            module.unset.active.suggestion();
            selectedIndex = index;

            // Verifica se podemos selecionar o item indicado
            if (selectedIndex !== -1 &&
                content.length > selectedIndex) {
              // Recuperamos o item selecionado e ativamo-os
              var activeItem = content.get(selectedIndex);
              $(activeItem)
                .addClass(className.selected)
              ;

              return activeItem;
            }

            return null;
          }
        },
        badQuery: function(searchTerm) {
          if (settings.preventBadQueries) {
            if (badQueries.indexOf(searchTerm) === -1) {
              module.verbose('Armazenando o termo', searchTerm,
                'na relação de sugestões que não retornaram valores');
              badQueries.push(searchTerm);
            }
          }
        },
        blur: function(event) {
          var
            blurDelay = settings.blurDelay
          ;
          event.preventDefault();
          module.verbose('Iniciando evento de blur');

          if (blurDelay > 0) {
            module.verbose('Atrasando blur', blurDelay);
            blurTimeout = setTimeout(function () {
              module.verbose('Lidando com o evento de blur');
              module.unset.hint();
              module.unset.visible();
              
              if (selection && module.is.value.changed()) {
                module.debug('Seleção invalidada');
                
                if (settings.alertInvalidSelection) {
                  module.verbose('Sinalizando erro');
                  $module
                    .addClass('error')
                  ;
                }
                
                if (typeof settings.onInvalidateSelection === "function") {
                  settings.onInvalidateSelection.call($search.get(0));
                }
              }
            }, blurDelay);
          } else {
            module.unset.hint();
            module.unset.visible();
            
            if (selection && module.is.value.changed()) {
              module.debug('Seleção invalidada');
              
              if (settings.alertInvalidSelection) {
                module.verbose('Sinalizando erro');
                $module
                  .addClass('error')
                ;
              }
              
              if (typeof settings.onInvalidateSelection === "function") {
                settings.onInvalidateSelection.call($search.get(0));
              }
            }
          }
        },
        disabled: function () {
          $module
            .addClass(className.disabled)
          ;
          $search
            .prop('readonly', true)
          ;
          clearTimeout(timer);
          module.abortAjax();
        },
        enable: function () {
          $module
            .removeClass(className.disabled)
          ;
          $search
            .prop('readonly', false)
          ;
        },
        error: function(message, title, searchTerm, xhr, status, httpMessage) {
          var
            searchHTML = module.create.message(message, 'error', title);
          $suggestions
            .html(searchHTML)
          ;
          $module
            .addClass('error')
          ;

          module.unset.hint();
          selection = null;
          module.set.visible();

          if (typeof settings.onSearchError === "function") {
            settings.onSearchError.call($search.get(0), searchTerm, xhr,
              status, httpMessage)
            ;
          }

          if (typeof settings.onInvalidateSelection === "function") {
            settings.onInvalidateSelection.call($search.get(0));
          }
        },
        hint: function() {
          var
            searchTerm = module.get.query(),
            hintObj    = module.get.hint(searchTerm),
            hintText   = '',
            suggestion = hintObj.value
          ;

          if (!searchTerm) {
            module.unset.hint();

            return;
          }

          if (suggestion) {
            hintText = searchTerm + suggestion.substr(searchTerm.length);
          }

          // Verifica se a sugestão a ser proposta foi modificada
          if (hintValue !== hintText) {
            module.debug('Informando uma nova dica', hintText);
            hintValue = hintText;
            hint      = suggestion;

            $hint
              .text(hintText)
            ;

            if (typeof settings.onHint === "function") {
              settings.onHint.call($search.get(0), hintText);
            }
          }
        },
        loading: function() {
          $module
            .find(selector.spinner)
            .removeClass('hidden')
          ;
        },
        select: {
          // Seleciona a sugestão pela dica atual
          hint: function() {
            var
              searchTerm = module.normalize(hint)
            ;
            module.verbose('Localizando índice da sugestão pelo termo',
              searchTerm)
            ;

            // Percorre as sugestões e tenta localizar a dica nas
            // sugestões atuais
            $.each(suggestions, function (i, suggestion) {
              var
                match = module.normalize(suggestion[settings.fieldName]),
                foundMatch = match === searchTerm
              ;

              if (foundMatch) {
                currentValue = suggestion[settings.fieldName];
                module.set.select.suggestion(i);

                return false;
              }
            });
          },
          // Seleciona a sugestão pelo índice informado
          suggestion: function(index) {
            var
              suggestion = module.get.suggestion(index)
            ;

            if (!suggestion) {
              module.debug('Não temos nenhuma sugestão, ignorando');

              return;
            }

            module.debug(
              'Selecionada sugestão', index, ':', suggestion.name
            );

            $search
              .val(suggestion.name)
            ;
            currentValue = suggestion.name;

            module.unset.hint();
            module.unset.visible();
            module.unset.error();
            suggestions = [];
            selection = suggestion.data;

            // Verificamos se foi informada uma função externa à ser
            // acionada quando ocorrer um evento de seleção
            if (typeof settings.onSelect === "function") {
              // Executa a função externa
              settings.onSelect.call(
                $search.get(0), $search, suggestion.data
              );
            }
          }
        },
        value: {
          changed: function() {
            var
              searchDelay = settings.searchDelay
            ;

            // Retira quaisquer erros
            module.unset.error();

            if (searchDelay > 0) {
              // Adia a pesquisa caso o valor mude muito rapidamente
              module.verbose('Atrasando pesquisa', searchDelay);
              clearTimeout(timer);
              timer = setTimeout(function() {
                module.verbose('Iniciando pesquisa...');
                if (module.is.focused()) {
                  module.verbose('Estamos com foco no componente');
                  module.event.value.changed();
                }
              }, searchDelay);
            } else {
              module.event.value.changed();
            }
          }
        },
        visible: function() {
          $module
            .addClass(className.active)
          ;
          $suggestions
            .removeClass(className.hidden)
          ;
        }
      },

      unset: {
        active: {
          // Desativa quaisquer sugestões
          suggestion: function() {
            $suggestions
              .find(selector.selected)
              .removeClass(className.selected)
            ;

            // Indicamos que não temos nenhum valor selecionado
            selectedIndex = -1;
          }
        },
        blur: function() {
          clearTimeout(this.blurTimeoutId);
        },
        error: function() {
          $module
            .removeClass('error')
          ;
        },
        hint: function() {
          hintValue = '';
          hint      = null;

          $hint
            .text('')
          ;
        },
        loading: function() {
          $module
            .find(selector.spinner)
            .addClass('hidden')
          ;
        },
        visible: function () {
          // Interrompe atividades em andamento
          clearTimeout(timer);
          module.abortAjax();

          // Verifica a necessidade de disparar o evento onHide
          if ((typeof settings.onHide === "function") && module.is.visible()) {
            settings.onHide.call($search.get(0));
          }

          // Esconde a lista de sugestões
          selectedIndex = -1;

          $module
            .removeClass(className.active)
          ;
          $suggestions
            .addClass('hidden')
          ;
        }
      },

      read: {
        cache: function(searchTerm) {
          var
            cache = $module.data(metadata.cache)
          ;
          module.verbose('Cache', cache);

          if (settings.cache) {
            module.verbose(
              'Verificando o cache para o termo', searchTerm
            );
            return (typeof cache == 'object') && (cache[searchTerm] !== undefined)
              ? cache[searchTerm]
              : false
            ;
          }

          return false;
        }
      },

      search: {
        local: function(searchTerm) {
          var
            limit = parseInt(settings.maxResults),
            data
          ;

          module.set.loading();
          module.debug(
            'Obtendo localmente todas sugestões baseadas no termo', searchTerm
          );
          searchTerm = module.normalize(searchTerm);
          data = $.grep(settings.source, function(suggestion) {
            var
              match = module.normalize(suggestion[settings.fieldName])
            ;

            return match.indexOf(searchTerm) !== -1;
          });

          if (limit > 0 && data.length > limit) {
            module.debug(
              'Retornando resultados dentro do limite especificado',
              limit
            );
            data = data.slice(0, limit);
          }

          // Adicionamos o termo pesquisado caso a consulta não tenha
          // retornado valores
          if (!data.length) {
            module.set.badQuery(searchTerm);
          }

          suggestions = data;
          module.suggest();

          if (typeof settings.onSearchComplete === "function") {
            settings.onSearchComplete.call(
              $search.get(0), searchTerm, suggestions
            );
          }

          module.unset.loading();
        },
        remote: function(searchTerm) {
          var
            ajax  = settings.ajax,
            limit = parseInt(settings.maxResults),
            data  = {
              'limit': limit,
              'searchTerm': searchTerm
            },
            cache = module.read.cache(searchTerm),
            customData,
            forceRestoreData = false
          ;

          if (cache) {
            module.debug(
              'Lendo resultados do cache para o termo', searchTerm
            );

            // Sempre seta que não temos mais erro
            module.unset.error();

            suggestions = cache;
            module.suggest();

            if (typeof settings.onSearchComplete === "function") {
              settings.onSearchComplete.call(
                $search.get(0), searchTerm, suggestions
              );
            }
          } else {
            module.set.loading();
            module.debug(
              'Obtendo remotamente todas sugestões baseadas no termo',
              searchTerm
            );

            // Determinamos os parâmetros de nossa requisição, se necessário
            if ($.isPlainObject(ajax) && ajax.data) {
              customData       = ajax.data;
              forceRestoreData = true;
              module.verbose('Preparando dados');
              module.verbose('Dados originais', data);
              
              var
                processedData = (typeof customData === 'function')
                 ? customData(data, settings) // uma função que pode manipular os dados ou retornar
                 : customData                 // um objeto ou matriz para mesclar
              ;
              module.verbose(
                'Tipo dos dados passados', typeof customData
              );
              module.verbose('Retorno do processamento', processedData);

              // Se a função que prepara os parâmetros retornou algo, use
              // estes valores sozinho
              data = typeof customData === 'function' && processedData
                ? processedData
                : $.extend(true, data, processedData)
              ;
              module.verbose('Dados modificados', data);

              // Remove a propriedade data, pois como já a resolvemos e
              // não desejamos que o jQuery o faça novamente
              delete ajax.data;
            }

            // As configurações da conexão AJAX
            ajaxSettings = {
              searchTerm  : searchTerm,
              url         : 'localhost',
              type        : 'GET',
              data        : data,
              dataType    : 'json',
              contentType : 'application/x-www-form-urlencoded; charset=UTF-8'
            };

            if ($.isPlainObject(ajax) ||
                Array.isArray(ajax)) {
              $.extend(true, ajaxSettings, ajax);
            }

            // Restauramos a propriedade data, pois como já temos os
            // dados processados, precisamos garantir o funcionamento
            // para a próxima requisição
            if (forceRestoreData) {
              ajax.data = customData;
            }

            // Abortamos quaisquer requisições anteriores
            module.abortAjax();

            module.verbose(
              'Requisitando dados remotamente usando as configurações',
              ajax
            );
            currentRequest = $.ajax(ajaxSettings)
              .always(module.event.xhr.always)
              .done(module.event.xhr.done)
              .fail(module.event.xhr.fail)
            ;
          }
        }
      },

      clear: function() {
        clearTimeout(timer);
        module.unset.hint();
        module.unset.visible();
        module.unset.error();
        selection = null;
        currentValue = '';
        selectedIndex = -1;
        ignoreValueChange = false;
        $search.val(currentValue);
        $module
          .removeData(metadata.cache)
        ;

        if (typeof settings.onInvalidateSelection === "function") {
          settings.onInvalidateSelection.call($search.get(0));
        }
      },

      suggest: function() {
        var
          searchHTML;

        // Verifica se não temos sugestões a serem exibidas
        if (!suggestions.length) {
          module.debug('Sem nada a ser sugerido');
          module.unset.hint();
          selection = null;

          if (settings.alertInvalidSelection) {
            module.verbose('Sinalizando erro');
            $module
              .addClass('error')
            ;
          }

          if (typeof settings.onInvalidateSelection === "function") {
            settings.onInvalidateSelection.call($search.get(0));
          }

          if (settings.showNoResults) {
            searchHTML = module.create.results(suggestions);
            $suggestions
              .html(searchHTML)
            ;

            module.set.visible();

            if (typeof settings.onNoResults === "function") {
              settings.onNoResults.call(
                $search.get(0), $search, currentValue
              );
            }
          } else {
            // Escondemos as sugestões
            module.unset.visible();
          }

          return;
        }

        // Se o que foi digitado corresponder exatamente à única opção
        // retornada, então selecionamos este item
        if (module.is.exactMatch(currentValue)) {
          module.debug(
            'Valor digitado corresponde à única sugestão, então '
              + 'seleciona-a'
          );
          module.set.select.suggestion(0);

          return;
        }

        module.debug('Gerando os itens a serem sugeridos');
        searchHTML = module.create.results(suggestions);
        $suggestions
          .html(searchHTML)
        ;

        // Seleciona o primeiro valor por padrão, se necessário
        if (settings.autoSelectFirst) {
          module.debug('Selecionando o primeiro item automaticamente');
          selectedIndex = 0;
          $suggestions
            .scrollTop(0)
          ;
          $suggestions
            .children(selector.suggestion)
            .first()
            .addClass(className.selected)
          ;
        }

        module.set.hint();
        module.set.visible();
      },

      templates: {
        message: function(message, type, header) {
          var
            html = '',
            icon = ''
          ;

          if (message !== undefined && type !== undefined) {
            switch (type) {
              case 'empty': icon = 'exclamation triangle'; break;
              case 'error': icon = 'exclamation circle';   break;
              default:      icon = 'info circle';
            }

            if ($suggestions.width() < 210) {
              html +=  ''
                + '<div class="message ' + type + '">'
                + '  <div class="content">'
                + '    <div class="header">' + header + '</div>'
                + '    <div class="description">' + message + '</div>';
                + '  </div>'
                + '</div>'
              ;
            } else {
              html +=  ''
                + '<div class="message ' + type + '">'
                + '  <i class="' + icon + ' icon"></i>'
                + '  <div class="content">'
                + '    <div class="header">' + header + '</div>'
                + '    <div class="description">' + message + '</div>';
                + '  </div>'
                + '</div>'
              ;
            }
          }

          return html;
        }
      },

      write: {
        cache: function(searchTerm, data) {
          var
            cache = ($module.data(metadata.cache) !== undefined)
              ? $module.data(metadata.cache)
              : {}
          ;

          if(settings.cache) {
            module.verbose(
              'Escrevendo resultado no cache', searchTerm, data
            );
            cache[searchTerm] = data;
            $module
              .data(metadata.cache, cache)
            ;
          }
        }
      }
    };

    this.disable = function() {
      module.verbose('Desabilitando autocomplete');
      module.set.disabled();
    };

    this.enable = function() {
      module.verbose('Habilitando autocomplete');
      module.set.enable();
    };

    this.clear = function() {
      module.debug('Resetando');
      module.clear();
    };

    // Inicializa nosso plugin
    module.initialize();
  };

  
  // Criamos um wrapper de plug-in leve em torno do construtor "Plugin",
  // prevenindo contra instanciações múltiplas.
  $.fn.autocomplete = function ( options ) {
    var
      instance,
      query = arguments[0],
      methodInvoked   = (typeof query == 'string'),
      queryArguments  = [].slice.call(arguments, 1)
    ;

    this.each(function() {
      if ( !$.data( this, moduleNamespace ) ) {
        /* Usamos "$.data" para salvar cada instância do plugin no caso
         * do usuário quer modificá-lo. Usando "$.data" desta maneira,
         * garantimos que os dados sejam removidos quando o(s) elemento(s)
         * DOM for removido através de métodos jQuery, assim como quando
         * o usuário deixar a página. É uma maneira inteligente de evitar
         * vazamentos de memória.
         */
        $.data(this, moduleNamespace, new Autocomplete(this, options));
      }

      instance = $.data( this, moduleNamespace );
    });

    if (methodInvoked) {
      // Invocamos o método
      if (instance === undefined) {
        this.clear();
      } else  {
        instance.clear();
      }
    }
    
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
  $.fn.autocomplete.defaults = {
    debug                 : false,
    verbose               : false,

    // Quantidade mínima de caracteres necessárias para iniciar a busca
    minCharacters         : 1,

    // Se deve selecionar o primeiro resultado automaticamente após uma
    // pesquisa retornar um ou mais valores
    // 
    autoSelectFirst       : false,

    // Se deve alertar em caso de entrada inválida
    alertInvalidSelection : true,

    // O nome do campo que contém o valor a ser utilizado ao completar
    fieldID               : 'id',
    fieldName             : 'name',

    // Objeto que contenha os dados a serem pesquisados
    source                : null,

    // Se o termo atual deve ser consultado ao receber o foco
    searchOnFocus         : true,

    // Tempo de espera antes de iniciar a pesquisa (em milissegundos)
    searchDelay           : 500,

    // Tempo de espera antes de perder o foco (em milissegundos)
    blurDelay             : 200,

    // Quantidade máxima de resultados obtidos a cada pesquisa
    maxResults            : 7,

    // Se armazena pesquisas no cache local
    cache                 : true,

    // Se o erro de nenhum resultado obtido deve ser exibido
    showNoResults         : true,

    // Se o botão de limpeza do valor deve ser exibido
    showClearValue        : false,

    // O timer utilizado para atrasar as consultas
    timer                 : null,

    // A flag indicativa de prevenir repetir consultas que não retornaram
    // sugestões
    preventBadQueries     : true,

    // A flag indicativa de converter os textos para maiúsculas
    convertToUppercase    : false,

    /* Funções de Callbacks */
    onChange              : function(searchTerm) { },
    onFormatResult        : null, 
    onHide                : function() { },
    onHint                : function(hint) { },
    onInvalidateSelection : function() { },
    onLookup              : null,
    onNoResults           : function(searchTerm) { },
    onSearchComplete      : function(searchTerm, suggestions) { },
    onSearchError         : function(searchTerm, xhr, status, httpMessage) { },
    onSelect              : function(suggestion) { }
  };
})( jQuery, window, document );
