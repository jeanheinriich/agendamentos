<?php
/*
 * This file is part of STC Integration Library.
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
 * As requisições ao serviço de obtenção dos dados de fabricantes de
 * equipamentos de rastreamento através da API do sistema STC.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

/**
 * API STC
 *
 * http://ap1.stc.srv.br/docs/
 *
 * Copyright (c) 2017 - STC Tecnologia <www.stctecnologia.com.br>
 */

namespace App\Providers\STC\Services;

use App\Models\STC\Manufacture;
use Core\HTTP\Service;

class ManufactureService
  extends STCService
  implements Service
{
  /**
   * O caminho para nosso serviço.
   * 
   * @var string
   */
  protected $path = 'ws/manufacture/list';

  /**
   * O método responsável por executar as requisições ao serviço,
   * sincronizando os dados.
   */
  public function synchronize(): void
  {
    // Ajusta os parâmetros para sincronismo
    $this->synchronizer->setURI($this->path);

    // Primeiramente preparamos os parâmetros de nossa requisição
    $this->synchronizer->prepareParameters();

    // Seta uma função para lidar com os dados recebidos
    $this->synchronizer->setOnDataProcessing([$this, 'onDataProcessing']);

    // Executa o sincronismo dos fabricantes de equipamentos de
    // rastreamento com o sistema STC
    $this->synchronizer->synchronize();
  }

  /**
   * A função responsável por processar os dados recebidos.
   * 
   * @param  array  $manufactureData
   *   Os dados obtidos do servidor STC
   */
  public function onDataProcessing(array $manufactureData): void
  {
    // Primeiro, verifica se este fabricante de equipamento não está
    // cadastrado
    if (Manufacture::where("contractorid", '=', $this->contractor->id)
          ->where("manufactureid", '=', strtolower($manufactureData['manufacture']))
          ->count() === 0) {
      $manufacture = new Manufacture();

      $manufacture->manufactureid = strtolower($manufactureData['manufacture']);
      $manufacture->name          = $this->normalizeString($manufactureData['name']);
      $manufacture->contractorid  = $this->contractor->id;
      $manufacture->save();
    } else {
      // Precisa atualizar apenas, então recupera a marca de
      // equipamento
      $manufacture = Manufacture::where("contractorid", '=', $this->contractor->id)
                                ->where("manufactureid", '=', strtolower($manufactureData['manufacture']))
                                ->firstOrFail();

      // Atualiza os dados
      $manufacture->name = $this->normalizeString($manufactureData['name']);
      $manufacture->save();
    }
  }
}
