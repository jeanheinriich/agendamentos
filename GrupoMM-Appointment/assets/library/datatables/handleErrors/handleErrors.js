/**********************************************************************
 * handleErrors.js
 * 
 * (c) 2018 by Emerson Cavalcanti (emersoncavalcanti@gmail.com)
 * 
 * Licensed under GNU General Public License 3.0 or later. 
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 * 
 * Some open source application is distributed in the hope that it will 
 * be useful, but WITHOUT ANY WARRANTY; without even the implied warranty 
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with Foobar. If not, see <http://www.gnu.org/licenses/>.
 *
 * @license GPL-3.0+ <http://spdx.org/licenses/GPL-3.0+>
 * --------------------------------------------------------------------
 * Controle de Modificações
 * 
 * 2018-12-05 - Emerson Cavalcanti
 *   - Versão inicial
 * --------------------------------------------------------------------
 * Descrição:
 * 
 * Funções que estendem o DataTables para permitir tratar corretamente
 * erros na comunicação por ajax, exibindo uma mensagem de erro
 * apropriada usando uma janela.
 **********************************************************************/
    
$.fn.dataTable.ext.errMode = function ( settings, helpPage, message ) {
  if (typeof(message) != 'undefined' && message != null) {
    if (message.length > 38) {
      // Exibe a mensagem de erro
      warningDialog('Atenção', message.substring(38));
    }
  }
};

function handleAjaxError(jqXHR, textStatus, error ) {
  console.log('handle handleAjaxError');
  if (textStatus === 'timeout') {
    warningDialog('Desculpe', "O servidor demorou muito tempo para responder à requisição");
  } else {
    var
      json,
      title,
      message,
      errors
    ;

    switch (jqXHR.status) {
      case 403:
        // Erro de permissão
        json = jQuery.parseJSON(jqXHR.responseText);
        title = 'Ops';
        message = 'Desculpe, ocorreu um erro no servidor (403) com a '
          + 'seguinte mensagem de erro: ' + json.message
        ;

        break;
      case 404:
        // Erro de página não encontrada
        title = 'Ops, página não encontrada';
        message = 'Desculpe, a página ' + url + ' requisitada não foi '
          + 'encontrada'
        ;

        break;
      case 405:
        // Erro de método não permitido
        json = jQuery.parseJSON(jqXHR.responseText);
        title = 'Ops';
        message = 'Desculpe, ocorreu um erro no servidor (405) com a '
          + 'seguinte mensagem de erro: ' + json.message
        ;

        break;
      case 500:
        // Erro interno da aplicação
        json = jQuery.parseJSON(jqXHR.responseText);
        title = 'Ops';
        message = 'Desculpe, ocorreu um erro no servidor (500) com a '
          + 'seguinte mensagem de erro: ' + json.message
        ;
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
        try
        {
          json = jQuery.parseJSON(jqXHR.responseText);
          
          title = 'Ops';
          message = 'Desculpe, ocorreu um erro no servidor ('
            + jqXHR.status + ') com a seguinte mensagem de erro: '
            + json.message
          ;
        }
        catch (e)
        {
          // Não respondeu como json, então abre uma nova janela e
          // coloca o resultado retornado
          var newWindow = window.open("", textStatus,
            "width=600, height=300")
          ;
          newWindow.document.write(jqXHR.responseText);

          title = 'Ops';
          message = 'Desculpe, ocorreu um erro no servidor ('
            + jqXHR.status + ')  e a aplicação foi interrompida'
          ;
        }
    }

    // Esconde a janela de progresso
    $('div.dataTables_processing.ui.segment')
      .css( 'display', 'none' )
    ;
    
    // Exibe a mensagem de erro
    errorDialog(title, message);

    // Reproduzir o som quando a mensagem é exibida
    var
      playPromise = $('#errorSound')[0].play()
    ;

    // Nos navegadores que ainda não suportam essa funcionalidade, o
    // playPromise não será definido
    if (playPromise !== undefined) {
      playPromise.then(_ => {
        // Autoplay started!
      }).catch(error => {
        // Autoplay was prevented
        console.log('Autoplay was prevented');
      });
    }
  }
}
