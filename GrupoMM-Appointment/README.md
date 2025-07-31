# TrackerERP

Este é o aplicativo de ERP do Grupo M&M.

## Começando

Essas instruções fornecerão uma cópia do projeto em funcionamento em sua
máquina local para fins de desenvolvimento e teste.

```
composer update
```

* Aponte o documento raiz do seu host para o diretório `public/` do aplicativo.
* Certifique-se de que `var` seja gravado na web.
* Certifique-se de que `var/log/` seja gravado na web.
* Certifique-se de que `var/cache/` seja gravado na web.
* Certifique-se de que `var/storage/` seja gravado na web.
* Certifique-se de que `var/storage/attachments/` seja gravado na web.
* Certifique-se de que `var/storage/images/` seja gravado na web.

# Download de bibliotecas do lado do cliente

Essas instruções farão o download das bibliotecas necessárias
para execução do aplicativo no lado do cliente.

```
npm install
```
Você precisará compilar Semantic UI

```
cd assets/library/semantic-ui/
gulp build
```

Uma vez compilado, você precisa instalar os demais componentes

```
cd ../../..
gulp
```

Para executar o aplicativo em desenvolvimento, você também pode executar este comando:

	composer start

Execute este comando para executar o conjunto de testes

	composer test
