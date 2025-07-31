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
 * Essa é uma trait (característica) simples de inclusão de métodos para
 * manipulação e tratamento do upload de arquivos que outras classes
 * podem incluir.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Controllers;

use RuntimeException;
use InvalidArgumentException;
use UnexpectedValueException;
use Core\Exceptions\UploadFileException;
use Slim\Http\UploadedFile;

trait HandleFileTrait
{
  /**
   * Um método genérico que processa os erros ocorridos no upload de
   * um arquivo.
   * 
   * @param  UploadedFile $uploadedFile
   *   O objeto com as informações do arquivo enviado
   *
   * @throws UploadFileException
   *
   * @return bool
   *   Se o arquivo foi transferido com sucesso
   */
  protected function fileHasBeenTransferred(
    UploadedFile $uploadedFile
  ): bool
  {
    $result = false;

    // Verifica o erro ocorrido
    switch ($uploadedFile->getError()) {
      case UPLOAD_ERR_OK:
        // Arquivo foi transferido com sucesso
        $result = true;

        break;
      case UPLOAD_ERR_INI_SIZE:
        // O arquivo enviado excede a diretiva upload_max_filesize no
        // php.ini
        
        // Registra o erro
        $maxFileSize = ini_get('upload_max_filesize');

        // Registra o erro
        throw new UploadFileException(
          sprintf("O arquivo '%s' ultrapassou o tamanho máximo "
            . "%s permitido.", $uploadedFile->getClientFilename(),
            $maxFileSize
          ),
          UPLOAD_ERR_INI_SIZE
        );

        break;
      case UPLOAD_ERR_FORM_SIZE:
        // O arquivo enviado excede a diretiva MAX_FILE_SIZE
        // especificada no formulário HTML
        
        // Registra o erro
        throw new UploadFileException(
          sprintf("O arquivo '%s' ultrapassou o limite de tamanho "
            . "especificado no formulário.",
            $uploadedFile->getClientFilename()
          ),
          UPLOAD_ERR_FORM_SIZE
        );

        break;
      case UPLOAD_ERR_PARTIAL:
        // O arquivo enviado foi apenas parcialmente carregado
        
        // Registra o erro
        throw new UploadFileException(
          sprintf("O arquivo '%s' foi apenas parcialmente carregado.",
            $uploadedFile->getClientFilename()
          ),
          UPLOAD_ERR_PARTIAL
        );

        break;
      case UPLOAD_ERR_NO_FILE:
        // Nenhum arquivo foi enviado
        $result = false;

        break;
      default:
        // Nenhum arquivo foi enviado
        
        // Registra o erro
        throw new UploadFileException(
          sprintf("Ocorreu um erro ao carregar o arquivo '%s'.",
            $uploadedFile->getClientFilename()
          ),
          UPLOAD_ERR_NO_FILE
        );

        break;
    }

    return $result;
  }

  /**
   * Um método que obtém o sufixo do nome de um arquivo de imagem.
   * 
   * @param string $filename
   *   O nome do arquivo
   * 
   * @return string
   *   O sufixo
   */
  protected function getImageSuffix(string $filename): string
  {
    $parts = pathinfo($filename);

    return substr($parts['filename'], -1);
  }

  /**
   * Um método que lê um arquivo de imagem codificando em base64.
   * 
   * @param string $filename
   *   O nome do arquivo
   * 
   * @return string
   *   O arquivo codificado em Base64
   *
   * @throws RuntimeException
   */
  protected function readBase64Image(string $filename):string
  {
    // Verifica se o arquivo existe
    if (file_exists($filename)) {
      $imageData = file_get_contents($filename);
      $mimeType = pathinfo($filename, PATHINFO_EXTENSION);
      $imageDataBase64 = 'data:image/' . $mimeType . ';base64,'
        . base64_encode($imageData)
      ;
    } else {
      throw new RuntimeException(
        sprintf("O arquivo '%s' não existe.", $filename)
      );
    }

    return $imageDataBase64;
  }

