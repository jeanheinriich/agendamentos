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
 * Conjunto de testes do manipulador de caminhos.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Tests\Core\Helpers;

use Core\Helpers\Path;
use PHPUnit\Framework\TestCase;

class PathTest
  extends TestCase
{
  /**
   * Testa as funcionalidades básicas para um diretório.
   * 
   * @return void
   */
  public function testDir()
  {
    $testPath = realpath(__DIR__);
    $path = new Path($testPath);

    $this->assertInstanceOf(Path::class, $path);
    $this->assertTrue($path->exists());
    $this->assertTrue($path->isWritable());
    $this->assertTrue($path->isReadable());
    $this->assertTrue($path->isExecutable());
    $this->assertFalse($path->isFile());
    $this->assertFalse($path->isLink());
    $this->assertTrue($path->isDirectory());
    $this->assertEquals('', $path->getExtension());
    $this->assertEquals('', $path->getFilename());
    $this->assertEquals($testPath, $path->getDirectory());
    $this->assertEquals('', $path->getBasename());
  }

  /**
   * Testa as funcionalidades básicas para um arquivo.
   * 
   * @return void
   */
  public function testFile()
  {
    $testFile = realpath(__DIR__) . '/file.txt';
    $path = new Path($testFile);

    $this->assertInstanceOf(Path::class, $path);
    $this->assertTrue($path->exists());
    $this->assertFalse($path->isWritable());
    $this->assertTrue($path->isReadable());
    $this->assertFalse($path->isExecutable());
    $this->assertTrue($path->isFile());
    $this->assertFalse($path->isLink());
    $this->assertFalse($path->isDirectory());
    $this->assertEquals('txt', $path->getExtension());
    $this->assertEquals('file', $path->getFilename());
    $this->assertEquals(__DIR__, $path->getDirectory());
    $this->assertEquals('file.txt', $path->getBasename());
    $this->assertEquals(17, $path->getSize());
  }

  /**
   * Testa as funcionalidades básicas para um link simbólico.
   * 
   * @return void
   */
  public function testLink()
  {
    $testFile = realpath(__DIR__) . '/link.txt';
    $path = new Path($testFile);
    $this->assertInstanceOf(Path::class, $path);
    $this->assertTrue($path->exists());
    $this->assertFalse($path->isWritable());
    $this->assertTrue($path->isReadable());
    $this->assertFalse($path->isExecutable());
    $this->assertTrue($path->isFile());
    $this->assertTrue($path->isLink());
    $this->assertFalse($path->isDirectory());
    $this->assertEquals('txt', $path->getExtension());
    $this->assertEquals('link', $path->getFilename());
    $this->assertEquals(__DIR__, $path->getDirectory());
    $this->assertEquals('link.txt', $path->getBasename());
    $this->assertEquals(17, $path->getSize());
  }

  /**
   * Testa as funcionalidades de leitura de um diretório com arquivos.
   * 
   * @return void
   */
  public function testReadDir()
  {
    $testDir = realpath(__DIR__);
    $path = new Path($testDir);
    $this->assertInstanceOf(Path::class, $path);
    $this->assertEquals(3, $path->count());

    foreach ($path->getFiles() as $file) {
      $this->assertTrue($file->isFile());
    }
  }

  /**
   * Testa as funcionalidades de conversão.
   * 
   * @return void
   */
  public function testCast()
  {
    $testDir = realpath(__DIR__);
    $path = new Path($testDir);
    $this->assertInstanceOf(Path::class, $path);
    $result = (string) $path;
    $this->assertTrue(is_string($result));
    $this->assertEquals($testDir, $result);
  }
}