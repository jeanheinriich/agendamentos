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
 * Um conjunto de testes do manipulador de mensagems de notificação.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Test\Geocode;

use Core\Geocode\Point;
use Core\Geocode\Providers\OpenStreetMap;
use PHPUnit\Framework\TestCase;

class OpenStreetMapTest
  extends TestCase
{
  /**
   * @var GeocodeInterface
   */
  protected $geocode;

  /**
   * Configura o ambiente de teste.
   *
   * @return void
   */
  protected function setUp(): void
  {
    $this->geocode = new OpenStreetMap('/tmp');
  }

  /**
   * Teste de requisição de uma coordenada geográfica.
   *
   * @return void
   */
  public function testCoordinate(): void
  {
    // Solicitamos a coordenada geográfica à partir de um endereço
    $coordinate = $this->geocode->coordinates(
      'Pennsylvania Avenue Northwest',
      '1917',
      'Washington',
      'DC',
      '20500',
      'United States Of America'
    );

    $this->assertInstanceOf(Point::class,
      $coordinate
    );
    // Comparamos a referência geográfica deste ponto
    $referencePoint = new Point(38.90039534482046, -77.04374576924522);

    // Avaliamos os valores armazenados
    $this->assertSame($referencePoint->getLatitude(),
      $coordinate->getLatitude()
    );
    $this->assertSame($referencePoint->getLongitude(),
      $coordinate->getLongitude()
    );
  }

  /**
   * Teste de requisição de um endereço.
   *
   * @return void
   */
  public function testAddress(): void
  {
    // Solicitamos o endereço à partir de uma coordenada geográfica
    $address = $this->geocode->address(
      38.90039534482046, -77.04374576924522
    );

    // Comparamos com o endereço deste ponto
    $this->assertSame(
      'The Seven Buildings, Pennsylvania Avenue Northwest, The West End, Washington, District of Columbia, US-DC, 20006',
      $address
    );
  }
}