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
 * Um sistema de conexão com a API do Google Maps, permitindo realizar
 * requisições.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

/**
 * Google Maps for Business
 * 
 * https://developers.google.com/maps/documentation/business/
 *
 * Copyright (c) - Google Inc
 */

namespace Core\Geocode\Providers;

use Core\Exceptions\JSONException;
use Core\Geocode\Geocode;
use Core\Geocode\GeocodeInterface;
use Core\Geocode\Point;
use InvalidArgumentException;

class GoogleMaps
  extends Geocode
  implements GeocodeInterface
{
  /**
   * A URL base para acesso à API do Google Maps.
   * 
   * @var string
   */
  protected $URL = 'https://maps.googleapis.com/maps/api/geocode/json';

  /**
   * A chave de identificação de cliente.
   * 
   * @var string
   */
  private $apiKey;

  /**
   * Associa a chave de cliente das API's do Google Maps.
   * 
   * @param string $key
   *   A chave de cliente
   */
  public function setKey(string $key)
  {
    $this->apiKey = $key;
  }

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
  ): Point
  {
    // Inicialmente, determinamos uma coordenada de retorno padrão
    $point = new Point(0, 0);

    if (empty($address) && empty($number) && empty($city)) {
      throw new InvalidArgumentException("Não foi informado um "
        . "endereço válido para consulta."
      );
    }

    if (is_null($this->apiKey)) {
      throw new InvalidArgumentException("Não foi informada a chave "
        . "de acesso à API do Google Maps."
      );
    }

    // Determina a URL da requisição
    $requestURL = $this->getURL('');

    // Determina os parâmetros da requisição
    $params = [
      'address' => sprintf("%s %s,%s %s",
        $address, $number, $postalCode, $city
      ),
      'key' => $this->apiKey
    ];

    // Realiza a requisição
    $result = $this->http->sendRequest($requestURL, 'GET', $params);

    // Convertemos os dados recebidos
    $data = json_decode($result['response']['body'], true);

    // Lidamos com os erros de JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new JSONException(json_last_error());
    }

    if (array_key_exists('status', $data)) {
      if ($data['status'] === 'OK') {
        if (array_key_exists('results', $data)) {
          if (array_key_exists('formatted_address', $data['results'])) {
            // Associamos diretamente o endereço
            $address = $data['results']['formatted_address'];
          }
        }
      }
    }

    return $point;
  }

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
  ): string
  {
    $address = '';
    
    // Validamos a latitude e longitude
    if (!$this->isLatitude((string) $latitude)) {
      throw new InvalidArgumentException("O valor da latitude é "
        . "inválido."
      );
    }

    if (!$this->isLongitude((string) $longitude)) {
      throw new InvalidArgumentException("O valor da longitude é "
        . "inválido."
      );
    }

    if (is_null($this->apiKey)) {
      throw new InvalidArgumentException("Não foi informada a chave "
        . "de acesso à API do Google Maps."
      );
    }

    // Determina a URL da requisição
    $requestURL = $this->getURL('');

    // Determina os parâmetros da requisição
    $params = [
      'latlng' => sprintf("%f,%f", $latitude, $longitude),
      'key' => $this->apiKey
    ];

    // Realiza a requisição
    $result = $this->http->sendRequest($requestURL, 'GET', $params);

    // Convertemos os dados recebidos
    $data = json_decode($result['response']['body'], true);

    // Lidamos com os erros de JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new JSONException(json_last_error());
    }

    if (array_key_exists('status', $data)) {
      if ($data['status'] === 'OK') {
        if (array_key_exists('results', $data)) {
          if (array_key_exists('formatted_address', $data['results'])) {
            // Associamos diretamente o endereço
            $address = $data['results']['formatted_address'];
          }
        }
      }
    }

    return $address;
  }
}
