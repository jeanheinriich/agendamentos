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

use Core\Breadcrumbs\Breadcrumb;
use PHPUnit\Framework\TestCase;

class BreadcrumbTest
  extends TestCase
{
  /**
   * Testa a criação de um gerenciador de trilhas em branco.
   */
  public function testEmptyBreadcrumb()
  {
    $breadcrumb = new Breadcrumb();
    $expected = [];

    $result = $breadcrumb->getTrail();
    $this->assertIsArray($result);
    $this->assertEquals($result, $expected);
  }

  /**
   * Testa a criação de um gerenciador de trilhas com conteúdo.
   */
  public function testBreadcrumb()
  {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->push('Início', '/');
    $breadcrumb->push('Cadastros', '/cadastre');
    $expected = [
      [ 'title' => 'Início', 'url' => '/' ],
      [ 'title' => 'Cadastros', 'url' => '/cadastre' ]
    ];
    $result = $breadcrumb->getTrail();
    $this->assertIsArray($result);
    $this->assertGreaterThan(0, $result);
    $first = $result[0];
    $this->assertArrayHasKey('title', $first);
    $this->assertArrayHasKey('url', $first);
    $this->assertEquals($first, $expected[0]);
    $this->assertEquals($breadcrumb->getTrail(), $expected);
  }
}