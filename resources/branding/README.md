# Branding

Coloque a logo principal do painel neste caminho:

- `resources/branding/logo.png`

O painel Filament (incluindo a tela de login) vai ler esse arquivo internamente e embutir a imagem no HTML.
Isso evita dependência de `public/` ou `storage:link` para a logo do login.

Opcionalmente, para a ilustração lateral da tela de login (layout dividido):

- `resources/branding/login-hero.png`

Configure no `.env` / ambiente:

- `FILAMENT_LOGIN_HERO_FILE=resources/branding/login-hero.png`
