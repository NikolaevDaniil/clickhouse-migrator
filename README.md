# ClickHouse Migrator

Инструмент для управления миграциями ClickHouse на PHP

## Использование

ClickHouse Migrator предоставляет несколько команд для управления миграциями:

Создание новой миграции:
```sh
php bin/migrate clickhouse:migrate:create НазваниеМиграции
```
#### Пример:
```sh
php bin/migrate clickhouse:migrate:create CreateUsersTable
```
После создания файла миграции откройте его и добавьте SQL-код в методы up() и down().
#### Применение миграций:
```sh
php bin/migrate clickhouse:migrate:up
```
Применяет все новые миграции. Перед применением будет запрошено подтверждение.

#### Тихий режим (без подтверждения):
```sh
php bin/migrate clickhouse:migrate:up --quiet
```
#### Откат последней миграции:
```sh
php bin/migrate clickhouse:migrate:down
```
Откатывает последнюю применённую миграцию. Перед откатом будет запрошено подтверждение.

#### Тихий режим (без подтверждения):
```sh
php bin/migrate clickhouse:migrate:down --quiet
```
#### Миграция до определённой версии:
```sh
php bin/migrate clickhouse:migrate:to VERSION
```
#### Пример:
```sh
php bin/migrate clickhouse:migrate:to 3
```
Мигрирует базу данных до указанной версии, применяя или откатывая миграции.
#### Просмотр статуса миграций:
```sh
php bin/migrate clickhouse:migrate:status
```
Отображает список всех миграций с указанием их статуса (применена или нет).