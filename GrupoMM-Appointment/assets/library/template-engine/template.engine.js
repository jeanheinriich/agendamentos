/* ---------------------------------------------------------------------
 * template.engine.js
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
 * Uma classe que fornece um micro template simples para uso com
 * formulários complexos, com uma base e diversos detalhes.
 * 
 * --------------------------------------------------------------------
 * Controle de Modificações
 * 
 * 2018-10-08 - Emerson Cavalcanti
 *   - Versão inicial
 * ---------------------------------------------------------------------
 */

(function(){
  var cache = {};
   
  this.TemplateEngine = function TemplateEngine(str, data){
    // Verifica se estamos recebendo um modelo, ou se precisamos carregar
    // o modelo (certificando-se de armazenar em cache o resultado)
    var fn = !/\W/.test(str) ?
      cache[str] = cache[str] || TemplateEngine(document.getElementById(str).innerHTML) :
      // Gere uma função reutilizável que funcionará como um gerador de
      // modelo (e que será armazenado em cache)
      new Function("obj",
        "var p=[],print=function(){p.push.apply(p,arguments);};" +
        
        // Introduz os dados como variáveis locais usando with(){}
        "with(obj){p.push('" +
         
        // Converte o modelo em JavaScript puro
        str
          .replace(/[\r\t\n]/g, " ")
          .split("<%").join("\t")
          .replace(/((^|%>)[^\t]*)'/g, "$1\r")
          .replace(/\t=(.*?)%>/g, "',$1,'")
          .split("\t").join("');")
          .split("%>").join("p.push('")
          .split("\r").join("\\'")
      + "');}return p.join('');");
    
    return data ? fn( data ) : fn;
  };
})();
