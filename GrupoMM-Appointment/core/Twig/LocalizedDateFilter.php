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
 * Classe responsável por extender o Twig permitindo a inclusão do
 * filtro 'localizedDate' que permite a renderização correta de um
 * campo data localizado para a nossa região.
 */

namespace Core\Twig;

use DateTime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class LocalizedDateFilter
  extends AbstractExtension
{
  /**
   * O construtor de nosso filtro
   */
  public function __construct() { }
  
  /**
   * Recupera os filtros para o sistema Twig.
   * 
   * @return array
   */
  public function getFilters()
  {
    return [
      new TwigFilter('localizedDate', [$this, 'localizedDateFilter']),
    ];
  }
  
  /**
   * Recupera uma data localizada para a região.
   * 
   * @param string $date
   *   A data a ser convertida
   * @param string $format
   *   O formato a ser convertido
   * 
   * @return string
   *   A data localizada
   */
  public function localizedDateFilter($date, $format)
  {
    $dayOfWeek = [
      '1' => ['Seg', 'Segunda-feira'],
      '2' => ['Ter', 'Terça-feira'],
      '3' => ['Qua', 'Quarta-feira'],
      '4' => ['Qui', 'Quinta-feira'],
      '5' => ['Sex', 'Sexta-feira'],
      '6' => ['Sáb', 'Sábado'],
      '7' => ['Dom', 'Domingo']
    ];
    $month = [
      '1' => ['Jan', 'Janeiro'],
      '2' => ['Fev', 'Fevereiro'],
      '3' => ['Mar', 'Março'],
      '4' => ['Abr', 'Abril'],
      '5' => ['Mai', 'Maio'],
      '6' => ['Jun', 'Junho'],
      '7' => ['Jul', 'Julho'],
      '8' => ['Ago', 'Agosto'],
      '9' => ['Set', 'Setembro'],
      '10' => ['Out', 'Outubro'],
      '11' => ['Nov', 'Novembro'],
      '12' => ['Dez', 'Dezembro']
    ];
    $short = 0;
    $long  = 1;
    
    // Formata a data e hora para exibição
    $formatter = new DateTime($date ?? 'now');
    
    if ($format === 'long') {
      // Formata a data/hora no padrão dia da semana, dia, mês, ano e
      // hora
      return $dayOfWeek[$formatter->format('N')][$long]
        . ', ' . $formatter->format('j') . ' de '
        . $month[$formatter->format('n')][$long] . ' de '
        . $formatter->format('Y') . ' às ' . $formatter->format('H:i:s')
      ;
    } else if ($format === 'short') {
      // Formata a data/hora no padrão dia da semana, dia, mês, ano e
      // hora de maneira abreviada
      return $dayOfWeek[$formatter->format('N')][$short]
        . ', ' . $formatter->format('j') . ' de '
        . $month[$formatter->format('n')][$short] . ' de '
        . $formatter->format('Y') . ' às ' . $formatter->format('H:i:s')
      ;
    } else if ($format === 'date') {
      // Formata a data/hora no padrão brasileiro (DD/MM/YYYY)
      $formatter = new DateTime($date);
      
      return $formatter->format('d/m/Y');
    } else {
      // Formata a data/hora no padrão brasileiro (DD/MM/YYYY H:i:s)
      $formatter = new DateTime($date);
      
      return $formatter->format('d/m/Y H:i:s');
    }
  }
}
