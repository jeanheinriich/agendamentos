<?php
/*
 * This file is part of Geocoder Library.
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
 * Descreve as funções necessárias à geolocalização.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Geocode;

interface GeocodeInterface
{
  /**
   * Faz a requisição de um código geográfico, obtendo a latitude e
   * longitude à partir do endereço do local.
   *
   * @param string $address
   *   O endereço
   * @param string $number
   *   O número da rua
   * @param string $city
   *   A cidade
   * @param string $state
   *   A UF
   * @param string $postalCode
   *   O código postal (CEP)
   * @param string $country (opcional)
   *   O país
   * 
   * @throws InvalidArgumentException
   *   Em caso de falha em um dos argumentos
   * @throws JSONException
   *   Em caso de falha no JSON
   *
   * @return Point
   *   As coordenadas geográficas do local
   */
  public function coordinates(
    string $address,
    string $number,
    string $city,
    string $state,
    string $postalCode,
    string $country = 'Brasil'
  ): Point;

  /**
   * Faz a requisição reversa de um código geográfico, obtendo o
   * endereço à partir da latitude e longitude do local.
   * 
   * @param float $latitude
   *   A latitude do local
   * @param float $longitude
   *   A longitude do local
   * 
   * @throws InvalidArgumentException
   *   Em caso de falha em um dos argumentos
   * @throws JSONException
   *   Em caso de falha no JSON
   *
   * @return string
   *   O endereço do local
   */
  public function address(
    float $latitude,
    float $longitude
  ): string;
}
