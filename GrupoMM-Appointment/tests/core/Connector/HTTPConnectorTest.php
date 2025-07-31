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
 * Conjunto de testes do gerador de formulários dinâmico.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Tests\Core\Forms;

use Core\HTTP\HTTPClient;
use Core\Exceptions\JSONException;
use Core\Exceptions\cURLException;
use Core\Exceptions\HTTPException;
use PHPUnit\Framework\TestCase;

class HTTPConnectorTest
  extends TestCase
{
  /**
   * Define a URL de requisição.
   * 
   * @param string $uri
   *   A URI base
   * @param string $path
   *   O caminho para o recurso desejado
   * 
   * @return string
   *   A URL de requisição
   */
  protected function getURL(string $uri, string $path): string
  {
    return sprintf("%s/%s", $uri, $path);
  }

  /**
   * Testa a comunicação através da API
   */
  public function testConnection()
  {
    $uri = 'http://echo.jsontest.com';
    $http = new HTTPClient('/tmp');

    // Realiza a requisição
    $url = $this->getURL($uri, '/key/value/one/two');
    $result = $http->sendRequest($url, 'GET', []);

    $this->assertIsArray($result);
    $body = $result['response']['body'];
    $this->assertIsString($body);
    $json = json_decode($body, true);
    $this->assertIsArray($json);
    $this->assertGreaterThan(0, $json);
    $this->assertArrayHasKey('one', $json);
    $this->assertEquals($json, [ 'one' => 'two', 'key' => 'value' ]);
  }

  public function testURLError()
  {
    $url = 'http://echo.jsontest.com.x';
    $http = new HTTPClient('/tmp');

    // Realiza a requisição
    $this->expectException(cURLException::class);
    $this->expectExceptionMessage("Erro CURLE_COULDNT_RESOLVE_HOST: Não foi possível resolver o host para o endereço 'http://echo.jsontest.com.x");

    $result = $http->sendRequest($url, 'GET', []);
  }

  public function testPageNotFoundError()
  {
    $uri = 'http://www.jsontest.com';
    $http = new HTTPClient('/tmp');

    // Realiza a requisição
    $url = $this->getURL($uri, 'x/y');

    $this->expectException(HTTPException::class);
    $this->expectExceptionMessage("Página ou recurso 'http://www.jsontest.com/x/y' não encontrado");
    $result = $http->sendRequest($url, 'GET', []);
  }
}