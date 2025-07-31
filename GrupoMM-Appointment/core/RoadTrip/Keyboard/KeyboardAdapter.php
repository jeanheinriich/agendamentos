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
 * Interface para um adaptador que nos permite interpretar os comandos
 * oriundos de teclados usados para registros das jornadas de trabalho
 * e que estão acoplados ao equipamento de rastreamento. Os dados são
 * transmitidos através de RS232 e enviados junto com os dados de
 * posicionamento do veículo.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\RoadTrip\Keyboard;

interface KeyboardAdapter
{
  /**
   * A função que nos permite interpretar os dados dos comandos oriundos
   * do teclado, convertendo-os para um objeto comando. Separa as partes
   * do comando, devolvendo em forma de um objeto o conteúdo do comando.
   * 
   * @param string $rs232Data
   *   Os dados recebidos do rastreador provenientes da interface RS232
   * que estabelece comunicação com o teclado nele acoplado
   * 
   * @return KeyboardEntry|null
   *   Os dados do comando registrado ou nulo caso o comando não seja
   * proveniente de um teclado da SGBRAS
   */
  public function parse(string $rs232Data):?KeyboardEntry;
}