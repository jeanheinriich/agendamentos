<?php
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
 * Configuração das validações de um número de ICCID (Integrated Circuit
 * Card ID).
 *
 * Cada SIM é identificado internacionalmente pelo seu identificador de
 * cartão de circuito integrado (ICCID). ICCID é o identificador do
 * próprio cartão SIM - ou seja, um identificador para o chip SIM.
 * 
 * Atualmente, os números ICCID também são usados para identificar
 * perfis eSIM e não apenas cartões SIM físicos. Os ICCIDs são
 * armazenados nos cartões SIM e também são gravados ou impressos no
 * corpo do cartão SIM durante um processo chamado personalização.
 * 
 * O ICCID é definido pela recomendação E.118 da ITU-T como o número da
 * conta principal. Seu layout é baseado na ISO/IEC 7812. De acordo com
 * E.118, o número pode ter até 22 dígitos, incluindo um único dígito de
 * verificação calculado usando o algoritmo Luhn. No entanto, o GSM Fase
 * 1 definiu o comprimento do ICCID como um campo de dados opaco, de 10
 * octetos (20 dígitos) de comprimento, cuja estrutura é específica de
 * um operador de rede móvel. Com a especificação GSM Fase 1 usando 10
 * octetos nos quais o ICCID é armazenado como BCD compactado, o campo
 * de dados tem espaço para 20 dígitos com o dígito hexadecimal "F"
 * sendo usado como preenchimento quando necessário.
 * 
 * Na prática, isso significa que nos cartões SIM GSM existem ICCIDs de
 * 20 dígitos (19 + 1) e 19 dígitos (18 + 1) em uso, dependendo do
 * emissor. No entanto, um único emissor sempre usa o mesmo tamanho para
 * seus ICCIDs.
 * 
 * O número é composto das seguintes subpartes:
 *   - Identificação de atividade industrial - Major industry identifier
 *     (MII), com 2 dígitos fíxos, 89 para fins de telecomunicação.
 *   - Número de identificação de país e expedidor com no máximo 5
 *     dígitos.
 *   - Número individual de identificação de conta (12 ou 11 dígitos)
 *   - Dígito verificador calculado pelo algorítmo Luhn.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Validation\Rules;

use Respect\Validation\Rules\AbstractRule;

class Iccid
  extends AbstractRule
{
  /**
   * Retorna se o último dígito do número informado é válido conforme o
   * algorítmo Luhn
   *
   * @param string $input
   *
   * @return bool
   */
  private function validLuhn(string $number)
  {
    $parity = strlen($number) % 2;
    $total = 0;

    // Divide cada dígito em uma matriz
    $digits = str_split($number);

    foreach($digits as $key => $digit) {
      // Para cada dígito
      // para cada segundo dígito da direita, devemos multiplicar por 2
      if (($key % 2) == $parity)
        $digit = ($digit * 2);

      // Cada dígito é o seu próprio número (11 é realmente 1 + 1)
      if ($digit >= 10) {
        // Divide os dígitos
        $digit_parts = str_split($digit);

        // Adicionamo-os juntos
        $digit = $digit_parts[0] + $digit_parts[1];
      }

      // Adicionamos ao total
      $total += $digit;
    }

    return (($total % 10) == 0 ? true : false);
  }

  /**
   * Valida o valor de um campo que contém um ICCID.
   *
   * @see https://en.wikipedia.org/wiki/International_mobile_subscriber_identity
   *
   * @param mixed $input
   *   O valor a ser validado
   *
   * @return bool
   */
  public function validate($input)
  {
    if (!is_scalar($input)) {
      return false;
    }

    // Recupera apenas os números, dispensando os sinais de pontuação
    $iccid = preg_replace('/\D/', '', $input);

    // Se o ICCID não tiver a quantidade de dígitos corretos, retorna
    if ((strlen($iccid) < 19) || (strlen($iccid) > 20))
      return false;

    return $this->validLuhn($iccid);
  }
}
