<?php
/*
 * This file is part of tests of Extension Library.
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
 * Conjunto de testes de conversão para horas noturnas.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Tests\Core\RoadTrip;

use Core\RoadTrip\NightHours;
use PHPUnit\Framework\TestCase;

class NightHoursTest
  extends TestCase
{
  /**
   * Testa a conversão de valores de horas trabalhadas no período noturno
   * para o equivalente em horas trabalhadas no período diurno.
   * 
   * @dataProvider nightHoursProvider
   */
  public function testConversion($seconds, $expected)
  {
    $nightHours = new NightHours();

    $this->assertEquals($nightHours->toDayHours($seconds), $expected);
  }

  /**
   * O provedor de dados para os testes de conversão de horas noturnas.
   * 
   * @return array
   */
  public function nightHoursProvider()
  {
    return [
      'Convertendo valores zerados' => [0, 0],
      'Convertendo 1h noturna' => [3150, 3600],
      'Convertendo 1h normal' => [3600, 4114]
    ];
  }
}