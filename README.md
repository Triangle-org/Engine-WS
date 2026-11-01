<p align="center"><a href="https://www.localzet.com" target="_blank">
  <img src="https://static.zorin.space/media/logos/LocalzetGroup.png" width="400">
</a></p>

<p align="center">
  <a href="https://packagist.org/packages/triangle/engine-ws">
  <img src="https://img.shields.io/packagist/dt/triangle/engine-ws?label=%D0%A1%D0%BA%D0%B0%D1%87%D0%B8%D0%B2%D0%B0%D0%BD%D0%B8%D1%8F" alt="Скачивания">
</a>
  <a href="https://github.com/Triangle-org/Engine-WS">
  <img src="https://img.shields.io/github/commit-activity/t/Triangle-org/Engine-WS?label=%D0%9A%D0%BE%D0%BC%D0%BC%D0%B8%D1%82%D1%8B" alt="Коммиты">
</a>
  <a href="https://packagist.org/packages/triangle/engine-ws">
  <img src="https://img.shields.io/packagist/v/triangle/engine-ws?label=%D0%92%D0%B5%D1%80%D1%81%D0%B8%D1%8F" alt="Версия">
</a>
  <a href="https://packagist.org/packages/triangle/engine-ws">
  <img src="https://img.shields.io/packagist/dependency-v/triangle/engine-ws/php?label=PHP" alt="Версия PHP">
</a>
  <a href="https://github.com/Triangle-org/Engine-WS">
  <img src="https://img.shields.io/github/license/Triangle-org/Engine-WS?label=%D0%9B%D0%B8%D1%86%D0%B5%D0%BD%D0%B7%D0%B8%D1%8F" alt="Лицензия">
</a>
</p>

# Triangle Engine WS

Experimental WebSocket application engine for Triangle on Localzet Server. The engine shares the base Engine lifecycle, dispatches routes on the upgrade path, and invokes route callbacks for incoming frames.

The current WS application template runs one process. Its broadcast helpers are process-local; the package does not provide inter-process rooms, a durable broker, or horizontal fan-out. HTTP Engine and WS Engine declare a Composer conflict and belong to separate application tracks.

## Проверка

```bash
composer install
composer test
```
