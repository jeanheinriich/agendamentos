# Certificados
Para gerar os certificados auto-assinados, utilize o seguinte comando:

```sh
mkcert grupomm.test "*.grupomm.test" www.grupomm.test 127.0.0.2
```

Serão criados dois arquivos:
 - grupomm.test+5-key.pem
 - grupomm.test+5.pem

Estes arquivos são a chave privada e pública do certificado. Copie para a pasta de configuração do NGINX.
