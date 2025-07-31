<?php
/*
 * This file is part of Extension Library.
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
 * Classe responsável pela abstração de uma requisição à um serviço
 * através da API do sistema Fipe.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

/**
 * API Fipe
 * https://deividfortuna.github.io/fipe/
 *
 * Copyright (c) 2015 - Deivid Fortuna <deividfortuna@gmail.com>
 */

namespace App\Providers\Fipe\Services;

use Core\HTTP\AbstractService;
use Core\HTTP\Synchronizer;
use Psr\Log\LoggerInterface;
use RuntimeException;

abstract class FipeService
  extends AbstractService
{
  /**
   * Os dados do contratante.
   *
   * @var object
   */
  protected $contractor;

  /**
   * O construtor de nosso serviço.
   *
   * @param Synchronizer $synchronizer
   *   O sistema para sincronismo dos dados do serviço
   * @param LoggerInterface $logger
   *   O acesso ao sistema de logs
   * @param object $contractor
   *   Os dados do contratante
   */
  public function __construct(Synchronizer $synchronizer,
    LoggerInterface $logger, $contractor)
  {
    // Primeiramente chamamos o constructor do serviço
    parent::__construct($synchronizer, $logger);

    // Armazena os dados do contratante
    $this->contractor = $contractor;
  }
}
