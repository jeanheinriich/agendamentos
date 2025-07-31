/* ---------------------------------------------------------------------
 * logo.input.js
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
 * file, permitindo a pré-visualização do arquivo de imagem
 * selecionado usando jquery e semantic.
 * --------------------------------------------------------------------
 * Controle de Modificações
 *
 * 2020-03-27 - Emerson Cavalcanti
 *   - Versão inicial
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
      preview      : 'img'
    }
  ;

  // O construtor do plugin cria uma nova instância do plugin para o
  // nó(s) DOM a partir do qual o plugin foi chamado.
  function LogoInput ( element, options ) {
    var
      settings  = ( $.isPlainObject(options) )
        ? $.extend(true, {}, $.fn.logoinput.defaults, options)
        : $.extend({}, $.fn.logoinput.defaults),

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
            // Os controles da parte inferior de nosso card
            $wrap          = module.create.element.div("extra content input"),
            inputAttr      = {
              name         : '_' + $input.attr("name"),
              placeholder  : settings.placeholder,
              readonly     : 'readonly'
            },
            $choosedInput  = module.create.element.input(inputAttr),
            $selectButton  = module.create.element.button("ui icon button", "file", ""),

            // O nosso card
            $card
          ;

          // Primeiramente, criamos um wrap ao redor do input para
          // conter todo nosso componente e deixamos ele invisível
          $input
            .hide()
            .removeAttr('multiple')
            .wrap($wrap)
          ;

          // Acrescentamos os demais itens do nosso componente
          $module = $input.closest('div.extra.content.input');
          $module
            .prepend($selectButton)
            .prepend($choosedInput)
          ;

          // Localizamos as partes de nosso componente para acelerar
          $fakeInput = $module.find('input[name="_' + $input.attr("name") + '"]');
          $button    = $module.find(selector.button);

          // Localizamos as partes externas
          $card      = $module.closest('div.ui.preview.card');
          $clear     = $card.find(selector.clear);
          $preview   = $card.find(selector.preview);
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
          ;
          $clear
            .on('click'     + eventNamespace, module.event.clear)
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
          $clear
            .off(eventNamespace)
          ;
        }
      },

      // ==================================================[ Eventos ]==

      event: {
        change: function(event) {
          var
            // O arquivo selecionado
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
            default:
              // Como temos apenas um arquivo selecionado, então exibimos
              // o próprio nome do arquivo
              module.verbose('Selecionado apenas um arquivo');
              var
                path = $input.val().split('\\')
              ;
              
              label = path[path.length - 1];

              break;
          }

          // Coloca o resultado da seleção para o usuário
          $fakeInput.val(label);

          // Atualiza a pré-visualização
          module.set.preview.file(files);
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
        scale: function(srcwidth, srcheight, targetwidth, targetheight) {
          var
            result = {
              width: 0,
              height: 0,
              left: 0,
              top: 0
            }
          ;

          if ((srcwidth <= 0) || (srcheight <= 0) ||
              (targetwidth <= 0) || (targetheight <= 0)) {
            return result;
          }

          var
            // Calcula os valores para escalar a imagem para a largura
            // final
            scaleX1 = targetwidth,
            scaleY1 = (srcheight * targetwidth) / srcwidth,

            // Calcula os valores para escalar a imagem para a altura
            // final
            scaleX2 = (srcwidth * targetheight) / srcheight,
            scaleY2 = targetheight,

            // A flag indicativa de que devemos escalar pela largura
            fScaleOnWidth
          ;

          // Agora descobrimos qual devemos usar, em função dos valores
          // calculados
          fScaleOnWidth = (scaleX2 > targetwidth);

          if (fScaleOnWidth) {
            // Escalamos pela largura
            result.width = Math.floor(scaleX1);
            result.height = Math.floor(scaleY1);
          } else {
            // Escalamos pela altura
            result.width = Math.floor(scaleX2);
            result.height = Math.floor(scaleY2);
          }

          // Centralizamos a imagem no destino
          result.left = Math.floor((targetwidth - result.width) / 2);
          result.top = Math.floor((targetheight - result.height) / 2);

          return result;
        }
      },

      set: {
        preview: {
          // Limpa o conteúdo da pré-visualização e acrescenta a
          // informação de "Nenhum conteúdo"
          empty: function() {
            $preview
              .attr('src', settings.emptyImage)
            ;
          },
          // Limpa o conteúdo da pré-visualização e renderiza o arquivo
          // selecionado
          file: function(files) {
            // Verifica se precisamos fazer o preview
            if (files.length == 1) {
              var
                file = files[0],
                reader = new FileReader()
              ;

              module.verbose('Atualizando a pré-visualização');

              // Fazemos a pré-visualização do arquivo selecionado
              reader.onload = module.set.preview.image;
              reader.readAsDataURL(file);
            } else {
              module.verbose('Limpando a pré-visualização');
              module.set.preview.empty();
            }
          },
          image: function(event) {
            var
              // Criamos uma imagem na qual iremos fazer o
              // redimensionamento
              img    = document.createElement("img")
            ;

            // Criamos o evento onde redimensionamos a imagem
            img.onload = function() {
              var
                // Criamos um canvas para redesenhar a imagem com o
                // tamanho final desejado
                canvas = document.createElement("canvas"),

                result, ctx
              ;

              // Calcula o novo tamanho e as compensações
              result = module.get.scale(this.width, this.height,
                settings.size.width, settings.size.height, true);

              module.verbose('A imagem final terá ', settings.size.width,
                'por', settings.size.height);
              module.verbose('Redimensionaremos a imagem usando:', result);

              // Ajustamos o tamanho do nosso canvas
              canvas.width  = settings.size.width;
              canvas.height = settings.size.height;
              ctx           = canvas.getContext("2d");

              // Desenhamos a imagem redimensionada na posição final
              ctx.drawImage(img, result.left, result.top, result.width,
                result.height);

              // Por último, atualizamos nossa imagem de preview
              $preview
                .attr('src', canvas.toDataURL("image/png"))
              ;
            };

            // Carregamos o conteúdo do arquivo de imagem
            img.src = event.target.result;
          }
        }
      }
    };

    // Inicializa nosso plugin
    module.initialize();
  };

  // Criamos um wrapper de plug-in leve em torno do construtor "Plugin",
  // prevenindo contra instanciações múltiplas.
  $.fn.logoinput = function ( options ) {
    this.each(function() {
      if ( !$.data( this, "plugin_" + pluginName ) ) {
        /* Usamos "$.data" para salvar cada instância do plugin no caso
         * do usuário quer modificá-lo. Usando "$.data" desta maneira,
         * garantimos que os dados sejam removidos quando o(s) elemento(s)
         * DOM for removido através de métodos jQuery, assim como quando
         * o usuário deixar a página. É uma maneira inteligente de evitar
         * vazamentos de memória.
         */
        $.data( this, moduleNamespace, new LogoInput( this, options ) );
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
  $.fn.logoinput.defaults = {
    debug                 : false,
    verbose               : false,
    size                  : {
      width: 255,
      height: 255
    },

    // Especifica uma breve dica que descreve o tipo de arquivo esperado
    // de um elemento <input> do tipo "file"
    placeholder: 'Selecione o logotipo...',

    // O nome do tipo de conteúdo sendo selecionado (default: logotipo)
    contentName: 'logotipo',

    // O conteúdo da imagem vazia (quando nenhum arquivo for selecionado)
    emptyImage: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAMEAAADACAYAAAC9Hgc5AAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAABsQAAAbEBYZgoDgAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAACAASURBVHic7Z15fFTV+f8/z7mThbCKQAIGCJmwKNalIQmI1iiQmQlCtZraWqGQhGjdl9pWf61N1bYu/VqX1gWSQLHa1lg3JJME1LTKlkBdcc2EsCeAyp5t7nl+f8wEZiYzmTsz984k6vv1csmdc889ydzn3nOe8zyfh/At+pCZGTfSNDzNJIWZCRlMnCyYUpgxiggjAIxkkALwUAACgAJgiEcPX3n8/0EA7WC0MtFuIm4h5t0S1CqYdxGL1qSD1NzYaO+I4m/4tYViPYD+R6kYmbPOrLByDrE8lwlnA3Q6gHFw3djRQgXQDOBjgD9m0LuKpC17Nk//HCiVURxHv+dbIwhCWm5uYsex+GwGXUjABUyYAWBQrMfVC4cBbAHjLQbeQgdvaH2/9lisB9WX+dYI/JA8Le9MEpQPcD5A0wEkxHpMEdAFYBMDdkWges/GmncAcKwH1Zf41ggAoKBASd5+6EICXQEgH8D4WA/JQFrA+Ldg+Y89m9esx7cG8c02gpQsy1QQFoCxAIQxsR5PDNhJxC8RROWeTdVvx3owseIbZwSnZc86VWXTIhCWAJgc6/H0IbYy8Jyiqn/fs2XtjlgPJpp8Y4xgzLQ5M1WFriWmKwAkxno8fRgnAZUk8Kc9G2v+F+vBRIOvuRGUiuSs9XOJ6E4AM2I9mv4GE62DlA+0NtS+hq/x2uFraQRTpxbEHxh06GoC3QHGlFiP52vAR0z8fyOODv371q2VnbEejN58zYygVKRkb7wc4D8CMMd6NF9DdhDx7/eOG1qOyko11oPRi6+NESRnWWaD8BAB58R6LN8APgKotKW++gV8DaZJ/d4IRmflfY+JHgSQE+uxfNMgYD2AX+2tr3kr1mOJhH5rBCOz8lMUUh8EcDX68e/xNeE1ZvXm1oa1TbEeSDhEM+BLJ0pFSnbCAkH8CoDp+NYA+gKTiKhk0GkT41IGTNzw5ZeN/Wq90K9uoNHZc6YxxFIA58Z6LN8SkA+ZxJLWTfaNsR6IVvqHEeTmmlKOJ9wO4B4A8bEezrcERRLxXwZ+ofyiP+Q89HkjGD199uksTSsBnhbrsXxLyLwvgYX76mvei/VAeqMvrwkoOcdyM5iex9c7qvPrTDIBiwedlnHw6G5HfawHE4g++SYYMXP+YFNnx3IQLo/1WL5FHwh4JS7etHjH26u/Ct46uvQ5I0jJsZ0Bli/i2wjPryPbFJY/2N2w5t1YD8STPmUEKdnWAoAr0LfTF0PhCICdDNpFxHvA2AeA2RWpecS3MQODCTABIDBGunMcUgGMhXdSfn/mODMXtjbU/ivWA+mmrxgBjc6x/oGZf4m+M6ZQ2A5QA4g/ZvBHzPSxk8S2LzfZD+t1gREz5w8Wzs4JJOXpJGgqmE53OwvS9LpGFGEiemDvpun/ry+IAsT8hps6tSD+wMAjFQT+SazHohFm4D1iWitIruti08b9DVUtsRrMqJxZyQTTdGKcB8ZsEM6BS9Kl70N4MXFAx0+a6+raYzuMGHJK5uyhCYrpRYAvjuU4NHAUhNXMeBUmXtu6vnZfrAcUiJRzbSM5Xp0lmOYxcAn6+DSKidbFCZ6/a0PNl7EaQ8yMIHXGJac51a4qAGfFagy9w20M8SKIKwcM6KiJ9dMqHDIybAlHh8vZAH4I4HIAA2M8JP8QPhFO1RKrtM6YGEFydt4EAr2Jvun/30CM5e1Sff6rLWsPxXowejFi5vzBirO9gJgWAzg/1uPxw3apiln7ttgd0b5w1I1gTObscVJR6gBMiPa1e6ETwCtM4uH+FPMSLqOyLWcT03VEfDWApFiPx4MWljyndXPth9G8aFSNoA++AY4C+KvKyiOxXNzGiuTz8kbBSTcRcCP6ztphHxgXtzTUbI3WBaNmBCnTrWmQXIe+YQBHGHjMBOefd9e//kWsBxNrxp0/95TOTvVmBt9MwLBYjwfAXlXB9/ZvqGmMxsWiYgTuBJj1iP0UyAnipehUSlvese+P8Vj6HKdlzzpVknI3M/0MQFyMh7NDqOoF0VgsG24E7jigOhC+a/S1grBaqPzzPVtqP4nxOPo8I3PmTDSxeIiB78dyHAR8zl1iptEPLGONIDMzLkUZsQqAxdDr9AZjDwh3ttTXrIzWJXNzS017Bu4YpwLjmUWaAMYzYwKA8QCfCmAoCBsdAw//2Ee1gcy2wqcAZIBpNwO7iLAHhEZVqp80V6/Yjigmtidn511CoMcR213pBm7ni4xU1jbSCCgl27oc4J8aeI3ekAx6VCa1372/ru6okRdKn1cyTqjO6czIBigHwHfRu9dlgymOLZ++WuEVP5RhK7yLQb/v5byjDGwl4k0M2hTnpA2f1pZt0+N3CMSYzHlJUnT9DsS3Ikah9wx+qbX+vCuMCrEwzAhSsiy/A+Fuo/oPwjZiXrS3ofa/RnQ+Zl5JUqLqtAF0KTFmA0gJ4fTN3KXMblq71GsPwmwt+hEIzyH076QZ4Fom1KLTtNa3X70YM23OTCnE3xAjPScG/bm1vvo2I/o2xAiScyzfJ8ZLRvUfhApnXMItB9a92iNKMxJSLUXDEwRfAtBlAFkAHhBqHwy82ykxa1dNuVeIQLq18HwiWoPINVJVABuZUClYPtdoX67rXHpkbu4g0ZbwMDGW6NmvVoj56r0Ntc/q3q/eHZ6WnT9JhVoPYKjeffcOtzHoptb6mjJDui8tFeMaWocCwICurmFOYgUmGkpSHcSgJGIaBIGhzDQU4LEgjIVrHXA6gEQG/isT1O83v7zioGe3aXklUxRFfRvAqTqPuAPAS8woa5o+9k2U6jeVGJ2V9xMmegrRD3lvFwIz9RYK1tUIRsycP9jU1bERwBl69quBj0CioGWT/aMoXzc4BQVKetvA9FP2xjdv2bK0y/Mjc97isVDEOrjyBYykiQmPDTieUL617gld1keu3G/lBUT9u6am+Hhlmp4ZanoaAaVkWSqjnxLJVZ2k/Fin2H1CFL0vZlvhQoBuBJCJ6EwdvyLip8H8sB5TpeE5tiHxkP8Ew6bH4DRDsLdsmnGJXgtl3f7wyVnWm4j4Ub360wIxHt2bNuT2SMVh02eXDKU450KVZGVz1Yqoh0+kzysZB1W9jJguA/h8GO+FOULAn2WX8nDEC+mCAiW5+fCfiHCLTmPTBvEdLZtq/6RLV3p04i57tBnRK34hmenW1obqxyLsh8zWooUs8FtIcWVT9bKGQA0nWAsnC4h7INj1N2NIgA8x01EiPsagYyDaJyS2OROd7/rO/bUy6ZKFp0nV9FMG/RTApPB+Lc18wcS/bsoetzTSNUNydt6NBHoE0Uvo6VBYTtcjX1kfI8i2PAHgZ3r0pYEuYl4cqZdggrX4LEH8VwAziOjyxqqyV3prn5lZEndwlLodwOheO2Y8k9iecJ2/uXfapYuGhWAclG4tnEmgEhB+BOPCGJyS+cxt1RWfRtpRcrblKgJWIFohF4zlLQ01hZF2o9d0iJKz8n5IRA/B2EVeO4gub9lUXRVuB2flLRh43JRwBzPfCSCeiG5orCr7q2ebMfNKkgY41esc9nKv122Greh3jIB7H3uY+Kamqop/+36Qm1tq2jVg530M/ALAZ8y8lojWqglqnRajSJ9XMo5U5+3MVET6J8b83mEv/7VenaXkWPPB/G8YOCtg8G7B4ld7G6qfhQ5rOF0XY6kzCgY41cM3Afg19HefdTL48tb62tfC7SDduiQLQv6LuDuQj/7gsJf9P882LgNwrgLoIoI8s9G+/ITHadIlC09T1TgHvOsadwH8WGJbYqm/p/+E/KLxgvEsgJl+hqQC2AjgnwT5r2CL1UnzSkZIp3ojA7cCGKzx1+6NDwlJ0xrtj5+QSjwrb8HA4yLurMbqig3hdjomZ06eZPEK9DeETiJ+qsuU+Gs994EM8Ui4Uyf/CP1k07tY0BWtG6tfDfN8Ss8vuokYD+Kkluk/Hfbyq+DxJMnNLTXtHLDzVcDt7WCUOarLvTaGzPlFT4FxjatXWksq3dJYs8xv7Ht6fuHlxLQMwCkaxqiC6E2wfCaxLfHF3lyZU2YtPLUrwXQ3mK6DS6IlHJxMPKOpqmKz58EMW2EFgxYy48FT9iu/9XXramV0lsXKhJehXyF0w+TfDXXLjc7K+54kejTC6jESoB+11FdXhnPypHklI1Sn+je4inR38w4haYbnExAA0q2FfyGi6z0OdTilMmF7zdK93QfSrIvSFBLlksT926rK1vi75tTc6wZ1DGh/jEGLwxkzgKNMWGki/r/PVlcE/NInWAsnC0H3glEQ6gUIuKfRXv5bz2Pp+UsuIZarPA5tZpNyedOqpWGFM6fk5F0Bpn8hssXyh8x0S2tD9esR9NErUfBNl4qU7A1XA3gIwKhQzybCrXs31TwSzpUzrIUzmOh5uASsujnKJDKbqpZ95tnWbCv6uXuMviPoMWXq9Zq2onkMPAZ9Ii8lgaskK/f05rky2wptAD0JzQlL9L/EQYdmbK08WYTPFRaCD9Fz4d8iCFd+XlUeVhzW6GzLtQw8GcapXzHhd60DOv6KujpnONfWStRie9LOyR3WEZdQyoTroNl7wA+11Nf+IpzrmW1FlwL0XI8YH8YiR3X53zwPpVuLf0DElfD/xNrjGHR4XLC9iAnWwslE9BgBeeGMNwgMcK2U4vfbasr8lkYaM68kKcmp3s3Az9H7PsNRyTzN1xuUYSv+OwfWfnICdL3DXrY0nMGnZFvuB/BLjc2dYDytkPO30cr6i3qAmzu26GEAc3ttyHi1pWHGZeHsCpqtxTeB+M/wvamZnndUl13pecjlKsVGH2NRCXgDhOfbVbzoG/DmicvbFPdrZroNUaidwOAXJMs7mqtXNPv7PMNaOAMCFczkt3QtEV/ZWFXxvNc5+cXfZ+aXg12agHt9p1AaoZRsyz/hkn7pjTcl0S37NlW/H8Y1wiZmukPJWZbZRHgU/mNPPu1Q1ZyQJU9KS0X6xp0PEcFfyG0Tof3cRvuzJ8Ir3K7QBvcYVDD+y4KfF8z/DhpWUFoqzJt2XA3Q/Qi2d6A71EbgxxLaEu7zt4B2rUk6nmbgKs/jTHiiqarcc82D8ZaS0SbhfBcgTVNVZjzcVF1+e6gjHpmbO0g5Fr8eRN/x8/FOAL+OZuKTJzGrT3Bsj6MpZcCk8s4BfASu2mPdXoRDQtKs/Vtqd4fYJaUnmf9CwE1+PpOCcFmj/W9e64BR5nMXA+wkiPu7RPzPmu1Ln/7q83c2f9n47vFgF5uKMwY649Q1AEaEOE49iANwvjNOXXBqxnd3fdn4jlfg4P7mhs4vG995cfjEc7cBwupu/37noMM/PPzRRyfn16WlYsSegy8Bfm9MvxBhxqkTM4d92fi/mlAGfLy5uTNp9KRaEvwTnEw4OkbgexKTOq/ate71LaH0pycx1yIFgDGZ80awqeM3zFQiiefv21Tr1+vSC5SeX/QXYlzn57NmBu5tspdXRDLGzMySOF93YXp+0cPEuDWSfvWAwS84RULJjtVP9oisNM8tzoTkv0HiUkdNuZd6g9lWdB8AzYt+Twj0SKO9LOTffXS25QIG1wD0GpmUO/aur9oezvX1pE8YQTcp59pGhpVUXVCgmI8POZ2cMl6YyLUDK0yHEzva2t6vfSbs3NS0SxcNU9rFpUz0AwIuNDn59E/XVOw58bl1UZpCSiP6RsWfHVLS1X4XzqWlwjc2yGwrvhjgWkQw9nANIezv2SD6lBH0BSbML04WnWwFcBkIVnht9vR0l5qtRS+DYqvK4IFKwJ+G7lN+09sml2vn2/Q/reuA3uF7HfaKWKXR6sI33ggmzykc41SQA6LpAC6GK0k+0ObOgY5Bh8ftqqxs6z7gfqIatpETFoS3nKpypecmXzdTCwri244O+S8BObpdj3mxo7pihW79RZlvmhG4IjOJphMwnV03QmrQs7y7uMbXX262Fb8PsObFZXSgpxz2Mn+RvWS2FT4EUMgenl7oIqa8xuqyOh37jBr90ggm5BeNV5h+z5DHmMgpuGfpI0nc4BvRabYV3ghQpDkIHzns5WfCI+YoI7+omBnLIuxXR+ilYfvElb1Nicy2oofg2ljTCd5HEJmN9rJd+vUZHfqdEeTmlpp2Ju74D4jO67WhEDMdq5et7/5x3NyfnRInOz+HDgntxMLaWL3shIswtaBgQMLRITsQG3epN0zPD9svrtYQ+EYZtqK/sr55IBsTBx2+0DMcoz/QP8r6eLBzwI67gxoA401PAwCAOO66EzopOjCpXh6RXZWVbX3hTUDAc2PbU3/Sw5VrLZplthXd4NOcG3PG3gCQnhtU09uPDulNPKxP0q+MwDx3yXkA3RWsHYO8kmEmzSsZAWYdn3hkSc9f5LUGiFP5LwDCCjvWB17amDN2QV1dqVew2cT8ou+B8AqAx8zWYu+NxNJSOWyfKAbRWh0HcpvLWdB/CGgEo8/LHz8qx9pnSilNzb1uEKR8BsH92h83VZfZPQ84u9Q7oXOSD7HwerJ+uqZiD5he0vMaWmHCEw57xbW+ewHplsLZkqnanY1GIH4kPb/YK7x7y5alXWq8swDAxzoNRwD8t/TZJVHWnQpMSo7tjDGZs8cF+jzwm0CV1wrm/6VkW54ckzkv5nPdtqSOewCkB2/Jf4bHojXt0kXDiFCi/4jEgknzSrz/LgpFVW0DAMC4zx0P5JVmOCG/eA4J8apPYCAR8zKztfAyz7bNL684SFDmEaBX8bxUilcf0Kmv8AcxwzI8Jdv6GFi+JxXhOx08QSAjIGa+Cq6n7rWq0vl5SrbllxkZNr2yhEIiPb9wGrHfmCBf9ncMOvJ3zwNKh1ICQ5TSeIB0Or2My7F62Xoi1Ot/Lb84GXSdo7r8N74fZNgK8wTjlQBSkQpIPJtuLfSqW9ZoX+qQTAugl+4SoyRm06LcXNPonLwSp4pPAL4RgImBq1BQ4HcW4dcIRmflXQDgxOvDXb3k/mPD5QeuqvNRpLRUENNT0LC9z4xnPDeycnNLTQCu7+WUiGDQ9VMLCnzDp/9i1PU8OALw/CZ7WY9kFbO18DIGBTIANzyAiF4y5y32EkVoqi6rIuBBncZIAD/m/g6iRnJ23sUpxxO2MNPTAEaeHAydlrLzyAX+zvFrBCxwpd/jwESAnx+dY1mTPC3vTF1GHYT0TTsXwaXQFhRS6DnPn3ck7bbCw5gNYEzHkaFeMfIJAw//C4BhAl5M2EaQ0x32CrvvZ2Zb4UK4Mum0JLiPIJN4wdeIU9vG/hrAOp2GO3VH0o5infrqlZEzLBkp2ZbnCfQ6ApQFJuk/aainERQUKGDqVUqRGbNJ0DvJWdanjVwvTM29bhAB92ls3uhYXeYVjkuQ4eb4aoZJek3TtlZWdjLzU4ZcjPAWOeOmeypgdJORX3QnQCsQQuI9M7Lbjw32Simtqyt1mlRaAFdRw4ghpnuMXCQPz7ENScnOe1BRsRXoPddaAlf4m9L3MIIxOw7OApCs4fomIi6RSuenyTmWm5Gbq/trr31A563QmLBCRF5iXBm2xSPBmKf3mPxcOWti/mKvOByS8U8C0LP4N4P4sWGtyixH7VP7PD+YWlAQn2ErrGDGHxDO5ifTTem2Qq8n5Ke1ZduINadDBmMkxal36NSXB6UiOSevMJ7lZwDdAQ1ZfQQMO3YK98ho7GEEUoofhTia4cR4JKUt4YOUaXm6CbOmzy4ZSmDt+paseqUMMosfIEpKaFIKr3WH60al5wO1Dw3eRyxsjqqKm303wSbNKxnRfmzI2ghULQAABHoyPX+Jl+RjY3X5kwC/EUm/Hlw3Zl6JbvWSU7KsWSnZG94mpnJoe2CfgAX3eDB6G0FBgQLCpWGNjDEFgqqSsy0vjcq0RV7NxOS8hYHhGlt/7DtFoGiqYxOunDC/ONn7EIelkOHdL61VSZ7tGaLRTYZl8XedqloPht/FXogMJqkuR2mp5/3ATMrP4KpzECmnJHaqF0XayYiZc8YkZ1mfBvFGADPC6oRhhc8b08sIUrYdzoQ2oaiAEHCpUOTHo3PyHj0lc3ZYc8Ex80qSiOhG7Rdlr0y0VEvRcAZyw7l2mMSLLngtABvt5e8g/AXmQSbc4shOtfhTyTbbCheyEG+dVNLTAaLzzBt3egmNuWRp6P906Pw/TUMOV4d79tSpBfHJOZabTV3iYyIuQWSRDiljptm8dLC8OmOFZkXQuSdxzHRTgqJ8kpyTV4gQ56pJqlyMEOJ8SMIrnj9B0BxEvQ4vX5eZWeJ1TWKEvHlG4NcI9J2mqvJHfXeAz8pbMDDDVlgB0N/Qe2HA8CDcn5a/yKv+WptJ/B5AcwS9tpic8qpw5fOTs/LmfTHwyMfEeATAkAjGcQJVSK9pu5cRELNeRtBNCjGVJ2dZtT/VS0sFM98cwjWc0mn6j+cBAhuh/ROMMYdGSa8Ms8bBh1+E9htoLxNf0WivmOcvHDnduiTrmBK/JdL5fxCGKRAPex7Ys2rpcabw8pABdAnClZ4pqaGQkmO5noheBVhDpIB2CPBvBGm5uYkA9x6dGSaC+B2tbdM37LgYwMQQum/wLTTBwJwQztcNBntvzFVWqgwEc5e2g+ghUxxPDqRobbYV/4ZIrgMwWcfh+ofpxxnWJV51p5uqyv8BUMh1wojo1nCV69wYVX5reto5ucO6fzhhBG3H488DKOSKjBo4PPzYkE1aGwsR2pOOGXWeP0/OK54A42uABSJ3grXYa6PGKeKXMuAv2Z9BqFQET3VUlf3Ct6Yx4BIG2zlg5waA70EUp3dM8s8+IQYM4pDeBkz4u6/kfVruokSzrVhzHFfLgI63AOhWm8wDU0dc4omQjhNGIFz1ePWHsHbrVm1JFmmXLhrGwA9C6V4I9qpU0iUwPZTz9UYI9pJ92bH6ya8IeM6nWR0TZzuqyn/oT3A3M7MkLt1W9EtB3ABgmpHjDcDp6T474Y6q8moAWuXaN3cOPNzjZjcNUP4K8KNply4a5u+kHtTVORkUdi2K3mAP75I4eZDCczkFvRiv1tpWdJouQ4ia9qrEe54/k5AxNQJmXO37JZMUj8IVmLaOgPkOe/lFvpLo3WRYl1gOjlI/IOB+REHWMRBCyLt9A86ISEtk6B4CXeYZwwUA6dbiXzFQCCDR1C40u68J6LWCUNgQn9jg7DYCAuFsAy7Fqok1u8aI+YrQuqe2bYOPNPocOze0PvSFgIGmdsVrStdYs2wrk5jisJef32gvX+XvvAxbcarZWrSSSVYjGnP/IDDTlPRjQ7xiyBqryl4F8GEvpx0VJOb6Luwz8ou/T8QnMs4kUTBN0hN0qM5auIqZ6E1md5SDAIDR5+WPQ4T7A/4g4P0D69Zo8gy440tCnJLxR76uN2JMDa0P/WHC9T4bT/CVgu8m7dJFwzJsRfcz+HMQFkRnhNoQ4N/4rg0Y+HOA5ioBV31etcxrepphWTKVmVfCY9ZBQO7k+YWaKu249WiNEOhNOu1Y3Jk4MTCn/K4BFwEz3tbalkzOixHi65/AXn+cyXMKx4Swy2wUe8BYkbHpi14Xshm2GxPMtuLbTR2Kg12y5dGq/KkZZppiPjLEKyit3aT8E0CPOmtMuN33LTdhfnEyC1mFnv79+K5OodmDR4xIPEwBkaTkAG4jYBgyFQKBtde9IhGyb5+ZvBaVqolDca3qBgPHCHiOWFgdgw6Pc1SX3+dbBaebzMySOLOtcAnj+GcA/6kPGG3vEF3r+eOeVUuPA/BKXHJnt3ltDKblLkoUXfwSAoSyC5KXaB6DgN+aDJEiIc8F3GG3BD7HiDLurIgQwgY4ZO8Ugb0U1hiksVKLLnwFwlowvzYgSI0xr5NGqfUEiqR8VZTh76Vfsmhi02srPj9xhNSlxIo7XZGXOqp7yDCSaYBSzr3E9zDIqnUETqmsU0j/ZQExTQaMfRO0tmysbtbScPKcwjEAMkK9AAvylm9nMjKBBgD2AvSUJMobtk9JdlSV/9Bhr1ip1QAAQIDqDByfERCc3gv9pqoVHxChnkDPOgYduQ4+KZnm/OIHfGsj+GH0pLmFmnaC9zdUtcCIRCVyOSBMU6cWxH+Bw7rfPMTaFzOqQv7KmwZFSvJadBNhjM5vtL0grmPgvwqJ/3y+uuyEIsO2Xk7KzCyJO5Qs8yXLq30l053sfFQh5Ub0DSVrTRBhcWZmiVclSwmxoCnntEaUlnnFN2XkF93JzJryByTTTABaq1G+C0Dz20Mjo4fn2IaY9iceHSsM0B+SRL250nzacjyBPoLrbaB5cayQ6jUdkuCRFL6oXgsD7xPwLhPeA8TmQB6dQGRYlkyFkAsOQl0ERjKBYJKdmwGc8K83V69oNluLXutDStZaSDk00mkDcKKEblPVss/gs41lthWXMLN28S3GDADPaGz9HvQ3AiRAnWgSJjlOJ30Bb0hqNoIme8WzAJ5FQYEy6fjg8VJVJjLJiQBNAng8XFUvT3P/tzs9rrPRvvyA1yVBgVI9GcCXRLyfIXYxsB3MOwjcTODtapzyybZXy1pD/yXdLkCSl4FQwJA9clsJuCEzs+Rhr4QYoscA7k9GACaxCB5G4IurUCI/gRAihjlALrD/tvQeGXCjSqbJJjCPM0SSlOC3wHWvVFaqn7lej00A/JYDmjJr4aky0TS0S0UcfOaizPSQAD8BiMMqy68E8GUH8GVvhfdCJTOzJO5wsjqDwTZmuowhg21spR4aKa+Eh0fFYS97IyO/6D1mY7xyxsD56bNLhvoGKwKu+sdg+S+EPsU7E66bL+jdLUh+xqz/fUqM0SYwjTPCBpSuBIf+vQKfvL7yCwB+S3s2VZfpH2dSWioyNuw+HQrnMnPeQagXgTE4lAcHE26Fr1tR8uMgKtN5tAZCVSIuoUfetDm/yAqWLyC8EI+h6fNKxmopFq4I2uY0Yt+YMNJEhHEGzIaO79my6kDwZn2PCfOLk+HEWYKRDeIZ2LTzPBY4JbI3MX/XPLfwIsfqije7j7QPPvJcwtEh96MvKFkHhR53+e+jxwAAIABJREFU5KTe4pvkAwBgrIBXNZ8Qe3bKDABBjWDXhpovU7IthwDoq1zBGGWSoHG6z7Uo+C/VTYa1+GomLgPQRGAHgxwAO5iFgyEdSYOPbNNb6vusvAUDj4i4VIIwE0kzCBnEdDoDZ6GLT+YK6/hnIYnbAJwwgl2VlW1mW9HTCLNwXpRgIrqnsaqsFD1UjtwNgPciKmDOUrNnklxFGPWdQhJGmgDWoW6VN8yk2QiYMAGuJ8npDDq9e2REDAKh/egQabYV7WHggAD2M/EBsDjA4ANN9vJ7PPuakF88R7jybgeDeRiIhoJ5GBOGkWtnNoWBlGPAQJc7jOESStP1fvf/e4LyJ1gLJ3tWkjc5+QmnSZtcSAzoZHCho6r82d4aCaINzOFn8jFp39thV71jvY1glIkM0OkU4BCmQjI1yPxaAEglIJUBV111MIRLPNbLCIj5XnTX4iIAYIC8e49hVRIhBN0K4EQYwqdrKvaY8wv/DaYfx25YPSHgSxZ8RZPH9K2b3NxSk6f8u1S5gSJwsBNhTAgDO6b7pIVxigCgKZovFDgE4alwY2fYJTfuBYHDLtcaFRg/9ZVmYeDhQM1jRKNTVWY6/BiA2Vp4x47EnV45BcRxDRFeT/v3LxG0yHqoMBAvYIRiM7FmIyBQj5tZIwm+Opqsn7S4USSKLm+ZeHdyzcYYjccLAtaoCWpWc+3STzyPu/OcnwTRg0TkpQvrFhrzDl8J7aqhhPDrbgQAEgQiWNkHglmEINhEYUuHHO8Y6vUHJJAR+ag6I29Iy13kHTYdhjSLzjCIHxu6T5nb/PIKrzDpyfMLB+9M2vkywNcCAPmr0kkRFPhgDsEIqC14m5CJFzBgmizAIQxWhm0E1Om9qGJGP3DL0igxQPEKLhu2X/l3ZE/TiDgE4Af+ZB4zbCVmZxdtAuOEficDw90Bjycg5s8RNhSCU4CNeBPECxjgGGEKRbov/GWVgEzz6gnYHm5f0YSA2+Dx8NmyZWkXQT4R9XEQ3oPENIe9/GXfz8xzCy8C1HoAp/t+5jSJKZ4/S8Knvm20w9qFnMm46VDPDZAIIWYj9vZ64HavnvyZ2ZBdagOYmmFd4uVWbJf0FIyZ8/qDAV56XFHOc9SUN/p+mJFfVAxJNQGdFsxekjaCI3qLhRJqYYQnWzGkighzKK+4SCAvIxDCtI0NycnWH3ddgxPxUbtqyr80W4ueA8Hoohb7mURhU9Wy13w/cNVjHvwIc5Aab0Spnj+qEq0ifDep9hubkGiAGXQKAPoXXqbeSgXpB/vI86W2jdkOwBmgeV/D6iuHDgXd0iyGQODXVFLP8mcAaXklUxKODtkIUFBxLGbpVTOC4pxhJ7wwoHn9SKy/EwdAh4ABr2CiEAYbwTyP4D0dcm3ikF6lSI1GELNXRUXH6vIPAehVE8CTgwBd02ivmOdP5Tojv2iBoqgN0BjaTCCvxPn4Nhm2a5pCMAIO5b7SToeAf4nAiJChWezhCC41IbWgwOutQ+BIN2+iCC/yLWVE0NldynjFKZUzHPaypb4fpRYUDDDnFz7KjJUIZb+IvDdYjw5vi+RBpr0slDHTbGOMgCBCkA/hSIzAlHh0qFcsiaT+ZAQYjHh1keeBRnv5a0Ak3pYT7AH4p47q8ku31yzd6/then7htISjQ7aASUtpXF+8jGBXZWU7whTIkgTNyUxs4HRI/1ADZs1PFQZFlkBN3pUtiUR/MgIQ4wbfCjFgisRd6gTxY6Y4nuKwV6z0/bBb55SY1sOP+1MT1CPgjxHCtMYLiX3BG524brjRBb3RKRjUI1MoYkh72VRi7i1nPSgS7KU9OqxFvI/IpljRJiN9w04vvXxTvFyOsH4HfkMyZTqqKm72p3I9MX9xzsFR6rtundPwVa7Z7wZrWAt6CuFNQK5UW705JkBSuyVqR3MZIQI1R3IhYu+yTO5dTyMWl4ZBgr2KmHz6asURAlWE0MXnxPihw14xa1t1WQ+Vj7TcRYkZtqL7JYt1AM6IdLzwG2VAYbnbiXsV7vAl8lp4PjCwWxAorATzIIwYMXO+puhUFiH9EfyRarYU+WgWUYAUkL4K5U2cW+w1NRFCPo7g8+z9TLhl2D5lamN1eaW/Bua5hReZksQ7bqlHfWRe/Cb7clh9kxCaQi5SZxQMAJAStGGo1wftFOAQ5mQhoEhnmpZ2HUmHHYjQt8+Kt3odwVcMpM9Dqiq9qtx8trqiCYwe/nw3Rwl4gLuUiU1V5Y/6xvwAwKRLFp5mthathKTXmWmKv07CH63fQLawjECYVE27/E7n4XQYkg7CuwTARrwJAJaapkRuHftPgjbsBWLyqk3rlgY3QsnYMIjopz0qvyvs6y49DqKH4jq70hrt5b/yp/wwtaAgPj2/6GZVjfvYrXJthJSIv/VKOHvGTf7WLv7w3RjVCwZ2CbDSbETnpIYgjsvQXNMsALN8pb6Z6R8R9hltBgmTWuh5wLG64k0ivAegA+ClTqlkOKrKfuFW3OhBuqVwdsexwe+5Kz3qnizlgdeN687rCNnYiEhzQpEQpPt6AAAEsFMoAoYEnZGP67LXtojYCBLULuFVbI6k+iwMCA40Eibc5FsdRoKLZRyNd9grrvHn7wcAs61optlW+DoJWqP71MfvONlrg+v4sSGjA7UN1AWAX/rWNAtyhiF1JySru8XuTfbdCCn+XzOaa21JHXZ5fQVgHbXLdwJkiKS3gaRlHBk81/NAU1XF5kDqeBm24unptqIaAG8DdLG/NkZATF6JNwpTKEagAnyNw17+YCjX7E3hOgI6Wgc6d7jzCUirKKpmGMjwLJPZGwMGH6kPUOExlCvOnTJroVcBcGKpVeeyL+AE6CUJxe/T3pMJ1uKzzPlFzzN4fURyJ+HC7KUmwmCNXhtqY+IrHfaKZaFcbniObQjC3djrbTTAJ6irc3YvZkKXTNRwjbb4eE0VcLZWVnYSWHNVmwDEdyaYvKqqHI8z/YP6ft7xVma60+Tk8Q572Q+aqpcFfCum5y/6jjm/6HlB/C4YBYiVeIbwTl4i1qQYcYBZ5vmr1RwME9QcGCEaDZdodHd9gvd6bx4eBAqh/ChFvMFFTD/z/NldVSWkp050oN0gfoyZL3DYy89sqi67P1jV93Rr8Q+IlfdievO7IZbeb4Kgsin0AZuUzKbqirAedEKSMRVJ3Xq5AgCI6N3eW4fN97Q2FCRqdbjeWea5S87z6lfpehxADz96DDgExjMEzB/blprmqKq4OZSbQpD6FvrG78EJbQN8xNUo4C40A7XcJS7QojcaEEJ22Of2hpQn3wROk2qUEVyUlpurKaLUXfUwck8Vq14x+p+9tnI3gBcj7jc8HAA9Tiysapua4qguX9hoL1/lKV7lS5p1UZrZWnRvuq3Y663WaF++H31jE7CpZ2UePwoUruNLx7WNnetvP0MzLm+ZMTW2hVoPuGuWHVi3Zk9ydt5uAp2m83WSjrfFXwBgjZbGBLzg3t4PH6YfZthKftNoX3rSoATug0QBDJhX+qAC2MjAKhK01rG6bIuWk6YWFMR3HB1iYcICMH4AQCHwnqkFBeWeOqwsUUYClxo1eE0wef1OY+aVJMGp+m5kdQB0k8NevtT3qTbeUjI6TqjTAtVz9mV086GZTHRq8JahQk37Nr3eCnjWliWKdGHqF8HaC7RJFiEvmvygSHbe6nnAsbr8QzC9oEPfvhwH4S0ADxLz901xfIrDXn5+k738AS0GkG5dkpWeX/Rw+9Ehuxh41T3f794nGNN2bIhXcfOmmvIq6JNrEAFys+dPiV08Fd4Pl+1MfL6/JJ5065Isk1DrJXFQD1g3TGRIMRMCnxA8OxH5x8zrCHSl/1Miwgrgdi0Nm6qXbTbbihoRRhE/T4hosTnv2ntc6mguWNBviPlyRBZEtpfAWyTobTCvEzSwobHKf6nWQKTllUwxmeSPmPkqQE7sLQBZADcDeM7jEDPhSfeOcGwg4WXcRDLn5E/8BtT4Hzd5/N27Sc8vuoqYywDsbKqq0PSGBAAC5hmRdC39GYEilbelMGSD9YzkrNnprQ1rtexFMFzenAeCNQxCEouuWwDc1X2gqWrZZ+b8ojIwrglyrgTQDMLHYHxMzJ+wonzc4ZSfhFvxJsOyZCoULmDmSwA1kzV+q8zIzrAVT2+0l534wgS3L2ck3gtjwyIC0WWKk14uXCbkEIMJeLAxZ9xdvjUMcnNLTbsG7LyPGb90pxyUQ2PuQUqWZSoDhtSmVgSdKC98wgj2TBj0fsr2w1/CgOLSBOUnAO7V0jaxLeGJ9gEdYwmcJkGp5CpiMRDAAIRQ9Z0I16dduuhBT1nBLoq/M447ZwDoAuE4Me1g8G6AdxGJHaoqm7lDftpct0Kzlqo/MmyLR0pWZpPgPDCsDJkSroYEg2+Gh1Zpo/3Zw2Zb8QrAOwchOtD6T18t94obImA8sbA1Vi+r8a1hMGleyYidXTufB3CR+5DTKRXNG5gEuoyNEd84sGfjjHe7FW9OJkJUVqrIsawBQ/8pEWEhgPug4Qng9jwE/IKn5l436MhAjhNq21ATxSssZMBdaXE03ut67lKqutcJS7UUDU8UmMnA+QBmMXAuEQudvr/LM2zFqe7IWAAAK87HSVWuh/ELfV96ODi6KP77O6qf7KEBm2ErOld1qi+BcCIbjIDnA8U/+YOJDXECELAGOPnG8s4GklQNYiPWBRmjsvJm7GuoXR9pRx7uudiI7xYUKOnHBp4BmKYRIxvg8wGcwcbdkHGS+Tp4Tu1eW/G52RUzZAt8mv4wyR7FFD1rNHdjthUuZOBJAJ46syxJvV/rtUZlW84GtAdhhgIzee1JeRmBClGtQHWXb9EXQbQAQMRGEE2mzFp4qpoQN1UFnwGJM4noHBzFuQCSjK9tcxIilKQWFNzrzr1ww48DFE0jONCUPe5/ve1UpM8uGUpx6tOAn9kE49Um+4oPtF6MmK4DGfI3ZpPJ5PVG63Gzp2Rb6gFkGXDxrxKTOsY019VFNN/WG3PetaMQp2aQyukS0kwk0gFOB3gSQLqXsgofLvEJPKOM/MKPohE67boaVjiqyhcH+niCpShbCPwDgP/kFyFmOlYv0/QQHDFz/mBTV8duGLP4b2ipr/HagfaXHP0CjDGCUzra4hcC6OE/DgCl24qqCRhPhEMMOgzwV5A4BOJDBOqx6yo9pkjEGER0UlFBjaM/+4YkZ9iKnmV0XQXpqgJFXiV1Yxqe4we6CUAZTg6QmemvAB6PwrU/IFYf8vtRaanIqN/5S2bcA//3EwC8rtUAAMDU2bnAV+BLR3rsRfUYNIMrCXQ/DLgLmOl2oLTMc1HSW3MCPQDw6y6Xovu7J9e//L0oyecHzzbCyWcCmAePw05Sb1dYyUO/KKOKM9MthbOaairWdh9IbEtY0T6g4z7oXdbUDQPHBOH3Q1vFn7ZsKesRtzR5TuEYZ/2uvzFjtr/z3Ugm/lVI1yVeYtQjSEjqIUPfYzHXWl+7TYd0x0BMGp29UfMOoMNe9gaBAyWbhwZjrtlWfJvnIZcuJ12JfiLiKwRu9vzZ5SRgQwqCE/g1yeqZjVXlf/SXyJ+eX3i500Tvgbk3AwCAZ9wlqTQxOivvewScE/KAtcD8wZ7N1T123P17NIj+acggADD4jlDaO1XTHdBNOZv/6Btl6rCXvRHqmGIFg+ZOsBZO9jwmiR6HvkbcCHB+o71iXnP1imZ/DdItRXPJFYYS7A16nEC/DuXiTFQaSvtQIMK//B33awQqi2dg3NNxxpgc6/laG7uKyNGfdLp2HKT816R5JV5fXpO94hEAf9PpGkZCgoSXNMu2qvLtBLyiQ9/Hieh3hKQzHfaKXnWbhOBre/u8GyJ6yHN/IxjJWdZZOLmxpjeSXMLDPfBrBPsbqloAVBs0GEiW94XSvmPQofsA6JUCmqo61ZU++p8Ytk9ZAgSq296X6KlkrUqKSMmawS9AlVMaq8pKG+29x0KlWRelsTbXbHOSs8P/YjoARPzbUNqHSO2ehtqd/j4IuMFDTMuNGw9dmJxj0bw22FVZ2UYsroN+znlb+qadXq/pLVuWdrWZlCvA3Nf3Mgb7SrNsqyl7C+GIFTCvJ8jcJntFgUuYIDgKiRsQPAiRCXzN+7XPaM4bT8m2zAVwgdb2IcMIeD8HNIK9cv8qAJq3uEOFGA8gM1OzKGxj9bIaAp7S7fpAaUZ+kVdppD2rlh5X4kzfB/ChXtcxAibc4CvNQq61gUboA2L80FFdMbPRvvw//lpkWAtnTLpkoVd+yZh5JUkECrhX4MFfG+0VoWQKEoDfhdA+VL4Y9JUIOGUMvNW/ZUsXSL+bzg+TU5QRwSI6vThuUn5OxBGp1XlAzHgqw1bsFbP/2aqlB7pE/PcAaPZoxID0jGNDL/E8MLRV+SeCl4H9FOCfOnJSzwmkXTppbmG6Ob/oeQh68rPXVnrlPSd1qdcELOZ3ks8T2xLuDP4rnGR0lvUnMChEAgCIeWljoz3gNK/XeBcJ59NAKOVYQ+a3p2TO1uzj3rNq6XEJLNBxTAqDn8mwFud6Htyx+smv1AR1DhHqdbqO7rBkL3ep2435dIDmOwG6Zmzb2DMd9oqVvuHOgCswMSO/uFSVtBWMAinpLnhMP9NyFyUy4edBhtVOwJU90y8DkzrDMpyJ/09r+zDoEkLptd5Dr0bgTj/z61bSiRHxiggpd8Dlcw6rukogEpn4FfPcYq8nUfPLKw7KTiUPRGsDnRhTCBdNsBSe7X1IfQqAZ1hKMzGuJyRNdNjLlvrLbc7MLIkz24puaB/QsY2ZfwtXuHpdU3WZV5SQSFKuAYKpSvBNjfbykPaYnE78CYBh4SkEVO7eZO/VQxU08lECD8PQiopUkpydF5J6msNethQMPTeJhkCyPcOy2EsnqWnt0kNjj6faADJyWhg2ivB+GDTal+8nVyba+0RYOLZt7MTG6vIn/Hp8CgqUjPyiBQdHqR/DFXrR7TaWTN77JpPnFw4mRu9THMKKUEW1UqZbc0FYFMo5oSIFPxasjabd6ZRs66sAzwveMmyanXEJZx1Y96omhWIAyLDdmMA4/ib0VSI4xMyX+JNCycgvupMZ90IvjX99aCfIcW4lCgCupH3P5Hw/UIat6BIQ7mXumVvBhCeaqsq99iLMtuLfA3yXb9uTPeIt4qQ5wdyrnmRk2BKODpfvwABlOY+BvdFSXz0rWCttMfCSQ/Lrh0GaqavjD6Gc0Gh/vCOus2uejgtlABhKRDXm/KIe4gCNVeV/lEQ2APv9nBcrEhnKQs8DvRlAuqVwttlW1MDAq/4MAECrk+K9XMfmvMVjAdzqp203n3WouDQUAwCAY8Pl3TDUAAAw3aOlmSYjaNlcU0+sTTYlAq5LybJdGMoJn7y+8gunlDbo68pNAuOVjPyiBb4fbKsqW0Og7/aRvYRmIr59oNoRbKpGGdbC+WZbUQMJWoNevDDE9HPfJBlW6GEgYHH2vZCYG2rudUqO5aKIpXWCwahrabD7df/6ojlYLyXLmgXiTaGcEwYtThXfPbClJqSbeoKl8Gwh6HUAeuvTPOjIGXtnD29KQYGSfnTIz8nl2zairGggOglcC6aVqe1jX+pNxAulpSJj0865DNwNbQrhLzvs5Zd5HsiwLrEwyUCRAwdIitzGmmUh6diOzMpPUUh9BwaUXvJC0EUtG6vrtDQN6YZOybK8AMLlYQ1KO2+2JHXkoa4upNgltyGshd5h0Yxqdio/8qeiNsFafJYieGWAqYWO0AfEvFzEKc98tmrpAS1npFuK5pIIWO7JlxbFpHzHs+8x80qSBjjVD+A/SeYgAReH6glCbq4p5XjCGzByZxgAMdbsbajRrNYdUl6sQspdMF4P86LRx+I1zeU82VZT8Z6UPBuApptEMwQrxakbJuYv6RHeu6267P3U42OnMeEW6F82dieIH4OgaQ572VmN1eV/DmIAXg+0Uw4otQC01IhmgAt9+x7gVB+AXwPgfRA0O2QDAJByPOH3MNgAAEhiEdJUK+SpTXJ23lMECmmnNwyYmb/f2lCrSarPE7OlKAMCdkQo4OWHLiL6Q2N26j3+Nptc8oLyIQZfhfCnjDtB/BKYnnfYy9cjiGs6M7Mk7uAodS6AJSDsdFSVe0V3ZuQX/pGZgiS08L0Oe8XdnkfSrUWziLAGPX+P7ZLZsq26ImQVvOTp1vkk+WU/feoKgcv31tcWB2/peU6IjMmcN0IqnZ/CAH0iH74CifNbNtk/CvXE8ZaS0SahrgZwrt6DImCN4uRFgaTUzXOLzgTjbrekohaaQPwaS1Q2VVesg4Y9mQzb4jMAsZCBRQCS3Yc7TE5O9xzXpLmF6aqkzxHgjU/AmsZBh22orDxRKjbVUjQ8QeBdAGN9mn9IIFsoodHduNeTbwAYFOq5IXLUqWJSqGvKsKwyJct6HYi115sKn1Yh6UJ/2UDBmDy/cLCzi/4JIN+AcR0iov/XOPDQU543kCcTLMUXkOD7qKc8/XEAdQDskKh21JQ3arlg2qWLhikdph8CvBDATL+NiB5yVJX9wvNQhq2oloE5flo7ukR8lo83iMy2oheBHqK/du5SfhyOuvRp2fmTVKhvAxgZ6rmhwkR3tW6q/mOo54X5aioVKdkbNsKYhHxfdpJJuWDv+qrtwZv2gNJtRb8g4A8wRhfoHUHyZ59XLd8UqEGGbfGFTOImMJoIvMbZJv+rWeGuoEBJPzz4IiJaCKIrenFVdnOkS8SP97yx063F+US82qfdF5J5pu+0xmwtvANE3rXEiB9zZI+71d8UMBgjMi2jTQrWA0gL9dww+LBFPfBdbNkS8po17PlZ8vS8HJK0DtHYQSV8wgpf2Lq+NqzC425PyTMATtF5ZACgAvQEd4nfRKTD301pqZhYv/N8FbiSmK8IVfaFgV812cs947HIbCvaihMbU9QGQbN91R/MtuKLAa4GTih0fEFERY1VZWFlrQ3PsQ2JZ1kHA6akfpBCyu/t2bxmXfCmPQn76di6sXYTgIgymjTDmEJOWpM6wxLWOqSppnw1gc4yIBiug4EPCDyQ4tWwwzemFhTEm23FF5ttRY+YN+3aIRn/IcZ14egeEXCtT64BM52In3ES4ce+BjA5r3gCwP/CSQOoU5Sus8M1gDGZ85LipXwF0TEAAHg6XAMAIlypj8mclySVzvegvycmEA0KnLbd9a/7LWatATLbipcw+GFyifxqpQvATgJ/BNBWBn8EIbaSHPBhqOECvpyVt2DgcVPcr5npx8BJ3c5IIKJLPW9gl8/fuY2B25rsFc96th0392enxHPHemaa4pJY4bsbBx55NNBaJxinZM4emqAor8GlyxoNtneo6tlfbVkb9ls4YndVynRrLiS/juiJw251xsm8A+vW9FrorjcmWAsnC6JynFxgtgPYQcB2ZmwH0Q4ibiZguxPYPv742N297s7qxOS84gmqwEwmzHRrnJ6OMKabBKxptJd7bRZNmF+c7Cs+llpQMCDh6JBaAOcD9B8mKmmqWvZZuONPnWEZ7lRRBSAnaGN9kBA0S+vOcCB08dmmZFkfAnGwhAs9aVYVzNm/oUaTZ8UvpaViYsOuyW1Obg237kBvTLpk4WmqGpcFovPAPI6ZnpftzqpQZN8zbDcmELWfLqWcAsKZIEwCYwwTxhBjNAJL1bNkPr03f35mZkncwWT1JQbOIKY7HfayiPJGRmRaRpsE14AoQP0yQ3iwpb4m4hgkfTYuMjPjUpQRbyF6TwAAaJFEln2bqt+P4jX9knbpomGiXZwJQZkEzATjfAD+qrwfJ/AbDFS2mUwvuEvMhs24uT87JREdp3SpiBOKGARQAjvVJCHEUWeC8xPP2gw+ULq16E8g7JNt6qOR1mNwu0FrEB0vUDfvD/pSZPeWNqkV3Xbv3H+I/yG0uXakfClBl+yrr94QjYtNnl84uKtTmUKQZxDhdHZNV85Fz40lLRwC41UCVrGM+4/DT4kjo8iwFae2Sz6uxxtwdJbFyoTnYIznLRBHQSInnI1Uf+i6hT06x7KAAwgcGUgHmG5raajuNY80VNKsi9KEMF0kGGdJ8BkETAEwTs9reMAAtgL0JjPq4rs6//PJ6yvDXfxHC0rJtvwCwO8R5UQjYlqwt6H677r1p1dH3aRkWx4HcEPQhjpDwHOynUta36/VrHXTG1MLCuLbjg25giRfD6Lzgp+hKwxgGwjvsuT3icQnzNTUydJhxPolVJLPyhtIibQc0BwaohvEeHRvQ80tuvapZ2cAgMzMuGTTyDeJ2f/WvrFsFSpfsWdLrZ7ZZu6QafljCbqSGBP07DsMOgA6APAXAA6DqB2Sj0GgE5IYJA8C4oAgqnQXSNeV5Bn53yGn89koL4ABAASs36seyA1nVzhIv/ozItMyWlG4wYDi4BrgNgL+39768x7VKAEfEhMsRdlEuJII3wdg1rv/CGhiwiuC5SuNg46+Ha6fPzClIjlnw43EeADRTSTqplmSc3p3AW49MSysNXla3pkk6G0YpJ2vgQ0KlEW766vC9nsHY0J+0Xgh+SKALgbRxQBH0eh5H4j+A6a1BPF6o71H8XjdSM7Om0DAcoBCSn/VkSOsKDNbN1RpLvcUCobGdqdMy7NB0KsIXMHEaI4x012tA9ufCDVTLRzGW0pGxxGfxSTPJuBst/doDE6GO4fDEQYcADcS0ycgbGGT8r+mVUt36DTswBQUKCk7Dl8L19M/ml4/T5yC5Nw9m9aEIusYEobXJBqdbb2GwbHW7Xkfgm6OdGcxXKYWFMS3HxqUjDjTWHaqSYKQBFACQAlMrgqPxPwVAEgh2oRUv5BC+aJTlQditRBOybJdyCQfMaxghjaYQUta66vLjbxIVApzJWdb7iKXKy3WvAZBN7ZsrG6O9UD6Kqfl2FJVln8AcDViXriNftFSXx2SvHs4RMW/e2y3463BqRMTEb2gqkBMAvM1A0+beOrA1PHvHtu9TRd36teB1BmW4QNOM9/FzP8AKBM32wV2AAAFi0lEQVSxNgDGPS0NNVF5cEb1F03JsvwFhOuDt4wKx0F4khV+MNw8ha8DLgkU520AXQtjSqaGDDMeaW2o6U3wS1eibe00OifvEWZdBXUj5RiA5ULSX8JJ4+yvjD4vfzxU523MWAJQsIy16MH0p5aG6l8gitXSY/LKS8my/A6Eu4O3jC5MtI4Yj7Yktb8UDW9S1MnNNSUfi7cJoiIG5iJ2Xju/MPF9rZtqfxPt68Zs3peSY7kbbGh1kkhoJuA5KfkfrZtr+3TVGi2MzJkzUWHlJwAvhnHxTxHCpS31tTG5H2K6+EnJsi4C8dMA4mM5jl5h/oCJ/mmC8oKRG296MyrbcjYBcwk8D6AcxHqhGxgngW7YW18dqMCI4cT8DzNm2pyZUoiXEAVJDh1oAbAGoFXx8craHW+v/iroGVHCndh+AYBL4JrqhBPeHW2OAvhRS32NrxpGVIm5EQDAmGnWyVLwa4herrIeOAnYCvBmgDYDcvPwY8Pe37q119oA+pCba0o5ljAZhEwizmSmmXDlNUQrxVUPWoj4kr2barfEeiB9wgiAE8p2LyH2ewmR0AnmTwGqbGmouVfvzpOz8n4liH7kDsfou1PI4GwRqvqDPVvWGh/6oYE+8+TYs2XVgUFfitkAnon1WCIgHkTfIdFDdU4XiGgmA2ejHxsAAc8JNf57fcUAgD7mInPniy5MybasBfAEYhe01VfpM2/uMHAC+PXe+pqQCjVGgz7zJvCkpb5mpVB5GgO6J4VEA+Z+fbMaADVJ5gtb+qABAH3UCABgz5baTwYkdcwgl3pa1HYPdcKYvyv13e+rFyoTO9sz9zXU9oUSV37pU9MhX5rr6toB3JycY3mDGOXQvxyTMRj1JuhHbxgGDgK4vrW+5rlYjyUY/eLJ0rqp5hWFxDkEhKWNGXWI+83NagQMqo1T4s7sDwYA9PE3gSfuquSXJmfnXUKgxxFdoacQIUOMgEGC+vbMsAXAL1vrq59BP5rC9os3gSet9bWvCTV+KkC/A2D8xlR4GGIEgvvsG0Yy09IOVZ3SUl+zEv3IAIB+aAQAsGfLquMt9dWlCpTvAPRGrMfjB2NuVtEH1wSM/0FiRmtD9TWRKEPHkn5pBN3srq/6rKW+ejYxX03A57EeTzdMBk2H+tbC2EHMV7c0zMhq2VxTH+vBREK/NgI3vLeh9tm9SR1ngFAIoDnWA+rD05aIYfBuAl3boh44fW9D7bNGaDtFm6+DEbioq3O2bKpZ3qIemATgpwC2xWoobNzObiy/ry8A/CpOGTpxb33103qrwMWSfuMd0syWLV0twMq03Nzn244llhDx7Yh6IolBbwIGxWBC5GDC42jjMr10XvsaXz8jcOPeaHsMKP1Lctb6uULQTcyYHetx9RdOpJqOH/yi/pKOfYuvrRGcpFS2NmAVgFUpWdYsFryEGD+CocoKxiyMo8AhAioFy7/url/jitvq10tebXwDjOAkLQ3VDQAaRubm3iba4n8oQD9mRi70/jsYNWkxJnZIJcKbzHiG2/nfLV/TKU9vfKOMoJv9dXVHAVQAqBh3/txTOjud8+DS2s9DP47VDwEVQD0T/gWF/9HyDdZdAr6hRuCJO094JYCVaefkDutISJjHEleAMAvh5jMY58+PpN9jAN4k4lVdTlp1YEvNXr0G1d/5xhuBJ83v1h2EK7PtGRQUKKN3HDpHgs4nxkwAF6O/RLG6OArGZiasIxZrWga2rftaainpwLdGEIjKSnUvsAWufx4FSsWonI1nEssLiXEeiM6Aq46Zn+mTYQvjQP1KAhySeBNJsUEwbdgzqO2Db296bfRXL0bfIDfXdNrxpHQJOVWCTxfAVHcS/NGW+hrd84xH51jWgDGSgU8B+phZfsxEnyQldXzqdgl/Sxj8f+5eoQLlJvS0AAAAAElFTkSuQmCC'
  };
})( jQuery, window, document );
