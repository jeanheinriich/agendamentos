/*
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
 * Um sistema de exibição de mensagens e de requisições assíncronas
 * usando o framework Semantic UI.
 */

// Um diálogo padrão
var commonDialog = function(title, message, type)
{
  // Exibe o diálogo conforme o tipo selecionado
  $('.ui.' + type + '.modal > .header').text(title);
  $('.ui.' + type + '.modal > .content > .description').html('<p>' + message + '</p>');
  $('.ui.' + type + '.modal').modal(
    {
      closable  : true,
      autofocus: true,
      restoreFocus: true
    })
    .modal('show')
  ;
};

// Um diálogo de informação
var infoDialog = function(title, message)
{
  // Exibe o diálogo de informação
  commonDialog(title, message, 'info');
};

// Um diálogo de sucesso
var successDialog = function(title, message)
{
  // Exibe o diálogo de sucesso
  commonDialog(title, message, 'success');
};

// Um diálogo de alerta
var warningDialog = function(title, message)
{
  // Exibe o diálogo de alerta
  commonDialog(title, message, 'warning');
};

// Um diálogo de erro
var errorDialog = function(title, message)
{
  // Exibe o diálogo de erro
  commonDialog(title, message, 'error');
};

// Um diálogo de questionamento
var questionDialog = function(title, message, confirm, reject)
{
  // Exibe o diálogo de questionamento
  $('.ui.question.modal > .header').text(title);
  $('.ui.question.modal > .content > .description').html('<p>' + message + '</p>');
  $('.ui.question.modal').modal({
      closable : true,
      onApprove : function(dialogRef) {
        // Executa o callback nos casos de confirmação
        if (jQuery.isFunction(confirm)) {
          confirm();
        }
      },
      onDeny : function() {
        // Executa o callback nos casos de rejeição
        if (jQuery.isFunction(reject)) {
          reject();
        }
      },
      autofocus: true,
      restoreFocus: true
    })
    .modal('show')
  ;

  // Reproduzir o som quando a mensagem é exibida
  var playPromise = $('#errorSound')[0].play();

  // Nos navegadores que ainda não suportam essa funcionalidade, o
  // playPromise não será definido
  if (playPromise !== undefined) {
    playPromise.then(function() {
      // Automatic playback started!
    }).catch(function(error) {
      // Automatic playback failed.
      // Show a UI element to let the user manually start playback.
      console.log('Autoplay was prevented');
    });
  };
};

// Um diálogo de interação
var interactionDialog = function(title, content, prepare, confirm, reject)
{
  // Exibe o diálogo de interação
  $('.ui.interaction.modal > .header').text(title);
  $('.ui.interaction.modal > .content').html(content);
  $('.ui.interaction.modal').modal({
      closable : true,
      onShow : function() {
        // Executa o callback nos casos de preparação do diálogo
        if (jQuery.isFunction(prepare)) {
          prepare();
        }
      },
      onApprove : function(dialogRef) {
        // Executa o callback nos casos de confirmação
        if (jQuery.isFunction(confirm))
        {
          confirm();
        }
      },
      onDeny : function() {
        // Executa o callback nos casos de rejeição
        if (jQuery.isFunction(reject)) {
          reject();
        }
      },
      autofocus: true,
      restoreFocus: true
    })
    .modal('show')
  ;

  // Reproduzir o som quando a mensagem é exibida
  var playPromise = $('#interactionSound')[0].play();

  // Nos navegadores que ainda não suportam essa funcionalidade, o
  // playPromise não será definido
  if (playPromise !== undefined) {
    playPromise.then(function() {
      // Automatic playback started!
    }).catch(function(error) {
      // Automatic playback failed.
      // Show a UI element to let the user manually start playback.
      console.log('Autoplay was prevented');
    });
  };
};

// Um diálogo de detalhamento
var detailDialog = function(title, content, prepare)
{
  // Exibe o diálogo de detalhamento
  $('.ui.detail.modal > .header').text(title);
  $('.ui.detail.modal > .content').html(content);
  $('.ui.detail.modal').modal({
      closable : true,
      onShow : function() {
        // Executa o callback nos casos de preparação do diálogo
        if (jQuery.isFunction(prepare)) {
          prepare();
        }
      },
      autofocus: true,
      restoreFocus: true
    })
    .modal('show')
  ;

  // Reproduzir o som quando a mensagem é exibida
  var playPromise = $('#interactionSound')[0].play();

  // Nos navegadores que ainda não suportam essa funcionalidade, o
  // playPromise não será definido
  if (playPromise !== undefined) {
    playPromise.then(function() {
      // Automatic playback started!
    }).catch(function(error) {
      // Automatic playback failed.
      // Show a UI element to let the user manually start playback.
      console.log('Autoplay was prevented');
    });
  };
};

