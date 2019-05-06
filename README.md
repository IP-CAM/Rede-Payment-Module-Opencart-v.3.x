# Módulo Opencart 3

Esse módulo é suportado pelas versões 3.0.x e os requisitos são os mesmo da respectiva versão da plataforma.

# Instalação

Esse módulo utiliza o SDK PHP como dependência. Para instalá-lo, no diretório da instalação da sua plataforma Magento, execute:

```bash
composer require developersrede/opencart3
```

Os logs são gerados através do [Monolog](https://github.com/Seldaek/monolog). Caso precise de logs de requisição e resposta, instale `monolog/monolog` como dependência:

```bash
composer require monolog/monolog
```