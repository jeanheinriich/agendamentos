<?php
/*
 * Este arquivo é parte do Sistema de ERP do Grupo M&M
 *
 * (c) Grupo M&M
 *
 * Para obter informações completas sobre direitos autorais e licenças,
 * consulte o arquivo LICENSE que foi distribuído com este código-fonte.
 * ---------------------------------------------------------------------
 * Descrição:
 * 
 * A exceção em caso de falha na conferência da igualdade entre dois
 * campos.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Validation\Exceptions;

use Respect\Validation\Exceptions\ValidationException;

class MatchesException
  extends ValidationException
{
  public static $defaultTemplates = [
    self::MODE_DEFAULT => [
      self::STANDARD => '{{name}} não confere com a primeira digitação',
    ],
    self::MODE_NEGATIVE => [
      self::STANDARD => '{{name}} deve conferir com a primeira digitação',
    ],
  ];
}
