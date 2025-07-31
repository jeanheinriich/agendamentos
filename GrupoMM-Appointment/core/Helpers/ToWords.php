<?php
/*
 * This file is part of Slim Framework 3 skeleton application.
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
 * Essa é uma classe que permite transcrever um valor numérico para a
 * sua representação por extenso.
 * 
 * ---------------------------------------------------------------------
 * As regras para a escrita de números cardinais por extenso:
 * ---------------------------------------------------------------------
 * 
 * 1. Deverá ser usada a conjunção 'e' entre as centenas, dezenas e
 * unidades:
 * 
 *   Trinta e sete (37)
 *   Duzentos e trinta e sete (237)
 *   Duzentos e sete (207)
 * 
 * 2. Na separação dos milhares e das centenas, a conjunção 'e' apenas
 * deverá ser usada quando o número termina nas centenas:
 * 
 *   Cinco mil e quinhentos (5500)
 *   Mil e trezentos (1300)
 *   Oito mil e cem (8100)
 * 
 * 3. Quando o número começa nos milhares e termina nas dezenas ou nas
 * unidades, não deverá ser usada a conjunção e na separação das
 * centenas, apenas na separação das restantes ordens:
 * 
 *   Dois mil cento e vinte e cinco (2125)
 *   Sete mil quatrocentos e noventa e um (7491)
 *   Seis mil quinhentos e dez (6510)
 *   Nove mil e trinta (9030)
 *   Nove mil e três (9003)
 * 
 * 4. Quando o número é muito grande, a leitura é feita por classes. A
 * conjunção e é usada para separar os algarismos da classe mas não
 * deverá ser usada para separar as classes. Não deverá ser usada nem a
 * conjunção e nem a vírgula:
 * 
 *   Trezentos e setenta e oito milhões vinte e sete mil trezentos e
 *   doze (378 027 312)
 *   Duzentos e nove bilhões seiscentos e setenta milhões cento e vinte
 *   e três mil quatrocentos e dezoito (209 670 123 418)
 * 
 * ---------------------------------------------------------------------
 * As regras para a escrita de números decimais por extenso:
 * ---------------------------------------------------------------------
 *
 * Os números decimais deverão ser lidos à partir da parte inteira,
 * seguida da parte decimal, acompanhada das expressões:
 *   décimos.................: quando houver uma casa decimal;
 *   centésimos............. : quando houver duas casas decimais;
 *   milésimos.............. : quando houver três casas decimais;
 *   décimos de milésimos... : quando houver quatro casas decimais;
 *   centésimos de milésimos : quando houver cinco casas decimais e,
 *     assim sucessivamente.
 * 
 * Caso tenha a parte inteira, acrescentamos a expressão 'inteiro' antes
 * da parte decimal seguido da conjunção 'e' e da respectiva parte
 * decimal. Exemplos:
 * 
 *   Oito décimos (0,8);
 *   Dois inteiros e dois décimos (2,2);
 *   Oitenta e cinco centésimos (0,85);
 *   Um inteiro e seiscentos e cinquenta e dois milésimos (1,652).
 *   
 * Principais grandezas decimais:
 * 
 *   Um décimo (0,1);
 *   Um centésimo (0,01);
 *   Um milésimo (0,001);
 *   Um décimo de milésimo (0,0001);
 *   Um centésimo de milésimo (0,00001);
 *   Um milionésimo (0,000001);
 *   Um décimo de milionésimo (0,0000001);
 *   Um centésimo de milionésimo (0,00000001);
 *   Um bilionésimo (0,000000001). 
 *
 * ---------------------------------------------------------------------
 * As regras para a escrita de valores monetários por extenso
 * ---------------------------------------------------------------------
 * 
 * A escrita de valores monetários deverá ser realizada,
 * preferencialmente, em algarismos. Quando a sua escrita por extenso
 * for realmente necessária, como em cheques, deverá obedecer às mesmas
 * regras dos números cardinais.
 * 
 *   R$ 1235,00 = mil duzentos e trinta e cinco reais
 *   R$ 1235,20 = mil duzentos e trinta e cinco reais e vinte centavos
 *   R$ 13 580,00 = treze mil quinhentos e oitenta reais
 *   R$ 13 580,45 = treze mil quinhentos e oitenta reais e quarenta e
 *   cinco centavos
 *   
 * Deverá ser utilizada a preposição 'de' na escrita de números redondos
 * a partir dos milhões e na escrita de números inferiores a um real,
 * quando só houver a indicação dos centavos.
 * 
 *   R$ 10 000 000,00 = dez milhões de reais
 *   R$ 50 000 000,00 = cinquenta milhões de reais
 *   R$ 1 000 000 000,00 = um bilhão de reais
 *   R$ 0,01 = um centavo de real
 *   R$ 0,30 = trinta centavos de real
 *   R$ 0,95 = noventa e cinco centavos de real
 * 
 * ---------------------------------------------------------------------
 * As regras para a escrita de valores em percentagem por extenso
 * ---------------------------------------------------------------------
 * 
 * Embora exista o substantivo "porcento", que tem o significado de
 * percentagem ou porcentagem (comissão; quantia paga ou recebida, na
 * razão de tantos por cento; fração por cento de qualquer coisa), o
 * símbolo % (por cento) antecedido por um número é uma locução que
 * significa que um valor corresponde a "tantas partes" de um total de
 * 100 partes.
 *
 * Quando o valor a ser expresso for inteiro, segue a mesma regra para a
 * escrita de números por extenso, acrescido da expressão 'por cento'.
 * A expressão por cento significa em cada cento, ou seja, em cada
 * centena ou cem.
 *
 *   35%: trinta e cinco por cento.
 *    2%: dois por cento.
 *    
 * Quando o valor não for inteiro, devemos separar a porção inteira do
 * valor, se ouver, e acrescentar a expressão inteiro (ou inteiros),
 * seguido da conjunção 'e' e da representação da parte fracionária, bem
 * como da expressão 'por cento' no final
 * 
 *   0,2%: dois décimos por cento.
 *   0,26%: vinte e seis centésimos por cento.
 *   35,2%: trinta e cinco inteiros e dois décimos por cento.
 *   35,26%: trinta e cinco inteiros e vinte e seis centésimos por cento.
 *   35,267%: trinta e cinco inteiros e duzentos e sessenta e sete
 *   milésimos por cento.
 *   35,2678%: trinta e cinco inteiros e dois mil, seiscentos e setenta
 *   e oito décimos de milésimo por cento.
 * 
 * ---------------------------------------------------------------------
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Helpers;

use InvalidArgumentException;
use OutOfBoundsException;

class ToWords
{
  /**
   * A palavra para valors negativos
   *
   * @var string
   */
  private $minus = 'negativo';

  /**
   * A conjunção a ser usada entre palavras na transcrição de valores.
   *
   * @var string
   */
  private $separator = ' e ';

  /**
   * A preposição a ser usada entre palavras na transcrição de valores
   * monetários.
   *
   * @var string
   */
  private $currencySeparator = ' de ';

  /**
   * Os nomes por extenso para os valores cujos sufixos não seguem o
   * padrão (apenas os números entre 11 e 19).
   *
   * @var string[]
   */
  private $nonStandardSuffix = [
    '',
    'onze',
    'doze',
    'treze',
    'quatorze',
    'quinze',
    'dezesseis',
    'dezessete',
    'dezoito',
    'dezenove'
  ];

  /**
   * A matriz que contém a transcrição dos valores (indexados pelos
   * próprios dígitos).
   *
   * @var string
   */
  private $words = [
    // Unidades
    [
      'zero',
      'um',
      'dois',
      'três',
      'quatro',
      'cinco',
      'seis',
      'sete',
      'oito',
      'nove'
    ],
    // Dezenas (o zero não é exibido)
    [
      '',
      'dez',
      'vinte',
      'trinta',
      'quarenta',
      'cinquenta',
      'sessenta',
      'setenta',
      'oitenta',
      'noventa'
    ],
    // Centenas (o zero não é exibido) e a palavra para o valor 100 é
    // trabalhada separadamente
    [
      '',
      'cento',
      'duzentos',
      'trezentos',
      'quatrocentos',
      'quinhentos',
      'seiscentos',
      'setecentos',
      'oitocentos',
      'novecentos'
    ],
  ];

  /**
   * A matriz que contém a grandeza do valor a ser representado com a
   * sua respectiva maneira de escrita tanto no singular quanto no
   * plural para as classes de um valor. Separamos o valor de 3 em 3
   * dígitos (classe) e determinamos a transcrição da grandeza da classe
   * através desta matriz
   *
   * @var string[]
   */
  private $magnitude = [
    [ '', '' ],
    [ 'mil', 'mil' ],
    [ 'milhão', 'milhões' ],
    [ 'bilhão', 'bilhões' ],
    [ 'trilhão', 'trilhões' ],
    [ 'quatrilhão', 'quatrilhões' ],
    [ 'quintilhão', 'quintilhões' ],
    [ 'sextilhão', 'sextilhões' ],
    [ 'septilhão', 'septilhões' ],
    [ 'octilhão', 'octilhões' ],
    [ 'nonilhão', 'nonilhões' ],
    [ 'decilhão', 'decilhões' ],
    [ 'undecilhão', 'undecilhões' ],
    [ 'dodecilhão', 'dodecilhões' ],
    [ 'tredecilhão', 'tredecilhões' ],
    [ 'quatuordecilhão', 'quatuordecilhões' ],
    [ 'quindecilhão', 'quindecilhões' ],
    [ 'sedecilhão', 'sedecilhões' ],
    [ 'septendecilhão', 'septendecilhões' ],
  ];

  /**
   * A matriz que contém a transcrição dos valores decimais (indexados
   * pelos próprios dígitos).
   *
   * @var string
   */
  private $decimalWords = [
    '',
    'décimo',
    'centésimo'
  ];

  /**
   * A matriz que contém a grandeza do valor a ser representado com a
   * sua respectiva maneira de escrita tanto no singular quanto no
   * plural para as classes de um valor decimal. Separamos o valor de
   * 3 em 3 dígitos (classe) e determinamos a transcrição da grandeza da
   * classe através desta matriz
   *
   * @var string[]
   */
  private $smallness = [
    [ '', '' ],
    [ 'milésimo', 'milésimos' ],
    [ 'milionésimo', 'milionésimos' ],
    [ 'bilionésimo', 'bilionésimo' ],
    [ 'trilhonésimo', 'trilhonésimos' ],
    [ 'quatrilhonésimo', 'quatrilhonésimos' ],
    [ 'quintilhonésimo', 'quintilhonésimos' ],
    [ 'sextilhonésimo', 'sextilhonésimos' ],
    [ 'septilhonésimo', 'septilhonésimos' ],
    [ 'octilhonésimo', 'octilhonésimos' ],
    [ 'nonilhonésimo', 'nonilhonésimos' ],
    [ 'decilhonésimo', 'decilhonésimos' ],
    [ 'undecilhonésimo', 'undecilhonésimos' ],
    [ 'dodecilhonésimo', 'dodecilhonésimos' ],
    [ 'tredecilhonésimo', 'tredecilhonésimos' ],
    [ 'quatuordecilhonésimo', 'quatuordecilhonésimos' ],
    [ 'quindecilhonésimo', 'quindecilhonésimos' ],
    [ 'sedecilhonésimo', 'sedecilhonésimos' ],
    [ 'septendecilhonésimo', 'septendecilhonésimos' ],
  ];

  private $currencyNames = [
    'BRL' => [['real', 'reais'], ['centavo', 'centavos']],
    'USD' => [['dólar', 'dólares'], ['centavo', 'centavos']],
    'EUR' => [['euro', 'euros'], ['centavo', 'centavos']],
    'GBP' => [['libra esterlina', 'libras esterlinas'], ['centavo', 'centavos']],
    'JPY' => [['iene', 'ienes'], ['centavo', 'centavos']],
    'ARS' => [['peso argentino', 'pesos argentinos'], ['centavo', 'centavos']],
    'MXN' => [['peso mexicano', 'pesos mexicanos'], ['centavo', 'centavos']],
    'UYU' => [['peso uruguaio', 'pesos uruguaios'], ['centavo', 'centavos']],
    'PYG' => [['guarani', 'guaranis'], ['centavo', 'centavos']],
    'BOB' => [['boliviano', 'bolivianos'], ['centavo', 'centavos']],
    'CLP' => [['peso chileno', 'pesos chilenos'], ['centavo', 'centavos']],
    'COP' => [['peso colombiano', 'pesos colombianos'], ['centavo', 'centavos']],
    'CUP' => [['peso cubano', 'pesos cubanos'], ['centavo', 'centavos']],
  ];

  /**
   * Transcreve um valor, de maneira recursiva.
   * 
   * @param string $chunk
   *   O valor a ser transcrito
   *
   * @return array
   *   A matriz de palavras com a transcrição do valor
   */
  private function parseChunk(string $chunk): array
  {
    // Como esta função é recursiva, interrompe quando o valor não puder
    // mais ser transcrito
    if (!$chunk) {
      return [];
    }

    // O número 100 é um caso especial, então lida separadamente.
    if ($chunk == 100) {
      return [ 'cem' ];
    }

    // Verificamos se a dezena está dentro da faixa em que os sufixos
    // não seguem o padrão
    if (($chunk < 20) && ($chunk > 10)) {
      // O valor está nesta faixa, então usa os sufixos não padrão para
      // transcrever o valor
      return [ $this->nonStandardSuffix[ $chunk % 10 ] ];
    }

    // Obtemos a palavra para transcrever o valor
    $i = strlen($chunk) - 1;
    $n = (int) $chunk[0];
    $word = $this->words[$i][$n];

    return array_merge([$word], $this->parseChunk(substr($chunk, 1)));
  }

  /**
   * Analisa se a conjunção 'e' deverá ser usada. É necessária a
   * conjunção 'e' entre as centenas, dezenas e unidades de um valor.
   * 
   * @param array $chunks
   *
   * @return bool
   */
  private function mustSeparate(array $chunks): bool
  {
    $found = null;

    // Percorremos os segmentos de 3 dígitos do valor a ser analizado
    foreach ($chunks as $chunk) {
      if ($chunk !== '000') {
        // O segmento do valor contém um bloco diferente de zero, então
        // interrompemos
        break;
      }
    }

    // Se o segmento sendo analizado for menor do que cem ou não for
    // divisível por 100, sinaliza de que precisamos incluir a conjunção
    if ($chunk < 100 || !($chunk % 100)) {
      return true;
    }

    // Todas as demais condições não inclui a conjunção 'e'
    return false;
  }

  /**
   * Transcreve um determinado valor cardinal para a sua representação
   * por extenso.
   * 
   * @param int $num
   *   O valor cardinal a ser transcrito
   *
   * @return string
   *   O valor por extenso
   * 
   * @throws OutOfBoundsException
   *   Em caso do valor ser absurdamente grande e ultrapassar os limites
   *   de transcrição desta classe.
   */
  private function cardinal(int $num): string
  {
    $neg = 0;
    $amountInWords = [];

    if ($num < 0) {
      // O valor é negativo
      $amountInWords[] = $this->minus;
      $num = -$num;
      $neg = 1;
    }

    // Remove os zeros à esquerda e espaços e adiciona o separador de
    // milhares. Usamos o separador para permitir separas as classes do
    // valor a ser transcrito
    $num = number_format($num, 0, '.', '.');

    if ($num == 0) {
      // O valor é zero, então simplesmente retorna
      return $this->words[0][0];
    }

    // Divide-se o valor em blocos de 3 dígitos, invertendo a matriz de
    // resultado para permitir processar o valor da direita para a
    // esquerda
    $chunks = array_reverse(explode(".", $num));

    // Percorremos os pedaços fazendo as transcrições
    foreach ($chunks as $index => $chunk) {
      // Verifica se a porção sendo analizada está dentro da faixa
      // permitida
      if (!array_key_exists($index, $this->magnitude)) {
        throw new OutOfBoundsException("O número {$num} está fora da "
          . "faixa permitida"
        );
      }

      // Verifica se a porção contém zero
      if ($chunk == 0) {
        // Quando uma porção contém zero, ela é desconsiderada na
        // transcrição do valor para sua representação por extenso,
        // então apenas continua
        continue;
      }

      // Obtém a magnitude da grandeza a ser transcrita e adiciona
      $magnitude = $this->magnitude[$index][(($chunk > 1)?1:0)];
      $amountInWords[] = $magnitude;

      // Adiciona a transcrição da grandeza
      $word = array_filter($this->parseChunk($chunk));
      $amountInWords[] = implode($this->separator, $word);
    }

    // Verifica se devemos incluir a conjunção separadora na última
    // porção de nosso texto transcrito
    if ( ((count($amountInWords) > 2) || $neg) &&
         $this->mustSeparate($chunks) ) {
      $amountInWords[1 + $neg] = trim($this->separator . $amountInWords[1 + $neg]);
    }

    // Invertemos a matriz contendo o valor transcrito para permitir a
    // sua escrita
    $amountInWords = array_reverse(array_filter($amountInWords));

    // Convertemos a matriz em um texto
    return implode(' ', $amountInWords);
  }

  /**
   * Transcreve a porção decimal de um determinado valor para a sua
   * representação por extenso.
   * 
   * @param string $num
   *   A porção decimal do valor a ser transcrito
   *
   * @return string
   *   O valor por extenso
   * 
   * @throws OutOfBoundsException
   *   Em caso do valor ser absurdamente pequeno e ultrapassar os
   *   limites de transcrição desta classe.
   */
  private function decimal(string $num): string
  {
    $amountInWords = [];

    if ($num == 0) {
      // O valor é zero, então simplesmente retorna
      return '';
    }

    // Eliminamos espaços e zeros à direita do valor a ser convertido
    $num = rtrim(trim($num), '0');

    // Contamos a profundidade do valor a ser transcrito
    $i = intdiv(strlen($num), 3);
    $n = strlen($num) % 3;

    // Acrescentamos a representação por extenso da porção fracionária
    // do valor a ser transcrito
    $amountInWords[] = $this->cardinal($num);

    if ($n > 0) {
      // Adiciona a transcrição da grandeza
      $amountInWords[] = $this->decimalWords[$n] . (($num > 1)?'s':'');

      if ($i > 0) {
        // Acrescentamos a preposição 'de'
        $amountInWords[] = 'de';
      }
    }

    if ($i > 0) {
      // Obtém a magnitude da grandeza a ser transcrita e adiciona
      $amountInWords[] = $this->smallness[$i][(($num > 1)?1:0)];
    }

    // Convertemos a matriz em um texto
    return implode(' ', $amountInWords);
  }

  /**
   * Transcreve um determinado valor inteiro para a sua representação
   * por extenso.
   * 
   * @param int $value
   *   O valor a ser transcrito
   * 
   * @return string
   *   O valor por extenso
   * @throws OutOfBoundsException
   *   Em caso do valor ser absurdamente grande e ultrapassar os limites
   * de transcrição desta classe.
   */
  public function intValue(int $value): string
  {
    return $this->cardinal($value);
  }

  /**
   * Transcreve um determinado valor em percentagem para a sua
   * representação por extenso.
   * 
   * @param float $value
   *   O valor a ser transcrito
   * @param int $decimals (opcional)
   *   A quantidade de casas decimais a serem consideradas (o padrão é
   *   2 casas decimais)
   * 
   * @return string
   *   O valor por extenso
   * @throws OutOfBoundsException
   *   Em caso do valor ser absurdamente grande e ultrapassar os limites
   * de transcrição desta classe.
   */
  public function percentageValue(float $value,
    int $decimals = 2): string
  {
    if ($value == 0) {
      // Se o valor for zero, apenas retorna
      return "zero por cento";
    }

    $negative = false;
    if ($value < 0) {
      // Se o valor é negativo, então acrescentamos a expressão no final
      $negative = true;

      // Invertemos o sinal do valor
      $value = -$value;
    }

    // Separamos a parte inteira da porção decimal do valor
    $num = number_format($value, $decimals, ';', '');
    list($cardinalValue, $fractionValue) = explode(";", $num);

    // Convertemos cada parte do valor
    $toWords = '';
    if ($cardinalValue > 0) {
      $toWords .= $this->cardinal($cardinalValue);

      if ($fractionValue > 0) {
        // Acrescentamos a expressão 'inteiro' quando houver a parte
        // fracionária
        $toWords .= ($fractionValue > 1)
          ? ' inteiros e '
          : ' inteiro e ';
      }
    }
    
    if ($fractionValue > 0) {
      $toWords .= $this->decimal($fractionValue);
    }

    // No final acrescentamos a expressão por cento
    $toWords .= ' por cento';

    if ($negative) {
      $toWords .= ' negativo';
    }

    return $toWords;
  }

  /**
   * Transcreve um determinado valor monetário para a sua representação
   * por extenso.
   * 
   * @param float $value
   *   O valor a ser transcrito
   * @param string $currency (opcional)
   *   A moeda em que o valor deve ser representado (padrão = 'BRL')
   * 
   * @return string
   *   O valor por extenso
   * 
   * @throws InvalidArgumentException
   *   Em caso da moeda não estar dentro das moedas permitidas.
   * @throws OutOfBoundsException
   *   Em caso do valor ser absurdamente grande e ultrapassar os limites
   * de transcrição desta classe.
   */
  public function monetaryValue(
    float $value,
    string $currency = 'BRL'
  ): string
  {
    $negative = 0;
    $amountInWords = [];
    $noDecimals = false;

    // Separamos a parte inteira da porção decimal do valor
    $num = number_format($value, 2, ';', '');
    list($cardinalValue, $fractionValue) = explode(";", $num);

    // Verifica se o valor é negativo
    if (substr($cardinalValue, 0, 1) == '-') {
      $cardinalValue = -$cardinalValue;
      $negative = 1;
    }

    // Remove os zeros à esquerda e espaços e adiciona o separador de
    // milhares. Usamos o separador para permitir separas as classes do
    // valor a ser transcrito
    $num = number_format($fractionValue, 0, '', '');

    // Verifica se a moeda é uma das disponíveis para transcrição
    $currency = strtoupper($currency);
    if (!isset($this->currencyNames[$currency])) {
      throw new InvalidArgumentException(
          sprintf('A moeda "%s" não está disponível para transcrição',
            $currency, get_class($this))
      );
    }

    // Obtemos os nomes da moeda tanto no singular quanto no plural,
    // incluíndo a parte fracionária
    $currencyNames = $this->currencyNames[$currency];

    if ($num > 0) {
      // Obtemos a representação por extenso da porção inteira do valor
      // a ser transcrito
      $amountInWords[] = $this->cardinal($num);

      // Analisa se o número entra na regra em que devemos utilizada a
      // preposição 'de' na escrita de números redondos a partir dos
      // milhões
      if (substr($num, -6) == '000000') {
        $amountInWords[] = trim($this->currencySeparator);
      }

      // Verifica o uso do singular ou do plural do valor monetário
      // sendo representado e acrescenta
      $amountInWords[] = $currencyNames[0][(($num > 1)?1:0)];
    }

    // Acrescentamos a transcrição da parte fracionária, se necessário
    $fractionValue = (int) $fractionValue;
    if ($fractionValue) {
      // Remove os zeros à esquerda e espaços e adiciona o separador de
      // milhares
      $num = number_format($fractionValue, 0, '.', '.');

      // Verificamos se o número está dentro da faixa permitida (entre 0
      // e 99 centavos)
      if ($num < 0 || $num > 99) {
        throw new OutOfBoundsException("A parte fracionária está fora "
          . "da faixa permitida (entre 01 e 99 centavos)"
        );
      }

      // Verifica se temos algum valor já transcrito
      if (count($amountInWords) > 0) {
        // Acrescentamos o separador
        $amountInWords[] = trim($this->separator);
      } else {
        // Não temos a parte inteira do valor, então tratamos de maneira
        // diferente, já que devemos utilizar a preposição 'de' na
        // escrita de números inferiores a uma unidade monetária, quando
        // só houver a indicação dos centavos desta moeda
        $noDecimals = true;
      }

      // Acrescentamos a representação por extenso da porção fracionária
      // do valor a ser transcrito
      $amountInWords[] = $this->cardinal($num);

      // Verifica o uso do singular ou do plural dos centavos do valor
      // monetário sendo representado e acrescenta
      $amountInWords[] = $currencyNames[1][(($num > 1)?1:0)];

      if ($noDecimals) {
        // Acrescentamos a preposição 'de' na escrita de números
        // inferiores a uma unidade monetária, quando só houver a
        // indicação dos centavos desta moeda e a própria representação
        // da moeda
        $amountInWords[] = trim($this->currencySeparator);
        $amountInWords[] = $currencyNames[0][0];
      }
    }

    // Acrescentamos a transcrição de um valor negativo
    if ($negative) {
      $amountInWords[] = $this->minus;
    }

    // Convertemos a matriz em um texto
    return implode(' ', $amountInWords);
  }
}