  /**
   * Um método que gera um arquivo de imagem a partir de uma imagem
   * codificada em base64.
   * 
   * @param string $targetPath
   *   O path onde será gravado o arquivo
   * @param string $data
   *   A imagem codificada em base64
   *
   * @throws InvalidArgumentException
   *   Em caso de argumento inválido
   * @throws RuntimeException
   *   Em caso de erro
   * 
   * @return string
   *   O nome do arquivo gerado
   */
  protected function writeBase64Image(
    string $targetPath,
    string $data
  ): string
  {
    if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
      $data = substr($data, strpos($data, ',') + 1);
      $type = strtolower($type[1]); // jpg, png, gif

      // Verifica se o tipo de arquivo está entre os formatos suportados
      if (!in_array($type, [ 'jpg', 'jpeg', 'gif', 'png' ])) {
        throw new RuntimeException(
          sprintf("O tipo de imagem '%s' não é suportado.", $type)
        );
      }

      $data = base64_decode($data);

      if ($data === false) {
        throw new RuntimeException("Não foi possível decodificar a "
          . "imagem.")
        ;
      }
    } else {
      throw new RuntimeException("Formato de imagem inválido.");
    }

    $imageFile = '';
    $filename = '';
    do {
      // Codifica o nome do arquivo
      $basename  = bin2hex(random_bytes(8));
      $filename  = sprintf('img_%s.%0.8s', $basename, $type);
      $imageFile = $targetPath . DIRECTORY_SEPARATOR . $filename;
    } while (file_exists($imageFile));