// Realiza uma requisição para recuperar um conteúdo em JSON usando o
// método informado
var requestJSON = function(url, parameters, method, success, error)
{
  $('#loader').show();
  var getterJson = $.ajax({
    dataType: "json",
    url: url,
    method: method,
    data: parameters
  });

  getterJson.done(function( json )
  {
    $('#loader').hide();

    if (Object.keys(json).length >= 4) {
      if (json.result == 'OK') {
        success( json.data, json.params, json.message )
      } else {
        // Executa o callback, se necessário
        if (jQuery.isFunction(error)) {
          error(json.message, json.data);
        } else {
          // Exibe a mensagem de erro
          errorDialog('Erro', json.message);
        }
      }
    } else {
      // Executa o callback, se necessário
      if (jQuery.isFunction(error)) {
        error('Resposta do servidor com tamanho inválido.', null);
      }

      // Exibe a mensagem de erro
      errorDialog('Erro', 'Resposta do servidor com tamanho inválido.');
    }
  });

  getterJson.fail(function(jqXHR, textStatus, errorThrown)
  {
    var
     json,
     title,
     message,
     errors
    ;

    $('#loader').hide();
    switch (jqXHR.status) {
      case 403:
        // Erro de permissão
        json = jQuery.parseJSON(jqXHR.responseText);
        title = 'Erro 403';
        message = json.message;

        break;
      case 404:
        // Erro de página não encontrada
        title = 'Erro 404';
        message = 'A página ' + url + ' requisitada não foi encontrada';

        break;
      case 405:
        // Erro de método não permitido
        json = jQuery.parseJSON(jqXHR.responseText);
        title = 'Erro 405';
        message = json.message;

        break;
      case 500:
        // Erro interno da aplicação
        json = jQuery.parseJSON(jqXHR.responseText);
        title = 'Erro 500';
        message = json.message;
        errors = json.exception;
        errors.forEach(function (value, index)
        {
          console.log(index + ': ' + value.type);
          console.log('Código: ' + value.code);
          console.log('Mensagem: ' + value.message);
          console.log('Arquivo: ' + value.file);
          console.log('Linha: ' + value.line);
        });

        break;
      default:
        // Alerta o usuário com o resultado
        console.log('Outro tipo de erro com status ' + jqXHR.status);
        console.log(jqXHR.responseText);
        try {
          json = jQuery.parseJSON(jqXHR.responseText);

          title = 'Ops';
          message = json.message;
        } catch (e) {
          // Não respondeu como json, então abre uma nova janela e
          // coloca o resultado retornado
          var newWindow = window.open("", textStatus, "width=600, height=300");
          newWindow.document.write(jqXHR.responseText);

          title = 'Ops';
          message = 'Desculpe, um erro ocorreu e a aplicação foi interrompida.';
        }
    }

    // Executa o callback de erro, se necessário
    if (jQuery.isFunction(error)) {
      var
        data = json.hasOwnProperty('data')
          ? json.data
          :null
      ;

      error(message, data);
    }

    // Exibe a mensagem de erro
    errorDialog(title, message);

    // Reproduzir o som quando a mensagem é exibida
    var playPromise = $('#errorSound')[0].play();

    // Nos navegadores que ainda não suportam essa funcionalidade, o
    // playPromise não será definido
    if (playPromise !== undefined) {
      playPromise.then(function() {
        // Automatic playback started!
      }).catch(function(error) {
        // Automatic playback failed.
        // Show a UI element to let the user manually start playback.
        console.log('Autoplay was prevented');
      });
    };
  });
};

// Realiza uma requisição para pegar um conteúdo em JSON
var getJSONData = function(url, parameters, success, error)
{
  requestJSON(url, parameters, 'GET', success, error);
};

// Realiza uma requisição para recuperar um conteúdo em JSON. Utiliza o
// método PATCH para não conflitar à nível de permissão com o POST e o
// PUT, e permitir enviar as informações codificadas por HTTPS
var requestJSONData = function(url, parameters, success, error)
{
  requestJSON(url, parameters, 'PATCH', success, error);
};

// Realiza uma requisição para modificar um conteúdo em JSON
var putJSONData = function(url, parameters, success, error)
{
  requestJSON(url, parameters, 'PUT', success, error);
};

// Realiza uma requisição para postar um conteúdo em JSON
var postJSONData = function(url, parameters, success, error)
{
  requestJSON(url, parameters, 'POST', success, error);
};

// Realiza uma requisição para apagar um conteúdo em JSON
var deleteJSONData = function(url, parameters, success, error)
{
  requestJSON(url, parameters, 'DELETE', success, error);
};
