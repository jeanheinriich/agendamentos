/**********************************************************************
 * calculate.password.strength.js
 * 
 * (c) 2017 by Emerson Cavalcanti (emersoncavalcanti@gmail.com)
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
 * 2018-09-20 - Emerson Cavalcanti
 *   - Versão inicial
 * --------------------------------------------------------------------
 * Descrição:
 * 
 * Uma biblioteca javascript que permite calcular o grau de complexidade
 * de uma senha.
 **********************************************************************/

"use strict";

var CalculatePasswordStrength = function(password) {
  this.score = 0;
  this.message = [];
  
  var charPassword = password.split("");
  
  var minPasswordLength = 8;
  
  // Os contadores parciais da decomposição da nossa senha
  var count = {};
  count.Excess = 0;
  count.Lower = 0;
  count.Upper = 0;
  count.Numbers = 0;
  count.Symbols = 0;
  count.charInSequence = 0;
  
  var bonus = {};
  bonus.Excess = 3;
  bonus.Upper = 4;
  bonus.Numbers = 5;
  bonus.Symbols = 5;
  bonus.Combo = 0; 
  bonus.FlatLower = 0;
  bonus.FlatNumber = 0;
  
  var calcScore = 0;
  var charInSequence = 'abcdefghijklmnopqrstuvwxyz';
  
  // Calcula os scores parciais com base no conteúdo da senha
  for (var i=0; i < charPassword.length; i++) {
    // Determina os tipos de caracteres
    if (charPassword[i].match(/[A-Z]/g)) { count.Upper++; }
    if (charPassword[i].match(/[a-z]/g)) { count.Lower++; }
    if (charPassword[i].match(/[0-9]/g)) { count.Numbers++; }
    if (charPassword[i].match(/(.*[!,@,#,$,%,^,&,*,?,_,~])/)) { count.Symbols++; }
    
    if ((i + 2) < charPassword.length) {
      // Determina a existência de caracteres em sequência
      if (isNaN(charPassword[i])) {
        // Verificamos sequências de letras
        var pos = charInSequence.indexOf(charPassword[i].toLowerCase());
        var nth1 = charPassword[i+1].toLowerCase();
        var nth2 = charPassword[i+2].toLowerCase();
        if ((nth1 === charInSequence[pos + 1]) &&
            (nth2 === charInSequence[pos + 2])) {
          count.charInSequence++;
        }
      } else {
        // Verificamos sequências de números
        var curr = parseInt(charPassword[i]);
        var nth1 = parseInt(charPassword[i+1]);
        var nth2 = parseInt(charPassword[i+2]);
        if ((nth1 === (curr + 1)) &&
            (nth2 === (curr + 2))) {
          count.charInSequence++;
        }
      }
    }
  }
  
  // Determina a quantidade de caracteres excedentes
  count.Excess = charPassword.length - minPasswordLength;
  
  // -----------------------------------------------------------------
  // Determina os bonus pelos caracteres presentes na senha
  // -----------------------------------------------------------------
  
  // Incrementa se temos maiúsculas e minúsculas
  if (count.Lower && count.Upper) { calcScore++; }
  
  // Incrementa se temos letras e números
  if ((count.Numbers && count.Lower) || (count.Numbers && count.Upper)) { calcScore++; }
  
  // Incrementa se temos símbolos
  if (count.Symbols) { calcScore++; }
  
  // -----------------------------------------------------------------
  // Determina os bonus por comprimento da senha
  // -----------------------------------------------------------------
  
  // Incrementa se temos mais caracteres que o mínimo
  if (count.Excess > 0) { calcScore++; }
  
  // Acrescenta um bonus ou punição pelo comprimento da senha
  var length = charPassword.length;
  if (length >= minPasswordLength) {
    calcScore += (1 + count.Excess);
  } else {
    // Descontamos a quantidade de caracteres que faltam para o mínimo
    calcScore += count.Excess;
  }
  
  // -----------------------------------------------------------------
  // Desconta caso tenhamos caracteres repetidos em sequência
  // -----------------------------------------------------------------
  
  // Letras ou números repetidos (Ex: aaa)
  if ((password.match(/([a-zA-Z0-9])\1/)) && (calcScore > 1)) { 
    calcScore--;
  }
  
  // Sequência de letras ou números (Ex: 1234)
  if (count.charInSequence) {
    calcScore-=count.charInSequence;
    if (calcScore < 1) calcScore = 1;
  }
  
  // Verifica os limites de nossa pontuação
  if (calcScore <= 0) {
    calcScore = 0;
  } else {
    if (calcScore > 8)
      calcScore = 8;
  }
  
  this.score = calcScore;
};
