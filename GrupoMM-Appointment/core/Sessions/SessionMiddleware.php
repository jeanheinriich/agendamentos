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
 * Esta classe de middleware inicia uma sessão segura e a criptografa se
 * a chave de criptografia estiver definida. O caminho do cookie de
 * sessão, domínio e valores de segurança são configurados
 * automaticamente por padrão.
 *
 * O suporte a sessão permite que se armazene dados entre solicitações
 * deste aplicativo. A cada visitante/usuário que acessa o website é
 * atribuída uma identificação única, chamada identificação de sessão.
 * Quando este visitante acessa o site, será verificado na sua requisição
 * se esta possui um cookie com o id específico da sessão. Se este for o
 * caso, o ambiente previamente salvo é recriado, senão um novo ambiente
 * é instanciado.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Sessions;

use Core\Middlewares\Middleware;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class SessionMiddleware
  extends Middleware
{
  /**
   * As opções de configuração de uma sessão
   * 
   * @var array
   */
  protected $options = [
    // O prefixo do nome do cookie onde é armazenado as informações de
    // sessão
    'prefix'    => 'APP',

    // O sufixo do nome do cookie onde é armazenado as informações de
    // sessão
    'suffix'    => 'Data',

    // O tempo de vida do cookie de sessão (em minutos). Padrão: 2h
    'lifetime'  => 60*2*1,

    // Estende automaticamente o tempo de vida da sessão
    'autorefresh' => true,

    // O caminho onde os arquivos de sessão são armazenados. Caso o
    // valor seja nulo, o caminho padrão do PHP será utilizado
    'save_path' => null,

    // Definir o nome, caminho do cookie da sessão, domínio e segurança
    // automaticamente
    'cookie_autoset' => true,

    // Definir o nome, caminho do cookie da sessão, domínio e segurança
    // manualmente
    'cookie_name' => 'Session',
    'path'      => '/',
    'secure'    => false,
    'httponly'  => true,

    // Criptografa os dados da sessão se a chave estiver definida
    'salt'      => null
  ];

  /**
   * O construtor do nosso middleware.
   * 
   * @param ContainerInterface $container
   *   A estrutura que contém os containers da aplicação
   */
  public function __construct(ContainerInterface $container)
  {
    parent::__construct($container);

    if ($this->has('settings')) {
      $sessionOptions = $this->settings['session'];

      // Faz o merge das configurações definidas na configuração com as
      // configurações padrão
      if (is_array($sessionOptions)) {
        $this->options = array_merge($this->options, $sessionOptions);
      }
    }
  }

  /**
   * A função executada sempre que o middleware for chamado.
   * 
   * @param ServerRequestInterface $request
   *   A requisição HTTP
   * @param ResponseInterface $response
   *   A resposta HTTP
   * @param callable $next
   *   O próximo middleware
   * 
   * @return ResponseInterface
   */
  public function __invoke(ServerRequestInterface $request,
    ResponseInterface $response, callable $next)
  {
    // Determina o estado da sessão
    switch (session_status()) {
      case PHP_SESSION_ACTIVE:
        // Não tenta inicializar novamente a sessão, pois a mesma já foi
        // inicializada anteriormente
        
        break;
      case PHP_SESSION_DISABLED:
        // Não tenta inicializar a sessão, pois está desabilitada. Então
        // gera uma exceção
        $this->error("A sessão não pode ser inicializada. PHP_SESSION "
          . "está desabilitado nas configurações do PHP."
        );

        throw new RuntimeException(
          "A sessão não pode ser inicializada."
        );

        break;
      default:
        // Inicializa a sessão
        $this->start($request);

        break;
    }

    if ($this->has('flash')) {
      $this->flash->changeToSessionStorage();
    }

    // Prossegue normalmente
    return $next($request, $response);
  }

  /**
   * Inicializa a seção do aplicativo.
   * 
   * @param ServerRequestInterface $request
   *   A requisição HTTP
   * 
   * @return void
   */
  protected function start(ServerRequestInterface $request) {
    // Recupera as informações do domínio
    $uri = $request->getUri();
    $domain = $uri->getHost();

    // Recupera as configurações
    $options = $this->options;

    if ($this->options['cookie_autoset'] === true) {
      // Recupera as configurações à partir da requisição
      $path = $uri->getPath();
      $app = trim($this->getApplication($path), '/');
      $options['path']   = '/' . $app;
      $options['secure'] = $uri->getScheme() === 'https'
        ? true
        : false
      ;
      $cookieName = ($app === '')
        ? 'Site'
        : strtoupper($app)
      ;
    } else {
      // Recupera o nome da configuração
      $cookieName = $options['cookie_name'];
    }

    // Determina o nome do cookie de sessão
    $cookieName = $options['prefix'] . '_' . $cookieName . '_'
      . $options['suffix']
    ;

    // Habilita configurações de segurança do gerenciamento da sessão

    // Habilita o modo rigoroso. Devido à implementação de cookies HTTP,
    // é fácil criar cookies imutáveis/inelegíveis através de injeções
    // JavaScript. Uma única vulnerabilidade de injeção JavaScript ou
    // modificação de armazenamento de cookies via acesso físico ao
    // cliente permite que os atacantes roubem sessões de usuário para
    // sempre quando o modo rigoroso não está ativo.
    ini_set('session.use_strict_mode', 1);

    // Usar cookies e somente cookies para armazenar a identificação da
    // sessão
    ini_set('session.use_cookies', 1);
    ini_set('session.use_only_cookies', 1);

    // Desabilita o gerenciamento de sessão baseado em URL, já que o
    // mesmo tem riscos de segurança adicionais em comparação com o
    // gerenciamento de sessão baseado em cookies (que é o que esta
    // classe utiliza). Se utilizar o gerenciamento de sessão baseado em
    // URL, os usuários poderiam enviar uma URL que contivesse uma
    // identificação de sessão ativa para seus amigos por e-mail ou os
    // usuários poderiam salvar um URL que contivesse uma identificação
    // de sessão em seus favoritos e acessar o site sempre com a mesma
    // identificação de sessão, por exemplo. Neste caso, esta flag
    // desabilita a inserção do ID de sessão nos links automaticamente,
    // impedindo este comportamento.
    ini_set('session.use_trans_sid', 0);

    // Lida com o tempo de vida do cookie de sessão
    if (is_string($options['lifetime'])) {
      // Se o tempo de vida do cookie é uma string, converte-o em um
      // valor em segundos
      $options['lifetime'] = strtotime($options['lifetime']) - time();
    } else {
      // Se o tempo de vida do cookie é um inteiro, então converte-o em
      // um valor em segundos, já que este valor é expresso em minutos
      $options['lifetime'] *= 60;
    }

    // Número definido de segundos após os quais os dados armazenados na
    // sessão serão vistos como lixo e descartados
    if ($options['lifetime'] > 0) {
      ini_set('session.gc_maxlifetime', $options['lifetime']);
    }

    // Definir o caminho onde os cookies de sessão serão armazenados
    if (is_string($options['save_path'])) {
      if (!is_writable($options['save_path'])) {
        throw new RuntimeException(
          'O caminho para armazenar os dados da sessão não pode ser '
          . 'escrito.'
        );
      }

      ini_set('session.save_path', $options['save_path']);
    }

    // Definir a força do id de sessão
    if (version_compare(PHP_VERSION, '7.1', '<')) {
      // Se a verão do PHP for menor do que a versão 7.1

      // O entropy_length permite especificar o comprimento da cadeia de
      // identificação da sessão. O comprimento do ID da sessão pode ser
      // entre 22 e 256. O padrão é 32. A hash_function determina qual a
      // função de hash será utilizada.
      ini_set('session.entropy_file', '/dev/urandom');
      ini_set('session.entropy_length', 128);
      ini_set('session.hash_function', 'sha512');
    } else {
      // Se a verão do PHP for maior ou igual a versão 7.1
      
      // O sid_length permite especificar o comprimento da cadeia de
      // identificação da sessão. O comprimento do ID da sessão pode ser
      // entre 22 e 256. O padrão é 32. Se você precisar de
      // compatibilidade, você pode especificar 32, 40, etc. Um ID de
      // sessão mais longo é mais difícil de adivinhar. Pelo menos 32
      // caracteres são recomendados.
      ini_set('session.sid_length', 128);
    }

    // session.cache_limiter especifica o método de controle de cache
    // usado para páginas de sessão. Pode ser um dos seguintes valores:
    // nocache, privado, privado_no_expire, ou público. O padrão é
    // nocache.
    session_cache_limiter('nocache');

    // Define o nome do cookie de sessão
    session_name($cookieName);

    // Define parâmetros dos cookies
    session_set_cookie_params(
      $options['lifetime'],
      $options['path'],
      $domain,
      $options['secure'],
      $options['httponly']
    );

    // Define a criptografia da sessão, se definido
    if (is_string($options['salt'])) {
      // Adicionar o agente de usuário HTTP à chave de criptografia para
      // fortalecer a criptografia
      $encryptionKey = $options['salt']
        . md5($request->getHeaderLine('HTTP_USER_AGENT'))
      ;

      // Define um novo manipulador de sessão que permite a criptografia
      $handler = new SecureSessionHandler($encryptionKey);
      session_set_save_handler($handler, true);
    }

    // Inicia a sessão
    if (@session_start()) {
      $this->debug("Sessão iniciada");
    } else {
      throw new RuntimeException('A sessão não pode ser iniciada.');
    }

    // Estende o tempo de vida da sessão
    if ($options['autorefresh'] === true && isset($_COOKIE[$cookieName])) {
      // Grava nosso cookie segundo as novas recomendações de
      // sameSite
      if (PHP_VERSION_ID < 70300) {
        setcookie(
          $cookieName,
          $_COOKIE[$cookieName],
          time() + $options['lifetime'],
          "{$options['path']}; sameSite=Lax",
          $domain,
          $options['secure'],
          $options['httponly'])
        ;
      } else {
        setcookie(
          $cookieName,
          $_COOKIE[$cookieName],
          [ 'expires' => time() + $options['lifetime'],
            'path' => $options['path'],
            'domain' => $domain,
            'samesite' => 'Lax',
            'secure' => $options['secure'],
            'httponly' => $options['httponly']
          ])
        ;
      }
      $this->debug("Atualizado o tempo de vida desta sessão");
    }
  }
}
