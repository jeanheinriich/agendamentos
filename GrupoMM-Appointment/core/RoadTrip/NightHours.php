<?php
/*
 * This file is part of the road trip registre analysis library.
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
 * Essa é uma classe que permite converter um determinado tempo (em
 * segundos) ocorrido no horário noturno para um valor (em segundos)
 * no horário normal. Isto é necessário já que a hora noturna (àquela
 * que ocorre entre 22:00:00 e 05:00:00, por exemplo) possui um tempo
 * reduzido, correspondendo à 00:52:30 da mesma hora ocorrida em horário
 * normal.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\RoadTrip;

class NightHours
{
  /**
   * A quantidade de segundos no horário noturno
   * 
   * @var integer
   */
  protected $secondsInNightHours = 3150;

  /**
   * A quantidade de segundos em uma hora
   * 
   * @var integer
   */
  protected $secondsPerHour = 3600;

  /**
   * A quantidade de segundos em um minuto
   * 
   * @var integer
   */
  protected $secondsPerMinute = 60;

  /**
   * Converte uma quantidade de tempo (em segundos) ocorrida no horário
   * noturno em uma quantidade de tempo (em segundos) no horário normal.
   * 
   * @param  int    $seconds    A quantidade de segundos ocorridas no
   *                            horário noturno
   * 
   * @return int    A quantidade de segundos no horário normal
   */
  public function toDayHours(int $seconds)
  {
    // Convertemos a quantidade de segundos ocorridas no horário noturno
    // em uma quantidade de segundos ocorrida no horário diurno
    return intdiv($seconds * $this->secondsPerHour,
      $this->secondsInNightHours
    );
  }
}
