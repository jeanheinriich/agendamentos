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
 * Um invólucro para as API's de geoposicionamento.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Geocode;

use Core\HTTP\HTTPClient;
use InvalidArgumentException;

abstract class Geocode
{
  /**
   * O sistema de requisição por HTTP.
   *
   * @var HTTPClient
   */
  protected $http;

  /**
   * A URL base para acesso à API do provedor de geoposicionamento.
   * 
   * @var string
   */
  protected $URL = '';

  /**
   * O construtor de nosso sistema de geolocalização.
   * 
   * @param string $path
   *   O caminho onde serão armazenados os cookies
   */
  public function __construct(string $path)
  {
    $this->http = new HTTPClient($path);
  }

  // ========================================[ Métodos auxiliares ]=====

  /**
   * Define a URL de requisição.
   * 
   * @param string $path
   *   O caminho para o recurso desejado
   * 
   * @return string
   *   A URL de requisição
   */
  public function getURL(string $path): string
  {
    return sprintf("%s/%s", $this->URL, $path);
  }

  /**
   * Obtém se o valor é uma latitude válida.
   *
   * @param $value
   *   O valor a ser validado
   *
   * @return bool
   */
  protected function isLatitude(string $value)
  {
    $latitudePattern = '/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/';

    return preg_match($latitudePattern, $value) == 1
      ? true
      : false
    ;
  }

  /**
   * Obtém se o valor é uma longitude válida.
   *
   * @param $value
   *   O valor a ser validado
   *
   * @return bool
   */
  protected function isLongitude(string $value)
  {
    $longitudePattern = '/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/';

    return preg_match($longitudePattern, $value) == 1
      ? true
      : false
    ;
  }

  // ========================[ Implementações da GeocodeInterface ]=====

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
  abstract public function coordinates(
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
  abstract public function address(
    float $latitude,
    float $longitude
  ): string;
}
