/* ---------------------------------------------------------------------
 * file.input.js
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
 * A classe que fornece uma maneira simples de formatar campos do tipo
 * file, permitindo a pré-visualização dos arquivos de imagens
 * selecionados usando jquery e semantic.
 * --------------------------------------------------------------------
 * Controle de Modificações
 *
 * 2019-01-08 - Emerson Cavalcanti
 *   - Versão inicial
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
    pluginName = 'FileInput',
    
    // O namespace para eventos e o módulo
    eventNamespace   = '.' + pluginName,
    moduleNamespace  = "plugin_" + pluginName,

    // Os seletores
    selector       = {
      button       : 'button',
      clear        : 'i.close',
      preview      : 'div.ui.cards'
    }
  ;

  // O construtor do plugin cria uma nova instância do plugin para o
  // nó(s) DOM a partir do qual o plugin foi chamado.
  function FileInput ( element, options ) {
    var
      settings  = ( $.isPlainObject(options) )
        ? $.extend(true, {}, $.fn.fileinput.defaults, options)
        : $.extend({}, $.fn.fileinput.defaults),

      $module,

      // O input de entrada
      $input            = $(element),

      // O input fake com aparência modificada
      $fakeInput,

      // O botão de seleção
      $button,

      // O botão de limpeza
      $clear,

      // A área de pré-visualização
      $preview,

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
            $wrap          = module.create.element.div("ui action input"),
            inputAttr      = {
              name         : '_' + $input.attr("name"),
              placeholder  : settings.placeholder,
              readonly     : 'readonly'
            },
            $stylishInput  = module.create.element.div("ui icon input"),
            $choosedInput  = module.create.element.input(inputAttr),
            $clearIcon     = module.create.element.icon("close"),
            $selectButton  = module.create.element.button("ui primary right labeled", "file", "Selecionar")
          ;

          // Primeiramente, criamos um wrap ao redor do input para
          // conter todo nosso componente e deixamos ele invisível
          $input
            .hide()
            .wrap($wrap)
          ;

          // Acrescentamos os demais itens do nosso componente
          $module = $input.closest('div.action.input');
          $clearIcon
            .css('pointer-events', 'visible')
          ;
          $stylishInput
            .append($choosedInput)
            .append($clearIcon)
          ;
          $module
            .prepend($selectButton)
            .prepend($stylishInput)
          ;

          // Verificamos se precisamos adicionar o preview do(s)
          // arquivo(s) selecionado(s)
          if (settings.preview) {
            var
              $previewWrap = module.create.element.div("ui preview basic segment"),
              $cards       = module.create.element.div("ui eight cards")
            ;

            $previewWrap
              .append($cards)
            ;
            $module
              .after($previewWrap);
          }

          // Localizamos as partes de nosso componente para acelerar
          $fakeInput = $module.find('input[name="_' + $input.attr("name") + '"]');
          $button    = $module.find(selector.button);
          $clear     = $module.find(selector.clear);
          $preview   = $module.next().children(selector.preview);

          // Deixa a pré-visualização inicialmente limpa
          module.set.preview.empty(true);
        },
        element: {
          button: function(classes, icon, label) {
            var
              $newButton = $("<button>")
                .addClass(classes)
                .addClass("icon")
                .addClass("button")
              ;

            $newButton
              .html('<i class="' + icon + ' icon"></i>' + label)
            ;

            return $newButton;
          },
          icon: function(classes) {
            return $("<i>")
                    .addClass(classes)
                    .addClass("icon");
          },
          input: function(attr) {
            var
              $newInput = $("<input>")
            ;
            for (var name in attr) {
              $newInput
                .attr(name, attr[name]);
            };

            return $newInput;
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
          module.bind.inputEvents();
          module.bind.mouseEvents();
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

          $module
            .on('click'     + eventNamespace, selector.button, module.event.select)
            .on('click'     + eventNamespace, selector.clear, module.event.clear)
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
        change: function(event) {
          var
            // A flag indicativa de aceitação de múltiplos arquivos
            multiple = $input.attr("multiple"),

            // Os arquivos selecionados
            files = $input[0].files,

            // O rótulo indicativo do que foi selecionado a ser exibido
            // no input estilizado
            label = ''
          ;

          module.debug('Alterada seleção de arquivos');

          // Quando a entrada de arquivos original sofrer modificações,
          // recuperamos seu valor e mostramos na nossa entrada "bonita"
          switch (files.length) {
            case 0:
              // Limpamos quaisquer pré-visualizações anteriores
              module.verbose('Nenhum arquivo selecionado');
              module.set.preview.empty();

              break;
            case 1:
              // Como temos apenas um arquivo selecionado, então exibimos
              // o próprio nome do arquivo
              module.verbose('Selecionado apenas um arquivo');
              var
                path = $input.val().split('\\')
              ;
              
              label = path[path.length - 1];

              break;
            default:
              // Quando selecionado vários arquivos, exibe o número de
              // arquivos selecionados em vez dos nomes destes arquivos
              module.verbose('Selecionados', files.length, 'arquivos');
              label = files.length + ' ' + settings.contentName + ' selecionados';

          }

          // Coloca o resultado da seleção para o usuário
          $fakeInput.val(label);

          // Atualiza a pré-visualização
          module.set.preview.files(files);
        },
        clear: function(event) {
          // Limpa a entrada
          module.debug('Limpada a seleção de arquivos');
          event
            .preventDefault()
          ;
          $input
            .val('')
            .change()
          ;
        },
        select: function(event) {
          // Força o input file original a iniciar a seleção
          module.debug('Solicitada seleção de arquivos');
          event
            .preventDefault()
          ;
          $input
            .click()
          ;
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
        preview: {
          image: function(file) {
            // Conforme o tipo do arquivo, realizamos a correta
            // renderização do mesmo
            switch (file.type) {
              case "image/png":
              case "image/jpeg":
              case "image/svg+xml":
                // Formatos de imagens conhecidos, então fazemos o preview
                // da imagem do arquivo
                var
                  reader = new FileReader()
                ;
                module.verbose('Renderizando arquivo de imagem', file.name, 'do tipo', file.type);

                reader.onload = function(event){
                  module.set.preview.image(file.name, event.target.result);
                }
                reader.readAsDataURL(file);

                break;
              case "application/msword":
                // Documento do Word, então exibimos um ícone
                module.verbose('Renderizando um ícone do tipo', file.type, 'para o arquivo', file.name);
                module.set.preview.image(file.name, '/images/mimetypes/doc.png');

                break;
              case "application/vnd.ms-excel":
              case "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet":
                // Documento do Excel, então exibimos um ícone
                module.verbose('Renderizando um ícone do tipo', file.type, 'para o arquivo', file.name);
                module.set.preview.image(file.name, '/images/mimetypes/xls.png');

                break;
              case "application/vnd.oasis.opendocument.text":
                // Documento do Word, então exibimos um ícone
                module.verbose('Renderizando um ícone do tipo', file.type, 'para o arquivo', file.name);
                module.set.preview.image(file.name, '/images/mimetypes/odt.png');

                break;
              case "application/vnd.oasis.opendocument.spreadsheet":
                // Documento do Excel, então exibimos um ícone
                module.verbose('Renderizando um ícone do tipo', file.type, 'para o arquivo', file.name);
                module.set.preview.image(file.name, '/images/mimetypes/ods.png');

                break;
              case "application/zip":
                // Documento compactado, então exibimos um ícone
                module.verbose('Renderizando um ícone do tipo', file.type, 'para o arquivo', file.name);
                module.set.preview.image(file.name, '/images/mimetypes/zip.png');

                break;
              case "application/x-rar-compressed":
                // Documento compactado, então exibimos um ícone
                module.verbose('Renderizando um ícone do tipo', file.type, 'para o arquivo', file.name);
                module.set.preview.image(file.name, '/images/mimetypes/rar.png');

                break;
              case "application/pdf":
                // Documento PDF, então exibimos um ícone
                module.verbose('Renderizando um ícone do tipo', file.type, 'para o arquivo', file.name);
                module.set.preview.image(file.name, '/images/mimetypes/pdf.png');

                break;
              default:
                // Outro tipo não conhecido, então exibimos um ícone
                module.verbose('Renderizando um ícone do tipo', file.type, 'para o arquivo', file.name);
                module.set.preview.image(file.name, '/images/mimetypes/file.png');

                break;
            }
          }
        }
      },
      set: {
        preview: {
          // Limpa o conteúdo da pré-visualização e acrescenta a
          // informação de "Nenhum conteúdo"
          empty: function() {
            if (settings.preview) {
              var
                $noContent   = $('<p>').text(settings.noContentLabel)
              ;

              $preview
                .empty()
                .append($noContent)
              ;
            }
          },
          // Limpa o conteúdo da pré-visualização e renderiza os arquivos
          // selecionados
          files: function(files) {
            // Verifica se precisamos fazer o preview
            if (settings.preview) {
              if (files.length > 0) {
                module.verbose('Atualizando a pré-visualização');

                // Inicialmente, limpamos o conteúdo da pré-visualização
                $preview
                  .empty()
                ;

                // Para cada arquivo selecionado, adiciona uma
                // pre-visualização, se possível, ou um ícone para
                // representar o arquivo
                $.each(files, function(index, file) {
                  module.get.preview.image(file);
                });
              } else {
                module.verbose('Limpando a pré-visualização');
                module.set.preview.empty();
              }
            }
          },
          image: function(name, imageDataUrl) {
            // Adiciona uma nova imagem de renderização
            $preview
              .append(''
                + '<div class="blue card">'
                +   '<div class="thumb" title="'+ name +'" '
                +         'style="background-image: url('+ imageDataUrl +')">'
                +     name
                +   '</div>'
                + '</div>'
              )
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
  $.fn.fileinput = function ( options ) {
    this.each(function() {
      if ( !$.data( this, "plugin_" + pluginName ) ) {
        /* Usamos "$.data" para salvar cada instância do plugin no caso
         * do usuário quer modificá-lo. Usando "$.data" desta maneira,
         * garantimos que os dados sejam removidos quando o(s) elemento(s)
         * DOM for removido através de métodos jQuery, assim como quando
         * o usuário deixar a página. É uma maneira inteligente de evitar
         * vazamentos de memória.
         */
        $.data( this, moduleNamespace, new FileInput( this, options ) );
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
  $.fn.fileinput.defaults = {
    debug                 : false,
    verbose               : false,

    // Especifica uma breve dica que descreve o tipo de arquivo esperado
    // de um elemento <input> do tipo "file"
    placeholder: 'Selecione os arquivos...',

    // Especifica se devemos apresentar uma prévia do(s) arquivo(s)
    // selecionado(s)
    preview: false,

    // O nome do tipo de conteúdo sendo selecionado (default: arquivos)
    contentName: 'arquivos',

    // A mensagem exibida no preview quando não houverem arquivos
    // selecionados
    noContentLabel: 'Nenhum arquivo selecionado para visualizar'
  };
})( jQuery, window, document );
