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
 * Descreve uma coordenada geográfica.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Geocode;

class Point
{
  /**
   * A latitude
   *
   * @var float
   */
  protected $latitude = 0.00;

  /**
   * A longitude
   *
   * @var integer
   */
  protected $longitude = 0;

  /**
   * O construtor do ponto geométrico.
   *
   * @param float $latitude
   *   A latitude
   * @param float $longitude
   *   A longitude
   */
  public function __construct(
    float $latitude = 0,
    float $longitude = 0
  )
  {
    $this->latitude = $latitude;
    $this->longitude = $longitude;
  }

  /**
   * Obtém a latitude.
   *
   * @return float
   */
  public function getLatitude(): float
  {
    return $this->latitude;
  }

  /**
   * Define a latitude.
   *
   * @param float $latitude
   *   A latitude
   */
  public function setLatitude(float $latitude): void
  {
    $this->latitude = (float) $latitude;
  }

  /**
   * Obtém a longitude.
   *
   * @return float
   */
  public function getLongitude()
  {
    return $this->longitude;
  }

  /**
   * Define a longitude.
   *
   * @param float $longitude
   *   A longitude
   */
  public function setLongitude(float $longitude): void
  {
    $this->longitude = (float) $longitude;
  }

  /**
   * Converte um ponto para texto.
   *
   * @return string
   */
  public function __toString()
  {
    return sprintf("%01.7f, %01.7f",
      $this->latitude, $this->longitude
    );
  }
}
