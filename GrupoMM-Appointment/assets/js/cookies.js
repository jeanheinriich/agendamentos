/**
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
 * Um gerenciador de cookies simplificado.
 * 
 */

/**
 * Recupera o valor de um cookie.
 *
 * @param string name
 *   O nome do cookie
 *
 * @return string
 *   O conteúdo do cookie
 */
var getCookie = function (name)
{
  var
    // Divide a string do cookie, obtendo todos os pares nome = valor
    // individuais em uma matriz
    cookieArray = document.cookie.split(";")
  ;

  // Percorre todos os pares
  for (var i = 0; i < cookieArray.length; i++) {
    var
      // Obtém o par chave/valor
      cookiePair = cookieArray[i].split("=")
    ;
    
    // Remover o espaço em branco no início do nome do cookie para
    // compará-lo com a string fornecida
    if (name == cookiePair[0].trim()) {
      // Decodifique o valor do cookie e retorna
      return decodeURIComponent(cookiePair[1]);
    }
  }

  // Retorna nulo se não encontrou nenhum valor
  return null;
};


/**
 * Seta o valor de um cookie.
 *
 * @param string name
 *   O nome do cookie
 * @param string value
 *   O valor a ser atribuído ao cookie
 * @param int daysToLive
 *   O tempo de vida (em dias)
 */
var setCookie = function (name, value, daysToLive)
{
  var
    // Codifique o valor para escapar de ponto e vírgula, vírgula e
    // espaço em branco
    cookie = name + '=' + encodeURIComponent(value),
    path = '/',
    // Obtém o nome do domínio
    domain = window.location.hostname,
    // Obtém o nível de segurança
    secure = window.location.protocol == 'https:'
      ? 'Secure; '
      : ''
  ;

  if (typeof daysToLive == "number") {
    // Define o atributo max-age para que o cookie expire após o número
    // especificado de dias
    cookie += "; max-age=" + (daysToLive * 24 * 60 * 60);
  } else {
    cookie += "; expires=" + new Date(2147483647 * 1000).toUTCString();
  }

  // Acrescenta o caminho e o domínio
  cookie += '; path=' + path + '; sameSite=Lax; domain=' + domain + "; "
    + secure
  ;

  // Armazena o cookie
  document.cookie = cookie;
};

/**
 * Apaga um cookie.
 *
 * @param string name
 *   O nome do cookie
 */
var delCookie = function (name)
{
  setCookie(name, '', 0);
};
