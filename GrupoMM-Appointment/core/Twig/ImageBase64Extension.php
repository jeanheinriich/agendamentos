<?php
/*
 * This file is part of Slim Framework 3 skeleton application.
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
 * Classe responsável por extender o Twig permitindo a inclusão de um
 * conversor para base64 de uma imagem.
 */

namespace Core\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ImageBase64Extension
  extends AbstractExtension
{
  /**
   * A pasta base
   *
   * @var string
   */
  protected $basePath;

  /**
   * O construtor de nossa extensão.
   * 
   * @param string $basePath
   *   A pasta inicial (default='/')
   */
  public function __construct(string $basePath = '/')
  {
    $this->basePath = $basePath;
  }
  
  /**
   * Recupera as funções para o sistema Twig.
   * 
   * @return array
   */
  public function getFunctions()
  {
    return [
      new TwigFunction('base64', [$this, 'image64'], [
          'needs_environment' => false,
          'is_safe' => ['html']
        ])
    ];
  }
  
  /**
   * Nossa função chamada via Twig e que converte a imagem informada
   * para o formato 'Base 64'.
   * 
   * @param string $image
   *   O nome do arquivo de imagem com o caminho
   * @param bool $inline
   *   Se o conteúdo será renderizado como inline
   * 
   * @return string
   *   O conteúdo convertido em Base 64
   */
  public function image64(string $imageFile,
    bool $inline = true): string
  {
    $result = '';

    if (file_exists($imageFile)) {
      $mimeType = mime_content_type($imageFile);

      // Verifique se o tipo MIME é uma imagem
      if (strpos($mimeType, 'image/') === 0) {
        if ($mimeType = 'image/svg') {
          $mimeType = 'image/svg+xml';
        }

        // Recuperamos o conteúdo do arquivo
        $binary = file_get_contents($imageFile);
        $base64 = base64_encode($binary);

        if ($inline) {
          $result = sprintf('data:%s;base64,%s', $mimeType, $base64);
        } else {
          $result = $base64;
        }
      }
    }

    // Retornamos o conteúdo convertido
    return $result;
  }
}
