# 💋 Kiss — Keep It Simply Static

Générateur de site statique en PHP. Utilise **Twig** pour les templates, supporte les sources de données YAML/JSON/PHP/XML/INI, la génération de pages avec paramètres dynamiques, la synchronisation de fichiers statiques, et un mode *watch* avec recompilation partielle.

## Prérequis

- Docker & Docker Compose
- `inotify-tools` (pour le mode *watch*, côté host uniquement)

## Démarrage

```sh
make up                          # Lance les containers
make composer -- install         # Installe les dépendances
make kiss -- build               # Construit le site
make kiss -- watch               # Construit + surveille les modifications
```

## Commandes

### Make

| Commande | Description |
|---|---|
| `make up` | Démarre les containers |
| `make down` | Arrête les containers |
| `make shell` | Bash dans le container `app` |
| `make composer -- <args>` | Exécute Composer |
| `make tests` | Lance tous les tests PHPUnit |
| `make test -- <args>` | Test unitaire ciblé (`make test -- tests/KissTest.php`) |
| `make phpcs` | Lint PSR-12 |
| `make phpstan` | Analyse statique niveau 5 |
| `make coverage` | Rapport de couverture HTML (`coverage/index.html`, nécessite `XDEBUG_MODE=coverage`) |
| `make kiss -- <args>` | Exécute le CLI (`src/bin/kiss`) |

> ⚠️ Make capture les flags (`-y`, `--version`). Contournement : `make kiss -- build` ou `make kiss -- --version`.

### CLI (via `make kiss --`)

| Commande | Description |
|---|---|
| `kiss build` | Construit tout le site |
| `kiss watch` | Construit puis surveille les fichiers avec `inotifywait` |
| `kiss reset [all\|dist\|cache]` | Supprime les dossiers `web/` et/ou `tmp/` |
| `kiss route [list\|nom]` | Liste les routes ou reconstruit une route spécifique |
| `kiss copy [chemin]` | Synchronise un fichier depuis `copy/` vers `web/` |
| `kiss test` | Valide la configuration (warmup sans build) |

## Structure du projet

```
kiss.yml        → Configuration du site
├── copy/       → Fichiers statiques (copiés tels quels vers web/)
├── data/       → Données globales (YAML/JSON/PHP/XML/INI)
├── route/      → Définitions de routes (YAML/JSON/PHP/XML/INI)
├── template/   → Templates Twig
├── web/        → Site généré (dist)
└── tmp/        → Cache (résolu vers /tmp/kiss/<hash>)
```

## Configuration

Fichier `kiss.yml` (supporté aussi : `.yaml`, `.php`, `.json`, `.xml`, `.ini`).

```yaml
debug: true
path:
  copy: copy
  data: data
  route: route
  template: template
  dist: web
  cache: tmp
```

Variables d'environnement (prioritaires) :
- `KISS_DEBUG=true`
- `KISS_VERBOSE=true`

## Sources de données

Les fichiers dans `data/` sont chargés automatiquement et disponibles dans Twig via `{{ global.* }}`.

Exemple — `data/site.yml` :
```yaml
name: Mon Site
tagline: Super site statique
```

Dans un template Twig :
```twig
<h1>{{ global.site.name }}</h1>
<p>{{ global.site.tagline }}</p>
```

## Routes

### Page unique

`route/index.yml` :
```yaml
path: /index.html
template: page.twig
data:
  title: Accueil
```

### Pages multiples avec paramètres

`route/blog.yml` :
```yaml
path: /{slug}.html
template: post.twig
data:
  - slug: hello-world
    title: Hello World
  - slug: second-article
    title: Second article
```

### Référence externe ($ref)

```yaml
path: /{slug}.html
template: post.twig
data:
  $ref: data/articles.yml
```

## Fonctionnement interne

- **Route manifest** : `tmp/kiss/route-manifest.php` enregistre les fichiers générés par chaque route ; les fichiers orphelins sont nettoyés à la reconstruction.
- **Template deps** : `tmp/kiss/template-deps.php` trace les dépendances entre templates (extends/include/embed) pour les recompilations partielles.
- **DataTree** : cache les sources de données distantes et locales sous forme de fichiers PHP sérialisés.
- **Watch** : `inotifywait` détecte les modifications et recompile uniquement les routes impactées.

## Tests

```sh
make tests                   # Tous les tests
make test -- --filter=testBuild  # Test spécifique
make coverage                # Rapport HTML
```

Les tests créent des dossiers temporaires dans `sys_get_temp_dir()/kiss-test-*` et les nettoient automatiquement. Tous les tests sont unitaires (pas de tests d'intégration).

## Licence

MIT
