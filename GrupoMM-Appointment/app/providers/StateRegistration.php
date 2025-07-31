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
 * Classe responsável pela validação de um número de inscrição estadual
 * conforme as regras de cada estado. Esta classe foi baseada nas informações
 * obtidas no site do Sintegra (http://www.sintegra.gov.br).
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Providers;

use InvalidArgumentException;

class StateRegistration
{
  /**
   * Valida o número de uma inscrição estadual.
   * 
   * @param string $number
   *   O número de inscrição a ser avaliado
   * @param string|null $stateOfRegistration
   *   O estado (UF) à qual pertence está inscrição
   * 
   * @return boolean
   *   O indicativo se a inscrição estadual informada é válida
   *
   * @throws InvalidArgumentException
   *   Caso a inscrição estadual seja inválida
   */
  public static function isValid(string $number,
    ?string $stateOfRegistration)
  {
    // Força a UF a ser sempre maiúscula
    $stateOfRegistration = strtoupper($stateOfRegistration);

    // Remove a pontuação da inscrição estadual fornecida
    $number = preg_replace('/[^a-z0-9]/i', '', strtoupper($number));

    // Elimina a verificação de inscrição estadual isenta
    if ($number == 'ISENTO' or $number == 'ISENTA') {
      return true;
    }

    if (is_null($stateOfRegistration)) {
      return false;
    }

    // Utiliza o módulo 11 por padrão, exceto quando especificado
    $modulo = 11;

    // As propriedades de cada estado
    $simplifiedProperties = [
      // Acre (AC)
      'AC' => [
        'size'        => 13,
        'weights'     => [
          '12' => [ 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 ],
          '13' => [ 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 ]
        ],
        'stateCodes'  => [ '01' ]
      ],
      // Alagoas (AL)
      'AL' => [
        'size'        => 9,
        'weights'     => [
          '9' => [ 9, 8, 7, 6, 5, 4, 3, 2 ]
        ],
        'stateCodes'  => [ '24' ]
      ],
      // Amapá (AP)
      'AP' => [
        'size'        => 9,
        'weights'     => [
          '9' => [ 9, 8, 7, 6, 5, 4, 3, 2 ]
        ],
        'stateCodes'  => [ '03' ]
      ],
      // Amazonas (AM)
      'AM' => [
        'size'        => 9,
        'weights'     => [
          '9' => [ 9, 8, 7, 6, 5, 4, 3, 2 ]
        ],
        'stateCodes'  => [ ]
      ],
      // Bahia (BA)
      'BA' => [ ],
      // Ceará (CE)
      'CE' => [
        'size'        => 9,
        'weights'     => [
          '9' => [ 9, 8, 7, 6, 5, 4, 3, 2 ]
        ],
        'stateCodes'  => [ ]
      ],
      // Distrito Federal (DF)
      'DF' => [
        'size'        => 13,
        'weights'     => [
          '12' => [ 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 ],
          '13' => [ 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 ]
        ],
        'stateCodes'  => [ '07' ]
      ],
      // Espírito Santo (ES)
      'ES' => [
        'size'        => 9,
        'weights'     => [
          '9' => [ 9, 8, 7, 6, 5, 4, 3, 2 ]
        ],
        'stateCodes'  => [ ]
      ],
      // Goiás (GO)
      'GO' => [
        'size'        => 9,
        'weights'     => [
          '9' => [ 9, 8, 7, 6, 5, 4, 3, 2 ]
        ],
        'stateCodes'  => [ '10', '11', '15' ]
      ],
      // Maranhão (MA)
      'MA' => [
        'size'        => 9,
        'weights'     => [
          '9' => [ 9, 8, 7, 6, 5, 4, 3, 2 ]
        ],
        'stateCodes'  => [ '12' ]
      ],
      // Mato Grosso (MT)
      'MT' => [
        'size'        => 11,
        'weights'     => [
          '11' => [ 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 ]
        ],
        'stateCodes'  => [ ]
      ],
      // Mato Grosso do Sul (MS)
      'MS' => [
        'size'        => 9,
        'weights'     => [
          '9' => [ 9, 8, 7, 6, 5, 4, 3, 2 ]
        ],
        'stateCodes'  => [ '28' ]
      ],
      // Minas Gerais (MG)
      'MG' => [
        'size'        => 13,
        'weights'     => [
          '12' => [ 1, 2, 1, 1, 2, 1, 2, 1, 2, 1, 2 ],
          '13' => [ 3, 2, 11, 10, 9, 8, 7, 6, 5, 4, 3, 2 ]
        ],
        'stateCodes'  => [ ]
      ],
      // Pará (PA)
      'PA' => [
        'size'        => 9,
        'weights'     => [
          '9' => [ 9, 8, 7, 6, 5, 4, 3, 2 ]
        ],
        'stateCodes'  => [ '15' ]
      ],
      // Paraíba (PB)
      'PB' => [
        'size'        => 9,
        'weights'     => [
          '9' => [ 9, 8, 7, 6, 5, 4, 3, 2 ]
        ],
        'stateCodes'  => [ ]
      ],
      // Paraná (PR)
      'PR' => [
        'size'        => 10,
        'weights'     => [
          '9' => [ 3, 2, 7, 6, 5, 4, 3, 2 ],
          '10' => [ 4, 3, 2, 7, 6, 5, 4, 3, 2 ]
        ],
        'stateCodes'  => [ ]
      ],
      // Pernambuco (PE)
      'PE' => [ ],
      // Piauí (PI)
      'PI' => [
        'size'        => 9,
        'weights'     => [
          '9' => [ 9, 8, 7, 6, 5, 4, 3, 2 ]
        ],
        'stateCodes'  => [ ]
      ],
      // Rio de Janeiro (RJ)
      'RJ' => [
        'size'        => 8,
        'weights'     => [
          '8' => [ 2, 7, 6, 5, 4, 3, 2 ]
        ],
        'stateCodes'  => [ ]
      ],
      // Rio Grande do Norte (RN)
      'RN' => [ ],
      // Rio Grande do Sul (RS)
      'RS' => [
        'size'        => 10,
        'weights'     => [
          '10' => [ 2, 9, 8, 7, 6, 5, 4, 3, 2 ]
        ],
        'stateCodes'  => [ ]
      ],
      // Rondônia (RO)
      'RO' => [ ],
      // Roraima (RR)
      'RR' => [
        'size'        => 9,
        'weights'     => [
          '9' => [ 1, 2, 3, 4, 5, 6, 7, 8 ]
        ],
        'modulo' => 9,
        'stateCodes'  => [ '24' ]
      ],
      // Santa Catarina (SC)
      'SC' => [
        'size'        => 9,
        'weights'     => [
          '9' => [ 9, 8, 7, 6, 5, 4, 3, 2 ]
        ],
        'stateCodes'  => [ ]
      ],
      // São Paulo (SP)
      'SP' => [ ],
      // Sergipe (SE)
      'SE' => [
        'size'        => 9,
        'weights'     => [
          '9' => [ 9, 8, 7, 6, 5, 4, 3, 2 ]
        ],
        'stateCodes'  => [ ]
      ],
      // Tocantins (TO)
      'TO' => [
        'size'        => 11,
        'weights'     => [
          '11' => [ 9, 8, 0, 0, 7, 6, 5, 4, 3, 2 ]
        ],
        'stateCodes'  => [ '01', '02', '03', '99' ]
      ]
    ];

    // Verifica se a UF existe
    if (array_key_exists($stateOfRegistration, $simplifiedProperties)) {
      // Recupera as propriedades padrão da UF
      $prop = $simplifiedProperties[ $stateOfRegistration ];

      // Caso a UF tenha uma definição padrão, então aplica
      if (count($prop) > 0) {
        // O tamanho do código
        $size = $prop['size'];

        // Os pesos usados no cálculo (por dígito verificador)
        $weights = $prop['weights'];

        // Verifica se a UF usa um módulo diferente do padrão
        if (array_key_exists('modulo', $prop)) {
          $modulo = $prop['modulo'];
        }

        // Os dígitos verificadores
        $checkDigits = array_keys($weights);

        // Código do estado: os primeiros dois algarísmos (quando
        // utilizado)
        $stateCodes = $prop['stateCodes'];
      }

      // Determina outros critérios conforme a unidade da federação
      switch ($stateOfRegistration) {
        case 'AP':
          // Amapá (AP)

          // Despreza-se o dígito verificador, trabalhando-se somente
          // com os demais 8 dígitos
          $numberWithoutDigit = substr($number, 0, -1);

          // Para calcular o dígito verificador, define-se dois valores,
          // p e d, de acordo com as seguintes faixas de Inscrição
          // Estadual:
          //   - De 03000001 a 03017000 => p = 5 e d = 0
          //   - De 03017001 a 03019022 => p = 9 e d = 1
          //   - De 03019023 em diante  => p = 0 e d = 0
          if ( ($numberWithoutDigit >= 3000001) &&
               ($numberWithoutDigit <= 3017000) ) {
            $p = 5;
            $d = 0;
          } elseif ( ($numberWithoutDigit >= 3017001) &&
                     ($numberWithoutDigit <= 3019022) ) {
            $p = 9;
            $d = 1;
          } else {
            $p = 0;
            $d = 0;
          }

          break;
        case 'BA':
          // Bahia (BA)

          // Não temos códigos de estado
          $stateCodes = [];

          // Temos inscrições estaduais com 8 e 9 algarísmos. Se a
          // quantidade for diferente da esperada, rejeita
          if (in_array(strlen($number), [ 8, 9 ])) {
            // Determina o tamanho do código
            $size = strlen($number);

            // Determina os dígitos verificadores
            $checkDigits = [ $size - 1, $size ];
          } else {
            // Se a quantidade de algarísmos for diferente da esperada,
            // então rejeita
            return false;
          }

          // A fórmula do cálculo dos dígitos verificadores da inscrição
          // estadual especifica que:
          //   - Para inscrições cujo primeiro algarísmo do número é 0,
          //     1, 2, 3, 4, 5 ou 8 o cálculo é realizado pelo módulo 10
          //   - Para inscrições cujo primeiro dígito do número é  6, 7
          //     ou 9 o cálculo é realizado pelo módulo 11.
          // Determinamos o módulo a ser usado
          $range1     = ['0', '1', '2', '3', '4', '5', '8'];
          $range2     = ['6', '7', '9'];
          $firstDigit = substr($number, 0, 1);
          if (in_array($firstDigit, $range1)) {
            $modulo = 10;
          } elseif (in_array($firstDigit, $range2)) {
            $modulo = 11;
          }

          // O cálculo é ligeiramente diferente dos demais estados, já
          // que calcula-se o segundo dígito primeiramente e depois o
          // primeiro usando o valor do segundo dígito. Desta forma,
          // inverte os dígitos para analisar
          $temp = $number[ $size - 2 ];
          $number[ $size - 2 ] = $number[ $size - 1 ];
          $number[ $size - 1 ] = $temp;

          // Determina os pesos usados no cálculo
          if ($size == 8) {
            $weights = [
              '7' => [ 7, 6, 5, 4, 3, 2 ],
              '8' => [ 8, 7, 6, 5, 4, 3, 2 ]
            ];
          } else {
            $weights = [
              '9' => [ 8, 7, 6, 5, 4, 3, 2 ],
              '8' => [ 9, 8, 7, 6, 5, 4, 3, 2 ]
            ];
          }

          break;
        case 'PE':
          // Pernambuco (PE)

          // Não temos códigos de estado
          $stateCodes = [];

          // Temos inscrições estaduais com 9 e 14 algarísmos. Se a
          // quantidade for diferente da esperada, rejeita
          if (in_array(strlen($number), [ 9, 14 ])) {
            // Determina o tamanho do código
            $size = strlen($number);

            // Conforme o tamanho, determina as demais características
            switch ($size) {
              case  9:
                // Temos dois dígitos verificadores
                $checkDigits = [ $size - 1, $size ];

                break;
              default:
                // Temos um dígito verificador
                $checkDigits = [ $size ];

                break;
            }

            // Os pesos usados no cálculo (por dígito verificador)
            $weights = [
              '8' => [ 8, 7, 6, 5, 4, 3, 2 ],
              '9' => [ 9, 8, 7, 6, 5, 4, 3, 2 ],
              '14' => [ 5, 4, 3, 2, 1, 9, 8, 7, 6, 5, 4, 3, 2 ]
            ];

          } else {
            // Se a quantidade de algarísmos for diferente da esperada,
            // então rejeita
            return false;
          }

          break;
        case 'RN':
          // Rio Grande do Norte (RN)

          // Códigos de estado
          $stateCodes = [ '20' ];

          // Temos inscrições estaduais com 9 e 10 algarísmos. Se a
          // quantidade for diferente da esperada, rejeita
          if (in_array(strlen($number), [ 9, 10 ])) {
            // Determina o tamanho do código
            $size = strlen($number);

            // O dígito verificador sempre é o último dígito
            $checkDigits = [ $size ];

            // Os pesos usados no cálculo (por dígito verificador)
            $weights = [
              '9' => [ 9, 8, 7, 6, 5, 4, 3, 2 ],
              '10' => [ 9, 8, 7, 6, 5, 4, 3, 2 ]
            ];
          } else {
            // Se a quantidade de algarísmos for diferente da esperada,
            // então rejeita
            return false;
          }

          break;
        case 'RO':
          // Rondônia (RO)

          // Não temos códigos de estado
          $stateCodes = [];

          // Temos inscrições estaduais com 9 e 14 algarísmos. Se a
          // quantidade for diferente da esperada, rejeita
          if (in_array(strlen($number), [ 9, 14 ])) {
            // Determina o tamanho do código
            $size = strlen($number);

            if ($size == 9) {
              // Os 3 primeiros dígitos são desconsiderados, pois
              // correspondem ao código da cidade, então remove do
              // código para permitir a correta verificação
              $number = substr($number, 3);
              $size = 6;
            }

            // O dígito verificador sempre é o último dígito
            $checkDigits = [ $size ];

            // Os pesos usados no cálculo (por dígito verificador)
            $weights = [
              '6' => [ 6, 5, 4, 3, 2 ],
              '14' => [ 6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 ]
            ];
          } else {
            // Se a quantidade de algarísmos for diferente da esperada,
            // então rejeita
            return false;
          }

          break;
        case 'SP':
          // São Paulo (SP)

          // Não temos códigos de estado
          $stateCodes = [];

          // Temos dois formatos
          if (substr($number, 0, 1) == 'P') {
            // Inscrição estadual de produtor rural

            // Os primeiros dois algarísmos são sempre P0 (código do
            // estado), então se contém valores inválidos, rejeita
            if (substr($number, 0, 2) != 'P0') {
              return false;
            }

            // O tamanho do código
            $size = 13;

            // O dígito verificador é o 10º dígito
            $checkDigits = [ 10 ];

            // Os pesos usados no cálculo (por dígito verificador)
            $weights = [
              '10' => [ 0, 1, 3, 4, 5, 6, 7, 8, 10]
            ];
          } else {
            // Inscrição estadual de indústrias e comércios

            // O tamanho do código
            $size = 12;

            // Os dígitos verificadores
            $checkDigits = [ 9, 12 ];

            // Os pesos usados no cálculo (por dígito verificador)
            $weights = [
              '9' => [ 1, 3, 4, 5, 6, 7, 8, 10],
              '12' => [ 3, 2, 10, 9, 8, 7, 6, 5, 4, 3, 2 ]
            ];
          }
          break;
      }

      // Verifica a consistência do tamanho do número
      if (strlen($number) != $size) {
        return false;
      }

      // Verifica o código do estado (UF) presente na inscrição
      // estadual, quando necessário
      if (count($stateCodes) > 0) {
        if ($stateOfRegistration == 'TO') {
          // O "3" e "4" algarísmos não entram no cálculo, porém podem
          // assumir somente os seguintes valores:
          //   01 = Produtor Rural (não possui CGC)
          //   02 = Industria e Comércio
          //   03 = Empresas Rudimentares
          //   99 = Empresas do Cadastro Antigo (SUSPENSAS)
          // Então se contém valores inválidos, rejeita
          if ( !in_array(substr($number, 2, 2), $stateCodes) ) {
            return false;
          }
        } else {
          if ( !in_array(substr($number, 0, 2), $stateCodes) ) {
            return false;
          }
        }
      }

      // Calcula cada dígito verificador separadamente
      foreach ($checkDigits as $checkDigit)
      {
        // Inicializa a soma
        $sum = 0;
        $str = '';
        if ($stateOfRegistration == 'AP') {
          $sum = $p;
        }

        // Para calcular o dígito verificador, multiplica-se cada
        // algarísmo pelo respectivo peso atribuído à ele, excluindo-se
        // o próprio dígito verificador
        for ($i = 0, $c = ($checkDigit - 1); $i < $c; $i++) {
          if (is_numeric($number[$i])) {
            if ( ($stateOfRegistration == 'MG') && ($checkDigit == 12) ) {
              // Soma-se os algarismos (não os produtos) do resultado
              // obtido, então armazena os resultados numa string
              $str .= $number[$i] * $weights[$checkDigit][$i];
            } else {
              // Soma-se os produtos dos algarísmos pelo seu peso
              // acumulativamente
              $sum += $number[$i] * $weights[$checkDigit][$i];
            }
          }
        }

        if ( ($stateOfRegistration == 'MG') && ($checkDigit == 12) ) {
          // Soma-se os algarismos (não os produtos) do resultado obtido
          for ($i = 0, $c = strlen($str); $i < $c; $i++) {
            $sum += $str[$i];
          }
        }

        if (in_array($stateOfRegistration, ['RR','SP'])) {
          // Calculamos o resto da divisão entre o resultado da soma
          // acima e o respectivo módulo
          $digit = $sum % $modulo;
        } else if ( ($stateOfRegistration == 'MG') && ($checkDigit == 12) ) {
          // Calculamos a subtração entre o valor da primeira dezena
          // exata imediatamente superior o resultado da soma acima
          $dicker = ceil($sum / 10) * 10;
          $digit  = $dicker - $sum;
        } else {
          // Calculamos a diferença entre o módulo e o resto da divisão
          // entre o o resultado da soma acima e o respectivo módulo
          $digit = $modulo - ($sum % $modulo);
        }

        if ($stateOfRegistration == 'AP') {
          if ($digit == 10) {
            // Caso a diferença for de 10 o dígito é 0
            $digit = 0;
          } elseif ($digit == 11) {
            // Caso a diferença for de 11 o dígito é o valor 'd'
            // determinado acima
            $digit = $d;
          }
        } else {
          // Caso a diferença tenha mais de um dígito, força o resultado
          // para 0
          if ($digit > 9) {
            $digit = 0;
          }
        }

        // Verifica neste ponto se o dígito verificador corresponde ao
        // calculado
        if ($digit != $number[ $checkDigit - 1 ]) {
          return false;
        }
      }

      return true;
    } else {
      // Unidade da Federação inválida
      throw new InvalidArgumentException("A Unidade da Federação "
          . "{$stateOfRegistration} é inválida")
      ;
    }

    return false;
  }
}
