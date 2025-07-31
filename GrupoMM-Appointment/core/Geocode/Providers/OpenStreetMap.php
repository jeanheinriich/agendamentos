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
 * Um sistema de conexão com a API Nominatim do OpenStreetMap,
 * permitindo realizar requisições. A API Nominatim é implantada em
 * servidores OpenStreetMap, e alimenta a busca na página principal,
 * além de oferecer uma API.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

/**
 * API Nominatim - OpenStreetMap
 *
 * Nominatim (do latim, 'por nome') é uma ferramenta para pesquisar
 * dados do OpenStreetMap por nome e endereço (geocodificação) e para
 * gerar endereços sintéticos de pontos OSM (geocodificação reversa).
 * Uma instância com dados atualizados pode ser encontrada em
 * https://nominatim.openstreetmap.org. Nominatim também é usado como
 * uma das fontes para a caixa de pesquisa na página inicial do
 * OpenStreetMap.
 *
 * https://nominatim.org/release-docs/develop/api/Overview/
 *
 * Copyright (c) - OpenStreetMap contributors
 */

namespace Core\Geocode\Providers;

use Core\Exceptions\JSONException;
use Core\Geocode\Geocode;
use Core\Geocode\GeocodeInterface;
use Core\Geocode\Point;
use InvalidArgumentException;

class OpenStreetMap
  extends Geocode
  implements GeocodeInterface
{
  /**
   * A URL base para acesso à API do Open Streeat Map.
   * 
   * @var string
   */
  protected $URL = 'http://nominatim.openstreetmap.org';

  /**
   * As partes do endereço que devem ser ignoradas.
   * 
   * @var array
   */
  protected $ignoredAddressFields = [
    'municipality',
    'county',
    'state_district',
    'region',
    'country',
    'country_code'
  ];

  /**
   * Adiciona uma vírgula ao final do valor se o mesmo contiver um
   * valor.
   * 
   * @param string|null $value
   *   O valor
   * @param string $comma
   *   O conteúdo da vírgula
   * 
   * @return string
   */
  protected function addComma(
    ?string $value,
    string $comma = ''
  ): string
  {
    if (!is_null($value)) {
      if (trim($value) !== '') {
        return $value . $comma;
      }
    }

    return '';
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

    // Determina a URL da requisição
    $requestURL = $this->getURL('search');

    // Determina os parâmetros da requisição, preenchendo todos os
    // possíveis campos
    $params = [
      'format'         => 'jsonv2',
      'street'         => "{$number} {$address}",
      'city'           => $city,
      'state'          => $state,
      'country'        => $country,
      'postalcode'     => $postalCode,
      'addressdetails' => 1
    ];

    // Retiramos os valores que estejam em branco
    $params = array_filter($params,
      fn($value) => !is_null($value) && trim($value) !== ''
    );

    // Realiza a requisição
    $result = $this->http->sendRequest($requestURL, 'GET', $params);

    // Convertemos os dados recebidos
    $data = json_decode($result['response']['body'], true);

    // Lidamos com os erros de JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new JSONException(json_last_error());
    }

    if (count($data) > 0) {
      // Foram retornados dados
      if (array_key_exists('lat', $data[0])) {
        // Recuperamos os dados da coordenada geográfica
        $latitude  = (float) $data[0]['lat'];
        $longitude = (float) $data[0]['lon'];

        // Atualizamos o ponto de retorno
        $point->setLatitude($latitude);
        $point->setLongitude($longitude);
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

    // Determina a URL da requisição
    $requestURL = $this->getURL('reverse');

    // Determina os parâmetros da requisição
    $params = [
      'format'         => 'jsonv2',
      'lat'            => $latitude,
      'lon'            => $longitude,
      'addressdetails' => 1
    ];

    // Realiza a requisição
    $result = $this->http->sendRequest($requestURL, 'GET', $params);

    // Convertemos os dados recebidos
    $data = json_decode($result['response']['body'], true);

    // Lidamos com os erros de JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new JSONException(json_last_error());
    }

    if (array_key_exists('address', $data)) {
      // Recuperamos os dados do endereço
      $addressData = $data['address'];

      // Extraímos apenas as informações necessárias do endereço
      foreach ($addressData as $field => $value) {
        if (in_array($field, $this->ignoredAddressFields)) {
          // O conteúdo deste campo é ignorado
        } else {
          // Adicionamos o campo na sequência
          $address .= $this->addComma($value, ', ');
        }
      }

      // Eliminamos caracteres separadores do resultado final
      $address = trim($address, ", ");
    }

    return $address;
  }
}