    // Verifica se o diretório é gravável
    if (is_writable($targetPath)) {
      if (file_put_contents($imageFile, $data) == strlen($data)) {
        // Força as permissões do arquivo
        chmod($imageFile, 0664);

        return $filename;
      } else {
        throw new RuntimeException("Não foi possível gravar imagem.");
      }
    } else {
      throw new InvalidArgumentException("O caminho de destino dos "
        . "uploads não é gravável."
      );
    }
  }
  
  /**
   * Converte o arquivo carregado para uma stream para armazenamento
   * direto no banco de dados, redimensionando a imagem para um tamanho
   * padrão. Caso a imagem não tenha a mesma proporção, os espaços podem
   * ser cortados (cropped) ou preenchidos com conteúdo em branco.
   * 
   * @param UploadedFile $uploadedFile
   *   O nome do arquivo enviado
   * @param int $finalWidth
   *   O comprimento máximo
   * @param int $finalHeight
   *   A altura máxima
   * @param bool $crop
   *   A flag indicativa de que devemos cortar a imagem (default false)
   *
   * @throws RuntimeException
   *   Em caso de erro
   * @throws UnexpectedValueException
   *   Em caso de valor não experado
   * 
   * @return String
   *   O arquivo convertido para Base64
   */
  public function getImage(
    UploadedFile $uploadedFile,
    int $targetWidth,
    int $targetHeight,
    bool $crop = false
  ): string
  {
    // Verifica se a extensão GD está carregada
    if (extension_loaded('gd')) {
      // Recupera o conteúdo da imagem através da stream
      $imageStream = $uploadedFile->getStream();
      $srcImage = imagecreatefromstring($imageStream);

      // Agora recupera as informações da imagem
      list($srcWidth, $srcHeight, $imageType) =
        getimagesizefromstring($imageStream)
      ;

      // Verifica se o tipo da imagem está dentre os tipos suportados.
      // O tipo pode ser 2 = JPG, 3 = PNG
      if (($imageType > 1) && ($imageType <= 3)) {
        // Se não forem fornecidos valores válidos, não processa
        if (($srcWidth <= 0) || ($srcHeight <= 0)) {
          throw new UnexpectedValueException(
            sprintf("O arquivo '%s' não possui um conteúdo válido.",
              $uploadedFile->getClientFilename()
            )
          );
        }
        if (($targetWidth <= 0) || ($targetHeight <= 0)) {
          throw new UnexpectedValueException("As dimensões finais "
            . "da imagem são inválidas."
          );
        }

        // Aqui fazemos dois cálculos, um para ajustar a imagem à largura
        // e outro à altura. Desta forma, escolhemos àquele que nos permite
        // obter o melhor ajuste para esta imagem.
        
        // Calcula os valores para escalar a imagem para a largura final
        $scaleX1 = $targetWidth;
        $scaleY1 = ($srcHeight * $targetWidth) / $srcWidth;

        // Calcula os valores para escalar a imagem para a altura final
        $scaleX2 = ($srcWidth * $targetHeight) / $srcHeight;
        $scaleY2 = $targetHeight;

        // Verificamos se devemos escalar pela largura ou pela altura
        $fScaleOnWidth = ($scaleX2 > $targetWidth)?true:false;

        // Determinamos o escalonamento da imagem
        if ($fScaleOnWidth) {
          // Escalamos pela largura
          $width = floor($scaleX1);
          $height = floor($scaleY1);
        } else {
          // Escalamos pela altura
          $width = floor($scaleX2);
          $height = floor($scaleY2);
        }

        if ($crop === true) {
          // Como vamos recortar a imagem para que a mesma tenha a
          // largura e/ou altura final, a porção vazia é ignorada
          $left = 0;
          $top  = 0;

          // Deixamos a imagem com a dimensão final igual ou menor do
          // que o tamanho final informado
          $targetWidth  = $width;
          $targetHeight = $height;
        } else {
          // Centralizamos a imagem no destino, deixando o restante
          // vazio
          $left = floor(($targetWidth - $width) / 2);
          $top  = floor(($targetHeight - $height) / 2);
        }

        // Cria a imagem onde desenhamos o conteúdo 
        $dstImage = imagecreatetruecolor($targetWidth, $targetHeight);

        // Preserva o canal alpha para imagens PNG
        if ($imageType === 3) {
          // Preserva a transparência
          imagealphablending($dstImage, true);
          imagesavealpha($dstImage, true);

          // Preenche todo o conteúdo da imagem com transparência
          $bgcolor = imagecolorallocatealpha($dstImage, 0, 0, 0, 127);
          imagefill($dstImage, 0, 0, $bgcolor);
        } else {
          // Preenche todo o conteúdo da imagem com branco
          $bgcolor = imagecolorallocate($dstImage, 255, 255, 255);
          imagefill($dstImage, 0, 0, $bgcolor);
        }

        // Redesenhamos a imagem de forma escalada, posicionando-a de
        // maneira centralizada no destino
        imagecopyresampled($dstImage, $srcImage, $left, $top, 0, 0,
          $width, $height, $srcWidth, $srcHeight
        );

        // Apaga a imagem temporária de origem
        if (is_resource($srcImage)) {
          imagedestroy($srcImage);
        }
      } else {
        throw new UnexpectedValueException(
          sprintf("O arquivo '%s' é de um tipo de imagem inválida. Os "
            . "tipos válidos são JPEG e PNG.",
            $uploadedFile->getClientFilename()
          )
        );
      }

      // Converte a imagem em memória para o formato final
      
      // Vamos começar o buffer de saída
      ob_start();

      switch ($imageType) {
        case 3:
          // Convertemos a imagem no padrão PNG
          imagepng($dstImage);
          $dataFormat = 'data:image/png;base64,';

          break;
        default:
          // Convertemos a imagem no padrão JPEG
          imagejpeg($dstImage);
          $dataFormat = 'data:image/jpeg;base64,';

          break;
      }

      // Carregamos o conteúdo em $imagedata
      $imagedata = ob_get_contents();

      // Terminamos o buffer de saída
      ob_end_clean();

      // Converte a imagem minituriarizada para base 64
      $base64 = $dataFormat . base64_encode($imagedata);

      // Apaga a imagem minituriarizada
      imagedestroy($dstImage);
    } else {
      throw new RuntimeException("A biblioteca GD não está disponível");
    }

    return $base64;
  }
  
  /**
   * Move o arquivo carregado para o diretório de upload e atribui-lhe
   * um nome exclusivo para evitar substituir um arquivo existente. Caso
   * o arquivo seja gif, png ou jpeg e for superior a uma dimensão,
   * redimensiona o arquivo para manter o tamanho dentro de limites
   * 
   * @param String $targetPath
   *   O path de destino
   * @param UploadedFile $uploadedFile
   *   O nome do arquivo enviado
   *
   * @throws InvalidArgumentException
   *   Em caso de argumento inválido
   * @throws RuntimeException
   *   Em caso de erro
   * 
   * @return String
   *   O nome do arquivo final
   */
  protected function moveFile(
    string $targetPath,
    UploadedFile $uploadedFile
  ): string
  {
    // Verifica se a extensão GD está carregada
    if (extension_loaded('gd')) {
      // Verifica se o destino é um diretório válido
      if (is_dir($targetPath)) {
        if (!is_writable($targetPath)) {
          throw new InvalidArgumentException("O caminho de destino dos "
            . "uploads não é gravável"
          );
        }
      } else {
        // Verifica se podemos criar o diretório corretamente
        if (false === @mkdir($targetPath, 0777, true)) {
          // Limpamos o cache
          clearstatcache(true, $targetPath);

          // Verificamos novamente se o diretório existe
          if (!is_dir($targetPath)) {
            throw new InvalidArgumentException("Não é possível criar o "
              . "caminho de destino dos uploads"
            );
          }
        }
      }

      // Recupera a extensão do arquivo
      $extension = pathinfo($uploadedFile->getClientFilename(),
        PATHINFO_EXTENSION)
      ;

      // Codifica o nome do arquivo
      $basename = bin2hex(random_bytes(8));
      $filename = sprintf('attm_%s.%0.8s', $basename, $extension);

      // Move o arquivo para a pasta correta
      $attachmentFile = $targetPath . DIRECTORY_SEPARATOR . $filename;
      $uploadedFile->moveTo($attachmentFile);

      // Agora recupera as informações da imagem
      list($width, $height, $imageType) = getimagesize($attachmentFile);

      if ((0 <= $imageType) && ($imageType <= 3)) {
        // Verifica se os tamanhos da imagem são superiores as dimensões
        // máximas permitidas
        $forceResize = false;
        $portrait = false;
        if ($width > $height) {
          // A imagem está em modo paisagem
          if (($width > 1024) || ($height > 768))
            $forceResize = true;
        } else {
          // A imagem está em modo retrato
          $portrait = true;
          if (($width > 768) || ($height > 1024))
            $forceResize = true;
        }

        if ($forceResize) {
          // Redimensiona a imagem apenas conforme o formato
          switch ($imageType)
          {
            case 1:
              // Imagem GIF
              $srcImage = imagecreatefromgif($attachmentFile);

              break;
            case 2:
              // Imagem JPG
              $srcImage = imagecreatefromjpeg($attachmentFile);

              break;
            case 3:
              // Imagem PNG
              $srcImage = imagecreatefrompng($attachmentFile);

              break;
          }

          // Determina o novo tamanho
          if ($portrait) {
            // Modo retrato
            $newHeight = 768;
            $newWidth  = ( $width / $height ) * $newHeight;
          } else {
            // Modo paisagem
            $newWidth  = 1024;
            $newHeight = ($height / $width) * $newWidth;
          }

          // Redimensiona a imagem
          $tmpImage = imagecreatetruecolor($newWidth, $newHeight);
          imagecopyresampled($tmpImage, $srcImage, 0, 0, 0, 0,
            $newWidth, $newHeight, $width, $height
          );

          // Sobrescreve a imagem
          imagejpeg($tmpImage, $attachmentFile, 90);

          // Apaga as imagens temporárias
          imagedestroy($srcImage);
          imagedestroy($tmpImage);
        }
      }
    } else {
      throw new RuntimeException("A biblioteca GD não está disponível");
    }

    return $filename;
  }

  /**
   * Renomeia um arquivo para um UUID informado e, se necessário,
   * adiciona um sufixo ao nome do arquivo.
   * 
   * @param string $targetPath
   *   O path de destino
   * @param string $originalFilename
   *   O nome do arquivo original
   * @param string $UUID
   *   O UUID para qual irá se renomear
   * @param string $suffix
   *   Um sufixo (opcional)
   * 
   * @throws InvalidArgumentException
   *   Em caso de argumento inválido
   * @throws RuntimeException
   *   Em caso de erro
   * 
   * @return string
   *   O nome do arquivo renomeado
   */
  protected function renameFile(
    string $targetPath,
    string $originalFilename,
    string $prefix = '',
    string $UUID = '',
    string $suffix = ''
  ): string
  {
    // Verifica se o diretório destino é válido
    if (is_dir($targetPath)) {
      if (!is_writable($targetPath)) {
        throw new InvalidArgumentException("O caminho de destino dos "
          . "uploads não é gravável"
        );
      }

      // Verifica se o arquivo existe
      $target = $targetPath . DIRECTORY_SEPARATOR . $originalFilename;
      if (file_exists($target)) {
        // Recuperamos a extensão
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);

        // Verifica se foi informado um prefixo
        if ($prefix) {
          // Acrescentamos um separador
          $prefix .= '_';
        }
        if ($suffix) {
          // Acrescentamos um separador
          $suffix = '_' . $suffix;
        }

        // Determinamos o novo nome do arquivo
        $newFilename = sprintf('%s%s%s.%s', $prefix, $UUID, $suffix,
          $extension)
        ;
        $destination = $targetPath . DIRECTORY_SEPARATOR . $newFilename;

        // Renomeamos o arquivo
        if (!rename($target, $destination)) {
          throw new RuntimeException(
            sprintf("Erro ao renomear o arquivo '%s'.",
              $originalFilename)
          );
        }

        return $newFilename;
      } else {
        throw new RuntimeException(
          sprintf("O arquivo '%s' não existe.", $originalFilename)
        );
      }
    } else {
      throw new InvalidArgumentException("Não existe o caminho de "
        . "destino dos uploads."
      );
    }

    return $originalFilename;
  }

  /**
   * Substituí o conteúdo de um arquivo.
   * 
   * @param string $targetPath
   *   O path de destino
   * @param string $originalFilename
   *   O nome do arquivo original
   * @param string $newFilename
   *   O arquivo cujo conteúdo vamos substituir
   * 
   * @throws InvalidArgumentException
   *   Em caso de argumento inválido
   * @throws RuntimeException
   *   Em caso de erro
   * 
   * @return string
   *   O nome do arquivo substituído
   */
  protected function replaceFile(
    string $targetPath,
    string $originalFilename,
    string $newFilename
  ): string
  {
    // Verifica se o diretório destino é válido
    if (is_dir($targetPath)) {
      if (!is_writable($targetPath)) {
        throw new InvalidArgumentException("O caminho de destino dos "
          . "uploads não é gravável."
        );
      }

      // Verifica se o arquivo original existe
      $target = $targetPath . DIRECTORY_SEPARATOR . $originalFilename;
      if (file_exists($target)) {
        // Renomeamos o arquivo original para preservarmos por segurança
        if (rename($target, $target . ".old")) {
          $target = $targetPath . DIRECTORY_SEPARATOR . $newFilename;

          // Recuperamos a extensão do novo arquivo
          $extension = pathinfo($newFilename, PATHINFO_EXTENSION);

          // Recuperamos o nome do arquivo original
          $path_parts  = pathinfo($originalFilename);
          $basename    = $path_parts['filename'];

          // Determinamos o nome do arquivo após a renomeação
          $renFilename = sprintf("%s.%s", $basename, $extension);
          $destination = $targetPath . DIRECTORY_SEPARATOR
            . $renFilename
          ;

          // Renomeamos o arquivo
          if (rename($target, $destination)) {
            // Apagamos o arquivo de segurança
            unlink($targetPath . DIRECTORY_SEPARATOR .
                   $originalFilename . ".old");

            return $renFilename;
          } else {
            // Restauramos o arquivo original
            rename(
              $targetPath . DIRECTORY_SEPARATOR .
              $originalFilename . ".old",
              $targetPath . DIRECTORY_SEPARATOR .
              $originalFilename
            );
            
            // Apaga o arquivo que tentamos sobrepor
            unlink($target);
            
            // Dispara a exceção
            throw new RuntimeException(
              sprintf("Erro ao renomear o arquivo '%s'.", $renFilename)
            );
          }
        } else {
          throw new RuntimeException(
            sprintf("Erro ao renomear o arquivo '%s'.", $originalFilename)
          );
        }
      } else {
        throw new RuntimeException(
          sprintf("O arquivo '%s' não existe.", $originalFilename)
        );
      }
    } else {
      throw new InvalidArgumentException("Não existe o caminho de "
        . "destino dos uploads."
      );
    }

    return $originalFilename;
  }

  /**
   * Remove o arquivo caso ele exista.
   * 
   * @param string $targetPath
   *   O path de destino
   * @param string $filename
   *   O nome do arquivo
   * 
   * @throws InvalidArgumentException
   * @throws RuntimeException
   */
  protected function deleteFile(
    string $targetPath,
    string $filename
  ): void
  {
    // Verifica se o diretório destino é válido
    if (is_dir($targetPath)) {
      if (!is_writable($targetPath)) {
        throw new InvalidArgumentException("O caminho de destino dos "
          . "uploads não é gravável."
        );
      }

      // Verifica se o arquivo existe
      $target = $targetPath . DIRECTORY_SEPARATOR . $filename;
      if (file_exists($target)) {
        if (!unlink($target)) {
          throw new RuntimeException(
            sprintf("Erro ao remover o arquivo enviado '%s'.",
              $filename
            )
          );
        }
      }

      // Verifica se o arquivo existe
      // 
    } else {
      throw new InvalidArgumentException("Não existe o caminho de "
        . "destino dos uploads."
      );
    }
  }

  /**
   * Método que converte um valor em bytes em um valor que possa ser
   * compreendido mais facilmente.
   * 
   * @param int $size
   *   O tamanho em bytes que se deseja converter
   * @param int|integer $decimals
   *   O número de casas decimais
   * 
   * @return string
   *   O valor convertido
   */
  protected function humanFilesize(
    int $size,
    int $decimals = 2
  ): string
  {
    $unitsOfMeasure = [
      'bytes', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'
    ];
    $step = 1024;
    $factor = 0;

    if ($size < 1024) {
      return sprintf("%s %s", $size, $unitsOfMeasure[$factor]);
    }

    while (($size / $step) > 0.9) {
      $size = $size / $step;
      $factor++;
    }

    return sprintf("%s %s",
             str_replace('.', ',', round($size, $decimals)),
             $unitsOfMeasure[$factor]);
  }

  /**
   * Gera a miniatura de uma imagem.
   * 
   * @param string $imageFile
   *   O nome do arquivo de imagem
   * 
   * @throws RuntimeException
   *   Em caso de erro
   * @throws UnexpectedValueException
   *   Em caso de valor não experado
   * 
   * @return mixed
   *   A imagem em miniatura
   */
  public static function generateThumbnail(string $imageFile)
  {
    // Verifica se a extensão GD está carregada
    if (extension_loaded('gd')) {
      // Recupera as informações da imagem
      list($width, $height, $imageType) = getimagesize($imageFile);

      // Verifica se o tipo da imagem está dentre os tipos suportados.
      // O tipo pode ser 2 = JPG, 3 = PNG
      if (($imageType > 1) && ($imageType <= 3)) {
        // Verifica a orientação da imagem
        $portrait = false;
        if ($width < $height) {
          // A imagem está em modo retrato
          $portrait = true;
        }
        
        // Redimensiona a imagem para o tamanho da miniatura
        switch ($imageType)
        {
          case 1:
            // Imagem GIF
            $srcImage = imagecreatefromgif($imageFile);

            break;
          case 2:
            // Imagem JPG
            $srcImage = imagecreatefromjpeg($imageFile);

            break;
          case 3:
            // Imagem PNG
            $srcImage = imagecreatefrompng($imageFile);

            break;
          default:
            return false;
        }
        
        // Calcula a parte da imagem a ser utilizada na miniatura
        if ($portrait) {
          // Modo retrato
          $x = 0;
          $y = ($height - $width) / 2;
          $smallestSide = $width;
        } else {
          // Modo paisagem
          $y = 0;
          $x = ($width - $height) / 2;
          $smallestSide = $height;
        }
        
        // Redimensiona a imagem para o tamanho da miniatura
        $thumbSize = 300;
        $thumbnailImage = imagecreatetruecolor($thumbSize, $thumbSize);
        imagecopyresampled($thumbnailImage, $srcImage,
          0, 0, $x, $y,
          $thumbSize, $thumbSize, $smallestSide, $smallestSide);
        
        // Apaga a imagem temporária
        if(is_resource($srcImage)) {
          imagedestroy($srcImage);
        }
        
        return $thumbnailImage;
      } else {
        throw new UnexpectedValueException(
          sprintf("Não é possível processar o tipo de imagem informado "
            . "(%s). Os tipo válidos são JPEG e PNG.", $imageType
          )
        );
      }
    } else {
      throw new RuntimeException("A biblioteca GD não está disponível");
    }
  }

  /**
   * Escala uma imagem para o tamanho indicado, mantendo a
   * transparência.
   * 
   * @param string $filename
   *   O nome do arquivo a ser escalado
   * @param int $targetWidth
   *   A largura final desejada
   * @param int $targetHeight
   *   A altura final desejada
   * @param bool $crop
   *   A flag que indica que a imagem pode ser cortada (Default: false)
   * 
   * @throws RuntimeException
   *   Em caso de erro
   * @throws UnexpectedValueException
   *   Em caso de valor não experado
   * 
   * @return bool
   *   O resultado da operação
   */
  public function scaleImage(
    string $filename,
    int $targetWidth,
    int $targetHeight,
    bool $crop = false
  ): bool
  {
    // Verifica se a extensão GD está carregada
    if (extension_loaded('gd')) {
      // Recupera a extensão do arquivo
      $extension = pathinfo($filename, PATHINFO_EXTENSION);

      // Agora recupera as informações da imagem
      if (!list($srcWidth, $srcHeight) = getimagesize($filename)) {
        throw new UnexpectedValueException(
          sprintf("O arquivo '%s' é de um tipo de imagem inválida. Os "
            . "tipos válidos são JPEG e PNG.",
            $filename
          )
        );
      }

      // Se não forem fornecidos valores válidos, não processa
      if (($srcWidth <= 0) || ($srcHeight <= 0)) {
        throw new UnexpectedValueException(
          sprintf("O arquivo '%s' não possui um conteúdo válido.",
            $filename
          )
        );
      }
      if (($targetWidth <= 0) || ($targetHeight <= 0)) {
        throw new UnexpectedValueException("As dimensões finais são "
          . "inválidas."
        );
      }

      // Carrega a imagem a partir do arquivo
      switch($extension) {
        case 'jpeg':
        case 'jpg':
          $srcImage = imagecreatefromjpeg($filename);

          break;
        case 'png':
          $srcImage = imagecreatefrompng($filename);

          break;
        default:
          throw new UnexpectedValueException(
            sprintf("Não é possível processar o tipo de imagem '%s'. "
              . "Os tipo válidos são JPEG e PNG.", $extension
            )
          );
      }

      // Aqui fazemos dois cálculos, um para ajustar a imagem à largura e
      // outro à altura. Desta forma, escolhemos àquele que nos permite
      // obter o melhor ajuste para esta imagem.
      
      // Calcula os valores para escalar a imagem para a largura final
      $scaleX1 = $targetWidth;
      $scaleY1 = ($srcHeight * $targetWidth) / $srcWidth;

      // Calcula os valores para escalar a imagem para a altura final
      $scaleX2 = ($srcWidth * $targetHeight) / $srcHeight;
      $scaleY2 = $targetHeight;

      // Verificamos se devemos escalar pela largura ou pela altura
      $fScaleOnWidth = ($scaleX2 > $targetWidth)
        ? true
        : false
      ;

      // Determinamos o escalonamento da imagem
      if ($fScaleOnWidth) {
        // Escalamos pela largura
        $width = floor($scaleX1);
        $height = floor($scaleY1);
      } else {
        // Escalamos pela altura
        $width = floor($scaleX2);
        $height = floor($scaleY2);
      }

      if ($crop === true) {
        // Como vamos recortar a imagem para que a mesma tenha a largura
        // e/ou altura final, a porção vazia é ignorada
        $left = 0;
        $top  = 0;

        $targetWidth  = $width;
        $targetHeight = $height;
      } else {
        // Centralizamos a imagem no destino, deixando o restante vazio
        $left = floor( ($targetWidth - $width) / 2 );
        $top  = floor( ($targetHeight - $height) / 2 );
      }

      // Cria a imagem de destino
      $dstImage = imagecreatetruecolor($targetWidth, $targetHeight);

      if ($extension === 'png') {
        // Preserva a transparência
        imagealphablending($dstImage, true);
        imagesavealpha($dstImage, true);

        // Preenche todo o conteúdo da imagem com transparência
        $bgcolor = imagecolorallocatealpha($dstImage, 0, 0, 0, 127);
        imagefill($dstImage, 0, 0, $bgcolor);
      } else {
        // Preenche todo o conteúdo da imagem com branco
        $bgcolor = imagecolorallocate($dstImage, 255, 255, 255);
        imagefill($dstImage, 0, 0, $bgcolor);
      }

      // Redesenhamos a imagem de forma escalada, posicionando-a de
      // maneira centralizada no destino
      imagecopyresampled($dstImage, $srcImage, $left, $top, 0, 0,
        $width, $height, $srcWidth, $srcHeight
      );
      
      // Apaga a imagem temporária de origem
      if (is_resource($srcImage)) {
        imagedestroy($srcImage);
      }

      // Converte a imagem em memória para o formato final
      switch($extension) {
        case 'png':
          imagepng($dstImage, $filename);

          break;
        default:
          // Grava no formato JPEG
          imagejpeg($dstImage, $filename);
      }
      
      // Apaga a imagem minituriarizada
      imagedestroy($dstImage);

      return true;
    } else {
      throw new RuntimeException("A biblioteca GD não está disponível");
    }

    return false;
  }
}
