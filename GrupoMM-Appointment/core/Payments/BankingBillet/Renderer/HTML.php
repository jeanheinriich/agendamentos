<?php
/*
 * This file is part of the payment's API library.
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
 * Uma classe que renderiza um boletos bancário no formato HTML.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\BankingBillet\Renderer;

use Exception;
use RuntimeException;

class HTML
  extends AbstractRenderer
{
  public function render(): string
  {
    if (count($this->billets) == 0) {
      throw new RuntimeException('Nenhum boleto foi adicionado');
    }

    // Obtemos os dados dos boletos a serem impressos
    $billetsData = [];
    foreach ($this->billets AS $billet) {
      // Obtemos os dados formatados do boleto a ser impresso
      $billetsData[] = $this->getBilletData($billet);
    }

    $extraData = [];
    $extraData['layout'] = $this->layout;
    $extraData['resourcePath'] = $this->getResourcePath();
    $extraData['cutHere'] = $this->getImageAsBase64('cutHere.png');
    $extraData['printBookCover'] = $this->printBookCover;
    if ($this->layout === 'paymentbooklet') {
      // Adicionamos separadamente as informações do emissor
      $extraData['emitter'] = $billetsData[0]['emitter'];
    }

    // Renderizamos cada boleto
    $layoutFileName = $this->getLayoutFileName();
    $run = function ($file, $billetsData, $extraData) {
      include $file;
    };
    
    ob_start();
    try {
      $run($layoutFileName, $billetsData, $extraData);
    }
    catch (Exception $e) {
      ob_clean();
      throw $e;
    }
      
    return ob_get_clean();
  }
}
