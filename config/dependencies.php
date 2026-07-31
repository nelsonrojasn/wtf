<?php
// config/dependencies.php

return function (Container $container) {
    // Registramos la clase Db como un servicio único (Singleton)
    $container->singleton(Db::class, function ($c) {
        return new Db();
    });
};
