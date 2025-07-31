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
 * Conjunto de testes do sistema de requisições por HTTP.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Tests\Core\Forms;

use Core\HTTP\HTTPClient;
use Core\Exceptions\cURLException;
use Core\Exceptions\HTTPException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class HTTPRequisitionTest
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
    $url = 'http://echo.jsontest.com/key/value/one/two';

    $http = new HTTPClient('/tmp');
    $http->addHeader('Content-Type', 'application/json');
    $result = $http->sendRequest($url, 'GET', ['X' => 'Y']);
    $this->assertIsArray($result);
    $this->assertIsArray($result['response']['headers']);
    $this->assertGreaterThan(5000, $result);
  }
}