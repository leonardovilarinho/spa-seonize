# SPA PHP SEO

> Repositório para escrever um SEO no servidor PHP para aplicações Vue, Angular ou React.

Para usar, basta inserir o arquivo `index.php` no seu projeto, na primeira linha do arquivo, coloque a localicação de dois arquivos:

- `library.json`: arquivo que irá informar as rotas da sua aplicação para o PHP;
- `index.html`: arquivo de entrada do SPA, arquivo compilado pelo Vue, React ou Angular.

Por exemplo: 
```php
<?php

// para arquivos localizados na mesma pasta do index.php
run('./library.json', './index.html');
```

Após isso, no `index.html` adicione a anotação `#meta-data#` dentro da tag `head` do documento. Assim o script PHP saberá onde exatamente deve colocar o SEO.

## Configurando o servidor

Seu servidor PHP deve estar disposto a trabalhar com URL amigáveis, para redirecionar todo o conteúdo da aplicação para o `index.php`, segue um exemplo de configuração
no Nginx:

```
server {
  listen 80;
  root /caminho/do/projeto;
  index index.php index.html;
  server_name site.local;

  location / {
    try_files $uri $uri/ @rewrites;
  }

  location @rewrites {
    rewrite ^/(.*)$ /index.php last;
  }

  location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/var/run/php/php7.2-fpm.sock;
  }

  location ~ /\.ht {
    deny all;
  }
}

```

## Entendendo o `library.json`

Nesse arquivo JSON você deve colocar os dados gerais da aplicação, suas rotas com a configuração de SEO:

```json
{
  "_name": "My Project",
  "_404": "/not-found",
  "pages": [
    {
      "name": "home",
      "route": "/",
      "meta": {
        "title": "Home Page",
        "description": "This is the home page"
      }
    },

    {
      "name": "notfound",
      "route": "/not-found",
      "meta": {
        "title": "Page not founded",
        "description": "This is not founded"
      }
    },

    {
      "name": "city",
      "route": "/city/:cep",
      "meta": {
        "title": "Cidade: $localidade$",
        "description": "Endereço: $logradouro$, $complemento$ - $bairro$ - $localidade$/$uf$",
        "_request": {
          "url": "https://viacep.com.br/ws/:cep/json",
          "data": {
            "cep": ":cep",
            "test": true
          }
        }
      }
    }
  ]
}
```

Nele, temos os atributos principais:

- `_name`: para indicar o nome do sistema, ele será concatenado no fim do título de cada página;
- `_404`: para indicar qual rota irá apresentar a página de erro 404, assim podemos redirecionar para ela quando não econtrar nada;
- `pages`: armazena o array de rotas do sistema. Onde cada rota poderá ter os atributos:
  - `name`: nome da rota;
  - `route`: caminho da rota, você pode usar `:param` para indicar parâmetros de uma rota dinâmica (como no Vue Router);
  - `meta`: metadados da rota, com nome e valor do mesmo;


## SEO estático

Podemos fazer o SEO para páginas estáticas simplesmente colocando os dados do mesmo dentro do atributo `meta` da rota, por exemplo para a página inicial:

```json
{
  "name": "home",
  "route": "/",
  "meta": {
    "title": "Home Page",
    "description": "This is the home page"
  }
}
```

## SEO dinâmico

Muitas aplicações fazem uso de requisições AJAX para montar seu conteúdo, podemos criar o SEO dessas páginas também. Para isso você terá de informar dentro do atributo
`meta` da rota os dados da requisição AJAX, em `_request`:

```json
{
  "name": "city",
  "route": "/city/:cep",
  "meta": {
    "title": "Cidade: $localidade$",
    "description": "Endereço: $logradouro$, $complemento$ - $bairro$ - $localidade$/$uf$",
    "_request": {
      "url": "https://viacep.com.br/ws/:cep/json",
      "data": {
        "cep": ":cep",
        "test": true
      }
    }
  }
}
```

O atributo `_request` deve ter:

- `url`: indicando o endpoint onde ele deve buscar o recurso requerido na página;

Esse atributo também pode ter opcionalmente as opções:

- `data`: para informar os dados que devemos enviar para a API;
- `post`: para informar que devemos fazer uma requisição do método POST e não GET. Use: `"post": true`;

**Notas:**

- Veja que podemos usar parâmetros dentro da URL ou do `data` da request, assim o script PHP irá trocar o parâmetro pelo valor indicado na rota do SPA, no
exemplo acima, a request enviada seria (para o CEP 38300970, ou rota, /city/38300970):

```
GET https://viacep.com.br/ws/38300970/json

{
  "cep": "38300970",
  "test": true
}
```

- Podemos montar o SEO com o retorno da request, para isso basta indicarmos entre cifrões ($) o dado a ser mostrado ali, por exemplo, a request dessa api de CEP me retorna:

```json
{
  "cep": "38300-970",
  "logradouro": "Avenida Nove",
  "complemento": "670",
  "bairro": "Centro",
  "localidade": "Ituiutaba",
  "uf": "MG",
  "unidade": "",
  "ibge": "3134202",
  "gia": ""
}
```

Logo, usarei `$localidade$` para mostrar a cidade no título, e assim por diante...

## Poupando requests no seu SPA

Toda vez que o usuário entrar diretamente em uma rota dinâmica, como `/city/38300970`, o PHP já fará a request para buscar o CEP, então podemos poupar outra request no nosso
framework de frontend para montar o layout. Só de usar esse script desse repositório, já será montada uma váriavel `window.phpseo_rota` onde `rota` será o nome da rota atual,
exemplo `window.phpseo_city` para a rota `/city/38300970`, armazenado o retorno da requisição:

```json
{
  "cep": "38300-970",
  "logradouro": "Avenida Nove",
  "complemento": "670",
  "bairro": "Centro",
  "localidade": "Ituiutaba",
  "uf": "MG",
  "unidade": "",
  "ibge": "3134202",
  "gia": ""
}
```

Assim, você no Vue, React ou Angular, no componente da página, você poderá verificar a existência dessa variável para poder puxa-la para o framework. Usando algo mais ou
menos assim (no Vue):

```javascript
mounted () {
  if ('phpseo_city' in window) {
    this.city = window.phpseo_city
  } else [
    // faça a request do cep novamente aqui, assim quando o usuário não acessar o link diretamente ele irá buscar pelo seu framework front-end
  ]
}
```

**Nota:** como você deve ter pensado, você terá de usar o `vue-head` ou `vue-meta` para ter a certeza de que os títulos serão trocados dinamicamente no seu SPA, dado que
o script PHP só será executando quando o usuário acessar a rota diretamente pela URL ou quando um indexador acessar.
